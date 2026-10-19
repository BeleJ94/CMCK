<?php

if (!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D', getenv('DAGRIL_DB_DATABASE') ?: '')) {
    throw new RuntimeException('Utilisez une base jetable créée par setup_porcine_flow_test.php.');
}

require_once __DIR__ . '/integration/TestBootstrap.php';
$db = DagrilIntegrationTest::boot();
require_once __DIR__ . '/../app/services/PorcineFlowService.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/controllers/PorcineFlowController.php';

$fixturePath = getenv('DAGRIL_FLOW_FIXTURE');
if (!$fixturePath || !is_file($fixturePath)) {
    throw new RuntimeException('Lancez PorcineFlowIntegrationTest.php avec DAGRIL_FLOW_FIXTURE avant ce test.');
}
$fixture = json_decode(file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
if (($fixture['database'] ?? '') !== getenv('DAGRIL_DB_DATABASE')) {
    throw new RuntimeException('La fixture doit correspondre à la base jetable active.');
}

function btrQuery(string $sql, array $params = []): PDOStatement
{
    global $db;
    $query = $db->prepare($sql);
    $query->execute($params);
    return $query;
}

function btrCheck(bool $condition, string $message): void
{
    DagrilIntegrationTest::check($condition, $message);
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function btrSnapshot(): array
{
    return [
        btrQuery('SELECT id,quantity_kg,reserved_kg,status FROM livestock_weight_stocks ORDER BY id')->fetchAll(),
        btrQuery('SELECT id,transfer_number,quantity_kg,status,dispatched_at,received_at FROM livestock_transfers ORDER BY id')->fetchAll(),
        btrQuery('SELECT id,document_number,status,deleted_at FROM documents ORDER BY id')->fetchAll(),
        btrQuery('SELECT COUNT(*) FROM livestock_weight_movements')->fetchColumn(),
        btrQuery('SELECT COUNT(*) FROM document_status_history')->fetchColumn(),
        btrQuery('SELECT COUNT(*) FROM document_number_counters')->fetchColumn(),
    ];
}

$_SERVER['HTTP_HOST'] = 'dagril.test';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_GET = [];
$admin = DagrilIntegrationTest::loginRole('administrateur', (int) $fixture['farm']);
$flow = new PorcineFlowService($db);
$shipment = btrQuery(
    "SELECT t.*,b.batch_number,s.name source_name,ds.name destination_name,d.document_number
     FROM livestock_transfers t
     JOIN livestock_batches b ON b.id=t.batch_id
     JOIN sites s ON s.id=t.source_site_id
     JOIN sites ds ON ds.id=t.destination_site_id
     JOIN documents d ON d.entity_type='livestock_transfers' AND d.entity_id=t.id AND d.deleted_at IS NULL
     JOIN document_types dt ON dt.id=d.document_type_id AND dt.code='BTR'
     WHERE t.batch_id=? AND t.source_site_id=? AND t.deleted_at IS NULL
     ORDER BY t.id LIMIT 1",
    [(int) $fixture['adult'], (int) $fixture['farm']]
)->fetch();
if (!$shipment) {
    throw new RuntimeException('La fixture doit contenir un envoi avec un BTR officiel.');
}
$id = (int) $shipment['id'];
$otherFarm = (int) btrQuery("SELECT id FROM sites WHERE code IN ('FARM-MUT','FARM-DIK') AND id<>? LIMIT 1", [(int) $fixture['farm']])->fetchColumn();
if (!$otherFarm) {
    throw new RuntimeException('Deux fermes sont nécessaires pour vérifier le périmètre.');
}

$db->beginTransaction();
try {
    $before = btrSnapshot();
    $document = $flow->shipmentBtr($id);
    btrCheck(is_array($document) && (int) $document['id'] === $id, 'Le BTR consulte le bon envoi.');
    btrCheck($document['official_document_number'] === $shipment['document_number'], 'Le BTR réutilise le numéro documentaire officiel.');
    btrCheck($document['batch_number'] === $shipment['batch_number'] && (float) $document['quantity_kg'] === (float) $shipment['quantity_kg'], 'Le lot et le poids correspondent à l’envoi.');
    btrCheck($document['source_name'] === $shipment['source_name'] && $document['destination_name'] === $shipment['destination_name'], 'La ferme et la destination sont exactes.');
    btrCheck($document['status'] === $shipment['status'], 'L’état affiché est celui de l’envoi.');
    btrCheck($document['truck_plate'] === $shipment['truck_plate'] && $document['driver_name'] === $shipment['driver_name'], 'Le véhicule et le chauffeur correspondent à l’envoi.');
    btrCheck(!empty($document['conversion_number']) && !empty($document['converted_at']) && !empty($document['expiry_date']), 'L’abattage et la DLC restent traçables.');
    btrCheck(btrSnapshot() === $before, 'La lecture ne modifie ni les stocks, ni les envois, ni les documents.');

    $missing = (int) btrQuery('SELECT COALESCE(MAX(id),0)+1 FROM livestock_transfers')->fetchColumn();
    btrCheck($flow->shipmentBtr($missing) === null, 'Un envoi inconnu ne révèle aucun document.');
    Auth::selectSite((string) $otherFarm);
    btrCheck($flow->shipmentBtr($id) === null, 'Un envoi hors du contexte sélectionné est inaccessible.');
    Auth::selectSite((string) $fixture['farm']);

    btrQuery('UPDATE livestock_transfers SET deleted_at=NOW() WHERE id=?', [$id]);
    btrCheck($flow->shipmentBtr($id) === null, 'Un envoi supprimé ne peut pas être prévisualisé.');
    btrQuery('UPDATE livestock_transfers SET deleted_at=NULL WHERE id=?', [$id]);

    btrQuery("UPDATE documents SET deleted_at=NOW() WHERE entity_type='livestock_transfers' AND entity_id=?", [$id]);
    $withoutDocument = $flow->shipmentBtr($id);
    btrCheck(is_array($withoutDocument) && $withoutDocument['official_document_number'] === null, 'Une référence documentaire absente ne provoque pas de nouveau BTR.');
    btrQuery("UPDATE documents SET deleted_at=NULL WHERE entity_type='livestock_transfers' AND entity_id=?", [$id]);

    $tag = bin2hex(random_bytes(5));
    btrQuery("INSERT INTO roles(name,slug,status) VALUES(?,?,'active')", ['Lecture élevage BTR', 'btr-read-' . $tag]);
    $role = (int) $db->lastInsertId();
    btrQuery("INSERT INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE component='livestock' AND action='read'", [$role]);
    btrQuery("INSERT INTO users(name,email,password,status) VALUES(?,?,?,'active')", ['Lecture BTR', 'btr-' . $tag . '@example.test', 'unused']);
    $userId = (int) $db->lastInsertId();
    btrQuery("INSERT INTO user_sites(user_id,site_id,is_default,status) VALUES(?,?,1,'active')", [$userId, (int) $fixture['farm']]);
    btrQuery("INSERT INTO user_role_assignments(user_id,role_id,site_id,assignment_type,starts_at,approval_status) VALUES(?,?,?,'direct',NOW(),'approved')", [$userId, $role, (int) $fixture['farm']]);
    Auth::login(btrQuery('SELECT * FROM users WHERE id=?', [$userId])->fetch());
    Auth::selectSite((string) $fixture['farm']);
    btrCheck(Auth::can('livestock', 'read') && !Auth::can('documents', 'read'), 'Le profil de test possède uniquement la lecture de l’élevage.');
    btrCheck((int) $flow->shipmentBtr($id)['id'] === $id, 'Un responsable élevage peut consulter le BTR sans permission documentaire.');

    btrQuery('UPDATE livestock_transfers SET driver_name=? WHERE id=?', ['<script>unsafe-btr</script>', $id]);
    $before = btrSnapshot();
    http_response_code(200);
    ob_start();
    (new PorcineFlowController())->shipmentBtr($id);
    $html = ob_get_clean();
    btrCheck(strpos($html, 'shipment-btr-document') !== false && http_response_code() === 200, 'Le contrôleur fournit un document BTR imprimable.');
    btrCheck(strpos($html, e($shipment['document_number'])) !== false && strpos($html, e($shipment['batch_number'])) !== false, 'L’aperçu présente les bonnes références.');
    btrCheck(strpos($html, '&lt;script&gt;unsafe-btr&lt;/script&gt;') !== false && strpos($html, '<script>unsafe-btr</script>') === false, 'Les données du BTR sont échappées dans l’aperçu.');
    btrCheck(btrSnapshot() === $before, 'La prévisualisation ne crée aucun mouvement ni document.');

    btrQuery('DELETE FROM role_permissions WHERE role_id=?', [$role]);
    $denied = false;
    try {
        $denied = $flow->shipmentBtr($id) === null;
    } catch (RuntimeException $exception) {
        $denied = http_response_code() === 403;
    }
    btrCheck($denied, 'La suppression de la permission élevage interdit immédiatement la lecture.');
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(200);
    Auth::login($admin);
    Auth::selectSite((string) $fixture['farm']);
    $_GET = [];
}

DagrilIntegrationTest::finish();
