<?php

require dirname(__DIR__) . '/app/helpers/functions.php';
require dirname(__DIR__) . '/app/core/Database.php';
require dirname(__DIR__) . '/app/core/Model.php';
require dirname(__DIR__) . '/app/core/Auth.php';
require dirname(__DIR__) . '/app/models/Weighing.php';
require dirname(__DIR__) . '/app/models/MachineFeed.php';
require dirname(__DIR__) . '/app/models/ProductionBatch.php';
require dirname(__DIR__) . '/app/models/Waste.php';
require dirname(__DIR__) . '/app/models/Packaging.php';
require dirname(__DIR__) . '/app/models/Distribution.php';
require dirname(__DIR__) . '/app/models/FinishedStock.php';
require dirname(__DIR__) . '/app/models/ReportModel.php';

Auth::start();

$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'BusinessRuleTest';

$db = Database::getInstance()->connection();
$user = [
    'id' => 1,
    'name' => 'Business Rule Test',
    'email' => 'test@cmck.local',
    'role_slug' => 'administrateur',
];
$ids = [
    'stock_movements' => [],
    'distributions' => [],
    'finished_stocks' => [],
    'packaging' => [],
    'waste_processings' => [],
    'waste_stocks' => [],
    'production_batches' => [],
    'machine_feeds' => [],
    'silo_movements' => [],
    'weighings' => [],
    'silos' => [],
    'machines' => [],
    'trucks' => [],
    'suppliers' => [],
];
$failures = [];

function run_query(PDO $db, $sql, array $params = [])
{
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function scalar(PDO $db, $sql, array $params = [])
{
    return run_query($db, $sql, $params)->fetchColumn();
}

function assert_true($condition, $message)
{
    global $failures;

    if ($condition) {
        echo "OK  - {$message}\n";
        return;
    }

    echo "FAIL - {$message}\n";
    $failures[] = $message;
}

function assert_near($actual, $expected, $message, $epsilon = 0.001)
{
    assert_true(abs((float) $actual - (float) $expected) <= $epsilon, $message . " (attendu {$expected}, obtenu {$actual})");
}

function cleanup(PDO $db, array $ids)
{
    run_query($db, "DELETE FROM activity_logs WHERE user_agent = 'BusinessRuleTest'");

    foreach ([
        'stock_movements',
        'distributions',
        'finished_stocks',
        'packaging',
        'waste_processings',
        'waste_stocks',
        'production_batches',
        'machine_feeds',
        'silo_movements',
        'weighings',
        'silos',
        'machines',
        'trucks',
        'suppliers',
    ] as $table) {
        if (empty($ids[$table])) {
            continue;
        }

        $placeholders = implode(',', array_fill(0, count($ids[$table]), '?'));
        run_query($db, "DELETE FROM {$table} WHERE id IN ({$placeholders})", array_values(array_unique($ids[$table])));
    }
}

try {
    $rawProductId = (int) scalar($db, "SELECT id FROM products WHERE code = 'MAIS-BRUT' LIMIT 1");
    $flourProductId = (int) scalar($db, "SELECT id FROM products WHERE code = 'FARINE-MAIS' LIMIT 1");
    $wasteProductId = (int) scalar($db, "SELECT id FROM products WHERE code = 'DECHETS-MAIS' LIMIT 1");
    $animalFeedId = (int) scalar($db, "SELECT id FROM products WHERE code = 'ALIMENT-BETAIL' LIMIT 1");
    $bagFormatId = (int) scalar($db, "SELECT id FROM bag_formats WHERE weight_kg = 25 LIMIT 1");

    foreach ([$rawProductId, $flourProductId, $wasteProductId, $animalFeedId, $bagFormatId] as $requiredId) {
        if ($requiredId <= 0) {
            throw new RuntimeException('Donnees de reference manquantes.');
        }
    }

    run_query($db, "INSERT INTO suppliers (name, phone, address, rccm, id_nat, status) VALUES ('TST Supplier QA', '+243 000', 'QA', 'TST-RCCM', 'TST-ID', 'active')");
    $supplierId = (int) $db->lastInsertId();
    $ids['suppliers'][] = $supplierId;

    run_query($db, "INSERT INTO trucks (supplier_id, plate_number, driver_name, driver_phone, status) VALUES (?, 'TST-QA-001', 'QA Driver', '+243 001', 'active')", [$supplierId]);
    $truckId = (int) $db->lastInsertId();
    $ids['trucks'][] = $truckId;

    run_query($db, "INSERT INTO silos (name, code, product_id, capacity_kg, current_stock_kg, alert_threshold_kg, status) VALUES ('TST Silo QA', 'TST-SILO-QA', ?, 10000, 1000, 100, 'active')", [$rawProductId]);
    $siloId = (int) $db->lastInsertId();
    $ids['silos'][] = $siloId;

    run_query($db, "INSERT INTO machines (name, code, machine_type, capacity_kg_hour, status) VALUES ('TST Mill QA', 'TST-MILL-QA', 'main', 1000, 'active')");
    $machineId = (int) $db->lastInsertId();
    $ids['machines'][] = $machineId;

    run_query($db, "INSERT INTO machines (name, code, machine_type, capacity_kg_hour, status) VALUES ('TST Waste QA', 'TST-WASTE-QA', 'waste', 500, 'active')");
    $wasteMachineId = (int) $db->lastInsertId();
    $ids['machines'][] = $wasteMachineId;

    $weighingModel = new Weighing();
    $encodedWeighingId = $weighingModel->createEntry([
        'supplier_id' => $supplierId,
        'truck_plate_number' => 'TST-QA-NEW',
        'driver_name' => 'QA New Driver',
        'driver_phone' => '+243 009',
        'product_id' => $rawProductId,
        'poids_brut' => 900,
    ], $user);
    $ids['weighings'][] = (int) $encodedWeighingId;
    $encodedTruckId = (int) scalar($db, "SELECT id FROM trucks WHERE plate_number = 'TST-QA-NEW' LIMIT 1");
    $ids['trucks'][] = $encodedTruckId;
    assert_true($encodedTruckId > 0, 'Reception cree le camion encode si la plaque est nouvelle');
    assert_true((int) scalar($db, 'SELECT truck_id FROM weighings WHERE id = ?', [$encodedWeighingId]) === $encodedTruckId, 'Reception lie la pesee au camion encode');

    run_query($db, "INSERT INTO weighings (supplier_id, truck_id, product_id, reference, poids_brut, poids_tare, poids_net, weighed_at, status, created_by) VALUES (?, ?, ?, 'TST-PB-INVALID', 1000, 0, 0, NOW(), 'pending', ?)", [$supplierId, $truckId, $rawProductId, $user['id']]);
    $invalidWeighingId = (int) $db->lastInsertId();
    $ids['weighings'][] = $invalidWeighingId;

    $invalidRejected = false;
    try {
        $weighingModel->validateExit($invalidWeighingId, ['poids_tare' => 1200, 'silo_id' => $siloId], $user);
    } catch (Exception $exception) {
        $invalidRejected = true;
    }
    assert_true($invalidRejected, 'Impossible de valider une pesee avec tare superieure au brut');
    assert_near(scalar($db, 'SELECT current_stock_kg FROM silos WHERE id = ?', [$siloId]), 1000, 'Le stock silo ne bouge pas apres pesee invalide');

    run_query($db, "INSERT INTO weighings (supplier_id, truck_id, product_id, reference, poids_brut, poids_tare, poids_net, weighed_at, status, created_by) VALUES (?, ?, ?, 'TST-PB-VALID', 1500, 0, 0, NOW(), 'pending', ?)", [$supplierId, $truckId, $rawProductId, $user['id']]);
    $validWeighingId = (int) $db->lastInsertId();
    $ids['weighings'][] = $validWeighingId;

    $weighingModel->validateExit($validWeighingId, ['poids_tare' => 400, 'silo_id' => $siloId], $user);
    $ids['silo_movements'][] = (int) scalar($db, 'SELECT id FROM silo_movements WHERE weighing_id = ?', [$validWeighingId]);
    assert_near(scalar($db, 'SELECT poids_net FROM weighings WHERE id = ?', [$validWeighingId]), 1100, 'Poids net calcule correctement');
    assert_near(scalar($db, 'SELECT current_stock_kg FROM silos WHERE id = ?', [$siloId]), 2100, 'Entree silo augmente le stock silo');

    $feedModel = new MachineFeed();
    $overFeedRejected = false;
    try {
        $feedModel->createFeed([
            'silo_id' => $siloId,
            'machine_id' => $machineId,
            'quantity_kg' => 2200,
            'fed_at' => date('Y-m-d H:i:s'),
            'ended_at' => '',
            'observation' => 'QA overfeed',
        ], $user);
    } catch (Exception $exception) {
        $overFeedRejected = true;
    }
    assert_true($overFeedRejected, 'Impossible d alimenter machine au-dela du stock disponible');

    $feedId = $feedModel->createFeed([
        'silo_id' => $siloId,
        'machine_id' => $machineId,
        'quantity_kg' => 600,
        'fed_at' => date('Y-m-d H:i:s'),
        'ended_at' => '',
        'observation' => 'QA feed',
    ], $user);
    $ids['machine_feeds'][] = (int) $feedId;
    $ids['silo_movements'][] = (int) scalar($db, 'SELECT silo_movement_id FROM machine_feeds WHERE id = ?', [$feedId]);
    $batchId = (int) scalar($db, 'SELECT id FROM production_batches WHERE machine_feed_id = ?', [$feedId]);
    $ids['production_batches'][] = $batchId;
    assert_near(scalar($db, 'SELECT current_stock_kg FROM silos WHERE id = ?', [$siloId]), 1500, 'Alimentation machine diminue le stock silo');

    (new ProductionBatch())->validateProduction([
        'production_batch_id' => $batchId,
        'output_quantity_kg' => 480,
        'waste_quantity_kg' => 120,
        'ended_at' => date('Y-m-d H:i:s'),
    ], $user);
    $ids['stock_movements'][] = (int) scalar($db, "SELECT id FROM stock_movements WHERE product_id = ? AND quantity_kg = 480 ORDER BY id DESC LIMIT 1", [$flourProductId]);
    $wasteStockId = (int) scalar($db, 'SELECT id FROM waste_stocks WHERE production_batch_id = ?', [$batchId]);
    $ids['waste_stocks'][] = $wasteStockId;
    $batch = run_query($db, 'SELECT input_quantity_kg, output_quantity_kg, waste_quantity_kg FROM production_batches WHERE id = ?', [$batchId])->fetch();
    assert_near(((float) $batch['output_quantity_kg'] / (float) $batch['input_quantity_kg']) * 100, 80, 'Production calcule le rendement correctement');
    assert_near(scalar($db, 'SELECT quantity_kg FROM waste_stocks WHERE id = ?', [$wasteStockId]), 120, 'Dechets augmentent le stock dechets');

    $wasteTotalBeforeProcessing = (new Waste())->totalAvailable();
    $animalStockBefore = (float) scalar($db, "SELECT COALESCE((SELECT stock_after_kg FROM stock_movements WHERE product_id = ? ORDER BY movement_at DESC, id DESC LIMIT 1), 0)", [$animalFeedId]);
    (new Waste())->processWaste([
        'machine_id' => $wasteMachineId,
        'input_quantity_kg' => 50,
        'output_quantity_kg' => 40,
        'processed_at' => date('Y-m-d H:i:s'),
    ], $user);
    $ids['waste_processings'] = array_merge($ids['waste_processings'], array_map('intval', run_query($db, 'SELECT id FROM waste_processings WHERE created_by = ? AND machine_id = ? ORDER BY id DESC LIMIT 1', [$user['id'], $wasteMachineId])->fetchAll(PDO::FETCH_COLUMN)));
    $ids['stock_movements'][] = (int) scalar($db, "SELECT id FROM stock_movements WHERE product_id = ? AND quantity_kg = 40 ORDER BY id DESC LIMIT 1", [$animalFeedId]);
    assert_near($wasteTotalBeforeProcessing - (new Waste())->totalAvailable(), 50, 'Traitement dechets diminue le stock dechets');
    $animalStockAfter = (float) scalar($db, "SELECT stock_after_kg FROM stock_movements WHERE product_id = ? ORDER BY movement_at DESC, id DESC LIMIT 1", [$animalFeedId]);
    assert_near($animalStockAfter - $animalStockBefore, 40, 'Aliment betail augmente le stock fini');

    $finishedFlourBefore = (float) scalar($db, 'SELECT COALESCE(SUM(total_weight_kg), 0) FROM finished_stocks WHERE product_id = ? AND deleted_at IS NULL', [$flourProductId]);
    (new Packaging())->createPackaging([
        'production_batch_id' => $batchId,
        'bag_format_id' => $bagFormatId,
        'bags_count' => 10,
        'packaged_at' => date('Y-m-d H:i:s'),
    ], $user);
    $packagingId = (int) scalar($db, 'SELECT id FROM packaging WHERE production_batch_id = ? ORDER BY id DESC LIMIT 1', [$batchId]);
    $finishedStockId = (int) scalar($db, 'SELECT id FROM finished_stocks WHERE packaging_id = ?', [$packagingId]);
    $ids['packaging'][] = $packagingId;
    $ids['finished_stocks'][] = $finishedStockId;
    $ids['stock_movements'][] = (int) scalar($db, 'SELECT id FROM stock_movements WHERE finished_stock_id = ? AND movement_type = "in" ORDER BY id DESC LIMIT 1', [$finishedStockId]);
    $finishedFlourAfter = (float) scalar($db, 'SELECT COALESCE(SUM(total_weight_kg), 0) FROM finished_stocks WHERE product_id = ? AND deleted_at IS NULL', [$flourProductId]);
    assert_near($finishedFlourAfter - $finishedFlourBefore, 250, 'Emballage augmente le stock produit fini');

    $distributionModel = new Distribution();
    $overDistributionRejected = false;
    try {
        $distributionModel->createDistribution([
            'finished_stock_id' => $finishedStockId,
            'recipient_name' => 'QA Client',
            'transporter' => 'QA Transport',
            'exit_voucher' => 'TST-BS-OVER',
            'quantity_bags' => 11,
            'distributed_at' => date('Y-m-d H:i:s'),
        ], $user);
    } catch (Exception $exception) {
        $overDistributionRejected = true;
    }
    assert_true($overDistributionRejected, 'Impossible de distribuer au-dela du stock disponible');

    $distributionId = $distributionModel->createDistribution([
        'finished_stock_id' => $finishedStockId,
        'recipient_name' => 'QA Client',
        'transporter' => 'QA Transport',
        'exit_voucher' => 'TST-BS-VALID',
        'quantity_bags' => 4,
        'distributed_at' => date('Y-m-d H:i:s'),
    ], $user);
    $ids['distributions'][] = (int) $distributionId;
    $ids['stock_movements'][] = (int) scalar($db, 'SELECT id FROM stock_movements WHERE distribution_id = ?', [$distributionId]);
    assert_near(scalar($db, 'SELECT quantity_bags FROM finished_stocks WHERE id = ?', [$finishedStockId]), 6, 'Distribution diminue le stock produit fini en sacs');
    assert_near(scalar($db, 'SELECT total_weight_kg FROM finished_stocks WHERE id = ?', [$finishedStockId]), 150, 'Distribution diminue le stock produit fini en kg');

    $rolesToCheck = [
        'agent-pont-bascule' => ['Pont-bascule'],
        'agent-silo' => ['Silos', 'Alimentation'],
        'agent-production' => ['Production', 'Dechets'],
        'agent-emballage' => ['Emballage', 'Stock finis'],
        'agent-distribution' => ['Distribution', 'Stock finis'],
    ];
    foreach ($rolesToCheck as $roleSlug => $expectedLabels) {
        $_SESSION['user'] = [
            'id' => 999,
            'name' => 'QA',
            'email' => 'qa@example.test',
            'role_id' => 999,
            'role_name' => $roleSlug,
            'role_slug' => $roleSlug,
        ];
        $labels = array_column(Auth::menu(), 'label');
        foreach ($expectedLabels as $label) {
            assert_true(in_array($label, $labels, true), "Menu {$roleSlug} contient {$label}");
        }
        assert_true(!in_array('Rapports', $labels, true) && !in_array('Utilisateurs', $labels, true), "Menu {$roleSlug} masque rapports/utilisateurs");
    }
    unset($_SESSION['user']);

    $filters = ['start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d'), 'supplier_id' => $supplierId, 'machine_id' => $machineId];
    $reports = new ReportModel();
    $dailyRows = array_filter($reports->dailyReception($filters), function ($row) {
        return $row['reference'] === 'TST-PB-VALID';
    });
    assert_true(count($dailyRows) === 1, 'Rapport reception affiche la pesee test');
    assert_near(array_values($dailyRows)[0]['poids_net'], 1100, 'Rapport reception affiche le bon poids net');

    $productionRows = array_filter($reports->production($filters), function ($row) use ($batchId) {
        return $row['batch_number'] === scalar(Database::getInstance()->connection(), 'SELECT batch_number FROM production_batches WHERE id = ?', [$batchId]);
    });
    assert_true(count($productionRows) === 1, 'Rapport production affiche le lot test');
    assert_near(array_values($productionRows)[0]['yield_rate'], 80, 'Rapport production affiche le bon rendement');

    $wasteRows = $reports->waste(['start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d'), 'supplier_id' => '', 'machine_id' => $wasteMachineId]);
    $testWasteRows = array_filter($wasteRows, function ($row) {
        return (float) $row['input_quantity_kg'] === 50.0 && (float) $row['output_quantity_kg'] === 40.0;
    });
    assert_true(count($testWasteRows) >= 1, 'Rapport dechets affiche le traitement test');

    $packagingRows = $reports->packaging(['start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d'), 'supplier_id' => '', 'machine_id' => '']);
    $testPackagingRows = array_filter($packagingRows, function ($row) {
        return (int) $row['bags_count'] === 10 && (float) $row['total_weight_kg'] === 250.0;
    });
    assert_true(count($testPackagingRows) >= 1, 'Rapport emballage affiche l emballage test');

    $distributionRows = $reports->distribution(['start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d'), 'supplier_id' => '', 'machine_id' => '']);
    $testDistributionRows = array_filter($distributionRows, function ($row) {
        return $row['exit_voucher'] === 'TST-BS-VALID';
    });
    assert_true(count($testDistributionRows) === 1, 'Rapport distribution affiche le bon de sortie test');
    assert_near(array_values($testDistributionRows)[0]['total_weight_kg'], 100, 'Rapport distribution affiche le bon poids distribue');
} finally {
    cleanup($db, $ids);
}

if ($failures) {
    echo "\n" . count($failures) . " echec(s) metier.\n";
    exit(1);
}

echo "\nToutes les regles metier testees sont conformes.\n";
