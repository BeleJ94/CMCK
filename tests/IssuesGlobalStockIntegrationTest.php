<?php
// Issues → aliment bétail par quantité globale : BTI vers PELL et ordre de fabrication par type d'issue,
// lots les plus anciens d'abord, quantités promises déduites, nouvelle répartition, déduction et annulation lot par lot.
// Base de test obligatoire (_test/_testing). Type d'issue de test isolé.
require __DIR__ . '/integration/TestBootstrap.php';
$db = DagrilIntegrationTest::boot();
require_once dirname(__DIR__) . '/app/models/Pelletization.php';
require_once dirname(__DIR__) . '/app/services/CancellationService.php';
$q = function ($sql, $p = []) use ($db) { $s = $db->prepare($sql); $s->execute($p); return $s; };
$v = function ($sql, $p = []) use ($q) { return $q($sql, $p)->fetchColumn(); };
$fx = ['type' => 0, 'stocks' => [], 'transfers' => [], 'orders' => [], 'recipe' => 0, 'additiveStock' => 0, 'additiveBefore' => null];
$qtys = function (array $ids) use ($q) { return array_map('floatval', $q('SELECT quantity_kg FROM waste_stocks WHERE id IN (' . implode(',', $ids) . ') ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)); };
try {
    $creator = DagrilIntegrationTest::loginRole('administrateur', 'all');
    $validator = DagrilIntegrationTest::loginRole('direction', 'all');
    $mino = (int) $v("SELECT id FROM sites WHERE code='MINO'");
    $pell = (int) $v("SELECT id FROM sites WHERE code='PELL'");
    $product = (int) $v("SELECT id FROM products WHERE code='DECHETS-MAIS'");
    $additive = (int) $v("SELECT id FROM pellet_additives WHERE status='active' ORDER BY id LIMIT 1");
    $q("INSERT INTO waste_types(code,name,product_id,status) VALUES(?,?,?,'active')", ['TST-ISS-' . random_int(10000, 99999), 'Son test global', $product]);
    $fx['type'] = $type = (int) $db->lastInsertId();
    $lot = function ($site, $kg, $date) use ($q, $db, $product, $type, &$fx) { $q("INSERT INTO waste_stocks(site_id,product_id,waste_type_id,quantity_kg,average_unit_cost,available_at,storage_status,status) VALUES(?,?,?,?,0.5,?,'available','active')", [$site, $product, $type, $kg, $date]); return $fx['stocks'][] = (int) $db->lastInsertId(); };
    $m1 = $lot($mino, 300, '2001-01-01 08:00:00'); $m2 = $lot($mino, 400, '2001-01-02 08:00:00'); $m3 = $lot($mino, 500, '2001-01-03 08:00:00');
    $model = new Pelletization();
    $group = function ($site) use ($model, $type) { foreach ($model->issueGroups() as $g) { if ($g['site_id'] === $site && $g['waste_type_id'] === $type) { return $g; } } return null; };
    Auth::login($creator); Auth::selectSite((string) $mino);
    $g = $group($mino);
    DagrilIntegrationTest::check($g && (float) $g['free_kg'] === 1200.0 && count($g['lots']) === 3 && $g['lots'][0]['id'] === $m1, 'Issues du site : 1 200 kg sur 3 lots, plus ancien en tête');

    // BTI de 500 kg : 300 kg du lot 1 puis 200 kg du lot 2.
    $fx['transfers'][] = $t1 = $model->createWasteTransfer(['issue_key' => $mino . ':' . $type, 'quantity_kg' => 500], $creator);
    $lines = $q('SELECT source_waste_stock_id s, quantity_kg k FROM waste_transfer_lines WHERE transfer_id=? ORDER BY id', [$t1])->fetchAll();
    DagrilIntegrationTest::check(count($lines) === 2 && (int) $lines[0]['s'] === $m1 && (float) $lines[0]['k'] === 300.0 && (int) $lines[1]['s'] === $m2 && (float) $lines[1]['k'] === 200.0, 'BTI : 300 kg du lot le plus ancien puis 200 kg du suivant');
    DagrilIntegrationTest::check((float) $group($mino)['free_kg'] === 700.0, 'Disponible : BTI en brouillon déduit');
    $count = (int) $v('SELECT COUNT(*) FROM waste_transfers');
    DagrilIntegrationTest::expectException(function () use ($model, $mino, $type, $creator) { $model->createWasteTransfer(['issue_key' => $mino . ':' . $type, 'quantity_kg' => 701], $creator); }, 'BTI au-delà du disponible refusé');
    DagrilIntegrationTest::check((int) $v('SELECT COUNT(*) FROM waste_transfers') === $count, 'Refus sans BTI partiel');

    // Le lot 1 est consommé ailleurs avant l'expédition : nouvelle répartition sur les lots 2 et 3.
    $q('UPDATE waste_stocks SET quantity_kg=100 WHERE id=?', [$m1]);
    Auth::login($validator); Auth::selectSite((string) $mino);
    $model->shipWasteTransfer($t1, $validator);
    $lines = $q('SELECT source_waste_stock_id s, quantity_kg k FROM waste_transfer_lines WHERE transfer_id=? ORDER BY id', [$t1])->fetchAll();
    DagrilIntegrationTest::check(count($lines) === 2 && (int) $lines[0]['s'] === $m1 && (float) $lines[0]['k'] === 100.0 && (int) $lines[1]['s'] === $m2 && (float) $lines[1]['k'] === 400.0, 'Expédition : nouvelle répartition 100 + 400 kg');
    DagrilIntegrationTest::check($qtys([$m1, $m2, $m3]) === [0.0, 0.0, 500.0], 'Stocks après expédition : 0 / 0 / 500 kg');

    // Réception à PELL : un stock par lot reçu.
    Auth::selectSite((string) $pell);
    $model->receiveWasteTransfer($t1, $validator);
    $received = array_map('intval', $q('SELECT destination_waste_stock_id FROM waste_transfer_lines WHERE transfer_id=? ORDER BY id', [$t1])->fetchAll(PDO::FETCH_COLUMN));
    foreach ($received as $rid) { $fx['stocks'][] = $rid; }
    DagrilIntegrationTest::check(count($received) === 2 && $qtys($received) === [100.0, 400.0], 'Réception : un stock PELL par lot (100 et 400 kg)');
    $p1 = $received[0]; $p2 = $received[1]; $p3 = $lot($pell, 600, date('Y-m-d H:i:s', time() + 60));
    DagrilIntegrationTest::check((float) $group($pell)['free_kg'] === 1100.0, 'Issues à PELL : 1 100 kg');

    // OF : recette 80 % issues / 20 % additif ; 500 kg → 400 kg d'issues sur les lots les plus anciens.
    Auth::login($creator);
    $fx['recipe'] = $model->createRecipeVersion(['code' => 'TST-ISS-REC-' . $type, 'name' => 'Recette issues test', 'output_form' => 'granule', 'waste_type_id' => $type, 'waste_percent' => 80, 'additive_id' => $additive, 'additive_percent' => 20, 'notes' => ''], $creator);
    $fx['additiveStock'] = (int) $v('SELECT id FROM pellet_additive_stocks WHERE site_id=? AND additive_id=?', [$pell, $additive]);
    if (!$fx['additiveStock']) { $q('INSERT INTO pellet_additive_stocks(site_id,additive_id,quantity_kg,average_unit_cost) VALUES(?,?,0,1)', [$pell, $additive]); $fx['additiveStock'] = (int) $db->lastInsertId(); }
    $fx['additiveBefore'] = (float) $v('SELECT quantity_kg FROM pellet_additive_stocks WHERE id=?', [$fx['additiveStock']]);
    $q('UPDATE pellet_additive_stocks SET quantity_kg=quantity_kg+1000 WHERE id=?', [$fx['additiveStock']]);
    $fx['orders'][] = $o1 = $model->createOrder(['recipe_version_id' => $fx['recipe'], 'waste_type_id' => $type, 'additive_id' => $additive, 'theoretical_input_kg' => 500], $creator);
    $cons = $q('SELECT waste_stock_id s, theoretical_quantity_kg t FROM pellet_order_waste_consumptions WHERE pellet_order_id=? ORDER BY id', [$o1])->fetchAll();
    DagrilIntegrationTest::check(count($cons) === 2 && (int) $cons[0]['s'] === $p1 && (float) $cons[0]['t'] === 100.0 && (int) $cons[1]['s'] === $p2 && (float) $cons[1]['t'] === 300.0, 'OF : 400 kg prévus, 100 kg du lot le plus ancien puis 300 kg');
    DagrilIntegrationTest::check((float) $group($pell)['free_kg'] === 700.0, 'Disponible PELL : fabrication en cours déduite');

    // Résultats : 450 kg réels restent sur les lots prévus (100 + 350 kg, le lot 2 contient 400 kg).
    $model->submitResults($o1, ['waste_actual_kg' => 450, 'additive_actual_kg' => 100, 'output_quantity_kg' => 520], $creator);
    $cons = $q('SELECT waste_stock_id s, actual_quantity_kg a FROM pellet_order_waste_consumptions WHERE pellet_order_id=? AND actual_quantity_kg>0 ORDER BY id', [$o1])->fetchAll();
    DagrilIntegrationTest::check(count($cons) === 2 && (int) $cons[0]['s'] === $p1 && (float) $cons[0]['a'] === 100.0 && (int) $cons[1]['s'] === $p2 && (float) $cons[1]['a'] === 350.0, 'Résultats : consommation réelle sur les lots prévus d’abord (100 + 350 kg)');
    Auth::login($validator);
    $model->validateOrder($o1, $validator);
    DagrilIntegrationTest::check($qtys([$p1, $p2, $p3]) === [0.0, 50.0, 600.0], 'Validation : déduction lot par lot (0 / 50 / 600 kg)');
    DagrilIntegrationTest::check((int) $v("SELECT COUNT(*) FROM waste_stock_movements WHERE reference_type='pellet_orders' AND reference_id=? AND movement_type='pellet_consumption'", [$o1]) === 2, 'Un mouvement de consommation par lot');
    $cost = (float) $v('SELECT cost_total FROM pellet_orders WHERE id=?', [$o1]);
    DagrilIntegrationTest::check($cost > 225 - 0.01, 'Coût : issues de tous les lots comptées (450 kg × 0,5)');

    // Annulation de l'OF : chaque lot récupère sa part.
    Auth::login($creator);
    $service = new CancellationService($db);
    $request = $service->request('pelletization', $o1, 'Test : OF global à annuler', $creator);
    $service->approve($request, $validator);
    DagrilIntegrationTest::check($qtys([$p1, $p2, $p3]) === [100.0, 400.0, 600.0], 'Annulation : 100 et 350 kg rendus à leurs lots');

    // Second OF : 40 kg prévus sur le lot 1, 150 kg réels → 100 kg du lot prévu puis complément de 50 kg sur le lot suivant.
    Auth::login($creator);
    $fx['orders'][] = $o2 = $model->createOrder(['recipe_version_id' => $fx['recipe'], 'waste_type_id' => $type, 'additive_id' => $additive, 'theoretical_input_kg' => 50], $creator);
    $model->submitResults($o2, ['waste_actual_kg' => 150, 'additive_actual_kg' => 10, 'output_quantity_kg' => 150], $creator);
    $cons = $q('SELECT waste_stock_id s, actual_quantity_kg a FROM pellet_order_waste_consumptions WHERE pellet_order_id=? AND actual_quantity_kg>0 ORDER BY id', [$o2])->fetchAll();
    DagrilIntegrationTest::check(count($cons) === 2 && (int) $cons[0]['s'] === $p1 && (float) $cons[0]['a'] === 100.0 && (int) $cons[1]['s'] === $p2 && (float) $cons[1]['a'] === 50.0, 'Résultats au-delà du prévu : complément sur le lot suivant le plus ancien');
    DagrilIntegrationTest::expectException(function () use ($model, $o2, $creator) { $model->submitResults($o2, ['waste_actual_kg' => 1, 'additive_actual_kg' => 0, 'output_quantity_kg' => 1], $creator); }, 'Résultats déjà soumis : nouvelle saisie refusée');
} catch (Exception $e) {
    DagrilIntegrationTest::check(false, 'Exception inattendue : ' . $e->getMessage());
} finally {
    $docs = function ($entity, $id) use ($q) { foreach ($q('SELECT id FROM documents WHERE entity_type=? AND entity_id=?', [$entity, $id])->fetchAll(PDO::FETCH_COLUMN) as $doc) { foreach (['workflow_notifications', 'workflow_level_approvals', 'workflow_transitions'] as $t) { $q("DELETE FROM $t WHERE workflow_instance_id IN (SELECT id FROM workflow_instances WHERE document_id=?)", [$doc]); } $q('DELETE FROM workflow_instances WHERE document_id=?', [$doc]); $q('DELETE FROM document_status_history WHERE document_id=?', [$doc]); $q('DELETE FROM documents WHERE id=?', [$doc]); } };
    $cancel = function ($type, $id) use ($q) { foreach (['DELETE FROM cancellation_stock_effects WHERE reversal_id IN (SELECT id FROM cancellation_reversals WHERE entity_type=? AND entity_id=?)', 'DELETE FROM cancellation_reversals WHERE entity_type=? AND entity_id=?', 'DELETE FROM cancellation_events WHERE request_id IN (SELECT id FROM cancellation_requests WHERE entity_type=? AND entity_id=?)', 'DELETE FROM cancellation_requests WHERE entity_type=? AND entity_id=?'] as $sql) { $q($sql, [$type, $id]); } };
    try {
        foreach ($fx['orders'] as $id) {
            $cancel('pelletization', $id); $docs('pellet_orders', $id);
            $q('DELETE m FROM pellet_stock_movements m JOIN pellet_stocks s ON s.id=m.pellet_stock_id WHERE s.pellet_order_id=?', [$id]); $q('DELETE FROM pellet_stocks WHERE pellet_order_id=?', [$id]);
            $q("DELETE FROM pellet_additive_movements WHERE reference_type='pellet_orders' AND reference_id=?", [$id]);
            $q('DELETE FROM pellet_order_waste_consumptions WHERE pellet_order_id=?', [$id]); $q('DELETE FROM pellet_order_additive_consumptions WHERE pellet_order_id=?', [$id]);
            $q("DELETE FROM activity_logs WHERE entity_type='pellet_orders' AND entity_id=?", [$id]); $q('DELETE FROM pellet_orders WHERE id=?', [$id]);
        }
        if ($fx['recipe']) { $rid = (int) $v('SELECT recipe_id FROM pellet_recipe_versions WHERE id=?', [$fx['recipe']]); $q('DELETE FROM pellet_recipe_wastes WHERE recipe_version_id=?', [$fx['recipe']]); $q('DELETE FROM pellet_recipe_additives WHERE recipe_version_id=?', [$fx['recipe']]); $q('DELETE FROM pellet_recipe_versions WHERE recipe_id=?', [$rid]); $q('DELETE FROM pellet_recipes WHERE id=?', [$rid]); }
        if ($fx['additiveStock'] && $fx['additiveBefore'] !== null) { $q('UPDATE pellet_additive_stocks SET quantity_kg=? WHERE id=?', [$fx['additiveBefore'], $fx['additiveStock']]); }
        foreach ($fx['transfers'] as $id) { $docs('waste_transfers', $id); $q('DELETE FROM waste_transfer_lines WHERE transfer_id=?', [$id]); $q('DELETE FROM waste_transfers WHERE id=?', [$id]); }
        foreach ($fx['stocks'] as $sid) { $q('DELETE FROM waste_stock_movements WHERE waste_stock_id=?', [$sid]); }
        foreach (array_reverse($fx['stocks']) as $sid) { $q('DELETE FROM waste_stocks WHERE id=?', [$sid]); }
        if ($fx['type']) { $q('DELETE FROM waste_types WHERE id=?', [$fx['type']]); }
    } catch (Exception $cleanup) { echo '  (nettoyage) ' . $cleanup->getMessage() . PHP_EOL; }
}
DagrilIntegrationTest::finish();
