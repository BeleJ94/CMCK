<?php
// Sortie sans commande par quantité globale : choix du produit, prélèvement des lots libres les plus anciens,
// un mouvement par lot, refus, détail et annulation lot par lot. Base de test obligatoire (_test/_testing).
require __DIR__ . '/integration/TestBootstrap.php';
$db = DagrilIntegrationTest::boot();
$root = dirname(__DIR__);
require_once $root . '/app/models/Distribution.php';
require_once $root . '/app/services/CancellationService.php';
$q = function ($sql, $p = []) use ($db) { $s = $db->prepare($sql); $s->execute($p); return $s; };
$v = function ($sql, $p = []) use ($q) { return $q($sql, $p)->fetchColumn(); };
$stocks = []; $distributions = []; $formatId = 0;
try {
    $admin = DagrilIntegrationTest::loginRole('administrateur', 'all');
    $site = (int) $v("SELECT id FROM sites WHERE code='MINO'");
    // Le module ne sort que la farine et l'aliment bétail : on isole le test par un format de sac dédié.
    $product = (int) $v("SELECT id FROM products WHERE code='FARINE-MAIS' AND deleted_at IS NULL");
    $q("INSERT INTO bag_formats(name,weight_kg,status) VALUES(?,20,'active')", ['QA Sortie ' . random_int(10000, 99999)]); $formatId = (int) $db->lastInsertId();
    // Lot 1 : 6 sacs dont 2 réservés (4 libres) ; lot 2 : 10 sacs ; lot 3 : 10 sacs.
    foreach ([['2001-01-01 08:00:00', 6, 2], ['2001-01-02 08:00:00', 10, 0], ['2001-01-03 08:00:00', 10, 0]] as $lot) {
        $q("INSERT INTO finished_stocks(site_id,product_id,bag_format_id,quantity_bags,reserved_bags,total_weight_kg,reserved_weight_kg,status,created_at) VALUES(?,?,?,?,?,?,?,'active',?)", [$site, $product, $formatId, $lot[1], $lot[2], $lot[1] * 20, $lot[2] * 20, $lot[0]]);
        $stocks[] = (int) $db->lastInsertId();
    }
    $model = new Distribution();
    $key = Distribution::stockKey($site, $product, $formatId);
    $group = null; foreach ($model->availableProducts() as $row) { if ($row['key'] === $key) { $group = $row; } }
    DagrilIntegrationTest::check($group && (int) $group['free_bags'] === 24 && count($group['lots']) === 3 && $group['lots'][0]['id'] === $stocks[0] && (int) $group['lots'][0]['bags'] === 4, 'Stock global : 24 sacs libres sur 3 lots, plus ancien en tête');

    $exit = function ($bags, $extra = []) use ($key) { return array_replace(['stock_key' => $key, 'quantity_bags' => $bags, 'recipient_name' => 'Client test global', 'transporter' => '', 'exit_voucher' => '', 'distributed_at' => date('Y-m-d H:i:s'), 'customer_id' => ''], $extra); };
    // 9 sacs : 4 libres du lot 1 puis 5 du lot 2.
    $distributions[] = $id = (int) $model->createDistribution($exit(9), $admin);
    $moves = $q("SELECT finished_stock_id, quantity_bags, quantity_kg FROM stock_movements WHERE distribution_id=? AND movement_type='out' ORDER BY id", [$id])->fetchAll();
    DagrilIntegrationTest::check(count($moves) === 2 && (int) $moves[0]['finished_stock_id'] === $stocks[0] && (int) $moves[0]['quantity_bags'] === 4 && (int) $moves[1]['finished_stock_id'] === $stocks[1] && (int) $moves[1]['quantity_bags'] === 5, 'Prélèvement : 4 sacs libres du lot le plus ancien puis 5 du suivant');
    DagrilIntegrationTest::check((float) $moves[1]['quantity_kg'] === 100.0, 'Poids par lot (5 × 20 kg)');
    $bags = array_map('intval', $q('SELECT quantity_bags FROM finished_stocks WHERE id IN (' . implode(',', $stocks) . ') ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    DagrilIntegrationTest::check($bags === [2, 5, 10], 'Stocks des lots : 2 (réservés, intacts) / 5 / 10');
    $d = $model->findDetailed($id);
    DagrilIntegrationTest::check((int) $d['quantity_bags'] === 9 && (float) $d['total_weight_kg'] === 180.0 && count($d['lots']) === 2, 'Bon de sortie : 9 sacs, 180 kg, 2 lots détaillés');
    $hist = null; foreach ($model->history() as $row) { if ((int) $row['id'] === $id) { $hist = $row; } }
    DagrilIntegrationTest::check($hist && strpos($hist['lots_label'], '#' . $stocks[0] . ' : 4') === 0, 'Historique : lots prélevés affichés');

    // Refus sans effet partiel : 16 sacs pour 15 libres.
    $before = $v('SELECT SUM(quantity_bags) FROM finished_stocks WHERE bag_format_id=?', [$formatId]);
    DagrilIntegrationTest::expectException(function () use ($model, $exit, $admin) { $model->createDistribution($exit(16), $admin); }, 'Quantité supérieure au stock libre refusée');
    DagrilIntegrationTest::check($v('SELECT SUM(quantity_bags) FROM finished_stocks WHERE bag_format_id=?', [$formatId]) === $before, 'Refus sans effet partiel');
    DagrilIntegrationTest::expectException(function () use ($model, $exit, $admin) { $model->createDistribution($exit(0), $admin); }, 'Quantité nulle refusée');

    // Lot explicite (ancien chemin) toujours accepté.
    $distributions[] = $legacy = (int) $model->createDistribution($exit(1, ['stock_key' => '', 'finished_stock_id' => $stocks[2]]), $admin);
    DagrilIntegrationTest::check((int) $v("SELECT finished_stock_id FROM stock_movements WHERE distribution_id=? AND movement_type='out'", [$legacy]) === $stocks[2], 'Sortie sur lot explicite conservée');

    // Annulation : chaque lot récupère ses propres sacs.
    $approver = DagrilIntegrationTest::loginRole('direction', 'all');
    $service = new CancellationService($db);
    Auth::login($admin);
    $request = $service->request('distribution', $id, 'Test : sortie globale à annuler', $admin);
    $service->approve($request, $approver);
    $bags = array_map('intval', $q('SELECT quantity_bags FROM finished_stocks WHERE id IN (' . implode(',', $stocks) . ') ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    DagrilIntegrationTest::check($bags === [6, 10, 9], 'Annulation : 4 sacs rendus au lot 1 et 5 au lot 2');
    DagrilIntegrationTest::check($v('SELECT status FROM distributions WHERE id=?', [$id]) === 'cancelled', 'Sortie annulée');
} catch (Exception $e) {
    DagrilIntegrationTest::check(false, 'Exception inattendue : ' . $e->getMessage());
} finally {
    foreach ($distributions as $id) {
        foreach (["DELETE FROM cancellation_stock_effects WHERE reversal_id IN (SELECT id FROM cancellation_reversals WHERE entity_type='distribution' AND entity_id=?)", "DELETE FROM cancellation_reversals WHERE entity_type='distribution' AND entity_id=?", "DELETE FROM cancellation_events WHERE request_id IN (SELECT id FROM cancellation_requests WHERE entity_type='distribution' AND entity_id=?)", "DELETE FROM cancellation_requests WHERE entity_type='distribution' AND entity_id=?", "DELETE FROM workflow_notifications WHERE workflow_instance_id IN (SELECT wi.id FROM workflow_instances wi JOIN documents d ON d.id=wi.document_id WHERE d.entity_type='distributions' AND d.entity_id=?)", "DELETE FROM workflow_level_approvals WHERE workflow_instance_id IN (SELECT wi.id FROM workflow_instances wi JOIN documents d ON d.id=wi.document_id WHERE d.entity_type='distributions' AND d.entity_id=?)", "DELETE FROM workflow_transitions WHERE workflow_instance_id IN (SELECT wi.id FROM workflow_instances wi JOIN documents d ON d.id=wi.document_id WHERE d.entity_type='distributions' AND d.entity_id=?)", "DELETE wi FROM workflow_instances wi JOIN documents d ON d.id=wi.document_id WHERE d.entity_type='distributions' AND d.entity_id=?", "DELETE FROM document_status_history WHERE document_id IN (SELECT id FROM documents WHERE entity_type='distributions' AND entity_id=?)", "DELETE FROM documents WHERE entity_type='distributions' AND entity_id=?", 'DELETE FROM stock_movements WHERE distribution_id=?', 'DELETE FROM distributions WHERE id=?'] as $sql) {
            try { $q($sql, [$id]); } catch (Exception $ignored) { echo '  (nettoyage) ' . $ignored->getMessage() . PHP_EOL; }
        }
    }
    foreach ($stocks as $sid) { try { $q('DELETE FROM stock_movements WHERE finished_stock_id=?', [$sid]); $q('DELETE FROM finished_stocks WHERE id=?', [$sid]); } catch (Exception $ignored) { echo '  (nettoyage) ' . $ignored->getMessage() . PHP_EOL; } }
    if ($formatId) { try { $q('DELETE FROM bag_formats WHERE id=?', [$formatId]); } catch (Exception $ignored) { echo '  (nettoyage) ' . $ignored->getMessage() . PHP_EOL; } }
}
DagrilIntegrationTest::finish();
