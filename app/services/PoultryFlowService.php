<?php
require_once __DIR__.'/PorcineFlowService.php';

/**
 * Broilers and laying hens: supplier receptions and hatching, daily eggs and mortality, farm slaughter
 * weighed and counted, carcass or live shipments, and slaughter of live birds at the butchery.
 */
class PoultryFlowService extends PorcineFlowService
{
    private const ITEMS=['broiler'=>'MP-VOLAILLE','layer'=>'MP-POULE'];

    public function poultryAvailable(): bool
    {
        return $this->available()&&$this->weighingReady()&&$this->piecesReady();
    }

    public function directory(): array
    {
        $params=[];$scope=Auth::siteClause('b.site_id',$params);
        // Lots created before the poultry flow have no profile: their type follows the batch purpose.
        $lots=$this->query("SELECT b.id,b.site_id,b.batch_number,b.facility_id,b.purpose,b.started_at,b.initial_heads,b.current_heads,b.initial_average_weight_kg,b.current_average_weight_kg,b.status,
                f.name facility_name,s.name site_name,COALESCE(p.flock_type,IF(b.purpose='laying','layer','broiler')) flock_type,COALESCE(p.origin,'existing') origin,
                p.receipt_number,p.delivery_reference,COALESCE(p.received_at,b.started_at) received_at,p.age_days,p.live_weight_kg,p.live_gross_kg,p.live_tare_kg,p.tare_note,p.notes,p.mother_batch_id,
                mother.batch_number mother_number,mf.name mother_facility,su.name supplier_name,u.name receiver_name,
                (SELECT COALESCE(SUM(c.input_heads+d.dead_heads),0) FROM livestock_conversions c JOIN poultry_slaughter_details d ON d.conversion_id=c.id WHERE c.batch_id=b.id AND c.status='submitted') pending_heads,
                (SELECT COALESCE(SUM(t.quantity_heads),0) FROM livestock_transfers t JOIN poultry_live_shipments ls ON ls.transfer_id=t.id WHERE t.batch_id=b.id AND t.status IN ('draft','approved') AND t.deleted_at IS NULL) shipping_heads,
                (SELECT COALESCE(SUM(t.quantity_heads),0) FROM livestock_transfers t JOIN poultry_live_shipments ls ON ls.transfer_id=t.id WHERE t.batch_id=b.id AND t.status IN ('in_transit','received') AND t.deleted_at IS NULL) live_shipped_heads,
                (SELECT COALESCE(SUM(c.input_heads),0) FROM livestock_conversions c JOIN poultry_slaughter_details d ON d.conversion_id=c.id WHERE c.batch_id=b.id AND c.status='validated') slaughtered_heads,
                (SELECT COALESCE(SUM(c.net_weight_kg),0) FROM livestock_conversions c JOIN poultry_slaughter_details d ON d.conversion_id=c.id WHERE c.batch_id=b.id AND c.status='validated') carcass_kg,
                (SELECT COALESCE(SUM(m.quantity_heads),0) FROM livestock_head_movements m WHERE m.batch_id=b.id AND m.movement_type='mortality') dead_heads,
                (SELECT COALESCE(SUM(r.mortality_heads),0) FROM poultry_daily_records r WHERE r.batch_id=b.id AND r.recorded_on=CURDATE()) dead_today,
                (SELECT COALESCE(SUM(r.eggs_count),0) FROM poultry_daily_records r WHERE r.batch_id=b.id)+(SELECT COALESCE(SUM(pp.eggs_count),0) FROM poultry_productions pp WHERE pp.batch_id=b.id) eggs,
                (SELECT COALESCE(SUM(r.eggs_count),0) FROM poultry_daily_records r WHERE r.batch_id=b.id AND r.recorded_on=CURDATE()) eggs_today,
                (SELECT MAX(r.recorded_on) FROM poultry_daily_records r WHERE r.batch_id=b.id) last_record_on,
                (SELECT COALESCE(SUM(x.initial_heads),0) FROM poultry_lot_profiles xp JOIN livestock_batches x ON x.id=xp.batch_id WHERE xp.mother_batch_id=b.id AND x.deleted_at IS NULL) hatched_heads
            FROM livestock_batches b JOIN livestock_species sp ON sp.id=b.species_id JOIN livestock_facilities f ON f.id=b.facility_id JOIN sites s ON s.id=b.site_id
            LEFT JOIN poultry_lot_profiles p ON p.batch_id=b.id LEFT JOIN suppliers su ON su.id=p.supplier_id LEFT JOIN users u ON u.id=p.created_by
            LEFT JOIN livestock_batches mother ON mother.id=p.mother_batch_id LEFT JOIN livestock_facilities mf ON mf.id=mother.facility_id
            WHERE sp.code='POULET' AND b.deleted_at IS NULL AND b.status IN ('active','completed'){$scope}
            ORDER BY b.status='active' DESC,COALESCE(p.received_at,b.started_at) DESC,b.id DESC",$params)->fetchAll();
        $today=new DateTimeImmutable('today');
        foreach($lots as &$lot){
            $lot['available_heads']=max(0,(int)$lot['current_heads']-(int)$lot['pending_heads']-(int)$lot['shipping_heads']);
            // Heads that left outside this flow, e.g. sales recorded in the former daily follow-up.
            $lot['other_exits']=max(0,(int)$lot['initial_heads']-(int)$lot['current_heads']-(int)$lot['slaughtered_heads']-(int)$lot['dead_heads']-(int)$lot['live_shipped_heads']);
            $lot['mortality_pct']=(int)$lot['initial_heads']>0?round((int)$lot['dead_heads']/(int)$lot['initial_heads']*100,1):0.0;
            $lot['age_now']=$lot['age_days']===null?null:(int)$lot['age_days']+(int)(new DateTimeImmutable(substr($lot['received_at'],0,10)))->diff($today)->format('%a');
            $lot['average_live_kg']=$lot['live_weight_kg']!==null&&(int)$lot['initial_heads']>0?(float)$lot['live_weight_kg']/(int)$lot['initial_heads']:null;
            $lot['average_carcass_kg']=(int)$lot['slaughtered_heads']>0?(float)$lot['carcass_kg']/(int)$lot['slaughtered_heads']:null;
        }
        unset($lot);
        $records=$this->query("SELECT r.*,b.batch_number,b.site_id,f.name facility_name,COALESCE(p.flock_type,IF(b.purpose='laying','layer','broiler')) flock_type,u.name author
            FROM poultry_daily_records r JOIN livestock_batches b ON b.id=r.batch_id JOIN livestock_facilities f ON f.id=b.facility_id LEFT JOIN poultry_lot_profiles p ON p.batch_id=b.id LEFT JOIN users u ON u.id=r.created_by
            WHERE b.deleted_at IS NULL{$scope} ORDER BY r.recorded_on DESC,r.id DESC LIMIT 300",$params)->fetchAll();
        $slaughters=$this->query("SELECT c.*,d.expiry_date,d.dead_heads,i.name item_name,b.batch_number,b.site_id,b.current_heads,f.name facility_name,s.name site_name,COALESCE(p.flock_type,IF(b.purpose='laying','layer','broiler')) flock_type,su.name supplier_name,
                u.name author,v.name validator,w.id weight_stock_id,w.quantity_kg stock_kg,w.reserved_kg stock_reserved_kg,w.status stock_status,
                (SELECT COALESCE(SUM(t.quantity_pieces),0) FROM livestock_transfers t WHERE t.weight_stock_id=w.id AND t.status<>'cancelled' AND t.deleted_at IS NULL) shipped_pieces,
                (SELECT COALESCE(SUM(t.quantity_kg),0) FROM livestock_transfers t WHERE t.weight_stock_id=w.id AND t.status='draft' AND t.deleted_at IS NULL) draft_kg
            FROM livestock_conversions c JOIN poultry_slaughter_details d ON d.conversion_id=c.id JOIN butchery_items i ON i.id=d.item_id JOIN livestock_batches b ON b.id=c.batch_id
            JOIN livestock_facilities f ON f.id=b.facility_id JOIN sites s ON s.id=b.site_id LEFT JOIN poultry_lot_profiles p ON p.batch_id=b.id LEFT JOIN suppliers su ON su.id=p.supplier_id
            LEFT JOIN users u ON u.id=c.created_by LEFT JOIN users v ON v.id=c.validated_by LEFT JOIN livestock_weight_stocks w ON w.conversion_id=c.id
            WHERE 1=1{$scope} ORDER BY c.converted_at DESC,c.id DESC",$params)->fetchAll();
        foreach($slaughters as &$s){
            // Carcasses still to ship: kilos outside reservations and drafts, pieces outside every shipment not cancelled.
            $s['available_kg']=$s['weight_stock_id']?max(0,round((float)$s['stock_kg']-(float)$s['stock_reserved_kg']-(float)$s['draft_kg'],3)):0.0;
            $s['available_pieces']=$s['weight_stock_id']?max(0,(int)$s['input_heads']-(int)$s['shipped_pieces']):0;
        }
        unset($s);
        $params=[];$scope=Auth::siteClause('t.source_site_id',$params);
        $receipt="r.receipt_number,r.status receipt_status,r.received_weight_kg,r.received_pieces,r.received_gross_kg,r.received_tare_kg,r.tare_note receipt_tare_note,r.weight_gap_kg,r.weight_gap_pct,r.tolerance_pct,r.gap_reason,r.received_at receipt_received_at,
                author.name author,approver.name approver,dispatcher.name dispatcher,weigher.name weigher,gap_approver.name gap_approver";
        $people="LEFT JOIN butchery_receipts r ON r.livestock_transfer_id=t.id AND r.deleted_at IS NULL
            LEFT JOIN users author ON author.id=t.created_by LEFT JOIN users approver ON approver.id=t.approved_by LEFT JOIN users dispatcher ON dispatcher.id=t.dispatched_by
            LEFT JOIN users weigher ON weigher.id=r.created_by LEFT JOIN users gap_approver ON gap_approver.id=r.gap_approved_by";
        $carcasses=$this->query("SELECT t.*,'carcass' kind,b.batch_number,c.conversion_number,c.converted_at,d.expiry_date,f.name facility_name,COALESCE(p.flock_type,IF(b.purpose='laying','layer','broiler')) flock_type,i.name item_name,NULL live_gross_kg,NULL live_tare_kg,NULL live_tare_note,{$receipt}
            FROM livestock_transfers t JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN poultry_slaughter_details d ON d.conversion_id=w.conversion_id JOIN livestock_conversions c ON c.id=d.conversion_id JOIN butchery_items i ON i.id=d.item_id
            JOIN livestock_batches b ON b.id=t.batch_id JOIN livestock_facilities f ON f.id=b.facility_id LEFT JOIN poultry_lot_profiles p ON p.batch_id=b.id {$people}
            WHERE t.deleted_at IS NULL{$scope}",$params)->fetchAll();
        $live=$this->query("SELECT t.*,'live' kind,b.batch_number,NULL conversion_number,t.created_at converted_at,ls.expiry_date,f.name facility_name,COALESCE(p.flock_type,IF(b.purpose='laying','layer','broiler')) flock_type,i.name item_name,ls.gross_kg live_gross_kg,ls.tare_kg live_tare_kg,ls.tare_note live_tare_note,{$receipt}
            FROM livestock_transfers t JOIN poultry_live_shipments ls ON ls.transfer_id=t.id JOIN butchery_items i ON i.id=ls.item_id
            JOIN livestock_batches b ON b.id=t.batch_id JOIN livestock_facilities f ON f.id=b.facility_id LEFT JOIN poultry_lot_profiles p ON p.batch_id=b.id {$people}
            WHERE t.deleted_at IS NULL{$scope}",$params)->fetchAll();
        $shipments=array_merge($carcasses,$live);
        usort($shipments,static fn($a,$b)=>(int)$b['id']<=>(int)$a['id']);
        $params=[];$scope=Auth::siteClause('f.site_id',$params);
        $houses=$this->query("SELECT f.id,f.site_id,f.name,f.capacity_heads,s.name site_name,(SELECT COALESCE(SUM(x.current_heads),0) FROM livestock_batches x WHERE x.facility_id=f.id AND x.status='active' AND x.deleted_at IS NULL) occupied_heads
            FROM livestock_facilities f JOIN sites s ON s.id=f.site_id WHERE f.facility_type='poultry_house' AND f.status='active' AND f.deleted_at IS NULL AND s.code IN ('FARM-MUT','FARM-DIK'){$scope} ORDER BY s.name,f.name",$params)->fetchAll();
        $shelf=[];foreach($this->query("SELECT code,default_shelf_life_days FROM butchery_items WHERE code IN ('MP-VOLAILLE','MP-POULE','VOL-VIF')")->fetchAll() as $item)$shelf[$item['code']]=max(1,(int)$item['default_shelf_life_days']);
        return [
            'lots'=>$lots,'records'=>$records,'slaughters'=>$slaughters,'shipments'=>$shipments,'houses'=>$houses,
            'suppliers'=>$this->query("SELECT id,name FROM suppliers WHERE status='active' AND deleted_at IS NULL ORDER BY name")->fetchAll(),
            'shelfLife'=>['broiler'=>$shelf['MP-VOLAILLE']??4,'layer'=>$shelf['MP-POULE']??4,'live'=>$shelf['VOL-VIF']??30],
        ];
    }

    /** Reception of chicks or laying hens from a supplier: a new lot in a poultry house. */
    public function receivePoultry(array $data,array $user): int
    {
        $site=$this->integer($data['site_id']??'');$this->access($site,'livestock','create',true);
        $type=$this->flockType($data['flock_type']??'');$supplier=$this->integer($data['supplier_id']??'');$facility=$this->integer($data['facility_id']??'');$heads=$this->integer($data['heads']??'');
        $at=$this->timestamp($data['received_at']??'');
        $age=($data['age_days']??'')===''?null:$this->whole($data['age_days'],'Âge à l’arrivée (jours)');if($age!==null&&$age>3650)throw new RuntimeException('Âge à l’arrivée : 3 650 jours au maximum.');
        $weight=TransitWeighingService::parseOptional($data,'Poids vif');
        $reference=$this->optionalText($data['delivery_reference']??'',120,'Bon de livraison');$notes=$this->optionalText($data['notes']??'',500,'Observations');
        return $this->operation('poultry_receipt',$site,$data,compact('site','type','supplier','facility','heads','at','age','weight','reference','notes'),$user,function()use($site,$type,$supplier,$facility,$heads,$at,$age,$weight,$reference,$notes,$user){
            $this->house($facility,$site,$heads);
            if(!$this->row("SELECT id FROM suppliers WHERE id=? AND status='active' AND deleted_at IS NULL",[$supplier]))throw new RuntimeException('Choisissez un fournisseur actif.');
            $average=$weight===null?null:round($weight['net']/$heads,3);
            $batch=$this->createLot($site,$facility,$type,substr($at,0,10),$heads,$average,$user);
            $this->query("INSERT INTO poultry_lot_profiles(batch_id,flock_type,origin,supplier_id,receipt_number,delivery_reference,received_at,age_days,live_gross_kg,live_tare_kg,live_weight_kg,tare_note,notes,created_by) VALUES(?,?,'supplier',?,?,?,?,?,?,?,?,?,?,?)",
                [$batch,$type,$supplier,$this->number('REC-VOL-FRN'),$reference,$at,$age,$weight['gross']??null,$weight['tare']??null,$weight['net']??null,$weight['tare_note']??null,$notes,$user['id']]);
            $this->headMove($batch,'stocking',$heads,0,$heads,'poultry_lot_profiles',$batch,$user,$at);
            return $batch;
        });
    }

    /** Chicks hatched from the eggs of a laying lot become a broiler lot. */
    public function hatch(array $data,array $user): int
    {
        $mother=$this->integer($data['mother_batch_id']??'');$facility=$this->integer($data['facility_id']??'');$heads=$this->integer($data['heads']??'');
        $at=$this->timestamp($data['hatched_at']??'');$notes=$this->optionalText($data['notes']??'',500,'Observations');
        $m=$this->lot($mother);$site=(int)$m['site_id'];$this->access($site,'livestock','create',true);
        return $this->operation('poultry_hatch',$site,$data,compact('mother','facility','heads','at','notes'),$user,function()use($mother,$site,$facility,$heads,$at,$notes,$user){
            $m=$this->row("SELECT b.*,COALESCE(p.flock_type,IF(b.purpose='laying','layer','broiler')) flock_type FROM livestock_batches b LEFT JOIN poultry_lot_profiles p ON p.batch_id=b.id WHERE b.id=? FOR UPDATE",[$mother]);
            if($m['flock_type']!=='layer')throw new RuntimeException('L’éclosion se rattache à un lot de pondeuses.');
            if(substr($at,0,10)<$m['started_at'])throw new RuntimeException('L’éclosion ne peut pas précéder l’arrivée des pondeuses le '.date('d/m/Y',strtotime($m['started_at'])).'.');
            $this->house($facility,$site,$heads);
            $batch=$this->createLot($site,$facility,'broiler',substr($at,0,10),$heads,null,$user);
            $this->query("INSERT INTO poultry_lot_profiles(batch_id,flock_type,origin,mother_batch_id,received_at,age_days,notes,created_by) VALUES(?,'broiler','hatch',?,?,0,?,?)",[$batch,$mother,$at,$notes,$user['id']]);
            $this->headMove($batch,'birth',$heads,0,$heads,'poultry_lot_profiles',$batch,$user,$at);
            return $batch;
        });
    }

    /** Daily record of a lot: eggs collected (layers) and dead birds, which leave the lot at once. */
    public function record(array $data,array $user): int
    {
        $id=$this->integer($data['batch_id']??'');$day=$this->date($data['recorded_on']??'','Date');if($day>date('Y-m-d'))throw new RuntimeException('La saisie ne peut pas porter sur une date future.');
        $eggs=$this->whole($data['eggs_count']??'0','Œufs ramassés');$dead=$this->whole($data['mortality_heads']??'0','Oiseaux morts');$notes=$this->optionalText($data['notes']??'',255,'Observation');
        if($eggs+$dead===0)throw new RuntimeException('Saisissez des œufs ramassés ou des oiseaux morts.');
        $b=$this->lot($id);$site=(int)$b['site_id'];$this->access($site,'livestock','update',true);
        return $this->operation('poultry_daily',$site,$data,compact('id','day','eggs','dead','notes'),$user,function()use($id,$day,$eggs,$dead,$notes,$user){
            $b=$this->row("SELECT b.*,COALESCE(p.flock_type,IF(b.purpose='laying','layer','broiler')) flock_type FROM livestock_batches b LEFT JOIN poultry_lot_profiles p ON p.batch_id=b.id WHERE b.id=? FOR UPDATE",[$id]);
            if($b['status']!=='active')throw new RuntimeException('Ce lot ne contient plus d’oiseaux.');
            if($day<$b['started_at'])throw new RuntimeException('La saisie ne peut pas précéder l’arrivée du lot le '.date('d/m/Y',strtotime($b['started_at'])).'.');
            if($eggs>0&&$b['flock_type']!=='layer')throw new RuntimeException('Les œufs se saisissent sur un lot de pondeuses.');
            $free=$this->freeHeads($b);
            if($dead>$free)throw new RuntimeException('Mortalité supérieure aux '.$free.' oiseau(x) disponible(s) après les abattages et envois en attente.');
            $this->query('INSERT INTO poultry_daily_records(batch_id,recorded_on,eggs_count,mortality_heads,notes,created_by) VALUES(?,?,?,?,?,?)',[$id,$day,$eggs,$dead,$notes,$user['id']]);
            $record=(int)$this->db->lastInsertId();
            if($dead>0)$this->leave($b,'mortality',$dead,'poultry_daily_records',$record,$user,$day.' '.date('H:i:s'));
            return $record;
        });
    }

    /** Farm slaughter: carcasses weighed and counted; birds found dead at the slaughter leave the lot too. */
    public function slaughter(array $data,array $user): int
    {
        $id=$this->integer($data['batch_id']??'');$heads=$this->integer($data['heads']??'');$dead=$this->whole($data['dead_heads']??'0','Oiseaux morts à l’abattage');
        $weight=TransitWeighingService::parse($data,'Poids des carcasses');
        $at=$this->timestamp($data['slaughtered_at']??'');$expiry=$this->date($data['expiry_date']??'','DLC');
        if($expiry<date('Y-m-d')||$expiry<substr($at,0,10))throw new RuntimeException('La DLC doit couvrir la date d’abattage et ne pas être dépassée.');
        $b=$this->lot($id);$site=(int)$b['site_id'];$this->access($site,'livestock','create',true);
        return $this->operation('poultry_slaughter',$site,$data,compact('id','heads','dead','weight','at','expiry'),$user,function()use($id,$heads,$dead,$weight,$at,$expiry,$user){
            $b=$this->row("SELECT b.*,COALESCE(p.flock_type,IF(b.purpose='laying','layer','broiler')) flock_type FROM livestock_batches b LEFT JOIN poultry_lot_profiles p ON p.batch_id=b.id WHERE b.id=? FOR UPDATE",[$id]);
            if($b['status']!=='active')throw new RuntimeException('Ce lot ne contient plus d’oiseaux.');
            if(substr($at,0,10)<$b['started_at'])throw new RuntimeException('L’abattage ne peut pas précéder l’arrivée du lot le '.date('d/m/Y',strtotime($b['started_at'])).'.');
            $free=$this->freeHeads($b);
            if($heads+$dead>$free)throw new RuntimeException('Effectif insuffisant : '.$free.' oiseau(x) disponible(s) après les abattages et envois en attente.');
            $code=self::ITEMS[$b['flock_type']];
            $item=$this->row("SELECT id,name FROM butchery_items WHERE code=? AND item_kind='raw_material' AND status='active' AND deleted_at IS NULL",[$code]);if(!$item)throw new RuntimeException('L’article '.($b['flock_type']==='layer'?'Carcasse de poule':'Carcasse de poulet').' doit être actif.');
            $this->query("INSERT INTO livestock_conversions(batch_id,conversion_number,conversion_type,input_heads,gross_weight_kg,tare_weight_kg,net_weight_kg,tare_note,converted_at,status,created_by) VALUES(?,?,'slaughter',?,?,?,?,?,?,'submitted',?)",
                [$id,$this->number($b['flock_type']==='layer'?'AB-POULE':'AB-POULET'),$heads,$weight['gross'],$weight['tare'],$weight['net'],$weight['tare_note'],$at,$user['id']]);
            $conversion=(int)$this->db->lastInsertId();
            $this->query('INSERT INTO poultry_slaughter_details(conversion_id,item_id,expiry_date,dead_heads) VALUES(?,?,?,?)',[$conversion,$item['id'],$expiry,$dead]);
            return $conversion;
        });
    }

    /** Validation by another user: birds leave the lot and the carcasses become a weighed stock at the farm. */
    public function validateSlaughter($id,array $user): int
    {
        $r=$this->row('SELECT c.*,b.site_id FROM livestock_conversions c JOIN poultry_slaughter_details d ON d.conversion_id=c.id JOIN livestock_batches b ON b.id=c.batch_id WHERE c.id=?',[$id]);
        if(!$r)throw new RuntimeException('Abattage introuvable.');$site=(int)$r['site_id'];$this->access($site,'livestock','validate',true);
        return $this->operation('poultry_slaughter_validate',$site,['operation_key'=>$this->validationKey('poultry_slaughter_validate',(int)$id)],['conversion'=>(int)$id],$user,function()use($id,$site,$user){
            $c=$this->row('SELECT c.*,d.expiry_date,d.dead_heads FROM livestock_conversions c JOIN poultry_slaughter_details d ON d.conversion_id=c.id WHERE c.id=? FOR UPDATE',[$id]);
            if($c['status']!=='submitted')throw new RuntimeException('Abattage non validable ou déjà validé.');
            if((int)$c['created_by']===(int)$user['id']&&!Auth::canSelfValidate($user['id'],'livestock',$site))throw new RuntimeException('Un autre utilisateur autorisé doit valider cet abattage.');
            if($c['expiry_date']<date('Y-m-d'))throw new RuntimeException('Les carcasses ont dépassé leur DLC.');
            $b=$this->row('SELECT * FROM livestock_batches WHERE id=? FOR UPDATE',[$c['batch_id']]);
            $heads=(int)$c['input_heads'];$dead=(int)$c['dead_heads'];
            if((int)$b['current_heads']<$heads+$dead)throw new RuntimeException('Effectif vivant insuffisant.');
            $b=$this->leave($b,'slaughter',$heads,'livestock_conversions',(int)$id,$user);
            if($dead>0)$this->leave($b,'mortality',$dead,'poultry_slaughter_details',(int)$id,$user);
            $this->query("INSERT INTO livestock_weight_stocks(site_id,batch_id,conversion_id,quantity_kg,reserved_kg,status) VALUES(?,?,?,?,0,'available')",[$site,$c['batch_id'],$id,$c['net_weight_kg']]);
            $stock=(int)$this->db->lastInsertId();
            $this->query("INSERT INTO livestock_weight_movements(stock_id,movement_type,quantity_kg,stock_before_kg,stock_after_kg,reserved_before_kg,reserved_after_kg,reference_type,reference_id,created_by) VALUES(?,'conversion_in',?,0,?,0,0,'livestock_conversions',?,?)",[$stock,$c['net_weight_kg'],$c['net_weight_kg'],$id,$user['id']]);
            $this->query("UPDATE livestock_conversions SET status='validated',validated_by=?,validated_at=CURRENT_TIMESTAMP WHERE id=?",[$user['id'],$id]);
            return $stock;
        });
    }

    public function cancelSlaughter($id,array $data,array $user): int
    {
        $r=$this->row('SELECT c.*,b.site_id FROM livestock_conversions c JOIN poultry_slaughter_details d ON d.conversion_id=c.id JOIN livestock_batches b ON b.id=c.batch_id WHERE c.id=?',[$id]);if(!$r)throw new RuntimeException('Abattage introuvable.');
        $site=(int)$r['site_id'];$this->access($site,'livestock','delete',true);$reason=$this->text($data['reason']??'',500,'Motif');
        return $this->operation('poultry_slaughter_cancel',$site,$data,['conversion'=>(int)$id,'reason'=>$reason],$user,function()use($id){
            $c=$this->row('SELECT status FROM livestock_conversions WHERE id=? FOR UPDATE',[$id]);
            if($c['status']!=='submitted')throw new RuntimeException('Seul un abattage en attente peut être annulé ici.');
            $this->query("UPDATE livestock_conversions SET status='cancelled' WHERE id=?",[$id]);
            return (int)$id;
        });
    }

    /** Carcass shipment: kilos and pieces; the last pieces of a stock take its remaining kilos. */
    public function shipment(array $data,array $user): int
    {
        $id=$this->integer($data['weight_stock_id']??'');$qty=$this->amount($data['quantity_kg']??'');$pieces=$this->pieces($data['quantity_pieces']??'');
        $truck=$this->text($data['truck_plate']??'',40,'Immatriculation');$driver=$this->text($data['driver_name']??'',150,'Chauffeur');
        $stock=$this->row('SELECT * FROM livestock_weight_stocks WHERE id=?',[$id]);if(!$stock)throw new RuntimeException('Stock pesé introuvable.');$site=(int)$stock['site_id'];$this->access($site,'livestock','create',true);
        return $this->operation('poultry_shipment',$site,$data,compact('id','qty','pieces','truck','driver'),$user,function()use($id,$qty,$pieces,$truck,$driver,$site,$user){
            $s=$this->row("SELECT w.*,d.expiry_date,c.input_heads FROM livestock_weight_stocks w JOIN poultry_slaughter_details d ON d.conversion_id=w.conversion_id JOIN livestock_conversions c ON c.id=d.conversion_id WHERE w.id=? FOR UPDATE",[$id]);
            if(!$s||!in_array($s['status'],['available','reserved'],true)||$s['expiry_date']<date('Y-m-d'))throw new RuntimeException('Stock de carcasses indisponible ou DLC dépassée.');
            $draft=(float)$this->query("SELECT COALESCE(SUM(quantity_kg),0) FROM livestock_transfers WHERE weight_stock_id=? AND status='draft' AND deleted_at IS NULL",[$id])->fetchColumn();
            $freeKg=round((float)$s['quantity_kg']-(float)$s['reserved_kg']-$draft,3);
            $freePieces=(int)$s['input_heads']-(int)$this->query("SELECT COALESCE(SUM(quantity_pieces),0) FROM livestock_transfers WHERE weight_stock_id=? AND status<>'cancelled' AND deleted_at IS NULL",[$id])->fetchColumn();
            if($qty>$freeKg)throw new RuntimeException('Poids insuffisant : '.number_format($freeKg,3,',',' ').' kg disponibles après les envois en préparation.');
            if($pieces>$freePieces)throw new RuntimeException('Carcasses insuffisantes : '.$freePieces.' pièce(s) disponible(s) après les envois en préparation.');
            if(($pieces===$freePieces)!==($qty===$freeKg))throw new RuntimeException($pieces===$freePieces?'Les '.$freePieces.' dernières carcasses emportent tout le poids restant : '.number_format($freeKg,3,',',' ').' kg.':'Tout le poids restant part avec toutes les carcasses restantes : '.$freePieces.' pièce(s).');
            $dest=(int)$this->query("SELECT id FROM sites WHERE code='BOUCH' AND status='active' AND deleted_at IS NULL")->fetchColumn();if(!$dest)throw new RuntimeException('Site Boucherie introuvable.');
            $this->query("INSERT INTO livestock_transfers(batch_id,weight_stock_id,source_site_id,destination_site_id,transfer_number,source_unit,quantity_kg,quantity_pieces,status,truck_plate,driver_name,created_by) VALUES(?,?,?,?,?,'kg',?,?,'draft',?,?,?)",[$s['batch_id'],$id,$site,$dest,$this->number(self::PREFIXES['transfer']['poultry']),$qty,$pieces,$truck,$driver,$user['id']]);
            $transfer=(int)$this->db->lastInsertId();require_once __DIR__.'/DocumentService.php';(new DocumentService($this->db))->register('BTR',$site,'livestock_transfers',$transfer,'draft',date('Y-m-d H:i:s'),$user);return $transfer;
        });
    }

    /** Live broilers sent to the butchery, counted and weighed at the farm (gross minus cage tare). */
    public function liveShipment(array $data,array $user): int
    {
        $id=$this->integer($data['batch_id']??'');$heads=$this->integer($data['heads']??'');$weight=TransitWeighingService::parse($data,'Poids vif');
        $truck=$this->text($data['truck_plate']??'',40,'Immatriculation');$driver=$this->text($data['driver_name']??'',150,'Chauffeur');
        $b=$this->lot($id);$site=(int)$b['site_id'];$this->access($site,'livestock','create',true);
        return $this->operation('poultry_live_shipment',$site,$data,compact('id','heads','weight','truck','driver'),$user,function()use($id,$heads,$weight,$truck,$driver,$site,$user){
            $b=$this->row("SELECT b.*,COALESCE(p.flock_type,IF(b.purpose='laying','layer','broiler')) flock_type FROM livestock_batches b LEFT JOIN poultry_lot_profiles p ON p.batch_id=b.id WHERE b.id=? FOR UPDATE",[$id]);
            if($b['status']!=='active')throw new RuntimeException('Ce lot ne contient plus d’oiseaux.');
            if($b['flock_type']!=='broiler')throw new RuntimeException('Seuls les poulets de chair sont envoyés vivants. Les poules sont abattues à la ferme.');
            $free=$this->freeHeads($b);
            if($heads>$free)throw new RuntimeException('Effectif insuffisant : '.$free.' poulet(s) disponible(s) après les abattages et envois en attente.');
            $item=$this->row("SELECT id,default_shelf_life_days FROM butchery_items WHERE code='VOL-VIF' AND status='active' AND deleted_at IS NULL");if(!$item)throw new RuntimeException('L’article Poulet vif doit être actif.');
            $dest=(int)$this->query("SELECT id FROM sites WHERE code='BOUCH' AND status='active' AND deleted_at IS NULL")->fetchColumn();if(!$dest)throw new RuntimeException('Site Boucherie introuvable.');
            $this->query("INSERT INTO livestock_transfers(batch_id,weight_stock_id,source_site_id,destination_site_id,transfer_number,source_unit,quantity_heads,quantity_kg,status,truck_plate,driver_name,created_by) VALUES(?,NULL,?,?,?,'head',?,?,'draft',?,?,?)",[$id,$site,$dest,$this->number(self::PREFIXES['transfer']['poultry_live']),$heads,$weight['net'],$truck,$driver,$user['id']]);
            $transfer=(int)$this->db->lastInsertId();
            $this->query('INSERT INTO poultry_live_shipments(transfer_id,item_id,gross_kg,tare_kg,tare_note,expiry_date) VALUES(?,?,?,?,?,?)',[$transfer,$item['id'],$weight['gross'],$weight['tare'],$weight['tare_note'],date('Y-m-d',strtotime('+'.max(1,(int)$item['default_shelf_life_days']).' days'))]);
            require_once __DIR__.'/DocumentService.php';(new DocumentService($this->db))->register('BTR',$site,'livestock_transfers',$transfer,'draft',date('Y-m-d H:i:s'),$user);
            return $transfer;
        });
    }

    /** Approval and dispatch; live birds leave the lot when the truck leaves. */
    public function advanceShipment($id,string $action,array $user): void
    {
        $t=$this->row('SELECT t.*,ls.transfer_id live FROM livestock_transfers t LEFT JOIN poultry_live_shipments ls ON ls.transfer_id=t.id WHERE t.id=? AND t.deleted_at IS NULL',[$id]);
        if(!$t)throw new RuntimeException('Envoi introuvable.');
        if(!$t['live']){parent::advanceShipment($id,$action,$user);return;}
        $site=(int)$t['source_site_id'];$this->access($site,'livestock',$action==='approve'?'validate':'update',true);
        require_once dirname(__DIR__).'/models/Livestock.php';
        if($action==='approve'){(new Livestock())->approveTransfer($id,$user);return;}
        $this->operation('poultry_live_dispatch',$site,['operation_key'=>$this->validationKey('poultry_live_dispatch',(int)$id)],['transfer'=>(int)$id],$user,function()use($id,$user){
            $t=$this->row('SELECT * FROM livestock_transfers WHERE id=? FOR UPDATE',[$id]);
            if($t['status']!=='approved')throw new RuntimeException('Envoi non expédiable ou déjà expédié.');
            $b=$this->row('SELECT * FROM livestock_batches WHERE id=? FOR UPDATE',[$t['batch_id']]);
            if((int)$b['current_heads']<(int)$t['quantity_heads'])throw new RuntimeException('Effectif insuffisant pour cet envoi.');
            $this->leave($b,'transfer_out',(int)$t['quantity_heads'],'livestock_transfers',(int)$id,$user);
            $this->query("UPDATE livestock_transfers SET status='in_transit',dispatched_by=?,dispatched_at=CURRENT_TIMESTAMP WHERE id=?",[$user['id'],$id]);
            return (int)$id;
        });
    }

    /** Slaughter of live birds at the butchery: pieces leave the live lot, carcasses enter the cold room. */
    public function butcherySlaughter(array $data,array $user): int
    {
        $site=$this->butcher('create');$lot=$this->integer($data['live_lot_id']??'');$heads=$this->integer($data['heads']??'');$dead=$this->whole($data['dead_heads']??'0','Poulets morts');
        $weight=TransitWeighingService::parse($data,'Poids des carcasses');
        $at=$this->timestamp($data['slaughtered_at']??'');$expiry=$this->date($data['expiry_date']??'','DLC');
        if($expiry<date('Y-m-d')||$expiry<substr($at,0,10))throw new RuntimeException('La DLC doit couvrir la date d’abattage et ne pas être dépassée.');
        return $this->operation('poultry_butchery_slaughter',$site,$data,compact('lot','heads','dead','weight','at','expiry'),$user,function()use($site,$lot,$heads,$dead,$weight,$at,$expiry,$user){
            $l=$this->row('SELECT l.*,i.is_live,r.livestock_transfer_id FROM butchery_finished_lots l JOIN butchery_items i ON i.id=l.item_id LEFT JOIN butchery_receipts r ON r.id=l.receipt_id WHERE l.id=? FOR UPDATE',[$lot]);
            if(!$l||(int)$l['site_id']!==$site||!(int)$l['is_live']||!in_array($l['status'],['available','reserved'],true)||$l['expiry_date']<date('Y-m-d'))throw new RuntimeException('Lot de poulets vivants indisponible.');
            if(substr($at,0,10)<$l['production_date'])throw new RuntimeException('L’abattage ne peut pas précéder l’arrivée des poulets le '.date('d/m/Y',strtotime($l['production_date'])).'.');
            $free=$this->coldPieces($lot);
            if($heads+$dead>$free)throw new RuntimeException('Poulets insuffisants hors comptoir : '.$free.' disponible(s). Remettez d’abord les poulets du comptoir en réserve.');
            $item=$this->row("SELECT id FROM butchery_items WHERE code='MP-VOLAILLE' AND item_kind='raw_material' AND status='active' AND deleted_at IS NULL");if(!$item)throw new RuntimeException('L’article Carcasse de poulet doit être actif.');
            $liveKg=self::share(round($this->coldAvailable($lot),3),$free,$heads+$dead);
            $kgAfter=round((float)$l['quantity_kg']-$liveKg,3);$piecesAfter=(int)$l['quantity_pieces']-$heads-$dead;
            $batch=$l['livestock_transfer_id']?(int)$this->query('SELECT batch_id FROM livestock_transfers WHERE id=?',[$l['livestock_transfer_id']])->fetchColumn():null;
            $this->query("INSERT INTO butchery_raw_lots(site_id,item_id,livestock_batch_id,lot_number,production_date,expiry_date,quantity_kg,reserved_kg,quantity_pieces,reserved_pieces,unit_cost,status) VALUES(?,?,?,?,?,?,?,0,?,0,0,'available')",[$site,$item['id'],$batch,$this->number('CARC-VOL-BOU'),substr($at,0,10),$expiry,$weight['net'],$heads]);
            $raw=(int)$this->db->lastInsertId();
            $this->query('INSERT INTO poultry_butchery_slaughters(site_id,live_lot_id,raw_lot_id,slaughter_number,heads,dead_heads,live_kg,gross_weight_kg,tare_weight_kg,net_weight_kg,tare_note,slaughtered_at,expiry_date,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$site,$lot,$raw,$this->number('AB-VOL-BOU'),$heads,$dead,$liveKg,$weight['gross'],$weight['tare'],$weight['net'],$weight['tare_note'],$at,$expiry,$user['id']]);
            $slaughter=(int)$this->db->lastInsertId();
            $this->query("UPDATE butchery_finished_lots SET quantity_kg=?,quantity_pieces=?,status=IF(?=0,'depleted',status) WHERE id=?",[$kgAfter,$piecesAfter,$piecesAfter,$lot]);
            $this->finishedMove($lot,$liveKg,(float)$l['quantity_kg'],$kgAfter,'slaughter_out','poultry_butchery_slaughters',$slaughter,$user,$heads+$dead);
            $this->rawMove($raw,$weight['net'],0,$weight['net'],'slaughter_in','poultry_butchery_slaughters',$slaughter,$user,$heads);
            return $slaughter;
        });
    }

    /** Live lots and recent slaughters for the butchery transformation page. */
    public function liveDirectory(): array
    {
        $site=$this->butcher('read');
        return ['poultrySlaughters'=>$this->query('SELECT s.*,l.lot_number live_lot_number,r.lot_number raw_lot_number,u.name author FROM poultry_butchery_slaughters s JOIN butchery_finished_lots l ON l.id=s.live_lot_id JOIN butchery_raw_lots r ON r.id=s.raw_lot_id LEFT JOIN users u ON u.id=s.created_by WHERE s.site_id=? ORDER BY s.slaughtered_at DESC,s.id DESC LIMIT 50',[$site])->fetchAll(),
            'carcassShelfLife'=>max(1,(int)$this->query("SELECT default_shelf_life_days FROM butchery_items WHERE code='MP-VOLAILLE'")->fetchColumn())];
    }

    private function whole($value,string $label): int
    {
        if(!is_scalar($value)||!preg_match('/^[0-9]{1,8}$/D',(string)$value))throw new RuntimeException($label.' : saisissez un nombre entier, positif ou nul.');
        return (int)$value;
    }

    private function flockType($value): string
    {
        if(!in_array($value,['broiler','layer'],true))throw new RuntimeException('Choisissez poulets de chair ou pondeuses.');
        return $value;
    }

    private function lot(int $id): array
    {
        $b=$this->row("SELECT b.* FROM livestock_batches b JOIN livestock_species sp ON sp.id=b.species_id WHERE b.id=? AND sp.code='POULET' AND b.deleted_at IS NULL",[$id]);
        if(!$b)throw new RuntimeException('Lot de volailles introuvable.');
        return $b;
    }

    /** Poultry house of the farm, locked, with room for the new birds. */
    private function house(int $facility,int $site,int $heads): void
    {
        $f=$this->row("SELECT * FROM livestock_facilities WHERE id=? AND site_id=? AND facility_type='poultry_house' AND status='active' AND deleted_at IS NULL FOR UPDATE",[$facility,$site]);
        if(!$f)throw new RuntimeException('Choisissez un poulailler actif de cette ferme.');
        $occupied=(int)$this->query("SELECT COALESCE(SUM(current_heads),0) FROM livestock_batches WHERE facility_id=? AND status='active' AND deleted_at IS NULL",[$facility])->fetchColumn();
        if($f['capacity_heads']!==null&&$occupied+$heads>(int)$f['capacity_heads'])throw new RuntimeException('Capacité du poulailler dépassée : '.max(0,(int)$f['capacity_heads']-$occupied).' place(s) libre(s) sur '.(int)$f['capacity_heads'].'.');
    }

    private function createLot(int $site,int $facility,string $type,string $day,int $heads,?float $average,array $user): int
    {
        $species=$this->row("SELECT id FROM livestock_species WHERE code='POULET' AND status='active' AND deleted_at IS NULL");if(!$species)throw new RuntimeException('L’espèce Poulet doit être active.');
        $this->query("INSERT INTO livestock_batches(site_id,species_id,facility_id,batch_number,purpose,started_at,initial_heads,current_heads,initial_average_weight_kg,current_average_weight_kg,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,'active',?)",
            [$site,$species['id'],$facility,$this->number($type==='layer'?'VOL-PONTE':'VOL-CHAIR'),$type==='layer'?'laying':'meat',$day,$heads,$heads,$average,$average,$user['id']]);
        return (int)$this->db->lastInsertId();
    }

    /** Living birds not yet committed to a pending slaughter or live shipment. */
    private function freeHeads(array $b): int
    {
        $pending=(int)$this->query("SELECT COALESCE(SUM(c.input_heads+d.dead_heads),0) FROM livestock_conversions c JOIN poultry_slaughter_details d ON d.conversion_id=c.id WHERE c.batch_id=? AND c.status='submitted'",[$b['id']])->fetchColumn();
        $shipping=(int)$this->query("SELECT COALESCE(SUM(t.quantity_heads),0) FROM livestock_transfers t JOIN poultry_live_shipments ls ON ls.transfer_id=t.id WHERE t.batch_id=? AND t.status IN ('draft','approved') AND t.deleted_at IS NULL",[$b['id']])->fetchColumn();
        return max(0,(int)$b['current_heads']-$pending-$shipping);
    }

    /** Birds leaving a locked lot; the lot ends with its last bird. */
    private function leave(array $b,string $type,int $heads,string $reference,int $referenceId,array $user,?string $at=null): array
    {
        $before=(int)$b['current_heads'];$after=$before-$heads;
        $this->query("UPDATE livestock_batches SET current_heads=?,status=IF(?>0,status,'completed') WHERE id=?",[$after,$after,$b['id']]);
        $this->headMove((int)$b['id'],$type,$heads,$before,$after,$reference,$referenceId,$user,$at);
        $b['current_heads']=$after;return $b;
    }

    /** Validation buttons carry no form key: one key per user and record keeps double clicks idempotent. */
    private function validationKey(string $action,int $id): string
    {
        return substr(hash('sha256',$action.':'.$id.':'.(Auth::user()['id']??0)),0,32);
    }
}
