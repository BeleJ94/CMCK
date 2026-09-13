<?php
/** Run: php tests/SiloAdministrationIntegrationTest.php
 * All writes use connection-local TEMPORARY tables; no application data is changed.
 */
require_once dirname(__DIR__) . '/app/core/Model.php';
require_once dirname(__DIR__) . '/app/models/Silo.php';

class Auth
{
    public static $site = null;
    public static $permissions = [1, 2];
    public static function currentSiteId() { return self::$site; }
    public static function sites() { return [['id' => 1, 'name' => 'Site A', 'code' => 'A'], ['id' => 2, 'name' => 'Site B', 'code' => 'B']]; }
    public static function canAccessSite($id) { return in_array((int) $id, [1, 2], true); }
    public static function can($component, $action, $site = null) { return $component === 'silos' && $action === 'administer' && in_array((int) $site, self::$permissions, true); }
    public static function requireSiteAccess($id) { if (!self::canAccessSite($id)) { throw new RuntimeException('Site refusé.'); } }
    public static function requirePermission($component, $action, $id) { if (!self::can($component, $action, $id)) { throw new RuntimeException('Permission refusée.'); } }
    public static function siteClause($column, array &$params, $parameter = 'scope_site_id') {
        if (self::$site === null) { return ''; }
        $params[$parameter] = self::$site;
        return ' AND ' . $column . ' = :' . $parameter;
    }
}
class SiloTestModel extends Silo { public function __construct(PDO $db) { $this->db = $db; } }
function silo_check($ok, $message) {
    global $checks;
    if (!$ok) { throw new RuntimeException('ÉCHEC : ' . $message); }
    $checks++; echo 'OK - ' . $message . PHP_EOL;
}
function silo_reject($callback, $message) {
    $rejected = false;
    try { $callback(); } catch (RuntimeException $exception) { $rejected = true; }
    silo_check($rejected, $message);
}
$c = require dirname(__DIR__) . '/config/database.php';
$db = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $c['host'], $c['port'], $c['database'], $c['charset']), $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
$db->exec("CREATE TEMPORARY TABLE sites (id INT PRIMARY KEY, status VARCHAR(20), deleted_at DATETIME NULL, name VARCHAR(120), code VARCHAR(50)) ENGINE=InnoDB");
$db->exec("CREATE TEMPORARY TABLE products (id INT PRIMARY KEY, name VARCHAR(120), category VARCHAR(30), status VARCHAR(20), deleted_at DATETIME NULL) ENGINE=InnoDB");
$db->exec("CREATE TEMPORARY TABLE silos (id INT AUTO_INCREMENT PRIMARY KEY, site_id INT, name VARCHAR(120), code VARCHAR(80) UNIQUE, product_id INT NULL, capacity_kg DECIMAL(12,3), current_stock_kg DECIMAL(12,3), unit VARCHAR(30), alert_threshold_kg DECIMAL(12,3), status VARCHAR(20), deleted_at DATETIME NULL) ENGINE=InnoDB");
$db->exec("CREATE TEMPORARY TABLE activity_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, site_id INT, action VARCHAR(60), module VARCHAR(60), entity_type VARCHAR(60), entity_id INT, description TEXT, old_values JSON, new_values JSON, ip_address VARCHAR(45), user_agent VARCHAR(255)) ENGINE=InnoDB");
$db->exec("INSERT INTO sites VALUES (1,'active',NULL,'Site A','A'),(2,'active',NULL,'Site B','B'),(3,'active',NULL,'Site C','C')");
$db->exec("INSERT INTO products VALUES (1,'Maïs','raw_material','active',NULL),(2,'Blé','raw_material','active',NULL),(3,'Farine','finished_product','active',NULL)");
$model = new SiloTestModel($db); $checks = 0; $actor = ['id' => 7];
$data = ['name' => 'Silo test', 'code' => 'test-01', 'site_id' => '1', 'product_id' => '1', 'capacity_kg' => '1000.000', 'alert_threshold_kg' => '100'];
$id = $model->saveSilo(array_merge($data, ['current_stock_kg' => '999', 'status' => 'inactive', 'unit' => 'tonnes']), $actor);
$row = $model->administrationRecord($id);
silo_check($row['code'] === 'TEST-01' && (float) $row['current_stock_kg'] === 0.0 && $row['status'] === 'active' && $row['unit'] === 'kg', 'création à stock zéro, statut actif et unité kg non falsifiables');
silo_check((int) $db->query('SELECT site_id FROM activity_logs LIMIT 1')->fetchColumn() === 1, 'journal rattaché au site réel en vue consolidée');
silo_reject(function () use ($model, $data, $actor) { $model->saveSilo($data, $actor); }, 'code dupliqué refusé');
foreach ([['capacity_kg' => '0'], ['capacity_kg' => '-1'], ['capacity_kg' => '1e3'], ['capacity_kg' => '1000000000'], ['capacity_kg' => '1.0001'], ['alert_threshold_kg' => '1001'], ['alert_threshold_kg' => '-1'], ['name' => ''], ['code' => 'a b'], ['site_id' => ''], ['product_id' => '3'], ['name' => []]] as $invalid) {
    silo_reject(function () use ($model, $data, $invalid, $actor) { $model->saveSilo(array_merge($data, $invalid), $actor); }, 'saisie invalide refusée : ' . json_encode($invalid));
}
$db->exec("UPDATE silos SET current_stock_kg = 500 WHERE id = $id");
foreach ([['capacity_kg' => '400'], ['site_id' => '2'], ['product_id' => '2'], ['product_id' => '']] as $invalid) {
    silo_reject(function () use ($model, $id, $data, $invalid, $actor) { $model->saveSilo(array_merge($data, $invalid), $actor, $id); }, 'stock protégé : ' . json_encode($invalid));
}
silo_reject(function () use ($model, $id, $actor) { $model->changeStatus($id, 'inactive', $actor); }, 'désactivation d’un silo rempli refusée');
$model->saveSilo(array_merge($data, ['name' => 'Nouveau nom', 'capacity_kg' => '1500']), $actor, $id);
silo_check((float) $model->administrationRecord($id)['current_stock_kg'] === 500.0, 'modification des caractéristiques sans modification du stock');
$db->exec("UPDATE silos SET current_stock_kg = 0 WHERE id = $id");
$model->changeStatus($id, 'inactive', $actor);
$count = (int) $db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();
$model->changeStatus($id, 'inactive', $actor);
silo_check($model->administrationRecord($id)['status'] === 'inactive' && (int) $db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn() === $count, 'désactivation répétée idempotente');
$model->changeStatus($id, 'active', $actor);
silo_check($model->administrationRecord($id)['status'] === 'active', 'réactivation');
silo_reject(function () use ($model, $id, $actor) { $model->changeStatus($id, 'cancelled', $actor); }, 'statut arbitraire refusé');
$model->saveSilo(array_merge($data, ['site_id' => '2', 'product_id' => '2']), $actor, $id);
silo_check((int) $model->administrationRecord($id)['site_id'] === 2, 'silo vide réaffecté au site et produit autorisés');
Auth::$site = 1;
silo_check(count($model->administrationRows()) === 0, 'liste filtrée par site courant');
silo_reject(function () use ($model, $id, $actor) { $model->changeStatus($id, 'inactive', $actor); }, 'écriture hors contexte courant refusée');
Auth::$site = null; Auth::$permissions = [1];
silo_check(count($model->administrationRows()) === 0 && count($model->administrationSites()) === 1, 'droits par site appliqués à la liste et au formulaire');
silo_reject(function () use ($model, $id) { $model->administrationRecord($id); }, 'lecture administrative sans permission refusée');
silo_reject(function () use ($model, $data, $actor) { $model->saveSilo(array_merge($data, ['code' => 'NEW', 'site_id' => '2']), $actor); }, 'création sur site sans permission refusée');
silo_reject(function () use ($model, $data, $actor) { $model->saveSilo(array_merge($data, ['code' => 'NEW', 'site_id' => '3']), $actor); }, 'site non affecté refusé');
Auth::$permissions = [1, 2];
$db->exec("UPDATE sites SET status='inactive' WHERE id=1");
silo_reject(function () use ($model, $data, $actor) { $model->saveSilo(array_merge($data, ['code' => 'NEW']), $actor); }, 'site inactif refusé');
$db->exec("UPDATE sites SET status='active' WHERE id=1");
silo_reject(function () use ($model, $data) { $model->saveSilo(array_merge($data, ['code' => 'ROLLBACK']), ['id' => null]); }, 'échec de journalisation remonte une erreur');
silo_check((int) $db->query("SELECT COUNT(*) FROM silos WHERE code='ROLLBACK'")->fetchColumn() === 0 && !$db->inTransaction(), 'échec de journalisation annule la création entière');
silo_check((int) $db->query('SELECT COUNT(*) FROM silos')->fetchColumn() === 1, 'aucun silo parasite après les refus');
echo $checks . " vérifications réussies ; tables temporaires supprimées à la déconnexion.\n";
