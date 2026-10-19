<?php
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base jetable obligatoire.');
require_once __DIR__.'/integration/TestBootstrap.php';$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../app/core/Controller.php';require_once __DIR__.'/../app/core/Router.php';require_once __DIR__.'/../app/controllers/ButcheryController.php';
$admin=DagrilIntegrationTest::loginRole('administrateur');$site=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='BOUCH'");
function rq($sql,$params=[]){global$db;$q=$db->prepare($sql);$q->execute($params);return$q;}
function checkRecipe($condition,$message){DagrilIntegrationTest::check($condition,$message);if(!$condition)throw new RuntimeException($message);}
$suffix=bin2hex(random_bytes(5));
rq("INSERT INTO roles(name,slug,status) VALUES(? ,?,'active')",['Lecture boucherie test','recipe-read-'.$suffix]);$role=(int)$db->lastInsertId();
rq("INSERT INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE component='butchery' AND action='read'",[$role]);
rq("INSERT INTO users(role_id,name,email,password,status) VALUES(?,'Lecteur boucherie',?,'unused','active')",[$role,'recipe-read-'.$suffix.'@example.test']);$uid=(int)$db->lastInsertId();
rq("INSERT INTO user_sites(user_id,site_id,is_default,status) VALUES(?,?,1,'active')",[$uid,$site]);
rq("INSERT INTO user_role_assignments(user_id,role_id,site_id,assignment_type,starts_at,approval_status) VALUES(?,?,?,'direct',NOW(),'approved')",[$uid,$role,$site]);
$reader=rq('SELECT u.*,r.name role_name,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?',[$uid])->fetch();Auth::login($reader);Auth::selectSite((string)$site);
checkRecipe(Auth::can('butchery','read')&&!Auth::can('butchery','administer'),'Compte de lecture sans administration');
http_response_code(200);ob_start();(new ButcheryController())->index('recipes');$html=ob_get_clean();
checkRecipe(http_response_code()===200&&strpos($html,'Fiches et versions')!==false,'Fiches accessibles en consultation sans erreur fatale');
checkRecipe(strpos($html,'Fatal error')===false&&strpos($html,'Warning:')===false,'Rendu sans erreur PHP');
checkRecipe(strpos($html,'id="butchery-recipe-editor"')===false&&strpos($html,'action="'.base_url('butchery/recipes').'"')===false,'Aucun formulaire de création pour le compte de lecture');
checkRecipe(strpos($html,'Leur création est réservée aux utilisateurs habilités')!==false,'Mode consultation expliqué');
$menu=[];foreach(Auth::menuGroups() as$group)foreach($group['items'] as$item)$menu[]=$item['path'];checkRecipe(in_array('butchery/recipes',$menu,true),'Lien de consultation disponible dans le menu');
$router=new Router();$router->get('/butchery/recipes','Butchery@index',['auth'=>true,'permission'=>['butchery','read']]);
$router->post('/butchery/recipes','Butchery@recipe',['auth'=>true,'permission'=>['butchery','administer']]);
$before=(int)rq('SELECT COUNT(*) FROM butchery_recipe_versions')->fetchColumn();
$_SERVER['SCRIPT_NAME']='/index.php';ob_start();$router->dispatch('POST','/butchery/recipes');$denied=ob_get_clean();
checkRecipe(http_response_code()===403&&strpos($denied,'403')!==false,'Création refusée par la route sans droit d’administration');
checkRecipe((int)rq('SELECT COUNT(*) FROM butchery_recipe_versions')->fetchColumn()===$before,'Aucune version créée après refus');
rq('DELETE FROM role_permissions WHERE role_id=?',[$role]);http_response_code(200);ob_start();(new ButcheryController())->index('recipes');$denied=ob_get_clean();
checkRecipe(http_response_code()===403&&strpos($denied,'403')!==false&&strpos($denied,'Stack trace')===false,'Compte sans lecture : refus propre sans trace technique');
Auth::login($admin);Auth::selectSite((string)$site);http_response_code(200);ob_start();(new ButcheryController())->index('recipes');$html=ob_get_clean();
checkRecipe(strpos($html,'id="butchery-recipe-editor"')!==false,'Administrateur : création des fiches toujours disponible');
DagrilIntegrationTest::finish();
