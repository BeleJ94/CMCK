<?php
require_once dirname(__DIR__).'/app/helpers/functions.php';
require_once dirname(__DIR__).'/app/core/Database.php';
require_once dirname(__DIR__).'/app/core/Model.php';
require_once dirname(__DIR__).'/app/core/Auth.php';
require_once dirname(__DIR__).'/app/services/AuthorizationService.php';
require_once dirname(__DIR__).'/app/models/AccessControl.php';
ob_start(); Auth::start();
$db=Database::getInstance()->connection(); $created=[];
function qa($ok,$label){if(!$ok){throw new RuntimeException('ECHEC: '.$label);} echo "[OK] {$label}\n";}
function one($db,$sql,$params=[]){$s=$db->prepare($sql);$s->execute($params);return $s->fetch();}
try{
 $admin=one($db,"SELECT u.*,r.name role_name,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='administrateur' AND u.status='active' AND u.deleted_at IS NULL LIMIT 1");
 $otherAdmin=one($db,"SELECT u.*,r.name role_name,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='administrateur' AND u.id<>:id AND u.status='active' AND u.deleted_at IS NULL LIMIT 1",['id'=>$admin['id']]);
 if(!$otherAdmin){$otherAdmin=one($db,"SELECT u.*,r.name role_name,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='direction' AND u.status='active' AND u.deleted_at IS NULL LIMIT 1");}
 $site=one($db,"SELECT id FROM sites WHERE code='MINO'"); $operatorRole=one($db,"SELECT id FROM roles WHERE slug='operateur-saisie'"); $sensitiveRole=one($db,"SELECT id FROM roles WHERE slug='directeur-site'");
 $email='rbac.qa.'.uniqid().'@dagril.test'; $s=$db->prepare("INSERT INTO users(role_id,name,email,password,status) VALUES(NULL,'QA RBAC',:email,:password,'active')");$s->execute(['email'=>$email,'password'=>password_hash('Test123!',PASSWORD_DEFAULT)]);$userId=(int)$db->lastInsertId();$created['user']=$userId;
 $db->prepare("INSERT INTO user_sites(user_id,site_id,is_default,status) VALUES(:u,:s,1,'active')")->execute(['u'=>$userId,'s'=>$site['id']]);
 $authz=new AuthorizationService($db); qa(!$authz->can($userId,'production','read',$site['id']),'Nouvel utilisateur sans permission metier');
 $db->prepare("INSERT INTO user_role_assignments(user_id,role_id,site_id,starts_at,expires_at,approval_status,assignment_type) VALUES(:u,:r,:s,DATE_SUB(NOW(),INTERVAL 2 DAY),DATE_SUB(NOW(),INTERVAL 1 DAY),'approved','delegation')")->execute(['u'=>$userId,'r'=>$operatorRole['id'],'s'=>$site['id']]);$expired=(int)$db->lastInsertId();
 qa(!$authz->can($userId,'production','read',$site['id']),'Permission expiree refusee');
 $db->prepare("INSERT INTO user_role_assignments(user_id,role_id,site_id,starts_at,expires_at,approval_status,assignment_type) VALUES(:u,:r,:s,NOW(),DATE_ADD(NOW(),INTERVAL 1 DAY),'approved','delegation')")->execute(['u'=>$userId,'r'=>$operatorRole['id'],'s'=>$site['id']]);$active=(int)$db->lastInsertId();
 qa($authz->can($userId,'production','read',$site['id']),'Lecture autorisee sur le site attribue'); qa(!$authz->can($userId,'production','validate',$site['id']),'Operateur de saisie sans validation');
 $otherSite=one($db,"SELECT id FROM sites WHERE id<>:id LIMIT 1",['id'=>$site['id']]);qa(!$authz->can($userId,'production','read',$otherSite['id']),'Permission refusee sur autre site');
 $conflict=false;try{$authz->assertCanValidateRecord($admin['id'],'production',['site_id'=>$site['id'],'created_by'=>$admin['id']]);}catch(RuntimeException $e){$conflict=strpos($e->getMessage(),'createur')!==false;}qa($conflict,'Separation createur-validateur');
 Auth::login($admin);Auth::selectSite((string)$site['id']);$model=new AccessControl();$assignment=$model->createAssignment(['user_id'=>$userId,'role_id'=>$sensitiveRole['id'],'site_id'=>$site['id'],'assignment_type'=>'direct','starts_at'=>date('Y-m-d H:i:s'),'expires_at'=>null,'reason'=>'QA'],$admin);$created['assignment']=$assignment;
 $pending=one($db,'SELECT approval_status FROM user_role_assignments WHERE id=:id',['id'=>$assignment]);qa($pending['approval_status']==='pending','Role sensible en attente');
 $self=false;try{$model->decide($assignment,'approve',$admin);}catch(RuntimeException $e){$self=true;}qa($self,'Auto-approbation sensible refusee');
 if($otherAdmin){Auth::login($otherAdmin);$model->decide($assignment,'approve',$otherAdmin);$approved=one($db,'SELECT approval_status FROM user_role_assignments WHERE id=:id',['id'=>$assignment]);qa($approved['approval_status']==='approved','Deuxieme approbation acceptee');}
 qa((int)one($db,"SELECT COUNT(*) c FROM activity_logs WHERE module='rbac' AND entity_id=:id",['id'=>$assignment])['c']>=1,'Attribution journalisee');
 echo "Tous les tests RBAC granulaires sont conformes.\n";
}finally{
 if(!empty($created['assignment'])){$db->prepare('DELETE FROM rbac_approval_events WHERE assignment_id=:id')->execute(['id'=>$created['assignment']]);$db->prepare('DELETE FROM activity_logs WHERE module=\'rbac\' AND entity_id=:id')->execute(['id'=>$created['assignment']]);}
 if(!empty($created['user'])){$db->prepare('DELETE FROM rbac_approval_events WHERE assignment_id IN (SELECT id FROM user_role_assignments WHERE user_id=:id)')->execute(['id'=>$created['user']]);$db->prepare('DELETE FROM user_role_assignments WHERE user_id=:id')->execute(['id'=>$created['user']]);$db->prepare('DELETE FROM user_sites WHERE user_id=:id')->execute(['id'=>$created['user']]);$db->prepare('DELETE FROM users WHERE id=:id')->execute(['id'=>$created['user']]);}
 Auth::logout();$out=ob_get_clean();echo $out;
}
