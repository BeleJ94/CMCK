<?php

class AgriculturalWorkService
{
    private $db;
    public const TYPES = ['Labour','Semis','Fertilisation','Désherbage','Traitement','Irrigation','Récolte','Entretien','Autre'];

    public function __construct(PDO $db=null){$this->db=$db?:Database::getInstance()->connection();}
    private function query($sql,array $params=[]){$s=$this->db->prepare($sql);$s->execute($params);return $s;}
    private function row($sql,array $params=[]){return $this->query($sql,$params)->fetch();}
    private function text($value,$label,$max,$required=true){if(!is_scalar($value)&&$value!==null)throw new RuntimeException($label.' invalide.');$v=trim((string)$value);if(($required&&$v==='')||mb_strlen($v)>$max)throw new RuntimeException($label.' : renseignez au maximum '.$max.' caractères.');return $v;}
    private function amount($value,$label,$positive=false,$max=9999999999){if(!is_scalar($value)||!is_numeric($value)||!is_finite((float)$value)||(float)$value<0||($positive&&(float)$value<=0)||(float)$value>$max)throw new RuntimeException($label.' invalide.');return (float)$value;}
    private function context($id){
        $r=$this->row("SELECT cp.*,c.site_id,c.status campaign_status,c.start_date,c.end_date,s.code site_code FROM agricultural_campaign_plots cp JOIN agricultural_campaigns c ON c.id=cp.campaign_id JOIN agricultural_plots p ON p.id=cp.plot_id AND p.site_id=c.site_id JOIN sites s ON s.id=c.site_id WHERE cp.id=? AND c.deleted_at IS NULL AND p.deleted_at IS NULL AND s.deleted_at IS NULL AND s.status='active' FOR UPDATE",[$id]);
        if(!$r)throw new RuntimeException('Parcelle planifiée introuvable.');Auth::requireSiteAccess($r['site_id']);
        if(!in_array($r['site_code'],['FARM-MUT','FARM-DIK'],true))throw new RuntimeException('Sélectionnez une ferme agricole.');
        return $r;
    }
    private function editable(array $cp){if(in_array($cp['campaign_status'],['closed','cancelled'],true)||$cp['status']==='closed')throw new RuntimeException('Cette campagne ou planification est clôturée ou annulée. Aucune saisie ou correction n’est autorisée.');}
    public function save(array $data,array $user,$id=null){
        $this->db->beginTransaction();
        try{
            $old=null;
            if($id){$work=$this->row('SELECT * FROM agricultural_works WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$id]);if(!$work)throw new RuntimeException('Travail introuvable.');$cp=$this->context($work['campaign_plot_id']);Auth::requirePermission('agriculture','update',$cp['site_id']);$this->editable($cp);if($work['status']!=='completed')throw new RuntimeException('Seul un travail réalisé peut être corrigé.');if((int)($data['revision']??0)!==(int)$work['revision'])throw new RuntimeException('Ce travail a été modifié. Fermez le formulaire et actualisez la liste avant de recommencer.');if((int)($data['campaign_plot_id']??0)!==(int)$work['campaign_plot_id'])throw new RuntimeException('La parcelle ne peut pas être changée lors d’une correction.');$old=$this->snapshot($id);$reason=$this->text($data['reason']??'','Motif de correction',500);}
            else{$cp=$this->context($data['campaign_plot_id']??0);Auth::requirePermission('agriculture','update',$cp['site_id']);$this->editable($cp);$reason=null;
                $key=$this->text($data['submission_key']??'','Identifiant de soumission',64);if(!preg_match('/^[a-f0-9]{32,64}$/D',$key))throw new RuntimeException('Rechargez le formulaire avant de soumettre.');
                $existing=$this->row('SELECT id,created_by,campaign_plot_id FROM agricultural_works WHERE submission_key=? FOR UPDATE',[$key]);if($existing){if((int)$existing['created_by']!==(int)$user['id']||(int)$existing['campaign_plot_id']!==(int)$cp['id'])throw new RuntimeException('Identifiant de soumission déjà utilisé.');$this->db->commit();return (int)$existing['id'];}
            }
            $type=$this->text($data['work_type']??'','Type de travail',100);if(!in_array($type,self::TYPES,true))throw new RuntimeException('Sélectionnez un type de travail.');if($type==='Autre')$type='Autre — '.$this->text($data['custom_type']??'','Précision du travail',85);
            $date=$this->text($data['worked_at']??'','Date et heure du travail',19,false);if($date==='')throw new RuntimeException('Date et heure manquantes : renseignez le jour, le mois, l’année, les heures et les minutes.');$date=str_replace('T',' ',$date);if(strlen($date)===16)$date.=':00';
            $zone=new DateTimeZone(config('app.timezone','Africa/Lubumbashi'));$now=new DateTimeImmutable('now',$zone);
            $parsed=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$date,$zone);
            if(!$parsed||$parsed->format('Y-m-d H:i:s')!==$date)throw new RuntimeException('Date et heure invalides : renseignez une date existante avec les heures et les minutes.');
            if($cp['start_date']>$now->format('Y-m-d'))throw new RuntimeException('Cette campagne commence le '.date('d/m/Y',strtotime($cp['start_date'])).'. Choisissez une campagne déjà commencée pour déclarer un travail réalisé.');
            if(substr($date,0,10)<$cp['start_date']||substr($date,0,10)>$cp['end_date'])throw new RuntimeException('Date hors campagne : le travail doit être réalisé entre le '.date('d/m/Y',strtotime($cp['start_date'])).' et le '.date('d/m/Y',strtotime($cp['end_date'])).'. Modifiez la date ou choisissez la campagne correspondante.');
            if($parsed>$now)throw new RuntimeException('Date ou heure future : indiquez une intervention déjà réalisée, au plus tard le '.$now->format('d/m/Y à H:i').' ('.$zone->getName().').');
            $responsible=$this->text($data['responsible_name']??'','Responsable du travail',160);$currency=$this->text($data['currency']??'USD','Devise',3);if(!in_array($currency,['USD','CDF'],true))throw new RuntimeException('Choisissez USD ou CDF.');if($old&&!empty($old['work']['currency'])&&$old['work']['currency']!==$currency)throw new RuntimeException('La devise d’un travail existant ne peut pas être changée.');
            $exchangeRate=$currency==='USD'?1:round($this->amount($data['exchange_rate']??null,'Taux CDF pour 1 USD',true,999999999),6);
            if($exchangeRate<=0)throw new RuntimeException('Le taux doit être au moins égal à 0,000001 CDF pour 1 USD.');
            $other=round($this->amount($data['other_cost']??0,'Autres coûts'),4);$otherReason=$this->text($data['other_cost_reason']??'','Justification des autres coûts',500,$other>0);
            $labor=$this->lines($data['labor']??[],'labor',$cp);$equipment=$this->lines($data['equipment']??[],'equipment',$cp);
            $total=$other+array_sum(array_column($labor,'cost'))+array_sum(array_column($equipment,'cost'));if($total>9999999999)throw new RuntimeException('Coût total trop élevé.');
            $values=['cp'=>$cp['id'],'type'=>$type,'description'=>$this->text($data['description']??'','Description',2000,false),'at'=>$date,'other'=>$other,'responsible'=>$responsible,'currency'=>$currency,'exchange_rate'=>$exchangeRate,'other_reason'=>$otherReason];
            if($id){$values['id']=$id;$this->query('UPDATE agricultural_works SET campaign_plot_id=:cp,work_type=:type,description=:description,worked_at=:at,other_cost=:other,responsible_name=:responsible,currency=:currency,exchange_rate=:exchange_rate,other_cost_reason=:other_reason,revision=revision+1 WHERE id=:id',$values);$this->query('DELETE FROM agricultural_work_labor WHERE work_id=?',[$id]);$this->query('DELETE FROM agricultural_work_equipment WHERE work_id=?',[$id]);}
            else{$values['user']=$user['id'];$values['key']=$key;$this->query("INSERT INTO agricultural_works(campaign_plot_id,work_type,description,worked_at,status,other_cost,responsible_name,currency,exchange_rate,other_cost_reason,created_by,submission_key) VALUES(:cp,:type,:description,:at,'completed',:other,:responsible,:currency,:exchange_rate,:other_reason,:user,:key)",$values);$id=(int)$this->db->lastInsertId();}
            foreach($labor as$l)$this->query('INSERT INTO agricultural_work_labor(work_id,worker_id,days_worked,unit_rate,cost) VALUES(?,?,?,?,?)',[$id,$l['id'],$l['quantity'],$l['rate'],$l['cost']]);
            foreach($equipment as$l)$this->query('INSERT INTO agricultural_work_equipment(work_id,equipment_id,hours_used,unit_rate,cost) VALUES(?,?,?,?,?)',[$id,$l['id'],$l['quantity'],$l['rate'],$l['cost']]);
            $this->history($id,$old?'correct':'create',$reason,$old,$this->snapshot($id),$user,$cp['site_id']);$this->db->commit();return $id;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    private function lines($rows,$kind,$cp){
        if(!is_array($rows)||count($rows)>50)throw new RuntimeException('Liste de ressources invalide (50 lignes maximum).');$result=[];$seen=[];
        $table=$kind==='labor'?'agricultural_workers':'agricultural_equipment';
        foreach($rows as$r){if(!is_array($r))throw new RuntimeException('Ligne de ressource invalide.');$rawId=$r['id']??'';$rawQty=$r['quantity']??'';if($rawId===''&&($rawQty===''||(is_numeric($rawQty)&&(float)$rawQty===0)))continue;if(!ctype_digit((string)$rawId)||(int)$rawId<=0)throw new RuntimeException('Choisissez une ressource pour chaque durée saisie.');$id=(int)$rawId;$qty=$this->amount($rawQty,'Durée de la ressource',true,999999);if(abs($qty-round($qty,2))>0.000001)throw new RuntimeException('Les durées acceptent deux décimales au maximum.');if(isset($seen[$id]))throw new RuntimeException('Une ressource figure plusieurs fois. Regroupez sa durée sur une ligne.');$seen[$id]=true;
            $resource=$this->row("SELECT * FROM {$table} WHERE id=? AND site_id=? AND status='active' AND deleted_at IS NULL FOR UPDATE",[$id,$cp['site_id']]);if(!$resource)throw new RuntimeException('Ressource inactive ou extérieure à la ferme sélectionnée.');$rate=$this->amount($r['rate']??null,'Tarif dans la devise du travail');
            $this->amount($rate,'Tarif de la ressource');$cost=round($qty*$rate,4);if($cost>9999999999)throw new RuntimeException('Coût de ressource trop élevé.');$result[]=['id'=>$id,'quantity'=>$qty,'rate'=>$rate,'cost'=>$cost];
        }return $result;
    }
    public function cancel($id,array $data,array $user){
        $this->db->beginTransaction();try{$w=$this->row('SELECT * FROM agricultural_works WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$id]);if(!$w)throw new RuntimeException('Travail introuvable.');$cp=$this->context($w['campaign_plot_id']);Auth::requirePermission('agriculture','delete',$cp['site_id']);if($w['status']==='cancelled')throw new RuntimeException('Ce travail est déjà annulé.');if((int)($data['revision']??0)!==(int)$w['revision'])throw new RuntimeException('Ce travail a changé. Actualisez son détail.');$reason=$this->text($data['reason']??'','Motif d’annulation',500);$old=$this->snapshot($id);$this->query("UPDATE agricultural_works SET status='cancelled',revision=revision+1 WHERE id=?",[$id]);$this->history($id,'cancel',$reason,$old,$this->snapshot($id),$user,$cp['site_id']);$this->db->commit();}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    private function snapshot($id){return ['work'=>$this->row('SELECT * FROM agricultural_works WHERE id=?',[$id]),'labor'=>$this->query('SELECT * FROM agricultural_work_labor WHERE work_id=? ORDER BY id',[$id])->fetchAll(),'equipment'=>$this->query('SELECT * FROM agricultural_work_equipment WHERE work_id=? ORDER BY id',[$id])->fetchAll()];}
    private function history($id,$action,$reason,$old,$new,$user,$site){
        $encode=function($v){return $v===null?null:json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);};
        $this->query('INSERT INTO agricultural_work_history(work_id,actor_id,action,reason,old_values,new_values) VALUES(?,?,?,?,?,?)',[$id,$user['id'],$action,$reason,$encode($old),$encode($new)]);
        $this->query('INSERT INTO activity_logs(user_id,site_id,action,module,entity_type,entity_id,description,old_values,new_values,user_agent) VALUES(?,?,?,?,?,?,?,?,?,?)',[$user['id'],$site,'work_'.$action,'agriculture','agricultural_works',$id,$reason?:'Travail agricole réalisé enregistré.',$encode($old),$encode($new),substr($_SERVER['HTTP_USER_AGENT']??'CLI',0,255)]);
    }
    public function directory(){
        $params=[];$scope=Auth::siteClause('c.site_id',$params);
        $works=$this->query("SELECT w.*,c.id campaign_id,c.code campaign_code,c.name campaign_name,c.status campaign_status,c.site_id,p.name plot_name,p.code plot_code,s.name site_name,u.name creator_name FROM agricultural_works w JOIN agricultural_campaign_plots cp ON cp.id=w.campaign_plot_id JOIN agricultural_campaigns c ON c.id=cp.campaign_id JOIN agricultural_plots p ON p.id=cp.plot_id JOIN sites s ON s.id=c.site_id JOIN users u ON u.id=w.created_by WHERE w.deleted_at IS NULL{$scope} ORDER BY w.worked_at DESC,w.id DESC",$params)->fetchAll();
        foreach($works as&$w){$w['labor']=$this->query('SELECT l.*,r.name FROM agricultural_work_labor l JOIN agricultural_workers r ON r.id=l.worker_id WHERE l.work_id=?',[$w['id']])->fetchAll();$w['equipment']=$this->query('SELECT l.*,r.name FROM agricultural_work_equipment l JOIN agricultural_equipment r ON r.id=l.equipment_id WHERE l.work_id=?',[$w['id']])->fetchAll();$w['history']=$this->query('SELECT h.action,h.reason,h.created_at,h.old_values,h.new_values,u.name actor_name FROM agricultural_work_history h JOIN users u ON u.id=h.actor_id WHERE work_id=? ORDER BY h.id DESC',[$w['id']])->fetchAll();$w['labor_cost']=array_sum(array_column($w['labor'],'cost'));$w['equipment_cost']=array_sum(array_column($w['equipment'],'cost'));$w['total_cost']=$w['labor_cost']+$w['equipment_cost']+(float)$w['other_cost'];$w['can_update']=Auth::can('agriculture','update',$w['site_id']);$w['can_cancel']=Auth::can('agriculture','delete',$w['site_id']);}unset($w);
        $params=[];$scope=Auth::siteClause('c.site_id',$params);
        $plans=$this->query("SELECT cp.id,cp.campaign_id,cp.status,c.site_id,c.name campaign_name,c.code campaign_code,c.start_date,c.end_date,c.status campaign_status,p.name plot_name,p.code plot_code,s.name site_name FROM agricultural_campaign_plots cp JOIN agricultural_campaigns c ON c.id=cp.campaign_id JOIN agricultural_plots p ON p.id=cp.plot_id JOIN sites s ON s.id=c.site_id WHERE c.deleted_at IS NULL AND p.deleted_at IS NULL AND s.status='active' AND s.deleted_at IS NULL AND s.code IN('FARM-MUT','FARM-DIK'){$scope} ORDER BY c.start_date DESC,p.code",$params)->fetchAll();foreach($plans as&$p)$p['can_update']=Auth::can('agriculture','update',$p['site_id']);unset($p);
        $resources=[];foreach(['labor'=>'agricultural_workers','equipment'=>'agricultural_equipment'] as$kind=>$table){$params=[];$scope=Auth::siteClause('r.site_id',$params);$resources[$kind]=$this->query("SELECT r.* FROM {$table} r WHERE r.deleted_at IS NULL{$scope} ORDER BY r.name",$params)->fetchAll();}
        // Legacy input costs are denominated in USD, as confirmed by the business.
        $params=[];$scope=Auth::siteClause('c.site_id',$params);$inputs=$this->query("SELECT c.id campaign_id,c.name campaign_name,c.site_id,SUM(a.actual_quantity*a.unit_cost) input_cost FROM agricultural_input_allocations a JOIN agricultural_campaign_plots cp ON cp.id=a.campaign_plot_id JOIN agricultural_campaigns c ON c.id=cp.campaign_id WHERE a.deleted_at IS NULL{$scope} GROUP BY c.id,c.name,c.site_id",$params)->fetchAll();
        return ['time_zone'=>config('app.timezone','Africa/Lubumbashi'),'server_now'=>date(DATE_ATOM),'works'=>$works,'plans'=>$plans,'resources'=>$resources,'inputs'=>$inputs,'types'=>self::TYPES];
    }
}
