<?php

class Alert extends Model
{
    protected $table = 'alerts';

    private $types = [
        'silo_low' => ['label' => 'Stock silo bas', 'pattern' => 'Stock silo bas:%'],
        'silo_full' => ['label' => 'Silo presque plein', 'pattern' => 'Silo presque plein:%'],
        'finished_low' => ['label' => 'Stock produit fini bas', 'pattern' => 'Stock produit fini bas:%'],
        'low_yield' => ['label' => 'Rendement machine faible', 'pattern' => 'Rendement machine faible:%'],
        'pending_weighing' => ['label' => 'Pesee en attente trop longue', 'pattern' => 'Pesee en attente trop longue:%'],
        'production_gap' => ['label' => 'Ecart anormal de production', 'pattern' => 'Ecart anormal de production:%'],
    ];

    public function types()
    {
        return $this->types;
    }

    public function levels()
    {
        return ['info', 'warning', 'danger', 'success'];
    }

    public function generateSystemAlerts()
    {
        $this->generateSiloAlerts();
        $this->generateFinishedStockAlerts();
        $this->generateYieldAlerts();
        $this->generatePendingWeighingAlerts();
        $this->generateProductionGapAlerts();
    }

    public function filtered(array $filters = [])
    {
        $params = [];
        $where = "WHERE deleted_at IS NULL";

        if (!empty($filters['level']) && in_array($filters['level'], $this->levels(), true)) {
            $where .= " AND severity = :severity";
            $params['severity'] = $filters['level'];
        }

        if (!empty($filters['type']) && isset($this->types[$filters['type']])) {
            $where .= " AND title LIKE :type_pattern";
            $params['type_pattern'] = $this->types[$filters['type']]['pattern'];
        }

        $rows = $this->query(
            "SELECT *
             FROM alerts
             {$where}
             ORDER BY
                CASE WHEN read_at IS NULL THEN 0 ELSE 1 END,
                FIELD(severity, 'danger', 'warning', 'info', 'success'),
                created_at DESC",
            $params
        )->fetchAll();

        return array_map([$this, 'withType'], $rows);
    }

    public function unreadImportant($limit = 5)
    {
        $rows = $this->query(
            "SELECT *
             FROM alerts
             WHERE status = 'active'
               AND read_at IS NULL
               AND deleted_at IS NULL
             ORDER BY FIELD(severity, 'danger', 'warning', 'info', 'success'), created_at DESC
             LIMIT " . (int) $limit
        )->fetchAll();

        return array_map([$this, 'withType'], $rows);
    }

    public function stats()
    {
        $row = $this->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) AS unread,
                SUM(CASE WHEN severity = 'danger' AND read_at IS NULL THEN 1 ELSE 0 END) AS danger,
                SUM(CASE WHEN severity = 'warning' AND read_at IS NULL THEN 1 ELSE 0 END) AS warning
             FROM alerts
             WHERE deleted_at IS NULL
               AND status = 'active'"
        )->fetch();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'unread' => (int) ($row['unread'] ?? 0),
            'danger' => (int) ($row['danger'] ?? 0),
            'warning' => (int) ($row['warning'] ?? 0),
        ];
    }

    public function markAsRead($id)
    {
        $this->query(
            "UPDATE alerts
             SET read_at = COALESCE(read_at, NOW())
             WHERE id = :id
               AND deleted_at IS NULL",
            ['id' => $id]
        );
    }

    public function markAllAsRead()
    {
        $this->query(
            "UPDATE alerts
             SET read_at = COALESCE(read_at, NOW())
             WHERE status = 'active'
               AND read_at IS NULL
               AND deleted_at IS NULL"
        );
    }

    private function generateSiloAlerts()
    {
        $silos = $this->query(
            "SELECT silos.*,
                    CASE
                        WHEN silos.capacity_kg > 0 THEN (silos.current_stock_kg / silos.capacity_kg) * 100
                        ELSE 0
                    END AS fill_rate
             FROM silos
             WHERE silos.deleted_at IS NULL
               AND silos.status IN ('active', 'validated')"
        )->fetchAll();

        foreach ($silos as $silo) {
            $stock = (float) $silo['current_stock_kg'];
            $capacity = (float) $silo['capacity_kg'];
            $threshold = (float) $silo['alert_threshold_kg'];
            $fillRate = (float) $silo['fill_rate'];

            if ($threshold > 0 && $stock <= $threshold) {
                $this->ensureAlert(
                    'Stock silo bas: ' . $silo['name'],
                    'Le stock du silo ' . $silo['name'] . ' est a ' . $this->kg($stock) . ', sous le seuil de ' . $this->kg($threshold) . '.',
                    'warning'
                );
            }

            if ($capacity > 0 && $fillRate >= 90) {
                $severity = $fillRate >= 100 ? 'danger' : 'warning';
                $this->ensureAlert(
                    'Silo presque plein: ' . $silo['name'],
                    'Le silo ' . $silo['name'] . ' est rempli a ' . number_format($fillRate, 1, ',', ' ') . '%.',
                    $severity
                );
            }
        }
    }

    private function generateFinishedStockAlerts()
    {
        $products = $this->query(
            "SELECT products.name,
                    COALESCE(SUM(finished_stocks.total_weight_kg), 0) AS available_kg
             FROM products
             LEFT JOIN finished_stocks ON finished_stocks.product_id = products.id
                AND finished_stocks.deleted_at IS NULL
                AND finished_stocks.status IN ('active', 'validated')
             WHERE products.category = 'finished_product'
               AND products.deleted_at IS NULL
             GROUP BY products.id, products.name"
        )->fetchAll();

        foreach ($products as $product) {
            $available = (float) $product['available_kg'];

            if ($available <= 0) {
                $this->ensureAlert(
                    'Stock produit fini bas: ' . $product['name'],
                    'Le produit fini ' . $product['name'] . ' est en rupture de stock.',
                    'danger'
                );
            } elseif ($available <= 500) {
                $this->ensureAlert(
                    'Stock produit fini bas: ' . $product['name'],
                    'Le stock disponible de ' . $product['name'] . ' est de ' . $this->kg($available) . '.',
                    'warning'
                );
            }
        }
    }

    private function generateYieldAlerts()
    {
        $machines = $this->query(
            "SELECT machines.name,
                    COALESCE(AVG(CASE
                        WHEN production_batches.input_quantity_kg > 0
                        THEN (production_batches.output_quantity_kg / production_batches.input_quantity_kg) * 100
                        ELSE NULL
                    END), 0) AS yield_rate,
                    COUNT(production_batches.id) AS batches_count
             FROM machines
             INNER JOIN machine_feeds ON machine_feeds.machine_id = machines.id
             INNER JOIN production_batches ON production_batches.machine_feed_id = machine_feeds.id
             WHERE production_batches.status IN ('active', 'validated')
               AND production_batches.started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
               AND production_batches.deleted_at IS NULL
               AND machine_feeds.deleted_at IS NULL
               AND machines.deleted_at IS NULL
             GROUP BY machines.id, machines.name
             HAVING batches_count > 0 AND yield_rate < 70"
        )->fetchAll();

        foreach ($machines as $machine) {
            $this->ensureAlert(
                'Rendement machine faible: ' . $machine['name'],
                'Le rendement moyen sur 7 jours est de ' . number_format((float) $machine['yield_rate'], 1, ',', ' ') . '%.',
                (float) $machine['yield_rate'] < 60 ? 'danger' : 'warning'
            );
        }
    }

    private function generatePendingWeighingAlerts()
    {
        $rows = $this->query(
            "SELECT reference, weighed_at, TIMESTAMPDIFF(HOUR, weighed_at, NOW()) AS pending_hours
             FROM weighings
             WHERE status = 'pending'
               AND weighed_at < DATE_SUB(NOW(), INTERVAL 4 HOUR)
               AND deleted_at IS NULL"
        )->fetchAll();

        foreach ($rows as $row) {
            $hours = (int) $row['pending_hours'];
            $this->ensureAlert(
                'Pesee en attente trop longue: ' . $row['reference'],
                'La pesee ' . $row['reference'] . ' attend une validation depuis environ ' . $hours . ' h.',
                $hours >= 12 ? 'danger' : 'warning'
            );
        }
    }

    private function generateProductionGapAlerts()
    {
        $rows = $this->query(
            "SELECT batch_number,
                    input_quantity_kg,
                    output_quantity_kg,
                    waste_quantity_kg,
                    CASE
                        WHEN input_quantity_kg > 0
                        THEN ((input_quantity_kg - output_quantity_kg - waste_quantity_kg) / input_quantity_kg) * 100
                        ELSE 0
                    END AS gap_rate
             FROM production_batches
             WHERE status IN ('active', 'validated')
               AND input_quantity_kg > 0
               AND started_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
               AND deleted_at IS NULL
             HAVING gap_rate > 10"
        )->fetchAll();

        foreach ($rows as $row) {
            $gapRate = (float) $row['gap_rate'];
            $this->ensureAlert(
                'Ecart anormal de production: ' . $row['batch_number'],
                'Le lot ' . $row['batch_number'] . ' presente un ecart non explique de ' . number_format($gapRate, 1, ',', ' ') . '%.',
                $gapRate > 20 ? 'danger' : 'warning'
            );
        }
    }

    private function ensureAlert($title, $message, $severity)
    {
        $existing = $this->query(
            "SELECT id
             FROM alerts
             WHERE title = :title
               AND status = 'active'
               AND deleted_at IS NULL
             LIMIT 1",
            ['title' => $title]
        )->fetch();

        if ($existing) {
            $this->query(
                "UPDATE alerts
                 SET message = :message,
                     severity = :severity
                 WHERE id = :id",
                [
                    'message' => $message,
                    'severity' => $severity,
                    'id' => $existing['id'],
                ]
            );
            return;
        }

        $this->query(
            "INSERT INTO alerts (title, message, severity, status)
             VALUES (:title, :message, :severity, 'active')",
            [
                'title' => $title,
                'message' => $message,
                'severity' => $severity,
            ]
        );
    }

    private function withType(array $alert)
    {
        $alert['type'] = 'system';
        $alert['type_label'] = 'Systeme';

        foreach ($this->types as $type => $definition) {
            $prefix = rtrim($definition['pattern'], '%');
            if (strpos($alert['title'], $prefix) === 0) {
                $alert['type'] = $type;
                $alert['type_label'] = $definition['label'];
                break;
            }
        }

        return $alert;
    }

    private function kg($value)
    {
        return number_format((float) $value, 0, ',', ' ') . ' kg';
    }
}
