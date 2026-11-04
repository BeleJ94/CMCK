<?php
// Transport ferme → silo par quantité globale : choix ferme + produit + variété, récoltes les plus anciennes d'abord,
// brouillons déduits du disponible, nouvelle répartition à la validation, réservation et expédition lot par lot.
// Base de test obligatoire (_test/_testing) avec la migration 053.
require __DIR__ . '/integration/TestBootstrap.php';
$db = DagrilIntegrationTest::boot();
require_once dirname(__DIR__) . '/app/models/Agriculture.php';
$q = function ($sql, $p = []) use ($db) { $s = $db->prepare($sql); $s->execute($p); return $s; };
$v = function ($sql, $p = []) use ($q) { return $q($sql, $p)->fetchColumn(); };
$fx = ['harvests' => [], 'stocks' => [], 'transports' => []];
try {
    $creator = DagrilIntegrationTest::loginRole('administrateur', 'all');
    $validator = DagrilIntegrationTest::loginRole('direction', 'all');
    $plan = $q("SELECT cp.id, c.site_id, cp.variety_id, v.product_id FROM agricultural_campaign_plots cp JOIN agricultural_campaigns c ON c.id=cp.campaign_id JOIN agricultural_varieties v ON v.id=cp.variety_id JOIN sites s ON s.id=c.site_id WHERE s.code IN ('FARM-MUT','FARM-DIK') ORDER BY cp.id LIMIT 1")->fetch();
    if (!$plan) { throw new RuntimeException('Aucune campagne agricole de ferme dans la base de test.'); }
    $model = new Agriculture();
    DagrilIntegrationTest::check($model->hasTransportLines(), 'Migration 053 appliquée');
    $key = Agriculture::transportKey($plan['site_id'], $plan['product_id'], $plan['variety_id']);
    $freeOf = function () use ($model, $key) { foreach ($model->transportProducts() as $g) { if ($g['key'] === $key) { return $g; } } return null; };
    Auth::login($creator);
    $before = $freeOf();
    $baseFree = $before ? (float) $before['free_kg'] : 0.0;
    // Trois récoltes de test plus anciennes que toutes les autres : 3 000 kg (dont 1 000 réservés), 4 000 kg, 5 000 kg.
    $suffix = random_int(10000, 99999);
    foreach ([['2000-01-01 08:00:00', 3000, 1000], ['2000-01-02 08:00:00', 4000, 0], ['2000-01-03 08:00:00', 5000, 0]] as $i => $lot) {
        $q("INSERT INTO agricultural_harvests(site_id,campaign_plot_id,harvest_number,harvested_at,gross_weight_kg,net_weight_kg,status,created_by,validated_by,validated_at) VALUES(?,?,?,?,?,?,'validated',?,?,NOW())", [$plan['site_id'], $plan['id'], 'TST-GLOB-' . $suffix . '-' . ($i + 1), $lot[0], $lot[1], $lot[1], $creator['id'], $creator['id']]);
        $fx['harvests'][] = (int) $db->lastInsertId();
        $q("INSERT INTO agricultural_stocks(site_id,harvest_id,product_id,variety_id,physical_quantity_kg,reserved_quantity_kg,status) VALUES(?,?,?,?,?,?,?)", [$plan['site_id'], end($fx['harvests']), $plan['product_id'], $plan['variety_id'], $lot[1], $lot[2], $lot[2] ? 'reserved' : 'available']);
        $fx['stocks'][] = (int) $db->lastInsertId();
    }
    $group = $freeOf();
    DagrilIntegrationTest::check($group && abs((float) $group['free_kg'] - ($baseFree + 11000)) < 0.001 && $group['lots'][0]['id'] === $fx['stocks'][0] && (float) $group['lots'][0]['kg'] === 2000.0, 'Disponible global : +11 000 kg, récolte la plus ancienne en tête (2 000 kg libres)');

    $bt = function ($kg) use ($key) { return ['stock_key' => $key, 'quantity_kg' => $kg, 'truck_plate' => 'TST-GLOB', 'driver_name' => 'Chauffeur test', 'route_notes' => '']; };
    // 5 000 kg : 2 000 kg libres de la récolte 1 puis 3 000 kg de la récolte 2.
    $fx['transports'][] = $id = $model->createTransport($bt(5000), $creator);
    $lines = $q('SELECT stock_id, quantity_kg FROM agricultural_transport_lines WHERE transport_id=? ORDER BY id', [$id])->fetchAll();
    DagrilIntegrationTest::check(count($lines) === 2 && (int) $lines[0]['stock_id'] === $fx['stocks'][0] && (float) $lines[0]['quantity_kg'] === 2000.0 && (int) $lines[1]['stock_id'] === $fx['stocks'][1] && (float) $lines[1]['quantity_kg'] === 3000.0, 'BT : 2 000 kg de la récolte la plus ancienne puis 3 000 kg de la suivante');
    DagrilIntegrationTest::check((float) $v('SELECT SUM(reserved_quantity_kg) FROM agricultural_stocks WHERE id IN (' . implode(',', $fx['stocks']) . ')') === 1000.0, 'Brouillon sans réservation');

    // Second brouillon : il démarre après les quantités déjà promises (récolte 2 : 1 000 kg restants).
    $fx['transports'][] = $id2 = $model->createTransport($bt(1500), $creator);
    $lines2 = $q('SELECT stock_id, quantity_kg FROM agricultural_transport_lines WHERE transport_id=? ORDER BY id', [$id2])->fetchAll();
    DagrilIntegrationTest::check(count($lines2) === 2 && (int) $lines2[0]['stock_id'] === $fx['stocks'][1] && (float) $lines2[0]['quantity_kg'] === 1000.0 && (int) $lines2[1]['stock_id'] === $fx['stocks'][2] && (float) $lines2[1]['quantity_kg'] === 500.0, 'Second brouillon : brouillon existant déduit (1 000 kg puis 500 kg)');
    $free = (float) $freeOf()['free_kg'];
    DagrilIntegrationTest::check(abs($free - ($baseFree + 11000 - 6500)) < 0.001, 'Disponible affiché : brouillons déduits');
    $count = (int) $v('SELECT COUNT(*) FROM agricultural_transports');
    DagrilIntegrationTest::expectException(function () use ($model, $bt, $free, $creator) { $model->createTransport($bt($free + 1), $creator); }, 'Quantité supérieure au disponible global refusée');
    DagrilIntegrationTest::check((int) $v('SELECT COUNT(*) FROM agricultural_transports') === $count, 'Refus sans BT partiel');

    // Avant validation, la récolte 1 est réservée par ailleurs : la validation répartit à nouveau.
    $q("UPDATE agricultural_stocks SET reserved_quantity_kg=physical_quantity_kg, status='reserved' WHERE id=?", [$fx['stocks'][0]]);
    Auth::login($validator);
    $model->approveTransport($id, $validator);
    $lines = $q('SELECT stock_id, quantity_kg FROM agricultural_transport_lines WHERE transport_id=? ORDER BY id', [$id])->fetchAll();
    DagrilIntegrationTest::check(count($lines) === 2 && (int) $lines[0]['stock_id'] === $fx['stocks'][1] && (float) $lines[0]['quantity_kg'] === 3000.0 && (int) $lines[1]['stock_id'] === $fx['stocks'][2] && (float) $lines[1]['quantity_kg'] === 2000.0, 'Validation : nouvelle répartition 3 000 + 2 000 kg (le second brouillon reste prioritaire sur ses 1 000 kg)');
    $reserved = array_map('floatval', $q('SELECT reserved_quantity_kg FROM agricultural_stocks WHERE id IN (' . implode(',', $fx['stocks']) . ') ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    DagrilIntegrationTest::check($reserved === [3000.0, 3000.0, 2000.0], 'Réservations par récolte : 3 000 (externe) / 3 000 / 2 000');
    DagrilIntegrationTest::check((int) $v('SELECT stock_id FROM agricultural_transports WHERE id=?', [$id]) === $fx['stocks'][1], 'Récolte principale du BT mise à jour');

    // Expédition : sortie physique lot par lot, un seul transport au pont-bascule.
    $bridge = $model->dispatchTransport($id, $validator);
    $physical = array_map('floatval', $q('SELECT physical_quantity_kg FROM agricultural_stocks WHERE id IN (' . implode(',', $fx['stocks']) . ') ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    DagrilIntegrationTest::check($physical === [3000.0, 1000.0, 3000.0], 'Expédition : 3 000 kg sortis de la récolte 2 et 2 000 kg de la récolte 3');
    DagrilIntegrationTest::check((float) $v('SELECT shipped_quantity_kg FROM weighbridge_transports WHERE id=?', [$bridge]) === 5000.0, 'Pont-bascule : un seul camion de 5 000 kg');
    DagrilIntegrationTest::check((int) $v("SELECT COUNT(*) FROM agricultural_stock_movements WHERE reference_type='agricultural_transports' AND reference_id=? AND movement_type='transport_out'", [$id]) === 2, 'Un mouvement de sortie par récolte');
} catch (Exception $e) {
    DagrilIntegrationTest::check(false, 'Exception inattendue : ' . $e->getMessage());
} finally {
    foreach ($fx['transports'] as $id) {
        $wids = $q('SELECT id FROM weighbridge_transports WHERE agricultural_transport_id=?', [$id])->fetchAll(PDO::FETCH_COLUMN);
        foreach ($wids as $wid) { $q("DELETE FROM activity_logs WHERE entity_type='weighbridge_transports' AND entity_id=?", [$wid]); $q('DELETE FROM weighbridge_transports WHERE id=?', [$wid]); }
        $docs = $q("SELECT id FROM documents WHERE entity_type='agricultural_transports' AND entity_id=?", [$id])->fetchAll(PDO::FETCH_COLUMN);
        foreach ($docs as $doc) {
            foreach (['workflow_notifications', 'workflow_level_approvals', 'workflow_transitions'] as $t) { $q("DELETE FROM $t WHERE workflow_instance_id IN (SELECT id FROM workflow_instances WHERE document_id=?)", [$doc]); }
            $q('DELETE FROM workflow_instances WHERE document_id=?', [$doc]); $q('DELETE FROM document_status_history WHERE document_id=?', [$doc]); $q('DELETE FROM documents WHERE id=?', [$doc]);
        }
        $q('DELETE FROM agricultural_transport_lines WHERE transport_id=?', [$id]);
        $q('DELETE FROM agricultural_transports WHERE id=?', [$id]);
    }
    if ($fx['transports']) { $q("DELETE FROM trucks WHERE plate_number='TST-GLOB'"); }
    foreach ($fx['stocks'] as $sid) { $q('DELETE FROM agricultural_stock_movements WHERE stock_id=?', [$sid]); $q('DELETE FROM agricultural_stocks WHERE id=?', [$sid]); }
    foreach ($fx['harvests'] as $hid) { $q('DELETE FROM agricultural_harvests WHERE id=?', [$hid]); }
}
DagrilIntegrationTest::finish();
