<?php
/** Shared audit metadata and redaction; never captures request bodies. */
class AuditService
{
    public static function sanitize($value)
    {
        if (!is_array($value)) return $value;
        $safe=[];
        foreach($value as$key=>$item){
            $safe[$key]=preg_match('/password|passwd|secret|token|authorization|cookie|api.?key|private.?key/i',(string)$key)?'[MASQUÉ]':self::sanitize($item);
        }
        return $safe;
    }
    public static function requestId(): string
    {
        if(!isset($GLOBALS['audit_request_id']))$GLOBALS['audit_request_id']=bin2hex(random_bytes(16));
        return $GLOBALS['audit_request_id'];
    }
    public static function record($action,$module,$description,array $values=[],?array $user=null): void
    {
        try {
            require_once dirname(__DIR__).'/models/ActivityLog.php';
            (new ActivityLog())->record($action,$module,null,null,$description,null,$values,$user);
        } catch(Throwable $e) { error_log('Audit: impossible d’enregistrer un événement.'); }
    }
    public static function watchRequest(): void
    {
        $method=$_SERVER['REQUEST_METHOD']??'GET';
        $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
        $user=Auth::user();
        $export=isset($_GET['export'])||preg_match('~/export$~',$path);
        if($method!=='POST'&&!$export)return;
        self::requestId();
        register_shutdown_function(function()use($method,$path,$user,$export){
            $status=http_response_code()?:200;
            $action=$status>=400?'request_failed':($export?'export_requested':'request_received');
            self::record($action,'http',$status>=400?'Requête refusée ou en erreur.':($export?'Demande d’export traitée ; réception du fichier non vérifiable.':'Requête reçue. Consulter les événements métier associés pour son résultat.'),['method'=>$method,'path'=>$path,'http_status'=>$status],$user);
        });
    }
}
