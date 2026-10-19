<?php
require_once __DIR__.'/PigService.php';

/** Farm slaughter, cold storage and counter operations share existing stock ledgers. */
class PorcineFlowService extends Model
{
    public function __construct(PDO $db = null) { $this->db=$db ?: Database::getInstance()->connection(); }
    public function available(): bool
    {
        return (int)$this->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('pig_slaughter_details','butchery_counters','butchery_counter_stocks','butchery_output_allocations','livestock_butchery_operations','butchery_counter_movements')")->fetchColumn()===6;
    }
    private function row($sql,array $params=[]) { return $this->query($sql,$params)->fetch(); }
    private function number(string $prefix): string { return $prefix.'-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(5))); }
    private function integer($value): int
    {
        if(!is_scalar($value)||!preg_match('/^[0-9]{1,8}$/D',(string)$value)||(int)$value<1)throw new RuntimeException('Saisissez un nombre entier positif.');
        return (int)$value;
    }
    private function amount($value,bool $zero=false,int $decimals=3): float
    {
        if(!is_scalar($value)||!preg_match('/^\d{1,9}(?:\.\d{1,'.$decimals.'})?$/D',(string)$value))throw new RuntimeException('Saisissez une quantité valide, avec au plus '.$decimals.' décimales.');
        $n=round((float)$value,$decimals);
        if($zero?$n<0:$n<=0)throw new RuntimeException('La quantité doit être '.($zero?'positive ou nulle.':'supérieure à zéro.'));
        return $n;
    }
    private function text($value,int $max,string $label): string
    {
        if(!is_string($value)||trim($value)===''||mb_strlen(trim($value))>$max)throw new RuntimeException($label.' : saisissez de 1 à '.$max.' caractères.');
        return trim($value);
    }
    private function day($value): string { return PigService::date($value)->format('Y-m-d'); }
    private function timestamp($value): string
    {
        if(!is_string($value))throw new RuntimeException('Date et heure invalides.');
        $value=str_replace('T',' ',$value);if(strlen($value)===16)$value.=':00';
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value);
        if(!$date||$date->format('Y-m-d H:i:s')!==$value||$date>new DateTimeImmutable('+5 minutes'))throw new RuntimeException('Renseignez une date valide, sans date future.');
        return $value;
    }
    private function access(int $site,string $component,string $permission,bool $farm=false): void
    {
        Auth::requireSiteAccess($site);Auth::requirePermission($component,$permission,$site);
        $selected=Auth::currentSiteId();
        if($permission!=='read'&&$selected!==null&&(int)$selected!==$site)throw new RuntimeException('Sélectionnez le site de cette opération, ou Tous les sites.');
        $code=$this->query("SELECT code FROM sites WHERE id=? AND status='active' AND deleted_at IS NULL",[$site])->fetchColumn();
        if($farm?!in_array($code,['FARM-MUT','FARM-DIK'],true):$code!=='BOUCH')throw new RuntimeException('Site incompatible avec cette opération.');
    }
    private function butcher(string $permission): int
    {
        $site=(int)$this->query("SELECT id FROM sites WHERE code='BOUCH' AND status='active' AND deleted_at IS NULL")->fetchColumn();
        $this->access($site,'butchery',$permission);return $site;
    }
    private function validateAuthor(array $row,array $user): void
    {
        if((int)$row['created_by']===(int)$user['id']&&!Auth::canSelfValidate($user['id'],'butchery',(int)$row['site_id']))throw new RuntimeException('Un autre utilisateur autorisé doit valider cette opération.');
    }
    private function operation(string $action,int $site,array $data,array $payload,array $user,callable $write): int
    {
        $key=$data['operation_key']??'';
        if(!is_string($key)||!preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Rechargez le formulaire avant de recommencer.');
        $hash=hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE));
        $this->db->beginTransaction();
        try{
            $this->query('INSERT INTO livestock_butchery_operations(operation_key,action,payload_hash,site_id,created_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE operation_key=VALUES(operation_key)',[$key,$action,$hash,$site,$user['id']]);
            $op=$this->row('SELECT * FROM livestock_butchery_operations WHERE operation_key=? FOR UPDATE',[$key]);
            if($op['action']!==$action||$op['payload_hash']!==$hash||(int)$op['created_by']!==(int)$user['id']||(int)$op['site_id']!==$site)throw new RuntimeException('Cette soumission a déjà été utilisée. Actualisez la page.');
            if($op['result_id']!==null){$this->db->commit();return (int)$op['result_id'];}
            $result=(int)$write((int)$op['id']);
            $this->query('UPDATE livestock_butchery_operations SET result_id=? WHERE id=?',[$result,$op['id']]);
            $this->logActivity($action,$action==='farm_slaughter'||$action==='farm_shipment'?'livestock':'butchery','livestock_butchery_operations',$op['id'],'Parcours ferme → boucherie.',null,$payload+['result_id'=>$result],$user,$site);
            $this->db->commit();return $result;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function farmDirectory(): array
    {
        $params=[];$scope=Auth::siteClause('b.site_id',$params);
        $slaughters=$this->query("SELECT c.*,b.site_id,b.batch_number,b.current_heads,p.sex,d.expiry_date,i.name item_name,w.id weight_stock_id,w.quantity_kg,w.reserved_kg,u.name author,v.name validator,s.name site_name
          FROM livestock_conversions c JOIN pig_slaughter_details d ON d.conversion_id=c.id JOIN livestock_batches b ON b.id=c.batch_id JOIN livestock_pig_profiles p ON p.batch_id=b.id JOIN sites s ON s.id=b.site_id JOIN butchery_items i ON i.id=d.item_id LEFT JOIN livestock_weight_stocks w ON w.conversion_id=c.id LEFT JOIN users u ON u.id=c.created_by LEFT JOIN users v ON v.id=c.validated_by WHERE 1=1{$scope} ORDER BY c.id DESC",$params)->fetchAll();
        $params=[];$scope=Auth::siteClause('t.source_site_id',$params);
        $shipments=$this->query("SELECT t.*,b.batch_number,d.expiry_date,c.conversion_number FROM livestock_transfers t JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN pig_slaughter_details d ON d.conversion_id=w.conversion_id JOIN livestock_conversions c ON c.id=d.conversion_id JOIN livestock_batches b ON b.id=t.batch_id WHERE t.deleted_at IS NULL{$scope} ORDER BY t.id DESC",$params)->fetchAll();
        return ['slaughters'=>$slaughters,'shipments'=>$shipments];
    }
    public function shipmentBtr($id): ?array
    {
        if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1)return null;
        $params=['id'=>(int)$id];$scope=Auth::siteClause('t.source_site_id',$params);
        $row=$this->row("SELECT t.*,b.batch_number,c.conversion_number,c.converted_at,d.expiry_date,
            source.name source_name,source.code source_code,destination.name destination_name,destination.code destination_code,
            author.name author,approver.name approver,dispatcher.name dispatcher
            FROM livestock_transfers t JOIN livestock_batches b ON b.id=t.batch_id
            JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN pig_slaughter_details d ON d.conversion_id=w.conversion_id
            JOIN livestock_conversions c ON c.id=d.conversion_id JOIN sites source ON source.id=t.source_site_id
            JOIN sites destination ON destination.id=t.destination_site_id LEFT JOIN users author ON author.id=t.created_by
            LEFT JOIN users approver ON approver.id=t.approved_by LEFT JOIN users dispatcher ON dispatcher.id=t.dispatched_by
            WHERE t.id=:id AND t.source_unit='kg' AND t.deleted_at IS NULL{$scope}",$params);
        if(!$row)return null;
        $this->access((int)$row['source_site_id'],'livestock','read',true);
        require_once __DIR__.'/DocumentService.php';
        $document=(new DocumentService($this->db))->findByEntity('BTR','livestock_transfers',(int)$row['id']);
        $row['official_document_number']=$document['document_number']??null;
        return $row;
    }

    public function slaughter(array $data,array $user): int
    {
        $id=$this->integer($data['batch_id']??'');$heads=$this->integer($data['heads']??'');$weight=$this->amount($data['weight_kg']??'');
        $at=$this->timestamp($data['slaughtered_at']??'');$expiry=$this->day($data['expiry_date']??'');
        if($expiry<date('Y-m-d')||$expiry<substr($at,0,10))throw new RuntimeException('La DLC doit couvrir la date d’abattage et ne pas être dépassée.');
        $b=$this->row('SELECT * FROM livestock_batches WHERE id=? AND deleted_at IS NULL',[$id]);if(!$b)throw new RuntimeException('Lot introuvable.');
        $site=(int)$b['site_id'];$this->access($site,'livestock','create',true);
        return $this->operation('farm_slaughter',$site,$data,compact('id','heads','weight','at','expiry'),$user,function()use($id,$heads,$weight,$at,$expiry,$user){
            $b=$this->row("SELECT * FROM livestock_batches WHERE id=? AND deleted_at IS NULL FOR UPDATE",[$id]);
            (new PigService($this->db))->assertExitAllowed($id,'slaughter');
            $committed=(int)$this->query("SELECT COALESCE(SUM(input_heads),0) FROM livestock_conversions WHERE batch_id=? AND status='submitted'",[$id])->fetchColumn();
            if($b['status']!=='active'||$heads>(int)$b['current_heads']-$committed)throw new RuntimeException('Effectif insuffisant après prise en compte des abattages en attente.');
            $profile=$this->row('SELECT born_on FROM livestock_pig_profiles WHERE batch_id=?',[$id]);
            if(substr($at,0,10)<$profile['born_on']||PigService::expectedGroup($profile['born_on'],new DateTimeImmutable(substr($at,0,10)))!=='C')throw new RuntimeException('Ces porcs n’appartenaient pas encore au groupe C à cette date.');
            $item=$this->row("SELECT id FROM butchery_items WHERE code='MP-PORC' AND item_kind='raw_material' AND status='active' AND deleted_at IS NULL");if(!$item)throw new RuntimeException('L’article Carcasse de porc doit être actif.');
            $this->query("INSERT INTO livestock_conversions(batch_id,conversion_number,conversion_type,input_heads,gross_weight_kg,tare_weight_kg,net_weight_kg,converted_at,status,created_by) VALUES(?,?,'slaughter',?,?,0,?,?,'submitted',?)",[$id,$this->number('AB-PORC'),$heads,$weight,$weight,$at,$user['id']]);
            $conversion=(int)$this->db->lastInsertId();$this->query('INSERT INTO pig_slaughter_details(conversion_id,item_id,expiry_date) VALUES(?,?,?)',[$conversion,$item['id'],$expiry]);return $conversion;
        });
    }
    public function validateSlaughter($id,array $user): int
    {
        require_once dirname(__DIR__).'/models/Livestock.php';
        $r=$this->row('SELECT c.*,b.site_id,d.expiry_date FROM livestock_conversions c JOIN pig_slaughter_details d ON d.conversion_id=c.id JOIN livestock_batches b ON b.id=c.batch_id WHERE c.id=?',[$id]);
        if(!$r)throw new RuntimeException('Abattage introuvable.');$this->access((int)$r['site_id'],'livestock','validate',true);
        if($r['expiry_date']<date('Y-m-d'))throw new RuntimeException('Les carcasses ont dépassé leur DLC.');
        return (new Livestock())->validateConversion($id,$user);
    }
    public function shipment(array $data,array $user): int
    {
        $id=$this->integer($data['weight_stock_id']??'');$qty=$this->amount($data['quantity_kg']??'');
        $truck=$this->text($data['truck_plate']??'',40,'Immatriculation');$driver=$this->text($data['driver_name']??'',150,'Chauffeur');
        $stock=$this->row('SELECT * FROM livestock_weight_stocks WHERE id=?',[$id]);if(!$stock)throw new RuntimeException('Stock carcasse introuvable.');$site=(int)$stock['site_id'];$this->access($site,'livestock','create',true);
        return $this->operation('farm_shipment',$site,$data,compact('id','qty','truck','driver'),$user,function()use($id,$qty,$truck,$driver,$site,$user){
            $s=$this->row("SELECT w.*,d.expiry_date FROM livestock_weight_stocks w JOIN pig_slaughter_details d ON d.conversion_id=w.conversion_id WHERE w.id=? FOR UPDATE",[$id]);
            if(!$s||!in_array($s['status'],['available','reserved'],true)||$s['expiry_date']<date('Y-m-d'))throw new RuntimeException('Carcasses indisponibles ou DLC dépassée.');
            $draft=(float)$this->query("SELECT COALESCE(SUM(quantity_kg),0) FROM livestock_transfers WHERE weight_stock_id=? AND status='draft' AND deleted_at IS NULL",[$id])->fetchColumn();
            if($qty>round((float)$s['quantity_kg']-(float)$s['reserved_kg']-$draft,3))throw new RuntimeException('Quantité insuffisante après les envois en préparation.');
            $dest=(int)$this->query("SELECT id FROM sites WHERE code='BOUCH' AND status='active' AND deleted_at IS NULL")->fetchColumn();if(!$dest)throw new RuntimeException('Site Boucherie introuvable.');
            $this->query("INSERT INTO livestock_transfers(batch_id,weight_stock_id,source_site_id,destination_site_id,transfer_number,source_unit,quantity_kg,status,truck_plate,driver_name,created_by) VALUES(?,?,?,?,?,'kg',?,'draft',?,?,?)",[$s['batch_id'],$id,$site,$dest,$this->number('BTR-PORC'),$qty,$truck,$driver,$user['id']]);
            $transfer=(int)$this->db->lastInsertId();require_once __DIR__.'/DocumentService.php';(new DocumentService($this->db))->register('BTR',$site,'livestock_transfers',$transfer,'draft',date('Y-m-d H:i:s'),$user);return $transfer;
        });
    }
    public function advanceShipment($id,string $action,array $user): void
    {
        require_once dirname(__DIR__).'/models/Livestock.php';
        $t=$this->row('SELECT t.*,d.expiry_date FROM livestock_transfers t JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN pig_slaughter_details d ON d.conversion_id=w.conversion_id WHERE t.id=? AND t.deleted_at IS NULL',[$id]);
        if(!$t)throw new RuntimeException('Envoi de carcasses introuvable.');$this->access((int)$t['source_site_id'],'livestock',$action==='approve'?'validate':'update',true);
        if($t['expiry_date']<date('Y-m-d'))throw new RuntimeException('Les carcasses ont dépassé leur DLC.');
        $m=new Livestock();if($action==='approve')$m->approveTransfer($id,$user);else $m->dispatchTransfer($id,$user);
    }
    public function butcheryDirectory(): array
    {
        $site=$this->butcher('read');
        $finished=$this->query("SELECT l.*,i.name item_name,COALESCE(cs.quantity_kg,0) counter_kg,l.quantity_kg-COALESCE(cs.quantity_kg,0) cold_kg,l.quantity_kg-l.reserved_kg-COALESCE(cs.quantity_kg,0) cold_available_kg FROM butchery_finished_lots l JOIN butchery_items i ON i.id=l.item_id LEFT JOIN (SELECT finished_lot_id,SUM(quantity_kg) quantity_kg FROM butchery_counter_stocks GROUP BY finished_lot_id) cs ON cs.finished_lot_id=l.id WHERE l.site_id=? AND l.deleted_at IS NULL ORDER BY l.expiry_date,l.id",[$site])->fetchAll();
        return ['site'=>$site,'finished'=>$finished,
          'raw'=>$this->query("SELECT l.*,i.name item_name,i.item_kind,i.unit item_unit,i.status item_status,i.deleted_at item_deleted,b.batch_number FROM butchery_raw_lots l JOIN butchery_items i ON i.id=l.item_id LEFT JOIN livestock_batches b ON b.id=l.livestock_batch_id WHERE l.site_id=? AND l.deleted_at IS NULL ORDER BY l.expiry_date,l.production_date,l.id",[$site])->fetchAll(),
          'incoming'=>$this->query("SELECT t.*,b.batch_number,s.name source_name,c.conversion_number,c.converted_at,d.expiry_date FROM livestock_transfers t JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN pig_slaughter_details d ON d.conversion_id=w.conversion_id JOIN livestock_conversions c ON c.id=d.conversion_id JOIN livestock_batches b ON b.id=t.batch_id JOIN sites s ON s.id=t.source_site_id WHERE t.destination_site_id=? AND t.status='in_transit' AND t.deleted_at IS NULL AND NOT EXISTS(SELECT 1 FROM butchery_receipts r WHERE r.livestock_transfer_id=t.id) ORDER BY t.id DESC",[$site])->fetchAll(),
          'pending'=>$this->query("SELECT r.*,t.transfer_number,t.quantity_kg,b.batch_number,s.name source_name,d.expiry_date FROM butchery_receipts r JOIN livestock_transfers t ON t.id=r.livestock_transfer_id JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN pig_slaughter_details d ON d.conversion_id=w.conversion_id JOIN livestock_batches b ON b.id=t.batch_id JOIN sites s ON s.id=t.source_site_id WHERE r.site_id=? AND r.status='sanitary_pending' AND r.deleted_at IS NULL ORDER BY r.id DESC",[$site])->fetchAll(),
          'counters'=>$this->query('SELECT * FROM butchery_counters WHERE site_id=? ORDER BY id',[$site])->fetchAll(),
          'counterStocks'=>$this->query("SELECT cs.*,c.name counter_name,c.status counter_status,l.site_id,l.lot_number,l.expiry_date,l.status lot_status,i.name item_name FROM butchery_counter_stocks cs JOIN butchery_counters c ON c.id=cs.counter_id JOIN butchery_finished_lots l ON l.id=cs.finished_lot_id JOIN butchery_items i ON i.id=l.item_id WHERE c.site_id=? AND cs.quantity_kg>0 ORDER BY c.id,l.expiry_date,cs.id",[$site])->fetchAll(),
          'items'=>$this->query("SELECT * FROM butchery_items WHERE item_kind IN ('finished_product','co_product') AND unit='kg' AND status='active' AND deleted_at IS NULL ORDER BY name")->fetchAll(),
          'orders'=>$this->query("SELECT o.*,r.name recipe_name,u.name author FROM butchery_orders o JOIN butchery_recipe_versions v ON v.id=o.recipe_version_id JOIN butchery_recipes r ON r.id=v.recipe_id LEFT JOIN users u ON u.id=o.created_by WHERE o.site_id=? AND o.deleted_at IS NULL ORDER BY o.id DESC",[$site])->fetchAll(),
          'allocations'=>$this->query("SELECT a.*,c.name counter_name FROM butchery_output_allocations a JOIN butchery_counters c ON c.id=a.counter_id WHERE c.site_id=?",[$site])->fetchAll(),
          'sales'=>$this->query("SELECT s.*,c.name counter_name,l.lot_number,i.name item_name FROM butchery_sales s LEFT JOIN butchery_counter_stocks cs ON cs.id=s.counter_stock_id LEFT JOIN butchery_counters c ON c.id=cs.counter_id LEFT JOIN butchery_finished_lots l ON l.id=s.finished_lot_id LEFT JOIN butchery_items i ON i.id=l.item_id WHERE s.site_id=? AND s.status='validated' ORDER BY s.id DESC LIMIT 100",[$site])->fetchAll()];
    }
    public function arrival(array $data,array $user): int
    {
        $site=$this->butcher('create');$id=$this->integer($data['transfer_id']??'');
        return $this->operation('carcass_arrival',$site,$data,['transfer'=>$id],$user,function()use($site,$id,$user){
            $t=$this->row("SELECT t.*,d.expiry_date FROM livestock_transfers t JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN pig_slaughter_details d ON d.conversion_id=w.conversion_id WHERE t.id=? FOR UPDATE",[$id]);
            if(!$t||(int)$t['destination_site_id']!==$site||$t['status']!=='in_transit'||$t['expiry_date']<date('Y-m-d'))throw new RuntimeException('Envoi non réceptionnable ou DLC dépassée.');
            if($this->row('SELECT id FROM butchery_receipts WHERE livestock_transfer_id=?',[$id]))throw new RuntimeException('L’arrivée de cet envoi est déjà enregistrée.');
            $this->query("INSERT INTO butchery_receipts(site_id,source_type,livestock_transfer_id,receipt_number,source_reference,received_unit,received_weight_kg,unit_cost,status,received_at,created_by) VALUES(?,'internal_btr',?,?,?,'kg',?,0,'sanitary_pending',CURRENT_TIMESTAMP,?)",[$site,$id,$this->number('REC-PORC'),$t['transfer_number'],$t['quantity_kg'],$user['id']]);return (int)$this->db->lastInsertId();
        });
    }
    public function receive(array $data,array $user): int
    {
        $site=$this->butcher('validate');$id=$this->integer($data['receipt_id']??'');$temp=$data['temperature_c']??'';
        if($temp!==''&&(!is_scalar($temp)||!is_numeric($temp)||!is_finite((float)$temp)||(float)$temp< -100||(float)$temp>100))throw new RuntimeException('Température invalide.');
        if(($data['visual_conform']??'')!=='1'||($data['documents_conform']??'')!=='1')throw new RuntimeException('Confirmez les contrôles visuel et documentaire pour accepter les carcasses.');
        return $this->operation('carcass_receipt',$site,$data,['receipt'=>$id,'temperature'=>$temp],$user,function()use($site,$id,$temp,$user){
            $r=$this->row('SELECT * FROM butchery_receipts WHERE id=? FOR UPDATE',[$id]);
            if(!$r||(int)$r['site_id']!==$site||$r['status']!=='sanitary_pending')throw new RuntimeException('Réception non contrôlable ou déjà traitée.');$this->validateAuthor($r,$user);
            $t=$this->row("SELECT t.*,d.item_id,d.expiry_date,c.converted_at FROM livestock_transfers t JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN pig_slaughter_details d ON d.conversion_id=w.conversion_id JOIN livestock_conversions c ON c.id=d.conversion_id WHERE t.id=? FOR UPDATE",[$r['livestock_transfer_id']]);
            if(!$t||(int)$t['destination_site_id']!==$site||$t['status']!=='in_transit'||$t['expiry_date']<date('Y-m-d'))throw new RuntimeException('Envoi non réceptionnable ou DLC dépassée.');
            $this->query("INSERT INTO butchery_sanitary_controls(receipt_id,temperature_c,visual_status,document_status,decision,controlled_at,controlled_by) VALUES(?,?,'conform','conform','accept',CURRENT_TIMESTAMP,?)",[$id,$temp===''?null:$temp,$user['id']]);
            $this->query("INSERT INTO butchery_raw_lots(site_id,item_id,receipt_id,livestock_batch_id,lot_number,production_date,expiry_date,quantity_kg,reserved_kg,unit_cost,status) VALUES(?,?,?,?,?,?,?,?,0,0,'available')",[$site,$t['item_id'],$id,$t['batch_id'],$this->number('CARC'),substr($t['converted_at'],0,10),$t['expiry_date'],$t['quantity_kg']]);
            $raw=(int)$this->db->lastInsertId();$this->rawMove($raw,$t['quantity_kg'],0,$t['quantity_kg'],'receipt_in','butchery_receipts',$id,$user);
            $this->query("UPDATE butchery_receipts SET status='accepted',validated_by=? WHERE id=?",[$user['id'],$id]);
            $this->query("UPDATE livestock_transfers SET status='received',received_at=CURRENT_TIMESTAMP WHERE id=?",[$t['id']]);return $id;
        });
    }
    private function rawMove($id,$qty,$before,$after,$type,$reference,$refId,$user): void
    {
        $this->query('INSERT INTO butchery_raw_movements(raw_lot_id,movement_type,quantity_kg,stock_before_kg,stock_after_kg,reference_type,reference_id,created_by) VALUES(?,?,?,?,?,?,?,?)',[$id,$type,$qty,$before,$after,$reference,$refId,$user['id']]);
    }
    private function finishedMove($id,$qty,$before,$after,$type,$reference,$refId,$user): void
    {
        $this->query('INSERT INTO butchery_finished_movements(finished_lot_id,movement_type,quantity_kg,stock_before_kg,stock_after_kg,reference_type,reference_id,created_by) VALUES(?,?,?,?,?,?,?,?)',[$id,$type,$qty,$before,$after,$reference,$refId,$user['id']]);
    }
    private function counter(int $id,int $site): array
    {
        $c=$this->row("SELECT * FROM butchery_counters WHERE id=? AND site_id=? AND status='active' FOR UPDATE",[$id,$site]);if(!$c)throw new RuntimeException('Choisissez un comptoir actif de cette boucherie.');return $c;
    }
    private function counterMove(int $stock,int $op,string $type,float $qty,float $before,float $after,array $user): void
    {
        $this->query('INSERT INTO butchery_counter_movements(counter_stock_id,operation_id,movement_type,quantity_kg,before_kg,after_kg,created_by) VALUES(?,?,?,?,?,?,?)',[$stock,$op,$type,$qty,$before,$after,$user['id']]);
    }
    private function supply(int $lot,int $counter,float $qty,int $op,array $user): int
    {
        $this->query('INSERT INTO butchery_counter_stocks(counter_id,finished_lot_id,quantity_kg) VALUES(?,?,0) ON DUPLICATE KEY UPDATE counter_id=VALUES(counter_id)',[$counter,$lot]);
        $stock=$this->row('SELECT * FROM butchery_counter_stocks WHERE counter_id=? AND finished_lot_id=? FOR UPDATE',[$counter,$lot]);
        $after=round((float)$stock['quantity_kg']+$qty,3);$this->query('UPDATE butchery_counter_stocks SET quantity_kg=? WHERE id=?',[$after,$stock['id']]);$this->counterMove((int)$stock['id'],$op,'supply',$qty,(float)$stock['quantity_kg'],$after,$user);return (int)$stock['id'];
    }
    public function transform(array $data,array $user): int
    {
        $site=$this->butcher('create');$sourceItem=isset($data['source_item_id'])?$this->integer($data['source_item_id']):0;
        $rawId=$sourceItem?0:$this->integer($data['raw_lot_id']??'');
        if($sourceItem&&!empty($data['raw_lot_id']))throw new RuntimeException('Choisissez le produit source ou un lot, pas les deux.');
        $lotMode=$sourceItem?($data['lot_mode']??'auto'):'legacy';$lotIds=[];
        if(!in_array($lotMode,['auto','manual','legacy'],true)||($sourceItem&&$lotMode==='legacy'))throw new RuntimeException('Mode de choix des lots invalide.');
        if($lotMode==='manual'){
            $selected=$data['source_lot_ids']??[];if(!is_array($selected)||!count($selected)||count($selected)>200)throw new RuntimeException('Choisissez au moins un lot pour le prélèvement manuel.');
            foreach($selected as$id)$lotIds[]=$this->integer($id);$lotIds=array_values(array_unique($lotIds));sort($lotIds,SORT_NUMERIC);
        }
        $qty=$this->amount($data['quantity_kg']??'');$day=$this->day($data['production_date']??'');if($day>date('Y-m-d'))throw new RuntimeException('La date de transformation ne peut pas être future.');
        $lines=$data['outputs']??null;if(!is_array($lines)||!count($lines)||count($lines)>30)throw new RuntimeException('Ajoutez de 1 à 30 produits obtenus.');
        $outputs=[];$total=0;
        foreach($lines as $line){
            if(!is_array($line))throw new RuntimeException('Ligne de produit invalide.');$item=$this->integer($line['item_id']??'');$counter=$this->amount($line['counter_kg']??'0',true);
            if(array_key_exists('quantity_kg',$line)){$obtained=$this->amount($line['quantity_kg']);if($counter>$obtained)throw new RuntimeException('La quantité au comptoir dépasse la quantité obtenue.');$cold=round($obtained-$counter,3);}else $cold=$this->amount($line['cold_kg']??'0',true);
            $expiry=$this->day($line['expiry_date']??'');if($cold+$counter<=0||$expiry<$day||$expiry<date('Y-m-d'))throw new RuntimeException('Chaque produit doit avoir une quantité positive et une DLC valide.');$outputs[]=compact('item','cold','counter','expiry');$total=round($total+$cold+$counter,3);
        }
        if($total>$qty)throw new RuntimeException('Le poids total des produits dépasse la quantité de carcasse consommée.');
        if($sourceItem&&round($qty-$total,3)>0&&($data['loss_confirmed']??'')!=='1')throw new RuntimeException('Confirmez l’écart de poids ou ajoutez les produits récupérés avant de continuer.');
        $counterId=(int)($data['counter_id']??0);if(array_sum(array_column($outputs,'counter'))>0&&$counterId<1)throw new RuntimeException('Choisissez le comptoir destinataire.');
        $payload=compact('rawId','qty','day','outputs','counterId');if($sourceItem)$payload+=compact('sourceItem','lotMode','lotIds')+['loss_confirmed'=>round($qty-$total,3)>0];
        return $this->operation('butchery_transform',$site,$data,$payload,$user,function()use($site,$rawId,$sourceItem,$lotMode,$lotIds,$qty,$day,$outputs,$total,$counterId,$user){
            if($sourceItem){
                $item=$this->row("SELECT id FROM butchery_items WHERE id=? AND item_kind='raw_material' AND unit='kg' AND status='active' AND deleted_at IS NULL",[$sourceItem]);if(!$item)throw new RuntimeException('Produit source indisponible.');
                $params=[$site,$sourceItem,date('Y-m-d'),$day];$restriction='';
                if($lotMode==='manual'){$restriction=' AND id IN ('.implode(',',array_fill(0,count($lotIds),'?')).')';$params=array_merge($params,$lotIds);}
                // Lock in the same ID order used by validation/cancellation, then allocate by earliest DLC.
                $sources=$this->query("SELECT * FROM butchery_raw_lots WHERE site_id=? AND item_id=? AND deleted_at IS NULL AND status IN ('available','reserved') AND expiry_date>=? AND production_date<=? AND quantity_kg>reserved_kg{$restriction} ORDER BY id FOR UPDATE",$params)->fetchAll();
                if($lotMode==='manual'&&count($sources)!==count($lotIds))throw new RuntimeException('Un lot sélectionné est indisponible ou ne correspond pas au produit source.');
                usort($sources,static fn($a,$b)=>strcmp($a['expiry_date'],$b['expiry_date'])?:strcmp($a['production_date'],$b['production_date'])?:((int)$a['id']<=>(int)$b['id']));
            }else{
                $raw=$this->row('SELECT * FROM butchery_raw_lots WHERE id=? FOR UPDATE',[$rawId]);
                if(!$raw||$raw['deleted_at']!==null||(int)$raw['site_id']!==$site||!in_array($raw['status'],['available','reserved'],true)||$raw['expiry_date']<date('Y-m-d')||$day<$raw['production_date'])throw new RuntimeException('Carcasse indisponible, expirée ou date de transformation incohérente.');
                $sources=[$raw];
            }
            $remaining=(int)round($qty*1000);$takes=[];
            foreach($sources as$source){$take=min($remaining,(int)round(((float)$source['quantity_kg']-(float)$source['reserved_kg'])*1000));if($take>0){$takes[]=['raw'=>$source,'quantity'=>$take/1000];$remaining-=$take;}if(!$remaining)break;}
            if($remaining>0)throw new RuntimeException('Quantité disponible insuffisante pour ce produit et les lots choisis. Actualisez les disponibilités.');
            $raw=$takes[0]['raw'];
            $recipe=$this->row("SELECT v.id FROM butchery_recipe_versions v JOIN butchery_recipes r ON r.id=v.recipe_id JOIN butchery_recipe_components c ON c.recipe_version_id=v.id WHERE v.status='active' AND r.status='active' AND r.deleted_at IS NULL AND c.item_id=? AND c.component_type='raw_material' ORDER BY (r.code='DEC-PORC-FERME') DESC,v.id DESC LIMIT 1",[$raw['item_id']]);if(!$recipe)throw new RuntimeException('Créez une fiche de découpe pour cette matière dans les référentiels.');
            if(array_sum(array_column($outputs,'counter'))>0)$this->counter($counterId,$site);
            $this->query("INSERT INTO butchery_orders(site_id,recipe_version_id,order_number,planned_input_kg,actual_input_kg,output_kg,co_product_kg,loss_kg,yield_percent,production_date,status,created_by,flow_managed) VALUES(?,?,?,?,?,?,0,?,?,?,'results_submitted',?,1)",[$site,$recipe['id'],$this->number('DEC'),$qty,$qty,$total,round($qty-$total,3),round($total/$qty*100,3),$day,$user['id']]);
            $order=(int)$this->db->lastInsertId();
            foreach($takes as$take){$source=$take['raw'];$this->query("INSERT INTO butchery_order_consumptions(order_id,raw_lot_id,item_id,consumption_type,quantity_kg,unit_cost) VALUES(?,?,?,'raw_material',?,?)",[$order,$source['id'],$source['item_id'],$take['quantity'],$source['unit_cost']]);$this->query('UPDATE butchery_raw_lots SET reserved_kg=reserved_kg+? WHERE id=?',[$take['quantity'],$source['id']]);}
            foreach($outputs as $out){$item=$this->row("SELECT * FROM butchery_items WHERE id=? AND item_kind IN ('finished_product','co_product') AND unit='kg' AND status='active' AND deleted_at IS NULL",[$out['item']]);if(!$item)throw new RuntimeException('Produit fini invalide.');$this->query('INSERT INTO butchery_order_outputs(order_id,item_id,output_type,quantity_kg,expiry_date) VALUES(?,?,?,?,?)',[$order,$item['id'],$item['item_kind'],$out['cold']+$out['counter'],$out['expiry']]);$output=(int)$this->db->lastInsertId();if($out['counter']>0)$this->query('INSERT INTO butchery_output_allocations(output_id,counter_id,counter_quantity_kg) VALUES(?,?,?)',[$output,$counterId,$out['counter']]);}
            $this->query("UPDATE butchery_orders SET output_kg=(SELECT COALESCE(SUM(quantity_kg),0) FROM butchery_order_outputs WHERE order_id=? AND output_type='finished_product'),co_product_kg=(SELECT COALESCE(SUM(quantity_kg),0) FROM butchery_order_outputs WHERE order_id=? AND output_type='co_product') WHERE id=?",[$order,$order,$order]);
            return $order;
        });
    }
    public function validateTransform($id,array $data,array $user): int
    {
        $site=$this->butcher('validate');
        return $this->operation('butchery_transform_validate',$site,$data,['order'=>(int)$id],$user,function($op)use($site,$id,$user){
            $order=$this->row('SELECT * FROM butchery_orders WHERE id=? FOR UPDATE',[$id]);if(!$order||(int)$order['site_id']!==$site||$order['status']!=='results_submitted'||!(int)$order['flow_managed'])throw new RuntimeException('Transformation non validable.');$this->validateAuthor($order,$user);
            $cons=$this->query('SELECT * FROM butchery_order_consumptions WHERE order_id=? ORDER BY raw_lot_id FOR UPDATE',[$id])->fetchAll();$cost=0;
            foreach($cons as $c){if(!$c['raw_lot_id'])throw new RuntimeException('Validez cette fabrication depuis son parcours historique.');$raw=$this->row('SELECT * FROM butchery_raw_lots WHERE id=? FOR UPDATE',[$c['raw_lot_id']]);$q=(float)$c['quantity_kg'];if(!$raw||$raw['deleted_at']!==null||(int)$raw['site_id']!==$site||$raw['expiry_date']<date('Y-m-d')||!in_array($raw['status'],['available','reserved'],true)||(float)$raw['quantity_kg']<$q||(float)$raw['reserved_kg']<$q)throw new RuntimeException('Réservation de matière indisponible ou expirée.');$after=round((float)$raw['quantity_kg']-$q,3);$reserved=round((float)$raw['reserved_kg']-$q,3);$this->query("UPDATE butchery_raw_lots SET quantity_kg=?,reserved_kg=?,status=IF(?=0,'depleted','available') WHERE id=?",[$after,$reserved,$after,$raw['id']]);$this->rawMove($raw['id'],$q,$raw['quantity_kg'],$after,'production_out','butchery_orders',$id,$user);$cost+=$q*(float)$c['unit_cost'];}
            $outputs=$this->query('SELECT o.*,a.counter_id,a.counter_quantity_kg FROM butchery_order_outputs o LEFT JOIN butchery_output_allocations a ON a.output_id=o.id WHERE o.order_id=? ORDER BY o.id FOR UPDATE',[$id])->fetchAll();$total=array_sum(array_column($outputs,'quantity_kg'));if($total<=0)throw new RuntimeException('Aucun produit obtenu.');
            foreach($outputs as $out){if($out['expiry_date']<date('Y-m-d'))throw new RuntimeException('La DLC d’un produit est dépassée.');if($out['counter_id'])$this->counter((int)$out['counter_id'],$site);
                $this->query("INSERT INTO butchery_finished_lots(site_id,item_id,order_output_id,lot_number,production_date,expiry_date,quantity_kg,reserved_kg,unit_cost,status) VALUES(?,?,?,?,?,?,?,0,?,'available')",[$site,$out['item_id'],$out['id'],$this->number('PORC-PF'),$order['production_date'],$out['expiry_date'],$out['quantity_kg'],$cost/$total]);$lot=(int)$this->db->lastInsertId();$this->finishedMove($lot,$out['quantity_kg'],0,$out['quantity_kg'],'production_in','butchery_orders',$id,$user);
                if($out['counter_id'])$this->supply($lot,(int)$out['counter_id'],(float)$out['counter_quantity_kg'],$op,$user);
            }
            $this->query("UPDATE butchery_orders SET status='validated',validated_by=?,validated_at=CURRENT_TIMESTAMP,total_cost=?,cost_per_kg=? WHERE id=?",[$user['id'],$cost,$cost/$total,$id]);return (int)$id;
        });
    }
    public function cancelTransform($id,array $data,array $user): int
    {
        $site=$this->butcher('delete');$reason=$this->text($data['reason']??'',500,'Motif');
        return $this->operation('butchery_transform_cancel',$site,$data,['order'=>(int)$id,'reason'=>$reason],$user,function()use($site,$id){
            $order=$this->row('SELECT * FROM butchery_orders WHERE id=? FOR UPDATE',[$id]);if(!$order||(int)$order['site_id']!==$site||!$order['flow_managed']||$order['status']!=='results_submitted')throw new RuntimeException('Seule une transformation en attente peut être annulée ici.');
            $lines=$this->query('SELECT * FROM butchery_order_consumptions WHERE order_id=? ORDER BY raw_lot_id FOR UPDATE',[$id])->fetchAll();
            foreach($lines as $line){$raw=$this->row('SELECT * FROM butchery_raw_lots WHERE id=? FOR UPDATE',[$line['raw_lot_id']]);if(!$raw||(float)$raw['reserved_kg']<(float)$line['quantity_kg'])throw new RuntimeException('Réservation incohérente.');$this->query('UPDATE butchery_raw_lots SET reserved_kg=reserved_kg-? WHERE id=?',[$line['quantity_kg'],$raw['id']]);}
            $this->query("UPDATE butchery_orders SET status='cancelled' WHERE id=?",[$id]);return (int)$id;
        });
    }
    public function cancelSlaughter($id,array $data,array $user): int
    {
        $r=$this->row('SELECT c.*,b.site_id FROM livestock_conversions c JOIN pig_slaughter_details d ON d.conversion_id=c.id JOIN livestock_batches b ON b.id=c.batch_id WHERE c.id=?',[$id]);if(!$r)throw new RuntimeException('Abattage introuvable.');$site=(int)$r['site_id'];$this->access($site,'livestock','delete',true);$reason=$this->text($data['reason']??'',500,'Motif');
        return $this->operation('farm_slaughter_cancel',$site,$data,['conversion'=>(int)$id,'reason'=>$reason],$user,function()use($id){$c=$this->row('SELECT * FROM livestock_conversions WHERE id=? FOR UPDATE',[$id]);if($c['status']!=='submitted')throw new RuntimeException('Seul un abattage en attente peut être annulé ici.');$this->query("UPDATE livestock_conversions SET status='cancelled' WHERE id=?",[$id]);return (int)$id;});
    }
    public function cancelShipment($id,array $data,array $user): int
    {
        $t=$this->row('SELECT t.* FROM livestock_transfers t JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN pig_slaughter_details d ON d.conversion_id=w.conversion_id WHERE t.id=?',[$id]);if(!$t)throw new RuntimeException('Envoi introuvable.');$site=(int)$t['source_site_id'];$this->access($site,'livestock','delete',true);$reason=$this->text($data['reason']??'',500,'Motif');
        return $this->operation('farm_shipment_cancel',$site,$data,['transfer'=>(int)$id,'reason'=>$reason],$user,function()use($id,$user,$reason){
            $t=$this->row('SELECT * FROM livestock_transfers WHERE id=? FOR UPDATE',[$id]);if(!in_array($t['status'],['draft','approved'],true))throw new RuntimeException('Seul un envoi non expédié peut être annulé ici.');
            if($t['status']==='approved'){$s=$this->row('SELECT * FROM livestock_weight_stocks WHERE id=? FOR UPDATE',[$t['weight_stock_id']]);$q=(float)$t['quantity_kg'];if((float)$s['reserved_kg']<$q)throw new RuntimeException('Réservation incohérente.');$after=round((float)$s['reserved_kg']-$q,3);$this->query("UPDATE livestock_weight_stocks SET reserved_kg=?,status=IF(?=0,'available','reserved') WHERE id=?",[$after,$after,$s['id']]);$this->query("INSERT INTO livestock_weight_movements(stock_id,movement_type,quantity_kg,stock_before_kg,stock_after_kg,reserved_before_kg,reserved_after_kg,reference_type,reference_id,created_by) VALUES(?,'release',?,?,?,?,?,'livestock_transfers',?,?)",[$s['id'],$q,$s['quantity_kg'],$s['quantity_kg'],$s['reserved_kg'],$after,$id,$user['id']]);}
            $this->query("UPDATE livestock_transfers SET status='cancelled' WHERE id=?",[$id]);
            $documents=$this->query("SELECT * FROM documents WHERE entity_type='livestock_transfers' AND entity_id=? AND status<>'cancelled' FOR UPDATE",[$id])->fetchAll();foreach($documents as $d){$this->query("UPDATE documents SET status='cancelled',cancelled_by=?,cancelled_at=CURRENT_TIMESTAMP WHERE id=?",[$user['id'],$d['id']]);$this->query("INSERT INTO document_status_history(document_id,old_status,new_status,changed_by,reason) VALUES(?,?,'cancelled',?,?)",[$d['id'],$d['status'],$user['id'],mb_substr($reason,0,255)]);}return (int)$id;
        });
    }
    public function coldAvailable(int $lot): float
    {
        return (float)$this->query('SELECT l.quantity_kg-l.reserved_kg-(SELECT COALESCE(SUM(quantity_kg),0) FROM butchery_counter_stocks WHERE finished_lot_id=l.id) FROM butchery_finished_lots l WHERE l.id=?',[$lot])->fetchColumn();
    }
    public function move(array $data,array $user): int
    {
        $site=$this->butcher('update');$lot=$this->integer($data['finished_lot_id']??'');$counter=$this->integer($data['counter_id']??'');$qty=$this->amount($data['quantity_kg']??'');$direction=$data['direction']??'';if(!in_array($direction,['supply','return_cold'],true))throw new RuntimeException('Sens du mouvement invalide.');
        return $this->operation('butchery_location',$site,$data,compact('lot','counter','qty','direction'),$user,function($op)use($site,$lot,$counter,$qty,$direction,$user){
            $l=$this->row('SELECT * FROM butchery_finished_lots WHERE id=? FOR UPDATE',[$lot]);if(!$l||(int)$l['site_id']!==$site||!in_array($l['status'],['available','reserved'],true)||$l['expiry_date']<date('Y-m-d'))throw new RuntimeException('Produit indisponible ou DLC dépassée.');$this->counter($counter,$site);
            if($direction==='supply'){if($qty>round($this->coldAvailable($lot),3))throw new RuntimeException('Quantité disponible en chambre froide insuffisante.');return $this->supply($lot,$counter,$qty,$op,$user);}
            $stock=$this->row('SELECT * FROM butchery_counter_stocks WHERE counter_id=? AND finished_lot_id=? FOR UPDATE',[$counter,$lot]);if(!$stock||$qty>(float)$stock['quantity_kg'])throw new RuntimeException('Stock du comptoir insuffisant.');$after=round((float)$stock['quantity_kg']-$qty,3);$this->query('UPDATE butchery_counter_stocks SET quantity_kg=? WHERE id=?',[$after,$stock['id']]);$this->counterMove((int)$stock['id'],$op,'return_cold',$qty,(float)$stock['quantity_kg'],$after,$user);return (int)$stock['id'];
        });
    }
    public function sell(array $data,array $user): int
    {
        $site=$this->butcher('create');$id=$this->integer($data['counter_stock_id']??'');$qty=$this->amount($data['quantity_kg']??'');$price=$this->amount($data['unit_price']??'',false,4);$customer=trim((string)($data['customer_name']??''))?:'Client comptoir';$customer=$this->text($customer,180,'Client');
        return $this->operation('butchery_counter_sale',$site,$data,compact('id','qty','price','customer'),$user,function($op)use($site,$id,$qty,$price,$customer,$user){
            $ref=$this->row('SELECT finished_lot_id,counter_id FROM butchery_counter_stocks WHERE id=?',[$id]);if(!$ref)throw new RuntimeException('Produit du comptoir introuvable.');
            // All operations lock total lot, counter, then per-counter stock in that order.
            $l=$this->row('SELECT * FROM butchery_finished_lots WHERE id=? FOR UPDATE',[$ref['finished_lot_id']]);if(!$l||(int)$l['site_id']!==$site||!in_array($l['status'],['available','reserved'],true)||$l['expiry_date']<date('Y-m-d'))throw new RuntimeException('Lot indisponible ou DLC dépassée.');$this->counter((int)$ref['counter_id'],$site);
            $stock=$this->row('SELECT * FROM butchery_counter_stocks WHERE id=? FOR UPDATE',[$id]);if($qty>(float)$stock['quantity_kg']||$qty>round((float)$l['quantity_kg']-(float)$l['reserved_kg'],3))throw new RuntimeException('Stock du comptoir insuffisant.');
            $this->query("INSERT INTO butchery_sales(site_id,sale_number,finished_lot_id,counter_stock_id,customer_name,quantity_kg,unit_price,sold_at,status,created_by) VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP,'validated',?)",[$site,$this->number('VTE'),$l['id'],$id,$customer,$qty,$price,$user['id']]);$sale=(int)$this->db->lastInsertId();$after=round((float)$stock['quantity_kg']-$qty,3);$this->query('UPDATE butchery_counter_stocks SET quantity_kg=? WHERE id=?',[$after,$id]);$this->counterMove($id,$op,'sale',$qty,(float)$stock['quantity_kg'],$after,$user);$total=round((float)$l['quantity_kg']-$qty,3);$this->query("UPDATE butchery_finished_lots SET quantity_kg=?,status=IF(?=0,'depleted',status) WHERE id=?",[$total,$total,$l['id']]);$this->finishedMove($l['id'],$qty,$l['quantity_kg'],$total,'sale_out','butchery_sales',$sale,$user);return $sale;
        });
    }
    public function posAvailable(): bool
    {
        return (int)$this->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('butchery_pos_prices','butchery_pos_tickets','butchery_pos_lines','butchery_pos_allocations')")->fetchColumn()===4;
    }
    public function posSettings(): array
    {
        $settings=require dirname(__DIR__,2).'/config/butchery_pos.php';
        if(!in_array($settings['currency'],$settings['currencies'],true))$settings['currency']='USD';
        return $settings;
    }
    private function posUnits($value,int $decimals,bool $zero=false,int $digits=6): int
    {
        if(!is_scalar($value)||!preg_match('/^\d{1,'.$digits.'}(?:\.\d{1,'.$decimals.'})?$/D',(string)$value))throw new RuntimeException('Montant ou poids invalide.');
        $parts=explode('.',(string)$value);$units=(int)$parts[0]*(10**$decimals)+(int)str_pad($parts[1]??'',$decimals,'0');
        if(!$zero&&$units===0)throw new RuntimeException('Le montant ou le poids doit être supérieur à zéro.');
        return $units;
    }
    private function posDecimal(int $units,int $decimals=2): string
    {
        $scale=10**$decimals;return intdiv($units,$scale).'.'.str_pad((string)($units%$scale),$decimals,'0',STR_PAD_LEFT);
    }
    private function posCurrency($value): string
    {
        if(!is_string($value)||!in_array($value,$this->posSettings()['currencies'],true))throw new RuntimeException('Choisissez une devise autorisée.');return $value;
    }
    public function posDirectory(): array
    {
        $site=$this->butcher('read');
        return [
            'posSettings'=>$this->posSettings(),
            'posCatalog'=>$this->query("SELECT cs.counter_id,i.id item_id,i.name,ROUND(SUM(cs.quantity_kg),3) quantity_kg FROM butchery_counter_stocks cs JOIN butchery_counters c ON c.id=cs.counter_id JOIN butchery_finished_lots l ON l.id=cs.finished_lot_id JOIN butchery_items i ON i.id=l.item_id WHERE c.site_id=? AND l.site_id=c.site_id AND c.status='active' AND l.deleted_at IS NULL AND l.status IN('available','reserved') AND l.expiry_date>=? AND i.status='active' AND i.deleted_at IS NULL AND i.unit='kg' AND i.item_kind IN('finished_product','co_product') AND cs.quantity_kg>0 GROUP BY cs.counter_id,i.id,i.name ORDER BY i.name",[$site,date('Y-m-d')])->fetchAll(),
            'posPrices'=>$this->query('SELECT * FROM butchery_pos_prices WHERE site_id=?',[$site])->fetchAll(),
            'posTickets'=>$this->query('SELECT * FROM butchery_pos_tickets WHERE site_id=? ORDER BY id DESC LIMIT 100',[$site])->fetchAll(),
            'posLegacySales'=>$this->query('SELECT s.*,i.name item_name FROM butchery_sales s LEFT JOIN butchery_finished_lots l ON l.id=s.finished_lot_id LEFT JOIN butchery_items i ON i.id=l.item_id WHERE s.site_id=? AND NOT EXISTS(SELECT 1 FROM butchery_pos_allocations a WHERE a.sale_id=s.id) ORDER BY s.id DESC LIMIT 100',[$site])->fetchAll(),
        ];
    }
    public function posSalesReport(array $query): array
    {
        $site=$this->butcher('read');
        require_once __DIR__.'/ButcherySalesReportService.php';
        $counters=$this->query('SELECT id,name FROM butchery_counters WHERE site_id=? ORDER BY name',[$site])->fetchAll();
        return (new ButcherySalesReportService())->report($this->db,$site,$query,$counters,$this->posSettings());
    }
    public function posPrice(array $data,array $user): int
    {
        $site=$this->butcher('update');$item=$this->integer($data['item_id']??'');$currency=$this->posCurrency($data['currency']??'');$price=$this->posUnits($data['price']??'',2);
        return $this->operation('butchery_pos_price',$site,$data,compact('item','currency','price'),$user,function()use($site,$item,$currency,$price,$user){
            if(!$this->row("SELECT id FROM butchery_items WHERE id=? AND status='active' AND deleted_at IS NULL AND unit='kg' AND item_kind IN('finished_product','co_product')",[$item]))throw new RuntimeException('Article de vente introuvable.');
            $this->query('INSERT INTO butchery_pos_prices(site_id,item_id,currency,price,updated_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE price=VALUES(price),updated_by=VALUES(updated_by)',[$site,$item,$currency,$this->posDecimal($price),$user['id']]);return $item;
        });
    }
    public function posCheckout(array $data,array $user): int
    {
        $site=$this->butcher('create');$counter=$this->integer($data['counter_id']??'');$currency=$this->posCurrency($data['currency']??'');
        $method=$data['payment_method']??'';if(!is_string($method)||!isset($this->posSettings()['payment_methods'][$method]))throw new RuntimeException('Choisissez un mode de paiement.');
        $received=$this->posUnits($data['received_amount']??'',2,true,12);$customer=$this->text($data['customer_name']??'Client comptoir',180,'Client');
        $posted=$data['lines']??null;if(!is_array($posted)||!count($posted)||count($posted)>30)throw new RuntimeException('Ajoutez de 1 à 30 articles au panier.');
        $lines=[];foreach($posted as$line){if(!is_array($line))throw new RuntimeException('Ligne de panier invalide.');$item=$this->integer($line['item_id']??'');if(isset($lines[$item]))throw new RuntimeException('Regroupez les quantités du même article.');$lines[$item]=['grams'=>$this->posUnits($line['quantity_kg']??'',3),'price'=>$this->posUnits($line['unit_price']??'',2)];}ksort($lines,SORT_NUMERIC);
        return $this->operation('butchery_pos_checkout',$site,$data,compact('counter','currency','method','received','customer','lines'),$user,function($op)use($site,$counter,$currency,$method,$received,$customer,$lines,$user){
            $total=0;$priced=[];
            foreach($lines as$item=>$line){
                $price=$this->row("SELECT p.price,i.name FROM butchery_pos_prices p JOIN butchery_items i ON i.id=p.item_id WHERE p.site_id=? AND p.item_id=? AND p.currency=? AND i.status='active' AND i.deleted_at IS NULL AND i.unit='kg' AND i.item_kind IN('finished_product','co_product') FOR UPDATE",[$site,$item,$currency]);
                if(!$price)throw new RuntimeException('Un article ne possède pas de tarif actif dans cette devise.');
                $cents=$this->posUnits($price['price'],2);if($cents!==$line['price'])throw new RuntimeException('Un tarif a changé. Actualisez les produits avant de confirmer.');
                $amount=intdiv($line['grams']*$cents+500,1000);if($amount<1)throw new RuntimeException('Le montant d’un article est inférieur à la précision de la devise.');
                $priced[$item]=$line+['name'=>$price['name'],'amount'=>$amount];$total+=$amount;
            }
            if($method==='cash'?$received<$total:$received!==$total)throw new RuntimeException($method==='cash'?'Le montant reçu est insuffisant.':'Le paiement doit correspondre au total exact.');
            // Lock total lots in ascending order, then counter and its stock rows, like existing movements.
            $ids=array_keys($lines);$marks=implode(',',array_fill(0,count($ids),'?'));
            $refs=$this->query("SELECT l.id FROM butchery_finished_lots l JOIN butchery_counter_stocks cs ON cs.finished_lot_id=l.id WHERE l.site_id=? AND cs.counter_id=? AND l.item_id IN ($marks) ORDER BY l.id",array_merge([$site,$counter],$ids))->fetchAll();
            $lots=[];foreach($refs as$ref)$lots[$ref['id']]=$this->row('SELECT * FROM butchery_finished_lots WHERE id=? FOR UPDATE',[$ref['id']]);
            $counterRow=$this->counter($counter,$site);
            $stocks=$this->query('SELECT * FROM butchery_counter_stocks WHERE counter_id=? ORDER BY finished_lot_id FOR UPDATE',[$counter])->fetchAll();
            usort($stocks,static function($a,$b)use($lots){$left=$lots[$a['finished_lot_id']]??[];$right=$lots[$b['finished_lot_id']]??[];return strcmp($left['expiry_date']??'9999',$right['expiry_date']??'9999')?:strcmp($left['production_date']??'9999',$right['production_date']??'9999')?:($a['finished_lot_id']<=>$b['finished_lot_id']);});
            $takes=[];
            foreach($priced as$item=>$line){
                $remaining=$line['grams'];
                foreach($stocks as$stock){$lot=$lots[$stock['finished_lot_id']]??null;if(!$lot||(int)$lot['item_id']!==$item||$lot['deleted_at']!==null||!in_array($lot['status'],['available','reserved'],true)||$lot['expiry_date']<date('Y-m-d'))continue;
                    $free=min((int)round((float)$stock['quantity_kg']*1000),(int)round(((float)$lot['quantity_kg']-(float)$lot['reserved_kg'])*1000));$take=min($remaining,max(0,$free));if(!$take)continue;
                    $takes[]=['item'=>$item,'stock'=>$stock,'lot'=>$lot,'grams'=>$take];$remaining-=$take;if(!$remaining)break;
                }
                if($remaining)throw new RuntimeException('Stock disponible insuffisant au comptoir pour '.$line['name'].'. Actualisez les produits.');
            }
            $this->query('INSERT INTO butchery_pos_tickets(site_id,counter_id,ticket_number,counter_name,cashier_name,customer_name,currency,total,payment_method,received_amount,change_amount,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$site,$counter,$this->number('TICKET'),$counterRow['name'],$user['name'],$customer,$currency,$this->posDecimal($total),$method,$this->posDecimal($received),$this->posDecimal($received-$total),$user['id']]);$ticket=(int)$this->db->lastInsertId();$lineIds=[];
            foreach($priced as$item=>$line){$this->query('INSERT INTO butchery_pos_lines(ticket_id,item_id,item_name,quantity_kg,unit_price,amount) VALUES(?,?,?,?,?,?)',[$ticket,$item,$line['name'],$this->posDecimal($line['grams'],3),$this->posDecimal($line['price']),$this->posDecimal($line['amount'])]);$lineIds[$item]=(int)$this->db->lastInsertId();}
            foreach($takes as$take){$lot=$take['lot'];$stock=$take['stock'];$quantity=$this->posDecimal($take['grams'],3);$stockAfter=round((float)$stock['quantity_kg']-(float)$quantity,3);$lotAfter=round((float)$lot['quantity_kg']-(float)$quantity,3);
                $this->query("INSERT INTO butchery_sales(site_id,sale_number,finished_lot_id,counter_stock_id,customer_name,quantity_kg,unit_price,sold_at,status,created_by) VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP,'validated',?)",[$site,$this->number('VTE'),$lot['id'],$stock['id'],$customer,$quantity,$this->posDecimal($priced[$take['item']]['price']),$user['id']]);$sale=(int)$this->db->lastInsertId();
                $this->query('INSERT INTO butchery_pos_allocations(line_id,sale_id) VALUES(?,?)',[$lineIds[$take['item']],$sale]);
                $this->query('UPDATE butchery_counter_stocks SET quantity_kg=? WHERE id=?',[$stockAfter,$stock['id']]);$this->counterMove($stock['id'],$op,'sale',(float)$quantity,(float)$stock['quantity_kg'],$stockAfter,$user);
                $this->query("UPDATE butchery_finished_lots SET quantity_kg=?,status=IF(?=0,'depleted',status) WHERE id=?",[$lotAfter,$lotAfter,$lot['id']]);$this->finishedMove($lot['id'],(float)$quantity,$lot['quantity_kg'],$lotAfter,'sale_out','butchery_sales',$sale,$user);
            }
            return $ticket;
        });
    }
    public function posTicket($id): ?array
    {
        $site=$this->butcher('read');if(!is_scalar($id)||!preg_match('/^[0-9]{1,8}$/D',(string)$id)||(int)$id<1)return null;$id=(int)$id;
        $ticket=$this->row('SELECT t.*,s.name site_name FROM butchery_pos_tickets t JOIN sites s ON s.id=t.site_id WHERE t.id=? AND t.site_id=?',[$id,$site]);if(!$ticket)return null;
        $ticket['lines']=$this->query('SELECT * FROM butchery_pos_lines WHERE ticket_id=? ORDER BY id',[$id])->fetchAll();return $ticket;
    }
    public function posCancel($id,array $data,array $user): int
    {
        $site=$this->butcher('delete');$id=$this->integer($id);$reason=$this->text($data['reason']??'',500,'Motif');
        if(($data['not_delivered']??'')!=='1')throw new RuntimeException('Confirmez qu’aucun produit n’a été livré.');
        return $this->operation('butchery_pos_cancel',$site,$data,compact('id','reason'),$user,function($op)use($site,$id,$reason,$user){
            $ticket=$this->row('SELECT * FROM butchery_pos_tickets WHERE id=? AND site_id=? FOR UPDATE',[$id,$site]);if(!$ticket||$ticket['status']!=='paid')throw new RuntimeException('Ticket introuvable ou déjà annulé.');
            $sales=$this->query('SELECT s.* FROM butchery_sales s JOIN butchery_pos_allocations a ON a.sale_id=s.id JOIN butchery_pos_lines l ON l.id=a.line_id WHERE l.ticket_id=? ORDER BY s.finished_lot_id',[$id])->fetchAll();
            $lots=[];foreach($sales as$s)$lots[$s['finished_lot_id']]=$this->row('SELECT * FROM butchery_finished_lots WHERE id=? FOR UPDATE',[$s['finished_lot_id']]);
            $this->row('SELECT id FROM butchery_counters WHERE id=? FOR UPDATE',[$ticket['counter_id']]);
            foreach($sales as$s){$lot=$lots[$s['finished_lot_id']];$stock=$this->row('SELECT * FROM butchery_counter_stocks WHERE id=? FOR UPDATE',[$s['counter_stock_id']]);$qty=(float)$s['quantity_kg'];$stockAfter=round((float)$stock['quantity_kg']+$qty,3);$lotAfter=round((float)$lot['quantity_kg']+$qty,3);
                $this->query('UPDATE butchery_counter_stocks SET quantity_kg=? WHERE id=?',[$stockAfter,$stock['id']]);$this->counterMove($stock['id'],$op,'sale_cancel',$qty,$stock['quantity_kg'],$stockAfter,$user);
                $this->query("UPDATE butchery_finished_lots SET quantity_kg=?,status=IF(status='depleted','available',status) WHERE id=?",[$lotAfter,$lot['id']]);$this->finishedMove($lot['id'],$qty,$lot['quantity_kg'],$lotAfter,'reversal','butchery_sales',$s['id'],$user);$this->query("UPDATE butchery_sales SET status='cancelled' WHERE id=?",[$s['id']]);
            }
            $this->query("UPDATE butchery_pos_tickets SET status='cancelled',cancelled_by=?,cancelled_at=CURRENT_TIMESTAMP,cancellation_reason=? WHERE id=?",[$user['id'],$reason,$id]);return $id;
        });
    }
    public function createCounter(array $data,array $user): int
    {
        $site=$this->butcher('administer');$name=$this->text($data['name']??'',150,'Nom du comptoir');
        return $this->operation('butchery_counter_create',$site,$data,['name'=>$name],$user,function()use($site,$name,$user){$this->query("INSERT INTO butchery_counters(site_id,code,name,status,created_by) VALUES(?,?,?,'active',?)",[$site,$this->number('CPT'),$name,$user['id']]);return (int)$this->db->lastInsertId();});
    }
}
