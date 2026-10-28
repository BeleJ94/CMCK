<?php

/** Client records for commercial distribution (047): companies, and regular individuals who want an account. */
class CustomerService extends Model
{
    public const TYPES=['company'=>'Entreprise','individual'=>'Particulier'];
    public const CURRENCIES=['USD','CDF'];
    private const ACTIONS=['customer_create','customer_update','customer_block','customer_unblock'];
    private $ready=null;

    public function __construct(PDO $db=null) { $this->db=$db?:Database::getInstance()->connection(); }

    public function ready(): bool
    {
        if($this->ready===null)$this->ready=(int)$this->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='customers'")->fetchColumn()>0;
        return $this->ready;
    }

    public function directory(): array
    {
        if(!$this->ready())return ['ready'=>false,'customers'=>[],'history'=>[]];
        $customers=$this->query("SELECT c.*,creator.name creator_name,editor.name editor_name,blocker.name blocker_name FROM customers c JOIN users creator ON creator.id=c.created_by LEFT JOIN users editor ON editor.id=c.updated_by LEFT JOIN users blocker ON blocker.id=c.blocked_by WHERE c.deleted_at IS NULL ORDER BY c.status='blocked',c.name")->fetchAll();
        // Orders of each client (048), most recent first.
        $orders=[];
        if((int)$this->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='customer_orders'")->fetchColumn())
            foreach($this->query("SELECT o.id,o.customer_id,o.order_number,o.status,o.created_at,SUM(l.quantity_bags) bags,SUM(l.quantity_bags*l.unit_price_usd) amount_usd FROM customer_orders o JOIN customer_order_lines l ON l.order_id=o.id GROUP BY o.id ORDER BY o.id DESC")->fetchAll() as $order)$orders[(int)$order['customer_id']][]=$order;
        foreach($customers as &$customer){$customer['version']=self::version($customer);$customer['orders']=$orders[(int)$customer['id']]??[];}
        unset($customer);
        $history=$this->query("SELECT a.action,a.description,a.created_at,u.name user_name FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id WHERE a.module='distributions' AND a.action IN ('".implode("','",self::ACTIONS)."') ORDER BY a.id DESC LIMIT 8")->fetchAll();
        return ['ready'=>true,'customers'=>$customers,'history'=>$history];
    }

    public function create(array $data,array $user): int
    {
        $this->guard('create');$values=$this->input($data);
        $this->db->beginTransaction();
        try{
            $this->assertUnique($values,0);
            $values['code']=$this->nextCode();
            $columns=array_keys($values);
            $this->query('INSERT INTO customers('.implode(',',$columns).',created_by) VALUES(:'.implode(',:',$columns).',:created_by)',$values+['created_by'=>$user['id']]);
            $id=(int)$this->db->lastInsertId();
            $this->logActivity('customer_create','distributions','customers',$id,'Création du client '.$values['code'].' · '.$values['name'].'.',null,$values,$user);
            $this->db->commit();return $id;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function update($id,array $data,array $user): void
    {
        $this->guard('update');$values=$this->input($data);$id=$this->id($id);
        $this->db->beginTransaction();
        try{
            $current=$this->lock($id);
            if(!hash_equals(self::version($current),(string)($data['version']??'')))throw new RuntimeException('Cette fiche a été modifiée entre-temps. Actualisez la page puis recommencez.');
            $changed=array_filter($values,static fn($value,$key)=>(string)$value!==(string)$current[$key],ARRAY_FILTER_USE_BOTH);
            if(!$changed)throw new RuntimeException('Aucune modification à enregistrer.');
            $this->assertUnique($values,$id);
            $sets=implode(',',array_map(static fn($key)=>$key.'=:'.$key,array_keys($values)));
            $this->query("UPDATE customers SET {$sets},updated_by=:updated_by WHERE id=:id",$values+['updated_by'=>$user['id'],'id'=>$id]);
            $this->logActivity('customer_update','distributions','customers',$id,'Modification du client '.$current['code'].' · '.$values['name'].'.',array_intersect_key($current,$changed),$changed,$user);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** Blocking keeps the record and its history; a blocked client will not receive new orders. */
    public function setStatus($id,array $data,array $user): void
    {
        $this->guard('validate');$id=$this->id($id);$status=$data['status']??'';
        if(!in_array($status,['active','blocked'],true))throw new RuntimeException('Statut invalide.');
        $reason=$status==='blocked'?trim((string)($data['reason']??'')):null;
        if($status==='blocked'&&($reason===''||mb_strlen($reason)>255))throw new RuntimeException('Indiquez le motif du blocage (255 caractères au maximum).');
        $this->db->beginTransaction();
        try{
            $current=$this->lock($id);
            if($current['status']===$status)throw new RuntimeException($status==='blocked'?'Ce client est déjà bloqué.':'Ce client est déjà actif.');
            if($status==='blocked')$this->query('UPDATE customers SET status=\'blocked\',blocked_reason=?,blocked_at=NOW(),blocked_by=?,updated_by=? WHERE id=?',[$reason,$user['id'],$user['id'],$id]);
            else $this->query('UPDATE customers SET status=\'active\',blocked_reason=NULL,blocked_at=NULL,blocked_by=NULL,updated_by=? WHERE id=?',[$user['id'],$id]);
            $this->logActivity($status==='blocked'?'customer_block':'customer_unblock','distributions','customers',$id,($status==='blocked'?'Blocage':'Déblocage').' du client '.$current['code'].' · '.$current['name'].($reason?' : '.$reason:'').'.',['status'=>$current['status']],['status'=>$status,'reason'=>$reason],$user);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public static function version(array $customer): string
    {
        $keys=['id','name','trade_name','customer_type','rccm','id_nat','nif','contact_name','contact_role','phone','email','address','city','billing_currency','notes','status'];
        return hash('sha256',json_encode(array_map(static fn($key)=>(string)($customer[$key]??''),$keys)));
    }

    private function guard(string $action): void
    {
        if(!$this->ready())throw new RuntimeException('Appliquez la migration 047 pour gérer les clients.');
        Auth::requirePermission('distributions',$action);
    }
    private function input(array $data): array
    {
        $text=static function($key,int $max,string $label,bool $required=false)use($data): ?string{
            $value=$data[$key]??'';if(!is_string($value))throw new RuntimeException($label.' invalide.');
            $value=trim(preg_replace('/\s+/u',' ',$value));
            if($value===''){if($required)throw new RuntimeException($label.' : champ obligatoire.');return null;}
            if(mb_strlen($value)>$max)throw new RuntimeException($label.' : '.$max.' caractères au maximum.');
            return $value;
        };
        $type=$data['customer_type']??'';if(!is_string($type)||!isset(self::TYPES[$type]))throw new RuntimeException('Choisissez le type de client.');
        $currency=$data['billing_currency']??'';if(!in_array($currency,self::CURRENCIES,true))throw new RuntimeException('Choisissez la devise de facturation.');
        $values=['customer_type'=>$type,'name'=>$text('name',180,$type==='company'?'Raison sociale':'Nom du client',true),'trade_name'=>$text('trade_name',180,'Nom commercial'),
            'rccm'=>$text('rccm',100,'RCCM'),'id_nat'=>$text('id_nat',100,'Id. Nat.'),'nif'=>$text('nif',100,'NIF'),
            'contact_name'=>$text('contact_name',150,'Contact'),'contact_role'=>$text('contact_role',100,'Fonction du contact'),'phone'=>$text('phone',50,'Téléphone',true),
            'email'=>$text('email',190,'E-mail'),'address'=>$text('address',255,'Adresse'),'city'=>$text('city',120,'Ville'),'billing_currency'=>$currency,'notes'=>$text('notes',500,'Observations')];
        if(mb_strlen($values['name'])<2)throw new RuntimeException('Le nom du client doit compter au moins 2 caractères.');
        if(!preg_match('/^\+?[0-9 ()\/.-]{6,50}$/D',$values['phone']))throw new RuntimeException('Téléphone : chiffres, espaces et + ( ) - / . uniquement.');
        if($values['email']!==null&&!filter_var($values['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Adresse e-mail invalide.');
        // Legal identifiers only apply to companies; they are stored in capitals to detect duplicates reliably.
        foreach(['rccm','id_nat','nif'] as $key)$values[$key]=$type==='company'&&$values[$key]!==null?mb_strtoupper($values[$key]):null;
        if($type==='individual')$values['trade_name']=null;
        return $values;
    }
    private function assertUnique(array $values,int $exclude): void
    {
        $same=$this->query('SELECT code FROM customers WHERE name=? AND id<>? AND deleted_at IS NULL LIMIT 1',[$values['name'],$exclude])->fetchColumn();
        if($same)throw new RuntimeException('Le client '.$same.' porte déjà ce nom.');
        foreach(['rccm'=>'RCCM','id_nat'=>'Id. Nat.','nif'=>'NIF'] as $key=>$label){
            if($values[$key]===null)continue;
            $same=$this->query("SELECT code FROM customers WHERE {$key}=? AND id<>? LIMIT 1",[$values[$key],$exclude])->fetchColumn();
            if($same)throw new RuntimeException('Ce '.$label.' est déjà enregistré pour le client '.$same.'.');
        }
    }
    private function nextCode(): string
    {
        $last=$this->query("SELECT code FROM customers WHERE code REGEXP '^CLI-[0-9]+$' ORDER BY CAST(SUBSTRING(code,5) AS UNSIGNED) DESC LIMIT 1 FOR UPDATE")->fetchColumn();
        return 'CLI-'.str_pad((string)((int)substr((string)$last,4)+1),4,'0',STR_PAD_LEFT);
    }
    private function lock(int $id): array
    {
        $customer=$this->query('SELECT * FROM customers WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$id])->fetch();
        if(!$customer)throw new RuntimeException('Client introuvable.');
        return $customer;
    }
    private function id($id): int
    {
        if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1)throw new RuntimeException('Client introuvable.');
        return (int)$id;
    }
}
