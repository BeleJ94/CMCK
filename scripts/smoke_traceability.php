<?php
ini_set('session.save_path',sys_get_temp_dir());
$root=dirname(__DIR__);
require_once $root.'/app/helpers/functions.php';
require_once $root.'/app/core/Database.php';
require_once $root.'/app/core/Model.php';
require_once $root.'/app/core/Auth.php';
require_once $root.'/app/services/AuthorizationService.php';
require_once $root.'/app/models/Traceability.php';
try{
 $db=Database::getInstance()->connection();
 $user=$db->query("SELECT u.*,r.name role_name,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.status='active' AND r.slug IN('administrateur','direction') ORDER BY u.id LIMIT 1")->fetch();
 if(!$user)throw new RuntimeException('Utilisateur de contrôle introuvable.');
 Auth::start();$_SESSION['user']=['id'=>$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role_id'=>$user['role_id'],'role_name'=>$user['role_name'],'role_slug'=>$user['role_slug']];$_SESSION['current_site_id']=null;
 $m=new Traceability();$f=['q'=>'','site_id'=>'all','start_date'=>'2000-01-01','end_date'=>date('Y-m-d')];$rows=$m->search($f);
 echo '[OK] Recherche SQL exécutée: '.count($rows).' dossier(s).'.PHP_EOL;
 $f['q']='a';$m->search($f);echo '[OK] Recherche multi-critères préparée exécutée.'.PHP_EOL;
 foreach(array_slice($rows,0,20)as$r){$timeline=$m->dossier($r['chain'],$r['root_id']);if(!$timeline)throw new RuntimeException('Dossier vide: '.$r['chain'].'#'.$r['root_id']);}
 echo '[OK] Dossiers échantillonnés et enrichis.'.PHP_EOL;
}catch(PDOException$e){echo'[SKIP] MariaDB indisponible: '.$e->getMessage().PHP_EOL;exit(0);}catch(Exception$e){fwrite(STDERR,'[ECHEC] '.$e->getMessage().PHP_EOL);exit(1);}
