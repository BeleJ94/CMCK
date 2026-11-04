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

    public function sendToBuffer($id,array$user){$this->db->beginTransaction();try{$s=$this->query('SELECT * FROM waste_stocks WHERE id=:id AND deleted_at IS NULL FOR UPDATE',['id'=>$id])->fetch();if(!$s)throw new RuntimeException('Stock introuvable.');Auth::requireSiteAccess($s['site_id']);if($s['status']!=='active'||(float)$s['quantity_kg']<=0||$s['storage_status']!=='available')throw new RuntimeException('Seul un stock disponible peut être placé en tampon.');$this->query("UPDATE waste_stocks SET storage_status='buffer' WHERE id=:id",['id'=>$id]);$this->query("INSERT INTO waste_stock_movements(waste_stock_id,movement_type,quantity_kg,stock_before_kg,stock_after_kg,reference_type,reference_id,movement_at,created_by) VALUES(:s,'buffer',:q,:b,:a,'waste_stocks',:r,NOW(),:u)",['s'=>$id,'q'=>$s['quantity_kg'],'b'=>$s['quantity_kg'],'a'=>$s['quantity_kg'],'r'=>$id,'u'=>$user['id']]);$this->db->commit();}catch(Exception$e){$this->db->rollBack();throw$e;}}
    // Vente en vrac des issues supprimée : les issues sont réservées à la fabrication d'aliment bétail.

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

    // L'ancienne transformation directe (processWaste) est remplacée par les ordres de fabrication d'aliment bétail (Pelletization).
}
