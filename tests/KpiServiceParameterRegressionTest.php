<?php

$database = getenv('DAGRIL_DB_DATABASE') ?: '';
if (!preg_match('/_(test|testing)$/i', $database)) {
    fwrite(STDERR, "GARDE-FOU: DAGRIL_DB_DATABASE doit désigner une base de test.\n");
    exit(2);
}

class Auth
{
    public static function can($component, $action, $siteId = null) { return true; }
    public static function canViewConsolidated() { return false; }
    public static function canAccessSite($siteId) { return true; }
    public static function currentSiteId() { return 1; }
}

$config = require dirname(__DIR__) . '/config/database.php';
$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $config['host'],
    $config['port'],
    $config['database'],
    $config['charset']
);
$pdo = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

require_once dirname(__DIR__) . '/app/services/KpiService.php';

$siteId = (int) $pdo->query("SELECT id FROM sites WHERE status='active' AND deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
if ($siteId < 1) {
    throw new RuntimeException('Aucun site actif dans la base de test.');
}

$service = new KpiService($pdo, [
    'site_id' => (string) $siteId,
    'start_date' => '2000-01-01',
    'end_date' => '2099-12-31',
]);

foreach (['breakage_rate', 'open_nonconformities'] as $code) {
    $rows = $service->detail($code);
    echo 'OK  - ' . $code . ' exécuté avec filtre site (' . count($rows) . " ligne(s))\n";
}

$summary = $service->summary();
foreach ($summary as $kpi) {
    if (!isset($kpi['chart']['type'], $kpi['chart']['labels'], $kpi['chart']['values'])) {
        throw new RuntimeException('Série graphique absente pour ' . $kpi['code']);
    }
}
echo 'OK  - séries graphiques générées pour ' . count($summary) . " KPI autorisés\n";
