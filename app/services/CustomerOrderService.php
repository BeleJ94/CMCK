<?php
require_once __DIR__.'/SalesSettingsService.php';
require_once __DIR__.'/TransferService.php';

/**
 * Client orders (048): booking at the production site, assigned transfers to the main office, client stock at the office.
 * Booked bags use finished_stocks.reserved_bags, so no other exit can take them. Bags of a line are, in turn:
 * reserved at the production site (allocation « source »), reserved by an approved transfer, in transit,
 * reserved at the office (allocation « office »), then delivered (lot 3). The rest is « à produire ».
 */
class CustomerOrderService extends Model
{
    /** Production site of each product sold to clients: flour at the mill, livestock feed at pelletization. */
    public const SOURCE_SITES=['FARINE-MAIS'=>'MINO','ALIMENT-BETAIL'=>'PELL'];
    public const STATUSES=['draft'=>['Brouillon','neutral'],'validated'=>['En cours','progress'],'closed'=>['Soldée','success'],'cancelled'=>['Annulée','neutral']];
    public const MOVEMENTS=['reserve'=>'Réservé au site de production','release'=>'Réservation libérée','transfer_out'=>'Confié à un envoi approuvé','transfer_back'=>'Revenu en réservation','office_in'=>'Arrivé au bureau principal','rejected'=>'Refusé à la réception','delivered'=>'Livré au client','delivery_cancelled'=>'Livraison annulée, sacs remis en réservation'];
    private const MAX_BAGS=100000;
    private static $installed=[];

    public function __construct(PDO $db=null) { $this->db=$db?:Database::getInstance()->connection(); }

    public static function installed(PDO $db): bool
    {
        $key=spl_object_id($db);
        if(!isset(self::$installed[$key]))self::$installed[$key]=(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='customer_orders'")->fetchColumn()>0;
        return self::$installed[$key];
    }
    public function ready(): bool { return self::installed($this->db); }

    public function directory(): array
    {
        if(!$this->ready())return ['ready'=>false];
        $settings=new SalesSettingsService($this->db);
        // The closer of an order exists from migration 049 (deliveries).
        $closer=(int)$this->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='customer_orders' AND column_name='closed_by'")->fetchColumn()>0;
        $orders=$this->query("SELECT o.*,c.code customer_code,c.name customer_name,c.status customer_status,d.name destination_name,creator.name creator_name,validator.name validator_name,canceller.name canceller_name,".($closer?'closer.name':'NULL')." closer_name
            FROM customer_orders o JOIN customers c ON c.id=o.customer_id JOIN sites d ON d.id=o.destination_site_id JOIN users creator ON creator.id=o.created_by
            LEFT JOIN users validator ON validator.id=o.validated_by LEFT JOIN users canceller ON canceller.id=o.cancelled_by".($closer?' LEFT JOIN users closer ON closer.id=o.closed_by':'')."
            ORDER BY FIELD(o.status,'draft','validated','closed','cancelled'),o.id DESC")->fetchAll();
        $lines=[];foreach(self::progress($this->db,'1=1',[],'l.id') as $line)$lines[(int)$line['order_id']][]=$line;
        $transfers=[];foreach($this->query("SELECT t.id,t.transfer_number,t.status,t.customer_order_id,t.created_at,ss.name source_name,ds.name destination_name,SUM(i.requested_bags) bags,SUM(i.shipped_bags) shipped_bags,SUM(i.accepted_bags) accepted_bags FROM stock_transfers t JOIN sites ss ON ss.id=t.source_site_id JOIN sites ds ON ds.id=t.destination_site_id JOIN stock_transfer_items i ON i.transfer_id=t.id WHERE t.customer_order_id IS NOT NULL AND t.deleted_at IS NULL GROUP BY t.id ORDER BY t.id DESC")->fetchAll() as $t)$transfers[(int)$t['customer_order_id']][]=$t;
        $movements=[];foreach($this->query("SELECT m.*,p.name product_name,bf.name format_name,s.name site_name,t.transfer_number,u.name author FROM customer_order_movements m JOIN customer_order_lines l ON l.id=m.order_line_id JOIN products p ON p.id=l.product_id JOIN bag_formats bf ON bf.id=l.bag_format_id LEFT JOIN finished_stocks fs ON fs.id=m.finished_stock_id LEFT JOIN sites s ON s.id=fs.site_id LEFT JOIN stock_transfers t ON t.id=m.transfer_id LEFT JOIN users u ON u.id=m.created_by ORDER BY m.id DESC LIMIT 1000")->fetchAll() as $m)if(count($movements[(int)$m['order_id']]??[])<20)$movements[(int)$m['order_id']][]=$m;
        foreach($orders as &$order){
            $id=(int)$order['id'];$order['lines']=$lines[$id]??[];$order['transfers']=$transfers[$id]??[];$order['movements']=$movements[$id]??[];
            foreach(['quantity_bags','source_bags','planned_bags','awaiting_bags','transit_bags','office_bags','delivered_bags','remaining_bags'] as $key)$order[$key]=array_sum(array_map(static fn($l)=>(int)$l[$key],$order['lines']));
            $order['amount_usd']=round(array_sum(array_map(static fn($l)=>(int)$l['quantity_bags']*(float)$l['unit_price_usd'],$order['lines'])),2);
            $order['plannable_bags']=array_sum(array_map(static fn($l)=>max(0,(int)$l['source_bags']-(int)$l['planned_bags']),$order['lines']));
            $order['in_flight']=count(array_filter($order['transfers'],static fn($t)=>!in_array($t['status'],['draft','submitted','cancelled','closed'],true)));
        }
        unset($order);
        $catalog=[];
        foreach($settings->catalog('company') as $row){
            if(!isset(self::SOURCE_SITES[$row['product_code']]))continue;
            $site=(int)$this->query('SELECT id FROM sites WHERE code=?',[self::SOURCE_SITES[$row['product_code']]])->fetchColumn();
            $row['source_site_id']=$site;$row['source_name']=(string)$this->query('SELECT name FROM sites WHERE id=?',[$site])->fetchColumn();
            $row['free_bags']=(int)$this->query("SELECT COALESCE(SUM(quantity_bags-reserved_bags),0) FROM finished_stocks WHERE site_id=? AND product_id=? AND bag_format_id=? AND deleted_at IS NULL AND status IN ('active','validated')",[$site,$row['product_id'],$row['bag_format_id']])->fetchColumn();
            $catalog[]=$row;
        }
        return ['ready'=>true,'orders'=>$orders,'catalog'=>$catalog,'rate'=>$settings->currentRate(),'depots'=>$settings->mainOfficeDepots(),
            'customers'=>$this->query("SELECT id,code,name,billing_currency FROM customers WHERE status='active' AND deleted_at IS NULL ORDER BY name")->fetchAll(),
            'canCreate'=>Auth::can('distributions','create'),'canValidate'=>Auth::can('distributions','validate'),'canTransfer'=>Auth::can('transfers','create')];
    }

    /** Creates an order, or replaces a draft: prices and rate are those in force at the time of saving. */
    public function save(array $data,array $user,$id=null): int
    {
        $this->guard('create');$editing=$id!==null;
        $settings=new SalesSettingsService($this->db);
        $customerId=$this->int($data['customer_id']??'','Client');
        $destination=$this->int($data['destination_site_id']??'','Dépôt du bureau principal');
        if(!in_array($destination,array_map('intval',array_column($settings->mainOfficeDepots(),'id')),true))throw new RuntimeException('Choisissez un dépôt du bureau principal.');
        $requested=trim((string)($data['requested_date']??''));
        if($requested!==''){$date=DateTimeImmutable::createFromFormat('!Y-m-d',$requested);if(!$date||$date->format('Y-m-d')!==$requested)throw new RuntimeException('Date souhaitée invalide.');if($requested<date('Y-m-d'))throw new RuntimeException('La date souhaitée ne peut pas être passée.');}
        $notes=trim((string)($data['notes']??''));if(mb_strlen($notes)>500)throw new RuntimeException('Observations : 500 caractères au maximum.');
        $catalog=[];foreach($settings->catalog('company') as $row)if(isset(self::SOURCE_SITES[$row['product_code']]))$catalog[$row['product_id'].'-'.$row['bag_format_id']]=$row;
        $lines=[];
        foreach(is_array($data['lines']??null)?$data['lines']:[] as $line){
            if(!is_array($line))throw new RuntimeException('Ligne de commande invalide.');
            $key=(string)($line['product_key']??'');if($key===''&&trim((string)($line['quantity_bags']??''))==='')continue;
            if(!isset($catalog[$key]))throw new RuntimeException('Choisissez un produit et un format pour chaque ligne.');
            $row=$catalog[$key];
            if($row['price_usd']===null)throw new RuntimeException('Fixez d’abord le tarif entreprises de « '.$row['product_name'].' · '.$row['format_name'].' » dans Tarifs et taux.');
            $bags=$line['quantity_bags']??'';if(!is_scalar($bags)||!ctype_digit((string)$bags)||(int)$bags<1||(int)$bags>self::MAX_BAGS)throw new RuntimeException('Nombre de sacs invalide pour « '.$row['format_name'].' » (1 à '.self::MAX_BAGS.').');
            if(isset($lines[$key]))throw new RuntimeException('« '.$row['product_name'].' · '.$row['format_name'].' » figure deux fois : regroupez les quantités sur une seule ligne.');
            $site=(int)$this->query('SELECT id FROM sites WHERE code=? AND deleted_at IS NULL',[self::SOURCE_SITES[$row['product_code']]])->fetchColumn();
            if(!$site)throw new RuntimeException('Site de production introuvable pour « '.$row['product_name'].' ».');
            $lines[$key]=['product_id'=>(int)$row['product_id'],'bag_format_id'=>(int)$row['bag_format_id'],'source_site_id'=>$site,'quantity_bags'=>(int)$bags,'unit_price_usd'=>(float)$row['price_usd']];
        }
        if(!$lines)throw new RuntimeException('Ajoutez au moins une ligne à la commande.');
        if(count($lines)>20)throw new RuntimeException('Une commande compte au plus 20 lignes.');
        $this->db->beginTransaction();
        try{
            $customer=$this->query('SELECT * FROM customers WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$customerId])->fetch();
            if(!$customer)throw new RuntimeException('Client introuvable.');
            if($customer['status']!=='active')throw new RuntimeException('Le client '.$customer['code'].' est bloqué : '.$customer['blocked_reason']);
            $rate=$settings->currentRate();
            if($customer['billing_currency']==='CDF'&&$rate===null)throw new RuntimeException('Ce client est facturé en CDF : saisissez d’abord le taux du jour dans Tarifs et taux.');
            $header=['customer_id'=>$customerId,'destination_site_id'=>$destination,'currency'=>$customer['billing_currency'],'exchange_rate'=>$rate,'requested_date'=>$requested===''?null:$requested,'notes'=>$notes===''?null:$notes];
            if($editing){
                $id=$this->int($id,'Commande');$order=$this->lockOrder($id);
                if($order['status']!=='draft')throw new RuntimeException('Seule une commande en brouillon peut être modifiée.');
                if((int)$order['created_by']!==(int)$user['id']&&!Auth::can('distributions','update'))throw new RuntimeException('Seul son auteur ou un utilisateur habilité peut modifier ce brouillon.');
                $this->query('UPDATE customer_orders SET customer_id=:customer_id,destination_site_id=:destination_site_id,currency=:currency,exchange_rate=:exchange_rate,requested_date=:requested_date,notes=:notes WHERE id=:id',$header+['id'=>$id]);
                $this->query('DELETE FROM customer_order_lines WHERE order_id=?',[$id]);$number=$order['order_number'];
            }else{
                $number=$this->nextNumber();
                $this->query('INSERT INTO customer_orders(order_number,customer_id,destination_site_id,currency,exchange_rate,requested_date,notes,created_by) VALUES(:number,:customer_id,:destination_site_id,:currency,:exchange_rate,:requested_date,:notes,:user)',$header+['number'=>$number,'user'=>$user['id']]);
                $id=(int)$this->db->lastInsertId();
            }
            foreach($lines as $line)$this->query('INSERT INTO customer_order_lines(order_id,product_id,bag_format_id,source_site_id,quantity_bags,unit_price_usd) VALUES(?,?,?,?,?,?)',[$id,$line['product_id'],$line['bag_format_id'],$line['source_site_id'],$line['quantity_bags'],$line['unit_price_usd']]);
            $bags=array_sum(array_column($lines,'quantity_bags'));$amount=array_sum(array_map(static fn($l)=>$l['quantity_bags']*$l['unit_price_usd'],$lines));
            $this->logActivity($editing?'customer_order_update':'customer_order_create','distributions','customer_orders',$id,($editing?'Modification':'Création').' de la commande '.$number.' · '.$customer['name'].' : '.$bags.' sac(s), '.number_format($amount,2,',',' ').' USD.',null,$header+['lines'=>array_values($lines)],$user);
            $this->db->commit();return $id;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** Validation by another authorised user, then booking of what is available at the production sites. */
    public function validate($id,array $user): string
    {
        $this->guard('validate');$id=$this->int($id,'Commande');
        $this->db->beginTransaction();
        try{
            $order=$this->lockOrder($id);
            if($order['status']!=='draft')throw new RuntimeException('Cette commande est déjà validée ou annulée.');
            if((int)$order['created_by']===(int)$user['id']&&!Auth::canSelfValidate($user['id'],'distributions'))throw new RuntimeException('Un autre utilisateur autorisé doit valider cette commande.');
            $customer=$this->query('SELECT code,name,status,blocked_reason FROM customers WHERE id=?',[$order['customer_id']])->fetch();
            if($customer['status']!=='active')throw new RuntimeException('Le client '.$customer['code'].' est bloqué : '.$customer['blocked_reason']);
            $this->query("UPDATE customer_orders SET status='validated',validated_by=?,validated_at=NOW() WHERE id=?",[$user['id'],$id]);
            $reserved=0;$total=0;
            foreach(self::lockedProgress($this->db,'l.order_id=?',[$id]) as $line){$total+=(int)$line['quantity_bags'];$reserved+=self::reserve($this->db,$line,(int)$line['remaining_bags'],$user,'Réservation à la validation');}
            $this->logActivity('customer_order_validate','distributions','customer_orders',$id,'Validation de la commande '.$order['order_number'].' · '.$customer['name'].' : '.$reserved.' sac(s) réservé(s) sur '.$total.'.',['status'=>'draft'],['status'=>'validated','reserved_bags'=>$reserved],$user);
            $this->db->commit();
            return 'Commande validée : '.$reserved.' sac(s) réservé(s) au site de production'.($total>$reserved?', '.($total-$reserved).' à produire, réservés automatiquement dès leur emballage.':'.');
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** Books what became available since the validation (new packaging, returns). */
    public function reserveAvailable($id,array $user): string
    {
        $this->guard('create');$id=$this->int($id,'Commande');
        $this->db->beginTransaction();
        try{
            $order=$this->lockOrder($id);if($order['status']!=='validated')throw new RuntimeException('Seule une commande en cours peut être réservée.');
            $lines=self::lockedProgress($this->db,'l.order_id=?',[$id]);$missing=array_sum(array_column($lines,'remaining_bags'));
            if(!$missing)throw new RuntimeException('Tous les sacs de cette commande sont déjà réservés, en route ou au bureau.');
            $reserved=0;foreach($lines as $line)$reserved+=self::reserve($this->db,$line,(int)$line['remaining_bags'],$user,'Réservation du disponible');
            if(!$reserved)throw new RuntimeException('Aucun sac disponible au site de production pour cette commande.');
            $this->db->commit();
            return $reserved.' sac(s) réservé(s).'.($missing>$reserved?' Il reste '.($missing-$reserved).' sac(s) à produire.':'');
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** Assigned transfer to the main office, one per production site, submitted for approval. */
    public function prepareTransfer($id,array $data,array $user): string
    {
        $this->guard('create');Auth::requirePermission('transfers','create');$id=$this->int($id,'Commande');
        $order=$this->query('SELECT * FROM customer_orders WHERE id=?',[$id])->fetch();
        if(!$order||$order['status']!=='validated')throw new RuntimeException('Seule une commande en cours peut être envoyée.');
        $destination=$this->int($data['destination_site_id']??$order['destination_site_id'],'Dépôt du bureau principal');
        if(!in_array($destination,array_map('intval',array_column((new SalesSettingsService($this->db))->mainOfficeDepots(),'id')),true))throw new RuntimeException('Choisissez un dépôt du bureau principal.');
        $wanted=is_array($data['bags']??null)?$data['bags']:[];$bySite=[];
        foreach(self::progress($this->db,'l.order_id=?',[$id],'l.id') as $line){
            $bags=$wanted[$line['id']]??'';if($bags===''||$bags==='0')continue;
            if(!ctype_digit((string)$bags))throw new RuntimeException('Nombre de sacs invalide.');$bags=(int)$bags;
            $free=(int)$line['source_bags']-(int)$line['planned_bags'];
            if($bags>$free)throw new RuntimeException('« '.$line['product_name'].' · '.$line['format_name'].' » : '.$free.' sac(s) réservé(s) disponible(s) pour un envoi.');
            // Lots of the line, oldest booking first, minus what draft transfers already plan to take.
            foreach($this->query("SELECT a.finished_stock_id,a.quantity_bags-COALESCE((SELECT SUM(i.requested_bags) FROM stock_transfer_items i JOIN stock_transfers t ON t.id=i.transfer_id WHERE i.customer_order_line_id=a.order_line_id AND i.source_finished_stock_id=a.finished_stock_id AND t.status IN ('draft','submitted')),0) free_bags FROM customer_order_allocations a WHERE a.order_line_id=? AND a.stage='source' AND a.quantity_bags>0 ORDER BY a.id",[$line['id']])->fetchAll() as $lot){
                $take=min($bags,(int)$lot['free_bags']);if($take<=0)continue;
                $bySite[(int)$line['source_site_id']][]=['finished_stock_id'=>(int)$lot['finished_stock_id'],'quantity_bags'=>$take,'customer_order_line_id'=>(int)$line['id']];
                $bags-=$take;if(!$bags)break;
            }
        }
        if(!$bySite)throw new RuntimeException('Indiquez le nombre de sacs à envoyer.');
        $notes=trim((string)($data['notes']??''));$numbers=[];$transfers=new TransferService($this->db);
        foreach($bySite as $site=>$items){
            $transfer=$transfers->create(['transfer_type'=>'inter_site','source_site_id'=>$site,'destination_site_id'=>$destination,'customer_order_id'=>$id,'notes'=>mb_substr('Commande '.$order['order_number'].($notes!==''?' · '.$notes:''),0,500),'items'=>$items],$user);
            $transfers->submit($transfer,$user);
            $numbers[]=(string)$this->query('SELECT transfer_number FROM stock_transfers WHERE id=?',[$transfer])->fetchColumn();
        }
        $this->logActivity('customer_order_transfer','distributions','customer_orders',$id,'Envoi vers le bureau principal pour la commande '.$order['order_number'].' : '.implode(', ',$numbers).'.',null,['transfers'=>$numbers],$user);
        return 'Envoi '.implode(', ',$numbers).' préparé et soumis pour approbation. Il se suit ensuite dans Transferts inter-sites.';
    }

    /** Cancellation releases every reservation: bags at the production site and at the office become free again. */
    public function cancel($id,array $data,array $user): void
    {
        $this->guard('validate');$id=$this->int($id,'Commande');
        $reason=trim((string)($data['reason']??''));if($reason===''||mb_strlen($reason)>255)throw new RuntimeException('Indiquez le motif d’annulation (255 caractères au maximum).');
        $this->db->beginTransaction();
        try{
            $order=$this->lockOrder($id);
            if(!in_array($order['status'],['draft','validated'],true))throw new RuntimeException('Cette commande est déjà soldée ou annulée.');
            if((int)$this->query('SELECT COALESCE(SUM(delivered_bags),0) FROM customer_order_lines WHERE order_id=?',[$id])->fetchColumn()>0)throw new RuntimeException('Des sacs ont déjà été livrés : clôturez la commande au lieu de l’annuler.');
            $flight=$this->query("SELECT transfer_number FROM stock_transfers WHERE customer_order_id=? AND status NOT IN ('draft','submitted','cancelled','closed') LIMIT 1",[$id])->fetchColumn();
            if($flight)throw new RuntimeException('L’envoi '.$flight.' est en cours : terminez sa réception et clôturez-le avant d’annuler la commande.');
            foreach($this->query("SELECT id,status FROM stock_transfers WHERE customer_order_id=? AND status IN ('draft','submitted') FOR UPDATE",[$id])->fetchAll() as $t){
                $this->query("UPDATE stock_transfers SET status='cancelled' WHERE id=?",[$t['id']]);
                $this->query("INSERT INTO stock_transfer_history(transfer_id,old_status,new_status,action,actor_id,reason) VALUES(?,?,'cancelled','cancel_order',?,?)",[$t['id'],$t['status'],$user['id'],'Commande '.$order['order_number'].' annulée : '.$reason]);
            }
            foreach($this->query("SELECT a.* FROM customer_order_allocations a JOIN customer_order_lines l ON l.id=a.order_line_id WHERE l.order_id=? AND a.quantity_bags>0 FOR UPDATE",[$id])->fetchAll() as $alloc){
                $this->query('UPDATE finished_stocks SET reserved_bags=GREATEST(reserved_bags-?,0),reserved_weight_kg=GREATEST(reserved_weight_kg-?,0) WHERE id=?',[$alloc['quantity_bags'],$alloc['quantity_kg'],$alloc['finished_stock_id']]);
                $this->query('UPDATE customer_order_allocations SET quantity_bags=0,quantity_kg=0 WHERE id=?',[$alloc['id']]);
                self::movement($this->db,['id'=>$alloc['order_line_id'],'order_id'=>$id],'release',(int)$alloc['quantity_bags'],(float)$alloc['quantity_kg'],(int)$alloc['finished_stock_id'],null,$user,$alloc['stage']==='office'?'Annulation : sacs remis en stock libre au bureau':'Annulation : sacs libérés au site de production');
            }
            $this->query("UPDATE customer_orders SET status='cancelled',cancelled_by=?,cancelled_at=NOW(),cancel_reason=? WHERE id=?",[$user['id'],$reason,$id]);
            $this->logActivity('customer_order_cancel','distributions','customer_orders',$id,'Annulation de la commande '.$order['order_number'].' : '.$reason.'.',['status'=>$order['status']],['status'=>'cancelled','reason'=>$reason],$user);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** Closes an order partly delivered: the rest is given up and its reservations are released. */
    public function close($id,array $data,array $user): void
    {
        $this->guard('validate');$id=$this->int($id,'Commande');
        $reason=trim((string)($data['reason']??''));if($reason===''||mb_strlen($reason)>255)throw new RuntimeException('Indiquez le motif de clôture (255 caractères au maximum).');
        $this->db->beginTransaction();
        try{
            $order=$this->lockOrder($id);
            if($order['status']!=='validated')throw new RuntimeException('Seule une commande en cours peut être clôturée.');
            $flight=$this->query("SELECT transfer_number FROM stock_transfers WHERE customer_order_id=? AND status NOT IN ('draft','submitted','cancelled','closed') LIMIT 1",[$id])->fetchColumn();
            if($flight)throw new RuntimeException('L’envoi '.$flight.' est en cours : terminez sa réception et clôturez-le avant de clôturer la commande.');
            foreach($this->query("SELECT id,status FROM stock_transfers WHERE customer_order_id=? AND status IN ('draft','submitted') FOR UPDATE",[$id])->fetchAll() as $t){
                $this->query("UPDATE stock_transfers SET status='cancelled' WHERE id=?",[$t['id']]);
                $this->query("INSERT INTO stock_transfer_history(transfer_id,old_status,new_status,action,actor_id,reason) VALUES(?,?,'cancelled','cancel_order',?,?)",[$t['id'],$t['status'],$user['id'],'Commande '.$order['order_number'].' clôturée : '.$reason]);
            }
            foreach($this->query("SELECT a.* FROM customer_order_allocations a JOIN customer_order_lines l ON l.id=a.order_line_id WHERE l.order_id=? AND a.quantity_bags>0 FOR UPDATE",[$id])->fetchAll() as $alloc){
                $this->query('UPDATE finished_stocks SET reserved_bags=GREATEST(reserved_bags-?,0),reserved_weight_kg=GREATEST(reserved_weight_kg-?,0) WHERE id=?',[$alloc['quantity_bags'],$alloc['quantity_kg'],$alloc['finished_stock_id']]);
                $this->query('UPDATE customer_order_allocations SET quantity_bags=0,quantity_kg=0 WHERE id=?',[$alloc['id']]);
                self::movement($this->db,['id'=>$alloc['order_line_id'],'order_id'=>$id],'release',(int)$alloc['quantity_bags'],(float)$alloc['quantity_kg'],(int)$alloc['finished_stock_id'],null,$user,'Clôture : reste non livré libéré');
            }
            $this->query("UPDATE customer_orders SET status='closed',closed_by=?,closed_at=NOW(),close_reason=? WHERE id=?",[$user['id'],$reason,$id]);
            $this->logActivity('customer_order_close','distributions','customer_orders',$id,'Clôture de la commande '.$order['order_number'].' : '.$reason.'.',['status'=>'validated'],['status'=>'closed','reason'=>$reason],$user);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** Progress of order lines in bags, for the deliveries. */
    public static function linesProgress(PDO $db,string $where,array $params,string $order='l.id',bool $lock=false): array
    {
        return $lock?self::lockedProgress($db,$where,$params,$order):self::progress($db,$where,$params,$order);
    }

    // Hooks called inside the transactions of packaging, pelletization and transfers.

    /** New or returned bags at a production site go first to validated orders still waiting, oldest first. */
    public static function allocatePending(PDO $db,int $site,int $product,int $format,?array $user=null): int
    {
        if(!self::installed($db))return 0;
        $total=0;
        foreach(self::lockedProgress($db,"l.source_site_id=? AND l.product_id=? AND l.bag_format_id=? AND o.status='validated'",[$site,$product,$format],'o.validated_at,o.id,l.id') as $line)
            if((int)$line['remaining_bags']>0)$total+=self::reserve($db,$line,(int)$line['remaining_bags'],$user,'Réservation automatique');
        return $total;
    }

    /** Approval of an assigned transfer: the order's reservation becomes the transfer's (no net change on the lot). */
    public static function transferHandover(PDO $db,array $item,int $bags,float $kg,array $user): void
    {
        if(empty($item['customer_order_line_id'])||$bags<=0)return;
        $line=self::line($db,(int)$item['customer_order_line_id']);
        $alloc=self::fetch($db,"SELECT * FROM customer_order_allocations WHERE order_line_id=? AND finished_stock_id=? AND stage='source' FOR UPDATE",[$line['id'],$item['source_finished_stock_id']]);
        if(!$alloc||(int)$alloc['quantity_bags']<$bags)throw new RuntimeException('Les sacs de la commande '.$line['order_number'].' ne sont plus tous réservés sur ce lot : actualisez la commande avant d’approuver l’envoi.');
        self::run($db,'UPDATE customer_order_allocations SET quantity_bags=quantity_bags-?,quantity_kg=GREATEST(quantity_kg-?,0) WHERE id=?',[$bags,$kg,$alloc['id']]);
        self::run($db,'UPDATE finished_stocks SET reserved_bags=reserved_bags-?,reserved_weight_kg=GREATEST(reserved_weight_kg-?,0) WHERE id=?',[$bags,$kg,$item['source_finished_stock_id']]);
        self::movement($db,$line,'transfer_out',$bags,$kg,(int)$item['source_finished_stock_id'],(int)$item['transfer_id'],$user,null);
    }

    /** Bags of an assigned transfer released before shipping (closure, cancellation) are booked again for the order. */
    public static function transferRestore(PDO $db,array $item,int $bags,float $kg,array $user,string $note): void
    {
        if(empty($item['customer_order_line_id'])||$bags<=0)return;
        $line=self::line($db,(int)$item['customer_order_line_id']);if($line['order_status']!=='validated')return;
        $lot=self::fetch($db,'SELECT id,site_id FROM finished_stocks WHERE id=? FOR UPDATE',[$item['source_finished_stock_id']]);
        self::run($db,'UPDATE finished_stocks SET reserved_bags=reserved_bags+?,reserved_weight_kg=reserved_weight_kg+? WHERE id=?',[$bags,$kg,$lot['id']]);
        self::allocate($db,(int)$line['id'],(int)$lot['id'],(int)$lot['site_id'],'source',$bags,$kg);
        self::movement($db,$line,'transfer_back',$bags,$kg,(int)$lot['id'],(int)$item['transfer_id'],$user,$note);
    }

    /** Reception at the office: accepted bags are reserved there for the client; rejected bags go back to the remainder. */
    public static function transferReceived(PDO $db,array $item,?int $officeStock,int $accepted,float $acceptedKg,int $rejected,float $rejectedKg,array $user): void
    {
        if(empty($item['customer_order_line_id']))return;
        $line=self::line($db,(int)$item['customer_order_line_id']);
        if($accepted>0&&$officeStock&&$line['order_status']==='validated'){
            $stock=self::fetch($db,'SELECT id,site_id FROM finished_stocks WHERE id=? FOR UPDATE',[$officeStock]);
            self::run($db,'UPDATE finished_stocks SET reserved_bags=reserved_bags+?,reserved_weight_kg=reserved_weight_kg+? WHERE id=?',[$accepted,$acceptedKg,$officeStock]);
            self::allocate($db,(int)$line['id'],$officeStock,(int)$stock['site_id'],'office',$accepted,$acceptedKg);
            self::movement($db,$line,'office_in',$accepted,$acceptedKg,$officeStock,(int)$item['transfer_id'],$user,null);
        }
        if($rejected>0)self::movement($db,$line,'rejected',$rejected,$rejectedKg,(int)$item['source_finished_stock_id'],(int)$item['transfer_id'],$user,'Retour vers le site de production ; à réserver de nouveau');
    }

    // Progress of each line, in bags.

    private static function progress(PDO $db,string $where,array $params,string $order): array
    {
        $rows=self::rowsOf($db,"SELECT l.*,o.order_number,o.status order_status,o.validated_at,p.code product_code,p.name product_name,bf.name format_name,bf.weight_kg,s.name source_name,
            COALESCE((SELECT SUM(a.quantity_bags) FROM customer_order_allocations a WHERE a.order_line_id=l.id AND a.stage='source'),0) source_bags,
            COALESCE((SELECT SUM(a.quantity_bags) FROM customer_order_allocations a WHERE a.order_line_id=l.id AND a.stage='office'),0) office_bags,
            COALESCE((SELECT SUM(i.requested_bags) FROM stock_transfer_items i JOIN stock_transfers t ON t.id=i.transfer_id WHERE i.customer_order_line_id=l.id AND t.status IN ('draft','submitted')),0) planned_bags,
            COALESCE((SELECT SUM(IF(t.status='closed',0,i.reserved_bags-i.shipped_bags)) FROM stock_transfer_items i JOIN stock_transfers t ON t.id=i.transfer_id WHERE i.customer_order_line_id=l.id AND t.status NOT IN ('draft','submitted','approved','cancelled')),0) awaiting_bags,
            COALESCE((SELECT SUM(i.shipped_bags-i.received_bags) FROM stock_transfer_items i JOIN stock_transfers t ON t.id=i.transfer_id WHERE i.customer_order_line_id=l.id AND t.status<>'cancelled'),0) transit_bags
            FROM customer_order_lines l JOIN customer_orders o ON o.id=l.order_id JOIN products p ON p.id=l.product_id JOIN bag_formats bf ON bf.id=l.bag_format_id JOIN sites s ON s.id=l.source_site_id
            WHERE {$where} ORDER BY {$order}",$params);
        foreach($rows as &$row){
            $committed=(int)$row['source_bags']+(int)$row['office_bags']+(int)$row['awaiting_bags']+(int)$row['transit_bags']+(int)$row['delivered_bags'];
            $row['remaining_bags']=max(0,(int)$row['quantity_bags']-$committed);
        }
        unset($row);
        return $rows;
    }
    /** Same progress, after locking the lines and their orders so that two bookings cannot overlap. */
    private static function lockedProgress(PDO $db,string $where,array $params,string $order='l.id'): array
    {
        self::run($db,"SELECT l.id FROM customer_order_lines l JOIN customer_orders o ON o.id=l.order_id WHERE {$where} FOR UPDATE",$params);
        return self::progress($db,$where,$params,$order);
    }

    /** FIFO booking on the production site lots that still have free bags. */
    private static function reserve(PDO $db,array $line,int $need,?array $user,string $note): int
    {
        if($need<=0)return 0;$done=0;
        foreach(self::rowsOf($db,"SELECT fs.id,fs.site_id,fs.quantity_bags-fs.reserved_bags free_bags,bf.weight_kg FROM finished_stocks fs JOIN bag_formats bf ON bf.id=fs.bag_format_id WHERE fs.site_id=? AND fs.product_id=? AND fs.bag_format_id=? AND fs.deleted_at IS NULL AND fs.status IN ('active','validated') AND fs.quantity_bags>fs.reserved_bags ORDER BY fs.created_at,fs.id FOR UPDATE",[$line['source_site_id'],$line['product_id'],$line['bag_format_id']]) as $lot){
            $take=min($need-$done,(int)$lot['free_bags']);if($take<=0)continue;$kg=round($take*(float)$lot['weight_kg'],3);
            self::run($db,'UPDATE finished_stocks SET reserved_bags=reserved_bags+?,reserved_weight_kg=reserved_weight_kg+? WHERE id=?',[$take,$kg,$lot['id']]);
            self::allocate($db,(int)$line['id'],(int)$lot['id'],(int)$lot['site_id'],'source',$take,$kg);
            self::movement($db,$line,'reserve',$take,$kg,(int)$lot['id'],null,$user,$note);
            $done+=$take;if($done>=$need)break;
        }
        return $done;
    }
    private static function allocate(PDO $db,int $line,int $stock,int $site,string $stage,int $bags,float $kg): void
    {
        self::run($db,'INSERT INTO customer_order_allocations(order_line_id,finished_stock_id,site_id,stage,quantity_bags,quantity_kg) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE quantity_bags=quantity_bags+VALUES(quantity_bags),quantity_kg=quantity_kg+VALUES(quantity_kg)',[$line,$stock,$site,$stage,$bags,$kg]);
    }
    private static function movement(PDO $db,array $line,string $type,int $bags,float $kg,?int $stock,?int $transfer,?array $user,?string $note): void
    {
        self::run($db,'INSERT INTO customer_order_movements(order_id,order_line_id,movement_type,quantity_bags,quantity_kg,finished_stock_id,transfer_id,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?)',[$line['order_id'],$line['id'],$type,$bags,$kg,$stock,$transfer,$note,$user['id']??null]);
    }
    private static function line(PDO $db,int $id): array
    {
        $line=self::fetch($db,'SELECT l.*,o.order_number,o.status order_status FROM customer_order_lines l JOIN customer_orders o ON o.id=l.order_id WHERE l.id=? FOR UPDATE',[$id]);
        if(!$line)throw new RuntimeException('Ligne de commande introuvable.');
        return $line;
    }
    private static function run(PDO $db,string $sql,array $params=[]): PDOStatement { $s=$db->prepare($sql);$s->execute($params);return $s; }
    private static function fetch(PDO $db,string $sql,array $params=[]) { return self::run($db,$sql,$params)->fetch(); }
    private static function rowsOf(PDO $db,string $sql,array $params=[]): array { return self::run($db,$sql,$params)->fetchAll(); }

    private function guard(string $action): void
    {
        if(!$this->ready())throw new RuntimeException('Appliquez la migration 048 pour gérer les commandes clients.');
        Auth::requirePermission('distributions',$action);
    }
    private function lockOrder(int $id): array
    {
        $order=$this->query('SELECT * FROM customer_orders WHERE id=? FOR UPDATE',[$id])->fetch();
        if(!$order)throw new RuntimeException('Commande introuvable.');
        return $order;
    }
    private function nextNumber(): string
    {
        $prefix='BC-'.date('Y').'-';
        $last=$this->query("SELECT order_number FROM customer_orders WHERE order_number LIKE ? ORDER BY order_number DESC LIMIT 1 FOR UPDATE",[$prefix.'%'])->fetchColumn();
        return $prefix.str_pad((string)((int)substr((string)$last,strlen($prefix))+1),4,'0',STR_PAD_LEFT);
    }
    private function int($value,string $label): int
    {
        if(!is_scalar($value)||!ctype_digit((string)$value)||(int)$value<1)throw new RuntimeException($label.' invalide.');
        return (int)$value;
    }
}
