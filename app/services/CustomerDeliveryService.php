<?php
require_once __DIR__.'/CustomerOrderService.php';
require_once __DIR__.'/DocumentService.php';

/**
 * Deliveries of client orders (049): bags reserved for an order are handed to the client, from a depot of the main office
 * or straight from the production site. A delivery takes the bags out of stock; the order is settled once everything is delivered.
 */
class CustomerDeliveryService extends Model
{
    public const MODES=['pickup'=>'Enlèvement par le client','company_truck'=>'Camion de l’entreprise'];
    public const STAGES=['office'=>'Bureau principal','source'=>'Site de production'];
    /** Marks an order settled by its last delivery, so that cancelling that delivery reopens it. */
    public const FULLY_DELIVERED='Entièrement livrée';
    private $ready=null;

    public function __construct(PDO $db=null) { $this->db=$db?:Database::getInstance()->connection(); }

    public function ready(): bool
    {
        if($this->ready===null)$this->ready=(int)$this->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='customer_deliveries'")->fetchColumn()>0;
        return $this->ready;
    }

    /** Orders to deliver with what is ready at each site, and the history of deliveries. */
    public function directory(): array
    {
        if(!$this->ready())return ['ready'=>false,'orders'=>[],'deliveries'=>[]];
        $orders=$this->query("SELECT o.*,c.code customer_code,c.name customer_name,c.phone customer_phone,c.status customer_status,c.blocked_reason,d.name destination_name FROM customer_orders o JOIN customers c ON c.id=o.customer_id JOIN sites d ON d.id=o.destination_site_id WHERE o.status='validated' ORDER BY o.validated_at,o.id")->fetchAll();
        $lines=[];foreach(CustomerOrderService::linesProgress($this->db,"o.status='validated'",[]) as $line)$lines[(int)$line['order_id']][]=$line;
        $ready=$this->readyBags();
        foreach($orders as &$order){
            $id=(int)$order['id'];$order['lines']=$lines[$id]??[];$order['options']=[];
            foreach($order['lines'] as &$line){$line['to_deliver']=max(0,(int)$line['quantity_bags']-(int)$line['delivered_bags']);}
            unset($line);
            foreach($ready[$id]??[] as $key=>$option){$option['bags']=array_sum($option['lines']);if($option['bags']>0&&Auth::canAccessSite($option['site_id']))$order['options'][$key]=$option;}
            uasort($order['options'],static fn($a,$b)=>[$a['stage']!=='office',$a['site_name']]<=>[$b['stage']!=='office',$b['site_name']]);
            foreach(['quantity_bags','delivered_bags','source_bags','office_bags','transit_bags','remaining_bags','to_deliver'] as $key)$order[$key]=array_sum(array_map(static fn($l)=>(int)$l[$key],$order['lines']));
            $order['ready_bags']=array_sum(array_column($order['options'],'bags'));
            $order['amount_usd']=round(array_sum(array_map(static fn($l)=>(int)$l['quantity_bags']*(float)$l['unit_price_usd'],$order['lines'])),2);
        }
        unset($order);
        usort($orders,static fn($a,$b)=>[$b['ready_bags']>0,$a['validated_at']]<=>[$a['ready_bags']>0,$b['validated_at']]);
        $deliveries=$this->query("SELECT d.*,c.code customer_code,c.name customer_name,o.order_number,o.status order_status,o.close_reason order_close_reason,o.currency,o.exchange_rate,s.name site_name,u.name author,canceller.name canceller_name,
                (SELECT doc.document_number FROM documents doc JOIN document_types dt ON dt.id=doc.document_type_id WHERE dt.code='BL' AND doc.entity_type='customer_deliveries' AND doc.entity_id=d.id AND doc.deleted_at IS NULL LIMIT 1) document_number,
                COALESCE(SUM(dl.quantity_bags),0) bags,COALESCE(SUM(dl.quantity_kg),0) kg,COALESCE(SUM(dl.quantity_bags*dl.unit_price_usd),0) amount_usd
            FROM customer_deliveries d JOIN customers c ON c.id=d.customer_id JOIN customer_orders o ON o.id=d.order_id JOIN sites s ON s.id=d.site_id JOIN users u ON u.id=d.created_by
            LEFT JOIN users canceller ON canceller.id=d.cancelled_by LEFT JOIN customer_delivery_lines dl ON dl.delivery_id=d.id
            GROUP BY d.id ORDER BY d.delivered_at DESC,d.id DESC LIMIT 200")->fetchAll();
        $deliveries=array_values(array_filter($deliveries,static fn($d)=>Auth::canAccessSite((int)$d['site_id'])));
        $details=[];
        foreach($this->query("SELECT dl.delivery_id,p.name product_name,bf.name format_name,SUM(dl.quantity_bags) bags,SUM(dl.quantity_kg) kg FROM customer_delivery_lines dl JOIN customer_order_lines l ON l.id=dl.order_line_id JOIN products p ON p.id=l.product_id JOIN bag_formats bf ON bf.id=l.bag_format_id GROUP BY dl.delivery_id,l.id ORDER BY l.id")->fetchAll() as $row)$details[(int)$row['delivery_id']][]=$row;
        foreach($deliveries as &$delivery)$delivery['lines']=$details[(int)$delivery['id']]??[];
        unset($delivery);
        return ['ready'=>true,'orders'=>$orders,'deliveries'=>$deliveries,'canDeliver'=>Auth::can('distributions','create'),'canCancel'=>Auth::can('distributions','validate')];
    }

    /** @return array{id:int,number:string,message:string} */
    public function deliver(array $data,array $user): array
    {
        $this->guard('create');
        $orderId=$this->int($data['order_id']??'','Commande');
        if(!preg_match('/^(office|source)-([0-9]+)$/D',(string)($data['source']??''),$m))throw new RuntimeException('Choisissez d’où partent les sacs.');
        [$stage,$site]=[$m[1],(int)$m[2]];Auth::requireSiteAccess($site);
        $mode=(string)($data['delivery_mode']??'');if(!isset(self::MODES[$mode]))throw new RuntimeException('Choisissez le mode de livraison.');
        $text=static function($key,int $max,string $label,bool $required=false)use($data): ?string{$value=trim(preg_replace('/\s+/u',' ',(string)($data[$key]??'')));if($value===''){if($required)throw new RuntimeException($label.' : champ obligatoire.');return null;}if(mb_strlen($value)>$max)throw new RuntimeException($label.' : '.$max.' caractères au maximum.');return $value;};
        $truck=$mode==='company_truck';
        $vehicle=$text('vehicle_reference',60,'Véhicule',$truck);$driver=$text('driver_name',150,'Chauffeur',$truck);
        $receiver=$text('receiver_name',150,'Réceptionnaire',true);$phone=$text('receiver_phone',50,'Téléphone du réceptionnaire');$notes=$text('notes',500,'Observations');
        if($phone!==null&&!preg_match('/^\+?[0-9 ()\/.-]{6,50}$/D',$phone))throw new RuntimeException('Téléphone du réceptionnaire invalide.');
        $at=DateTimeImmutable::createFromFormat('Y-m-d\TH:i',(string)($data['delivered_at']??''));
        if(!$at)throw new RuntimeException('Date de livraison invalide.');
        if($at>new DateTimeImmutable('+10 minutes'))throw new RuntimeException('La date de livraison ne peut pas être future.');
        $wanted=is_array($data['bags']??null)?$data['bags']:[];
        $this->db->beginTransaction();
        try{
            $order=$this->query("SELECT o.*,c.code customer_code,c.name customer_name,c.status customer_status,c.blocked_reason FROM customer_orders o JOIN customers c ON c.id=o.customer_id WHERE o.id=? FOR UPDATE",[$orderId])->fetch();
            if(!$order||$order['status']!=='validated')throw new RuntimeException('Seule une commande en cours peut être livrée.');
            if($order['customer_status']!=='active')throw new RuntimeException('Le client '.$order['customer_code'].' est bloqué'.($order['blocked_reason']?' : '.$order['blocked_reason']:'').'. Débloquez sa fiche avant de livrer.');
            if($at->format('Y-m-d H:i:s')<substr((string)$order['validated_at'],0,16).':00')throw new RuntimeException('La livraison ne peut pas précéder la validation de la commande.');
            $lines=CustomerOrderService::linesProgress($this->db,'l.order_id=?',[$orderId],'l.id',true);
            $number=$this->nextNumber();
            $this->query('INSERT INTO customer_deliveries(delivery_number,order_id,customer_id,site_id,source_stage,delivery_mode,vehicle_reference,driver_name,receiver_name,receiver_phone,delivered_at,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$number,$orderId,$order['customer_id'],$site,$stage,$mode,$vehicle,$driver,$receiver,$phone,$at->format('Y-m-d H:i:s'),$notes,$user['id']]);
            $delivery=(int)$this->db->lastInsertId();$total=0;$kgTotal=0.0;
            foreach($lines as $line){
                $bags=$wanted[$line['id']]??'';if($bags===''||$bags==='0')continue;
                if(!is_scalar($bags)||!ctype_digit((string)$bags))throw new RuntimeException('Nombre de sacs invalide.');$bags=(int)$bags;
                $toDeliver=(int)$line['quantity_bags']-(int)$line['delivered_bags'];
                if($bags>$toDeliver)throw new RuntimeException('« '.$line['format_name'].' » : il reste '.$toDeliver.' sac(s) à livrer sur cette commande.');
                $left=$bags;
                foreach($this->lots((int)$line['id'],$stage,$site,true) as $lot){
                    $take=min($left,(int)$lot['free_bags']);if($take<=0)continue;
                    $stock=$this->query('SELECT fs.*,bf.weight_kg FROM finished_stocks fs JOIN bag_formats bf ON bf.id=fs.bag_format_id WHERE fs.id=? FOR UPDATE',[$lot['finished_stock_id']])->fetch();
                    if((int)$stock['quantity_bags']<$take||(int)$stock['reserved_bags']<$take)throw new RuntimeException('Stock incohérent sur le lot #'.$stock['id'].' : actualisez la page.');
                    $kg=round($take*(float)$stock['weight_kg'],3);
                    $this->query("UPDATE finished_stocks SET quantity_bags=quantity_bags-?,total_weight_kg=GREATEST(total_weight_kg-?,0),reserved_bags=reserved_bags-?,reserved_weight_kg=GREATEST(reserved_weight_kg-?,0),status=IF(quantity_bags<=0,'inactive',status) WHERE id=?",[$take,$kg,$take,$kg,$stock['id']]);
                    $this->query('UPDATE customer_order_allocations SET quantity_bags=quantity_bags-?,quantity_kg=GREATEST(quantity_kg-?,0) WHERE id=?',[$take,$kg,$lot['id']]);
                    $this->query('INSERT INTO customer_delivery_lines(delivery_id,order_line_id,finished_stock_id,quantity_bags,quantity_kg,unit_price_usd) VALUES(?,?,?,?,?,?)',[$delivery,$line['id'],$stock['id'],$take,$kg,$line['unit_price_usd']]);
                    $this->stockMovement($stock,'out',$take,$kg,$delivery,$at,$user);
                    $this->orderMovement($orderId,(int)$line['id'],'delivered',$take,$kg,(int)$stock['id'],$user,'Bon '.$number);
                    $left-=$take;if(!$left)break;
                }
                if($left>0)throw new RuntimeException('« '.$line['format_name'].' » : seulement '.($bags-$left).' sac(s) prêt(s) à cet endroit pour cette commande.');
                $this->query('UPDATE customer_order_lines SET delivered_bags=delivered_bags+? WHERE id=?',[$bags,$line['id']]);
                $total+=$bags;$kgTotal+=round($bags*(float)$line['weight_kg'],3);
            }
            if(!$total)throw new RuntimeException('Indiquez le nombre de sacs à livrer.');
            (new DocumentService($this->db))->register('BL',$site,'customer_deliveries',$delivery,'validated',$at->format('Y-m-d H:i:s'),$user);
            $settled=(int)$this->query('SELECT COALESCE(SUM(quantity_bags-delivered_bags),0) FROM customer_order_lines WHERE order_id=?',[$orderId])->fetchColumn()===0;
            if($settled)$this->query("UPDATE customer_orders SET status='closed',closed_by=?,closed_at=NOW(),close_reason=? WHERE id=?",[$user['id'],self::FULLY_DELIVERED,$orderId]);
            $this->logActivity('customer_delivery','distributions','customer_deliveries',$delivery,'Livraison '.$number.' de la commande '.$order['order_number'].' à '.$order['customer_name'].' : '.$total.' sac(s).',null,['order'=>$order['order_number'],'bags'=>$total,'site_id'=>$site,'stage'=>$stage,'mode'=>$mode,'receiver'=>$receiver],$user);
            $this->db->commit();
            return ['id'=>$delivery,'number'=>$number,'message'=>'Livraison '.$number.' enregistrée : '.$total.' sac(s) remis à '.$order['customer_name'].'.'.($settled?' La commande '.$order['order_number'].' est soldée.':'')];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** Cancelling a delivery puts its bags back in stock, reserved again for the order. */
    public function cancel($id,array $data,array $user): void
    {
        $this->guard('validate');$id=$this->int($id,'Livraison');
        $reason=trim((string)($data['reason']??''));if($reason===''||mb_strlen($reason)>255)throw new RuntimeException('Indiquez le motif d’annulation (255 caractères au maximum).');
        $this->db->beginTransaction();
        try{
            $delivery=$this->query('SELECT * FROM customer_deliveries WHERE id=? FOR UPDATE',[$id])->fetch();
            if(!$delivery||$delivery['status']!=='validated')throw new RuntimeException('Livraison introuvable ou déjà annulée.');
            Auth::requireSiteAccess((int)$delivery['site_id']);
            $order=$this->query('SELECT * FROM customer_orders WHERE id=? FOR UPDATE',[$delivery['order_id']])->fetch();
            if($order['status']==='cancelled'||($order['status']==='closed'&&$order['close_reason']!==self::FULLY_DELIVERED))throw new RuntimeException('La commande '.$order['order_number'].' est clôturée : cette livraison ne peut plus être annulée.');
            if($order['status']==='closed')$this->query("UPDATE customer_orders SET status='validated',closed_by=NULL,closed_at=NULL,close_reason=NULL WHERE id=?",[$order['id']]);
            $at=new DateTimeImmutable();
            foreach($this->query('SELECT * FROM customer_delivery_lines WHERE delivery_id=? FOR UPDATE',[$id])->fetchAll() as $line){
                $stock=$this->query('SELECT * FROM finished_stocks WHERE id=? FOR UPDATE',[$line['finished_stock_id']])->fetch();
                $this->query("UPDATE finished_stocks SET quantity_bags=quantity_bags+?,total_weight_kg=total_weight_kg+?,reserved_bags=reserved_bags+?,reserved_weight_kg=reserved_weight_kg+?,status='active' WHERE id=?",[$line['quantity_bags'],$line['quantity_kg'],$line['quantity_bags'],$line['quantity_kg'],$stock['id']]);
                $this->query('INSERT INTO customer_order_allocations(order_line_id,finished_stock_id,site_id,stage,quantity_bags,quantity_kg) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE quantity_bags=quantity_bags+VALUES(quantity_bags),quantity_kg=quantity_kg+VALUES(quantity_kg)',[$line['order_line_id'],$stock['id'],$stock['site_id'],$delivery['source_stage'],$line['quantity_bags'],$line['quantity_kg']]);
                $this->query('UPDATE customer_order_lines SET delivered_bags=GREATEST(delivered_bags-?,0) WHERE id=?',[$line['quantity_bags'],$line['order_line_id']]);
                $this->stockMovement($stock,'adjustment',(int)$line['quantity_bags'],(float)$line['quantity_kg'],$id,$at,$user,true);
                $this->orderMovement((int)$order['id'],(int)$line['order_line_id'],'delivery_cancelled',(int)$line['quantity_bags'],(float)$line['quantity_kg'],(int)$stock['id'],$user,$reason);
            }
            $this->query("UPDATE customer_deliveries SET status='cancelled',cancelled_by=?,cancelled_at=NOW(),cancel_reason=? WHERE id=?",[$user['id'],$reason,$id]);
            $this->query("UPDATE documents SET status='cancelled',cancelled_by=?,cancelled_at=NOW() WHERE entity_type='customer_deliveries' AND entity_id=? AND deleted_at IS NULL",[$user['id'],$id]);
            $this->logActivity('customer_delivery_cancel','distributions','customer_deliveries',$id,'Annulation de la livraison '.$delivery['delivery_number'].' : '.$reason.'.',['status'=>'validated'],['status'=>'cancelled','reason'=>$reason],$user);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** One delivery with its lines, for the printed note. */
    public function find($id): ?array
    {
        if(!$this->ready()||!is_scalar($id)||!ctype_digit((string)$id))return null;
        $delivery=$this->query("SELECT d.*,c.code customer_code,c.name customer_name,c.phone customer_phone,c.address customer_address,c.city customer_city,o.order_number,o.currency,o.exchange_rate,s.name site_name,s.code site_code,u.name author,
                (SELECT doc.document_number FROM documents doc JOIN document_types dt ON dt.id=doc.document_type_id WHERE dt.code='BL' AND doc.entity_type='customer_deliveries' AND doc.entity_id=d.id AND doc.deleted_at IS NULL LIMIT 1) document_number
            FROM customer_deliveries d JOIN customers c ON c.id=d.customer_id JOIN customer_orders o ON o.id=d.order_id JOIN sites s ON s.id=d.site_id JOIN users u ON u.id=d.created_by WHERE d.id=?",[(int)$id])->fetch();
        if(!$delivery||!Auth::canAccessSite((int)$delivery['site_id']))return null;
        $delivery['lines']=$this->query('SELECT p.name product_name,bf.name format_name,dl.unit_price_usd,SUM(dl.quantity_bags) bags,SUM(dl.quantity_kg) kg FROM customer_delivery_lines dl JOIN customer_order_lines l ON l.id=dl.order_line_id JOIN products p ON p.id=l.product_id JOIN bag_formats bf ON bf.id=l.bag_format_id WHERE dl.delivery_id=? GROUP BY l.id ORDER BY l.id',[(int)$id])->fetchAll();
        return $delivery;
    }

    /** Bags ready per order, site and line: reserved at the office, or at the production site and not planned in a transfer. */
    private function readyBags(): array
    {
        $ready=[];
        foreach($this->query("SELECT l.order_id,a.order_line_id,a.stage,a.site_id,s.name site_name,SUM(a.quantity_bags-IF(a.stage='source',COALESCE((SELECT SUM(i.requested_bags) FROM stock_transfer_items i JOIN stock_transfers t ON t.id=i.transfer_id WHERE i.customer_order_line_id=a.order_line_id AND i.source_finished_stock_id=a.finished_stock_id AND t.status IN ('draft','submitted')),0),0)) bags
            FROM customer_order_allocations a JOIN customer_order_lines l ON l.id=a.order_line_id JOIN customer_orders o ON o.id=l.order_id JOIN sites s ON s.id=a.site_id
            WHERE o.status='validated' AND a.quantity_bags>0 GROUP BY l.order_id,a.order_line_id,a.stage,a.site_id")->fetchAll() as $row){
            if((int)$row['bags']<=0)continue;$key=$row['stage'].'-'.$row['site_id'];
            $ready[(int)$row['order_id']][$key]['stage']=$row['stage'];$ready[(int)$row['order_id']][$key]['site_id']=(int)$row['site_id'];$ready[(int)$row['order_id']][$key]['site_name']=$row['site_name'];
            $ready[(int)$row['order_id']][$key]['lines'][(int)$row['order_line_id']]=(int)$row['bags'];
        }
        return $ready;
    }
    /** Allocations of a line at a site, oldest first; at the production site, without the bags planned in a transfer. */
    private function lots(int $line,string $stage,int $site,bool $lock): array
    {
        return $this->query("SELECT a.*,a.quantity_bags-IF(a.stage='source',COALESCE((SELECT SUM(i.requested_bags) FROM stock_transfer_items i JOIN stock_transfers t ON t.id=i.transfer_id WHERE i.customer_order_line_id=a.order_line_id AND i.source_finished_stock_id=a.finished_stock_id AND t.status IN ('draft','submitted')),0),0) free_bags
            FROM customer_order_allocations a WHERE a.order_line_id=? AND a.stage=? AND a.site_id=? AND a.quantity_bags>0 ORDER BY a.id".($lock?' FOR UPDATE':''),[$line,$stage,$site])->fetchAll();
    }
    private function stockMovement(array $stock,string $type,int $bags,float $kg,int $delivery,DateTimeImmutable $at,array $user,bool $in=false): void
    {
        $before=$this->query('SELECT stock_after_kg FROM stock_movements WHERE product_id=? AND site_id=? AND deleted_at IS NULL ORDER BY movement_at DESC,id DESC LIMIT 1',[$stock['product_id'],$stock['site_id']])->fetchColumn();
        $before=$before===false?0.0:(float)$before;$after=max(0,$in?$before+$kg:$before-$kg);
        $this->query("INSERT INTO stock_movements(site_id,product_id,finished_stock_id,customer_delivery_id,movement_type,quantity_bags,quantity_kg,stock_before_kg,stock_after_kg,movement_at,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,'validated',?)",
            [$stock['site_id'],$stock['product_id'],$stock['id'],$delivery,$type,$bags,$kg,$before,$after,$at->format('Y-m-d H:i:s'),$user['id']]);
    }
    private function orderMovement(int $order,int $line,string $type,int $bags,float $kg,int $stock,array $user,?string $note): void
    {
        $this->query('INSERT INTO customer_order_movements(order_id,order_line_id,movement_type,quantity_bags,quantity_kg,finished_stock_id,notes,created_by) VALUES(?,?,?,?,?,?,?,?)',[$order,$line,$type,$bags,$kg,$stock,$note,$user['id']]);
    }
    private function nextNumber(): string
    {
        $prefix='BL-'.date('Y').'-';
        $last=$this->query('SELECT delivery_number FROM customer_deliveries WHERE delivery_number LIKE ? ORDER BY delivery_number DESC LIMIT 1 FOR UPDATE',[$prefix.'%'])->fetchColumn();
        return $prefix.str_pad((string)((int)substr((string)$last,strlen($prefix))+1),4,'0',STR_PAD_LEFT);
    }
    private function guard(string $action): void
    {
        if(!$this->ready())throw new RuntimeException('Appliquez la migration 049 pour livrer les commandes.');
        Auth::requirePermission('distributions',$action);
    }
    private function int($value,string $label): int
    {
        if(!is_scalar($value)||!ctype_digit((string)$value)||(int)$value<1)throw new RuntimeException($label.' introuvable.');
        return (int)$value;
    }
}
