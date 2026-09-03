<?php

$database = getenv('DAGRIL_DB_DATABASE') ?: '';
if (!preg_match('/_(test|testing)$/i', $database)) {
    fwrite(STDERR, "GARDE-FOU: DAGRIL_DB_DATABASE doit désigner une base de test.\n");
    exit(2);
}

require_once dirname(__DIR__) . '/app/helpers/functions.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Model.php';

class Auth
{
    public static $currentSiteId;
    public static $butcherySiteId;
    public static $consolidated = false;

    public static function currentSiteId() { return self::$currentSiteId; }
    public static function canViewConsolidated() { return self::$consolidated; }
    public static function canAccessSite($siteId) { return (int) $siteId === (int) self::$butcherySiteId; }
    public static function requireCurrentSite() { return self::$currentSiteId; }
    public static function requireSiteAccess($siteId) {
        if (!self::canAccessSite($siteId)) { throw new RuntimeException('Accès refusé.'); }
    }
}

require_once dirname(__DIR__) . '/app/models/Butchery.php';

$db = Database::getInstance()->connection();
Auth::$butcherySiteId = (int) $db->query("SELECT id FROM sites WHERE code='BOUCH' AND status='active' AND deleted_at IS NULL LIMIT 1")->fetchColumn();
Auth::$currentSiteId = (int) $db->query("SELECT id FROM sites WHERE code<>'BOUCH' AND status='active' AND deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
if (!Auth::$butcherySiteId || !Auth::$currentSiteId) {
    throw new RuntimeException('Sites de test requis absents.');
}

$model = new Butchery();
$model->pendingReceipts();
if ($model->hasOperationalSiteContext()) {
    throw new RuntimeException('Un autre site ne doit pas devenir un contexte opérationnel BOUCH.');
}
echo "OK  - consultation BOUCH depuis un autre contexte autorisé\n";

Auth::$currentSiteId = null;
Auth::$consolidated = true;
$model->pendingReceipts();
if ($model->hasOperationalSiteContext()) {
    throw new RuntimeException('La vue consolidée ne doit pas autoriser les écritures BOUCH.');
}
echo "OK  - consultation BOUCH consolidée sans autorisation implicite d’écriture\n";

