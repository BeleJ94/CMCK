<?php
$database=getenv('DAGRIL_DB_DATABASE')?:'';
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',$database)||!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)){http_response_code(403);exit('Disposable localhost UI tests only');}
$profile=getenv('DAGRIL_UI_TEST_PROFILE')?:'admin';
if($profile==='livestock'){
 $root=dirname(__DIR__,2);require_once $root.'/app/helpers/functions.php';require_once $root.'/app/core/Database.php';require_once $root.'/app/core/Auth.php';
 ini_set('session.save_path',sys_get_temp_dir());Auth::start();
 if(!Auth::check()){$db=Database::getInstance()->connection();$q=$db->prepare("SELECT * FROM users WHERE email=? AND status='active' AND deleted_at IS NULL");$q->execute(['ui.elevage@example.test']);$user=$q->fetch();if(!$user){http_response_code(500);exit('Restricted fixture missing');}Auth::login($user);}
}
require __DIR__.'/WorksBrowserRouter.php';
