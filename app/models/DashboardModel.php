<?php

class DashboardModel extends Model
{
    public function directionData()
    {
        $today = $this->statsForDate('CURDATE()');
        $yesterday = $this->statsForDate('DATE_SUB(CURDATE(), INTERVAL 1 DAY)');

        return [
            'today' => $today,
            'yesterday' => $yesterday,
            'trends' => $this->trends($today, $yesterday),
            'stockSnapshot' => $this->stockSnapshot(),
            'alertStats' => $this->alertStats(),
            'productionSevenDays' => $this->productionSevenDays(),
            'yieldByMachine' => $this->yieldByMachine(),
            'receptionBySupplier' => $this->receptionBySupplier(),
            'distributionSevenDays' => $this->distributionSevenDays(),
            'alerts' => $this->importantAlerts(),
        ];
    }

    private function statsForDate($dateExpression)
    {
        $weighing = $this->query(
            "SELECT COALESCE(SUM(poids_net), 0) AS maize_received, COUNT(DISTINCT truck_id) AS trucks_received
             FROM weighings
             WHERE DATE(weighed_at) = {$dateExpression}
               AND status IN ('validated', 'active')
               AND deleted_at IS NULL"
        )->fetch();

        $silos = $this->query(
            "SELECT COALESCE(SUM(current_stock_kg), 0) AS silo_stock
             FROM silos
             WHERE deleted_at IS NULL
               AND status IN ('active', 'validated')"
        )->fetch();

        $treated = $this->query(
            "SELECT COALESCE(SUM(quantity_kg), 0) AS treated_quantity
             FROM machine_feeds
             WHERE DATE(fed_at) = {$dateExpression}
               AND status IN ('validated', 'active')
               AND deleted_at IS NULL"
        )->fetch();

        $production = $this->query(
            "SELECT
                COALESCE(SUM(CASE WHEN products.code = 'FARINE-MAIS' THEN production_batches.output_quantity_kg ELSE 0 END), 0) AS flour_produced,
                COALESCE(SUM(CASE WHEN products.code = 'ALIMENT-BETAIL' THEN production_batches.output_quantity_kg ELSE 0 END), 0) AS animal_feed_produced,
                COALESCE(SUM(production_batches.waste_quantity_kg), 0) AS waste_generated,
                COALESCE(AVG(CASE
                    WHEN production_batches.input_quantity_kg > 0
                    THEN (production_batches.output_quantity_kg / production_batches.input_quantity_kg) * 100
                    ELSE NULL
                END), 0) AS average_yield
             FROM production_batches
             INNER JOIN products ON products.id = production_batches.product_id
             WHERE DATE(production_batches.started_at) = {$dateExpression}
               AND production_batches.status IN ('validated', 'active')
               AND production_batches.deleted_at IS NULL"
        )->fetch();

        $wasteProcessing = $this->query(
            "SELECT COALESCE(SUM(output_quantity_kg), 0) AS animal_feed_from_waste
             FROM waste_processings
             WHERE DATE(processed_at) = {$dateExpression}
               AND status IN ('validated', 'active')
               AND deleted_at IS NULL"
        )->fetch();

        $production['animal_feed_produced'] = (float) $production['animal_feed_produced'] + (float) $wasteProcessing['animal_feed_from_waste'];

        $distribution = $this->query(
            "SELECT COALESCE(SUM(total_weight_kg), 0) AS distributed_products
             FROM distributions
             WHERE DATE(distributed_at) = {$dateExpression}
               AND status IN ('validated', 'active')
               AND deleted_at IS NULL"
        )->fetch();

        return array_merge($weighing, $silos, $treated, $production, $distribution);
    }

    private function stockSnapshot()
    {
        $finished = $this->query(
            "SELECT COALESCE(SUM(total_weight_kg), 0) AS finished_stock,
                    COALESCE(SUM(quantity_bags), 0) AS finished_bags
             FROM finished_stocks
             WHERE deleted_at IS NULL
               AND status IN ('active', 'validated')"
        )->fetch();

        $waste = $this->query(
            "SELECT COALESCE(SUM(quantity_kg), 0) AS waste_stock
             FROM waste_stocks
             WHERE deleted_at IS NULL
               AND status IN ('active', 'validated')"
        )->fetch();

        return array_merge($finished, $waste);
    }

    private function alertStats()
    {
        return $this->query(
            "SELECT
                SUM(CASE WHEN severity = 'danger' THEN 1 ELSE 0 END) AS danger,
                SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) AS warning,
                SUM(CASE WHEN severity = 'info' THEN 1 ELSE 0 END) AS info,
                COUNT(*) AS total
             FROM alerts
             WHERE status = 'active'
               AND read_at IS NULL
               AND deleted_at IS NULL"
        )->fetch();
    }

    private function trends(array $today, array $yesterday)
    {
        return [
            'maize_received' => $this->variation($today['maize_received'], $yesterday['maize_received']),
            'treated_quantity' => $this->variation($today['treated_quantity'], $yesterday['treated_quantity']),
            'flour_produced' => $this->variation($today['flour_produced'], $yesterday['flour_produced']),
            'distributed_products' => $this->variation($today['distributed_products'], $yesterday['distributed_products']),
            'average_yield' => $this->variation($today['average_yield'], $yesterday['average_yield']),
        ];
    }

    private function variation($current, $previous)
    {
        $current = (float) $current;
        $previous = (float) $previous;

        if ($previous <= 0) {
            return [
                'label' => $current > 0 ? 'Nouveau' : 'Stable',
                'value' => $current > 0 ? 100 : 0,
                'tone' => $current > 0 ? 'green' : 'blue',
            ];
        }

        $value = (($current - $previous) / $previous) * 100;

        return [
            'label' => ($value >= 0 ? '+' : '') . number_format($value, 1, ',', ' ') . '% vs veille',
            'value' => $value,
            'tone' => $value >= 0 ? 'green' : 'red',
        ];
    }

    private function productionSevenDays()
    {
        return $this->query(
            "SELECT DATE(started_at) AS day, COALESCE(SUM(output_quantity_kg), 0) AS total
             FROM production_batches
             WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
               AND status IN ('validated', 'active')
               AND deleted_at IS NULL
             GROUP BY DATE(started_at)
             ORDER BY day ASC"
        )->fetchAll();
    }

    private function yieldByMachine()
    {
        return $this->query(
            "SELECT machines.name, COALESCE(AVG(
                CASE
                    WHEN production_batches.input_quantity_kg > 0
                    THEN (production_batches.output_quantity_kg / production_batches.input_quantity_kg) * 100
                    ELSE NULL
                END
             ), 0) AS yield_rate
             FROM production_batches
             INNER JOIN machine_feeds ON machine_feeds.id = production_batches.machine_feed_id
             INNER JOIN machines ON machines.id = machine_feeds.machine_id
             WHERE production_batches.started_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
               AND production_batches.status IN ('validated', 'active')
               AND production_batches.deleted_at IS NULL
             GROUP BY machines.id, machines.name
             ORDER BY yield_rate DESC"
        )->fetchAll();
    }

    private function receptionBySupplier()
    {
        return $this->query(
            "SELECT suppliers.name, COALESCE(SUM(weighings.poids_net), 0) AS total
             FROM weighings
             INNER JOIN suppliers ON suppliers.id = weighings.supplier_id
             WHERE weighings.weighed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
               AND weighings.status IN ('validated', 'active')
               AND weighings.deleted_at IS NULL
             GROUP BY suppliers.id, suppliers.name
             ORDER BY total DESC
             LIMIT 6"
        )->fetchAll();
    }

    private function distributionSevenDays()
    {
        return $this->query(
            "SELECT DATE(distributed_at) AS day, COALESCE(SUM(total_weight_kg), 0) AS total
             FROM distributions
             WHERE distributed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
               AND status IN ('validated', 'active')
               AND deleted_at IS NULL
             GROUP BY DATE(distributed_at)
             ORDER BY day ASC"
        )->fetchAll();
    }

    private function importantAlerts()
    {
        return $this->query(
            "SELECT title, message, severity, created_at
             FROM alerts
             WHERE status = 'active'
               AND read_at IS NULL
               AND deleted_at IS NULL
               AND severity IN ('danger', 'warning', 'info')
             ORDER BY FIELD(severity, 'danger', 'warning', 'info'), created_at DESC
             LIMIT 5"
        )->fetchAll();
    }
}
