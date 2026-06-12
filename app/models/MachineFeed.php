<?php

class MachineFeed extends Model
{
    protected $table = 'machine_feeds';

    public function allDetailed()
    {
        return $this->query(
            "SELECT machine_feeds.*,
                    silos.name AS silo_name,
                    silos.code AS silo_code,
                    machines.name AS machine_name,
                    products.name AS product_name,
                    users.name AS agent_name,
                    production_batches.id AS batch_id,
                    production_batches.batch_number,
                    production_batches.status AS batch_status
             FROM machine_feeds
             INNER JOIN silos ON silos.id = machine_feeds.silo_id
             INNER JOIN machines ON machines.id = machine_feeds.machine_id
             INNER JOIN products ON products.id = machine_feeds.product_id
             LEFT JOIN users ON users.id = machine_feeds.created_by
             LEFT JOIN production_batches ON production_batches.machine_feed_id = machine_feeds.id
                AND production_batches.deleted_at IS NULL
             WHERE machine_feeds.deleted_at IS NULL
             ORDER BY machine_feeds.fed_at DESC"
        )->fetchAll();
    }

    public function findDetailed($id)
    {
        return $this->query(
            "SELECT machine_feeds.*,
                    silos.name AS silo_name,
                    silos.code AS silo_code,
                    machines.name AS machine_name,
                    products.name AS product_name,
                    users.name AS agent_name,
                    production_batches.batch_number,
                    production_batches.input_quantity_kg,
                    production_batches.output_quantity_kg,
                    production_batches.waste_quantity_kg,
                    production_batches.status AS batch_status
             FROM machine_feeds
             INNER JOIN silos ON silos.id = machine_feeds.silo_id
             INNER JOIN machines ON machines.id = machine_feeds.machine_id
             INNER JOIN products ON products.id = machine_feeds.product_id
             LEFT JOIN users ON users.id = machine_feeds.created_by
             LEFT JOIN production_batches ON production_batches.machine_feed_id = machine_feeds.id
                AND production_batches.deleted_at IS NULL
             WHERE machine_feeds.id = :id
               AND machine_feeds.deleted_at IS NULL
             LIMIT 1",
            ['id' => $id]
        )->fetch();
    }

    public function silosForSelect()
    {
        return $this->query(
            "SELECT silos.id, silos.name, silos.code, silos.current_stock_kg, silos.product_id, products.name AS product_name
             FROM silos
             LEFT JOIN products ON products.id = silos.product_id
             WHERE silos.deleted_at IS NULL
               AND silos.status IN ('active', 'validated')
             ORDER BY silos.name ASC"
        )->fetchAll();
    }

    public function machinesForSelect()
    {
        return $this->query(
            "SELECT id, name, machine_type, capacity_kg_hour
             FROM machines
             WHERE deleted_at IS NULL
               AND status IN ('active', 'validated')
               AND machine_type = 'main'
             ORDER BY name ASC"
        )->fetchAll();
    }

    public function createFeed(array $data, array $user)
    {
        $this->db->beginTransaction();

        try {
            $silo = $this->query(
                "SELECT *
                 FROM silos
                 WHERE id = :id
                   AND deleted_at IS NULL
                 FOR UPDATE",
                ['id' => $data['silo_id']]
            )->fetch();

            if (!$silo) {
                throw new RuntimeException('Silo source introuvable.');
            }

            $quantity = (float) $data['quantity_kg'];
            $stockBefore = (float) $silo['current_stock_kg'];

            if ($quantity > $stockBefore) {
                throw new RuntimeException('Quantite envoyee superieure au stock silo.');
            }

            $stockAfter = $stockBefore - $quantity;

            $this->query(
                "INSERT INTO silo_movements (
                    silo_id, product_id, weighing_id, movement_type, quantity_kg,
                    stock_before_kg, stock_after_kg, movement_at, status, created_by
                 ) VALUES (
                    :silo_id, :product_id, NULL, 'out', :quantity_kg,
                    :stock_before_kg, :stock_after_kg, :movement_at, 'validated', :created_by
                 )",
                [
                    'silo_id' => $data['silo_id'],
                    'product_id' => $silo['product_id'],
                    'quantity_kg' => $quantity,
                    'stock_before_kg' => $stockBefore,
                    'stock_after_kg' => $stockAfter,
                    'movement_at' => $data['fed_at'],
                    'created_by' => $user['id'] ?? null,
                ]
            );

            $movementId = $this->db->lastInsertId();

            $this->query(
                "UPDATE silos
                 SET current_stock_kg = :stock
                 WHERE id = :id",
                ['stock' => $stockAfter, 'id' => $data['silo_id']]
            );

            $this->query(
                "INSERT INTO machine_feeds (
                    machine_id, silo_id, product_id, silo_movement_id, quantity_kg,
                    fed_at, ended_at, observation, status, created_by
                 ) VALUES (
                    :machine_id, :silo_id, :product_id, :silo_movement_id, :quantity_kg,
                    :fed_at, :ended_at, :observation, 'pending', :created_by
                 )",
                [
                    'machine_id' => $data['machine_id'],
                    'silo_id' => $data['silo_id'],
                    'product_id' => $silo['product_id'],
                    'silo_movement_id' => $movementId,
                    'quantity_kg' => $quantity,
                    'fed_at' => $data['fed_at'],
                    'ended_at' => $data['ended_at'] ?: null,
                    'observation' => $data['observation'] ?: null,
                    'created_by' => $user['id'] ?? null,
                ]
            );

            $feedId = $this->db->lastInsertId();

            $this->query(
                "INSERT INTO production_batches (
                    machine_feed_id, product_id, batch_number, input_quantity_kg,
                    output_quantity_kg, waste_quantity_kg, started_at, ended_at,
                    status, created_by
                 ) VALUES (
                    :machine_feed_id, :product_id, :batch_number, :input_quantity_kg,
                    0, 0, :started_at, :ended_at, 'pending', :created_by
                 )",
                [
                    'machine_feed_id' => $feedId,
                    'product_id' => $silo['product_id'],
                    'batch_number' => $this->batchNumber(),
                    'input_quantity_kg' => $quantity,
                    'started_at' => $data['fed_at'],
                    'ended_at' => $data['ended_at'] ?: null,
                    'created_by' => $user['id'] ?? null,
                ]
            );
            $batchId = $this->db->lastInsertId();

            $this->logActivity(
                'stock_movement',
                'silos',
                'silo_movements',
                $movementId,
                'Sortie silo vers alimentation machine.',
                ['silo_id' => $data['silo_id'], 'stock_kg' => $stockBefore],
                ['silo_id' => $data['silo_id'], 'stock_kg' => $stockAfter, 'quantity_kg' => $quantity, 'machine_id' => $data['machine_id']],
                $user
            );
            $this->logActivity(
                'create',
                'production',
                'production_batches',
                $batchId,
                'Creation lot de production depuis alimentation machine.',
                null,
                ['machine_feed_id' => $feedId, 'input_quantity_kg' => $quantity, 'status' => 'pending'],
                $user
            );

            $this->db->commit();

            return $feedId;
        } catch (Exception $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function batchNumber()
    {
        return 'LOT-' . date('Ymd-His') . '-' . random_int(100, 999);
    }
}
