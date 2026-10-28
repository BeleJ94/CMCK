<?php

/** Commercial settings (047): depots of the main office, USD/CDF rate of the day and price lists per channel. */
class SalesSettingsService extends Model
{
    public const CHANNELS=['company'=>'Entreprises','counter'=>'Comptoir'];
    /** A rate moving by more than this share against the rate in force must be confirmed. */
    public const RATE_ALERT=0.10;
    private $ready=null;

    public function __construct(PDO $db=null) { $this->db=$db?:Database::getInstance()->connection(); }

    public function ready(): bool
    {
        if($this->ready===null)$this->ready=(int)$this->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('exchange_rates','sale_prices')")->fetchColumn()===2;
        return $this->ready;
    }

    public function directory(): array
    {
        if(!$this->ready())return ['ready'=>false];
        $rates=$this->query("SELECT r.*,u.name author FROM exchange_rates r JOIN users u ON u.id=r.created_by WHERE r.currency_from='USD' AND r.currency_to='CDF' ORDER BY r.effective_at DESC,r.id DESC LIMIT 12")->fetchAll();
        $rows=$this->priceGrid();$history=[];
        foreach($this->query("SELECT p.*,u.name author FROM sale_prices p JOIN users u ON u.id=p.created_by ORDER BY p.effective_at DESC,p.id DESC")->fetchAll() as $price){
            $key=$price['product_id'].'-'.$price['bag_format_id'];
            $history[$key][]=$price;
        }
        foreach($rows as &$row){
            $key=$row['product_id'].'-'.$row['bag_format_id'];$row['key']=$key;$row['history']=array_slice($history[$key]??[],0,8);
            foreach(array_keys(self::CHANNELS) as $channel)$row[$channel]=current(array_filter($history[$key]??[],static fn($p)=>$p['channel']===$channel))?:null;
        }
        unset($row);
        $depots=$this->query("SELECT s.id,s.code,s.name,s.main_office,(SELECT COALESCE(SUM(fs.quantity_bags-fs.reserved_bags),0) FROM finished_stocks fs WHERE fs.site_id=s.id AND fs.deleted_at IS NULL AND fs.status IN ('active','validated')) free_bags,(SELECT COALESCE(SUM(fs.total_weight_kg-fs.reserved_weight_kg),0) FROM finished_stocks fs WHERE fs.site_id=s.id AND fs.deleted_at IS NULL AND fs.status IN ('active','validated')) free_kg FROM sites s JOIN site_types t ON t.id=s.site_type_id WHERE t.code='DEPOT' AND s.status='active' AND s.deleted_at IS NULL ORDER BY s.name")->fetchAll();
        return ['ready'=>true,'rate'=>$rates[0]??null,'rates'=>$rates,'prices'=>$rows,'depots'=>$depots,
            'canConfigure'=>Auth::can('distributions','validate'),'canAdminister'=>Auth::can('distributions','administer')];
    }

    /** Rate in force: 1 USD = rate CDF, or null before the first entry. */
    public function currentRate(): ?float
    {
        if(!$this->ready())return null;
        $rate=$this->query("SELECT rate FROM exchange_rates WHERE currency_from='USD' AND currency_to='CDF' AND effective_at<=NOW() ORDER BY effective_at DESC,id DESC LIMIT 1")->fetchColumn();
        return $rate===false?null:(float)$rate;
    }

    public function setRate(array $data,array $user): void
    {
        $this->guard('validate');
        $value=$data['rate']??'';
        if(!is_scalar($value)||!preg_match('/^\d{1,8}(?:[.,]\d{1,4})?$/D',trim((string)$value)))throw new RuntimeException('Saisissez le taux : 1 USD = combien de CDF, avec au plus 4 décimales.');
        $rate=round((float)str_replace(',','.',trim((string)$value)),4);
        if($rate<=0)throw new RuntimeException('Le taux doit être supérieur à zéro.');
        $notes=trim((string)($data['notes']??''));if(mb_strlen($notes)>255)throw new RuntimeException('Observation : 255 caractères au maximum.');
        $this->db->beginTransaction();
        try{
            $previous=$this->query("SELECT rate FROM exchange_rates WHERE currency_from='USD' AND currency_to='CDF' ORDER BY effective_at DESC,id DESC LIMIT 1 FOR UPDATE")->fetchColumn();
            if($previous!==false&&abs($rate-(float)$previous)<0.00005)throw new RuntimeException('Ce taux est déjà en vigueur.');
            if($previous!==false&&abs($rate/(float)$previous-1)>self::RATE_ALERT&&($data['confirm_variation']??'')!=='1')
                throw new RuntimeException('Le taux varie de '.number_format(($rate/(float)$previous-1)*100,1,',',' ').' % par rapport au taux en vigueur ('.number_format((float)$previous,2,',',' ').' CDF). Confirmez la variation pour l’enregistrer.');
            $this->query("INSERT INTO exchange_rates(currency_from,currency_to,rate,effective_at,notes,created_by) VALUES('USD','CDF',?,NOW(),?,?)",[$rate,$notes===''?null:$notes,$user['id']]);
            $this->logActivity('exchange_rate_set','distributions','exchange_rates',(int)$this->db->lastInsertId(),'Taux du jour : 1 USD = '.number_format($rate,2,',',' ').' CDF.',$previous===false?null:['rate'=>(float)$previous],['rate'=>$rate,'notes'=>$notes],$user);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** Prices of one product and bag format; a blank channel keeps its current price. */
    public function setPrices(array $data,array $user): void
    {
        $this->guard('validate');
        $product=$this->id($data['product_id']??'','Produit');$format=$this->id($data['bag_format_id']??'','Format');
        $row=current(array_filter($this->priceGrid(),static fn($r)=>(int)$r['product_id']===$product&&(int)$r['bag_format_id']===$format));
        if(!$row)throw new RuntimeException('Ce format n’est pas vendu pour ce produit.');
        $wanted=[];
        foreach(self::CHANNELS as $channel=>$label){
            $value=trim((string)($data['price_'.$channel]??''));if($value==='')continue;
            if(!preg_match('/^\d{1,9}(?:[.,]\d{1,2})?$/D',$value))throw new RuntimeException('Tarif '.mb_strtolower($label).' : montant en USD, avec au plus 2 décimales.');
            $price=round((float)str_replace(',','.',$value),2);if($price<=0)throw new RuntimeException('Tarif '.mb_strtolower($label).' : le montant doit être supérieur à zéro.');
            $wanted[$channel]=$price;
        }
        if(!$wanted)throw new RuntimeException('Saisissez au moins un tarif.');
        $this->db->beginTransaction();
        try{
            $changed=[];$old=[];
            foreach($wanted as $channel=>$price){
                $current=$this->query('SELECT price_usd FROM sale_prices WHERE product_id=? AND bag_format_id=? AND channel=? ORDER BY effective_at DESC,id DESC LIMIT 1 FOR UPDATE',[$product,$format,$channel])->fetchColumn();
                if($current!==false&&abs((float)$current-$price)<0.005)continue;
                $this->query('INSERT INTO sale_prices(product_id,bag_format_id,channel,price_usd,effective_at,created_by) VALUES(?,?,?,?,NOW(),?)',[$product,$format,$channel,$price,$user['id']]);
                $changed[$channel]=$price;$old[$channel]=$current===false?null:(float)$current;
            }
            if(!$changed)throw new RuntimeException('Ces tarifs sont déjà en vigueur.');
            $labels=implode(', ',array_map(static fn($channel,$price)=>mb_strtolower(self::CHANNELS[$channel]).' '.number_format($price,2,',',' ').' USD',array_keys($changed),$changed));
            $this->logActivity('sale_price_set','distributions','sale_prices',$product,'Tarif '.$row['product_name'].' · '.$row['format_name'].' : '.$labels.'.',$old,$changed,$user);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** Depots that make up the main office: they will hold the clients' stock and the counter. */
    public function setMainOffice(array $data,array $user): void
    {
        $this->guard('administer');
        $ids=$data['site_ids']??[];if(!is_array($ids)||!$ids)throw new RuntimeException('Choisissez au moins un dépôt pour le bureau principal.');
        $ids=array_values(array_unique(array_map(fn($id)=>$this->id($id,'Dépôt'),$ids)));sort($ids,SORT_NUMERIC);
        $this->db->beginTransaction();
        try{
            $depots=$this->query("SELECT s.id,s.name,s.main_office FROM sites s JOIN site_types t ON t.id=s.site_type_id WHERE t.code='DEPOT' AND s.status='active' AND s.deleted_at IS NULL FOR UPDATE")->fetchAll();
            $known=array_map('intval',array_column($depots,'id'));
            foreach($ids as $id)if(!in_array($id,$known,true))throw new RuntimeException('Dépôt introuvable ou inactif.');
            $current=array_map('intval',array_column(array_filter($depots,static fn($d)=>(int)$d['main_office']===1),'id'));sort($current,SORT_NUMERIC);
            if($current===$ids)throw new RuntimeException('Aucune modification à enregistrer.');
            $this->query('UPDATE sites s JOIN site_types t ON t.id=s.site_type_id SET s.main_office=0 WHERE t.code=\'DEPOT\'');
            $this->query('UPDATE sites SET main_office=1 WHERE id IN ('.implode(',',$ids).')');
            $names=array_column(array_filter($depots,static fn($d)=>in_array((int)$d['id'],$ids,true)),'name');
            $this->logActivity('main_office_set','distributions','sites',null,'Bureau principal : '.implode(', ',$names).'.',['site_ids'=>$current],['site_ids'=>$ids],$user);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** Pairs on sale with their current price for a channel (null when not set yet). */
    public function catalog(string $channel): array
    {
        if(!$this->ready()||!isset(self::CHANNELS[$channel]))return [];
        $rows=$this->priceGrid();
        foreach($rows as &$row){$price=$this->query('SELECT price_usd FROM sale_prices WHERE product_id=? AND bag_format_id=? AND channel=? AND effective_at<=NOW() ORDER BY effective_at DESC,id DESC LIMIT 1',[$row['product_id'],$row['bag_format_id'],$channel])->fetchColumn();$row['price_usd']=$price===false?null:(float)$price;}
        unset($row);
        return $rows;
    }

    /** Active depots of the main office, which receive the clients' goods. */
    public function mainOfficeDepots(): array
    {
        return $this->query("SELECT s.id,s.code,s.name FROM sites s JOIN site_types t ON t.id=s.site_type_id WHERE t.code='DEPOT' AND s.main_office=1 AND s.status='active' AND s.deleted_at IS NULL ORDER BY s.name")->fetchAll();
    }

    /** Product and bag format pairs on sale: formats meant for the product (empty packaging) or already packed. */
    private function priceGrid(): array
    {
        return $this->query("SELECT p.id product_id,p.code product_code,p.name product_name,bf.id bag_format_id,bf.name format_name,bf.weight_kg FROM products p JOIN bag_formats bf
            WHERE p.category='finished_product' AND p.status='active' AND p.deleted_at IS NULL AND bf.status='active' AND bf.deleted_at IS NULL
            AND (EXISTS(SELECT 1 FROM empty_packaging_items e WHERE e.bag_format_id=bf.id AND e.target_product_code=p.code AND e.deleted_at IS NULL)
              OR EXISTS(SELECT 1 FROM finished_stocks fs WHERE fs.product_id=p.id AND fs.bag_format_id=bf.id AND fs.deleted_at IS NULL))
            ORDER BY p.name,bf.weight_kg,bf.name")->fetchAll();
    }
    private function guard(string $action): void
    {
        if(!$this->ready())throw new RuntimeException('Appliquez la migration 047 pour paramétrer les tarifs et le taux.');
        Auth::requirePermission('distributions',$action);
    }
    private function id($value,string $label): int
    {
        if(!is_scalar($value)||!ctype_digit((string)$value)||(int)$value<1)throw new RuntimeException($label.' invalide.');
        return (int)$value;
    }
}
