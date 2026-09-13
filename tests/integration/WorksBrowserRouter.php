<?php
if(!preg_match('/_(test|testing)$/',getenv('DAGRIL_DB_DATABASE')?:'')){http_response_code(500);exit('Test database required');}
if(!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)){http_response_code(403);exit('Local tests only');}
$root=dirname(__DIR__,2);$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(strpos($path,'/public/assets/')===0){$file=realpath($root.$path);if($file&&strpos($file,$root.'/public/assets/')===0&&is_file($file)){header('Content-Type: '.(substr($file,-3)==='.js'?'application/javascript':(substr($file,-4)==='.css'?'text/css':'application/octet-stream')));readfile($file);return;}http_response_code(404);return;}
if(strpos($path,'/assets/')===0&&is_file($root.'/public'.$path))return false;
require_once $root.'/app/helpers/functions.php';require_once $root.'/app/core/Database.php';require_once $root.'/app/core/Auth.php';
Auth::start();if(!Auth::check()){$db=Database::getInstance()->connection();$u=$db->query("SELECT u.*,r.slug role_slug,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='administrateur' AND u.status='active' AND u.deleted_at IS NULL LIMIT 1")->fetch();Auth::login($u);Auth::selectSite('all');}
$_SERVER['SCRIPT_NAME']='/index.php';require $root.'/public/index.php';
