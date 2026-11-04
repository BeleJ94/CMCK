<?php

class ReportModel extends Model
{
    public function referenceData()
    {
        return [
            'suppliers' => $this->query(
                "SELECT id, name
                 FROM suppliers
                 WHERE deleted_at IS NULL
                   AND status IN ('active', 'validated')
                 ORDER BY name ASC"
            )->fetchAll(),
            'machines' => $this->query(
                "SELECT id, name, machine_type
                 FROM machines
                 WHERE deleted_at IS NULL
                   AND status IN ('active', 'validated')
                 ORDER BY machine_type ASC, name ASC"
            )->fetchAll(),
        ];
    }

    public function dailyReception(array $filters)
    {
        $params = $this->dateParams($filters);
        $supplierClause = '';

        if (!empty($filters['supplier_id'])) {
            $supplierClause = ' AND weighings.supplier_id = :supplier_id';
            $params['supplier_id'] = $filters['supplier_id'];
        }
        $supplierClause .= Auth::siteClause('weighings.site_id', $params);

        return $this->query(
            "SELECT weighings.reference,
                    weighings.weighed_at,
                    suppliers.name AS supplier_name,
                    trucks.plate_number,
                    trucks.driver_name,
                    products.name AS product_name,
                    weighings.poids_brut,
                    weighings.poids_tare,
                    weighings.poids_net,
                    weighings.status
             FROM weighings
             LEFT JOIN suppliers ON suppliers.id = weighings.supplier_id
             INNER JOIN trucks ON trucks.id = weighings.truck_id
             INNER JOIN products ON products.id = weighings.product_id
             WHERE weighings.deleted_at IS NULL
               AND DATE(weighings.weighed_at) BETWEEN :start_date AND :end_date" . $supplierClause . "
             ORDER BY weighings.weighed_at DESC",
            $params
        )->fetchAll();
    }

    public function receptionBySupplier(array $filters)
    {
        $params = $this->dateParams($filters);
        $supplierClause = '';

        if (!empty($filters['supplier_id'])) {
            $supplierClause = ' AND suppliers.id = :supplier_id';
            $params['supplier_id'] = $filters['supplier_id'];
        }
        $supplierClause .= Auth::siteClause('weighings.site_id', $params);

        return $this->query(
            "SELECT suppliers.name AS supplier_name,
                    COUNT(weighings.id) AS trucks_count,
                    COALESCE(SUM(weighings.poids_net), 0) AS net_weight_kg
             FROM suppliers
             LEFT JOIN weighings ON weighings.supplier_id = suppliers.id
                AND weighings.deleted_at IS NULL
                AND DATE(weighings.weighed_at) BETWEEN :start_date AND :end_date
             WHERE suppliers.deleted_at IS NULL" . $supplierClause . "
             GROUP BY suppliers.id, suppliers.name
             HAVING trucks_count > 0 OR net_weight_kg > 0
             ORDER BY net_weight_kg DESC",
            $params
        )->fetchAll();
    }

    public function production(array $filters)
    {
        $params = $this->dateParams($filters);
        $machineClause = '';

        if (!empty($filters['machine_id'])) {
            $machineClause = ' AND machines.id = :machine_id';
            $params['machine_id'] = $filters['machine_id'];
        }
        $machineClause .= Auth::siteClause('production_batches.site_id', $params);

        return $this->query(
            "SELECT production_batches.batch_number,
                    production_batches.started_at,
                    production_batches.ended_at,
                    machines.name AS machine_name,
                    products.name AS product_name,
                    production_batches.input_quantity_kg,
                    production_batches.output_quantity_kg,
                    production_batches.waste_quantity_kg,
                    CASE
                        WHEN production_batches.input_quantity_kg > 0
                        THEN (production_batches.output_quantity_kg / production_batches.input_quantity_kg) * 100
                        ELSE 0
                    END AS yield_rate,
                    production_batches.status
             FROM production_batches
             INNER JOIN machine_feeds ON machine_feeds.id = production_batches.machine_feed_id
             INNER JOIN machines ON machines.id = machine_feeds.machine_id
             INNER JOIN products ON products.id = production_batches.product_id
             WHERE production_batches.deleted_at IS NULL
               AND DATE(production_batches.started_at) BETWEEN :start_date AND :end_date" . $machineClause . "
             ORDER BY production_batches.started_at DESC",
            $params
        )->fetchAll();
    }

    public function yieldByMachine(array $filters)
    {
        $params = $this->dateParams($filters);
        $machineClause = '';

        if (!empty($filters['machine_id'])) {
            $machineClause = ' AND machines.id = :machine_id';
            $params['machine_id'] = $filters['machine_id'];
        }
        $machineClause .= Auth::siteClause('production_batches.site_id', $params);

        return $this->query(
            "SELECT machines.name AS machine_name,
                    COUNT(production_batches.id) AS batches_count,
                    COALESCE(SUM(production_batches.input_quantity_kg), 0) AS input_quantity_kg,
                    COALESCE(SUM(production_batches.output_quantity_kg), 0) AS output_quantity_kg,
                    COALESCE(SUM(production_batches.waste_quantity_kg), 0) AS waste_quantity_kg,
                    COALESCE(AVG(CASE
                        WHEN production_batches.input_quantity_kg > 0
                        THEN (production_batches.output_quantity_kg / production_batches.input_quantity_kg) * 100
                        ELSE NULL
                    END), 0) AS yield_rate
             FROM machines
             LEFT JOIN machine_feeds ON machine_feeds.machine_id = machines.id
                AND machine_feeds.deleted_at IS NULL
             LEFT JOIN production_batches ON production_batches.machine_feed_id = machine_feeds.id
                AND production_batches.deleted_at IS NULL
                AND DATE(production_batches.started_at) BETWEEN :start_date AND :end_date
             WHERE machines.deleted_at IS NULL" . $machineClause . "
             GROUP BY machines.id, machines.name
             HAVING batches_count > 0
             ORDER BY yield_rate DESC",
            $params
        )->fetchAll();
    }

    public function waste(array $filters)
    {
        $params = $this->dateParams($filters);
        $machineClause = '';

        if (!empty($filters['machine_id'])) {
            $machineClause = ' AND machines.id = :machine_id';
            $params['machine_id'] = $filters['machine_id'];
        }
        $machineClause .= Auth::siteClause('waste_processings.site_id', $params);

        return $this->query(
            "SELECT waste_processings.processed_at, waste_processings.status,
                    machines.name AS machine_name,
                    production_batches.batch_number,
                    waste_processings.input_quantity_kg,
                    waste_processings.output_quantity_kg,
                    CASE
                        WHEN waste_processings.input_quantity_kg > 0
                        THEN (waste_processings.output_quantity_kg / waste_processings.input_quantity_kg) * 100
                        ELSE 0
                    END AS yield_rate,
                    users.name AS agent_name
             FROM waste_processings
             INNER JOIN waste_stocks ON waste_stocks.id = waste_processings.waste_stock_id
             LEFT JOIN production_batches ON production_batches.id = waste_stocks.production_batch_id
             LEFT JOIN machines ON machines.id = waste_processings.machine_id
             LEFT JOIN users ON users.id = waste_processings.created_by
             WHERE waste_processings.deleted_at IS NULL
               AND DATE(waste_processings.processed_at) BETWEEN :start_date AND :end_date" . $machineClause . "
             ORDER BY waste_processings.processed_at DESC",
            $params
        )->fetchAll();
    }

    /** Fabrications d'aliment bétail validées sur la période (date de fin, à défaut de création). */
    public function feedOrders(array $filters)
    {
        $params = $this->dateParams($filters);
        $siteClause = Auth::siteClause('o.site_id', $params);
        $hasForm = (bool) $this->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pellet_recipe_versions' AND column_name='output_form'")->fetchColumn();
        $form = $hasForm ? 'rv.output_form' : "'granule'";

        return $this->query(
            "SELECT o.id, o.order_number, COALESCE(o.ended_at, o.created_at) AS operation_at, r.code AS recipe_code, rv.version_number, {$form} AS output_form,
                    COALESCE((SELECT SUM(wc.actual_quantity_kg) FROM pellet_order_waste_consumptions wc WHERE wc.pellet_order_id = o.id), 0) AS issues_kg,
                    COALESCE((SELECT SUM(ac.actual_quantity_kg) FROM pellet_order_additive_consumptions ac WHERE ac.pellet_order_id = o.id), 0) AS additives_kg,
                    o.output_quantity_kg, o.loss_quantity_kg, o.yield_percent, o.cost_total, o.cost_per_kg, v.name AS validator_name
             FROM pellet_orders o
             INNER JOIN pellet_recipe_versions rv ON rv.id = o.recipe_version_id
             INNER JOIN pellet_recipes r ON r.id = rv.recipe_id
             LEFT JOIN users v ON v.id = o.validated_by
             WHERE o.deleted_at IS NULL AND o.status = 'validated'
               AND DATE(COALESCE(o.ended_at, o.created_at)) BETWEEN :start_date AND :end_date" . $siteClause . "
             ORDER BY operation_at DESC, o.id DESC",
            $params
        )->fetchAll();
    }

    /** Bilan matière de la période : grain chargé → farine + issues + écart, puis issues → aliment bétail. */
    public function materialBalance(array $filters, array $feedOrders)
    {
        $params = $this->dateParams($filters);
        $siteClause = Auth::siteClause('pb.site_id', $params);
        $mill = $this->query(
            "SELECT COUNT(*) AS batches, COALESCE(SUM(pb.actual_input_quantity_kg), 0) AS loaded_kg, COALESCE(SUM(pb.output_quantity_kg), 0) AS flour_kg, COALESCE(SUM(pb.waste_quantity_kg), 0) AS issues_kg
             FROM production_batches pb
             WHERE pb.deleted_at IS NULL AND pb.status = 'validated'
               AND DATE(COALESCE(pb.ended_at, pb.started_at)) BETWEEN :start_date AND :end_date" . $siteClause,
            $params
        )->fetch();
        $sum = function ($key) use ($feedOrders) { return array_sum(array_map(function ($row) use ($key) { return (float) $row[$key]; }, $feedOrders)); };
        $loaded = (float) $mill['loaded_kg'];
        $feedOutput = $sum('output_quantity_kg');
        $feedCost = $sum('cost_total');

        return [
            'batches' => (int) $mill['batches'],
            'loaded_kg' => $loaded,
            'flour_kg' => (float) $mill['flour_kg'],
            'issues_kg' => (float) $mill['issues_kg'],
            'gap_kg' => round($loaded - (float) $mill['flour_kg'] - (float) $mill['issues_kg'], 3),
            'feed_orders' => count($feedOrders),
            'feed_issues_kg' => $sum('issues_kg'),
            'feed_additives_kg' => $sum('additives_kg'),
            'feed_output_kg' => $feedOutput,
            'feed_loss_kg' => $sum('loss_quantity_kg'),
            'feed_cost_total' => $feedCost,
            'feed_cost_per_kg' => $feedOutput > 0 ? $feedCost / $feedOutput : 0,
        ];
    }

    public function packaging(array $filters)
    {
        $params = $this->dateParams($filters);
        $siteClause = Auth::siteClause('packaging.site_id', $params);

        return $this->query(
            "SELECT packaging.packaged_at,
                    production_batches.batch_number,
                    products.name AS product_name,
                    bag_formats.name AS format_name,
                    packaging.bags_count,
                    packaging.total_weight_kg,
                    users.name AS agent_name,
                    packaging.status
             FROM packaging
             INNER JOIN production_batches ON production_batches.id = packaging.production_batch_id
             INNER JOIN products ON products.id = packaging.product_id
             INNER JOIN bag_formats ON bag_formats.id = packaging.bag_format_id
             LEFT JOIN users ON users.id = packaging.created_by
             WHERE packaging.deleted_at IS NULL
               AND DATE(packaging.packaged_at) BETWEEN :start_date AND :end_date{$siteClause}
             ORDER BY packaging.packaged_at DESC",
            $params
        )->fetchAll();
    }

    public function distribution(array $filters)
    {
        $params = $this->dateParams($filters);
        $siteClause = Auth::siteClause('distributions.site_id', $params);

        return $this->query(
            "SELECT distributions.distributed_at,
                    distributions.exit_voucher,
                    distributions.recipient_name,
                    distributions.transporter,
                    products.name AS product_name,
                    bag_formats.name AS format_name,
                    distributions.quantity_bags,
                    distributions.total_weight_kg,
                    users.name AS agent_name,
                    distributions.status
             FROM distributions
             INNER JOIN products ON products.id = distributions.product_id
             INNER JOIN bag_formats ON bag_formats.id = distributions.bag_format_id
             LEFT JOIN users ON users.id = distributions.created_by
             WHERE distributions.deleted_at IS NULL
               AND DATE(distributions.distributed_at) BETWEEN :start_date AND :end_date{$siteClause}
             ORDER BY distributions.distributed_at DESC",
            $params
        )->fetchAll();
    }

    public function siloStocks()
    {
        $params = [];
        $siteClause = Auth::siteClause('silos.site_id', $params);
        return $this->query(
            "SELECT silos.name,
                    silos.code,
                    products.name AS product_name,
                    silos.capacity_kg,
                    silos.current_stock_kg,
                    silos.alert_threshold_kg,
                    CASE
                        WHEN silos.capacity_kg > 0
                        THEN (silos.current_stock_kg / silos.capacity_kg) * 100
                        ELSE 0
                    END AS fill_rate,
                    silos.status
             FROM silos
             LEFT JOIN products ON products.id = silos.product_id
             WHERE silos.deleted_at IS NULL{$siteClause}
             ORDER BY silos.name ASC", $params
        )->fetchAll();
    }

    public function finishedStocks()
    {
        $params = [];
        $siteClause = Auth::siteClause('finished_stocks.site_id', $params);
        return $this->query(
            "SELECT products.name AS product_name,
                    bag_formats.name AS format_name,
                    COALESCE(SUM(finished_stocks.quantity_bags), 0) AS quantity_bags,
                    COALESCE(SUM(finished_stocks.total_weight_kg), 0) AS total_weight_kg
             FROM finished_stocks
             INNER JOIN products ON products.id = finished_stocks.product_id
             INNER JOIN bag_formats ON bag_formats.id = finished_stocks.bag_format_id
             WHERE finished_stocks.deleted_at IS NULL
               AND finished_stocks.status IN ('active', 'validated'){$siteClause}
             GROUP BY products.id, bag_formats.id
             ORDER BY products.name ASC, bag_formats.weight_kg ASC", $params
        )->fetchAll();
    }

    public function globalSummary(array $filters)
    {
        $reception = $this->dailyReception($filters);
        $production = $this->production($filters);
        $waste = $this->waste($filters);
        $packaging = $this->packaging($filters);
        $distribution = $this->distribution($filters);

        $validated = static function($row) { return ($row['status'] ?? '') === 'validated'; };
        $reception = array_values(array_filter($reception, $validated));
        $production = array_values(array_filter($production, $validated));
        $packaging = array_values(array_filter($packaging, static function($row){return in_array($row['status']??'', ['validated','active'], true);}));
        $waste = array_values(array_filter($waste, $validated));
        $distribution = array_values(array_filter($distribution, $validated));

        $packagedKg = array_sum(array_map(function ($row) { return (float) $row['total_weight_kg']; }, $packaging));
        $distributedKg = array_sum(array_map(function ($row) { return (float) $row['total_weight_kg']; }, $distribution));

        return [
            'received_kg' => array_sum(array_map(function ($row) { return (float) $row['poids_net']; }, $reception)),
            'produced_kg' => array_sum(array_map(function ($row) { return (float) $row['output_quantity_kg']; }, $production)),
            'waste_kg' => array_sum(array_map(function ($row) { return (float) $row['waste_quantity_kg']; }, $production)),
            'waste_processed_kg' => array_sum(array_map(function ($row) { return (float) $row['input_quantity_kg']; }, $waste)),
            'packaged_kg' => $packagedKg,
            'distributed_kg' => $distributedKg,
            'reception_count' => count($reception),
            'production_count' => count($production),
            'waste_count' => count($waste),
            'packaging_count' => count($packaging),
            'distribution_count' => count($distribution),
            'average_yield' => array_sum(array_map(function ($row) { return (float) $row['input_quantity_kg']; }, $production)) > 0
                ? (array_sum(array_map(function ($row) { return (float) $row['output_quantity_kg']; }, $production)) / array_sum(array_map(function ($row) { return (float) $row['input_quantity_kg']; }, $production))) * 100
                : 0,
            'net_finished_flow_kg' => $packagedKg - $distributedKg,
        ];
    }

    public function executiveSummary(array $filters, array $summary)
    {
        $days = $this->periodDays($filters);
        $dailyReceived = $days > 0 ? $summary['received_kg'] / $days : 0;
        $dailyProduced = $days > 0 ? $summary['produced_kg'] / $days : 0;
        $dailyDistributed = $days > 0 ? $summary['distributed_kg'] / $days : 0;
        $riskLevel = 'success';
        $riskText = 'Situation maitrisee sur la periode.';

        if ((float) $summary['average_yield'] < 70 && (float) $summary['produced_kg'] > 0) {
            $riskLevel = 'danger';
            $riskText = 'Rendement faible : analyse machine recommandee.';
        } elseif ((float) $summary['distributed_kg'] > (float) $summary['packaged_kg'] && (float) $summary['distributed_kg'] > 0) {
            $riskLevel = 'warning';
            $riskText = 'Distribution superieure a l emballage de la periode.';
        } elseif ((float) $summary['waste_kg'] > 0 && (float) $summary['produced_kg'] > 0 && ($summary['waste_kg'] / max($summary['produced_kg'], 1)) > 0.2) {
            $riskLevel = 'warning';
            $riskText = 'Niveau d’issues élevé par rapport a la production.';
        }

        return [
            'period_days' => $days,
            'risk_level' => $riskLevel,
            'risk_text' => $riskText,
            'daily_received_kg' => $dailyReceived,
            'daily_produced_kg' => $dailyProduced,
            'daily_distributed_kg' => $dailyDistributed,
            'finished_flow_kg' => $summary['packaged_kg'] - $summary['distributed_kg'],
            'key_message' => $this->keyMessage($summary),
        ];
    }

    public function reportHighlights(array $filters)
    {
        return [
            'topSuppliers' => array_slice($this->receptionBySupplier($filters), 0, 5),
            'machineYields' => array_slice($this->yieldByMachine($filters), 0, 5),
        ];
    }

    private function dateParams(array $filters)
    {
        return [
            'start_date' => $filters['start_date'],
            'end_date' => $filters['end_date'],
        ];
    }

    private function periodDays(array $filters)
    {
        $start = new DateTime($filters['start_date']);
        $end = new DateTime($filters['end_date']);

        return max(1, (int) $start->diff($end)->days + 1);
    }

    private function keyMessage(array $summary)
    {
        if ((float) $summary['received_kg'] <= 0 && (float) $summary['produced_kg'] <= 0) {
            return 'Aucune activite significative enregistree sur la periode.';
        }

        if ((float) $summary['produced_kg'] > (float) $summary['received_kg'] && (float) $summary['received_kg'] > 0) {
            return 'La production a consomme une partie du stock existant en plus des receptions de la periode.';
        }

        if ((float) $summary['distributed_kg'] > (float) $summary['packaged_kg'] && (float) $summary['packaged_kg'] > 0) {
            return 'Les sorties depassent les emballages de la periode, verifier la couverture stock.';
        }

        return 'Les flux reception, production, emballage et distribution sont disponibles pour controle direction.';
    }
}
