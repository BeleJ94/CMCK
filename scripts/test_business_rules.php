<?php

require dirname(__DIR__) . '/app/helpers/functions.php';
// Match the web bootstrap, including runs around local midnight.
date_default_timezone_set(config('app.timezone', 'UTC'));
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
ob_start();

$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'BusinessRuleTest';

$db = Database::getInstance()->connection();
$user = [
    'id' => 1,
    'name' => 'Business Rule Test',
    'email' => 'test@dagril-erp.local',
    'role_slug' => 'administrateur',
];
$_SESSION['user'] = $user;
unset($_SESSION['current_site_id']);
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

function qa_reference($reference)
{
    static $suffix;
    if ($suffix === null) $suffix = strtoupper(bin2hex(random_bytes(4)));
    return $reference . '-' . $suffix;
}

function run_query(PDO $db, $sql, array $params = [])
{
    // Isolate this run from fixtures left by a previously interrupted test.
    $sql = preg_replace_callback('/TST-[A-Z0-9-]+/', function ($m) { return qa_reference($m[0]); }, $sql);
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
    foreach ($ids['waste_stocks'] as $stockId) {
        run_query($db, 'DELETE FROM waste_stock_movements WHERE waste_stock_id = ?', [$stockId]);
    }
    run_query($db, "DELETE FROM activity_logs WHERE user_agent = 'BusinessRuleTest'");
    if (!empty($ids['machine_feeds'])) {
        $feedPlaceholders = implode(',', array_fill(0, count($ids['machine_feeds']), '?'));
        run_query($db, "UPDATE machine_feeds SET bss_document_id=NULL WHERE id IN ({$feedPlaceholders})", array_values(array_unique($ids['machine_feeds'])));
    }
    foreach (['weighings', 'production_batches', 'distributions', 'silo_movements'] as $entityType) {
        if (empty($ids[$entityType])) { continue; }
        $placeholders = implode(',', array_fill(0, count($ids[$entityType]), '?'));
        $documentIds = run_query($db, "SELECT id FROM documents WHERE entity_type = ? AND entity_id IN ({$placeholders})", array_merge([$entityType], array_values(array_unique($ids[$entityType]))))->fetchAll(PDO::FETCH_COLUMN);
        if ($documentIds) {
            $docPlaceholders = implode(',', array_fill(0, count($documentIds), '?'));
            $workflowIds = run_query($db, "SELECT id FROM workflow_instances WHERE document_id IN ({$docPlaceholders})", $documentIds)->fetchAll(PDO::FETCH_COLUMN);
            if ($workflowIds) {
                $workflowPlaceholders = implode(',', array_fill(0, count($workflowIds), '?'));
                foreach (['workflow_notifications', 'workflow_level_approvals', 'workflow_transitions'] as $workflowTable) {
                    run_query($db, "DELETE FROM {$workflowTable} WHERE workflow_instance_id IN ({$workflowPlaceholders})", $workflowIds);
                }
                run_query($db, "DELETE FROM workflow_instances WHERE id IN ({$workflowPlaceholders})", $workflowIds);
            }
            run_query($db, "DELETE FROM document_status_history WHERE document_id IN ({$docPlaceholders})", $documentIds);
            run_query($db, "DELETE FROM document_attachments WHERE document_id IN ({$docPlaceholders})", $documentIds);
            run_query($db, "DELETE FROM documents WHERE id IN ({$docPlaceholders})", $documentIds);
        }
    }

    if (!empty($ids['production_batches'])) {
        $batchPlaceholders = implode(',', array_fill(0, count($ids['production_batches']), '?'));
        run_query($db, "DELETE FROM bulk_flour_stock_movements WHERE production_batch_id IN ({$batchPlaceholders})", array_values(array_unique($ids['production_batches'])));
        run_query($db, "DELETE FROM bulk_flour_stocks WHERE production_batch_id IN ({$batchPlaceholders})", array_values(array_unique($ids['production_batches'])));
        run_query($db, "DELETE FROM production_waste_lines WHERE production_batch_id IN ({$batchPlaceholders})", array_values(array_unique($ids['production_batches'])));
    }

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
    $siloSiteId = (int) scalar($db, "SELECT id FROM sites WHERE code = 'SILO' LIMIT 1");
    $minoSiteId = (int) scalar($db, "SELECT id FROM sites WHERE code = 'MINO' LIMIT 1");
    $pellSiteId = (int) scalar($db, "SELECT id FROM sites WHERE code = 'PELL' LIMIT 1");
    $validator = run_query($db, "SELECT u.id,u.name,u.email,r.slug role_slug FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE r.slug IN ('direction','administrateur') AND u.id<>? AND u.status='active' AND u.deleted_at IS NULL ORDER BY FIELD(r.slug,'direction','administrateur') LIMIT 1", [$user['id']])->fetch();

    foreach ([$rawProductId, $flourProductId, $wasteProductId, $animalFeedId, $bagFormatId, $siloSiteId, $minoSiteId, $pellSiteId, $validator['id'] ?? 0] as $requiredId) {
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

    run_query($db, "INSERT INTO silos (site_id, name, code, product_id, capacity_kg, current_stock_kg, alert_threshold_kg, status) VALUES (?, 'TST Silo QA', 'TST-SILO-QA', ?, 10000, 1000, 100, 'active')", [$siloSiteId, $rawProductId]);
    $siloId = (int) $db->lastInsertId();
    $ids['silos'][] = $siloId;

    run_query($db, "INSERT INTO machines (site_id, name, code, machine_type, capacity_kg_hour, status) VALUES (?, 'TST Mill QA', 'TST-MILL-QA', 'main', 1000, 'active')", [$minoSiteId]);
    $machineId = (int) $db->lastInsertId();
    $ids['machines'][] = $machineId;

    run_query($db, "INSERT INTO machines (site_id, name, code, machine_type, capacity_kg_hour, status) VALUES (?, 'TST Waste QA', 'TST-WASTE-QA', 'waste', 500, 'active')", [$pellSiteId]);
    $wasteMachineId = (int) $db->lastInsertId();
    $ids['machines'][] = $wasteMachineId;

    Auth::selectSite((string) $siloSiteId);
    $weighingModel = new Weighing();
    $encodedWeighingId = $weighingModel->createEntry([
        'supplier_id' => $supplierId,
        'truck_plate_number' => qa_reference('TST-QA-NEW'),
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

    run_query($db, "INSERT INTO weighings (site_id, supplier_id, truck_id, product_id, reference, poids_brut, poids_tare, poids_net, weighed_at, status, created_by) VALUES (?, ?, ?, ?, 'TST-PB-INVALID', 1000, 0, 0, NOW(), 'pending', ?)", [$siloSiteId, $supplierId, $truckId, $rawProductId, $user['id']]);
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

    run_query($db, "INSERT INTO weighings (site_id, supplier_id, truck_id, product_id, reference, poids_brut, poids_tare, poids_net, weighed_at, status, created_by) VALUES (?, ?, ?, ?, 'TST-PB-VALID', 1500, 0, 0, NOW(), 'pending', ?)", [$siloSiteId, $supplierId, $truckId, $rawProductId, $user['id']]);
    $validWeighingId = (int) $db->lastInsertId();
    $ids['weighings'][] = $validWeighingId;

    $weighingModel->validateExit($validWeighingId, ['poids_tare' => 400, 'silo_id' => $siloId], $validator);
    $ids['silo_movements'][] = (int) scalar($db, 'SELECT id FROM silo_movements WHERE weighing_id = ?', [$validWeighingId]);
    assert_near(scalar($db, 'SELECT poids_net FROM weighings WHERE id = ?', [$validWeighingId]), 1100, 'Poids net calcule correctement');
    assert_near(scalar($db, 'SELECT current_stock_kg FROM silos WHERE id = ?', [$siloId]), 2100, 'Entree silo augmente le stock silo');

    Auth::selectSite((string) $minoSiteId);
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
    ], $validator);
    $ids['stock_movements'][] = (int) scalar($db, "SELECT id FROM stock_movements WHERE product_id = ? AND quantity_kg = 480 ORDER BY id DESC LIMIT 1", [$flourProductId]);
    $wasteStockId = (int) scalar($db, 'SELECT id FROM waste_stocks WHERE production_batch_id = ?', [$batchId]);
    $ids['waste_stocks'][] = $wasteStockId;
    $batch = run_query($db, 'SELECT input_quantity_kg, output_quantity_kg, waste_quantity_kg FROM production_batches WHERE id = ?', [$batchId])->fetch();
    assert_near(((float) $batch['output_quantity_kg'] / (float) $batch['input_quantity_kg']) * 100, 80, 'Production calcule le rendement correctement');
    assert_near(scalar($db, 'SELECT quantity_kg FROM waste_stocks WHERE id = ?', [$wasteStockId]), 120, 'Dechets augmentent le stock dechets');

    Auth::selectSite((string) $pellSiteId);
    $wasteTotalBeforeProcessing = (new Waste())->totalAvailable();
    $animalStockBefore = (float) scalar($db, "SELECT COALESCE((SELECT stock_after_kg FROM stock_movements WHERE product_id = ? ORDER BY movement_at DESC, id DESC LIMIT 1), 0)", [$animalFeedId]);
    (new Waste())->processWaste([
        'machine_id' => $wasteMachineId,
        'input_quantity_kg' => 50,
        'output_quantity_kg' => 40,
        'processed_at' => date('Y-m-d H:i:s'),
    ], $user);
    $ids['waste_processings'] = array_merge($ids['waste_processings'], array_map('intval', run_query($db, 'SELECT id FROM waste_processings WHERE created_by = ? AND machine_id = ? ORDER BY id DESC', [$user['id'], $wasteMachineId])->fetchAll(PDO::FETCH_COLUMN)));
    $ids['stock_movements'][] = (int) scalar($db, "SELECT id FROM stock_movements WHERE product_id = ? AND quantity_kg = 40 ORDER BY id DESC LIMIT 1", [$animalFeedId]);
    assert_near($wasteTotalBeforeProcessing - (new Waste())->totalAvailable(), 50, 'Traitement dechets diminue le stock dechets');
    $animalStockAfter = (float) scalar($db, "SELECT stock_after_kg FROM stock_movements WHERE product_id = ? ORDER BY movement_at DESC, id DESC LIMIT 1", [$animalFeedId]);
    assert_near($animalStockAfter - $animalStockBefore, 40, 'Aliment betail augmente le stock fini');

    Auth::selectSite((string) $minoSiteId);
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
            'exit_voucher' => qa_reference('TST-BS-OVER'),
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
        'exit_voucher' => qa_reference('TST-BS-VALID'),
        'quantity_bags' => 4,
        'distributed_at' => date('Y-m-d H:i:s'),
    ], $user);
    $ids['distributions'][] = (int) $distributionId;
    $ids['stock_movements'][] = (int) scalar($db, 'SELECT id FROM stock_movements WHERE distribution_id = ?', [$distributionId]);
    assert_near(scalar($db, 'SELECT quantity_bags FROM finished_stocks WHERE id = ?', [$finishedStockId]), 6, 'Distribution diminue le stock produit fini en sacs');
    assert_near(scalar($db, 'SELECT total_weight_kg FROM finished_stocks WHERE id = ?', [$finishedStockId]), 150, 'Distribution diminue le stock produit fini en kg');

    $rolesToCheck = [
        'agent-pont-bascule' => ['weighings'],
        'agent-silo' => ['silos', 'machine-feeds'],
        'agent-production' => ['production', 'waste'],
        'agent-emballage' => ['packaging', 'finished-stocks'],
        'agent-distribution' => ['distributions', 'finished-stocks'],
    ];
    foreach ($rolesToCheck as $roleSlug => $expectedLabels) {
        $roleUser = run_query($db, "SELECT users.*, roles.name AS role_name, roles.slug AS role_slug FROM users INNER JOIN roles ON roles.id = users.role_id WHERE roles.slug = ? AND users.status = 'active' AND users.deleted_at IS NULL LIMIT 1", [$roleSlug])->fetch();
        if (!$roleUser) { throw new RuntimeException('Utilisateur requis introuvable pour le role ' . $roleSlug); }
        Auth::login($roleUser);
        $labels = array_column(Auth::menu(), 'path');
        foreach ($expectedLabels as $label) {
            assert_true(in_array($label, $labels, true), "Menu {$roleSlug} contient {$label}");
        }
        assert_true(!in_array('reports', $labels, true) && !in_array('users', $labels, true), "Menu {$roleSlug} masque rapports/utilisateurs");
    }
    $adminUser = run_query($db, "SELECT users.*, roles.name AS role_name, roles.slug AS role_slug FROM users INNER JOIN roles ON roles.id = users.role_id WHERE roles.slug = 'administrateur' AND users.status = 'active' AND users.deleted_at IS NULL LIMIT 1")->fetch();
    Auth::login($adminUser);
    Auth::selectSite('all');

    $filters = ['start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d'), 'supplier_id' => $supplierId, 'machine_id' => $machineId];
    $reports = new ReportModel();
    $dailyRows = array_filter($reports->dailyReception($filters), function ($row) {
        return $row['reference'] === qa_reference('TST-PB-VALID');
    });
    assert_true(count($dailyRows) === 1, 'Rapport reception affiche la pesee test');
    assert_near(array_values($dailyRows)[0]['poids_net'], 1100, 'Rapport reception affiche le bon poids net');

    $productionRows = array_filter($reports->production($filters), function ($row) use ($batchId) {
        return $row['batch_number'] === scalar(Database::getInstance()->connection(), 'SELECT batch_number FROM production_batches WHERE id = ?', [$batchId]);
    });
    assert_true(count($productionRows) === 1, 'Rapport production affiche le lot test');
    assert_near(array_values($productionRows)[0]['yield_rate'], 80, 'Rapport production affiche le bon rendement');

    $wasteRows = $reports->waste(['start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d'), 'supplier_id' => '', 'machine_id' => $wasteMachineId]);
    // A processing operation may consume several FIFO stock lines.
    assert_near(array_sum(array_column($wasteRows, 'input_quantity_kg')), 50, 'Rapport déchets : totalité du stock traité');
    assert_near(array_sum(array_column($wasteRows, 'output_quantity_kg')), 40, 'Rapport déchets : totalité de la production');

    $packagingRows = $reports->packaging(['start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d'), 'supplier_id' => '', 'machine_id' => '']);
    $testPackagingRows = array_filter($packagingRows, function ($row) {
        return (int) $row['bags_count'] === 10 && (float) $row['total_weight_kg'] === 250.0;
    });
    assert_true(count($testPackagingRows) >= 1, 'Rapport emballage affiche l emballage test');

    $distributionRows = $reports->distribution(['start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d'), 'supplier_id' => '', 'machine_id' => '']);
    $testDistributionRows = array_filter($distributionRows, function ($row) {
        return $row['exit_voucher'] === qa_reference('TST-BS-VALID');
    });
    assert_true(count($testDistributionRows) === 1, 'Rapport distribution affiche le bon de sortie test');
    assert_near(array_values($testDistributionRows)[0]['total_weight_kg'], 100, 'Rapport distribution affiche le bon poids distribue');
} finally {
    cleanup($db, $ids);
}

if ($failures) {
    echo "\n" . count($failures) . " echec(s) metier.\n";
    ob_end_flush();
    exit(1);
}

echo "\nToutes les regles metier testees sont conformes.\n";
ob_end_flush();
