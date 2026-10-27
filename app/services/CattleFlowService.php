<?php
require_once __DIR__.'/PorcineFlowService.php';

/** Cattle bought from suppliers, slaughtered and weighed at the farm, then shipped like pig carcasses. */
class CattleFlowService extends PorcineFlowService
{
    public function cattleAvailable(): bool
    {
        return $this->available()&&$this->weighingReady()&&$this->hasTable('cattle_slaughter_details');
    }

    public function directory(): array
    {
        $params=[];$scope=Auth::siteClause('b.site_id',$params);
        $lots=$this->query("SELECT b.id,b.site_id,b.batch_number,b.facility_id,b.started_at,b.initial_heads,b.current_heads,b.initial_average_weight_kg,b.status,f.name facility_name,s.name site_name,
                r.id receipt_id,r.receipt_number,r.delivery_reference,r.received_at,r.live_weight_kg,r.live_gross_kg,r.live_tare_kg,r.tare_note,r.notes,su.name supplier_name,u.name receiver_name,
                (SELECT COALESCE(SUM(c.input_heads),0) FROM livestock_conversions c JOIN cattle_slaughter_details d ON d.conversion_id=c.id WHERE c.batch_id=b.id AND c.status='submitted') pending_heads,
                (SELECT COALESCE(SUM(c.input_heads),0) FROM livestock_conversions c JOIN cattle_slaughter_details d ON d.conversion_id=c.id WHERE c.batch_id=b.id AND c.status='validated') slaughtered_heads,
                (SELECT COALESCE(SUM(c.net_weight_kg),0) FROM livestock_conversions c JOIN cattle_slaughter_details d ON d.conversion_id=c.id WHERE c.batch_id=b.id AND c.status='validated') carcass_kg,
                (SELECT COALESCE(SUM(m.quantity_heads),0) FROM livestock_head_movements m WHERE m.batch_id=b.id AND m.movement_type='mortality') dead_heads,
                (SELECT MAX(c.converted_at) FROM livestock_conversions c JOIN cattle_slaughter_details d ON d.conversion_id=c.id WHERE c.batch_id=b.id AND c.status='validated') last_slaughter_at
            FROM livestock_batches b JOIN livestock_species sp ON sp.id=b.species_id JOIN livestock_facilities f ON f.id=b.facility_id JOIN sites s ON s.id=b.site_id
            LEFT JOIN cattle_receipts r ON r.batch_id=b.id LEFT JOIN suppliers su ON su.id=r.supplier_id LEFT JOIN users u ON u.id=r.created_by
            WHERE sp.code='BOVIN' AND b.deleted_at IS NULL AND b.status IN ('active','completed'){$scope}
            ORDER BY b.status='active' DESC,COALESCE(r.received_at,b.started_at) DESC,b.id DESC",$params)->fetchAll();
        foreach($lots as &$lot){
            $lot['available_heads']=max(0,(int)$lot['current_heads']-(int)$lot['pending_heads']);
            // Heads that left the lot outside farm slaughter, e.g. historical live transfers.
            $lot['other_exits']=max(0,(int)$lot['initial_heads']-(int)$lot['current_heads']-(int)$lot['slaughtered_heads']-(int)$lot['dead_heads']);
            $average=$lot['live_weight_kg']!==null&&(int)$lot['initial_heads']>0?(float)$lot['live_weight_kg']/(int)$lot['initial_heads']:($lot['initial_average_weight_kg']!==null?(float)$lot['initial_average_weight_kg']:null);
            $lot['average_live_kg']=$average;
            $lot['carcass_yield_pct']=$average&&(int)$lot['slaughtered_heads']>0?round((float)$lot['carcass_kg']/($average*(int)$lot['slaughtered_heads'])*100,1):null;
        }
        unset($lot);
        $slaughters=$this->query("SELECT c.*,d.expiry_date,b.batch_number,b.site_id,b.current_heads,f.name facility_name,s.name site_name,su.name supplier_name,
                u.name author,v.name validator,w.id weight_stock_id,w.quantity_kg stock_kg,w.reserved_kg stock_reserved_kg,w.status stock_status
            FROM livestock_conversions c JOIN cattle_slaughter_details d ON d.conversion_id=c.id JOIN livestock_batches b ON b.id=c.batch_id
            JOIN livestock_facilities f ON f.id=b.facility_id JOIN sites s ON s.id=b.site_id LEFT JOIN cattle_receipts r ON r.batch_id=b.id LEFT JOIN suppliers su ON su.id=r.supplier_id
            LEFT JOIN users u ON u.id=c.created_by LEFT JOIN users v ON v.id=c.validated_by LEFT JOIN livestock_weight_stocks w ON w.conversion_id=c.id
            WHERE 1=1{$scope} ORDER BY c.converted_at DESC,c.id DESC",$params)->fetchAll();
        $params=[];$scope=Auth::siteClause('t.source_site_id',$params);
        $shipments=$this->query("SELECT t.*,b.batch_number,c.conversion_number,c.converted_at,d.expiry_date,f.name facility_name,su.name supplier_name,
                r.receipt_number,r.status receipt_status,r.received_weight_kg,r.received_gross_kg,r.received_tare_kg,r.tare_note receipt_tare_note,r.weight_gap_kg,r.weight_gap_pct,r.tolerance_pct,r.gap_reason,r.received_at receipt_received_at,
                author.name author,approver.name approver,dispatcher.name dispatcher,weigher.name weigher,gap_approver.name gap_approver
            FROM livestock_transfers t JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN cattle_slaughter_details d ON d.conversion_id=w.conversion_id JOIN livestock_conversions c ON c.id=d.conversion_id
            JOIN livestock_batches b ON b.id=t.batch_id JOIN livestock_facilities f ON f.id=b.facility_id LEFT JOIN cattle_receipts cr ON cr.batch_id=b.id LEFT JOIN suppliers su ON su.id=cr.supplier_id
            LEFT JOIN butchery_receipts r ON r.livestock_transfer_id=t.id AND r.deleted_at IS NULL
            LEFT JOIN users author ON author.id=t.created_by LEFT JOIN users approver ON approver.id=t.approved_by LEFT JOIN users dispatcher ON dispatcher.id=t.dispatched_by
            LEFT JOIN users weigher ON weigher.id=r.created_by LEFT JOIN users gap_approver ON gap_approver.id=r.gap_approved_by
            WHERE t.deleted_at IS NULL{$scope} ORDER BY t.id DESC",$params)->fetchAll();
        $params=[];$scope=Auth::siteClause('f.site_id',$params);
        $facilities=$this->query("SELECT f.id,f.site_id,f.name,f.capacity_heads,s.name site_name,(SELECT COALESCE(SUM(x.current_heads),0) FROM livestock_batches x WHERE x.facility_id=f.id AND x.status='active' AND x.deleted_at IS NULL) occupied_heads
            FROM livestock_facilities f JOIN sites s ON s.id=f.site_id WHERE f.facility_type='housing' AND f.status='active' AND f.deleted_at IS NULL AND s.code IN ('FARM-MUT','FARM-DIK'){$scope} ORDER BY s.name,f.name",$params)->fetchAll();
        $item=$this->row("SELECT default_shelf_life_days FROM butchery_items WHERE code='MP-BOEUF' AND status='active' AND deleted_at IS NULL");
        return [
            'lots'=>$lots,'slaughters'=>$slaughters,'shipments'=>$shipments,'facilities'=>$facilities,
            'suppliers'=>$this->query("SELECT id,name FROM suppliers WHERE status='active' AND deleted_at IS NULL ORDER BY name")->fetchAll(),
            'shelfLifeDays'=>max(1,(int)($item['default_shelf_life_days']??5)),
        ];
    }

    /** Reception of cattle from a supplier: a new lot in a farm building. */
    public function receiveCattle(array $data,array $user): int
    {
        $site=$this->integer($data['site_id']??'');$this->access($site,'livestock','create',true);
        $supplier=$this->integer($data['supplier_id']??'');$facility=$this->integer($data['facility_id']??'');$heads=$this->integer($data['heads']??'');
        $at=$this->timestamp($data['received_at']??'');
        $weight=TransitWeighingService::parseOptional($data,'Poids vif');
        $reference=$this->optionalText($data['delivery_reference']??'',120,'Bon de livraison');$notes=$this->optionalText($data['notes']??'',500,'Observations');
        return $this->operation('cattle_receipt',$site,$data,compact('site','supplier','facility','heads','at','weight','reference','notes'),$user,function()use($site,$supplier,$facility,$heads,$at,$weight,$reference,$notes,$user){
            $f=$this->row("SELECT * FROM livestock_facilities WHERE id=? AND site_id=? AND facility_type='housing' AND status='active' AND deleted_at IS NULL FOR UPDATE",[$facility,$site]);
            if(!$f)throw new RuntimeException('Choisissez un bâtiment d’élevage actif de cette ferme.');
            $occupied=(int)$this->query("SELECT COALESCE(SUM(current_heads),0) FROM livestock_batches WHERE facility_id=? AND status='active' AND deleted_at IS NULL",[$facility])->fetchColumn();
            if($f['capacity_heads']!==null&&$occupied+$heads>(int)$f['capacity_heads'])throw new RuntimeException('Capacité du bâtiment dépassée : '.max(0,(int)$f['capacity_heads']-$occupied).' place(s) libre(s) sur '.(int)$f['capacity_heads'].'.');
            if(!$this->row("SELECT id FROM suppliers WHERE id=? AND status='active' AND deleted_at IS NULL",[$supplier]))throw new RuntimeException('Choisissez un fournisseur actif.');
            $species=$this->row("SELECT id FROM livestock_species WHERE code='BOVIN' AND status='active' AND deleted_at IS NULL");if(!$species)throw new RuntimeException('L’espèce Bovin doit être active.');
            $average=$weight===null?null:round($weight['net']/$heads,3);
            $this->query("INSERT INTO livestock_batches(site_id,species_id,facility_id,batch_number,purpose,started_at,initial_heads,current_heads,initial_average_weight_kg,current_average_weight_kg,status,created_by) VALUES(?,?,?,?,'fattening',?,?,?,?,?,'active',?)",
                [$site,$species['id'],$facility,$this->number('BOV'),substr($at,0,10),$heads,$heads,$average,$average,$user['id']]);
            $batch=(int)$this->db->lastInsertId();
            $this->query('INSERT INTO cattle_receipts(batch_id,site_id,supplier_id,receipt_number,delivery_reference,received_at,heads,live_gross_kg,live_tare_kg,live_weight_kg,tare_note,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$batch,$site,$supplier,$this->number('REC-BOV'),$reference,$at,$heads,$weight['gross']??null,$weight['tare']??null,$weight['net']??null,$weight['tare_note']??null,$notes,$user['id']]);
            $receipt=(int)$this->db->lastInsertId();
            $this->query("INSERT INTO livestock_head_movements(batch_id,movement_type,quantity_heads,heads_before,heads_after,reference_type,reference_id,movement_at,created_by) VALUES(?,'stocking',?,0,?,'cattle_receipts',?,?,?)",[$batch,$heads,$heads,$receipt,$at,$user['id']]);
            return $batch;
        });
    }

    /** Farm slaughter of cattle: carcass weight net or gross minus tare, validated by another user. */
    public function slaughter(array $data,array $user): int
    {
        $id=$this->integer($data['batch_id']??'');$heads=$this->integer($data['heads']??'');
        $weight=TransitWeighingService::parse($data,'Poids des carcasses');
        $at=$this->timestamp($data['slaughtered_at']??'');$expiry=$this->date($data['expiry_date']??'','DLC');
        if($expiry<date('Y-m-d')||$expiry<substr($at,0,10))throw new RuntimeException('La DLC doit couvrir la date d’abattage et ne pas être dépassée.');
        $b=$this->row("SELECT b.* FROM livestock_batches b JOIN livestock_species sp ON sp.id=b.species_id WHERE b.id=? AND sp.code='BOVIN' AND b.deleted_at IS NULL",[$id]);if(!$b)throw new RuntimeException('Lot de bovins introuvable.');
        $site=(int)$b['site_id'];$this->access($site,'livestock','create',true);
        return $this->operation('cattle_slaughter',$site,$data,compact('id','heads','weight','at','expiry'),$user,function()use($id,$heads,$weight,$at,$expiry,$user){
            $b=$this->row('SELECT * FROM livestock_batches WHERE id=? FOR UPDATE',[$id]);
            if($b['status']!=='active')throw new RuntimeException('Ce lot ne contient plus de bovins vivants.');
            if(substr($at,0,10)<$b['started_at'])throw new RuntimeException('L’abattage ne peut pas précéder l’arrivée du lot le '.date('d/m/Y',strtotime($b['started_at'])).'.');
            $committed=(int)$this->query("SELECT COALESCE(SUM(c.input_heads),0) FROM livestock_conversions c WHERE c.batch_id=? AND c.status='submitted'",[$id])->fetchColumn();
            if($heads>(int)$b['current_heads']-$committed)throw new RuntimeException('Effectif insuffisant : '.max(0,(int)$b['current_heads']-$committed).' bovin(s) disponible(s) après les abattages en attente.');
            $item=$this->row("SELECT id FROM butchery_items WHERE code='MP-BOEUF' AND item_kind='raw_material' AND status='active' AND deleted_at IS NULL");if(!$item)throw new RuntimeException('L’article Carcasse de bœuf doit être actif.');
            $this->query("INSERT INTO livestock_conversions(batch_id,conversion_number,conversion_type,input_heads,gross_weight_kg,tare_weight_kg,net_weight_kg,tare_note,converted_at,status,created_by) VALUES(?,?,'slaughter',?,?,?,?,?,?,'submitted',?)",
                [$id,$this->number('AB-BOEUF'),$heads,$weight['gross'],$weight['tare'],$weight['net'],$weight['tare_note'],$at,$user['id']]);
            $conversion=(int)$this->db->lastInsertId();
            $this->query('INSERT INTO cattle_slaughter_details(conversion_id,item_id,expiry_date) VALUES(?,?,?)',[$conversion,$item['id'],$expiry]);
            return $conversion;
        });
    }

    public function validateSlaughter($id,array $user): int
    {
        require_once dirname(__DIR__).'/models/Livestock.php';
        $r=$this->row('SELECT c.*,b.site_id,d.expiry_date FROM livestock_conversions c JOIN cattle_slaughter_details d ON d.conversion_id=c.id JOIN livestock_batches b ON b.id=c.batch_id WHERE c.id=?',[$id]);
        if(!$r)throw new RuntimeException('Abattage introuvable.');$this->access((int)$r['site_id'],'livestock','validate',true);
        if($r['expiry_date']<date('Y-m-d'))throw new RuntimeException('Les carcasses ont dépassé leur DLC.');
        return (new Livestock())->validateConversion($id,$user);
    }

    public function cancelSlaughter($id,array $data,array $user): int
    {
        $r=$this->row('SELECT c.*,b.site_id FROM livestock_conversions c JOIN cattle_slaughter_details d ON d.conversion_id=c.id JOIN livestock_batches b ON b.id=c.batch_id WHERE c.id=?',[$id]);if(!$r)throw new RuntimeException('Abattage introuvable.');
        $site=(int)$r['site_id'];$this->access($site,'livestock','delete',true);$reason=$this->text($data['reason']??'',500,'Motif');
        return $this->operation('cattle_slaughter_cancel',$site,$data,['conversion'=>(int)$id,'reason'=>$reason],$user,function()use($id){
            $c=$this->row('SELECT status FROM livestock_conversions WHERE id=? FOR UPDATE',[$id]);
            if($c['status']!=='submitted')throw new RuntimeException('Seul un abattage en attente peut être annulé ici.');
            $this->query("UPDATE livestock_conversions SET status='cancelled' WHERE id=?",[$id]);
            return (int)$id;
        });
    }
}
