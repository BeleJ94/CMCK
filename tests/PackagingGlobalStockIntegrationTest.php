<?php
// Conditionnement par quantité globale : prélèvement des lots les plus anciens, lignes par lot, refus et annulation.
// Base de test obligatoire (nom terminant par _test/_testing), migration 052 appliquée.
require __DIR__ . '/integration/TestBootstrap.php';
$db = DagrilIntegrationTest::boot();
$root = dirname(__DIR__);
require_once $root . '/app/models/Packaging.php';
require_once $root . '/app/services/StockAllocationService.php';
require_once $root . '/app/services/CancellationService.php';

$q = function ($sql, $params = []) use ($db) { $s = $db->prepare($sql); $s->execute($params); return $s; };
$fx = ['batches' => [], 'bulks' => [], 'packaging' => []];

// Service pur : répartition sans base.
$lots = [['id' => 1, 'q' => 30], ['id' => 2, 'q' => 0], ['id' => 3, 'q' => 50]];
$split = StockAllocationService::allocate($lots, 60, 'q');
DagrilIntegrationTest::check(count($split) === 2 && $split[0]['quantity'] == 30 && $split[1]['lot']['id'] === 3 && $split[1]['quantity'] == 30, 'Répartition : lot épuisé ignoré, ordre respecté');
DagrilIntegrationTest::expectException(function () use ($lots) { StockAllocationService::allocate($lots, 81, 'q'); }, 'Répartition : quantité supérieure au total refusée');
DagrilIntegrationTest::expectException(function () use ($lots) { StockAllocationService::allocate($lots, 0, 'q'); }, 'Répartition : quantité nulle refusée');

try {
    DagrilIntegrationTest::check((new Packaging())->hasLinesTable(), 'Migration 052 appliquée');
    $admin = DagrilIntegrationTest::loginRole('administrateur', 'all');
    $site = (int) DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='MINO'");
    $feed = (int) DagrilIntegrationTest::scalar('SELECT id FROM machine_feeds ORDER BY id LIMIT 1');
    $format = DagrilIntegrationTest::row("SELECT id, weight_kg FROM bag_formats WHERE status='active' AND weight_kg = 10 ORDER BY id LIMIT 1");
    // Produit isolé : aucun lot réel ne peut interférer avec la répartition.
    $code = 'TST-VRAC-' . random_int(10000, 99999);
    $q("INSERT INTO products(name, code, category) VALUES('Farine test vrac', ?, 'finished_product')", [$code]);
    $product = (int) $db->lastInsertId();
    foreach ([['2001-01-01 08:00:00', 30], ['2001-01-02 08:00:00', 40], ['2001-01-03 08:00:00', 50]] as $i => $lot) {
        $q("INSERT INTO production_batches(site_id,machine_feed_id,product_id,batch_number,input_quantity_kg,actual_input_quantity_kg,output_quantity_kg,waste_quantity_kg,started_at,ended_at,status,created_by,validated_by) VALUES(?,?,?,?,?,?,?,0,?,?,'validated',?,?)",
            [$site, $feed, $product, $code . '-L' . ($i + 1), $lot[1], $lot[1], $lot[1], $lot[0], $lot[0], $admin['id'], $admin['id']]);
        $fx['batches'][] = (int) $db->lastInsertId();
        $q("INSERT INTO bulk_flour_stocks(site_id,product_id,production_batch_id,quantity_kg,status) VALUES(?,?,?,?,'active')", [$site, $product, end($fx['batches']), $lot[1]]);
        $fx['bulks'][] = (int) $db->lastInsertId();
    }
    $key = Packaging::stockKey($site, $product);
    $model = new Packaging();
    $available = $model->findAvailableProduct($key);
    DagrilIntegrationTest::check($available && (float) $available['available_quantity_kg'] === 120.0 && (int) $available['lots_count'] === 3, 'Stock global : 120 kg sur 3 lots');

    // 5 sacs de 10 kg = 50 kg : 30 kg du lot 1 puis 20 kg du lot 2.
    $packagingId = $model->createPackaging(['stock_key' => $key, 'bag_format_id' => $format['id'], 'bags_count' => 5, 'packaged_at' => date('Y-m-d H:i:s')], $admin);
    $fx['packaging'][] = $packagingId;
    $lines = $q('SELECT production_batch_id, quantity_kg FROM packaging_lines WHERE packaging_id = ? ORDER BY id', [$packagingId])->fetchAll();
    DagrilIntegrationTest::check(count($lines) === 2 && (int) $lines[0]['production_batch_id'] === $fx['batches'][0] && (float) $lines[0]['quantity_kg'] === 30.0 && (int) $lines[1]['production_batch_id'] === $fx['batches'][1] && (float) $lines[1]['quantity_kg'] === 20.0, 'Prélèvement : 30 kg du lot le plus ancien puis 20 kg du suivant');
    $stocks = array_map('floatval', $q('SELECT quantity_kg FROM bulk_flour_stocks WHERE id IN (' . implode(',', $fx['bulks']) . ') ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    DagrilIntegrationTest::check($stocks === [0.0, 20.0, 50.0], 'Stocks des lots : 0 / 20 / 50 kg');
    DagrilIntegrationTest::check(DagrilIntegrationTest::scalar("SELECT status FROM bulk_flour_stocks WHERE id = ?", [$fx['bulks'][0]]) === 'depleted', 'Lot épuisé marqué « depleted »');
    DagrilIntegrationTest::check((int) DagrilIntegrationTest::scalar("SELECT COUNT(*) FROM bulk_flour_stock_movements WHERE packaging_id = ?", [$packagingId]) === 2, 'Un mouvement de vrac par lot');
    DagrilIntegrationTest::check((float) DagrilIntegrationTest::scalar('SELECT total_weight_kg FROM finished_stocks WHERE packaging_id = ?', [$packagingId]) === 50.0, 'Stock fini : 50 kg');

    // Refus sans effet partiel : 8 sacs = 80 kg pour 70 kg restants.
    $before = $q('SELECT SUM(quantity_kg) FROM bulk_flour_stocks WHERE product_id = ?', [$product])->fetchColumn();
    DagrilIntegrationTest::expectException(function () use ($model, $key, $format, $admin) { $model->createPackaging(['stock_key' => $key, 'bag_format_id' => $format['id'], 'bags_count' => 8, 'packaged_at' => date('Y-m-d H:i:s')], $admin); }, 'Stock global insuffisant refusé');
    DagrilIntegrationTest::check($q('SELECT SUM(quantity_kg) FROM bulk_flour_stocks WHERE product_id = ?', [$product])->fetchColumn() === $before, 'Refus sans effet partiel');
    DagrilIntegrationTest::expectException(function () use ($model, $format, $admin) { $model->createPackaging(['stock_key' => 'abc', 'bag_format_id' => $format['id'], 'bags_count' => 1, 'packaged_at' => date('Y-m-d H:i:s')], $admin); }, 'Produit à conditionner invalide refusé');

    // Le lot 2 n'est référencé que par une ligne : son annulation de production doit rester bloquée.
    $approver = DagrilIntegrationTest::loginRole('direction', 'all');
    $service = new CancellationService($db);
    Auth::login($admin);
    DagrilIntegrationTest::expectException(function () use ($service, $fx, $admin, $approver) {
        $request = $service->request('production', $fx['batches'][1], 'Test : lot partiellement conditionné', $admin);
        $service->approve($request, $approver);
    }, 'Annulation de production bloquée pour un lot prélevé via une ligne');
    DagrilIntegrationTest::check((float) DagrilIntegrationTest::scalar('SELECT quantity_kg FROM bulk_flour_stocks WHERE id = ?', [$fx['bulks'][1]]) === 20.0, 'Lot 2 intact après le refus');

    // Annulation : chaque lot retrouve sa propre quantité.
    Auth::login($admin);
    $request = $service->request('packaging', $packagingId, 'Test : emballage global à annuler', $admin);
    $service->approve($request, $approver);
    $stocks = array_map('floatval', $q('SELECT quantity_kg FROM bulk_flour_stocks WHERE id IN (' . implode(',', $fx['bulks']) . ') ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    DagrilIntegrationTest::check($stocks === [30.0, 40.0, 50.0], 'Annulation : 30 kg rendus au lot 1, 20 kg au lot 2');
} catch (Exception $e) {
    DagrilIntegrationTest::check(false, 'Exception inattendue : ' . $e->getMessage());
} finally {
    foreach ($fx['packaging'] as $id) {
        $q('DELETE FROM stock_movements WHERE finished_stock_id IN (SELECT id FROM finished_stocks WHERE packaging_id = ?)', [$id]);
        $q('DELETE FROM finished_stocks WHERE packaging_id = ?', [$id]);
        $q('DELETE FROM bulk_flour_stock_movements WHERE packaging_id = ?', [$id]);
        $q('DELETE FROM packaging_lines WHERE packaging_id = ?', [$id]);
    }
    if ($fx['packaging']) {
        $ph = implode(',', array_fill(0, count($fx['packaging']), '?'));
        foreach (["DELETE FROM packaging WHERE id IN ($ph)"] as $sql) {
            try { $q($sql, $fx['packaging']); } catch (Exception $ignored) { }
        }
    }
    $cleanCancellations = function ($type, $id) use ($q) {
        foreach (['DELETE FROM cancellation_stock_effects WHERE reversal_id IN (SELECT id FROM cancellation_reversals WHERE entity_type = ? AND entity_id = ?)', 'DELETE FROM cancellation_reversals WHERE entity_type = ? AND entity_id = ?', 'DELETE FROM cancellation_events WHERE request_id IN (SELECT id FROM cancellation_requests WHERE entity_type = ? AND entity_id = ?)', 'DELETE FROM cancellation_requests WHERE entity_type = ? AND entity_id = ?'] as $sql) {
            try { $q($sql, [$type, $id]); } catch (Exception $ignored) { }
        }
    };
    foreach ($fx['packaging'] as $id) { $cleanCancellations('packaging', $id); }
    foreach ($fx['batches'] as $id) { $cleanCancellations('production', $id); }
    foreach ($fx['batches'] as $id) {
        foreach (['DELETE FROM bulk_flour_stock_movements WHERE production_batch_id = ?', 'DELETE FROM bulk_flour_stocks WHERE production_batch_id = ?', 'DELETE FROM production_batches WHERE id = ?'] as $sql) {
            try { $q($sql, [$id]); } catch (Exception $ignored) { }
        }
    }
    if (!empty($product)) { try { $q('DELETE FROM products WHERE id = ?', [$product]); } catch (Exception $ignored) { } }
}
DagrilIntegrationTest::finish();
