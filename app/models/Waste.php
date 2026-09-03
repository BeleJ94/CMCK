<?php

class Waste extends Model
{
    protected $table = 'waste_processings';

    public function totalAvailable()
    {
        $params = [];
        $siteClause = Auth::siteClause('site_id', $params);
        $row = $this->query(
            "SELECT COALESCE(SUM(quantity_kg), 0) AS total
             FROM waste_stocks
             WHERE status = 'active'
               AND storage_status IN ('available', 'buffer')
               AND deleted_at IS NULL{$siteClause}", $params
        )->fetch();

        return (float) $row['total'];
    }

    public function stockLines()
    {
        $params = [];
        $siteClause = Auth::siteClause('waste_stocks.site_id', $params);
        return $this->query(
            "SELECT waste_stocks.*,
                    products.name AS product_name,
                    production_batches.batch_number,waste_types.name AS waste_type_name,sites.code AS site_code
             FROM waste_stocks
             INNER JOIN products ON products.id = waste_stocks.product_id
             LEFT JOIN production_batches ON production_batches.id = waste_stocks.production_batch_id
             LEFT JOIN waste_types ON waste_types.id=waste_stocks.waste_type_id
             JOIN sites ON sites.id=waste_stocks.site_id
             WHERE waste_stocks.deleted_at IS NULL{$siteClause}
             ORDER BY waste_stocks.created_at ASC, waste_stocks.id ASC", $params
        )->fetchAll();
    }

    public function sendToBuffer($id,array$user){$this->db->beginTransaction();try{$s=$this->query('SELECT * FROM waste_stocks WHERE id=:id AND deleted_at IS NULL FOR UPDATE',['id'=>$id])->fetch();if(!$s)throw new RuntimeException('Stock introuvable.');Auth::requireSiteAccess($s['site_id']);$this->query("UPDATE waste_stocks SET storage_status='buffer' WHERE id=:id",['id'=>$id]);$this->query("INSERT INTO waste_stock_movements(waste_stock_id,movement_type,quantity_kg,stock_before_kg,stock_after_kg,reference_type,reference_id,movement_at,created_by) VALUES(:s,'buffer',:q,:b,:a,'waste_stocks',:r,NOW(),:u)",['s'=>$id,'q'=>$s['quantity_kg'],'b'=>$s['quantity_kg'],'a'=>$s['quantity_kg'],'r'=>$id,'u'=>$user['id']]);$this->db->commit();}catch(Exception$e){$this->db->rollBack();throw$e;}}
    public function sell(array$d,array$user){$this->db->beginTransaction();try{$s=$this->query('SELECT * FROM waste_stocks WHERE id=:id AND deleted_at IS NULL FOR UPDATE',['id'=>$d['waste_stock_id']])->fetch();$q=(float)$d['quantity_kg'];if(!$s||$q<=0||$q>(float)$s['quantity_kg'])throw new RuntimeException('Quantité de vente supérieure au disponible.');Auth::requireSiteAccess($s['site_id']);$n='VDEC-'.date('YmdHis').'-'.random_int(100,999);$this->query('INSERT INTO waste_sales(waste_stock_id,sale_number,customer_name,quantity_kg,unit_price,sold_at,created_by) VALUES(:s,:n,:c,:q,:p,NOW(),:u)',['s'=>$s['id'],'n'=>$n,'c'=>$d['customer_name'],'q'=>$q,'p'=>$d['unit_price'],'u'=>$user['id']]);$id=$this->db->lastInsertId();$b=(float)$s['quantity_kg'];$a=$b-$q;$this->query("UPDATE waste_stocks SET quantity_kg=:q,storage_status=IF(:q2>0,'available','sold'),status=IF(:q3>0,'active','inactive') WHERE id=:id",['q'=>$a,'q2'=>$a,'q3'=>$a,'id'=>$s['id']]);$this->query("INSERT INTO waste_stock_movements(waste_stock_id,movement_type,quantity_kg,stock_before_kg,stock_after_kg,reference_type,reference_id,movement_at,created_by) VALUES(:s,'sale',:q,:b,:a,'waste_sales',:r,NOW(),:u)",['s'=>$s['id'],'q'=>$q,'b'=>$b,'a'=>$a,'r'=>$id,'u'=>$user['id']]);$this->db->commit();}catch(Exception$e){$this->db->rollBack();throw$e;}}

    public function wasteMachines()
    {
        $params = [];
        $siteClause = Auth::siteClause('site_id', $params);
        return $this->query(
            "SELECT id, name, code, capacity_kg_hour
             FROM machines
             WHERE machine_type = 'waste'
               AND status = 'active'
               AND deleted_at IS NULL{$siteClause}
             ORDER BY name ASC", $params
        )->fetchAll();
    }

    public function history()
    {
        $params = [];
        $siteClause = Auth::siteClause('waste_processings.site_id', $params);
        return $this->query(
            "SELECT waste_processings.*,
                    machines.name AS machine_name,
                    users.name AS agent_name,
                    waste_stocks.production_batch_id,
                    production_batches.batch_number,
                    products.name AS waste_product_name
             FROM waste_processings
             INNER JOIN waste_stocks ON waste_stocks.id = waste_processings.waste_stock_id
             INNER JOIN products ON products.id = waste_stocks.product_id
             LEFT JOIN production_batches ON production_batches.id = waste_stocks.production_batch_id
             LEFT JOIN machines ON machines.id = waste_processings.machine_id
             LEFT JOIN users ON users.id = waste_processings.created_by
             WHERE waste_processings.deleted_at IS NULL{$siteClause}
             ORDER BY waste_processings.processed_at DESC, waste_processings.id DESC", $params
        )->fetchAll();
    }

    public function processWaste(array $data, array $user)
    {
        $siteId = Auth::requireCurrentSite();
        $this->db->beginTransaction();

        try {
            $machine = $this->query(
                "SELECT id
                 FROM machines
                 WHERE id = :id
                   AND machine_type = 'waste'
                   AND status = 'active'
                   AND deleted_at IS NULL AND site_id = :site_id
                 LIMIT 1
                 FOR UPDATE",
                ['id' => $data['machine_id'], 'site_id' => $siteId]
            )->fetch();

            if (!$machine) {
                throw new RuntimeException('Machine dechets active introuvable.');
            }

            $stocks = $this->query(
                "SELECT id, quantity_kg
                 FROM waste_stocks
                 WHERE quantity_kg > 0
                   AND status = 'active'
                   AND storage_status IN ('available', 'buffer')
                   AND deleted_at IS NULL AND site_id = :site_id
                 ORDER BY created_at ASC, id ASC
                 FOR UPDATE", ['site_id' => $siteId]
            )->fetchAll();

            $available = array_sum(array_map(function ($stock) {
                return (float) $stock['quantity_kg'];
            }, $stocks));

            $input = (float) $data['input_quantity_kg'];
            $output = (float) $data['output_quantity_kg'];

            if ($input > $available) {
                throw new RuntimeException('La quantite traitee depasse le stock dechets disponible.');
            }

            if ($output > $input) {
                throw new RuntimeException('L aliment betail produit ne peut pas depasser la quantite dechets traitee.');
            }

            $remainingInput = $input;
            $remainingOutput = $output;

            foreach ($stocks as $index => $stock) {
                if ($remainingInput <= 0) {
                    break;
                }

                $consume = min((float) $stock['quantity_kg'], $remainingInput);
                $allocatedOutput = $index === count($stocks) - 1 || $consume >= $remainingInput
                    ? $remainingOutput
                    : round(($consume / $input) * $output, 3);

                $this->query(
                    "UPDATE waste_stocks
                     SET quantity_kg = quantity_kg - :quantity_kg_update,
                         status = CASE WHEN quantity_kg - :quantity_kg_status <= 0 THEN 'inactive' ELSE status END,
                         storage_status = CASE WHEN quantity_kg - :quantity_kg_storage <= 0 THEN 'consumed' ELSE storage_status END
                     WHERE id = :id",
                    [
                        'quantity_kg_update' => $consume,
                        'quantity_kg_status' => $consume,
                        'quantity_kg_storage' => $consume,
                        'id' => $stock['id'],
                    ]
                );

                $this->query(
                    "INSERT INTO waste_processings (
                        site_id, waste_stock_id, machine_id, input_quantity_kg, output_quantity_kg,
                        processed_at, status, created_by
                     ) VALUES (
                        :site_id, :waste_stock_id, :machine_id, :input_quantity_kg, :output_quantity_kg,
                        :processed_at, 'validated', :created_by
                     )",
                    [
                        'site_id' => $siteId, 'waste_stock_id' => $stock['id'],
                        'machine_id' => $machine['id'],
                        'input_quantity_kg' => $consume,
                        'output_quantity_kg' => $allocatedOutput,
                        'processed_at' => $data['processed_at'],
                        'created_by' => $user['id'] ?? null,
                    ]
                );

                $processingId = $this->db->lastInsertId();
                $this->query(
                    "INSERT INTO waste_stock_movements(waste_stock_id,movement_type,quantity_kg,stock_before_kg,stock_after_kg,reference_type,reference_id,movement_at,created_by)
                     VALUES(:stock,'processing',:quantity,:before,:after,'waste_processings',:reference,:at,:user)",
                    ['stock'=>$stock['id'],'quantity'=>$consume,'before'=>$stock['quantity_kg'],'after'=>(float)$stock['quantity_kg']-$consume,'reference'=>$processingId,'at'=>$data['processed_at'],'user'=>$user['id'] ?? null]
                );

                $remainingInput -= $consume;
                $remainingOutput -= $allocatedOutput;
            }

            $animalFeedId = $this->productId('ALIMENT-BETAIL');
            $stockBefore = $this->latestProductStock($animalFeedId, $siteId);
            $stockAfter = $stockBefore + $output;

            if ($output > 0) {
                $this->query(
                    "INSERT INTO stock_movements (
                        site_id, product_id, finished_stock_id, distribution_id, movement_type,
                        quantity_bags, quantity_kg, stock_before_kg, stock_after_kg,
                        movement_at, status, created_by
                     ) VALUES (
                        :site_id, :product_id, NULL, NULL, 'in',
                        0, :quantity_kg, :stock_before_kg, :stock_after_kg,
                        :movement_at, 'validated', :created_by
                     )",
                    [
                        'site_id' => $siteId, 'product_id' => $animalFeedId,
                        'quantity_kg' => $output,
                        'stock_before_kg' => $stockBefore,
                        'stock_after_kg' => $stockAfter,
                        'movement_at' => $data['processed_at'],
                        'created_by' => $user['id'] ?? null,
                    ]
                );
                $stockMovementId = $this->db->lastInsertId();
            } else {
                $stockMovementId = null;
            }

            $this->logActivity(
                'production',
                'dechets',
                'waste_processings',
                null,
                'Traitement dechets et production aliment betail.',
                ['available_waste_kg' => $available],
                ['input_quantity_kg' => $input, 'output_quantity_kg' => $output, 'processed_at' => $data['processed_at']],
                $user
            );

            if ($stockMovementId !== null) {
                $this->logActivity(
                    'stock_movement',
                    'stocks-finis',
                    'stock_movements',
                    $stockMovementId,
                    'Entree stock fini depuis traitement dechets.',
                    ['product_id' => $animalFeedId, 'stock_kg' => $stockBefore],
                    ['product_id' => $animalFeedId, 'stock_kg' => $stockAfter, 'quantity_kg' => $output],
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
