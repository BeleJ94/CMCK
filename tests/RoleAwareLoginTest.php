<?php
require_once __DIR__.'/integration/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();$_SERVER['HTTP_HOST']='dagril.test';$_SERVER['SCRIPT_NAME']='/index.php';
function sql($s,$p=[]){global $db;$q=$db->prepare($s);$q->execute($p);return $q;}
function check($c,$m){DagrilIntegrationTest::check($c,$m);}
$db->beginTransaction();
try{
 $site=(int)sql("SELECT id FROM sites WHERE code='FARM-DIK'")->fetchColumn();$other=(int)sql("SELECT id FROM sites WHERE code='FARM-MUT'")->fetchColumn();$tag=bin2hex(random_bytes(5));
 sql("INSERT INTO users(name,email,password,status) VALUES(?,?,?,'active')",['Test accueil','landing-'.$tag.'@example.test','unused']);$uid=(int)$db->lastInsertId();
 sql("INSERT INTO user_sites(user_id,site_id,is_default,status) VALUES(?,?,1,'active')",[$uid,$site]);
 $u=sql('SELECT * FROM users WHERE id=?',[$uid])->fetch();Auth::login($u);
 check(Auth::homePathFor(Auth::user())==='access-pending','Un compte sans role dispose d un accueil accessible');
 check(Auth::authorizedIntendedUrl('/dashboard')===null,'Ancienne destination dashboard refusee sans permission');
 sql("INSERT INTO roles(name,slug,status) VALUES(?,?,'active')",['Test elevage','test-elevage-'.$tag]);$role=(int)$db->lastInsertId();
 sql("INSERT INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE component='livestock' AND action IN('read','create','update','validate')",[$role]);
 sql("INSERT INTO user_role_assignments(user_id,role_id,site_id,assignment_type,starts_at,approval_status) VALUES(?,?,?,'direct',NOW(),'approved')",[$uid,$role,$site]);
 check(Auth::homePathFor(Auth::user())==='livestock','Accueil choisi selon le role attribue meme sans role historique');
 check(Auth::can('livestock','read',$site)&&Auth::can('livestock','validate',$site),'Lecture et validation autorisees sur DIKAPA');
 check(!Auth::can('livestock','read',$other),'Aucune extension des permissions vers MUTALA');
 check(!Auth::can('users','administer')&&!Auth::can('dashboard','read'),'Aucun droit administrateur ou tableau de bord ajoute');
 check(Auth::authorizedIntendedUrl('/dashboard')===null,'Une ancienne URL interdite ne remplace plus le bon accueil');
 check(Auth::authorizedIntendedUrl('/livestock/pigs')==='/livestock/pigs','Destination elevage autorisee conservee');
 check(Auth::authorizedIntendedUrl('/livestock/facilities')===null,'Une sous-page d administration reste refusee');
 check(Auth::authorizedIntendedUrl('https://other.test/livestock')===null,'Redirection externe refusee');
 check(Auth::authorizedIntendedUrl('//other.test/livestock')===null,'Redirection externe sans protocole refusee');
 check(Auth::authorizedIntendedUrl('/livestock/../users')===null,'Chemin ambigu refuse');
 check(Auth::authorizedIntendedUrl("/livestock\r\nX-Test: yes")===null,'Injection d entete refusee');
}finally{if($db->inTransaction())$db->rollBack();}
DagrilIntegrationTest::finish();
