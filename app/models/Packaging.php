<?php

class Packaging extends Model
{
    protected $table = 'packaging';

    public function availableBatches()
    {
        $params = [];
        $siteClause = Auth::siteClause('production_batches.site_id', $params);
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
               AND products.category = 'finished_product'{$siteClause}
             GROUP BY production_batches.id, products.id, machines.id
             HAVING available_quantity_kg > 0
             ORDER BY production_batches.ended_at ASC, production_batches.id ASC", $params
        )->fetchAll();
    }

    public function bagFormats()
    {
        return $this->query(
            "SELECT i.id packaging_item_id,b.id,b.name,b.weight_kg,i.code,i.target_product_code
             FROM empty_packaging_items i JOIN bag_formats b ON b.id=i.bag_format_id
             WHERE i.status='active' AND i.deleted_at IS NULL AND b.status = 'active' AND b.deleted_at IS NULL
             ORDER BY b.weight_kg ASC"
        )->fetchAll();
    }

    public function history()
    {
        $params = [];
        $siteClause = Auth::siteClause('packaging.site_id', $params);
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
             WHERE packaging.deleted_at IS NULL{$siteClause}
             ORDER BY packaging.packaged_at DESC, packaging.id DESC", $params
        )->fetchAll();
    }

    public function findBatch($id)
    {
        $params = ['id' => $id];
        $siteClause = Auth::siteClause('production_batches.site_id', $params);
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
               AND products.category = 'finished_product'{$siteClause}
             GROUP BY production_batches.id, products.id
             LIMIT 1",
            $params
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
            $packagingItem=null;
            if(!empty($data['packaging_item_id'])){$packagingItem=$this->query("SELECT i.*,b.weight_kg,b.name format_name FROM empty_packaging_items i JOIN bag_formats b ON b.id=i.bag_format_id WHERE i.id=:id AND i.status='active' AND i.deleted_at IS NULL FOR UPDATE",['id'=>$data['packaging_item_id']])->fetch();$format=$packagingItem?['id'=>$packagingItem['bag_format_id'],'weight_kg'=>$packagingItem['weight_kg'],'name'=>$packagingItem['format_name']]:null;}
            else{$format = $this->findBagFormat($data['bag_format_id']);}

            if (!$batch) {
                throw new RuntimeException('Lot de production introuvable ou non disponible.');
            }
            Auth::requireSiteAccess($batch['site_id']);

            if (!$format) {
                throw new RuntimeException('Format sac introuvable.');
            }

            $bags = (int) $data['bags_count'];
            $totalWeight = (float) $format['weight_kg'] * $bags;

            $emptyStock=null;
            if($packagingItem){$product=$this->query('SELECT code FROM products WHERE id=:id',['id'=>$batch['product_id']])->fetch();if(!$product||$product['code']!==$packagingItem['target_product_code']){throw new RuntimeException('Format d’emballage incompatible avec le produit.');}$emptyStock=$this->query('SELECT * FROM empty_packaging_stocks WHERE site_id=:site AND packaging_item_id=:item AND deleted_at IS NULL FOR UPDATE',['site'=>$batch['site_id'],'item'=>$packagingItem['id']])->fetch();if(!$emptyStock||(int)$emptyStock['operational_quantity']<$bags){throw new RuntimeException('Stock de sacs vides délivrés à l’atelier insuffisant.');}}

            $bulkStock = $this->query("SELECT * FROM bulk_flour_stocks WHERE production_batch_id=:batch AND deleted_at IS NULL FOR UPDATE", ['batch'=>$batch['id']])->fetch();
            if (!$bulkStock || (float)$bulkStock['quantity_kg'] < $totalWeight) {
                throw new RuntimeException('Stock de farine non emballée insuffisant.');
            }

            if ($totalWeight > (float) $batch['available_quantity_kg']) {
                throw new RuntimeException('Impossible d emballer plus que la quantite disponible.');
            }

            $this->query(
                "INSERT INTO packaging (
                    site_id, production_batch_id, bag_format_id, packaging_item_id, product_id, bags_count,
                    total_weight_kg, packaged_at, status, created_by
                 ) VALUES (
                    :site_id, :production_batch_id, :bag_format_id, :packaging_item_id, :product_id, :bags_count,
                    :total_weight_kg, :packaged_at, 'validated', :created_by
                 )",
                [
                    'site_id' => $batch['site_id'], 'production_batch_id' => $batch['id'],
                    'bag_format_id' => $format['id'],
                    'packaging_item_id'=>$packagingItem['id']??null,
                    'product_id' => $batch['product_id'],
                    'bags_count' => $bags,
                    'total_weight_kg' => $totalWeight,
                    'packaged_at' => $data['packaged_at'],
                    'created_by' => $user['id'] ?? null,
                ]
            );

            $packagingId = $this->db->lastInsertId();

            if($emptyStock){$emptyBefore=(int)$emptyStock['operational_quantity'];$emptyAfter=$emptyBefore-$bags;$this->query('UPDATE empty_packaging_stocks SET operational_quantity=:qty WHERE id=:id',['qty'=>$emptyAfter,'id'=>$emptyStock['id']]);$this->query("INSERT INTO empty_packaging_movements(stock_id,movement_type,quantity,stock_before,stock_after,unit_cost,reference_type,reference_id,movement_at,created_by) VALUES(:stock,'consumption',:qty,:before,:after,:cost,'packaging',:packaging,:at,:user)",['stock'=>$emptyStock['id'],'qty'=>$bags,'before'=>$emptyBefore,'after'=>$emptyAfter,'cost'=>$emptyStock['average_unit_cost'],'packaging'=>$packagingId,'at'=>$data['packaged_at'],'user'=>$user['id']]);}

            $bulkBefore=(float)$bulkStock['quantity_kg'];$bulkAfter=$bulkBefore-$totalWeight;
            $this->query("UPDATE bulk_flour_stocks SET quantity_kg=:qty,status=:status WHERE id=:id",['qty'=>$bulkAfter,'status'=>$bulkAfter>0?'active':'depleted','id'=>$bulkStock['id']]);
            $this->query("INSERT INTO bulk_flour_stock_movements(bulk_flour_stock_id,production_batch_id,packaging_id,movement_type,quantity_kg,stock_before_kg,stock_after_kg,movement_at,created_by) VALUES(:stock,:batch,:packaging,'packaging_out',:qty,:before,:after,:at,:user)",['stock'=>$bulkStock['id'],'batch'=>$batch['id'],'packaging'=>$packagingId,'qty'=>$totalWeight,'before'=>$bulkBefore,'after'=>$bulkAfter,'at'=>$data['packaged_at'],'user'=>$user['id']]);

            $this->query(
                "INSERT INTO finished_stocks (
                    site_id, product_id, bag_format_id, packaging_id, quantity_bags,
                    total_weight_kg, status
                 ) VALUES (
                    :site_id, :product_id, :bag_format_id, :packaging_id, :quantity_bags,
                    :total_weight_kg, 'active'
                 )",
                [
                    'site_id' => $batch['site_id'], 'product_id' => $batch['product_id'],
                    'bag_format_id' => $format['id'],
                    'packaging_id' => $packagingId,
                    'quantity_bags' => $bags,
                    'total_weight_kg' => $totalWeight,
                ]
            );

            $finishedStockId = $this->db->lastInsertId();
            $stockBefore = $this->latestProductStock($batch['product_id'], $batch['site_id']);
            $stockAfter = $stockBefore + $totalWeight;

            $this->query(
                "INSERT INTO stock_movements (
                    site_id, product_id, finished_stock_id, distribution_id, movement_type,
                    quantity_bags, quantity_kg, stock_before_kg, stock_after_kg,
                    movement_at, status, created_by
                 ) VALUES (
                    :site_id, :product_id, :finished_stock_id, NULL, 'in',
                    :quantity_bags, :quantity_kg, :stock_before_kg, :stock_after_kg,
                    :movement_at, 'validated', :created_by
                 )",
                [
                    'site_id' => $batch['site_id'], 'product_id' => $batch['product_id'],
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
            "SELECT production_batches.id, production_batches.site_id,
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

    private function latestProductStock($productId, $siteId)
    {
        $row = $this->query(
            "SELECT stock_after_kg
             FROM stock_movements
             WHERE product_id = :product_id AND site_id = :site_id
               AND deleted_at IS NULL
             ORDER BY movement_at DESC, id DESC
             LIMIT 1",
            ['product_id' => $productId, 'site_id' => $siteId]
        )->fetch();

        return $row ? (float) $row['stock_after_kg'] : 0;
    }
}
