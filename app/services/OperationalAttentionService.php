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
            $pending=0;foreach($rows as $row)if(PigService::expectedGroup($row['born_on'])!==$row['confirmed_group'])$pending++;
            $add('pending_pig_groups',$pending,'Passages de groupe à vérifier','livestock/pigs?filter=pending','warning','bi-heart-pulse');
            if($this->hasTables(['pig_slaughter_details'])){
                $count=$this->count("SELECT COUNT(*) FROM livestock_conversions c JOIN pig_slaughter_details d ON d.conversion_id=c.id JOIN livestock_batches b ON b.id=c.batch_id WHERE b.site_id IN ($scope) AND c.status='submitted' AND b.deleted_at IS NULL");
                $add('pending_pig_slaughters',$count,'Abattages à valider','livestock/pigs/slaughters?status=submitted','warning','bi-clipboard-check');
                $count=$this->count("SELECT COUNT(*) FROM livestock_transfers t JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN pig_slaughter_details d ON d.conversion_id=w.conversion_id WHERE t.source_site_id IN ($scope) AND t.status='approved' AND t.deleted_at IS NULL");
                $add('ready_pig_shipments',$count,'Envois prêts à expédier','livestock/pigs/shipments?status=approved','progress','bi-truck');
            }
        }
        if(!empty($sites['butchery'])&&$this->hasTables(['pig_slaughter_details','butchery_counters'])){
            $scope=$this->scope($sites['butchery']);
            $count=$this->count("SELECT COUNT(*) FROM livestock_transfers t JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN pig_slaughter_details d ON d.conversion_id=w.conversion_id WHERE t.destination_site_id IN ($scope) AND t.status='in_transit' AND t.deleted_at IS NULL AND NOT EXISTS(SELECT 1 FROM butchery_receipts r WHERE r.livestock_transfer_id=t.id)");
            $add('incoming_carcasses',$count,'Envois de carcasses attendus','butchery/receipts?kind=incoming','progress','bi-box-arrow-in-down');
            $count=$this->count("SELECT COUNT(*) FROM butchery_receipts r JOIN livestock_transfers t ON t.id=r.livestock_transfer_id JOIN livestock_weight_stocks w ON w.id=t.weight_stock_id JOIN pig_slaughter_details d ON d.conversion_id=w.conversion_id WHERE r.site_id IN ($scope) AND r.status='sanitary_pending' AND r.deleted_at IS NULL");
            $add('pending_carcass_controls',$count,'Contrôles sanitaires en attente','butchery/receipts?status=pending','warning','bi-shield-check');
            $count=$this->count("SELECT COUNT(*) FROM butchery_orders WHERE site_id IN ($scope) AND status='results_submitted' AND flow_managed=1 AND deleted_at IS NULL");
            $add('pending_butchery_transformations',$count,'Transformations à valider','butchery/production?status=results_submitted','warning','bi-gear');
            $count=$this->count("SELECT COUNT(*) FROM butchery_raw_lots WHERE site_id IN ($scope) AND quantity_kg>0 AND expiry_date<CURDATE() AND status NOT IN ('cancelled','rejected') AND deleted_at IS NULL")+
                $this->count("SELECT COUNT(*) FROM butchery_finished_lots l WHERE l.site_id IN ($scope) AND l.expiry_date<CURDATE() AND l.status<>'cancelled' AND l.deleted_at IS NULL AND l.quantity_kg>(SELECT COALESCE(SUM(cs.quantity_kg),0) FROM butchery_counter_stocks cs WHERE cs.finished_lot_id=l.id)");
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
