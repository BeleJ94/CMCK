<?php
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base porcine jetable obligatoire pour ce test.');
require_once __DIR__.'/integration/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../app/core/Controller.php';
require_once __DIR__.'/../app/controllers/PorcineFlowController.php';

function coldRoomViewCheck($condition,string$message):void{DagrilIntegrationTest::check($condition,$message);if(!$condition)throw new RuntimeException($message);}
function coldRoomViewRender(?array$data=null):string{
    ob_start();
    try{
        if($data===null){(new PorcineFlowController())->coldRoom();}
        else{extract($data,EXTR_SKIP);require __DIR__.'/../app/views/porcine_flow/butchery.php';}
        return ob_get_clean();
    }catch(Throwable$error){ob_end_clean();throw$error;}
}
function coldRoomViewXPath(string$html):DOMXPath{
    coldRoomViewCheck(strpos($html,'Warning:')===false&&strpos($html,'Fatal error:')===false,'Rendu chambre froide sans avertissement PHP');
    $doc=new DOMDocument();@$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);return new DOMXPath($doc);
}

$admin=DagrilIntegrationTest::loginRole('administrateur');
$site=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='BOUCH' AND status='active' AND deleted_at IS NULL");
$farm=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='FARM-DIK' AND status='active' AND deleted_at IS NULL");
if(!$site||!$farm)throw new RuntimeException('Les sites BOUCH et FARM-DIK sont requis.');
Auth::selectSite((string)$site);
$directory=(new PorcineFlowService($db))->butcheryDirectory();
$scope='//*[@data-flow-directory]';
$xpath=coldRoomViewXPath(coldRoomViewRender());
coldRoomViewCheck($xpath->query($scope.'//table[contains(concat(" ",normalize-space(@class)," ")," cold-room-table ")]')->length===1,'Administrateur : un registre compact de chambre froide');
coldRoomViewCheck($xpath->query($scope.'//section[@id="cold-room-article-detail"]//table[contains(concat(" ",normalize-space(@class)," ")," cold-room-table ")]')->length===1,'Le registre des lots est présenté dans la modale de l’article');
$articles=[];
foreach(['raw'=>$directory['raw'],'finished'=>$directory['finished']]as$kind=>$lots){
    foreach($lots as$lot){
        $physical=(float)($kind==='raw'?$lot['quantity_kg']:$lot['cold_kg']);
        if($physical<=0)continue;
        $key=$kind.'-'.$lot['item_id'];
        if(!isset($articles[$key]))$articles[$key]=['kind'=>$kind,'lots'=>0];
        $articles[$key]['lots']++;
    }
}
coldRoomViewCheck($xpath->query($scope.'//*[@data-cold-card]')->length===count($articles),'Une seule carte par article et catégorie, même avec plusieurs lots');
foreach($articles as$key=>$article){
    $card=$scope.'//*[@data-cold-card and @data-article-key="'.$key.'"]';
    coldRoomViewCheck($xpath->query($card)->length===1&&$xpath->query($card.'//*[@data-cold-article="'.$key.'" and @data-workspace-modal-open="cold-room-article-detail"]')->length===1,'Article '.$key.' : carte unique et bouton de détail');
    coldRoomViewCheck($xpath->query($scope.'//section[@id="cold-room-article-detail"]//*[@data-flow-item and @data-article="'.$key.'"]')->length===$article['lots'],'Article '.$key.' : tous ses lots restent consultables sans duplication');
}
coldRoomViewCheck($xpath->query($scope.'//*[@data-cold-card]//img')->length===count($articles),'Les cartes articles réutilisent les illustrations de la boucherie');
coldRoomViewCheck($xpath->query($scope.'//button[@data-cold-supply]')->length>0,'Administrateur : approvisionnement proposé sur les produits disponibles');
coldRoomViewCheck($xpath->query($scope.'//section[@id="flow-supply"]//form')->length===1,'Administrateur : formulaire de mouvement existant conservé');
coldRoomViewCheck($xpath->query($scope.'//a[contains(normalize-space(.),"Transformer des carcasses")]')->length===1,'Administrateur : accès à la transformation conservé');
coldRoomViewCheck($xpath->query($scope.'//button[starts-with(@data-workspace-modal-open,"cold-room-kpi-")]')->length===4,'Administrateur : quatre indicateurs dédiés au froid');

// Permissions fixtures are rolled back, including their role and account.
$suffix=bin2hex(random_bytes(5));$email='cold-room-read-'.$suffix.'@example.test';$roleSlug='cold-room-read-'.$suffix;
$db->beginTransaction();
try{
    $db->prepare("INSERT INTO roles(name,slug,status) VALUES('Lecture chambre froide test',?,'active')")->execute([$roleSlug]);$role=(int)$db->lastInsertId();
    $db->prepare("INSERT INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE component='butchery' AND action='read'")->execute([$role]);
    $db->prepare("INSERT INTO users(role_id,name,email,password,status) VALUES(?,'Lecteur chambre froide',?,'unused','active')")->execute([$role,$email]);$userId=(int)$db->lastInsertId();
    $db->prepare("INSERT INTO user_sites(user_id,site_id,is_default,status) VALUES(?,?,1,'active')")->execute([$userId,$site]);
    $db->prepare("INSERT INTO user_role_assignments(user_id,role_id,site_id,assignment_type,starts_at,approval_status) VALUES(?,?,?,'direct',NOW(),'approved')")->execute([$userId,$role,$site]);
    $reader=DagrilIntegrationTest::row('SELECT u.*,r.name role_name,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?',[$userId]);
    Auth::login($reader);Auth::selectSite((string)$site);
    coldRoomViewCheck(Auth::can('butchery','read',$site)&&!Auth::can('butchery','update',$site)&&!Auth::can('butchery','create',$site),'Compte de test limité à la lecture en boucherie');
    $xpath=coldRoomViewXPath(coldRoomViewRender());
    coldRoomViewCheck($xpath->query($scope.'//button[starts-with(@data-workspace-modal-open,"cold-room-detail-")]')->length>0,'Lecture seule : les détails des lots restent accessibles');
    coldRoomViewCheck($xpath->query($scope.'//button[starts-with(@data-workspace-modal-open,"cold-room-kpi-")]')->length===4,'Lecture seule : les quatre indicateurs restent accessibles');
    coldRoomViewCheck($xpath->query($scope.'//*[@data-cold-supply or @data-workspace-modal-open="flow-supply"]')->length===0&&$xpath->query($scope.'//*[@id="flow-supply"]')->length===0,'Lecture seule : aucun bouton ou formulaire d’approvisionnement');
    coldRoomViewCheck($xpath->query($scope.'//a[contains(normalize-space(.),"Transformer des carcasses")]')->length===0,'Lecture seule : aucune action de transformation');
    coldRoomViewCheck($xpath->query($scope.'//form')->length===0,'Lecture seule : aucune opération de mutation dans le répertoire');
}finally{
    if($db->inTransaction())$db->rollBack();
    Auth::login($admin);Auth::selectSite((string)$site);
}
coldRoomViewCheck((int)DagrilIntegrationTest::scalar('SELECT COUNT(*) FROM users WHERE email=?',[$email])===0&&(int)DagrilIntegrationTest::scalar('SELECT COUNT(*) FROM roles WHERE slug=?',[$roleSlug])===0,'Le compte et le rôle de lecture sont supprimés par rollback');

Auth::selectSite((string)$farm);
$xpath=coldRoomViewXPath(coldRoomViewRender());
coldRoomViewCheck($xpath->query($scope.'//button[contains(normalize-space(.),"Travailler sur Boucherie")]')->length===1,'Autre site : l’invite de changement de contexte est visible');
coldRoomViewCheck($xpath->query($scope.'//*[@data-cold-supply or @data-workspace-modal-open="flow-supply"]')->length===0&&$xpath->query($scope.'//*[@id="flow-supply"]')->length===0,'Autre site : aucune action ou modale d’approvisionnement');
coldRoomViewCheck($xpath->query($scope.'//a[contains(normalize-space(.),"Transformer des carcasses")]')->length===0,'Autre site : aucune action de transformation');
coldRoomViewCheck($xpath->query($scope.'//button[starts-with(@data-workspace-modal-open,"cold-room-detail-")]')->length>0,'Autre site : la consultation des détails reste disponible');
Auth::selectSite((string)$site);

// Render empty presentation arrays without modifying stock or its movement ledgers.
$empty=array_replace($directory,['raw'=>[],'finished'=>[],'section'=>'cold-room','success'=>null,'error'=>null]);
$xpath=coldRoomViewXPath(coldRoomViewRender($empty));
$values=[];foreach($xpath->query($scope.'//*[contains(concat(" ",normalize-space(@class)," ")," ui-kpi__value ")]')as$value)$values[]=trim($value->textContent);
coldRoomViewCheck($values===['0,000 kg','0,000 kg','0,000 kg','0'],'Stock vide : les quatre indicateurs affichent zéro');
coldRoomViewCheck($xpath->query($scope.'//*[@data-flow-item]')->length===0&&$xpath->query($scope.'//section[starts-with(@id,"cold-room-detail-")]')->length===0,'Stock vide : aucun lot ni fausse modale de lot');
coldRoomViewCheck($xpath->query($scope.'//*[@data-cold-card]')->length===0,'Stock vide : aucune fausse carte article');
coldRoomViewCheck($xpath->query($scope.'//p[contains(normalize-space(.),"La chambre froide est vide")]')->length===1,'Stock vide : message explicatif visible');
coldRoomViewCheck($xpath->query($scope.'//*[@data-flow-table-body]')->length===1&&$xpath->query($scope.'//*[@data-flow-page]')->length===2&&$xpath->query($scope.'//*[@data-flow-page-info]')->length===1,'Stock vide : tableau et pagination restent présents');
coldRoomViewCheck($xpath->query($scope.'//*[@data-cold-search]')->length===1&&$xpath->query($scope.'//*[@data-cold-kind]')->length===1,'Stock vide : recherche et catégories des articles restent utilisables');
coldRoomViewCheck($xpath->query($scope.'//*[@data-flow-search]')->length===1&&$xpath->query($scope.'//*[@data-flow-state]')->length===1&&$xpath->query($scope.'//*[@data-flow-kind]')->length===0,'Les filtres de lots restent dans leur modale sans catégorie redondante');

// A product identifier can exist in both registers: category is part of its key.
$rawExample=$directory['raw'][0]??null;$finishedExample=$directory['finished'][0]??null;
if(!$rawExample||!$finishedExample)throw new RuntimeException('Une matière et un produit transformé sont nécessaires au scénario de regroupement.');
$rawExample=array_replace($rawExample,['item_id'=>987654,'quantity_kg'=>12,'reserved_kg'=>2,'expiry_date'=>date('Y-m-d',strtotime('+3 days')),'status'=>'reserved']);
$rawTwin=array_replace($rawExample,['id'=>987652,'lot_number'=>'REGROUPEMENT-MATIERE-2','quantity_kg'=>7,'reserved_kg'=>0,'status'=>'available']);
$finishedExample=array_replace($finishedExample,['item_id'=>987654,'cold_kg'=>8,'cold_available_kg'=>6,'reserved_kg'=>2,'expiry_date'=>date('Y-m-d',strtotime('+3 days')),'status'=>'reserved']);
$grouped=array_replace($directory,['raw'=>[$rawExample,$rawTwin],'finished'=>[$finishedExample],'section'=>'cold-room','success'=>null,'error'=>null]);
$xpath=coldRoomViewXPath(coldRoomViewRender($grouped));
coldRoomViewCheck($xpath->query($scope.'//*[@data-cold-card]')->length===2,'Même identifiant article : matières et produits transformés restent deux catégories distinctes');
coldRoomViewCheck($xpath->query($scope.'//*[@data-cold-card and @data-article-key="raw-987654"]')->length===1&&$xpath->query($scope.'//*[@data-cold-card and @data-article-key="finished-987654"]')->length===1,'Les clés article incluent le type de stock');
coldRoomViewCheck($xpath->query($scope.'//section[@id="cold-room-article-detail"]//*[@data-flow-item and @data-article="raw-987654"]')->length===2,'Les deux lots d’une même matière partagent une carte et restent distincts dans la modale');
DagrilIntegrationTest::finish();
