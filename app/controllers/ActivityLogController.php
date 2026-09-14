<?php
class ActivityLogController extends Controller
{
    private function allowed(): bool
    {
        if((Auth::user()['role_slug']??'')==='administrateur')return true;
        require_once dirname(__DIR__).'/services/AuditService.php';
        AuditService::record('access_denied','security','Accès au journal d’audit refusé.');
        http_response_code(403);echo 'Accès réservé aux administrateurs.';return false;
    }
    private function filters(): array
    {
        $f=[];foreach(['module','action','user_id','site_id','from','to','search','space','result','review']as$k)$f[$k]=is_string($_GET[$k]??null)?trim($_GET[$k]):'';
        foreach(['from','to']as$k){$d=DateTime::createFromFormat('!Y-m-d',$f[$k]);if(!$d||$d->format('Y-m-d')!==$f[$k])$f[$k]='';}
        return $f;
    }
    public function index()
    {
        if(!$this->allowed())return;
        $m=$this->model('SystemAudit');$f=$this->filters();$export=$_GET['export']??'';
        $listing=$m->listing($f,max(0,((int)($_GET['page']??1)-1))*50,$export?2000:50);
        if($export){
            if(!in_array($export,['excel','pdf'],true)){http_response_code(400);echo 'Format invalide.';return;}
            if($listing['count']>2000){http_response_code(422);echo 'L’export est limité à 2 000 événements. Réduisez la période ou ajoutez des filtres.';return;}
            $rows=$listing['rows'];$summary=implode(' · ',array_map(fn($k)=>$k.' : '.$f[$k],array_keys(array_filter($f,fn($v)=>$v!==''))));
            $this->model('ActivityLog')->record('audit_export','audit',null,null,'Export du journal d’audit.',null,['format'=>$export,'count'=>count($rows),'filters'=>$f]);
            header('Cache-Control: private, no-store');
            if($export==='pdf'){(new PdfService())->stream('Audit du système',$this->renderViewToString('activity_logs.export',['rows'=>$rows,'summary'=>$summary]),'dagril-audit-'.date('Ymd-His').'.pdf');return;}
            require_once dirname(__DIR__).'/services/SpreadsheetExportService.php';
            $sheet=[['DAGRIL · Audit du système'],['Édité le '.date('d/m/Y H:i')],[$summary?:'Tous les événements'],[],['Date','Utilisateur','Site','Module','Action','Référence','Résultat','Description']];
            foreach($rows as$r)$sheet[]=[$r['created_at'],$r['user_name']?:'Non attribué',$r['site_code']?:'Global / non précisé',$r['module'],$r['action'],($r['entity_type']??'').' #'.($r['entity_id']??''),SystemAudit::result($r),$r['description']];
            $sheet[]=[count($rows).' événements'];$content=(new SpreadsheetExportService())->build($sheet,[23,25,20,23,32,30,20,65]);header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="dagril-audit-'.date('Ymd-His').'.xlsx"');echo $content;return;
        }
        $this->view('activity_logs.index',['title'=>'Audit du système','listing'=>$listing,'filters'=>$f,'options'=>$m->options(),'stats'=>$m->counters()],'layouts.main');
    }
    public function review($id)
    {
        if(!$this->allowed())return;
        if(!verify_csrf($_POST['_token']??''))return $this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page.'],419);
        $note=trim($_POST['note']??'');if($note===''||mb_strlen($note)>2000)return $this->json(['ok'=>false,'message'=>'Renseignez une note de 1 à 2 000 caractères.'],422);
        try{$this->model('SystemAudit')->review($id,$note);return $this->json(['ok'=>true,'message'=>'Examen enregistré dans le journal.','refresh_url'=>base_url('activity-logs')]);}
        catch(RuntimeException $e){return $this->json(['ok'=>false,'message'=>'Impossible d’enregistrer l’examen. Vérifiez que l’événement existe.'],422);}
    }
}
