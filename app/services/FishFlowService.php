<?php
require_once __DIR__.'/PorcineFlowService.php';

/** Fish farming by pond parcel: stocking, weighed harvests, then shipments shared with the porcine flow. */
class FishFlowService extends PorcineFlowService
{
    public function fishAvailable(): bool
    {
        return $this->available()&&$this->hasTable('livestock_pond_parcels')&&$this->weighingReady();
    }

    public function directory(): array
    {
        $params=[];$scope=Auth::siteClause('f.site_id',$params);
        $ponds=$this->query("SELECT f.id,f.site_id,f.code,f.name,f.area_m2,f.status,s.name site_name FROM livestock_facilities f JOIN sites s ON s.id=f.site_id WHERE f.facility_type='pond' AND f.deleted_at IS NULL{$scope} ORDER BY s.name,f.name",$params)->fetchAll();
        $parcels=$this->query("SELECT p.* FROM livestock_pond_parcels p JOIN livestock_facilities f ON f.id=p.facility_id WHERE f.facility_type='pond' AND f.deleted_at IS NULL{$scope} ORDER BY p.facility_id,LENGTH(p.code),p.code",$params)->fetchAll();
        $params=[];$scope=Auth::siteClause('b.site_id',$params);
        $lots=$this->query("SELECT b.id,b.site_id,b.batch_number,b.species_id,b.parcel_id,b.facility_id,b.started_at,b.initial_heads,b.current_heads,b.status,sp.name species_name,
                p.code parcel_code,p.name parcel_name,COALESCE(p.area_m2,f.area_m2) area_m2,f.name pond_name,s.name site_name,
                (SELECT COALESCE(SUM(fs.total_weight_kg),0) FROM fish_stockings fs WHERE fs.batch_id=b.id) stocked_kg,
                (SELECT COALESCE(SUM(fc.heads_caught),0) FROM fish_catches fc WHERE fc.batch_id=b.id AND fc.status='validated') harvested_heads,
                (SELECT COALESCE(SUM(fc.net_weight_kg),0) FROM fish_catches fc WHERE fc.batch_id=b.id AND fc.status='validated') harvested_kg,
                (SELECT COALESCE(SUM(fc.heads_caught),0) FROM fish_catches fc WHERE fc.batch_id=b.id AND fc.status='submitted') pending_heads,
                (SELECT COUNT(*) FROM fish_catches fc WHERE fc.batch_id=b.id AND fc.status='submitted') pending_harvests,
                (SELECT COUNT(*) FROM fish_catches fc WHERE fc.batch_id=b.id AND fc.status='submitted' AND fc.final_harvest=1) pending_final,
                (SELECT COALESCE(SUM(m.quantity_heads),0) FROM livestock_head_movements m WHERE m.batch_id=b.id AND m.movement_type='mortality') dead_heads,
                (SELECT MAX(fc.caught_at) FROM fish_catches fc WHERE fc.batch_id=b.id AND fc.status='validated') last_harvest_at
            FROM livestock_batches b JOIN livestock_species sp ON sp.id=b.species_id JOIN livestock_facilities f ON f.id=b.facility_id JOIN sites s ON s.id=b.site_id
            LEFT JOIN livestock_pond_parcels p ON p.id=b.parcel_id
            WHERE sp.category='fish' AND b.parcel_id IS NOT NULL AND b.deleted_at IS NULL AND b.status IN ('active','completed'){$scope}
            ORDER BY b.started_at DESC,b.id DESC",$params)->fetchAll();
        $today=new DateTimeImmutable('today');
        foreach($lots as &$lot){
            $lot['age_days']=(int)(new DateTimeImmutable($lot['started_at']))->diff($today)->days;
            $lot['remaining_heads']=max(0,(int)$lot['current_heads']-(int)$lot['pending_heads']);
            // Survivors are only known when fish are counted at harvest.
            $lot['survival_pct']=(int)$lot['initial_heads']>0?round((int)$lot['harvested_heads']/(int)$lot['initial_heads']*100,1):null;
            $lot['yield_kg_m2']=(float)$lot['area_m2']>0?round((float)$lot['harvested_kg']/(float)$lot['area_m2'],3):null;
        }
        unset($lot);
        $harvests=$this->query("SELECT fc.*,c.conversion_number,c.created_by author_id,c.tare_note conversion_tare_note,b.batch_number,b.site_id,b.status batch_status,p.code parcel_code,p.name parcel_name,f.name pond_name,s.name site_name,sp.name species_name,
                u.name author,v.name validator,w.id weight_stock_id,w.quantity_kg stock_kg,w.reserved_kg stock_reserved_kg,w.status stock_status
            FROM fish_catches fc JOIN livestock_conversions c ON c.id=fc.conversion_id JOIN livestock_batches b ON b.id=fc.batch_id JOIN livestock_species sp ON sp.id=b.species_id
            JOIN livestock_facilities f ON f.id=b.facility_id JOIN sites s ON s.id=b.site_id LEFT JOIN livestock_pond_parcels p ON p.id=b.parcel_id
            LEFT JOIN users u ON u.id=fc.created_by LEFT JOIN users v ON v.id=fc.validated_by LEFT JOIN livestock_weight_stocks w ON w.conversion_id=c.id
            WHERE fc.item_id IS NOT NULL{$scope} ORDER BY fc.caught_at DESC,fc.id DESC",$params)->fetchAll();
        $params=[];$scope=Auth::siteClause('t.source_site_id',$params);
        $shipments=$this->query("SELECT t.*,b.batch_number,fc.catch_number,fc.expiry_date,p.code parcel_code,f.name pond_name,sp.name species_name,
                r.receipt_number,r.status receipt_status,r.received_weight_kg,r.received_gross_kg,r.received_tare_kg,r.tare_note receipt_tare_note,r.weight_gap_kg,r.weight_gap_pct,r.tolerance_pct,r.gap_reason,r.received_at receipt_received_at,r.gap_approved_at,
                author.name author,approver.name approver,dispatcher.name dispatcher,weigher.name weigher,gap_approver.name gap_approver
            FROM livestock_transfers t JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN fish_catches fc ON fc.conversion_id=w.conversion_id
            JOIN livestock_batches b ON b.id=t.batch_id JOIN livestock_species sp ON sp.id=b.species_id JOIN livestock_facilities f ON f.id=b.facility_id LEFT JOIN livestock_pond_parcels p ON p.id=b.parcel_id
            LEFT JOIN butchery_receipts r ON r.livestock_transfer_id=t.id AND r.deleted_at IS NULL
            LEFT JOIN users author ON author.id=t.created_by LEFT JOIN users approver ON approver.id=t.approved_by LEFT JOIN users dispatcher ON dispatcher.id=t.dispatched_by
            LEFT JOIN users weigher ON weigher.id=r.created_by LEFT JOIN users gap_approver ON gap_approver.id=r.gap_approved_by
            WHERE t.deleted_at IS NULL AND fc.item_id IS NOT NULL{$scope} ORDER BY t.id DESC",$params)->fetchAll();
        $item=$this->row("SELECT default_shelf_life_days FROM butchery_items WHERE code='MP-POISSON' AND status='active' AND deleted_at IS NULL");
        return [
            'ponds'=>$ponds,'parcels'=>$parcels,'lots'=>$lots,'harvests'=>$harvests,'shipments'=>$shipments,
            'species'=>$this->query("SELECT id,name FROM livestock_species WHERE category='fish' AND status='active' AND deleted_at IS NULL ORDER BY name")->fetchAll(),
            'shelfLifeDays'=>max(1,(int)($item['default_shelf_life_days']??3)),
        ];
    }

    public function createParcel(array $data,array $user): int
    {
        $pond=$this->integer($data['facility_id']??'');
        $f=$this->row("SELECT * FROM livestock_facilities WHERE id=? AND facility_type='pond' AND deleted_at IS NULL",[$pond]);if(!$f)throw new RuntimeException('Étang introuvable.');
        $site=(int)$f['site_id'];$this->access($site,'livestock','administer',true);
        [$name,$area]=$this->parcelInput($data);
        return $this->operation('fish_parcel',$site,$data,compact('pond','name','area'),$user,function()use($pond,$name,$area,$user){
            $f=$this->row('SELECT * FROM livestock_facilities WHERE id=? FOR UPDATE',[$pond]);
            $this->assertArea($f,$area,null);
            $next=1;foreach($this->query('SELECT code FROM livestock_pond_parcels WHERE facility_id=?',[$pond])->fetchAll(PDO::FETCH_COLUMN) as $code)if(preg_match('/^P(\d+)$/D',$code,$m))$next=max($next,(int)$m[1]+1);
            $this->query('INSERT INTO livestock_pond_parcels(facility_id,code,name,area_m2,created_by) VALUES(?,?,?,?,?)',[$pond,'P'.$next,$name===''?'Parcelle '.$next:$name,$area,$user['id']]);
            return (int)$this->db->lastInsertId();
        });
    }

    public function updateParcel($id,array $data,array $user): int
    {
        $p=$this->row('SELECT p.*,f.site_id FROM livestock_pond_parcels p JOIN livestock_facilities f ON f.id=p.facility_id WHERE p.id=?',[$this->integer($id)]);if(!$p)throw new RuntimeException('Parcelle introuvable.');
        $site=(int)$p['site_id'];$this->access($site,'livestock','administer',true);
        [$name,$area]=$this->parcelInput($data);
        $status=$data['status']??'active';if(!in_array($status,['active','inactive'],true))throw new RuntimeException('Statut invalide.');
        if($name==='')throw new RuntimeException('Nom de la parcelle : saisissez de 1 à 120 caractères.');
        return $this->operation('fish_parcel_update',$site,$data,['parcel'=>(int)$p['id'],'name'=>$name,'area'=>$area,'status'=>$status],$user,function()use($p,$name,$area,$status){
            $f=$this->row('SELECT * FROM livestock_facilities WHERE id=? FOR UPDATE',[$p['facility_id']]);
            $this->assertArea($f,$area,(int)$p['id']);
            if($status==='inactive'&&$this->row("SELECT id FROM livestock_batches WHERE parcel_id=? AND status='active' AND deleted_at IS NULL",[$p['id']]))throw new RuntimeException('Cette parcelle contient un lot en cours : terminez sa pêche avant de la désactiver.');
            $this->query('UPDATE livestock_pond_parcels SET name=?,area_m2=?,status=? WHERE id=?',[$name,$area,$status,$p['id']]);
            return (int)$p['id'];
        });
    }

    private function parcelInput(array $data): array
    {
        $name=$data['name']??'';if(!is_string($name)||mb_strlen(trim($name))>120)throw new RuntimeException('Nom de la parcelle : 120 caractères au maximum.');
        $area=$data['area_m2']??'';$area=$area===''||$area===null?null:$this->amount($area,false,3);
        return [trim($name),$area];
    }

    /** Active parcels never cover more than the surface declared for their pond. */
    private function assertArea(array $pond,?float $area,?int $except): void
    {
        if($area===null||$pond['area_m2']===null)return;
        $used=(float)$this->query("SELECT COALESCE(SUM(area_m2),0) FROM livestock_pond_parcels WHERE facility_id=? AND status='active'".($except?' AND id<>'.(int)$except:''),[$pond['id']])->fetchColumn();
        if(round($used+$area,3)>(float)$pond['area_m2'])throw new RuntimeException('La surface des parcelles dépasserait celle de l’étang ('.number_format((float)$pond['area_m2'],0,',',' ').' m², dont '.number_format($used,0,',',' ').' m² déjà attribués).');
    }

    /** Stocking: fingerlings put into a free parcel start a new lot. */
    public function stock(array $data,array $user): int
    {
        $parcel=$this->integer($data['parcel_id']??'');$species=$this->integer($data['species_id']??'');$heads=$this->integer($data['heads']??'');
        $weight=($data['stocked_weight_kg']??'')===''?null:$this->amount($data['stocked_weight_kg']);
        $on=$this->date($data['stocked_on']??'','Date de mise en charge');if($on>date('Y-m-d'))throw new RuntimeException('La mise en charge ne peut pas être dans le futur.');
        $p=$this->row('SELECT p.*,f.site_id FROM livestock_pond_parcels p JOIN livestock_facilities f ON f.id=p.facility_id WHERE p.id=? AND f.deleted_at IS NULL',[$parcel]);if(!$p)throw new RuntimeException('Parcelle introuvable.');
        $site=(int)$p['site_id'];$this->access($site,'livestock','create',true);
        return $this->operation('fish_stocking',$site,$data,compact('parcel','species','heads','weight','on'),$user,function()use($parcel,$species,$heads,$weight,$on,$site,$user){
            $p=$this->row('SELECT * FROM livestock_pond_parcels WHERE id=? FOR UPDATE',[$parcel]);
            if($p['status']!=='active')throw new RuntimeException('Cette parcelle est désactivée.');
            if($this->row("SELECT id FROM livestock_batches WHERE parcel_id=? AND status='active' AND deleted_at IS NULL",[$parcel]))throw new RuntimeException('Cette parcelle contient déjà un lot en cours. Terminez sa pêche avant une nouvelle mise en charge.');
            if(!$this->row("SELECT id FROM livestock_species WHERE id=? AND category='fish' AND status='active' AND deleted_at IS NULL",[$species]))throw new RuntimeException('Choisissez une espèce de poisson active.');
            $average=$weight===null?null:round($weight/$heads,3);
            $this->query("INSERT INTO livestock_batches(site_id,species_id,facility_id,parcel_id,batch_number,purpose,started_at,initial_heads,current_heads,initial_average_weight_kg,current_average_weight_kg,status,created_by) VALUES(?,?,?,?,?,'meat',?,?,?,?,?,'active',?)",
                [$site,$species,$p['facility_id'],$parcel,$this->number('POIS'),$on,$heads,$heads,$average,$average,$user['id']]);
            $batch=(int)$this->db->lastInsertId();
            $this->query('INSERT INTO fish_stockings(batch_id,stocked_heads,total_weight_kg,stocked_at,created_by) VALUES(?,?,?,?,?)',[$batch,$heads,$weight,$on.' 00:00:00',$user['id']]);
            $stocking=(int)$this->db->lastInsertId();
            $this->headMove($batch,'stocking',$heads,0,$heads,'fish_stockings',$stocking,$user);
            return $batch;
        });
    }

    /** Harvest weighed at the farm; a final harvest empties the parcel and closes the lot. */
    public function harvest(array $data,array $user): int
    {
        $id=$this->integer($data['batch_id']??'');$heads=$this->integer($data['heads']??'');
        $weight=TransitWeighingService::parse($data,'Poids pêché');
        $at=$this->timestamp($data['caught_at']??'');$expiry=$this->date($data['expiry_date']??'','DLC');$final=($data['final_harvest']??'')==='1';
        if($expiry<date('Y-m-d')||$expiry<substr($at,0,10))throw new RuntimeException('La DLC doit couvrir la date de pêche et ne pas être dépassée.');
        $b=$this->row('SELECT * FROM livestock_batches WHERE id=? AND deleted_at IS NULL',[$id]);if(!$b)throw new RuntimeException('Lot introuvable.');
        $site=(int)$b['site_id'];$this->access($site,'livestock','create',true);
        return $this->operation('fish_harvest',$site,$data,compact('id','heads','weight','at','expiry','final'),$user,function()use($id,$heads,$weight,$at,$expiry,$final,$user){
            $b=$this->row("SELECT b.*,COALESCE(p.area_m2,f.area_m2) area_m2 FROM livestock_batches b JOIN livestock_species sp ON sp.id=b.species_id JOIN livestock_facilities f ON f.id=b.facility_id LEFT JOIN livestock_pond_parcels p ON p.id=b.parcel_id WHERE b.id=? AND sp.category='fish' AND b.deleted_at IS NULL FOR UPDATE",[$id]);
            if(!$b||$b['status']!=='active'||!$b['parcel_id'])throw new RuntimeException('Lot de poissons en cours introuvable.');
            if(substr($at,0,10)<$b['started_at'])throw new RuntimeException('La pêche ne peut pas précéder la mise en charge du '.date('d/m/Y',strtotime($b['started_at'])).'.');
            $pending=$this->row("SELECT COALESCE(SUM(heads_caught),0) heads,COALESCE(SUM(final_harvest),0) finals FROM fish_catches WHERE batch_id=? AND status='submitted'",[$id]);
            if((int)$pending['finals']>0)throw new RuntimeException('Une pêche finale de ce lot attend déjà sa validation.');
            if($final&&(int)$pending['heads']>0)throw new RuntimeException('Faites d’abord valider les pêches en attente de ce lot.');
            $remaining=(int)$b['current_heads']-(int)$pending['heads'];
            if($heads>$remaining)throw new RuntimeException('Plus de poissons pêchés que de poissons restants dans ce lot ('.$remaining.').');
            $item=$this->row("SELECT id FROM butchery_items WHERE code='MP-POISSON' AND item_kind='raw_material' AND status='active' AND deleted_at IS NULL");if(!$item)throw new RuntimeException('L’article Poisson frais doit être actif.');
            $this->query("INSERT INTO livestock_conversions(batch_id,conversion_number,conversion_type,input_heads,gross_weight_kg,tare_weight_kg,net_weight_kg,tare_note,converted_at,status,created_by) VALUES(?,?,'fish_catch',?,?,?,?,?,?,'submitted',?)",
                [$id,$this->number('PECHE'),$heads,$weight['gross'],$weight['tare'],$weight['net'],$weight['tare_note'],$at,$user['id']]);
            $conversion=(int)$this->db->lastInsertId();
            $this->query("INSERT INTO fish_catches(batch_id,conversion_id,item_id,catch_number,heads_caught,gross_weight_kg,tare_weight_kg,tare_note,net_weight_kg,yield_kg_m2,status,caught_at,expiry_date,final_harvest,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,'submitted',?,?,?,?)",
                [$id,$conversion,$item['id'],$this->number('PEC'),$heads,$weight['gross'],$weight['tare'],$weight['tare_note'],$weight['net'],(float)$b['area_m2']>0?round($weight['net']/(float)$b['area_m2'],4):0,$at,$expiry,$final?1:0,$user['id']]);
            return $conversion;
        });
    }

    public function validateHarvest($id,array $data,array $user): int
    {
        $c=$this->row('SELECT c.*,b.site_id FROM livestock_conversions c JOIN fish_catches fc ON fc.conversion_id=c.id JOIN livestock_batches b ON b.id=c.batch_id WHERE c.id=? AND fc.item_id IS NOT NULL',[$this->integer($id)]);
        if(!$c)throw new RuntimeException('Pêche introuvable.');$site=(int)$c['site_id'];$this->access($site,'livestock','validate',true);
        return $this->operation('fish_harvest_validate',$site,$data,['conversion'=>(int)$c['id']],$user,function()use($c,$site,$user){
            $c=$this->row('SELECT * FROM livestock_conversions WHERE id=? FOR UPDATE',[$c['id']]);
            if($c['status']!=='submitted')throw new RuntimeException('Cette pêche est déjà validée ou annulée.');
            if((int)$c['created_by']===(int)$user['id']&&!Auth::canSelfValidate($user['id'],'livestock',$site))throw new RuntimeException('Un autre utilisateur autorisé doit valider cette pêche.');
            $fc=$this->row('SELECT * FROM fish_catches WHERE conversion_id=? FOR UPDATE',[$c['id']]);
            if($fc['expiry_date']<date('Y-m-d'))throw new RuntimeException('Le poisson pêché a dépassé sa DLC.');
            $b=$this->row('SELECT * FROM livestock_batches WHERE id=? FOR UPDATE',[$c['batch_id']]);
            if($b['status']!=='active'||(int)$b['current_heads']<(int)$c['input_heads'])throw new RuntimeException('Effectif du lot insuffisant pour cette pêche.');
            $before=(int)$b['current_heads'];$after=$before-(int)$c['input_heads'];
            $this->headMove((int)$b['id'],'transfer_out',(int)$c['input_heads'],$before,$after,'livestock_conversions',(int)$c['id'],$user);
            // Fish not caught when the parcel is emptied are counted as dead.
            if((int)$fc['final_harvest']===1&&$after>0){$this->headMove((int)$b['id'],'mortality',$after,$after,0,'fish_catches',(int)$fc['id'],$user);$after=0;}
            $this->query("UPDATE livestock_batches SET current_heads=?,status=? WHERE id=?",[$after,$after>0?'active':'completed',$b['id']]);
            $this->query("INSERT INTO livestock_weight_stocks(site_id,batch_id,conversion_id,quantity_kg,reserved_kg,status) VALUES(?,?,?,?,0,'available')",[$site,$b['id'],$c['id'],$c['net_weight_kg']]);
            $stock=(int)$this->db->lastInsertId();
            $this->query("INSERT INTO livestock_weight_movements(stock_id,movement_type,quantity_kg,stock_before_kg,stock_after_kg,reserved_before_kg,reserved_after_kg,reference_type,reference_id,created_by) VALUES(?,'conversion_in',?,0,?,0,0,'livestock_conversions',?,?)",[$stock,$c['net_weight_kg'],$c['net_weight_kg'],$c['id'],$user['id']]);
            $this->query("UPDATE livestock_conversions SET status='validated',validated_by=?,validated_at=CURRENT_TIMESTAMP WHERE id=?",[$user['id'],$c['id']]);
            $this->query("UPDATE fish_catches SET status='validated',validated_by=?,validated_at=CURRENT_TIMESTAMP WHERE id=?",[$user['id'],$fc['id']]);
            return $stock;
        });
    }

    public function cancelHarvest($id,array $data,array $user): int
    {
        $c=$this->row('SELECT c.*,b.site_id FROM livestock_conversions c JOIN fish_catches fc ON fc.conversion_id=c.id JOIN livestock_batches b ON b.id=c.batch_id WHERE c.id=? AND fc.item_id IS NOT NULL',[$this->integer($id)]);
        if(!$c)throw new RuntimeException('Pêche introuvable.');$site=(int)$c['site_id'];$this->access($site,'livestock','delete',true);$reason=$this->text($data['reason']??'',500,'Motif');
        return $this->operation('fish_harvest_cancel',$site,$data,['conversion'=>(int)$c['id'],'reason'=>$reason],$user,function()use($c){
            $row=$this->row('SELECT status FROM livestock_conversions WHERE id=? FOR UPDATE',[$c['id']]);
            if($row['status']!=='submitted')throw new RuntimeException('Seule une pêche en attente peut être annulée ici.');
            $this->query("UPDATE livestock_conversions SET status='cancelled' WHERE id=?",[$c['id']]);
            $this->query("UPDATE fish_catches SET status='cancelled' WHERE conversion_id=?",[$c['id']]);
            return (int)$c['id'];
        });
    }
}
