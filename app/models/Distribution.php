<?php

class Distribution extends Model
{
    protected $table = 'distributions';

    public function availableStocks()
    {
        return $this->query(
            "SELECT finished_stocks.id,
                    finished_stocks.product_id,
                    finished_stocks.bag_format_id,
                    finished_stocks.quantity_bags,
                    finished_stocks.total_weight_kg,
                    products.name AS product_name,
                    products.code AS product_code,
                    bag_formats.name AS format_name,
                    bag_formats.weight_kg
             FROM finished_stocks
             INNER JOIN products ON products.id = finished_stocks.product_id
             INNER JOIN bag_formats ON bag_formats.id = finished_stocks.bag_format_id
             WHERE finished_stocks.deleted_at IS NULL
               AND finished_stocks.status IN ('active', 'validated')
               AND finished_stocks.quantity_bags > 0
               AND finished_stocks.total_weight_kg > 0
               AND products.code IN ('FARINE-MAIS', 'ALIMENT-BETAIL')
             ORDER BY products.name ASC, bag_formats.weight_kg ASC, finished_stocks.created_at ASC"
        )->fetchAll();
    }

    public function history()
    {
        return $this->query(
            "SELECT distributions.*,
                    products.name AS product_name,
                    products.code AS product_code,
                    bag_formats.name AS format_name,
                    bag_formats.weight_kg,
                    users.name AS agent_name,
                    validators.name AS validator_name
             FROM distributions
             INNER JOIN products ON products.id = distributions.product_id
             INNER JOIN bag_formats ON bag_formats.id = distributions.bag_format_id
             LEFT JOIN users ON users.id = distributions.created_by
             LEFT JOIN users validators ON validators.id = distributions.validated_by
             WHERE distributions.deleted_at IS NULL
             ORDER BY distributions.distributed_at DESC, distributions.id DESC"
        )->fetchAll();
    }

    public function findDetailed($id)
    {
        return $this->query(
            "SELECT distributions.*,
                    products.name AS product_name,
                    products.code AS product_code,
                    bag_formats.name AS format_name,
                    bag_formats.weight_kg,
                    users.name AS agent_name,
                    validators.name AS validator_name
             FROM distributions
             INNER JOIN products ON products.id = distributions.product_id
             INNER JOIN bag_formats ON bag_formats.id = distributions.bag_format_id
             LEFT JOIN users ON users.id = distributions.created_by
             LEFT JOIN users validators ON validators.id = distributions.validated_by
             WHERE distributions.id = :id
               AND distributions.deleted_at IS NULL
             LIMIT 1",
            ['id' => $id]
        )->fetch();
    }

    public function createDistribution(array $data, array $user)
    {
        $this->db->beginTransaction();

        try {
            $stock = $this->lockedStock($data['finished_stock_id']);

            if (!$stock) {
                throw new RuntimeException('Stock produit fini introuvable.');
            }

            $bags = (int) $data['quantity_bags'];
            $totalWeight = (float) $stock['weight_kg'] * $bags;

            if ($bags > (int) $stock['quantity_bags'] || $totalWeight > (float) $stock['total_weight_kg']) {
                throw new RuntimeException('Impossible de sortir plus que le stock disponible.');
            }

            $voucher = $this->uniqueVoucher($data['exit_voucher'] ?: null);

            $this->query(
                "INSERT INTO distributions (
                    finished_stock_id, product_id, bag_format_id, recipient_name,
                    transporter, exit_voucher, quantity_bags, total_weight_kg,
                    distributed_at, status, created_by, validated_by
                 ) VALUES (
                    :finished_stock_id, :product_id, :bag_format_id, :recipient_name,
                    :transporter, :exit_voucher, :quantity_bags, :total_weight_kg,
                    :distributed_at, 'validated', :created_by, :validated_by
                 )",
                [
                    'finished_stock_id' => $stock['id'],
                    'product_id' => $stock['product_id'],
                    'bag_format_id' => $stock['bag_format_id'],
                    'recipient_name' => $data['recipient_name'],
                    'transporter' => $data['transporter'] ?: null,
                    'exit_voucher' => $voucher,
                    'quantity_bags' => $bags,
                    'total_weight_kg' => $totalWeight,
                    'distributed_at' => $data['distributed_at'],
                    'created_by' => $user['id'] ?? null,
                    'validated_by' => $user['id'] ?? null,
                ]
            );

            $distributionId = $this->db->lastInsertId();

            $this->query(
                "UPDATE finished_stocks
                 SET quantity_bags = quantity_bags - :quantity_bags_update,
                     total_weight_kg = total_weight_kg - :total_weight_kg_update,
                     status = CASE
                        WHEN quantity_bags - :quantity_bags_status <= 0 OR total_weight_kg - :total_weight_kg_status <= 0 THEN 'inactive'
                        ELSE status
                     END
                 WHERE id = :id",
                [
                    'quantity_bags_update' => $bags,
                    'total_weight_kg_update' => $totalWeight,
                    'quantity_bags_status' => $bags,
                    'total_weight_kg_status' => $totalWeight,
                    'id' => $stock['id'],
                ]
            );

            $stockBefore = $this->latestProductStock($stock['product_id']);
            $stockAfter = max($stockBefore - $totalWeight, 0);

            $this->query(
                "INSERT INTO stock_movements (
                    product_id, finished_stock_id, distribution_id, movement_type,
                    quantity_bags, quantity_kg, stock_before_kg, stock_after_kg,
                    movement_at, status, created_by
                 ) VALUES (
                    :product_id, :finished_stock_id, :distribution_id, 'out',
                    :quantity_bags, :quantity_kg, :stock_before_kg, :stock_after_kg,
                    :movement_at, 'validated', :created_by
                 )",
                [
                    'product_id' => $stock['product_id'],
                    'finished_stock_id' => $stock['id'],
                    'distribution_id' => $distributionId,
                    'quantity_bags' => $bags,
                    'quantity_kg' => $totalWeight,
                    'stock_before_kg' => $stockBefore,
                    'stock_after_kg' => $stockAfter,
                    'movement_at' => $data['distributed_at'],
                    'created_by' => $user['id'] ?? null,
                ]
            );
            $stockMovementId = $this->db->lastInsertId();

            $this->logActivity(
                'distribution',
                'distribution',
                'distributions',
                $distributionId,
                'Creation et validation distribution.',
                null,
                [
                    'exit_voucher' => $voucher,
                    'recipient_name' => $data['recipient_name'],
                    'quantity_bags' => $bags,
                    'total_weight_kg' => $totalWeight,
                    'status' => 'validated',
                ],
                $user
            );
            $this->logActivity(
                'stock_movement',
                'stocks-finis',
                'stock_movements',
                $stockMovementId,
                'Sortie stock fini par distribution.',
                ['finished_stock_id' => $stock['id'], 'stock_kg' => $stockBefore, 'quantity_bags' => $stock['quantity_bags']],
                ['finished_stock_id' => $stock['id'], 'stock_kg' => $stockAfter, 'quantity_bags_out' => $bags, 'quantity_kg' => $totalWeight],
                $user
            );

            $this->db->commit();

            return $distributionId;
        } catch (Exception $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function nextVoucher()
    {
        return 'BS-' . date('Y') . '-' . str_pad((string) ($this->lastId() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function lockedStock($id)
    {
        return $this->query(
            "SELECT finished_stocks.*,
                    products.name AS product_name,
                    bag_formats.name AS format_name,
                    bag_formats.weight_kg
             FROM finished_stocks
             INNER JOIN products ON products.id = finished_stocks.product_id
             INNER JOIN bag_formats ON bag_formats.id = finished_stocks.bag_format_id
             WHERE finished_stocks.id = :id
               AND finished_stocks.deleted_at IS NULL
               AND finished_stocks.status IN ('active', 'validated')
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

    private function uniqueVoucher($requested = null)
    {
        $base = strtoupper(trim((string) ($requested ?: $this->nextVoucher())));
        $base = preg_replace('/[^A-Z0-9-]+/', '-', $base) ?: $this->nextVoucher();
        $voucher = $base;
        $suffix = 1;

        while ($this->voucherExists($voucher)) {
            $voucher = $base . '-' . $suffix;
            $suffix++;
        }

        return $voucher;
    }

    private function voucherExists($voucher)
    {
        $row = $this->query(
            "SELECT COUNT(*) AS total FROM distributions WHERE exit_voucher = :exit_voucher",
            ['exit_voucher' => $voucher]
        )->fetch();

        return (int) $row['total'] > 0;
    }

    private function lastId()
    {
        $row = $this->query("SELECT COALESCE(MAX(id), 0) AS last_id FROM distributions")->fetch();

        return (int) $row['last_id'];
    }
}
