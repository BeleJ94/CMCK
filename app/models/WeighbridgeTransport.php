<?php

class WeighbridgeTransport extends Model
{
    protected $table = 'weighbridge_transports';

    public function references()
    {
        return [
            'sites' => $this->query("SELECT id,code,name FROM sites WHERE status='active' AND deleted_at IS NULL ORDER BY name")->fetchAll(),
            'suppliers' => $this->query("SELECT id,name FROM suppliers WHERE status IN('active','validated') AND deleted_at IS NULL ORDER BY name")->fetchAll(),
            'products' => $this->query("SELECT id,name FROM products WHERE category='raw_material' AND status IN('active','validated') AND deleted_at IS NULL ORDER BY name")->fetchAll(),
        ];
    }

    public function available()
    {
        $params = [];
        $scope = Auth::siteClause('t.site_id', $params);
        return $this->query("SELECT t.*,s.name supplier_name,os.name origin_site_name,p.name product_name,tr.plate_number,tr.driver_name,COALESCE(d.document_number,t.transport_reference) bt_number FROM weighbridge_transports t LEFT JOIN suppliers s ON s.id=t.supplier_id LEFT JOIN sites os ON os.id=t.origin_site_id JOIN products p ON p.id=t.product_id JOIN trucks tr ON tr.id=t.truck_id LEFT JOIN documents d ON d.entity_type='weighbridge_transports' AND d.entity_id=t.id AND d.document_type_id=(SELECT id FROM document_types WHERE code='BT' LIMIT 1) AND d.deleted_at IS NULL WHERE t.deleted_at IS NULL AND t.status='in_transit'{$scope} ORDER BY t.dispatched_at", $params)->fetchAll();
    }

    public function allDetailed()
    {
        $params = [];
        $scope = Auth::siteClause('t.site_id', $params);
        return $this->query("SELECT t.*,s.name supplier_name,os.name origin_site_name,p.name product_name,tr.plate_number,tr.driver_name,COALESCE(d.document_number,t.transport_reference) bt_number,w.reference weighing_reference FROM weighbridge_transports t LEFT JOIN suppliers s ON s.id=t.supplier_id LEFT JOIN sites os ON os.id=t.origin_site_id JOIN products p ON p.id=t.product_id JOIN trucks tr ON tr.id=t.truck_id LEFT JOIN weighings w ON w.transport_id=t.id AND w.deleted_at IS NULL LEFT JOIN documents d ON d.entity_type='weighbridge_transports' AND d.entity_id=t.id AND d.document_type_id=(SELECT id FROM document_types WHERE code='BT' LIMIT 1) AND d.deleted_at IS NULL WHERE t.deleted_at IS NULL{$scope} ORDER BY t.created_at DESC", $params)->fetchAll();
    }

    public function createTransport(array $data, array $user)
    {
        $siteId = Auth::requireCurrentSite();
        if (($data['origin_type'] ?? '') !== 'external_supplier') {
            throw new RuntimeException('Les BT agricoles doivent être créés et expédiés depuis Agriculture → Transports.');
        }
        $supplier = $this->query("SELECT id FROM suppliers WHERE id=:id AND status IN ('active','validated') AND deleted_at IS NULL", ['id'=>$data['supplier_id']??0])->fetch();
        if (!$supplier) {
            throw new RuntimeException('Sélectionnez un fournisseur actif.');
        }
        $this->db->beginTransaction();
        try {
            $truckId = $this->truck($data);
            $reference = 'TRP-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $this->query("INSERT INTO weighbridge_transports(site_id,origin_type,origin_site_id,supplier_id,product_id,truck_id,transport_reference,shipped_quantity_kg,route_description,toll_amount,toll_details,status,loaded_at,dispatched_at,created_by) VALUES(:site,:origin_type,:origin_site,:supplier,:product,:truck,:reference,:quantity,:route,:toll,:details,'in_transit',NOW(),NOW(),:user)", [
                'site'=>$siteId,'origin_type'=>$data['origin_type'],'origin_site'=>$data['origin_type']==='internal_farm'?$data['origin_site_id']:null,
                'supplier'=>$data['origin_type']==='external_supplier'?$data['supplier_id']:null,'product'=>$data['product_id'],'truck'=>$truckId,
                'reference'=>$reference,'quantity'=>$data['shipped_quantity_kg'],'route'=>$data['route_description'] ?: null,'toll'=>$data['toll_amount'],'details'=>$data['toll_details'] ?: null,'user'=>$user['id']
            ]);
            $id = (int) $this->db->lastInsertId();
            require_once dirname(__DIR__) . '/services/DocumentService.php';
            (new DocumentService($this->db))->register('BT', $siteId, 'weighbridge_transports', $id, 'approved', date('Y-m-d H:i:s'), $user);
            $this->logActivity('dispatch_transport','pont-bascule','weighbridge_transports',$id,'BT créé, chargement terminé et transport mis en transit.',null,$data,$user);
            $this->db->commit();
            return $id;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function truck(array $data)
    {
        $plate = strtoupper(trim($data['truck_plate_number']));
        $row = $this->query('SELECT * FROM trucks WHERE plate_number=:plate AND deleted_at IS NULL LIMIT 1 FOR UPDATE',['plate'=>$plate])->fetch();
        $supplier = $data['origin_type']==='external_supplier'?$data['supplier_id']:null;
        if ($row) {
            $this->query('UPDATE trucks SET supplier_id=:supplier,driver_name=:driver,driver_phone=:phone,status=\'active\' WHERE id=:id',['supplier'=>$supplier,'driver'=>$data['driver_name'] ?: $row['driver_name'],'phone'=>$data['driver_phone'] ?: $row['driver_phone'],'id'=>$row['id']]);
            return (int) $row['id'];
        }
        $this->query("INSERT INTO trucks(supplier_id,plate_number,driver_name,driver_phone,status) VALUES(:supplier,:plate,:driver,:phone,'active')",['supplier'=>$supplier,'plate'=>$plate,'driver'=>$data['driver_name'] ?: null,'phone'=>$data['driver_phone'] ?: null]);
        return (int) $this->db->lastInsertId();
    }
}
