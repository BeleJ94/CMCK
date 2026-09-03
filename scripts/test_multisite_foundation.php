<?php

require dirname(__DIR__) . '/app/helpers/functions.php';
require dirname(__DIR__) . '/app/core/Database.php';
require dirname(__DIR__) . '/app/core/Model.php';
require dirname(__DIR__) . '/app/core/Auth.php';
require dirname(__DIR__) . '/app/models/Site.php';
require dirname(__DIR__) . '/app/models/Silo.php';

Auth::start();
ob_start();
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'MultisiteFoundationTest';
$db = Database::getInstance()->connection();
$failures = [];
$testSiteId = null;
$testSiloId = null;
$testUserId = null;
$originalAssignments = [];
$originalDefault = null;

function ms_assert($condition, $message)
{
    global $failures;
    echo ($condition ? 'OK  - ' : 'FAIL - ') . $message . PHP_EOL;
    if (!$condition) { $failures[] = $message; }
}

function ms_query(PDO $db, $sql, array $params = [])
{
    $statement = $db->prepare($sql); $statement->execute($params); return $statement;
}

try {
    $admin = ms_query($db, "SELECT users.*, roles.name role_name, roles.slug role_slug FROM users INNER JOIN roles ON roles.id = users.role_id WHERE roles.slug = 'administrateur' AND users.status = 'active' AND users.deleted_at IS NULL LIMIT 1")->fetch();
    $direction = ms_query($db, "SELECT users.*, roles.name role_name, roles.slug role_slug FROM users INNER JOIN roles ON roles.id = users.role_id WHERE roles.slug = 'direction' AND users.status = 'active' AND users.deleted_at IS NULL LIMIT 1")->fetch();
    $fieldUser = ms_query($db, "SELECT users.*, roles.name role_name, roles.slug role_slug FROM users INNER JOIN roles ON roles.id = users.role_id WHERE roles.slug NOT IN ('administrateur','direction') AND users.status = 'active' AND users.deleted_at IS NULL LIMIT 1")->fetch();
    if (!$admin || !$direction || !$fieldUser) { throw new RuntimeException('Utilisateurs de test requis introuvables.'); }

    Auth::login($admin); $_SESSION['current_site_id'] = 'all';
    $model = new Site();
    $typeId = (int) ms_query($db, "SELECT id FROM site_types WHERE code = 'DEPOT' LIMIT 1")->fetchColumn();
    $testSiteId = $model->createSite(['site_type_id' => $typeId, 'code' => 'TST-MS', 'name' => 'Site test multisite', 'description' => 'Test automatique', 'status' => 'active'], $admin);
    ms_assert($testSiteId > 0, 'Creation d un site');
    $model->updateSite($testSiteId, ['site_type_id' => $typeId, 'code' => 'TST-MS', 'name' => 'Site test multisite modifie', 'description' => 'Test automatique', 'status' => 'active'], $admin);
    ms_assert($model->findDetailed($testSiteId)['name'] === 'Site test multisite modifie', 'Modification d un site');

    $testUserId = (int) $fieldUser['id'];
    $original = ms_query($db, "SELECT site_id, is_default FROM user_sites WHERE user_id = ? AND status = 'active' AND deleted_at IS NULL", [$testUserId])->fetchAll();
    $originalAssignments = array_map('intval', array_column($original, 'site_id'));
    foreach ($original as $row) { if ($row['is_default']) { $originalDefault = (int) $row['site_id']; } }
    $model->syncUserSites($testUserId, [$testSiteId], $testSiteId, $admin);
    ms_assert((int) ms_query($db, "SELECT COUNT(*) FROM user_sites WHERE user_id = ? AND site_id = ? AND status = 'active' AND deleted_at IS NULL", [$testUserId, $testSiteId])->fetchColumn() === 1, 'Affectation d un utilisateur');

    $rawProductId = (int) ms_query($db, "SELECT id FROM products WHERE category = 'raw_material' AND deleted_at IS NULL LIMIT 1")->fetchColumn();
    ms_query($db, "INSERT INTO silos (site_id, name, code, product_id, capacity_kg, current_stock_kg, alert_threshold_kg, status) VALUES (?, 'Silo test multisite', 'TST-MS-SILO', ?, 1000, 100, 10, 'active')", [$testSiteId, $rawProductId]);
    $testSiloId = (int) $db->lastInsertId();

    Auth::login($fieldUser); Auth::selectSite((string) $testSiteId);
    $visible = array_column((new Silo())->allWithStats(), 'id');
    ms_assert(in_array($testSiloId, array_map('intval', $visible), true), 'Donnees du site autorise visibles');
    $otherSiteId = (int) ms_query($db, "SELECT id FROM sites WHERE code = 'SILO' LIMIT 1")->fetchColumn();
    $refused = false;
    try { Auth::selectSite((string) $otherSiteId); } catch (Exception $exception) { $refused = true; }
    ms_assert($refused, 'Refus d un acces a un site non autorise');

    Auth::login($direction); Auth::selectSite('all');
    $consolidated = array_column((new Silo())->allWithStats(), 'id');
    ms_assert(in_array($testSiloId, array_map('intval', $consolidated), true), 'Vue consolidee de la Direction');
} finally {
    Auth::login($admin ?: []); $_SESSION['current_site_id'] = 'all';
    if ($testUserId && $originalAssignments) {
        (new Site())->syncUserSites($testUserId, $originalAssignments, $originalDefault ?: $originalAssignments[0], $admin);
    }
    if ($testSiloId) { ms_query($db, "DELETE FROM silos WHERE id = ?", [$testSiloId]); }
    if ($testSiteId) {
        ms_query($db, "DELETE FROM activity_logs WHERE site_id = ? OR user_agent = 'MultisiteFoundationTest'", [$testSiteId]);
        ms_query($db, "DELETE FROM user_sites WHERE site_id = ?", [$testSiteId]);
        ms_query($db, "DELETE FROM sites WHERE id = ?", [$testSiteId]);
    }
}

if ($failures) { echo count($failures) . " echec(s) multisite." . PHP_EOL; ob_end_flush(); exit(1); }
echo "Tous les tests multisites sont conformes." . PHP_EOL;
ob_end_flush();
