<?php
require_once __DIR__.'/PorcineFlowService.php';

/** Referential of transformation products: the products each raw material gives (046). */
class ButcheryProductService extends PorcineFlowService
{
    public const KINDS=['finished_product'=>'Produit fini','co_product'=>'Coproduit'];
    private const ACTIONS=['butchery_product_create','butchery_product_update','butchery_material_products'];

    public function ready(): bool { return $this->hasTable('butchery_product_sources'); }

    public function directory(): array
    {
        $site=$this->butcher('read');$ready=$this->ready();
        $materials=$this->query("SELECT i.id,i.code,i.name,(SELECT COALESCE(SUM(l.quantity_kg-l.reserved_kg),0) FROM butchery_raw_lots l WHERE l.item_id=i.id AND l.site_id=? AND l.status IN ('available','reserved') AND l.expiry_date>=CURDATE() AND l.deleted_at IS NULL) stock_kg FROM butchery_items i WHERE i.item_kind='raw_material' AND i.unit='kg' AND i.status='active' AND i.deleted_at IS NULL ORDER BY i.name",[$site])->fetchAll();
        $products=$this->products();
        $sources=$ready?$this->sources():[];
        foreach($products as &$product){$product['sources']=$sources[(int)$product['id']]??[];$product['version']=self::version($product);}
        unset($product);
        foreach($materials as &$material){
            $material['products']=array_values(array_map(static fn($p)=>(int)$p['id'],array_filter($products,static fn($p)=>in_array((int)$material['id'],$p['sources'],true))));
            $material['version']=self::materialVersion($material['products']);
        }
        unset($material);
        $history=$ready?$this->query("SELECT a.action,a.description,a.created_at,u.name user_name FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id WHERE a.module='butchery' AND a.action IN ('".implode("','",self::ACTIONS)."') ORDER BY a.id DESC LIMIT 8")->fetchAll():[];
        return ['ready'=>$ready,'piecesReady'=>$this->piecesReady(),'canAdminister'=>Auth::can('butchery','administer',$site),'materials'=>$materials,'products'=>$products,'history'=>$history];
    }

    public function create(array $data,array $user): int
    {
        $site=$this->admin();
        $name=$this->text($data['name']??null,160,'Nom du produit');$kind=$this->kind($data);$shelf=$this->shelf($data);
        $pieces=$this->piecesReady()&&($data['track_pieces']??'')==='1';
        $sources=$this->materials($data['source_ids']??[]);
        if(!$sources)throw new RuntimeException('Choisissez au moins une matière d’origine.');
        $code=$this->optionalText($data['code']??'',60,'Code');
        if($code!==null){$code=mb_strtoupper($code);if(!preg_match('/^[A-Z0-9][A-Z0-9-]{0,59}$/D',$code))throw new RuntimeException('Code : lettres, chiffres et tirets uniquement.');}
        $this->db->beginTransaction();
        try{
            if($code===null)$code=$this->suggestCode($name);
            elseif($this->row('SELECT id FROM butchery_items WHERE code=? FOR UPDATE',[$code]))throw new RuntimeException('Le code '.$code.' est déjà utilisé par un autre article.');
            if($this->row("SELECT id FROM butchery_items WHERE name=? AND item_kind IN ('finished_product','co_product') AND deleted_at IS NULL",[$name]))throw new RuntimeException('Un produit porte déjà le nom « '.$name.' ».');
            // Products counted in pieces (whole birds) are sold by the piece unless the price list says otherwise.
            if($this->piecesReady())$this->query("INSERT INTO butchery_items(code,name,item_kind,unit,track_pieces,default_shelf_life_days,status) VALUES(?,?,?,?,?,?,'active')",[$code,$name,$kind,$pieces?'piece':'kg',$pieces?1:0,$shelf]);
            else $this->query("INSERT INTO butchery_items(code,name,item_kind,unit,default_shelf_life_days,status) VALUES(?,?,?,'kg',?,'active')",[$code,$name,$kind,$shelf]);
            $id=(int)$this->db->lastInsertId();
            foreach($sources as $raw)$this->query('INSERT INTO butchery_product_sources(product_item_id,raw_item_id,created_by) VALUES(?,?,?)',[$id,$raw,$user['id']]);
            $this->logActivity('butchery_product_create','butchery','butchery_items',$id,'Création du produit '.$code.' · '.$name.', obtenu de '.$this->names($sources).'.',null,['code'=>$code,'name'=>$name,'item_kind'=>$kind,'track_pieces'=>$pieces,'default_shelf_life_days'=>$shelf,'sources'=>$sources],$user,$site);
            $this->db->commit();return $id;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function update($id,array $data,array $user): void
    {
        $site=$this->admin();$id=$this->integer($id);
        $values=['name'=>$this->text($data['name']??null,160,'Nom du produit'),'item_kind'=>$this->kind($data),'default_shelf_life_days'=>$this->shelf($data)];
        $sources=$this->materials($data['source_ids']??[]);
        $this->db->beginTransaction();
        try{
            $current=$this->product($id,true);$current['sources']=$this->sources($id)[$id]??[];
            if(!hash_equals(self::version($current),(string)($data['version']??'')))throw new RuntimeException('Ce produit a été modifié entre-temps. Actualisez la page puis recommencez.');
            if($values['name']!==$current['name']&&$this->row("SELECT id FROM butchery_items WHERE name=? AND id<>? AND item_kind IN ('finished_product','co_product') AND deleted_at IS NULL",[$values['name'],$id]))throw new RuntimeException('Un produit porte déjà le nom « '.$values['name'].' ».');
            $changed=array_filter($values,static fn($value,$key)=>(string)$value!==(string)$current[$key],ARRAY_FILTER_USE_BOTH);
            if(!$changed&&$sources===$current['sources'])throw new RuntimeException('Aucune modification à enregistrer.');
            if($changed)$this->query('UPDATE butchery_items SET name=?,item_kind=?,default_shelf_life_days=? WHERE id=?',[$values['name'],$values['item_kind'],$values['default_shelf_life_days'],$id]);
            $this->link($id,$current['sources'],$sources,$user,true);
            $old=array_intersect_key($current,$changed);$new=$changed;
            if($sources!==$current['sources']){$old['sources']=$current['sources'];$new['sources']=$sources;}
            $this->logActivity('butchery_product_update','butchery','butchery_items',$id,'Modification du produit '.$current['code'].' · '.$values['name'].($sources?', obtenu de '.$this->names($sources):', retiré de la transformation').'.',$old,$new,$user,$site);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** Replaces the products a raw material gives, from the material card. */
    public function saveMaterial($id,array $data,array $user): void
    {
        $site=$this->admin();$id=$this->integer($id);
        $wanted=[];foreach(is_array($data['product_ids']??null)?$data['product_ids']:[] as $product)$wanted[]=$this->integer($product);
        $wanted=array_values(array_unique($wanted));sort($wanted,SORT_NUMERIC);
        $this->db->beginTransaction();
        try{
            $material=$this->row("SELECT id,name FROM butchery_items WHERE id=? AND item_kind='raw_material' AND unit='kg' AND status='active' AND deleted_at IS NULL FOR UPDATE",[$id]);
            if(!$material)throw new RuntimeException('Matière introuvable ou inactive.');
            $current=array_map('intval',$this->query('SELECT product_item_id FROM butchery_product_sources WHERE raw_item_id=? ORDER BY product_item_id FOR UPDATE',[$id])->fetchAll(PDO::FETCH_COLUMN));
            if(!hash_equals(self::materialVersion($current),(string)($data['version']??'')))throw new RuntimeException('Les produits de cette matière ont été modifiés entre-temps. Actualisez la page puis recommencez.');
            foreach($wanted as $product)$this->product($product,false);
            if($wanted===$current)throw new RuntimeException('Aucune modification à enregistrer.');
            $this->link($id,$current,$wanted,$user,false);
            $added=array_values(array_diff($wanted,$current));$removed=array_values(array_diff($current,$wanted));
            $detail=array_filter([$added?'ajout de '.$this->names($added):'',$removed?'retrait de '.$this->names($removed):'']);
            $this->logActivity('butchery_material_products','butchery','butchery_items',$id,'Produits de « '.$material['name'].' » : '.implode(', ',$detail).'.',['products'=>$current],['products'=>$wanted],$user,$site);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public static function version(array $product): string
    {
        return hash('sha256',json_encode([(int)$product['id'],(string)$product['name'],(string)$product['item_kind'],(string)$product['default_shelf_life_days'],array_map('intval',$product['sources'])]));
    }
    public static function materialVersion(array $products): string { sort($products,SORT_NUMERIC);return hash('sha256',json_encode(array_map('intval',$products))); }

    /** Readable code from the product name: « Côtes de porc » gives COTES-DE-PORC, then COTES-DE-PORC-2 if taken. */
    public function suggestCode(string $name): string
    {
        $ascii=strtr($name,['à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ç'=>'c','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ÿ'=>'y','œ'=>'oe','æ'=>'ae','À'=>'A','Â'=>'A','Ç'=>'C','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E','Î'=>'I','Ï'=>'I','Ô'=>'O','Ù'=>'U','Û'=>'U','Œ'=>'OE','Æ'=>'AE']);
        $base=substr(trim(preg_replace('/[^A-Z0-9]+/','-',strtoupper($ascii)),'-'),0,50);$base=rtrim($base,'-')?:'PRODUIT';
        $taken=array_flip($this->query("SELECT code FROM butchery_items WHERE code=? OR code LIKE ? FOR UPDATE",[$base,$base.'-%'])->fetchAll(PDO::FETCH_COLUMN));
        for($n=1,$code=$base;isset($taken[$code]);$code=$base.'-'.(++$n));
        return $code;
    }

    private function admin(): int
    {
        $site=$this->butcher('administer');
        if(!$this->ready())throw new RuntimeException('Appliquez la migration 046 pour paramétrer les produits de transformation.');
        return $site;
    }
    private function products(): array
    {
        $p=$this->piecesReady();$columns=$p?'i.unit,i.track_pieces':"'kg' unit,0 track_pieces";$units=$p?"i.unit IN ('kg','piece') AND i.is_live=0":"i.unit='kg'";
        return $this->query("SELECT i.id,i.code,i.name,i.item_kind,{$columns},i.default_shelf_life_days,(SELECT COUNT(*) FROM butchery_order_outputs o WHERE o.item_id=i.id) outputs FROM butchery_items i WHERE i.item_kind IN ('finished_product','co_product') AND {$units} AND i.status='active' AND i.deleted_at IS NULL ORDER BY i.name")->fetchAll();
    }
    private function product(int $id,bool $lock): array
    {
        $units=$this->piecesReady()?"unit IN ('kg','piece') AND is_live=0":"unit='kg'";
        $product=$this->row("SELECT * FROM butchery_items WHERE id=? AND item_kind IN ('finished_product','co_product') AND {$units} AND status='active' AND deleted_at IS NULL".($lock?' FOR UPDATE':''),[$id]);
        if(!$product)throw new RuntimeException('Produit introuvable ou inactif.');
        return $product;
    }
    private function sources(?int $product=null): array
    {
        $map=[];
        foreach($this->query('SELECT product_item_id,raw_item_id FROM butchery_product_sources'.($product?' WHERE product_item_id=?':'').' ORDER BY product_item_id,raw_item_id'.($product?' FOR UPDATE':''),$product?[$product]:[])->fetchAll() as $row)$map[(int)$row['product_item_id']][]=(int)$row['raw_item_id'];
        return $map;
    }
    /** Product ↔ material links: only the differences are written, the existing ones keep their author. */
    private function link(int $id,array $current,array $wanted,array $user,bool $fromProduct): void
    {
        $pair=static fn($other)=>$fromProduct?[$id,$other]:[$other,$id];
        foreach(array_diff($current,$wanted) as $other)$this->query('DELETE FROM butchery_product_sources WHERE product_item_id=? AND raw_item_id=?',$pair($other));
        foreach(array_diff($wanted,$current) as $other)$this->query('INSERT INTO butchery_product_sources(product_item_id,raw_item_id,created_by) VALUES(?,?,?)',array_merge($pair($other),[$user['id']]));
    }
    private function materials($ids): array
    {
        if(!is_array($ids))throw new RuntimeException('Matières d’origine invalides.');
        $list=[];foreach($ids as $id)$list[]=$this->integer($id);
        $list=array_values(array_unique($list));sort($list,SORT_NUMERIC);
        foreach($list as $id)if(!$this->row("SELECT id FROM butchery_items WHERE id=? AND item_kind='raw_material' AND unit='kg' AND status='active' AND deleted_at IS NULL",[$id]))throw new RuntimeException('Matière d’origine introuvable ou inactive.');
        return $list;
    }
    private function kind(array $data): string
    {
        $kind=$data['item_kind']??'';
        if(!is_string($kind)||!isset(self::KINDS[$kind]))throw new RuntimeException('Choisissez le type de produit.');
        return $kind;
    }
    private function shelf(array $data): ?int
    {
        $value=$data['default_shelf_life_days']??'';
        if($value===''||$value===null)return null;
        if(!is_scalar($value)||!preg_match('/^[0-9]{1,4}$/D',(string)$value)||(int)$value<1||(int)$value>365)throw new RuntimeException('DLC conseillée : de 1 à 365 jours, ou laissez vide.');
        return (int)$value;
    }
    private function names(array $ids): string
    {
        $names=$this->query('SELECT name FROM butchery_items WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY name',$ids)->fetchAll(PDO::FETCH_COLUMN);
        return '« '.implode(' », « ',$names).' »';
    }
}
