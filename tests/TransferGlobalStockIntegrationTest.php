<?php
// Transferts inter-sites par quantité globale : répartition des sacs sur les lots les plus anciens,
// nouvelle répartition à la validation si un lot a été consommé, refus et réservation par lot.
// Base de test obligatoire (nom terminant par _test/_testing).
require __DIR__ . '/integration/TestBootstrap.php';
$db = DagrilIntegrationTest::boot();
require_once dirname(__DIR__) . '/app/services/TransferService.php';
function tq($db, $sql, $p = []) { $s = $db->prepare($sql); $s->execute($p); return $s; }
function tv($db, $sql, $p = []) { return tq($db, $sql, $p)->fetchColumn(); }
$transferIds = []; $stocks = []; $productId = 0; $formatId = 0;
try {
    $creator = DagrilIntegrationTest::loginRole('direction', 'all');
    $validator = DagrilIntegrationTest::loginRole('administrateur', 'all');
    $source = (int) tv($db, "SELECT id FROM sites WHERE code='MINO'");
    $dest = (int) tv($db, "SELECT id FROM sites WHERE code<>'MINO' AND status='active' AND deleted_at IS NULL ORDER BY id LIMIT 1");
    Auth::login($creator);
    $suffix = random_int(10000, 99999);
    tq($db, "INSERT INTO products(name,code,category,unit,status) VALUES('QA Transfert global',?,'finished_product','kg','active')", ['TST-TRG-' . $suffix]); $productId = (int) $db->lastInsertId();
    tq($db, "INSERT INTO bag_formats(name,weight_kg,status) VALUES(?,25,'active')", ['QA Sac global ' . $suffix]); $formatId = (int) $db->lastInsertId();
    // Trois lots : 10 sacs (le plus ancien, dont 2 réservés), 10 sacs, 10 sacs.
    foreach ([['2001-01-01 08:00:00', 10, 2], ['2001-01-02 08:00:00', 10, 0], ['2001-01-03 08:00:00', 10, 0]] as $lot) {
        tq($db, "INSERT INTO finished_stocks(site_id,product_id,bag_format_id,quantity_bags,reserved_bags,total_weight_kg,reserved_weight_kg,status,created_at) VALUES(?,?,?,?,?,?,?,'active',?)", [$source, $productId, $formatId, $lot[1], $lot[2], $lot[1] * 25, $lot[2] * 25, $lot[0]]);
        $stocks[] = (int) $db->lastInsertId();
    }
    $svc = new TransferService($db);
    $global = function ($bags) use ($source, $dest, $productId, $formatId) { return ['transfer_type' => 'inter_site', 'source_site_id' => $source, 'destination_site_id' => $dest, 'items' => [['product_id' => $productId, 'bag_format_id' => $formatId, 'quantity_bags' => $bags]]]; };

    // 15 sacs : 8 libres du lot 1, puis 7 du lot 2.
    $transferIds[] = $id = $svc->create($global(15), $creator);
    $items = tq($db, 'SELECT source_finished_stock_id, requested_bags, requested_kg FROM stock_transfer_items WHERE transfer_id=? ORDER BY id', [$id])->fetchAll();
    DagrilIntegrationTest::check(count($items) === 2 && (int) $items[0]['source_finished_stock_id'] === $stocks[0] && (int) $items[0]['requested_bags'] === 8 && (int) $items[1]['source_finished_stock_id'] === $stocks[1] && (int) $items[1]['requested_bags'] === 7, 'Création : 8 sacs libres du lot le plus ancien puis 7 du suivant');
    DagrilIntegrationTest::check((float) $items[1]['requested_kg'] === 175.0, 'Poids calculé par ligne (7 × 25 kg)');
    DagrilIntegrationTest::check((int) tv($db, 'SELECT SUM(reserved_bags) FROM finished_stocks WHERE product_id=?', [$productId]) === 2, 'Création sans réservation');

    // Refus : 29 sacs demandés pour 28 libres, sans effet partiel.
    $count = (int) tv($db, 'SELECT COUNT(*) FROM stock_transfers');
    DagrilIntegrationTest::expectException(function () use ($svc, $global, $creator) { $svc->create($global(29), $creator); }, 'Quantité supérieure au stock global refusée');
    DagrilIntegrationTest::check((int) tv($db, 'SELECT COUNT(*) FROM stock_transfers') === $count, 'Refus sans transfert partiel');

    // Le lot 1 est consommé ailleurs entre la demande et la validation : la validation répartit à nouveau.
    tq($db, 'UPDATE finished_stocks SET reserved_bags = quantity_bags, reserved_weight_kg = total_weight_kg WHERE id = ?', [$stocks[0]]);
    $svc->submit($id, $creator);
    Auth::login($validator);
    $svc->approve($id, $validator);
    $items = tq($db, 'SELECT source_finished_stock_id, requested_bags, reserved_bags FROM stock_transfer_items WHERE transfer_id=? ORDER BY id', [$id])->fetchAll();
    DagrilIntegrationTest::check(count($items) === 2 && (int) $items[0]['source_finished_stock_id'] === $stocks[1] && (int) $items[0]['reserved_bags'] === 10 && (int) $items[1]['source_finished_stock_id'] === $stocks[2] && (int) $items[1]['reserved_bags'] === 5, 'Validation : nouvelle répartition 10 + 5 sur les lots encore libres');
    DagrilIntegrationTest::check(tv($db, 'SELECT status FROM stock_transfers WHERE id=?', [$id]) === 'reserved', 'Transfert réservé');
    $reserved = array_map('intval', tq($db, 'SELECT reserved_bags FROM finished_stocks WHERE id IN (' . implode(',', $stocks) . ') ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    DagrilIntegrationTest::check($reserved === [10, 10, 5], 'Réservations par lot : 10 (externe) / 10 / 5');
    DagrilIntegrationTest::check((int) tv($db, "SELECT COUNT(*) FROM stock_transfer_reservations r JOIN stock_transfer_items i ON i.id=r.transfer_item_id WHERE i.transfer_id=? AND r.status='active'", [$id]) === 2, 'Une réservation active par ligne');

    // Validation impossible si le stock global ne suffit plus.
    Auth::login($creator);
    $transferIds[] = $id2 = $svc->create($global(5), $creator);
    $svc->submit($id2, $creator);
    tq($db, 'UPDATE finished_stocks SET reserved_bags = quantity_bags, reserved_weight_kg = total_weight_kg WHERE id = ?', [$stocks[2]]);
    Auth::login($validator);
    DagrilIntegrationTest::expectException(function () use ($svc, $id2, $validator) { $svc->approve($id2, $validator); }, 'Validation refusée si le stock global est épuisé');
    DagrilIntegrationTest::check(tv($db, 'SELECT status FROM stock_transfers WHERE id=?', [$id2]) === 'submitted', 'Refus de validation sans effet');

    // Ancien chemin par lot (commandes clients) toujours accepté.
    Auth::login($creator);
    tq($db, 'UPDATE finished_stocks SET reserved_bags = 0, reserved_weight_kg = 0 WHERE id = ?', [$stocks[0]]);
    $transferIds[] = $id3 = $svc->create(['transfer_type' => 'inter_site', 'source_site_id' => $source, 'destination_site_id' => $dest, 'items' => [['finished_stock_id' => $stocks[0], 'quantity_bags' => 3]]], $creator);
    DagrilIntegrationTest::check((int) tv($db, 'SELECT source_finished_stock_id FROM stock_transfer_items WHERE transfer_id=?', [$id3]) === $stocks[0], 'Ligne sur lot explicite conservée');
} catch (Exception $e) {
    DagrilIntegrationTest::check(false, 'Exception inattendue : ' . $e->getMessage());
} finally {
    foreach ($transferIds as $transferId) {
     if($transferId){
      $docs=tq($db,"SELECT d.id FROM documents d WHERE (d.entity_type='stock_transfers' AND d.entity_id=?) OR (d.entity_type='transfer_shipments' AND d.entity_id IN(SELECT id FROM transfer_shipments WHERE transfer_id=?)) OR (d.entity_type='transfer_receipts' AND d.entity_id IN(SELECT id FROM transfer_receipts WHERE transfer_id=?)) OR (d.entity_type='transfer_returns' AND d.entity_id IN(SELECT id FROM transfer_returns WHERE transfer_id=?)) OR (d.entity_type='transfer_non_conformities' AND d.entity_id IN(SELECT id FROM transfer_non_conformities WHERE transfer_id=?))",array_fill(0,5,$transferId))->fetchAll(PDO::FETCH_COLUMN);
      if($docs){$p=implode(',',array_fill(0,count($docs),'?'));$wis=tq($db,"SELECT id FROM workflow_instances WHERE document_id IN({$p})",$docs)->fetchAll(PDO::FETCH_COLUMN);if($wis){$q=implode(',',array_fill(0,count($wis),'?'));foreach(['workflow_notifications','workflow_level_approvals','workflow_transitions'] as $t){tq($db,"DELETE FROM {$t} WHERE workflow_instance_id IN({$q})",$wis);}tq($db,"DELETE FROM workflow_instances WHERE id IN({$q})",$wis);}tq($db,"DELETE FROM document_status_history WHERE document_id IN({$p})",$docs);tq($db,"DELETE FROM documents WHERE id IN({$p})",$docs);}
      foreach(['stock_transfer_movements','stock_transfer_history'] as $t){tq($db,"DELETE FROM {$t} WHERE transfer_id=?",[$transferId]);}
      tq($db,'DELETE FROM transfer_return_items WHERE return_id IN(SELECT id FROM transfer_returns WHERE transfer_id=?)',[$transferId]); tq($db,'DELETE FROM transfer_returns WHERE transfer_id=?',[$transferId]); tq($db,'DELETE FROM transfer_non_conformities WHERE transfer_id=?',[$transferId]); tq($db,'DELETE FROM transfer_receipt_items WHERE receipt_id IN(SELECT id FROM transfer_receipts WHERE transfer_id=?)',[$transferId]); tq($db,'DELETE FROM transfer_receipts WHERE transfer_id=?',[$transferId]); tq($db,'DELETE FROM transfer_shipment_items WHERE shipment_id IN(SELECT id FROM transfer_shipments WHERE transfer_id=?)',[$transferId]); tq($db,'DELETE FROM transfer_shipments WHERE transfer_id=?',[$transferId]); tq($db,'DELETE FROM stock_transfer_reservations WHERE transfer_item_id IN(SELECT id FROM stock_transfer_items WHERE transfer_id=?)',[$transferId]); tq($db,'DELETE FROM stock_transfer_items WHERE transfer_id=?',[$transferId]); tq($db,'DELETE FROM stock_transfers WHERE id=?',[$transferId]);
     }

    }
    foreach ($stocks as $sid) { tq($db, "DELETE FROM stock_transfer_movements WHERE transfer_item_id IN (SELECT id FROM stock_transfer_items WHERE source_finished_stock_id=?)", [$sid]); tq($db, "DELETE FROM finished_stocks WHERE id=?", [$sid]); }
    if ($formatId) { tq($db, "DELETE FROM finished_stocks WHERE bag_format_id=?", [$formatId]); tq($db, "DELETE FROM bag_formats WHERE id=?", [$formatId]); }
    if ($productId) { tq($db, "DELETE FROM products WHERE id=?", [$productId]); }
}
DagrilIntegrationTest::finish();
