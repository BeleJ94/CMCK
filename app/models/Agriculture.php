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
        return $this->query("SELECT c.*,s.name season_name,si.code site_code,COALESCE(SUM(cp.actual_area_ha),0) actual_area_ha,COALESCE(SUM(cp.actual_area_ha*cp.target_tons_per_ha),0) target_tons,COALESCE(SUM(h.net_weight_kg),0)/1000 realized_tons FROM agricultural_campaigns c JOIN agricultural_seasons s ON s.id=c.season_id JOIN sites si ON si.id=c.site_id LEFT JOIN agricultural_campaign_plots cp ON cp.campaign_id=c.id LEFT JOIN agricultural_harvests h ON h.campaign_plot_id=cp.id AND h.status='validated' AND h.deleted_at IS NULL WHERE c.deleted_at IS NULL{$scope} GROUP BY c.id ORDER BY c.start_date DESC",$params)->fetchAll();
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
        return $this->query("SELECT h.*,c.code campaign_code,p.code plot_code,v.name variety_name,si.code site_code,si.name site_name,st.id stock_id,st.physical_quantity_kg,st.reserved_quantity_kg,st.status stock_status FROM agricultural_harvests h JOIN agricultural_campaign_plots cp ON cp.id=h.campaign_plot_id JOIN agricultural_campaigns c ON c.id=cp.campaign_id JOIN agricultural_plots p ON p.id=cp.plot_id JOIN agricultural_varieties v ON v.id=cp.variety_id JOIN sites si ON si.id=h.site_id LEFT JOIN agricultural_stocks st ON st.harvest_id=h.id WHERE h.deleted_at IS NULL{$scope} ORDER BY h.harvested_at DESC",$params)->fetchAll();
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
        $cp=$this->scopedCampaignPlot($data['campaign_plot_id']);$this->db->beginTransaction();try{$this->query("INSERT INTO agricultural_works(campaign_plot_id,work_type,description,worked_at,status,other_cost,created_by) VALUES(:cp,:type,:description,:at,'completed',:cost,:user)",['cp'=>$cp['id'],'type'=>trim($data['work_type']),'description'=>trim($data['description']??'')?:null,'at'=>$data['worked_at'],'cost'=>(float)($data['other_cost']??0),'user'=>$user['id']]);$id=(int)$this->db->lastInsertId();if(!empty($data['worker_id'])&&(float)$data['days_worked']>0){$worker=$this->row('SELECT * FROM agricultural_workers WHERE id=? AND site_id=? AND deleted_at IS NULL',[$data['worker_id'],$cp['site_id']]);if(!$worker)throw new RuntimeException('Main-d’œuvre hors site.');$this->query('INSERT INTO agricultural_work_labor(work_id,worker_id,days_worked,cost) VALUES(:work,:worker,:days,:cost)',['work'=>$id,'worker'=>$worker['id'],'days'=>$data['days_worked'],'cost'=>(float)$data['days_worked']*(float)$worker['daily_rate']]);}if(!empty($data['equipment_id'])&&(float)$data['hours_used']>0){$equipment=$this->row('SELECT * FROM agricultural_equipment WHERE id=? AND site_id=? AND deleted_at IS NULL',[$data['equipment_id'],$cp['site_id']]);if(!$equipment)throw new RuntimeException('Matériel hors site.');$this->query('INSERT INTO agricultural_work_equipment(work_id,equipment_id,hours_used,cost) VALUES(:work,:equipment,:hours,:cost)',['work'=>$id,'equipment'=>$equipment['id'],'hours'=>$data['hours_used'],'cost'=>(float)$data['hours_used']*(float)$equipment['hourly_cost']]);}$this->db->commit();return$id;}catch(Exception$e){$this->db->rollBack();throw$e;}
    }

    public function createHarvest(array $data,array $user)
    {
        $cp=$this->scopedCampaignPlot($data['campaign_plot_id']);$gross=(float)$data['gross_weight_kg'];$tare=(float)$data['tare_weight_kg'];$dry=(float)$data['drying_loss_kg'];$net=$gross-$tare-$dry;if($gross<=0||$tare<0||$dry<0||$net<=0)throw new RuntimeException('Poids de récolte invalides.');$number='REC-'.$cp['site_code'].'-'.date('YmdHis').'-'.random_int(100,999);$yield=$net/1000/(float)$cp['actual_area_ha'];
        $this->query("INSERT INTO agricultural_harvests(site_id,campaign_plot_id,harvest_number,harvested_at,gross_weight_kg,tare_weight_kg,drying_loss_kg,net_weight_kg,moisture_before,moisture_after,drying_started_at,drying_ended_at,yield_tons_per_ha,status,created_by) VALUES(:site,:cp,:number,:at,:gross,:tare,:dry,:net,:mb,:ma,:ds,:de,:yield,'submitted',:user)",['site'=>$cp['site_id'],'cp'=>$cp['id'],'number'=>$number,'at'=>$data['harvested_at'],'gross'=>$gross,'tare'=>$tare,'dry'=>$dry,'net'=>$net,'mb'=>$data['moisture_before']?:null,'ma'=>$data['moisture_after']?:null,'ds'=>$data['drying_started_at']?:null,'de'=>$data['drying_ended_at']?:null,'yield'=>$yield,'user'=>$user['id']]);return(int)$this->db->lastInsertId();
    }

    public function validateHarvest($id,array $user)
    {
        $this->db->beginTransaction();try{$h=$this->row("SELECT h.*,cp.variety_id,v.product_id FROM agricultural_harvests h JOIN agricultural_campaign_plots cp ON cp.id=h.campaign_plot_id JOIN agricultural_varieties v ON v.id=cp.variety_id WHERE h.id=? FOR UPDATE",[$id]);if(!$h||$h['status']!=='submitted')throw new RuntimeException('Récolte non validable ou déjà validée.');Auth::requireSiteAccess($h['site_id']);$selfApproval=(int)$h['created_by']===(int)$user['id'];if($selfApproval&&!Auth::hasRole(['administrateur']))throw new RuntimeException('Le créateur ne peut pas valider la récolte.');$this->query("INSERT INTO agricultural_stocks(site_id,harvest_id,product_id,variety_id,physical_quantity_kg,reserved_quantity_kg,status) VALUES(:site,:harvest,:product,:variety,:qty,0,'available')",['site'=>$h['site_id'],'harvest'=>$h['id'],'product'=>$h['product_id'],'variety'=>$h['variety_id'],'qty'=>$h['net_weight_kg']]);$stock=(int)$this->db->lastInsertId();$this->movement($stock,'harvest_in',$h['net_weight_kg'],0,$h['net_weight_kg'],0,0,'agricultural_harvests',$id,$user);$this->query("UPDATE agricultural_harvests SET status='validated',validated_by=:user,validated_at=NOW() WHERE id=:id",['user'=>$user['id'],'id'=>$id]);$message=$selfApproval?'Validation administrative de sa propre récolte et entrée atomique en stock agricole.':'Validation récolte et entrée atomique en stock agricole.';$this->logActivity('validate_harvest','agriculture','agricultural_harvests',$id,$message,$h,['stock_id'=>$stock,'administrative_override'=>$selfApproval],$user);$this->db->commit();return$stock;}catch(Exception$e){$this->db->rollBack();throw$e;}
    }

    public function createTransport(array $data,array $user)
    {
        $destination=$this->siteId('MINO');$this->db->beginTransaction();try{$stock=$this->row("SELECT * FROM agricultural_stocks WHERE id=? AND status='available' FOR UPDATE",[$data['stock_id']??0]);if(!$stock)throw new RuntimeException('Sélectionnez un stock agricole disponible.');$site=$this->farmSite($stock['site_id']);$qty=(float)($data['quantity_kg']??0);$available=(float)$stock['physical_quantity_kg']-(float)$stock['reserved_quantity_kg'];if($qty<=0||$qty>$available)throw new RuntimeException('Stock agricole disponible insuffisant.');$number='BT-AG-'.date('YmdHis').'-'.random_int(100,999);$this->query("INSERT INTO agricultural_transports(stock_id,source_site_id,destination_site_id,transport_number,quantity_kg,status,truck_plate,driver_name,route_description,created_by) VALUES(:stock,:source,:destination,:number,:qty,'draft',:truck,:driver,:route,:user)",['stock'=>$stock['id'],'source'=>$site,'destination'=>$destination,'number'=>$number,'qty'=>$qty,'truck'=>strtoupper(trim($data['truck_plate'])),'driver'=>trim($data['driver_name']),'route'=>trim($data['route_description']??'')?:null,'user'=>$user['id']]);$id=(int)$this->db->lastInsertId();require_once dirname(__DIR__).'/services/DocumentService.php';(new DocumentService($this->db))->register('BT',$site,'agricultural_transports',$id,'draft',date('Y-m-d H:i:s'),$user);$this->db->commit();return$id;}catch(Exception$e){$this->db->rollBack();throw$e;}
    }

    public function approveTransport($id,array $user)
    {
        $this->db->beginTransaction();try{$t=$this->row('SELECT * FROM agricultural_transports WHERE id=? FOR UPDATE',[$id]);if(!$t||$t['status']!=='draft')throw new RuntimeException('BT non validable ou déjà validé.');Auth::requireSiteAccess($t['source_site_id']);$selfApproval=(int)$t['created_by']===(int)$user['id'];if($selfApproval&&!Auth::hasRole(['administrateur']))throw new RuntimeException('Le créateur ne peut pas valider le BT.');$s=$this->row('SELECT * FROM agricultural_stocks WHERE id=? FOR UPDATE',[$t['stock_id']]);$available=(float)$s['physical_quantity_kg']-(float)$s['reserved_quantity_kg'];if($available<(float)$t['quantity_kg'])throw new RuntimeException('Stock agricole disponible insuffisant.');$rb=(float)$s['reserved_quantity_kg'];$ra=$rb+(float)$t['quantity_kg'];$this->query("UPDATE agricultural_stocks SET reserved_quantity_kg=:reserved,status='reserved' WHERE id=:id",['reserved'=>$ra,'id'=>$s['id']]);$this->movement($s['id'],'reserve',$t['quantity_kg'],$s['physical_quantity_kg'],$s['physical_quantity_kg'],$rb,$ra,'agricultural_transports',$id,$user);$this->query("UPDATE agricultural_transports SET status='approved',approved_by=:user WHERE id=:id",['user'=>$user['id'],'id'=>$id]);$this->approveDocument($id,$user);$message=$selfApproval?'Auto-validation administrative du BT et réservation atomique du stock agricole.':'Validation du BT et réservation atomique du stock agricole.';$this->logActivity('approve_farm_transport','agriculture','agricultural_transports',$id,$message,$t,['status'=>'approved','reserved_quantity_kg'=>$ra,'administrative_override'=>$selfApproval],$user);$this->db->commit();}catch(Exception$e){$this->db->rollBack();throw$e;}
    }

    public function dispatchTransport($id,array $user)
    {
        $this->db->beginTransaction();try{$t=$this->row('SELECT * FROM agricultural_transports WHERE id=? FOR UPDATE',[$id]);if(!$t||$t['status']!=='approved')throw new RuntimeException('BT non expédiable ou déjà expédié.');Auth::requireSiteAccess($t['source_site_id']);$s=$this->row('SELECT * FROM agricultural_stocks WHERE id=? FOR UPDATE',[$t['stock_id']]);$q=(float)$t['quantity_kg'];if((float)$s['physical_quantity_kg']<$q||(float)$s['reserved_quantity_kg']<$q)throw new RuntimeException('Stock réservé incohérent.');$pa=(float)$s['physical_quantity_kg']-$q;$ra=(float)$s['reserved_quantity_kg']-$q;$this->query("UPDATE agricultural_stocks SET physical_quantity_kg=:physical,reserved_quantity_kg=:reserved,status=IF(:physical2>0,'available','in_transit') WHERE id=:id",['physical'=>$pa,'reserved'=>$ra,'physical2'=>$pa,'id'=>$s['id']]);$this->movement($s['id'],'transport_out',$q,$s['physical_quantity_kg'],$pa,$s['reserved_quantity_kg'],$ra,'agricultural_transports',$id,$user);$this->query("UPDATE agricultural_transports SET status='in_transit',dispatched_by=:user,dispatched_at=NOW() WHERE id=:id",['user'=>$user['id'],'id'=>$id]);$this->logActivity('dispatch_farm_transport','agriculture','agricultural_transports',$id,'Sortie du stock agricole et mise en transit.',$t,['status'=>'in_transit'], $user);$this->db->commit();}catch(Exception$e){$this->db->rollBack();throw$e;}
    }

    private function farmSite($requestedSiteId=null){$site=$requestedSiteId!==null&&$requestedSiteId!==''?(int)$requestedSiteId:Auth::requireCurrentSite();Auth::requireSiteAccess($site);$code=$this->query('SELECT code FROM sites WHERE id=:id AND status=\'active\' AND deleted_at IS NULL',['id'=>$site])->fetchColumn();if(!in_array($code,['FARM-MUT','FARM-DIK'],true))throw new RuntimeException('Sélectionnez la ferme MUTALA ou DIKAPA.');return$site; }
    private function scopedCampaignPlot($id){$row=$this->row("SELECT cp.*,c.site_id,p.site_id plot_site_id,s.code site_code FROM agricultural_campaign_plots cp JOIN agricultural_campaigns c ON c.id=cp.campaign_id JOIN agricultural_plots p ON p.id=cp.plot_id JOIN sites s ON s.id=c.site_id WHERE cp.id=? AND c.deleted_at IS NULL AND p.deleted_at IS NULL",[$id]);if(!$row||(int)$row['site_id']!==(int)$row['plot_site_id'])throw new RuntimeException('Campagne/parcelle hors site.');$this->farmSite($row['site_id']);return$row;}
    private function siteId($code){$id=$this->query("SELECT id FROM sites WHERE code=:code AND status='active' AND deleted_at IS NULL",['code'=>$code])->fetchColumn();if(!$id)throw new RuntimeException('Site requis introuvable.');return(int)$id;}
    private function movement($stock,$type,$qty,$pb,$pa,$rb,$ra,$rt,$rid,$user){$this->query('INSERT INTO agricultural_stock_movements(stock_id,movement_type,quantity_kg,physical_before_kg,physical_after_kg,reserved_before_kg,reserved_after_kg,reference_type,reference_id,movement_at,created_by) VALUES(:stock,:type,:qty,:pb,:pa,:rb,:ra,:rt,:rid,NOW(),:user)',['stock'=>$stock,'type'=>$type,'qty'=>$qty,'pb'=>$pb,'pa'=>$pa,'rb'=>$rb,'ra'=>$ra,'rt'=>$rt,'rid'=>$rid,'user'=>$user['id']]);}
    private function approveDocument($transport,array$user){$d=$this->row("SELECT d.* FROM documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE dt.code='BT' AND d.entity_type='agricultural_transports' AND d.entity_id=? FOR UPDATE",[$transport]);if($d){$this->query("UPDATE documents SET status='approved',validated_by=:user,validated_at=NOW() WHERE id=:id",['user'=>$user['id'],'id'=>$d['id']]);$this->query("INSERT INTO document_status_history(document_id,old_status,new_status,changed_by,reason) VALUES(:document,:old,'approved',:user,'Validation BT agricole')",['document'=>$d['id'],'old'=>$d['status'],'user'=>$user['id']]);}}
    private function row($sql,array$params=[]){$q=$this->db->prepare($sql);$q->execute($params);return$q->fetch();}
}
