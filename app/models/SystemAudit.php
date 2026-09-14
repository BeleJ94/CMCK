<?php
require_once dirname(__DIR__).'/services/AuditService.php';
class SystemAudit extends Model
{
    public const SECURITY=['login','logout','login_failed','access_denied'];
    public function where(array $f, array &$p): string
    {
        $w=' WHERE 1=1';
        foreach(['module','action','user_id','site_id'] as$k)if($f[$k]!==''){$w.=" AND l.$k=:$k";$p[$k]=$f[$k];}
        if($f['from']){$w.=' AND l.created_at>=:from';$p['from']=$f['from'].' 00:00:00';}
        if($f['to']){$w.=' AND l.created_at<DATE_ADD(:to, INTERVAL 1 DAY)';$p['to']=$f['to'];}
        if($f['search']){$w.=" AND (l.description LIKE :q OR CONCAT(COALESCE(l.entity_type,''),' #',COALESCE(l.entity_id,'')) LIKE :q2 OR u.name LIKE :q3)";foreach(['q','q2','q3']as$k)$p[$k]='%'.$f['search'].'%';}
        if($f['space']==='security')$w.=" AND l.action IN ('login','logout','login_failed','access_denied')";
        if($f['space']==='attention')$w.=" AND (l.action REGEXP 'update|cancel|permission|delete|failed|denied' OR l.old_values IS NOT NULL) AND l.action<>'audit_review'";
        if($f['result']==='failed')$w.=" AND l.action IN ('login_failed','access_denied','request_failed')";
        if($f['result']==='unspecified')$w.=" AND l.action NOT IN ('login_failed','access_denied','request_failed','login','logout','audit_review')";
        if($f['result']==='confirmed')$w.=" AND l.action IN ('login','logout','audit_review')";
        if($f['review']==='pending')$w.=" AND NOT EXISTS(SELECT 1 FROM activity_logs r WHERE r.action='audit_review' AND r.entity_type='activity_logs' AND r.entity_id=l.id)";
        return $w;
    }
    public function listing(array $f,int $offset=0,int $limit=50): array
    {
        $p=[];$w=$this->where($f,$p);$base=' FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id LEFT JOIN sites s ON s.id=l.site_id';
        $count=(int)$this->query('SELECT COUNT(*)'.$base.$w,$p)->fetchColumn();
        $offset=max(0,min($offset,max(0,(int)(ceil($count/$limit)-1)*$limit)));
        $rows=$this->query("SELECT l.*,u.name user_name,s.code site_code,EXISTS(SELECT 1 FROM activity_logs r WHERE r.action='audit_review' AND r.entity_type='activity_logs' AND r.entity_id=l.id) reviewed".$base.$w.' ORDER BY l.created_at DESC,l.id DESC LIMIT '.(int)$limit.' OFFSET '.$offset,$p)->fetchAll();
        $reviews=[];
        if($rows){$ids=array_column($rows,'id');$marks=implode(',',array_fill(0,count($ids),'?'));$notes=$this->query("SELECT r.entity_id,r.description,r.created_at,u.name user_name FROM activity_logs r LEFT JOIN users u ON u.id=r.user_id WHERE r.action='audit_review' AND r.entity_type='activity_logs' AND r.entity_id IN ($marks) ORDER BY r.id DESC",$ids)->fetchAll();foreach($notes as$note)$reviews[$note['entity_id']][]=$note;}
        foreach($rows as&$row){$row['reviews']=$reviews[$row['id']]??[];foreach(['old_values','new_values']as$key)$row[$key]=AuditService::sanitize(json_decode($row[$key]??'',true)?:[]);}unset($row);
        return ['rows'=>$rows,'count'=>$count,'page'=>(int)floor($offset/$limit)+1];
    }
    public function options(): array
    {
        return ['module'=>$this->query("SELECT DISTINCT module value FROM activity_logs WHERE module IS NOT NULL ORDER BY module")->fetchAll(),'action'=>$this->query('SELECT DISTINCT action value FROM activity_logs ORDER BY action')->fetchAll(),'user_id'=>$this->query('SELECT id value,name label FROM users ORDER BY name')->fetchAll(),'site_id'=>$this->query('SELECT id value,code label FROM sites ORDER BY code')->fetchAll()];
    }
    public function counters(): array
    {
        return $this->query("SELECT COUNT(*) total,MIN(created_at) first_event,SUM(created_at>=CURDATE()) today,SUM(action IN ('login_failed','access_denied','request_failed') AND created_at>=CURDATE()) failures,SUM(action='access_denied' AND created_at>=CURDATE()) denied FROM activity_logs")->fetch();
    }
    public function review($id,$note): void
    {
        if(!$this->query('SELECT id FROM activity_logs WHERE id=:id',['id'=>$id])->fetch())throw new RuntimeException('Événement introuvable.');
        $this->logActivity('audit_review','audit','activity_logs',$id,$note,null,['reviewed_event_id'=>(int)$id],Auth::user());
    }
    public static function result(array $r): string
    {
        if(in_array($r['action'],['login_failed','access_denied','request_failed'],true))return 'Échec / refus';
        if(in_array($r['action'],['login','logout','audit_review'],true))return 'Confirmé';
        return 'Non précisé';
    }
}
