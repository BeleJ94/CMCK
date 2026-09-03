<?php

$database=getenv('DAGRIL_DB_DATABASE')?:'';
if(!preg_match('/_(test|testing)$/i',$database)){fwrite(STDERR,"GARDE-FOU: DAGRIL_DB_DATABASE doit désigner une base de test.\n");exit(2);}

require_once dirname(__DIR__).'/app/helpers/functions.php';
require_once dirname(__DIR__).'/app/core/Database.php';
require_once dirname(__DIR__).'/app/core/Model.php';
class Auth { public static function currentSiteId(){return null;} public static function user(){return null;} public static function canViewConsolidated(){return true;} public static function requireSiteAccess($siteId){return true;} }
require_once dirname(__DIR__).'/app/models/User.php';

$db=Database::getInstance()->connection();$id=null;$email='ui.users.'.uniqid().'@dagril.test';
function user_test($condition,$label){if(!$condition)throw new RuntimeException('ÉCHEC: '.$label);echo "OK  - {$label}\n";}
try{
    $actor=$db->query("SELECT id,name,email FROM users WHERE status='active' AND deleted_at IS NULL ORDER BY id LIMIT 1")->fetch();
    $sites=$db->query("SELECT id FROM sites WHERE status='active' AND deleted_at IS NULL ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
    if(!$actor||count($sites)<2)throw new RuntimeException('Référentiels de test incomplets.');
    $model=new User();
    $id=$model->createAccount(['name'=>'Utilisateur Interface','email'=>$email,'phone'=>'+243 000 000','password'=>'Temporaire!42','status'=>'pending','site_ids'=>$sites],$actor);
    $created=$db->query('SELECT * FROM users WHERE id='.(int)$id)->fetch();
    user_test($created&&$created['role_id']===null,'nouvel utilisateur sans rôle métier');
    $siteCount=(int)$db->query('SELECT COUNT(*) FROM user_sites WHERE user_id='.(int)$id." AND status='active' AND deleted_at IS NULL")->fetchColumn();
    user_test($siteCount===2,'affectations multi-sites enregistrées');
    $model->updateAccount($id,['name'=>'Utilisateur Interface Modifié','email'=>$email,'phone'=>'+243 111 111','password'=>'NouveauSecret!42','status'=>'active','site_ids'=>[$sites[1]]],$actor);
    $updated=$db->query('SELECT * FROM users WHERE id='.(int)$id)->fetch();
    user_test($updated['name']==='Utilisateur Interface Modifié'&&$updated['status']==='active','modification du compte');
    user_test(password_verify('NouveauSecret!42',$updated['password']),'changement sécurisé du mot de passe');
    user_test((int)$db->query('SELECT COUNT(*) FROM activity_logs WHERE module=\'users\' AND entity_id='.(int)$id)->fetchColumn()===2,'création et modification journalisées');
    $blocked=false;try{$model->updateAccount($id,['name'=>$updated['name'],'email'=>$email,'phone'=>'','password'=>'','status'=>'inactive','site_ids'=>[$sites[1]]],['id'=>$id]);}catch(RuntimeException $e){$blocked=true;}
    user_test($blocked,'auto-désactivation refusée');
}finally{
    if($id){$db->prepare("DELETE FROM activity_logs WHERE module='users' AND entity_id=?")->execute([$id]);$db->prepare('DELETE FROM user_sites WHERE user_id=?')->execute([$id]);$db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);}
}
