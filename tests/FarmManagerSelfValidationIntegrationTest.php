<?php
/**
 * Run on a disposable database created by scripts/setup_porcine_flow_test.php,
 * after applying migration 039. All fixtures below are fictional and isolated.
 */
if (!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D', getenv('DAGRIL_DB_DATABASE') ?: '')) {
    throw new RuntimeException('Base jetable setup_porcine_flow_test.php obligatoire.');
}
require_once __DIR__ . '/integration/TestBootstrap.php';
$db = DagrilIntegrationTest::boot();
if (!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D', (string) $db->query('SELECT DATABASE()')->fetchColumn())) {
    throw new RuntimeException('La connexion ne cible pas une base jetable autorisée.');
}
require_once dirname(__DIR__) . '/app/models/Agriculture.php';

function farmManagerQuery($sql, array $params = [])
{
    $statement = DagrilIntegrationTest::db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function farmManagerCheck($condition, $message)
{
    DagrilIntegrationTest::check($condition, $message);
    if (!$condition) throw new RuntimeException($message);
}

function farmManagerLogin($userId, $siteId)
{
    $user = farmManagerQuery('SELECT * FROM users WHERE id=?', [$userId])->fetch();
    Auth::login($user);
    Auth::selectSite((string) $siteId);
    return Auth::user();
}

function farmManagerHarvestData($campaignPlotId)
{
    return [
        'campaign_plot_id' => $campaignPlotId,
        'harvested_at' => date('Y-m-d H:i:s'),
        'gross_weight_kg' => '1000', 'tare_weight_kg' => '20', 'drying_loss_kg' => '30',
        'moisture_before' => '16', 'moisture_after' => '13',
        'drying_started_at' => '', 'drying_ended_at' => '',
    ];
}

$tag = 'FMSV-' . strtoupper(bin2hex(random_bytes(5)));
$farm = (int) DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='FARM-DIK' AND status='active' AND deleted_at IS NULL");
$otherFarm = (int) DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='FARM-MUT' AND status='active' AND deleted_at IS NULL");
$managerRole = (int) DagrilIntegrationTest::scalar("SELECT id FROM roles WHERE slug='responsable-ferme' AND status='active' AND deleted_at IS NULL");
farmManagerCheck($farm > 0 && $otherFarm > 0 && $managerRole > 0, 'Deux fermes et le rôle Responsable ferme sont disponibles');
farmManagerCheck((int) DagrilIntegrationTest::scalar("SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.component IN ('agriculture','livestock') AND p.action IN ('read','create','update','validate')", [$managerRole]) === 8, 'Le rôle Responsable ferme possède les droits de saisie et validation agricoles et élevage');
$admin = DagrilIntegrationTest::loginRole('administrateur', $farm);

// An ordinary role deliberately has the same agriculture permissions. The role
// itself, its effective assignment and its site must authorize self-validation.
farmManagerQuery("INSERT INTO roles(name,slug,status) VALUES(? ,?,'active')", [$tag . ' opérateur', strtolower($tag) . '-operateur']);
$operatorRole = (int) $db->lastInsertId();
farmManagerQuery("INSERT INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE component='agriculture' AND action IN ('read','create','update','validate')", [$operatorRole]);
$actors = [];
foreach (['manager', 'operator'] as $kind) {
    // No legacy primary role: the manager exception must use effective RBAC.
    farmManagerQuery("INSERT INTO users(role_id,name,email,password,status) VALUES(NULL,?,?,?,'active')", [$tag . ' ' . $kind, strtolower($tag) . '-' . $kind . '@example.test', password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT)]);
    $actors[$kind] = (int) $db->lastInsertId();
    foreach ([$farm, $otherFarm] as $site) {
        farmManagerQuery("INSERT INTO user_sites(user_id,site_id,is_default,status) VALUES(?,?,?,'active')", [$actors[$kind], $site, $site === $farm ? 1 : 0]);
    }
    farmManagerQuery("INSERT INTO user_role_assignments(user_id,role_id,site_id,assignment_type,starts_at,approval_status,requested_by,approved_by,approved_at) VALUES(?,?,?,'direct',DATE_SUB(NOW(),INTERVAL 1 MINUTE),'approved',?,?,NOW())", [$actors[$kind], $kind === 'manager' ? $managerRole : $operatorRole, $farm, $admin['id'], $admin['id']]);
}

farmManagerQuery("INSERT INTO products(name,code,category,unit,status) VALUES(?,?,'raw_material','kg','active')", [$tag . ' maïs', $tag . '-MAIS']);
$product = (int) $db->lastInsertId();
farmManagerQuery("INSERT INTO agricultural_varieties(product_id,code,name,status) VALUES(?,?,?,'active')", [$product, $tag . '-VAR', $tag . ' variété']);
$variety = (int) $db->lastInsertId();
farmManagerQuery("INSERT INTO agricultural_seasons(code,name,start_month,end_month,status) VALUES(?,?,1,12,'active')", [$tag . '-SAISON', $tag . ' saison']);
$season = (int) $db->lastInsertId();
$plots = [];
foreach (['manager' => $farm, 'operator' => $farm, 'admin' => $farm, 'other' => $otherFarm] as $kind => $site) {
    farmManagerQuery("INSERT INTO agricultural_campaigns(site_id,season_id,code,name,start_date,end_date,status,created_by) VALUES(?,?,?,?,?,?,'harvesting',?)", [$site, $season, $tag . '-C-' . $kind, $tag . ' campagne ' . $kind, date('Y-01-01'), date('Y-12-31'), $admin['id']]);
    $campaign = (int) $db->lastInsertId();
    farmManagerQuery("INSERT INTO agricultural_plots(site_id,code,name,total_area_ha,status) VALUES(?,?,?,1,'active')", [$site, $tag . '-P-' . $kind, $tag . ' parcelle ' . $kind]);
    $plot = (int) $db->lastInsertId();
    farmManagerQuery("INSERT INTO agricultural_campaign_plots(campaign_id,plot_id,variety_id,planned_area_ha,actual_area_ha,target_tons_per_ha,status) VALUES(?,?,?,1,1,2,'harvested')", [$campaign, $plot, $variety]);
    $plots[$kind] = (int) $db->lastInsertId();
}

$agriculture = new Agriculture();
$manager = farmManagerLogin($actors['manager'], $farm);
farmManagerCheck(!Auth::hasRole(['administrateur']) && empty($manager['role_id']), 'Le responsable utilise un rôle effectif sans rôle principal administratif');
farmManagerCheck(Auth::can('agriculture', 'create', $farm) && Auth::can('agriculture', 'validate', $farm), 'Le responsable peut saisir et valider dans sa ferme');
farmManagerCheck(Auth::canSelfValidate($manager['id'], 'agriculture', $farm), 'Auto-validation agricole autorisée au responsable de cette ferme');
farmManagerCheck(Auth::canAccessSite($otherFarm) && !Auth::canSelfValidate($manager['id'], 'agriculture', $otherFarm), 'Un simple accès à une autre ferme ne permet pas son auto-validation');
farmManagerCheck(!Auth::canSelfValidate($actors['operator'], 'agriculture', $farm), 'L’exception ne peut pas être utilisée pour une identité différente');

$harvest = $agriculture->createHarvest(farmManagerHarvestData($plots['manager']), $manager);
farmManagerCheck(DagrilIntegrationTest::scalar('SELECT status FROM agricultural_harvests WHERE id=?', [$harvest]) === 'submitted', 'La récolte du responsable attend sa validation explicite');
farmManagerCheck((int) DagrilIntegrationTest::scalar('SELECT COUNT(*) FROM agricultural_stocks WHERE harvest_id=?', [$harvest]) === 0, 'Aucun stock agricole crédité avant validation');
$stock = $agriculture->validateHarvest($harvest, $manager);
$savedHarvest = DagrilIntegrationTest::row('SELECT * FROM agricultural_harvests WHERE id=?', [$harvest]);
farmManagerCheck($savedHarvest['status'] === 'validated' && (int) $savedHarvest['created_by'] === $actors['manager'] && (int) $savedHarvest['validated_by'] === $actors['manager'], 'Création et validation de la récolte tracées avec le même responsable');
farmManagerCheck((float) DagrilIntegrationTest::scalar('SELECT physical_quantity_kg FROM agricultural_stocks WHERE id=?', [$stock]) === 950.0, 'Entrée en stock du poids net exact de 950 kg');
farmManagerCheck((int) DagrilIntegrationTest::scalar("SELECT COUNT(*) FROM agricultural_stock_movements WHERE stock_id=? AND movement_type='harvest_in' AND created_by=?", [$stock, $manager['id']]) === 1, 'Une entrée en stock unique est attribuée au responsable');
DagrilIntegrationTest::expectException(fn() => $agriculture->validateHarvest($harvest, $manager), 'La double validation de récolte demeure refusée');
farmManagerCheck((int) DagrilIntegrationTest::scalar('SELECT COUNT(*) FROM agricultural_stocks WHERE harvest_id=?', [$harvest]) === 1 && !$db->inTransaction(), 'Le refus ne duplique pas le stock et ferme la transaction');

$transportData = ['stock_id' => $stock, 'quantity_kg' => '400', 'truck_plate' => $tag . '-CAM', 'driver_name' => 'Chauffeur de test', 'route_notes' => ''];
$transport = $agriculture->createTransport($transportData, $manager);
farmManagerCheck((float) DagrilIntegrationTest::scalar('SELECT reserved_quantity_kg FROM agricultural_stocks WHERE id=?', [$stock]) === 0.0, 'Le brouillon de BT ne réserve pas prématurément le stock');
DagrilIntegrationTest::expectException(fn() => $agriculture->dispatchTransport($transport, $manager), 'Le BT reste non expédiable avant approbation');
$agriculture->approveTransport($transport, $manager);
$savedTransport = DagrilIntegrationTest::row('SELECT * FROM agricultural_transports WHERE id=?', [$transport]);
farmManagerCheck($savedTransport['status'] === 'approved' && (int) $savedTransport['created_by'] === (int) $manager['id'] && (int) $savedTransport['approved_by'] === (int) $manager['id'], 'Le responsable approuve son BT et reste identifié comme auteur et validateur');
farmManagerCheck((float) DagrilIntegrationTest::scalar('SELECT physical_quantity_kg FROM agricultural_stocks WHERE id=?', [$stock]) === 950.0 && (float) DagrilIntegrationTest::scalar('SELECT reserved_quantity_kg FROM agricultural_stocks WHERE id=?', [$stock]) === 400.0, 'L’approbation réserve exactement 400 kg sans sortir de matière');
$document = DagrilIntegrationTest::row("SELECT * FROM documents WHERE entity_type='agricultural_transports' AND entity_id=?", [$transport]);
farmManagerCheck($document['status'] === 'approved' && (int) $document['created_by'] === (int) $manager['id'] && (int) $document['validated_by'] === (int) $manager['id'], 'Le document officiel conserve le même créateur et validateur');
DagrilIntegrationTest::expectException(fn() => $agriculture->approveTransport($transport, $manager), 'Une seconde approbation du même BT est refusée');
$bridge = $agriculture->dispatchTransport($transport, $manager);
$savedTransport = DagrilIntegrationTest::row('SELECT * FROM agricultural_transports WHERE id=?', [$transport]);
farmManagerCheck($savedTransport['status'] === 'in_transit' && (int) $savedTransport['dispatched_by'] === (int) $manager['id'], 'Le même responsable expédie le BT sans intervention d’un autre compte');
farmManagerCheck((float) DagrilIntegrationTest::scalar('SELECT physical_quantity_kg FROM agricultural_stocks WHERE id=?', [$stock]) === 550.0 && (float) DagrilIntegrationTest::scalar('SELECT reserved_quantity_kg FROM agricultural_stocks WHERE id=?', [$stock]) === 0.0, 'L’expédition retire 400 kg et libère exactement leur réservation');
farmManagerCheck((int) DagrilIntegrationTest::scalar('SELECT agricultural_transport_id FROM weighbridge_transports WHERE id=?', [$bridge]) === $transport && DagrilIntegrationTest::scalar('SELECT transport_reference FROM weighbridge_transports WHERE id=?', [$bridge]) === $document['document_number'], 'Le pont-bascule reçoit le transport avec la référence officielle d’origine');
DagrilIntegrationTest::expectException(fn() => $agriculture->dispatchTransport($transport, $manager), 'Une seconde expédition du même BT est refusée');
farmManagerCheck((int) DagrilIntegrationTest::scalar("SELECT COUNT(*) FROM agricultural_stock_movements WHERE stock_id=? AND movement_type='transport_out'", [$stock]) === 1, 'Une seule sortie de stock est enregistrée');

$operator = farmManagerLogin($actors['operator'], $farm);
farmManagerCheck(Auth::can('agriculture', 'create', $farm) && Auth::can('agriculture', 'validate', $farm) && !Auth::canSelfValidate($operator['id'], 'agriculture', $farm), 'Les droits create et validate seuls ne donnent pas l’exception au simple opérateur');
$operatorHarvest = $agriculture->createHarvest(farmManagerHarvestData($plots['operator']), $operator);
DagrilIntegrationTest::expectException(fn() => $agriculture->validateHarvest($operatorHarvest, $operator), 'L’opérateur ordinaire ne peut toujours pas valider sa propre récolte');
farmManagerCheck(DagrilIntegrationTest::scalar('SELECT status FROM agricultural_harvests WHERE id=?', [$operatorHarvest]) === 'submitted' && (int) DagrilIntegrationTest::scalar('SELECT COUNT(*) FROM agricultural_stocks WHERE harvest_id=?', [$operatorHarvest]) === 0, 'Le refus de l’opérateur conserve une récolte soumise sans stock');
$admin = farmManagerLogin($admin['id'], $farm);
$operatorStock = $agriculture->validateHarvest($operatorHarvest, $admin);
$operator = farmManagerLogin($actors['operator'], $farm);
$operatorTransport = $agriculture->createTransport(array_replace($transportData, ['stock_id' => $operatorStock, 'truck_plate' => $tag . '-OP']), $operator);
DagrilIntegrationTest::expectException(fn() => $agriculture->approveTransport($operatorTransport, $operator), 'L’opérateur ordinaire ne peut toujours pas approuver son propre BT');
farmManagerCheck(DagrilIntegrationTest::scalar('SELECT status FROM agricultural_transports WHERE id=?', [$operatorTransport]) === 'draft' && (float) DagrilIntegrationTest::scalar('SELECT reserved_quantity_kg FROM agricultural_stocks WHERE id=?', [$operatorStock]) === 0.0, 'Le refus conserve le brouillon du BT sans réservation');

$admin = farmManagerLogin($admin['id'], $farm);
farmManagerCheck(Auth::canSelfValidate($admin['id'], 'agriculture', $farm), 'L’exception d’auto-validation administrative est conservée');
$adminHarvest = $agriculture->createHarvest(farmManagerHarvestData($plots['admin']), $admin);
$adminStock = $agriculture->validateHarvest($adminHarvest, $admin);
farmManagerCheck($adminStock > 0 && (int) DagrilIntegrationTest::scalar('SELECT validated_by FROM agricultural_harvests WHERE id=?', [$adminHarvest]) === (int) $admin['id'], 'L’administrateur valide toujours sa propre récolte');
Auth::selectSite((string) $otherFarm);
$otherHarvest = $agriculture->createHarvest(farmManagerHarvestData($plots['other']), $admin);
$manager = farmManagerLogin($actors['manager'], $otherFarm);
DagrilIntegrationTest::expectException(fn() => $agriculture->validateHarvest($otherHarvest, $manager), 'Le responsable sans droit de validation sur l’autre ferme reste refusé');
farmManagerCheck((int) DagrilIntegrationTest::scalar('SELECT COUNT(*) FROM agricultural_stocks WHERE harvest_id=?', [$otherHarvest]) === 0 && !$db->inTransaction(), 'Le refus hors périmètre ne crée aucun stock ni transaction ouverte');

DagrilIntegrationTest::finish();
