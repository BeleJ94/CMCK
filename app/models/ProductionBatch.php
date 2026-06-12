<?php

class ProductionBatch extends Model
{
    protected $table = 'production_batches';

    public function allDetailed()
    {
        return $this->query(
            "SELECT production_batches.*,
                    machines.name AS machine_name,
                    machine_feeds.quantity_kg AS feed_quantity_kg,
                    products.name AS product_name,
                    users.name AS agent_name,
                    validators.name AS validator_name
             FROM production_batches
             INNER JOIN machine_feeds ON machine_feeds.id = production_batches.machine_feed_id
             INNER JOIN machines ON machines.id = machine_feeds.machine_id
             INNER JOIN products ON products.id = production_batches.product_id
             LEFT JOIN users ON users.id = production_batches.created_by
             LEFT JOIN users validators ON validators.id = production_batches.validated_by
             WHERE production_batches.deleted_at IS NULL
             ORDER BY production_batches.started_at DESC"
        )->fetchAll();
    }

    public function pendingForSelect()
    {
        return $this->query(
            "SELECT production_batches.id,
                    production_batches.batch_number,
                    production_batches.input_quantity_kg,
                    production_batches.started_at,
                    machines.name AS machine_name
             FROM production_batches
             INNER JOIN machine_feeds ON machine_feeds.id = production_batches.machine_feed_id
             INNER JOIN machines ON machines.id = machine_feeds.machine_id
             WHERE production_batches.status = 'pending'
               AND production_batches.deleted_at IS NULL
             ORDER BY production_batches.started_at ASC"
        )->fetchAll();
    }

    public function findDetailed($id)
    {
        return $this->query(
            "SELECT production_batches.*,
                    machine_feeds.quantity_kg AS feed_quantity_kg,
                    machines.name AS machine_name,
                    products.name AS product_name,
                    users.name AS agent_name,
                    validators.name AS validator_name
             FROM production_batches
             INNER JOIN machine_feeds ON machine_feeds.id = production_batches.machine_feed_id
             INNER JOIN machines ON machines.id = machine_feeds.machine_id
             INNER JOIN products ON products.id = production_batches.product_id
             LEFT JOIN users ON users.id = production_batches.created_by
             LEFT JOIN users validators ON validators.id = production_batches.validated_by
             WHERE production_batches.id = :id
               AND production_batches.deleted_at IS NULL
             LIMIT 1",
            ['id' => $id]
        )->fetch();
    }

    public function validateProduction(array $data, array $user)
    {
        $this->db->beginTransaction();

        try {
            $batch = $this->query(
                "SELECT *
                 FROM production_batches
                 WHERE id = :id
                   AND deleted_at IS NULL
                 FOR UPDATE",
                ['id' => $data['production_batch_id']]
            )->fetch();

            if (!$batch) {
                throw new RuntimeException('Lot de traitement introuvable.');
            }

            if ($batch['status'] === 'validated') {
                throw new RuntimeException('Ce lot est deja valide.');
            }

            $treated = (float) $batch['input_quantity_kg'];
            $good = (float) $data['output_quantity_kg'];
            $waste = (float) $data['waste_quantity_kg'];

            if ($good + $waste > $treated) {
                throw new RuntimeException('Bon produit + dechets ne doit pas depasser la quantite traitee.');
            }

            $flourProductId = $this->productId('FARINE-MAIS');
            $wasteProductId = $this->productId('DECHETS-MAIS');
            $stockBefore = $this->latestProductStock($flourProductId);
            $stockAfter = $stockBefore + $good;

            $this->query(
                "UPDATE production_batches
                 SET product_id = :product_id,
                     output_quantity_kg = :output_quantity_kg,
                     waste_quantity_kg = :waste_quantity_kg,
                     ended_at = :ended_at,
                     status = 'validated',
                     validated_by = :validated_by
                 WHERE id = :id",
                [
                    'product_id' => $flourProductId,
                    'output_quantity_kg' => $good,
                    'waste_quantity_kg' => $waste,
                    'ended_at' => $data['ended_at'],
                    'validated_by' => $user['id'] ?? null,
                    'id' => $batch['id'],
                ]
            );

            $this->query(
                "UPDATE machine_feeds
                 SET status = 'validated'
                 WHERE id = :id",
                ['id' => $batch['machine_feed_id']]
            );

            if ($good > 0) {
                $this->query(
                    "INSERT INTO stock_movements (
                        product_id, finished_stock_id, distribution_id, movement_type,
                        quantity_bags, quantity_kg, stock_before_kg, stock_after_kg,
                        movement_at, status, created_by
                     ) VALUES (
                        :product_id, NULL, NULL, 'in',
                        0, :quantity_kg, :stock_before_kg, :stock_after_kg,
                        :movement_at, 'validated', :created_by
                     )",
                    [
                        'product_id' => $flourProductId,
                        'quantity_kg' => $good,
                        'stock_before_kg' => $stockBefore,
                        'stock_after_kg' => $stockAfter,
                        'movement_at' => $data['ended_at'],
                        'created_by' => $user['id'] ?? null,
                    ]
                );
                $stockMovementId = $this->db->lastInsertId();
            } else {
                $stockMovementId = null;
            }

            if ($waste > 0) {
                $this->query(
                    "INSERT INTO waste_stocks (product_id, production_batch_id, quantity_kg, status)
                     VALUES (:product_id, :production_batch_id, :quantity_kg, 'active')
                     ON DUPLICATE KEY UPDATE quantity_kg = VALUES(quantity_kg), status = 'active'",
                    [
                        'product_id' => $wasteProductId,
                        'production_batch_id' => $batch['id'],
                        'quantity_kg' => $waste,
                    ]
                );
            }

            $this->logActivity(
                'production',
                'production',
                'production_batches',
                $batch['id'],
                'Validation production.',
                $batch,
                [
                    'product_id' => $flourProductId,
                    'output_quantity_kg' => $good,
                    'waste_quantity_kg' => $waste,
                    'ended_at' => $data['ended_at'],
                    'status' => 'validated',
                ],
                $user
            );

            if ($stockMovementId !== null) {
                $this->logActivity(
                    'stock_movement',
                    'stocks-finis',
                    'stock_movements',
                    $stockMovementId,
                    'Entree stock fini depuis production.',
                    ['product_id' => $flourProductId, 'stock_kg' => $stockBefore],
                    ['product_id' => $flourProductId, 'stock_kg' => $stockAfter, 'quantity_kg' => $good],
                    $user
                );
            }

            $this->db->commit();
        } catch (Exception $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function productId($code)
    {
        $row = $this->query(
            "SELECT id FROM products WHERE code = :code AND deleted_at IS NULL LIMIT 1",
            ['code' => $code]
        )->fetch();

        if (!$row) {
            throw new RuntimeException('Produit requis introuvable: ' . $code);
        }

        return $row['id'];
    }

    private function latestProductStock($productId)
    {
        $row = $this->query(
            "SELECT stock_after_kg
             FROM stock_movements
             WHERE product_id = :product_id
               AND deleted_at IS NULL
             ORDER BY movement_at DESC, id DESC
             LIMIT 1",
            ['product_id' => $productId]
        )->fetch();

        return $row ? (float) $row['stock_after_kg'] : 0;
    }
}
