<?php

class Packaging extends Model
{
    protected $table = 'packaging';

    public function availableBatches()
    {
        return $this->query(
            "SELECT production_batches.id,
                    production_batches.batch_number,
                    production_batches.product_id,
                    production_batches.output_quantity_kg,
                    products.name AS product_name,
                    products.code AS product_code,
                    machines.name AS machine_name,
                    COALESCE(SUM(packaging.total_weight_kg), 0) AS packaged_quantity_kg,
                    production_batches.output_quantity_kg - COALESCE(SUM(packaging.total_weight_kg), 0) AS available_quantity_kg
             FROM production_batches
             INNER JOIN products ON products.id = production_batches.product_id
             INNER JOIN machine_feeds ON machine_feeds.id = production_batches.machine_feed_id
             INNER JOIN machines ON machines.id = machine_feeds.machine_id
             LEFT JOIN packaging ON packaging.production_batch_id = production_batches.id
                AND packaging.deleted_at IS NULL
                AND packaging.status IN ('validated', 'active')
             WHERE production_batches.status = 'validated'
               AND production_batches.output_quantity_kg > 0
               AND production_batches.deleted_at IS NULL
               AND products.category = 'finished_product'
             GROUP BY production_batches.id, products.id, machines.id
             HAVING available_quantity_kg > 0
             ORDER BY production_batches.ended_at ASC, production_batches.id ASC"
        )->fetchAll();
    }

    public function bagFormats()
    {
        return $this->query(
            "SELECT id, name, weight_kg
             FROM bag_formats
             WHERE status = 'active'
               AND deleted_at IS NULL
             ORDER BY weight_kg ASC"
        )->fetchAll();
    }

    public function history()
    {
        return $this->query(
            "SELECT packaging.*,
                    production_batches.batch_number,
                    products.name AS product_name,
                    bag_formats.name AS format_name,
                    bag_formats.weight_kg AS format_weight_kg,
                    users.name AS agent_name
             FROM packaging
             INNER JOIN production_batches ON production_batches.id = packaging.production_batch_id
             INNER JOIN products ON products.id = packaging.product_id
             INNER JOIN bag_formats ON bag_formats.id = packaging.bag_format_id
             LEFT JOIN users ON users.id = packaging.created_by
             WHERE packaging.deleted_at IS NULL
             ORDER BY packaging.packaged_at DESC, packaging.id DESC"
        )->fetchAll();
    }

    public function findBatch($id)
    {
        return $this->query(
            "SELECT production_batches.id,
                    production_batches.batch_number,
                    production_batches.product_id,
                    production_batches.output_quantity_kg,
                    products.name AS product_name,
                    COALESCE(SUM(packaging.total_weight_kg), 0) AS packaged_quantity_kg,
                    production_batches.output_quantity_kg - COALESCE(SUM(packaging.total_weight_kg), 0) AS available_quantity_kg
             FROM production_batches
             INNER JOIN products ON products.id = production_batches.product_id
             LEFT JOIN packaging ON packaging.production_batch_id = production_batches.id
                AND packaging.deleted_at IS NULL
                AND packaging.status IN ('validated', 'active')
             WHERE production_batches.id = :id
               AND production_batches.status = 'validated'
               AND production_batches.deleted_at IS NULL
               AND products.category = 'finished_product'
             GROUP BY production_batches.id, products.id
             LIMIT 1",
            ['id' => $id]
        )->fetch();
    }

    public function findBagFormat($id)
    {
        return $this->query(
            "SELECT id, name, weight_kg
             FROM bag_formats
             WHERE id = :id
               AND status = 'active'
               AND deleted_at IS NULL
             LIMIT 1",
            ['id' => $id]
        )->fetch();
    }

    public function createPackaging(array $data, array $user)
    {
        $this->db->beginTransaction();

        try {
            $batch = $this->lockedBatch($data['production_batch_id']);
            $format = $this->findBagFormat($data['bag_format_id']);

            if (!$batch) {
                throw new RuntimeException('Lot de production introuvable ou non disponible.');
            }

            if (!$format) {
                throw new RuntimeException('Format sac introuvable.');
            }

            $bags = (int) $data['bags_count'];
            $totalWeight = (float) $format['weight_kg'] * $bags;

            if ($totalWeight > (float) $batch['available_quantity_kg']) {
                throw new RuntimeException('Impossible d emballer plus que la quantite disponible.');
            }

            $this->query(
                "INSERT INTO packaging (
                    production_batch_id, bag_format_id, product_id, bags_count,
                    total_weight_kg, packaged_at, status, created_by
                 ) VALUES (
                    :production_batch_id, :bag_format_id, :product_id, :bags_count,
                    :total_weight_kg, :packaged_at, 'validated', :created_by
                 )",
                [
                    'production_batch_id' => $batch['id'],
                    'bag_format_id' => $format['id'],
                    'product_id' => $batch['product_id'],
                    'bags_count' => $bags,
                    'total_weight_kg' => $totalWeight,
                    'packaged_at' => $data['packaged_at'],
                    'created_by' => $user['id'] ?? null,
                ]
            );

            $packagingId = $this->db->lastInsertId();

            $this->query(
                "INSERT INTO finished_stocks (
                    product_id, bag_format_id, packaging_id, quantity_bags,
                    total_weight_kg, status
                 ) VALUES (
                    :product_id, :bag_format_id, :packaging_id, :quantity_bags,
                    :total_weight_kg, 'active'
                 )",
                [
                    'product_id' => $batch['product_id'],
                    'bag_format_id' => $format['id'],
                    'packaging_id' => $packagingId,
                    'quantity_bags' => $bags,
                    'total_weight_kg' => $totalWeight,
                ]
            );

            $finishedStockId = $this->db->lastInsertId();
            $stockBefore = $this->latestProductStock($batch['product_id']);
            $stockAfter = $stockBefore + $totalWeight;

            $this->query(
                "INSERT INTO stock_movements (
                    product_id, finished_stock_id, distribution_id, movement_type,
                    quantity_bags, quantity_kg, stock_before_kg, stock_after_kg,
                    movement_at, status, created_by
                 ) VALUES (
                    :product_id, :finished_stock_id, NULL, 'in',
                    :quantity_bags, :quantity_kg, :stock_before_kg, :stock_after_kg,
                    :movement_at, 'validated', :created_by
                 )",
                [
                    'product_id' => $batch['product_id'],
                    'finished_stock_id' => $finishedStockId,
                    'quantity_bags' => $bags,
                    'quantity_kg' => $totalWeight,
                    'stock_before_kg' => $stockBefore,
                    'stock_after_kg' => $stockAfter,
                    'movement_at' => $data['packaged_at'],
                    'created_by' => $user['id'] ?? null,
                ]
            );
            $stockMovementId = $this->db->lastInsertId();

            $this->logActivity(
                'stock_movement',
                'stocks-finis',
                'stock_movements',
                $stockMovementId,
                'Entree stock fini depuis emballage.',
                ['product_id' => $batch['product_id'], 'stock_kg' => $stockBefore],
                [
                    'product_id' => $batch['product_id'],
                    'finished_stock_id' => $finishedStockId,
                    'packaging_id' => $packagingId,
                    'quantity_bags' => $bags,
                    'quantity_kg' => $totalWeight,
                    'stock_kg' => $stockAfter,
                ],
                $user
            );

            $this->db->commit();
        } catch (Exception $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function lockedBatch($id)
    {
        return $this->query(
            "SELECT production_batches.id,
                    production_batches.product_id,
                    production_batches.output_quantity_kg,
                    COALESCE(SUM(packaging.total_weight_kg), 0) AS packaged_quantity_kg,
                    production_batches.output_quantity_kg - COALESCE(SUM(packaging.total_weight_kg), 0) AS available_quantity_kg
             FROM production_batches
             LEFT JOIN packaging ON packaging.production_batch_id = production_batches.id
                AND packaging.deleted_at IS NULL
                AND packaging.status IN ('validated', 'active')
             WHERE production_batches.id = :id
               AND production_batches.status = 'validated'
               AND production_batches.deleted_at IS NULL
             GROUP BY production_batches.id
             LIMIT 1
             FOR UPDATE",
            ['id' => $id]
        )->fetch();
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
