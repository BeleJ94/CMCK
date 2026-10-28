<?php
/** Commercial distribution, lot 1 (047): client records, rate of the day, price lists and main office depots. */
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Utilisez une base jetable créée par setup_porcine_flow_test.php.');
require_once __DIR__.'/integration/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../app/core/Controller.php';
require_once __DIR__.'/../app/controllers/CustomerController.php';require_once __DIR__.'/../app/controllers/SalesSettingsController.php';
function q($sql,$params=[]){global $db;$q=$db->prepare($sql);$q->execute($params);return $q;}
function ok($condition,$message){DagrilIntegrationTest::check($condition,$message);if(!$condition)throw new RuntimeException($message);}
function no($callback,$message,$expected){try{$callback();}catch(RuntimeException $e){ok(strpos($e->getMessage(),$expected)!==false,$message.' · « '.$e->getMessage().' »');return;}ok(false,$message);}
function customer($id){return q('SELECT * FROM customers WHERE id=?',[$id])->fetch();}
function version($id){return CustomerService::version(customer($id));}
$admin=DagrilIntegrationTest::loginRole('administrateur');
$customers=new CustomerService($db);$settings=new SalesSettingsService($db);
ok($customers->ready()&&$settings->ready(),'Migration 047 détectée : clients, taux et tarifs actifs');
$company=['customer_type'=>'company','name'=>'Boulangerie du Fleuve','trade_name'=>'BDF','rccm'=>'cd/kng/rccm/24-b-0101','id_nat'=>'01-A1234','nif'=>'a1234567x','contact_name'=>'Mme Kabange','contact_role'=>'Achats','phone'=>'+243 81 000 0001','email'=>'achats@bdf.test','address'=>'Avenue du Commerce 12','city'=>'Kinshasa','billing_currency'=>'USD','notes'=>'Livraison le matin'];

// Client records: code, legal identifiers, duplicates and validation.
$first=$customers->create($company,$admin);$c=customer($first);
ok($c['code']==='CLI-0001'&&$c['rccm']==='CD/KNG/RCCM/24-B-0101'&&$c['nif']==='A1234567X'&&$c['status']==='active'&&(int)$c['created_by']===(int)$admin['id'],'Client entreprise créé : code CLI-0001, identifiants en majuscules');
$person=$customers->create(['customer_type'=>'individual','name'=>'Jean  Mukendi','trade_name'=>'Ignoré','nif'=>'X1','phone'=>'0990000002','billing_currency'=>'CDF'],$admin);$p=customer($person);
ok($p['code']==='CLI-0002'&&$p['name']==='Jean Mukendi'&&$p['nif']===null&&$p['trade_name']===null&&$p['billing_currency']==='CDF','Particulier : espaces normalisés, identifiants légaux et nom commercial ignorés');
no(fn()=>$customers->create(array_replace($company,['name'=>'']),$admin),'Nom obligatoire','Raison sociale : champ obligatoire');
no(fn()=>$customers->create(array_replace($company,['name'=>'Autre','phone'=>'abc']),$admin),'Téléphone invalide refusé','Téléphone');
no(fn()=>$customers->create(array_replace($company,['name'=>'Autre','email'=>'pas-un-mail','rccm'=>'','id_nat'=>'','nif'=>'']),$admin),'E-mail invalide refusé','e-mail');
no(fn()=>$customers->create(array_replace($company,['name'=>'BOULANGERIE DU FLEUVE','nif'=>'']),$admin),'Doublon de nom refusé, sans tenir compte de la casse','CLI-0001 porte déjà ce nom');
no(fn()=>$customers->create(array_replace($company,['name'=>'Autre société','rccm'=>'','id_nat'=>'']),$admin),'Doublon de NIF refusé','NIF est déjà enregistré pour le client CLI-0001');
no(fn()=>$customers->create(array_replace($company,['billing_currency'=>'EUR']),$admin),'Devise inconnue refusée','devise');

// Edition with concurrency check, then blocking with a reason.
$v=version($first);
no(fn()=>$customers->update($first,$company+['version'=>'perimee'],$admin),'Version périmée refusée','modifiée entre-temps');
no(fn()=>$customers->update($first,$company+['version'=>$v],$admin),'Aucune modification refusée','Aucune modification');
$customers->update($first,array_replace($company,['phone'=>'+243 81 000 0009','city'=>'Kinshasa / Gombe'])+['version'=>$v],$admin);
ok(customer($first)['phone']==='+243 81 000 0009'&&(int)customer($first)['updated_by']===(int)$admin['id'],'Fiche modifiée et auteur de la modification tracé');
no(fn()=>$customers->update($first,array_replace($company,['city'=>'Matadi'])+['version'=>$v],$admin),'Ancienne version refusée après modification','modifiée entre-temps');
no(fn()=>$customers->setStatus($first,['status'=>'blocked','reason'=>' '],$admin),'Blocage sans motif refusé','motif du blocage');
$customers->setStatus($first,['status'=>'blocked','reason'=>'Impayés'],$admin);$c=customer($first);
ok($c['status']==='blocked'&&$c['blocked_reason']==='Impayés'&&$c['blocked_at']!==null,'Client bloqué avec son motif');
no(fn()=>$customers->setStatus($first,['status'=>'blocked','reason'=>'Encore'],$admin),'Double blocage refusé','déjà bloqué');
$customers->setStatus($first,['status'=>'active'],$admin);ok(customer($first)['status']==='active'&&customer($first)['blocked_reason']===null,'Client débloqué, motif effacé');
ok((int)DagrilIntegrationTest::scalar("SELECT COUNT(*) FROM activity_logs WHERE entity_type='customers' AND entity_id=? AND action IN ('customer_create','customer_update','customer_block','customer_unblock')",[$first])===4,'Création, modification, blocage et déblocage tracés dans l’audit');

// Rate of the day: positive, different from the rate in force, large variations confirmed.
$direction=DagrilIntegrationTest::loginRole('direction');
ok($settings->currentRate()===null,'Aucun taux avant la première saisie');
no(fn()=>$settings->setRate(['rate'=>'0'],$direction),'Taux nul refusé','supérieur à zéro');
no(fn()=>$settings->setRate(['rate'=>'abc'],$direction),'Taux illisible refusé','Saisissez le taux');
$settings->setRate(['rate'=>'2850','notes'=>'Taux banque'],$direction);ok($settings->currentRate()===2850.0,'Premier taux enregistré par la direction : 1 USD = 2 850 CDF');
no(fn()=>$settings->setRate(['rate'=>'2850'],$direction),'Taux identique refusé','déjà en vigueur');
no(fn()=>$settings->setRate(['rate'=>'3300'],$direction),'Variation de +15,8 % à confirmer','Confirmez la variation');
$settings->setRate(['rate'=>'3300','confirm_variation'=>'1'],$direction);$settings->setRate(['rate'=>'3350,5'],$direction);
ok($settings->currentRate()===3350.5&&(int)DagrilIntegrationTest::scalar('SELECT COUNT(*) FROM exchange_rates')===3,'Variation confirmée puis petit ajustement à la virgule : historique de 3 taux');

// Price lists per product, bag format and channel.
$flour=(int)DagrilIntegrationTest::scalar("SELECT id FROM products WHERE code='FARINE-MAIS'");$feed=(int)DagrilIntegrationTest::scalar("SELECT id FROM products WHERE code='ALIMENT-BETAIL'");
$f50=(int)DagrilIntegrationTest::scalar("SELECT id FROM bag_formats WHERE code='EMB-F50'");$a30=(int)DagrilIntegrationTest::scalar("SELECT id FROM bag_formats WHERE code='EMB-A30'");
no(fn()=>$settings->setPrices(['product_id'=>$flour,'bag_format_id'=>$a30,'price_company'=>'30'],$direction),'Format d’aliment refusé pour la farine','pas vendu pour ce produit');
no(fn()=>$settings->setPrices(['product_id'=>$flour,'bag_format_id'=>$f50],$direction),'Aucun tarif saisi refusé','au moins un tarif');
no(fn()=>$settings->setPrices(['product_id'=>$flour,'bag_format_id'=>$f50,'price_company'=>'-3'],$direction),'Tarif négatif refusé','montant en USD');
$settings->setPrices(['product_id'=>$flour,'bag_format_id'=>$f50,'price_company'=>'35','price_counter'=>'37,50'],$direction);
no(fn()=>$settings->setPrices(['product_id'=>$flour,'bag_format_id'=>$f50,'price_company'=>'35.00','price_counter'=>'37.5'],$direction),'Tarifs identiques refusés','déjà en vigueur');
$settings->setPrices(['product_id'=>$flour,'bag_format_id'=>$f50,'price_company'=>'35','price_counter'=>'38'],$direction);
$row=current(array_filter($settings->directory()['prices'],static fn($r)=>(int)$r['product_id']===$flour&&(int)$r['bag_format_id']===$f50));
ok((float)$row['company']['price_usd']===35.0&&(float)$row['counter']['price_usd']===38.0&&count($row['history'])===3,'Tarifs farine 50 kg : entreprises 35 USD, comptoir 38 USD, seul le tarif changé est historisé');
$grid=$settings->directory()['prices'];
ok(count(array_filter($grid,static fn($r)=>(int)$r['product_id']===$feed))>=2&&!array_filter($grid,static fn($r)=>$r['product_code']==='MAIS-BRUT'),'Grille : formats d’aliment bétail présents, matière première absente');

// Main office depots.
$depots=$settings->directory()['depots'];$main=array_column(array_filter($depots,static fn($d)=>(int)$d['main_office']===1),'code');sort($main);
ok($main===['DEP-DEC','DEP-DEV'],'Migration : DEVCO et DECO forment le bureau principal');
$deco=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='DEP-DEC'");$devco=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='DEP-DEV'");
no(fn()=>$settings->setMainOffice(['site_ids'=>[$deco]],$direction),'Direction sans droit d’administration refusée','Permission refusee');
$admin=DagrilIntegrationTest::loginRole('administrateur');
no(fn()=>$settings->setMainOffice(['site_ids'=>[]],$admin),'Bureau principal vide refusé','au moins un dépôt');
no(fn()=>$settings->setMainOffice(['site_ids'=>[(string)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='MINO'")]],$admin),'La minoterie n’est pas un dépôt','Dépôt introuvable');
$settings->setMainOffice(['site_ids'=>[(string)$deco]],$admin);ok((int)DagrilIntegrationTest::scalar('SELECT main_office FROM sites WHERE id=?',[$devco])===0,'Bureau principal réduit à DECO');
$settings->setMainOffice(['site_ids'=>[(string)$deco,(string)$devco]],$admin);ok((int)DagrilIntegrationTest::scalar('SELECT SUM(main_office) FROM sites')===2,'DEVCO et DECO de nouveau au bureau principal');

// Permissions of the distribution agent: create, but neither edit, block, nor set rates and prices.
$agent=DagrilIntegrationTest::loginRole('agent-distribution');
$third=$customers->create(['customer_type'=>'company','name'=>'Dépôt Kasa-Vubu','phone'=>'0810000003','billing_currency'=>'USD'],$agent);ok(customer($third)['code']==='CLI-0003','L’agent de distribution crée une fiche client');
no(fn()=>$customers->update($third,['customer_type'=>'company','name'=>'Dépôt Kasa-Vubu 2','phone'=>'0810000003','billing_currency'=>'USD','version'=>version($third)],$agent),'Modification refusée à l’agent','Permission refusee');
no(fn()=>$customers->setStatus($third,['status'=>'blocked','reason'=>'Test'],$agent),'Blocage refusé à l’agent','Permission refusee');
no(fn()=>$settings->setRate(['rate'=>'3400'],$agent),'Taux refusé à l’agent','Permission refusee');
$page=function(string $class){http_response_code(200);ob_start();(new $class())->index();return ob_get_clean();};
$html=$page('CustomerController');
ok(strpos($html,'Boulangerie du Fleuve')!==false&&strpos($html,'id="customer-new"')!==false&&strpos($html,'id="customer-edit-')===false&&strpos($html,'id="customer-block-')===false,'Agent : répertoire et création, sans modification ni blocage');
$html=$page('SalesSettingsController');
ok(strpos($html,'3 350,50 CDF')!==false&&strpos($html,'Consultation seule')!==false&&strpos($html,'data-sales-rate-form')===false&&strpos($html,'id="sales-price-')===false,'Agent : tarifs et taux en consultation seule');
$menu=[];foreach(Auth::menuGroups() as $group)foreach($group['items'] as $entry)$menu[]=$entry['path'];
ok(in_array('customers',$menu,true)&&in_array('sales/settings',$menu,true),'Menu de l’agent : Clients, Tarifs et taux');
DagrilIntegrationTest::loginRole('administrateur');
foreach(['CustomerController','SalesSettingsController'] as $class){$html=$page($class);ok(strpos($html,'Warning:')===false&&strpos($html,'Fatal error')===false&&strpos($html,'Deprecated')===false,$class.' : rendu sans erreur PHP');}
ok(strpos($html,'data-sales-rate-form')!==false&&strpos($html,'id="sales-main-office"')!==false&&strpos($html,'117 268 CDF')!==false&&strpos($html,'127 319 CDF')!==false,'Administrateur : taux, tarifs et bureau principal modifiables ; équivalents CDF au taux du jour (35 et 38 USD × 3 350,5)');
DagrilIntegrationTest::finish();
