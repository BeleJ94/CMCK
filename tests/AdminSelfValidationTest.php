<?php
// Isolate the self-validation rule from the permission database.
class Auth {
    public static $admin=false;
    public static $actor=7;
    public static function canSelfValidate($userId){return self::$admin&&(int)$userId===self::$actor;}
}
require dirname(__DIR__).'/app/services/AuthorizationService.php';
class TestAuthorization extends AuthorizationService {
    public $allow=true;
    public $checkedSite;
    public function __construct(){}
    public function assertAllowed($userId,$component,$action,$siteId=null){$this->checkedSite=$siteId;if(!$this->allow)throw new RuntimeException('Permission refusée');}
}
$service=new TestAuthorization();$record=['created_by'=>7,'site_id'=>2];$checks=0;
function self_check($condition,$message){global $checks;if(!$condition)throw new RuntimeException($message);$checks++;echo 'OK - '.$message.PHP_EOL;}
function self_refused($callback){try{$callback();return false;}catch(RuntimeException $e){return true;}}
self_check(self_refused(function()use($service,$record){$service->assertCanValidateRecord(7,'weighings',$record);}), 'non-administrateur empêché de valider sa création');
Auth::$admin=true;
$service->assertCanValidateRecord(7,'weighings',$record);self_check($service->checkedSite===2,'administrateur autorisé avec contrôle des droits sur le site');
$service->allow=false;
self_check(self_refused(function()use($service,$record){$service->assertCanValidateRecord(7,'weighings',$record);}), 'administrateur sans permission de validation refusé');
$service->allow=true;
self_check(self_refused(function()use($service){$service->assertCanValidateRecord(8,'weighings',['created_by'=>8,'site_id'=>2]);}), 'identité distincte sans exception administrative');
Auth::$admin=false;$service->assertCanValidateRecord(7,'weighings',['created_by'=>8,'site_id'=>2]);self_check(true,'validation par un autre utilisateur toujours possible');
echo $checks." vérifications réussies.\n";
