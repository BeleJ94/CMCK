<?php

class Agriculture extends Model
{
    protected $table = 'agricultural_campaigns';

    public function suggestedCodes()
    {
        $siteId = Auth::currentSiteId();
        $siteCode = $siteId ? (string) $this->query("SELECT code FROM sites WHERE id=:id AND deleted_at IS NULL LIMIT 1", ['id'=>$siteId])->fetchColumn() : 'FERME';
        $shortSite = str_replace('FARM-', '', strtoupper($siteCode ?: 'FERME'));
        return [
            'campaign'=>$this->nextSuggestedCode('agricultural_campaigns','code','CAMP-'.$shortSite.'-'.date('Y'),$siteId),
            'plot'=>$this->nextSuggestedCode('agricultural_plots','code','PAR-'.$shortSite,$siteId),
            'worker'=>$this->nextSuggestedCode('agricultural_workers','worker_number','MO-'.$shortSite,$siteId),
            'equipment'=>$this->nextSuggestedCode('agricultural_equipment','code','MAT-'.$shortSite,$siteId),
        ];
    }

    private function nextSuggestedCode($table,$column,$prefix,$siteId)
    {
        $allowed=['agricultural_campaigns.code','agricultural_plots.code','agricultural_workers.worker_number','agricultural_equipment.code'];
        if(!in_array($table.'.'.$column,$allowed,true))throw new InvalidArgumentException('Référentiel de numérotation invalide.');
        if(!$siteId)return $prefix.'-001';
        $rows=$this->query("SELECT {$column} FROM {$table} WHERE site_id=:site AND {$column} LIKE :pattern",['site'=>$siteId,'pattern'=>$prefix.'-%'])->fetchAll();$used=[];
        foreach($rows as$row){if(preg_match('/^'.preg_quote($prefix,'/').'-(\d+)$/',(string)$row[$column],$match))$used[(int)$match[1]]=true;}
        $sequence=1;while(isset($used[$sequence]))$sequence++;
        return $prefix.'-'.str_pad((string)$sequence,3,'0',STR_PAD_LEFT);
    }

    public function references()
    {
        $siteId = Auth::currentSiteId();
        $farmSites = array_values(array_filter(Auth::sites(), function ($site) {
            return in_array($site['code'], ['FARM-MUT', 'FARM-DIK'], true);
        }));
        foreach ($farmSites as &$farmSite) {
            $shortSite = str_replace('FARM-', '', $farmSite['code']);
            $farmSite['campaign_suggestion'] = $this->nextSuggestedCode('agricultural_campaigns', 'code', 'CAMP-' . $shortSite . '-' . date('Y'), (int) $farmSite['id']);
            $farmSite['plot_suggestion'] = $this->nextSuggestedCode('agricultural_plots', 'code', 'PAR-' . $shortSite, (int) $farmSite['id']);
            $farmSite['worker_suggestion'] = $this->nextSuggestedCode('agricultural_workers', 'worker_number', 'MO-' . $shortSite, (int) $farmSite['id']);
            $farmSite['equipment_suggestion'] = $this->nextSuggestedCode('agricultural_equipment', 'code', 'MAT-' . $shortSite, (int) $farmSite['id']);
        }
        unset($farmSite);
        return [
            'farm_sites'=>$farmSites,
            'transport_destination'=>$this->row("SELECT id,code,name FROM sites WHERE code='SILO' AND status='active' AND deleted_at IS NULL LIMIT 1",[]),
            'seasons'=>$this->query("SELECT * FROM agricultural_seasons WHERE status='active' AND deleted_at IS NULL ORDER BY name")->fetchAll(),
            'varieties'=>$this->query("SELECT * FROM agricultural_varieties WHERE status='active' AND deleted_at IS NULL ORDER BY name")->fetchAll(),
            'inputs'=>$this->query("SELECT * FROM agricultural_inputs WHERE status='active' AND deleted_at IS NULL ORDER BY input_type,name")->fetchAll(),
            'plots'=>$siteId ? $this->query("SELECT * FROM agricultural_plots WHERE site_id=:site AND status='active' AND deleted_at IS NULL ORDER BY code",['site'=>$siteId])->fetchAll() : [],
            'workers'=>$siteId ? $this->query("SELECT * FROM agricultural_workers WHERE site_id=:site AND status='active' AND deleted_at IS NULL ORDER BY name",['site'=>$siteId])->fetchAll() : [],
            'equipment'=>$siteId ? $this->query("SELECT * FROM agricultural_equipment WHERE site_id=:site AND status='active' AND deleted_at IS NULL ORDER BY name",['site'=>$siteId])->fetchAll() : [],
        ];
    }

    public function campaigns()
    {
        $params=[];$scope=Auth::siteClause('c.site_id',$params);
        return $this->query("SELECT c.*,s.name season_name,si.code site_code,si.name site_name,
            COALESCE(p.actual_area_ha,0) actual_area_ha,COALESCE(p.target_tons,0) target_tons,
            COALESCE(h.realized_tons,0) realized_tons,COALESCE(p.plot_count,0) plot_count
            FROM agricultural_campaigns c
            JOIN agricultural_seasons s ON s.id=c.season_id JOIN sites si ON si.id=c.site_id
            LEFT JOIN (SELECT campaign_id,COUNT(*) plot_count,SUM(actual_area_ha) actual_area_ha,
                SUM(actual_area_ha*target_tons_per_ha) target_tons FROM agricultural_campaign_plots GROUP BY campaign_id) p ON p.campaign_id=c.id
            LEFT JOIN (SELECT cp.campaign_id,SUM(h.net_weight_kg)/1000 realized_tons
                FROM agricultural_campaign_plots cp JOIN agricultural_harvests h ON h.campaign_plot_id=cp.id
                WHERE h.status='validated' AND h.deleted_at IS NULL GROUP BY cp.campaign_id) h ON h.campaign_id=c.id
            WHERE c.deleted_at IS NULL{$scope} ORDER BY c.start_date DESC,c.id DESC",$params)->fetchAll();
    }

    public function plots()
    {
        $params=[];$scope=Auth::siteClause('p.site_id',$params);
        return $this->query("SELECT p.*,s.code site_code,s.name site_name,(SELECT COUNT(*) FROM agricultural_campaign_plots cp WHERE cp.plot_id=p.id) planning_count,(SELECT COALESCE(SUM(cp.actual_area_ha),0) FROM agricultural_campaign_plots cp WHERE cp.plot_id=p.id) exploited_area_ha FROM agricultural_plots p JOIN sites s ON s.id=p.site_id WHERE p.deleted_at IS NULL{$scope} ORDER BY s.code,p.code",$params)->fetchAll();
    }

    public function campaignPlots()
    {
        $params=[];$scope=Auth::siteClause('c.site_id',$params);
        return $this->query("SELECT cp.*,c.code campaign_code,p.code plot_code,p.name plot_name,v.name variety_name FROM agricultural_campaign_plots cp JOIN agricultural_campaigns c ON c.id=cp.campaign_id JOIN agricultural_plots p ON p.id=cp.plot_id JOIN agricultural_varieties v ON v.id=cp.variety_id WHERE c.deleted_at IS NULL{$scope} ORDER BY c.start_date DESC,p.code",$params)->fetchAll();
    }

    public function inputAllocations(){$params=[];$scope=Auth::siteClause('c.site_id',$params);return$this->query("SELECT a.*,i.code input_code,i.name input_name,i.input_type,i.unit,c.code campaign_code,p.code plot_code,s.code site_code,u.name creator_name,(a.actual_quantity*a.unit_cost) total_cost FROM agricultural_input_allocations a JOIN agricultural_inputs i ON i.id=a.input_id JOIN agricultural_campaign_plots cp ON cp.id=a.campaign_plot_id JOIN agricultural_campaigns c ON c.id=cp.campaign_id JOIN agricultural_plots p ON p.id=cp.plot_id JOIN sites s ON s.id=c.site_id JOIN users u ON u.id=a.created_by WHERE a.deleted_at IS NULL{$scope} ORDER BY a.id DESC",$params)->fetchAll();}

    public function works(){$params=[];$scope=Auth::siteClause('c.site_id',$params);return$this->query("SELECT w.*,c.code campaign_code,p.code plot_code,s.code site_code,u.name creator_name,(w.other_cost+(SELECT COALESCE(SUM(l.cost),0) FROM agricultural_work_labor l WHERE l.work_id=w.id)+(SELECT COALESCE(SUM(e.cost),0) FROM agricultural_work_equipment e WHERE e.work_id=w.id)) total_cost FROM agricultural_works w JOIN agricultural_campaign_plots cp ON cp.id=w.campaign_plot_id JOIN agricultural_campaigns c ON c.id=cp.campaign_id JOIN agricultural_plots p ON p.id=cp.plot_id JOIN sites s ON s.id=c.site_id JOIN users u ON u.id=w.created_by WHERE w.deleted_at IS NULL{$scope} ORDER BY w.worked_at DESC",$params)->fetchAll();}

    public function stocks(){$params=[];$scope=Auth::siteClause('st.site_id',$params);return$this->query("SELECT st.*,s.code site_code,h.harvest_number,p.name product_name,v.name variety_name,(st.physical_quantity_kg-st.reserved_quantity_kg) available_quantity_kg FROM agricultural_stocks st JOIN sites s ON s.id=st.site_id JOIN agricultural_harvests h ON h.id=st.harvest_id JOIN products p ON p.id=st.product_id JOIN agricultural_varieties v ON v.id=st.variety_id WHERE 1=1{$scope} ORDER BY st.id DESC",$params)->fetchAll();}

    public function workers(){$params=[];$scope=Auth::siteClause('w.site_id',$params);return$this->query("SELECT w.*,s.code site_code,s.name site_name FROM agricultural_workers w JOIN sites s ON s.id=w.site_id WHERE w.deleted_at IS NULL{$scope} ORDER BY s.code,w.name",$params)->fetchAll();}

    public function equipmentList(){$params=[];$scope=Auth::siteClause('e.site_id',$params);return$this->query("SELECT e.*,s.code site_code,s.name site_name FROM agricultural_equipment e JOIN sites s ON s.id=e.site_id WHERE e.deleted_at IS NULL{$scope} ORDER BY s.code,e.name",$params)->fetchAll();}

    public function harvests()
    {
        $params=[];$scope=Auth::siteClause('h.site_id',$params);
        return $this->query("SELECT h.*,c.code campaign_code,p.code plot_code,v.name variety_name,si.code site_code,si.name site_name,st.id stock_id,st.physical_quantity_kg,st.reserved_quantity_kg,st.status stock_status,EXISTS(SELECT 1 FROM agricultural_transports existing_transport WHERE existing_transport.stock_id=st.id AND existing_transport.status='draft' AND existing_transport.deleted_at IS NULL) has_draft_transport FROM agricultural_harvests h JOIN agricultural_campaign_plots cp ON cp.id=h.campaign_plot_id JOIN agricultural_campaigns c ON c.id=cp.campaign_id JOIN agricultural_plots p ON p.id=cp.plot_id JOIN agricultural_varieties v ON v.id=cp.variety_id JOIN sites si ON si.id=h.site_id LEFT JOIN agricultural_stocks st ON st.harvest_id=h.id WHERE h.deleted_at IS NULL{$scope} ORDER BY h.harvested_at DESC",$params)->fetchAll();
    }

    public function transports()
    {
        $params=[];$scope=Auth::siteClause('t.source_site_id',$params);
        return $this->query("SELECT t.*,d.document_number,h.harvest_number,s.physical_quantity_kg,s.reserved_quantity_kg FROM agricultural_transports t JOIN agricultural_stocks s ON s.id=t.stock_id JOIN agricultural_harvests h ON h.id=s.harvest_id LEFT JOIN documents d ON d.entity_type='agricultural_transports' AND d.entity_id=t.id AND d.document_type_id=(SELECT id FROM document_types WHERE code='BT' LIMIT 1) WHERE t.deleted_at IS NULL{$scope} ORDER BY t.id DESC",$params)->fetchAll();
    }

    public function createCampaign(array $data,array $user)
    {
        $site=$this->farmSite($data['site_id'] ?? null);
        if(empty($data['code'])||empty($data['name'])||empty($data['start_date'])||empty($data['end_date'])||$data['end_date']<$data['start_date']) throw new RuntimeException('Données de campagne invalides.');
        $this->query("INSERT INTO agricultural_campaigns(site_id,season_id,code,name,start_date,end_date,status,created_by) VALUES(:site,:season,:code,:name,:start,:end,'planned',:user)",['site'=>$site,'season'=>$data['season_id'],'code'=>trim($data['code']),'name'=>trim($data['name']),'start'=>$data['start_date'],'end'=>$data['end_date'],'user'=>$user['id']]);
        return (int)$this->db->lastInsertId();
    }

    public static function campaignVersion(array $campaign)
    {
        $values=[];foreach(['id','site_id','season_id','code','name','start_date','end_date','status','updated_at'] as $key)$values[$key]=(string)($campaign[$key]??'');
        return hash('sha256',json_encode($values));
    }

    public function updateCampaign($id,array $data,array $user)
    {
        if(!Auth::hasRole(['administrateur']))throw new RuntimeException('La modification des campagnes est réservée aux administrateurs.');
        $this->db->beginTransaction();
        try {
            $old=$this->row('SELECT * FROM agricultural_campaigns WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$id]);
            if(!$old)throw new RuntimeException('Campagne introuvable.');
            Auth::requireSiteAccess($old['site_id']);Auth::requirePermission('agriculture','update',$old['site_id']);
            if(!is_string($data['version']??null)||!hash_equals(self::campaignVersion($old),$data['version']))throw new RuntimeException('Cette campagne a été modifiée depuis son ouverture. Actualisez la page avant de recommencer.');
            $values=[];
            foreach(['code'=>80,'name'=>160,'reason'=>500] as $key=>$max){$value=$data[$key]??'';if(!is_string($value)||trim($value)===''||mb_strlen(trim($value))>$max)throw new RuntimeException(['code'=>'Code','name'=>'Nom','reason'=>'Motif de modification'][$key].' obligatoire ('.$max.' caractères maximum).');$values[$key]=trim($value);}
            foreach(['start_date','end_date'] as $key){$value=$data[$key]??'';$date=is_string($value)?DateTimeImmutable::createFromFormat('!Y-m-d',$value):false;if(!$date||$date->format('Y-m-d')!==$value)throw new RuntimeException('Renseignez des dates de début et de fin valides.');$values[$key]=$value;}
            if($values['end_date']<$values['start_date'])throw new RuntimeException('La date de fin doit être égale ou postérieure à la date de début.');
            if(!in_array($data['status']??null,['draft','planned','in_progress','harvesting','closed','cancelled'],true))throw new RuntimeException('Statut de campagne invalide.');
            $values['status']=$data['status'];
            if(!ctype_digit((string)($data['season_id']??'')))throw new RuntimeException('Choisissez une saison valide.');
            $season=$this->row('SELECT * FROM agricultural_seasons WHERE id=?',[$data['season_id']]);
            if(!$season||((int)$season['id']!==(int)$old['season_id']&&($season['status']!=='active'||$season['deleted_at']!==null)))throw new RuntimeException('Choisissez une saison active.');
            $values['season_id']=$season['id'];
            if(isset($data['site_id'])&&(string)$data['site_id']!==(string)$old['site_id'])throw new RuntimeException('La ferme de rattachement ne peut pas être changée.');
            if($this->row('SELECT id FROM agricultural_campaigns WHERE site_id=? AND code=? AND id<>?',[$old['site_id'],$values['code'],$id]))throw new RuntimeException('Ce code est déjà utilisé par une campagne de cette ferme.');
            if($values['start_date']!==$old['start_date']||$values['end_date']!==$old['end_date']){
                foreach(['agricultural_works'=>'worked_at','agricultural_harvests'=>'harvested_at','agricultural_input_allocations'=>'applied_at'] as $table=>$column){
                    $range=$this->row("SELECT MIN(DATE(r.$column)) first_date,MAX(DATE(r.$column)) last_date FROM $table r JOIN agricultural_campaign_plots cp ON cp.id=r.campaign_plot_id WHERE cp.campaign_id=? AND r.deleted_at IS NULL",[$id]);
                    if($range['first_date']&&($range['first_date']<$values['start_date']||$range['last_date']>$values['end_date']))throw new RuntimeException('La période doit inclure les opérations déjà enregistrées : du '.date('d/m/Y',strtotime($range['first_date'])).' au '.date('d/m/Y',strtotime($range['last_date'])).'.');
                }
            }
            $reason=$values['reason'];unset($values['reason']);$values['id']=$id;
            $this->query('UPDATE agricultural_campaigns SET code=:code,name=:name,season_id=:season_id,start_date=:start_date,end_date=:end_date,status=:status WHERE id=:id',$values);
            $new=$this->row('SELECT * FROM agricultural_campaigns WHERE id=?',[$id]);
            $this->logActivity('update_campaign','agriculture','agricultural_campaigns',$id,$reason,$old,$new,$user);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function createPlot(array $data)
    {
        $site=$this->farmSite($data['site_id'] ?? null);$area=(float)($data['total_area_ha']??0);if($area<=0)throw new RuntimeException('Superficie invalide.');
        $this->query("INSERT INTO agricultural_plots(site_id,code,name,total_area_ha,status) VALUES(:site,:code,:name,:area,'active')",['site'=>$site,'code'=>trim($data['code']),'name'=>trim($data['name']),'area'=>$area]);return(int)$this->db->lastInsertId();
    }

    public function planPlot(array $data)
    {
        $campaign=$this->row('SELECT * FROM agricultural_campaigns WHERE id=? AND deleted_at IS NULL',[$data['campaign_id']??0]);$plot=$this->row('SELECT * FROM agricultural_plots WHERE id=? AND deleted_at IS NULL',[$data['plot_id']??0]);
        if(!$campaign||!$plot||(int)$campaign['site_id']!==(int)$plot['site_id'])throw new RuntimeException('La campagne et la parcelle doivent appartenir à la même ferme.');
        $site=$this->farmSite($campaign['site_id']);$planned=(float)($data['planned_area_ha']??0);$actual=(float)($data['actual_area_ha']??0);$target=(float)($data['target_tons_per_ha']??0);if($planned<=0||$actual<=0||$planned>(float)$plot['total_area_ha']||$actual>(float)$plot['total_area_ha']||$target<=0)throw new RuntimeException('Planification parcelle invalide.');
        $this->query("INSERT INTO agricultural_campaign_plots(campaign_id,plot_id,variety_id,planned_area_ha,actual_area_ha,target_tons_per_ha,status) VALUES(:campaign,:plot,:variety,:planned,:actual,:target,'in_progress')",['campaign'=>$campaign['id'],'plot'=>$plot['id'],'variety'=>$data['variety_id'],'planned'=>$planned,'actual'=>$actual,'target'=>$target]);return(int)$this->db->lastInsertId();
    }

    public function allocateInput(array $data,array $user)
    {
        $cp=$this->scopedCampaignPlot($data['campaign_plot_id']);$planned=(float)$data['planned_quantity'];$actual=(float)$data['actual_quantity'];$cost=(float)$data['unit_cost'];if($planned<0||$actual<0||$cost<0)throw new RuntimeException('Imputation intrant invalide.');
        $this->query('INSERT INTO agricultural_input_allocations(campaign_plot_id,input_id,planned_quantity,actual_quantity,unit_cost,applied_at,created_by) VALUES(:cp,:input,:planned,:actual,:cost,:at,:user)',['cp'=>$cp['id'],'input'=>$data['input_id'],'planned'=>$planned,'actual'=>$actual,'cost'=>$cost,'at'=>$data['applied_at']?:null,'user'=>$user['id']]);return(int)$this->db->lastInsertId();
    }

    public function createWorker(array $data)
    {
        $site=$this->farmSite($data['site_id'] ?? null);$rate=(float)($data['daily_rate']??0);if(!in_array($data['worker_type'],['permanent','daily'],true)||$rate<0)throw new RuntimeException('Main-d’œuvre invalide.');$this->query("INSERT INTO agricultural_workers(site_id,worker_number,name,worker_type,daily_rate,status) VALUES(:site,:number,:name,:type,:rate,'active')",['site'=>$site,'number'=>trim($data['worker_number']),'name'=>trim($data['name']),'type'=>$data['worker_type'],'rate'=>$rate]);return(int)$this->db->lastInsertId();
    }

    public function createEquipment(array $data)
    {
        $site=$this->farmSite($data['site_id'] ?? null);$cost=(float)($data['hourly_cost']??0);if($cost<0)throw new RuntimeException('Coût matériel invalide.');$this->query("INSERT INTO agricultural_equipment(site_id,code,name,hourly_cost,status) VALUES(:site,:code,:name,:cost,'active')",['site'=>$site,'code'=>trim($data['code']),'name'=>trim($data['name']),'cost'=>$cost]);return(int)$this->db->lastInsertId();
    }

    public function recordWork(array $data,array $user)
    {
        require_once dirname(__DIR__).'/services/AgriculturalWorkService.php';
        return (new AgriculturalWorkService($this->db))->save($data,$user);
    }

    public function createHarvest(array $data,array $user)
    {
        $cp=$this->scopedCampaignPlot($data['campaign_plot_id']);$gross=(float)$data['gross_weight_kg'];$tare=(float)$data['tare_weight_kg'];$dry=(float)$data['drying_loss_kg'];$net=$gross-$tare-$dry;if($gross<=0||$tare<0||$dry<0||$net<=0)throw new RuntimeException('Poids de récolte invalides.');$number='REC-'.$cp['site_code'].'-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(6)));$yield=$net/1000/(float)$cp['actual_area_ha'];
        $this->query("INSERT INTO agricultural_harvests(site_id,campaign_plot_id,harvest_number,harvested_at,gross_weight_kg,tare_weight_kg,drying_loss_kg,net_weight_kg,moisture_before,moisture_after,drying_started_at,drying_ended_at,yield_tons_per_ha,status,created_by) VALUES(:site,:cp,:number,:at,:gross,:tare,:dry,:net,:mb,:ma,:ds,:de,:yield,'submitted',:user)",['site'=>$cp['site_id'],'cp'=>$cp['id'],'number'=>$number,'at'=>$data['harvested_at'],'gross'=>$gross,'tare'=>$tare,'dry'=>$dry,'net'=>$net,'mb'=>$data['moisture_before']?:null,'ma'=>$data['moisture_after']?:null,'ds'=>$data['drying_started_at']?:null,'de'=>$data['drying_ended_at']?:null,'yield'=>$yield,'user'=>$user['id']]);return(int)$this->db->lastInsertId();
    }

    public static function harvestVersion(array $harvest)
    {
        $values=[];foreach(['id','campaign_plot_id','harvested_at','gross_weight_kg','tare_weight_kg','drying_loss_kg','net_weight_kg','moisture_before','moisture_after','drying_started_at','drying_ended_at','status','validated_at'] as $key)$values[$key]=(string)($harvest[$key]??'');
        return hash('sha256',json_encode($values));
    }

    public function updateHarvest($id,array $data,array $user)
    {
        $this->db->beginTransaction();
        try{
            $old=$this->row('SELECT * FROM agricultural_harvests WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$id]);
            if(!$old)throw new RuntimeException('Récolte introuvable.');
            Auth::requireSiteAccess($old['site_id']);Auth::requirePermission('agriculture','update',$old['site_id']);
            if($old['status']==='cancelled')throw new RuntimeException('Une récolte annulée ne peut pas être modifiée.');
            if($old['status']==='validated'&&!Auth::hasRole(['administrateur']))throw new RuntimeException('Seul un administrateur peut modifier une récolte déjà validée.');
            if(!is_string($data['version']??null)||!hash_equals(self::harvestVersion($old),$data['version']))throw new RuntimeException('Cette récolte a changé depuis son ouverture. Actualisez la liste avant de recommencer.');
            if((string)($data['campaign_plot_id']??'')!==(string)$old['campaign_plot_id'])throw new RuntimeException('La campagne et la parcelle de la récolte ne peuvent pas être changées.');
            $reason=$data['reason']??'';if(!is_string($reason)||trim($reason)===''||mb_strlen($reason)>500)throw new RuntimeException('Précisez le motif de modification (500 caractères maximum).');
            $values=[];
            foreach(['gross_weight_kg','tare_weight_kg','drying_loss_kg','moisture_before','moisture_after'] as $key){
                $raw=$data[$key]??'';$optional=str_starts_with($key,'moisture');
                if($optional&&$raw===''){$values[$key]=null;continue;}
                if(!is_scalar($raw)||!is_numeric($raw)||!is_finite((float)$raw)||(float)$raw<0||(float)$raw>($optional?100:99999999999))throw new RuntimeException($optional?'L’humidité doit être comprise entre 0 et 100 %.':'Les poids doivent être des nombres positifs ou nuls.');
                $values[$key]=round((float)$raw,3);
            }
            $net=round($values['gross_weight_kg']-$values['tare_weight_kg']-$values['drying_loss_kg'],3);
            if($values['gross_weight_kg']<=0||$net<=0)throw new RuntimeException('Le poids brut doit dépasser la tare et la perte de séchage cumulées.');
            foreach(['harvested_at','drying_started_at','drying_ended_at'] as $key){$value=$data[$key]??'';if($key!=='harvested_at'&&$value===''){$values[$key]=null;continue;}$value=is_string($value)?str_replace('T',' ',$value):'';if(strlen($value)===16)$value.=':00';$date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value);if(!$date||$date->format('Y-m-d H:i:s')!==$value)throw new RuntimeException('Renseignez une date et une heure valides pour la récolte et le séchage.');$values[$key]=$value;}
            if($values['drying_ended_at']&&(!$values['drying_started_at']||$values['drying_ended_at']<$values['drying_started_at']))throw new RuntimeException('La fin du séchage doit être postérieure à son début.');
            $cp=$this->scopedCampaignPlot($old['campaign_plot_id']);if((float)$cp['actual_area_ha']<=0)throw new RuntimeException('La surface exploitée doit être supérieure à zéro.');
            $stock=null;$delta=round($net-(float)$old['net_weight_kg'],3);$after=null;
            if($old['status']==='validated'){
                $stock=$this->row('SELECT * FROM agricultural_stocks WHERE harvest_id=? FOR UPDATE',[$id]);
                if(!$stock||$stock['status']==='cancelled')throw new RuntimeException('Le stock associé est absent ou annulé. La correction ne peut pas être appliquée.');
                $after=round((float)$stock['physical_quantity_kg']+$delta,3);
                if($after<0||$after<(float)$stock['reserved_quantity_kg'])throw new RuntimeException('Ce poids net est trop faible : une partie de la récolte est déjà expédiée ou réservée. La correction doit préserver ces quantités.');
                $draft=$this->row("SELECT COALESCE(SUM(quantity_kg),0) quantity FROM agricultural_transports WHERE stock_id=? AND status='draft' AND deleted_at IS NULL",[$stock['id']]);
                if(round($after-(float)$stock['reserved_quantity_kg'],3)<(float)$draft['quantity'])throw new RuntimeException('La correction laisserait trop peu de stock pour les bons de transport en brouillon. Ajustez ces bons avant de réduire le poids.');
            }
            $values['net_weight_kg']=$net;$values['yield_tons_per_ha']=round($net/1000/(float)$cp['actual_area_ha'],4);$values['id']=$id;
            $this->query('UPDATE agricultural_harvests SET harvested_at=:harvested_at,gross_weight_kg=:gross_weight_kg,tare_weight_kg=:tare_weight_kg,drying_loss_kg=:drying_loss_kg,net_weight_kg=:net_weight_kg,moisture_before=:moisture_before,moisture_after=:moisture_after,drying_started_at=:drying_started_at,drying_ended_at=:drying_ended_at,yield_tons_per_ha=:yield_tons_per_ha WHERE id=:id',$values);
            $new=$this->row('SELECT * FROM agricultural_harvests WHERE id=?',[$id]);
            $this->logActivity('update_harvest','agriculture','agricultural_harvests',$id,trim($reason),$old,$new,$user);$auditId=(int)$this->db->lastInsertId();
            if($stock&&$delta!=0){$status=$after>0?((float)$stock['reserved_quantity_kg']>0?'reserved':'available'):($stock['status']==='in_transit'?'in_transit':'depleted');$this->query('UPDATE agricultural_stocks SET physical_quantity_kg=?,status=? WHERE id=?',[$after,$status,$stock['id']]);$this->movement($stock['id'],'reversal',$delta,$stock['physical_quantity_kg'],$after,$stock['reserved_quantity_kg'],$stock['reserved_quantity_kg'],'activity_logs',$auditId,$user);}
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function validateHarvest($id,array $user)
    {
        $this->db->beginTransaction();try{$h=$this->row("SELECT h.*,cp.variety_id,v.product_id FROM agricultural_harvests h JOIN agricultural_campaign_plots cp ON cp.id=h.campaign_plot_id JOIN agricultural_varieties v ON v.id=cp.variety_id WHERE h.id=? FOR UPDATE",[$id]);if(!$h||$h['status']!=='submitted')throw new RuntimeException('Récolte non validable ou déjà validée.');Auth::requireSiteAccess($h['site_id']);$selfApproval=(int)$h['created_by']===(int)$user['id'];if($selfApproval&&!Auth::hasRole(['administrateur']))throw new RuntimeException('Le créateur ne peut pas valider la récolte.');$this->query("INSERT INTO agricultural_stocks(site_id,harvest_id,product_id,variety_id,physical_quantity_kg,reserved_quantity_kg,status) VALUES(:site,:harvest,:product,:variety,:qty,0,'available')",['site'=>$h['site_id'],'harvest'=>$h['id'],'product'=>$h['product_id'],'variety'=>$h['variety_id'],'qty'=>$h['net_weight_kg']]);$stock=(int)$this->db->lastInsertId();$this->movement($stock,'harvest_in',$h['net_weight_kg'],0,$h['net_weight_kg'],0,0,'agricultural_harvests',$id,$user);$this->query("UPDATE agricultural_harvests SET status='validated',validated_by=:user,validated_at=NOW() WHERE id=:id",['user'=>$user['id'],'id'=>$id]);$message=$selfApproval?'Validation administrative de sa propre récolte et entrée atomique en stock agricole.':'Validation récolte et entrée atomique en stock agricole.';$this->logActivity('validate_harvest','agriculture','agricultural_harvests',$id,$message,$h,['stock_id'=>$stock,'administrative_override'=>$selfApproval],$user);$this->db->commit();return$stock;}catch(Exception$e){$this->db->rollBack();throw$e;}
    }

    public function createTransport(array $data,array $user)
    {
        $destination=$this->siteId('SILO');
        $this->db->beginTransaction();
        try {
            $stock=$this->row("SELECT * FROM agricultural_stocks WHERE id=? AND status IN ('available','reserved') FOR UPDATE",[$data['stock_id']??0]);
            if(!$stock)throw new RuntimeException('Sélectionnez un stock agricole disponible.');
            $site=$this->farmSite($stock['site_id']);
            // The stock lock serializes concurrent creations, including unreserved drafts.
            $existing=$this->row("SELECT t.transport_number,d.document_number FROM agricultural_transports t LEFT JOIN documents d ON d.entity_type='agricultural_transports' AND d.entity_id=t.id AND d.document_type_id=(SELECT id FROM document_types WHERE code='BT' LIMIT 1) AND d.deleted_at IS NULL WHERE t.stock_id=? AND t.status='draft' AND t.deleted_at IS NULL LIMIT 1 FOR UPDATE",[$stock['id']]);
            if($existing){
                $reference=$existing['document_number']?:$existing['transport_number'];
                throw new RuntimeException('Ce stock possède déjà le BT en brouillon '.$reference.'. Consultez ce bon dans Agriculture → Transports ou sélectionnez un autre stock.');
            }
            $qty=(float)($data['quantity_kg']??0);
            $available=(float)$stock['physical_quantity_kg']-(float)$stock['reserved_quantity_kg'];
            if($qty<=0||$qty>$available)throw new RuntimeException('Stock agricole disponible insuffisant.');
            $route=$this->transportRoute($site,$destination,$data['route_notes']??'');
            $number='BT-AG-'.date('YmdHis').'-'.random_int(100,999);
            $this->query("INSERT INTO agricultural_transports(stock_id,source_site_id,destination_site_id,transport_number,quantity_kg,status,truck_plate,driver_name,route_description,created_by) VALUES(:stock,:source,:destination,:number,:qty,'draft',:truck,:driver,:route,:user)",['stock'=>$stock['id'],'source'=>$site,'destination'=>$destination,'number'=>$number,'qty'=>$qty,'truck'=>strtoupper(trim($data['truck_plate'])),'driver'=>trim($data['driver_name']),'route'=>$route,'user'=>$user['id']]);
            $id=(int)$this->db->lastInsertId();
            require_once dirname(__DIR__).'/services/DocumentService.php';
            (new DocumentService($this->db))->register('BT',$site,'agricultural_transports',$id,'draft',date('Y-m-d H:i:s'),$user);
            $this->db->commit();
            return $id;
        }catch(Exception$e){
            $this->db->rollBack();
            throw $e;
        }
    }

    private function transportRoute($sourceId, $destinationId, $notes)
    {
        if (!is_string($notes)) {
            throw new RuntimeException('Les précisions sur le trajet doivent être du texte.');
        }
        $source = $this->row('SELECT name FROM sites WHERE id=?', [$sourceId]);
        $destination = $this->row('SELECT name FROM sites WHERE id=?', [$destinationId]);
        $route = $source['name'] . ' → ' . $destination['name'];
        $notes = trim($notes);
        if ($notes !== '') $route .= ' · ' . $notes;
        if (mb_strlen($route, 'UTF-8') > 255) {
            throw new RuntimeException('Le trajet complet est limité à 255 caractères. Réduisez les précisions ou les noms des sites.');
        }
        return $route;
    }

    public function approveTransport($id,array $user)
    {
        $this->db->beginTransaction();try{$t=$this->row('SELECT * FROM agricultural_transports WHERE id=? FOR UPDATE',[$id]);if(!$t||$t['status']!=='draft')throw new RuntimeException('BT non validable ou déjà validé.');Auth::requireSiteAccess($t['source_site_id']);$selfApproval=(int)$t['created_by']===(int)$user['id'];if($selfApproval&&!Auth::hasRole(['administrateur']))throw new RuntimeException('Le créateur ne peut pas valider le BT.');$s=$this->row('SELECT * FROM agricultural_stocks WHERE id=? FOR UPDATE',[$t['stock_id']]);$available=(float)$s['physical_quantity_kg']-(float)$s['reserved_quantity_kg'];if($available<(float)$t['quantity_kg'])throw new RuntimeException('Stock agricole disponible insuffisant.');$rb=(float)$s['reserved_quantity_kg'];$ra=$rb+(float)$t['quantity_kg'];$this->query("UPDATE agricultural_stocks SET reserved_quantity_kg=:reserved,status='reserved' WHERE id=:id",['reserved'=>$ra,'id'=>$s['id']]);$this->movement($s['id'],'reserve',$t['quantity_kg'],$s['physical_quantity_kg'],$s['physical_quantity_kg'],$rb,$ra,'agricultural_transports',$id,$user);$this->query("UPDATE agricultural_transports SET status='approved',approved_by=:user WHERE id=:id",['user'=>$user['id'],'id'=>$id]);$this->approveDocument($id,$user);$message=$selfApproval?'Auto-validation administrative du BT et réservation atomique du stock agricole.':'Validation du BT et réservation atomique du stock agricole.';$this->logActivity('approve_farm_transport','agriculture','agricultural_transports',$id,$message,$t,['status'=>'approved','reserved_quantity_kg'=>$ra,'administrative_override'=>$selfApproval],$user);$this->db->commit();}catch(Exception$e){$this->db->rollBack();throw$e;}
    }

    public function dispatchTransport($id, array $user)
    {
        $this->db->beginTransaction();
        try {
            $transport = $this->row('SELECT * FROM agricultural_transports WHERE id=? AND deleted_at IS NULL FOR UPDATE', [$id]);
            if (!$transport || $transport['status'] !== 'approved') {
                throw new RuntimeException('BT non expédiable ou déjà expédié.');
            }
            Auth::requireSiteAccess($transport['source_site_id']);
            $stock = $this->row('SELECT * FROM agricultural_stocks WHERE id=? FOR UPDATE', [$transport['stock_id']]);
            $quantity = (float) $transport['quantity_kg'];
            if (!$stock || (int) $stock['site_id'] !== (int) $transport['source_site_id'] || $quantity <= 0
                || (float) $stock['physical_quantity_kg'] < $quantity || (float) $stock['reserved_quantity_kg'] < $quantity) {
                throw new RuntimeException('Stock réservé incohérent.');
            }
            // The parent BT lock and its approved status serialize dispatch attempts.
            // Bridge creation and stock movements use this same transaction.
            $bridgeId = $this->createWeighbridgeTransport($transport, $stock, $user);
            $physicalAfter = (float) $stock['physical_quantity_kg'] - $quantity;
            $reservedAfter = (float) $stock['reserved_quantity_kg'] - $quantity;
            $this->query("UPDATE agricultural_stocks SET physical_quantity_kg=:physical,reserved_quantity_kg=:reserved,status=IF(:reserved2>0,'reserved',IF(:physical2>0,'available','in_transit')) WHERE id=:id", [
                'physical' => $physicalAfter, 'reserved' => $reservedAfter, 'reserved2' => $reservedAfter, 'physical2' => $physicalAfter, 'id' => $stock['id'],
            ]);
            $this->movement($stock['id'], 'transport_out', $quantity, $stock['physical_quantity_kg'], $physicalAfter, $stock['reserved_quantity_kg'], $reservedAfter, 'agricultural_transports', $id, $user);
            $this->query("UPDATE agricultural_transports SET status='in_transit',dispatched_by=:user,dispatched_at=NOW() WHERE id=:id", ['user' => $user['id'], 'id' => $id]);
            $this->logActivity('dispatch_farm_transport', 'agriculture', 'agricultural_transports', $id,
                'Sortie du stock agricole et mise à disposition du BT au pont-bascule.', $transport,
                ['status' => 'in_transit', 'weighbridge_transport_id' => $bridgeId], $user);
            $this->db->commit();
            return $bridgeId;
        } catch (Exception $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }
    }

    private function createWeighbridgeTransport(array $transport, array $stock, array $user)
    {
        $existing = $this->row('SELECT id FROM weighbridge_transports WHERE agricultural_transport_id=? FOR UPDATE', [$transport['id']]);
        if ($existing) { throw new RuntimeException('Ce BT agricole est déjà relié au pont-bascule.'); }

        $destination = $this->row("SELECT id,code FROM sites WHERE id=? AND status='active' AND deleted_at IS NULL", [$transport['destination_site_id']]);
        if (!$destination) { throw new RuntimeException('Site destinataire indisponible.'); }
        // Legacy agricultural BTs name the mill (MINO); its receiving bridge is SILO.
        $receivingSite = $destination['code'] === 'MINO' ? $this->siteId('SILO') : (int) $destination['id'];
        $document = $this->row("SELECT d.document_number FROM documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE dt.code='BT' AND d.entity_type='agricultural_transports' AND d.entity_id=? AND d.status='approved' AND d.deleted_at IS NULL FOR UPDATE", [$transport['id']]);
        if (!$document) { throw new RuntimeException('Le document BT agricole approuvé est introuvable.'); }
        $plate = strtoupper(trim((string) $transport['truck_plate']));
        if ($plate === '' || strlen($plate) > 50) { throw new RuntimeException('Immatriculation du camion obligatoire et limitée à 50 caractères.'); }
        if (trim((string) $transport['driver_name']) === '') { throw new RuntimeException('Le chauffeur doit être renseigné sur le BT agricole.'); }
        $truck = $this->row('SELECT * FROM trucks WHERE plate_number=? FOR UPDATE', [$plate]);
        if ($truck) {
            if ($truck['deleted_at'] !== null || !in_array($truck['status'], ['active', 'validated'], true)) {
                throw new RuntimeException('Le camion du BT est inactif. Réactivez-le avant l’expédition.');
            }
            $this->query('UPDATE trucks SET driver_name=:driver WHERE id=:id', ['driver' => $transport['driver_name'], 'id' => $truck['id']]);
            $truckId = $truck['id'];
        } else {
            $this->query("INSERT INTO trucks(plate_number,driver_name,status) VALUES(:plate,:driver,'active')", ['plate' => $plate, 'driver' => $transport['driver_name']]);
            $truckId = (int) $this->db->lastInsertId();
        }
        // Keep the original official BT number; do not issue a second BT.
        $this->query("INSERT INTO weighbridge_transports(site_id,origin_type,origin_site_id,agricultural_transport_id,supplier_id,product_id,truck_id,transport_reference,shipped_quantity_kg,route_description,toll_amount,status,loaded_at,dispatched_at,created_by)
            VALUES(:site,'internal_farm',:origin,:agricultural,NULL,:product,:truck,:reference,:quantity,:route,0,'in_transit',NOW(),NOW(),:user)", [
            'site' => $receivingSite, 'origin' => $transport['source_site_id'], 'agricultural' => $transport['id'],
            'product' => $stock['product_id'], 'truck' => $truckId, 'reference' => $document['document_number'],
            'quantity' => $transport['quantity_kg'], 'route' => $transport['route_description'], 'user' => $user['id'],
        ]);
        $id = (int) $this->db->lastInsertId();
        $this->query("INSERT INTO activity_logs(user_id,site_id,action,module,entity_type,entity_id,description,new_values,user_agent)
            VALUES(:user,:site,'receive_farm_dispatch','pont-bascule','weighbridge_transports',:id,:description,:values,:agent)", [
            'user' => $user['id'], 'site' => $receivingSite, 'id' => $id,
            'description' => 'BT agricole disponible pour la pesée d’entrée : ' . $document['document_number'],
            'values' => json_encode(['agricultural_transport_id' => $transport['id'], 'status' => 'in_transit', 'quantity_kg' => $transport['quantity_kg']], JSON_UNESCAPED_UNICODE),
            'agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255),
        ]);
        return $id;
    }

    private function farmSite($requestedSiteId=null){$site=$requestedSiteId!==null&&$requestedSiteId!==''?(int)$requestedSiteId:Auth::requireCurrentSite();Auth::requireSiteAccess($site);$code=$this->query('SELECT code FROM sites WHERE id=:id AND status=\'active\' AND deleted_at IS NULL',['id'=>$site])->fetchColumn();if(!in_array($code,['FARM-MUT','FARM-DIK'],true))throw new RuntimeException('Sélectionnez la ferme MUTALA ou DIKAPA.');return$site; }
    private function scopedCampaignPlot($id){$row=$this->row("SELECT cp.*,c.site_id,p.site_id plot_site_id,s.code site_code FROM agricultural_campaign_plots cp JOIN agricultural_campaigns c ON c.id=cp.campaign_id JOIN agricultural_plots p ON p.id=cp.plot_id JOIN sites s ON s.id=c.site_id WHERE cp.id=? AND c.deleted_at IS NULL AND p.deleted_at IS NULL",[$id]);if(!$row||(int)$row['site_id']!==(int)$row['plot_site_id'])throw new RuntimeException('Campagne/parcelle hors site.');$this->farmSite($row['site_id']);return$row;}
    private function siteId($code){$id=$this->query("SELECT id FROM sites WHERE code=:code AND status='active' AND deleted_at IS NULL",['code'=>$code])->fetchColumn();if(!$id)throw new RuntimeException('Site requis introuvable.');return(int)$id;}
    private function movement($stock,$type,$qty,$pb,$pa,$rb,$ra,$rt,$rid,$user){$this->query('INSERT INTO agricultural_stock_movements(stock_id,movement_type,quantity_kg,physical_before_kg,physical_after_kg,reserved_before_kg,reserved_after_kg,reference_type,reference_id,movement_at,created_by) VALUES(:stock,:type,:qty,:pb,:pa,:rb,:ra,:rt,:rid,NOW(),:user)',['stock'=>$stock,'type'=>$type,'qty'=>$qty,'pb'=>$pb,'pa'=>$pa,'rb'=>$rb,'ra'=>$ra,'rt'=>$rt,'rid'=>$rid,'user'=>$user['id']]);}
    private function approveDocument($transport,array$user){$d=$this->row("SELECT d.* FROM documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE dt.code='BT' AND d.entity_type='agricultural_transports' AND d.entity_id=? FOR UPDATE",[$transport]);if($d){$this->query("UPDATE documents SET status='approved',validated_by=:user,validated_at=NOW() WHERE id=:id",['user'=>$user['id'],'id'=>$d['id']]);$this->query("INSERT INTO document_status_history(document_id,old_status,new_status,changed_by,reason) VALUES(:document,:old,'approved',:user,'Validation BT agricole')",['document'=>$d['id'],'old'=>$d['status'],'user'=>$user['id']]);}}
    private function row($sql,array$params=[]){$q=$this->db->prepare($sql);$q->execute($params);return$q->fetch();}
}
