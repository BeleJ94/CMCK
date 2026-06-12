<?php

class Silo extends Model
{
    protected $table = 'silos';

    public function allWithStats()
    {
        return $this->query(
            "SELECT silos.*,
                    products.name AS product_name,
                    CASE
                        WHEN silos.capacity_kg > 0 THEN (silos.current_stock_kg / silos.capacity_kg) * 100
                        ELSE 0
                    END AS fill_rate
             FROM silos
             LEFT JOIN products ON products.id = silos.product_id
             WHERE silos.deleted_at IS NULL
             ORDER BY silos.name ASC"
        )->fetchAll();
    }

    public function findDetailed($id)
    {
        return $this->query(
            "SELECT silos.*,
                    products.name AS product_name,
                    CASE
                        WHEN silos.capacity_kg > 0 THEN (silos.current_stock_kg / silos.capacity_kg) * 100
                        ELSE 0
                    END AS fill_rate
             FROM silos
             LEFT JOIN products ON products.id = silos.product_id
             WHERE silos.id = :id
               AND silos.deleted_at IS NULL
             LIMIT 1",
            ['id' => $id]
        )->fetch();
    }

    public function movements($siloId = null)
    {
        $params = [];
        $where = "WHERE silo_movements.deleted_at IS NULL";

        if ($siloId !== null) {
            $where .= " AND silo_movements.silo_id = :silo_id";
            $params['silo_id'] = $siloId;
        }

        return $this->query(
            "SELECT silo_movements.*,
                    silos.name AS silo_name,
                    silos.code AS silo_code,
                    products.name AS product_name,
                    weighings.reference AS weighing_reference,
                    users.name AS created_by_name
             FROM silo_movements
             INNER JOIN silos ON silos.id = silo_movements.silo_id
             INNER JOIN products ON products.id = silo_movements.product_id
             LEFT JOIN weighings ON weighings.id = silo_movements.weighing_id
             LEFT JOIN users ON users.id = silo_movements.created_by
             {$where}
             ORDER BY silo_movements.movement_at DESC",
            $params
        )->fetchAll();
    }

    public function entriesByDelivery($siloId = null)
    {
        $params = [];
        $where = "WHERE silo_movements.deleted_at IS NULL
                    AND silo_movements.movement_type = 'in'
                    AND silo_movements.weighing_id IS NOT NULL";

        if ($siloId !== null) {
            $where .= " AND silo_movements.silo_id = :silo_id";
            $params['silo_id'] = $siloId;
        }

        return $this->query(
            "SELECT silo_movements.*,
                    silos.name AS silo_name,
                    suppliers.name AS supplier_name,
                    trucks.plate_number,
                    weighings.reference AS weighing_reference
             FROM silo_movements
             INNER JOIN silos ON silos.id = silo_movements.silo_id
             INNER JOIN weighings ON weighings.id = silo_movements.weighing_id
             INNER JOIN suppliers ON suppliers.id = weighings.supplier_id
             INNER JOIN trucks ON trucks.id = weighings.truck_id
             {$where}
             ORDER BY silo_movements.movement_at DESC",
            $params
        )->fetchAll();
    }

    public function exitsToMachines($siloId = null)
    {
        $params = [];
        $where = "WHERE machine_feeds.deleted_at IS NULL";

        if ($siloId !== null) {
            $where .= " AND machine_feeds.silo_id = :silo_id";
            $params['silo_id'] = $siloId;
        }

        return $this->query(
            "SELECT machine_feeds.*,
                    silos.name AS silo_name,
                    machines.name AS machine_name,
                    products.name AS product_name,
                    users.name AS created_by_name
             FROM machine_feeds
             INNER JOIN silos ON silos.id = machine_feeds.silo_id
             INNER JOIN machines ON machines.id = machine_feeds.machine_id
             INNER JOIN products ON products.id = machine_feeds.product_id
             LEFT JOIN users ON users.id = machine_feeds.created_by
             {$where}
             ORDER BY machine_feeds.fed_at DESC",
            $params
        )->fetchAll();
    }

    public function alerts()
    {
        return array_values(array_filter($this->allWithStats(), function ($silo) {
            return $this->isLowStock($silo) || $this->isAlmostFull($silo) || (float) $silo['current_stock_kg'] > (float) $silo['capacity_kg'];
        }));
    }

    public function isLowStock(array $silo)
    {
        return (float) $silo['alert_threshold_kg'] > 0
            && (float) $silo['current_stock_kg'] <= (float) $silo['alert_threshold_kg'];
    }

    public function isAlmostFull(array $silo)
    {
        return (float) $silo['fill_rate'] >= 90;
    }
}
