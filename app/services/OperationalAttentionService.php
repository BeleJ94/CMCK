<?php

/** Read-only, permission-scoped source for operational attention counts. */
class OperationalAttentionService
{
    private $db;
    private static $cache=[];
    private static $tables=[];

    public function __construct(PDO $db=null) { $this->db=$db?:Database::getInstance()->connection(); }

    public function items(): array
    {
        $user=Auth::user();if(!$user)return [];
        $selected=Auth::currentSiteId();$sites=[];
        foreach(Auth::sites() as $site){
            if($selected!==null&&(int)$site['id']!==$selected)continue;
            foreach(['livestock','butchery','alerts'] as $component){
                if(Auth::can($component,'read',(int)$site['id']))$sites[$component][]=(int)$site['id'];
            }
        }
        $cacheKey=json_encode([$user['id'],$selected,$sites]);
        if(isset(self::$cache[$cacheKey]))return self::$cache[$cacheKey];
        $items=[];
        $add=static function($key,$count,$label,$path,$severity,$icon)use(&$items){
            if($count>0)$items[]=['key'=>$key,'count'=>(int)$count,'label'=>$label,'path'=>$path,'severity'=>$severity,'icon'=>$icon];
        };
        if(!empty($sites['livestock'])&&$this->hasTables(['livestock_pig_profiles','livestock_pig_events'])){
            require_once __DIR__.'/PigService.php';
            $scope=$this->scope($sites['livestock']);
            $rows=$this->db->query("SELECT p.born_on,p.confirmed_group FROM livestock_pig_profiles p JOIN livestock_batches b ON b.id=p.batch_id WHERE b.site_id IN ($scope) AND b.status='active' AND b.current_heads>0 AND b.deleted_at IS NULL")->fetchAll(PDO::FETCH_ASSOC);
            $groups=PigGroupService::active($this->db);$pending=0;foreach($rows as $row)if(PigService::expectedGroup($row['born_on'],null,$groups)!==$row['confirmed_group'])$pending++;
            $add('pending_pig_groups',$pending,'Passages de groupe à vérifier','livestock/pigs?filter=pending','warning','bi-heart-pulse');
            if($this->hasTables(['pig_slaughter_details'])){
                $count=$this->count("SELECT COUNT(*) FROM livestock_conversions c JOIN pig_slaughter_details d ON d.conversion_id=c.id JOIN livestock_batches b ON b.id=c.batch_id WHERE b.site_id IN ($scope) AND c.status='submitted' AND b.deleted_at IS NULL");
                $add('pending_pig_slaughters',$count,'Abattages à valider','livestock/pigs/slaughters?status=submitted','warning','bi-clipboard-check');
                $count=$this->count("SELECT COUNT(*) FROM livestock_transfers t JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN pig_slaughter_details d ON d.conversion_id=w.conversion_id WHERE t.source_site_id IN ($scope) AND t.status='approved' AND t.deleted_at IS NULL");
                $add('ready_pig_shipments',$count,'Envois prêts à expédier','livestock/pigs/shipments','progress','bi-truck');
            }
        }
        $fish=$this->hasTables(['livestock_pond_parcels']);
        if(!empty($sites['livestock'])&&$fish){
            $scope=$this->scope($sites['livestock']);
            $count=$this->count("SELECT COUNT(*) FROM fish_catches fc JOIN livestock_batches b ON b.id=fc.batch_id WHERE b.site_id IN ($scope) AND fc.status='submitted' AND fc.item_id IS NOT NULL AND b.deleted_at IS NULL");
            $add('pending_fish_harvests',$count,'Pêches à valider','livestock/fish/harvests','warning','bi-water');
            $count=$this->count("SELECT COUNT(*) FROM livestock_transfers t JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN fish_catches fc ON fc.conversion_id=w.conversion_id WHERE t.source_site_id IN ($scope) AND t.status='approved' AND t.deleted_at IS NULL");
            $add('ready_fish_shipments',$count,'Envois de poisson prêts à expédier','livestock/fish/harvests','progress','bi-truck');
        }
        if(!empty($sites['livestock'])&&$this->hasTables(['cattle_slaughter_details'])){
            $scope=$this->scope($sites['livestock']);
            $count=$this->count("SELECT COUNT(*) FROM livestock_conversions c JOIN cattle_slaughter_details d ON d.conversion_id=c.id JOIN livestock_batches b ON b.id=c.batch_id WHERE b.site_id IN ($scope) AND c.status='submitted' AND b.deleted_at IS NULL");
            $add('pending_cattle_slaughters',$count,'Abattages de bovins à valider','livestock/cattle/slaughters','warning','bi-clipboard-check');
            $count=$this->count("SELECT COUNT(*) FROM livestock_transfers t JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN cattle_slaughter_details d ON d.conversion_id=w.conversion_id WHERE t.source_site_id IN ($scope) AND t.status='approved' AND t.deleted_at IS NULL");
            $add('ready_cattle_shipments',$count,'Envois de bœuf prêts à expédier','livestock/cattle/slaughters','progress','bi-truck');
        }
        $poultry=$this->hasTables(['poultry_lot_profiles']);
        if(!empty($sites['livestock'])&&$poultry){
            $scope=$this->scope($sites['livestock']);
            $count=$this->count("SELECT COUNT(*) FROM livestock_conversions c JOIN poultry_slaughter_details d ON d.conversion_id=c.id JOIN livestock_batches b ON b.id=c.batch_id WHERE b.site_id IN ($scope) AND c.status='submitted' AND b.deleted_at IS NULL");
            $add('pending_poultry_slaughters',$count,'Abattages de volailles à valider','livestock/poultry/slaughters','warning','bi-clipboard-check');
            $count=$this->count("SELECT COUNT(*) FROM livestock_transfers t LEFT JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id LEFT JOIN poultry_slaughter_details d ON d.conversion_id=w.conversion_id LEFT JOIN poultry_live_shipments ls ON ls.transfer_id=t.id WHERE t.source_site_id IN ($scope) AND t.status='approved' AND t.deleted_at IS NULL AND (d.conversion_id IS NOT NULL OR ls.transfer_id IS NOT NULL)");
            $add('ready_poultry_shipments',$count,'Envois de volailles prêts à expédier','livestock/poultry/slaughters','progress','bi-truck');
        }
        if(!empty($sites['butchery'])&&$this->hasTables(['pig_slaughter_details','butchery_counters'])){
            $scope=$this->scope($sites['butchery']);
            // Pig carcasses and, once migration 042 is applied, fish harvests reach the same reception.
            $parts=['SELECT conversion_id FROM pig_slaughter_details'];
            if($fish)$parts[]='SELECT conversion_id FROM fish_catches WHERE conversion_id IS NOT NULL AND item_id IS NOT NULL';
            if($this->hasTables(['cattle_slaughter_details']))$parts[]='SELECT conversion_id FROM cattle_slaughter_details';
            if($poultry)$parts[]='SELECT conversion_id FROM poultry_slaughter_details';
            $shippable='('.implode(' UNION ALL ',$parts).')';
            // Live poultry (044) reaches the same reception without a weighed farm stock.
            $receivable="(SELECT t.id transfer_id FROM livestock_transfers t JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN $shippable d ON d.conversion_id=w.conversion_id".($poultry?' UNION ALL SELECT transfer_id FROM poultry_live_shipments':'').')';
            $count=$this->count("SELECT COUNT(*) FROM livestock_transfers t JOIN $receivable d ON d.transfer_id=t.id WHERE t.destination_site_id IN ($scope) AND t.status='in_transit' AND t.deleted_at IS NULL AND NOT EXISTS(SELECT 1 FROM butchery_receipts r WHERE r.livestock_transfer_id=t.id)");
            $add('incoming_carcasses',$count,$fish?'Envois à peser et réceptionner':'Envois de carcasses attendus','butchery/receipts?kind=incoming','progress','bi-box-arrow-in-down');
            $count=$this->count("SELECT COUNT(*) FROM butchery_receipts r JOIN livestock_transfers t ON t.id=r.livestock_transfer_id JOIN $receivable d ON d.transfer_id=t.id WHERE r.site_id IN ($scope) AND r.status='sanitary_pending' AND r.deleted_at IS NULL");
            $add('pending_carcass_controls',$count,'Contrôles sanitaires en attente','butchery/receipts?status=pending','warning','bi-shield-check');
            $count=$this->count("SELECT COUNT(*) FROM butchery_orders WHERE site_id IN ($scope) AND status='results_submitted' AND flow_managed=1 AND deleted_at IS NULL");
            $add('pending_butchery_transformations',$count,'Transformations à valider','butchery/production?status=results_submitted','warning','bi-gear');
            $count=$this->count("SELECT COUNT(*) FROM butchery_raw_lots WHERE site_id IN ($scope) AND quantity_kg>0 AND expiry_date<CURDATE() AND status NOT IN ('cancelled','rejected') AND deleted_at IS NULL")+
                $this->count("SELECT COUNT(*) FROM butchery_finished_lots l WHERE l.site_id IN ($scope) AND l.expiry_date<CURDATE() AND l.status<>'cancelled' AND l.deleted_at IS NULL".($poultry?' AND l.receipt_id IS NULL':'')." AND l.quantity_kg>(SELECT COALESCE(SUM(cs.quantity_kg),0) FROM butchery_counter_stocks cs WHERE cs.finished_lot_id=l.id)");
            $add('expired_cold_lots',$count,'Lots au froid avec DLC dépassée','butchery/cold-room?status=expired','danger','bi-snow');
            $count=$this->count("SELECT COUNT(*) FROM butchery_counter_stocks cs JOIN butchery_finished_lots l ON l.id=cs.finished_lot_id JOIN butchery_counters c ON c.id=cs.counter_id WHERE c.site_id IN ($scope) AND cs.quantity_kg>0 AND l.expiry_date<CURDATE() AND l.status<>'cancelled' AND l.deleted_at IS NULL");
            $add('expired_counter_lots',$count,'Lots au comptoir avec DLC dépassée','butchery/counters?status=expired','danger','bi-shop');
        }
        if(!empty($sites['alerts'])){
            $scope=$this->scope($sites['alerts']);
            $count=$this->count("SELECT COUNT(*) FROM alerts WHERE site_id IN ($scope) AND status='active' AND read_at IS NULL AND deleted_at IS NULL");
            $add('unread_alerts',$count,'Alertes non lues','alerts','warning','bi-bell');
        }
        return self::$cache[$cacheKey]=$items;
    }

    public function countFor(string $key): int
    {
        foreach($this->items() as $item)if($item['key']===$key)return $item['count'];
        return 0;
    }
    private function scope(array $ids): string { return implode(',',array_unique(array_map('intval',$ids))); }
    private function count(string $sql): int { return (int)$this->db->query($sql)->fetchColumn(); }
    private function hasTables(array $names): bool
    {
        foreach($names as $name){
            if(!array_key_exists($name,self::$tables)){
                $q=$this->db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$q->execute([$name]);self::$tables[$name]=(bool)$q->fetchColumn();
            }
            if(!self::$tables[$name])return false;
        }
        return true;
    }
}
