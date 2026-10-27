<?php
/** Products referential (046): the products each raw material gives, and their use in the transformation form and server. */
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Utilisez une base jetable créée par setup_porcine_flow_test.php.');
require_once __DIR__.'/integration/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../app/core/Controller.php';require_once __DIR__.'/../app/core/Router.php';
require_once __DIR__.'/../app/services/ButcheryProductService.php';require_once __DIR__.'/../app/controllers/PorcineFlowController.php';
$admin=DagrilIntegrationTest::loginRole('administrateur');$user=$admin;
$bouch=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='BOUCH'");Auth::selectSite((string)$bouch);
$service=new ButcheryProductService($db);$flow=new PorcineFlowService($db);
function q($sql,$params=[]){global $db;$q=$db->prepare($sql);$q->execute($params);return $q;}
function k($patch=[]){return array_replace(['operation_key'=>bin2hex(random_bytes(16))],$patch);}
function ok($condition,$message){DagrilIntegrationTest::check($condition,$message);if(!$condition)throw new RuntimeException($message);}
function no($callback,$message,$expected){try{$callback();}catch(RuntimeException $e){ok(strpos($e->getMessage(),$expected)!==false,$message.' · « '.$e->getMessage().' »');return;}ok(false,$message);}
function item($code){return (int)DagrilIntegrationTest::scalar('SELECT id FROM butchery_items WHERE code=?',[$code]);}
function codes($raw){return q('SELECT i.code FROM butchery_product_sources s JOIN butchery_items i ON i.id=s.product_item_id WHERE s.raw_item_id=? ORDER BY i.code',[$raw])->fetchAll(PDO::FETCH_COLUMN);}
function material($id){global $service;foreach($service->directory()['materials'] as $m)if((int)$m['id']===$id)return $m;throw new RuntimeException('Matière absente');}
function product($id){global $service;foreach($service->directory()['products'] as $p)if((int)$p['id']===$id)return $p;throw new RuntimeException('Produit absent');}
$porc=item('MP-PORC');$boeuf=item('MP-BOEUF');$poisson=item('MP-POISSON');$volaille=item('MP-VOLAILLE');$poule=item('MP-POULE');

// Migration 046: known products of each material.
ok($service->ready(),'Migration 046 détectée : référentiel des produits actif');
ok(codes($porc)===['ABATS','MORCEAUX-PORC','PORC-COTELETTE','PORC-MAKOSO'],'Porc : morceaux, côtelettes, makoso et abats');
ok(codes($boeuf)===['ABATS','MORCEAUX-BOEUF'],'Bœuf : morceaux et abats');
ok(codes($poisson)===['POISSON-PREPARE'],'Poisson : poisson préparé');
ok(codes($volaille)===['MORCEAUX-VOLAILLE','VOL-ENTIER']&&codes($poule)===['MORCEAUX-VOLAILLE','POULE-ENTIERE'],'Volailles : entiers et morceaux, pour les poulets et les poules');
$d=$service->directory();
ok(!in_array('VOL-VIF',array_column($d['products'],'code'),true)&&!in_array('MP-PORC',array_column($d['products'],'code'),true),'Catalogue : ni poulet vif ni matière première');
ok(count(material($porc)['products'])===4&&$d['canAdminister'],'Fiche matière : 4 produits du porc, administration permise');

// Creation: code from the name, materials required.
no(fn()=>$service->create(['name'=>'Filet de porc','item_kind'=>'finished_product'],$user),'Création sans matière refusée','au moins une matière');
no(fn()=>$service->create(['name'=>'Filet de porc','item_kind'=>'bone','source_ids'=>[(string)$porc]],$user),'Type inconnu refusé','type de produit');
no(fn()=>$service->create(['name'=>'Filet de porc','item_kind'=>'finished_product','source_ids'=>[(string)item('VOL-ENTIER')]],$user),'Un produit ne peut pas servir de matière','Matière d’origine introuvable');
no(fn()=>$service->create(['name'=>'Filet de porc','item_kind'=>'finished_product','source_ids'=>[(string)$porc],'default_shelf_life_days'=>'0'],$user),'DLC conseillée nulle refusée','DLC conseillée');
no(fn()=>$service->create(['name'=>'Filet de porc','item_kind'=>'finished_product','source_ids'=>[(string)$porc],'code'=>'MP-PORC'],$user),'Code déjà utilisé refusé','déjà utilisé');
no(fn()=>$service->create(['name'=>'Côtelettes de porc','item_kind'=>'finished_product','source_ids'=>[(string)$porc]],$user),'Nom déjà pris refusé','porte déjà le nom');
$filet=$service->create(['name'=>'Côtes de porc','item_kind'=>'finished_product','source_ids'=>[(string)$porc],'default_shelf_life_days'=>'5'],$user);
$row=q('SELECT * FROM butchery_items WHERE id=?',[$filet])->fetch();
ok($row['code']==='COTES-DE-PORC'&&$row['unit']==='kg'&&(int)$row['track_pieces']===0&&(int)$row['default_shelf_life_days']===5,'Produit créé : code COTES-DE-PORC déduit du nom, au kilo, DLC conseillée 5 jours');
ok(codes($porc)===['ABATS','COTES-DE-PORC','MORCEAUX-PORC','PORC-COTELETTE','PORC-MAKOSO'],'Nouveau produit rattaché au porc');
$second=$service->create(['name'=>'Cotes de porc fumees','code'=>'','item_kind'=>'finished_product','source_ids'=>[(string)$porc]],$user);
ok(DagrilIntegrationTest::scalar('SELECT code FROM butchery_items WHERE id=?',[$second])==='COTES-DE-PORC-FUMEES','Code sans accents, séparé par des tirets');
ok($service->suggestCode('Côtes de porc')==='COTES-DE-PORC-2','Code déjà pris : suffixe -2');
$entier=$service->create(['name'=>'Poisson entier vidé','code'=>'pois-vide','item_kind'=>'finished_product','source_ids'=>[(string)$poisson],'track_pieces'=>'1'],$user);
$row=q('SELECT code,unit,track_pieces FROM butchery_items WHERE id=?',[$entier])->fetch();
ok($row['code']==='POIS-VIDE'&&$row['unit']==='piece'&&(int)$row['track_pieces']===1,'Produit compté en pièces, code saisi mis en majuscules');
ok((int)DagrilIntegrationTest::scalar("SELECT COUNT(*) FROM activity_logs WHERE action='butchery_product_create' AND entity_id IN (?,?,?)",[$filet,$second,$entier])===3,'Créations tracées dans l’audit');

// Edition from the product: version check, materials, retirement.
$p=product($filet);
no(fn()=>$service->update($filet,['name'=>'Côtes de porc','item_kind'=>'finished_product','default_shelf_life_days'=>'5','source_ids'=>[(string)$porc],'version'=>'perimee'],$user),'Version périmée refusée','modifié entre-temps');
no(fn()=>$service->update($filet,['name'=>'Côtes de porc','item_kind'=>'finished_product','default_shelf_life_days'=>'5','source_ids'=>[(string)$porc],'version'=>$p['version']],$user),'Aucune modification refusée','Aucune modification');
$service->update($filet,['name'=>'Côtes de porc et de bœuf','item_kind'=>'finished_product','default_shelf_life_days'=>'','source_ids'=>[(string)$porc,(string)$boeuf],'version'=>$p['version']],$user);
$row=q('SELECT name,default_shelf_life_days FROM butchery_items WHERE id=?',[$filet])->fetch();
ok($row['name']==='Côtes de porc et de bœuf'&&$row['default_shelf_life_days']===null&&in_array('COTES-DE-PORC',codes($boeuf),true),'Produit modifié : nom, DLC retirée, désormais obtenu du porc et du bœuf');
no(fn()=>$service->update($filet,['name'=>'Autre nom','item_kind'=>'finished_product','source_ids'=>[(string)$porc],'version'=>$p['version']],$user),'Ancienne version refusée après modification','modifié entre-temps');
$p=product($filet);$service->update($filet,['name'=>$p['name'],'item_kind'=>'co_product','source_ids'=>[],'version'=>$p['version']],$user);
ok(!in_array('COTES-DE-PORC',codes($porc),true)&&!in_array('COTES-DE-PORC',codes($boeuf),true)&&product($filet)['sources']===[]&&product($filet)['item_kind']==='co_product','Produit retiré de la transformation : plus aucune matière');
$log=q("SELECT old_values,new_values FROM activity_logs WHERE action='butchery_product_update' AND entity_id=? ORDER BY id DESC LIMIT 1",[$filet])->fetch();
ok(strpos($log['old_values'],'sources')!==false&&strpos($log['new_values'],'co_product')!==false,'Modification tracée avec les anciennes et nouvelles valeurs');

// Edition from the material card.
$m=material($boeuf);
no(fn()=>$service->saveMaterial($boeuf,['product_ids'=>$m['products'],'version'=>$m['version']],$user),'Matière inchangée refusée','Aucune modification');
no(fn()=>$service->saveMaterial($boeuf,['product_ids'=>[(string)item('VOL-VIF')],'version'=>$m['version']],$user),'Poulet vif refusé comme produit de transformation','Produit introuvable');
no(fn()=>$service->saveMaterial(item('VOL-ENTIER'),['product_ids'=>[],'version'=>$m['version']],$user),'Un produit fini n’est pas une matière','Matière introuvable');
$service->saveMaterial($boeuf,['product_ids'=>[(string)item('MORCEAUX-BOEUF'),(string)item('VIANDE-DECOUPEE')],'version'=>$m['version']],$user);
ok(codes($boeuf)===['MORCEAUX-BOEUF','VIANDE-DECOUPEE'],'Bœuf : abats retirés, viande découpée ajoutée');
ok(codes($porc)===['ABATS','COTES-DE-PORC-FUMEES','MORCEAUX-PORC','PORC-COTELETTE','PORC-MAKOSO'],'Les abats restent un produit du porc');
no(fn()=>$service->saveMaterial($boeuf,['product_ids'=>[(string)item('MORCEAUX-BOEUF')],'version'=>$m['version']],$user),'Version de la matière périmée refusée','modifiés entre-temps');
ok(strpos((string)DagrilIntegrationTest::scalar("SELECT description FROM activity_logs WHERE action='butchery_material_products' AND entity_id=? ORDER BY id DESC LIMIT 1",[$boeuf]),'ajout de « Viande découpée »')!==false,'Changement de la matière tracé : ajouts et retraits');

// Transformation: only the products of the source material are accepted.
q("INSERT INTO butchery_raw_lots(site_id,item_id,lot_number,production_date,expiry_date,quantity_kg,reserved_kg,unit_cost,status) VALUES(?,?,?,?,?,40,0,2,'available')",[$bouch,$boeuf,'TEST-REF-'.bin2hex(random_bytes(3)),date('Y-m-d'),date('Y-m-d',strtotime('+4 days'))]);
$expiry=date('Y-m-d',strtotime('+3 days'));
$cut=fn($product)=>['source_item_id'=>(string)$boeuf,'lot_mode'=>'auto','production_date'=>date('Y-m-d'),'quantity_kg'=>'10','outputs'=>[['item_id'=>(string)$product,'quantity_kg'=>'9','expiry_date'=>$expiry]],'loss_confirmed'=>'1'];
no(fn()=>$flow->transform(k($cut(item('PORC-COTELETTE'))),$user),'Côtelettes de porc refusées pour une carcasse de bœuf','ne fait pas partie des produits de « Carcasse de bœuf »');
no(fn()=>$flow->transform(k($cut(item('ABATS'))),$user),'Abats retirés du bœuf refusés','Ajoutez-le dans Boucherie › Produits');
ok((float)DagrilIntegrationTest::scalar('SELECT SUM(reserved_kg) FROM butchery_raw_lots WHERE item_id=?',[$boeuf])===0.0,'Refus sans réservation de matière');
$order=$flow->transform(k($cut(item('VIANDE-DECOUPEE'))),$user);
ok((int)DagrilIntegrationTest::scalar('SELECT item_id FROM butchery_order_outputs WHERE order_id=?',[$order])===item('VIANDE-DECOUPEE'),'Produit de la matière accepté : viande découpée de bœuf');
$directory=$flow->butcheryDirectory();
$given=array_map('intval',$directory['productSources'][$boeuf]);sort($given);$expected=material($boeuf)['products'];sort($expected);
ok($given===$expected,'Formulaire de transformation : produits de chaque matière transmis');

// Pages: read-only consultation, administration and the transformation form.
$page=function(string $method){http_response_code(200);ob_start();(new PorcineFlowController())->$method();return ob_get_clean();};
$html=$page('products');
ok(strpos($html,'Produits de transformation')!==false&&strpos($html,'id="butchery-product-new"')!==false&&strpos($html,'id="butchery-material-'.$boeuf.'"')!==false,'Administrateur : référentiel, ajout d’un produit et choix par matière');
ok(strpos($html,'Warning:')===false&&strpos($html,'Fatal error')===false&&strpos($html,'Deprecated')===false,'Référentiel rendu sans erreur PHP');
$html=$page('production');
ok(strpos($html,'data-products-filtered')!==false&&strpos($html,'data-transform-products-hint')!==false&&strpos($html,'butchery/products')!==false,'Transformation : produits filtrés par matière et lien vers le référentiel');
$suffix=bin2hex(random_bytes(5));
q("INSERT INTO roles(name,slug,status) VALUES(?,?,'active')",['Lecture produits test','product-read-'.$suffix]);$role=(int)$db->lastInsertId();
q("INSERT INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE component='butchery' AND action IN ('read','create')",[$role]);
q("INSERT INTO users(role_id,name,email,password,status) VALUES(?,'Boucher produits',?,'unused','active')",[$role,'product-read-'.$suffix.'@example.test']);$uid=(int)$db->lastInsertId();
q("INSERT INTO user_sites(user_id,site_id,is_default,status) VALUES(?,?,1,'active')",[$uid,$bouch]);
q("INSERT INTO user_role_assignments(user_id,role_id,site_id,assignment_type,starts_at,approval_status) VALUES(?,?,?,'direct',NOW(),'approved')",[$uid,$role,$bouch]);
Auth::login(q('SELECT u.*,r.name role_name,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?',[$uid])->fetch());Auth::selectSite((string)$bouch);
$html=$page('products');
ok(strpos($html,'Consultation seule')!==false&&strpos($html,'id="butchery-product-new"')===false&&strpos($html,'butchery/products/materials/')===false,'Boucher sans administration : consultation seule, aucun formulaire');
$menu=[];foreach(Auth::menuGroups() as $group)foreach($group['items'] as $entry)$menu[]=$entry['path'];
ok(in_array('butchery/products',$menu,true),'Lien « Produits » dans le menu Boucherie');
no(fn()=>(new ButcheryProductService($db))->create(['name'=>'Produit interdit','item_kind'=>'finished_product','source_ids'=>[(string)$porc]],Auth::user()),'Création refusée sans droit d’administration','Permission refusee');
ok(!DagrilIntegrationTest::scalar("SELECT id FROM butchery_items WHERE name='Produit interdit'"),'Aucun produit créé après refus');
DagrilIntegrationTest::finish();
