<?php
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base jetable obligatoire.');
require_once __DIR__.'/integration/TestBootstrap.php';$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../app/services/PorcineFlowService.php';
$fx=json_decode(file_get_contents(getenv('DAGRIL_FLOW_FIXTURE')),true);if($fx['database']!==getenv('DAGRIL_DB_DATABASE'))throw new RuntimeException('Fixture incompatible.');
$flow=new PorcineFlowService($db);$user=DagrilIntegrationTest::loginRole('administrateur');Auth::selectSite((string)$fx['butchery']);
function posq($sql,$values=[]){global$db;$q=$db->prepare($sql);$q->execute($values);return$q;}
function poscheck($ok,$message){DagrilIntegrationTest::check($ok,$message);if(!$ok)throw new RuntimeException($message);}
function posno($callback,$message){$failed=false;try{$callback();}catch(Throwable$e){$failed=true;}poscheck($failed,$message);}
function poskey($data=[]){return ['operation_key'=>bin2hex(random_bytes(16))]+$data;}
function possnapshot(){return [posq('SELECT id,quantity_kg,reserved_kg FROM butchery_finished_lots ORDER BY id')->fetchAll(),posq('SELECT id,quantity_kg FROM butchery_counter_stocks ORDER BY id')->fetchAll(),posq('SELECT COUNT(*) FROM butchery_pos_tickets')->fetchColumn(),posq('SELECT COUNT(*) FROM butchery_sales')->fetchColumn(),posq('SELECT COUNT(*) FROM butchery_counter_movements')->fetchColumn(),posq('SELECT COUNT(*) FROM livestock_butchery_operations')->fetchColumn()];}
poscheck($flow->posAvailable(),'Migration POS disponible');
foreach([[$fx['item1'],'10.00'],[$fx['item2'],'5.00']]as[$item,$price]){$payload=poskey(['item_id'=>$item,'currency'=>'USD','price'=>$price]);$flow->posPrice($payload,$user);poscheck($flow->posPrice($payload,$user)===$item,'Tarif idempotent');}
posno(fn()=>$flow->posPrice(poskey(['item_id'=>$fx['item1'],'currency'=>'EUR','price'=>'10']),$user),'Devise non configurée refusée');
posno(fn()=>$flow->posPrice(poskey(['item_id'=>$fx['item1'],'currency'=>'USD','price'=>'-1']),$user),'Tarif négatif refusé');
// A second, later-expiring lot of the same article in the same counter.
$base=posq('SELECT l.*,o.order_id FROM butchery_finished_lots l JOIN butchery_order_outputs o ON o.id=l.order_output_id WHERE l.id=?',[$fx['lot1']])->fetch();
posq("INSERT INTO butchery_order_outputs(order_id,item_id,output_type,quantity_kg,expiry_date) VALUES(?,?,'finished_product',10,?)",[$base['order_id'],$fx['item1'],date('Y-m-d',strtotime('+8 days'))]);$output=(int)$db->lastInsertId();
posq("INSERT INTO butchery_finished_lots(site_id,item_id,order_output_id,lot_number,production_date,expiry_date,quantity_kg,reserved_kg,unit_cost,status) VALUES(?,?,?,?,?,?,10,0,0,'available')",[$fx['butchery'],$fx['item1'],$output,'POS-LATER-'.bin2hex(random_bytes(4)),date('Y-m-d'),date('Y-m-d',strtotime('+8 days'))]);$later=(int)$db->lastInsertId();
posq('INSERT INTO butchery_counter_stocks(counter_id,finished_lot_id,quantity_kg) VALUES(?,?,10)',[$fx['counter'],$later]);
$firstStock=(float)posq('SELECT quantity_kg FROM butchery_counter_stocks WHERE id=?',[$fx['counterStock']])->fetchColumn();
$sale=poskey(['counter_id'=>$fx['counter'],'currency'=>'USD','payment_method'=>'cash','received_amount'=>'300.00','customer_name'=>'Client <script>test</script>','lines'=>[['item_id'=>$fx['item1'],'quantity_kg'=>number_format($firstStock+1,3,'.',''),'unit_price'=>'10.00'],['item_id'=>$fx['item2'],'quantity_kg'=>'1.500','unit_price'=>'5.00']]]);
$before=possnapshot();$bad=$sale;$bad['lines'][1]['quantity_kg']='999.000';posno(fn()=>$flow->posCheckout($bad,$user),'Panier dont un article manque refusé');poscheck(possnapshot()===$before,'Aucun effet partiel : stocks, tickets, ventes et opérations inchangés');
$bad=$sale;$bad['lines'][0]['unit_price']='1.00';posno(fn()=>$flow->posCheckout($bad,$user),'Prix du client falsifié refusé');
$bad=$sale;$bad['received_amount']='0.01';posno(fn()=>$flow->posCheckout($bad,$user),'Paiement insuffisant refusé');
$ticket=$flow->posCheckout($sale,$user);$after=possnapshot();poscheck($flow->posCheckout($sale,$user)===$ticket&&possnapshot()===$after,'Retry du même paiement : un seul ticket et un seul débit');
$receipt=$flow->posTicket($ticket);poscheck(count($receipt['lines'])===2,'Deux articles sur un seul reçu');
poscheck((float)$receipt['total']===($firstStock+1)*10+7.5,'Total du panier exact');poscheck((float)$receipt['change_amount']===300-(float)$receipt['total'],'Monnaie exacte');
poscheck((float)posq('SELECT quantity_kg FROM butchery_counter_stocks WHERE id=?',[$fx['counterStock']])->fetchColumn()===0.0,'Lot à DLC la plus proche épuisé en premier');poscheck((float)posq('SELECT quantity_kg FROM butchery_counter_stocks WHERE counter_id=? AND finished_lot_id=?',[$fx['counter'],$later])->fetchColumn()===9.0,'Complément prélevé dans le second lot');
poscheck((int)posq('SELECT COUNT(*) FROM butchery_pos_allocations a JOIN butchery_pos_lines l ON l.id=a.line_id WHERE l.ticket_id=?',[$ticket])->fetchColumn()===3,'Trois allocations traçables pour les deux articles');
$flow->posPrice(poskey(['item_id'=>$fx['item1'],'currency'=>'USD','price'=>'11']),$user);poscheck((float)$flow->posTicket($ticket)['lines'][0]['unit_price']===10.0,'Le reçu conserve le tarif au moment de la vente');
posno(fn()=>$flow->posCheckout(array_replace($sale,['received_amount'=>'301']),$user),'Clé réutilisée avec un autre montant refusée');
posno(fn()=>$flow->posCancel($ticket,poskey(['reason'=>'Erreur','not_delivered'=>'0']),$user),'Annulation sans confirmation de non-livraison refusée');
$cancel=poskey(['reason'=>'Test erreur de saisie','not_delivered'=>'1']);$flow->posCancel($ticket,$cancel,$user);$cancelled=possnapshot();poscheck($flow->posCancel($ticket,$cancel,$user)===$ticket&&possnapshot()===$cancelled,'Annulation idempotente');
poscheck($flow->posTicket($ticket)['status']==='cancelled','Reçu annulé marqué et conservé');poscheck((float)posq('SELECT quantity_kg FROM butchery_counter_stocks WHERE id=?',[$fx['counterStock']])->fetchColumn()===$firstStock,'Stock restauré au comptoir après erreur de saisie');
posno(fn()=>$flow->posCancel($ticket,poskey(['reason'=>'Double annulation','not_delivered'=>'1']),$user),'Seconde annulation distincte refusée');
$flow->posPrice(poskey(['item_id'=>$fx['item1'],'currency'=>'USD','price'=>'10']),$user);
$single=poskey(['counter_id'=>$fx['counter'],'currency'=>'USD','payment_method'=>'mobile_money','received_amount'=>'10.00','customer_name'=>'Test','lines'=>[['item_id'=>$fx['item1'],'quantity_kg'=>'1.000','unit_price'=>'10.00']]]);
$bad=$single;$bad['received_amount']='11';posno(fn()=>$flow->posCheckout($bad,$user),'Paiement non espèces doit être exact');
$paid=$flow->posCheckout($single,$user);poscheck($flow->posTicket($paid)['payment_method']==='mobile_money','Paiement Mobile Money enregistré');$flow->posCancel($paid,poskey(['reason'=>'Fin du test','not_delivered'=>'1']),$user);
posq('UPDATE butchery_finished_lots SET expiry_date=? WHERE id IN(?,?)',[date('Y-m-d',strtotime('-1 day')),$fx['lot1'],$later]);$before=possnapshot();posno(fn()=>$flow->posCheckout(poskey(array_diff_key($single,['operation_key'=>true])),$user),'Lots périmés exclus du prélèvement');poscheck(possnapshot()===$before,'Refus de vente périmée sans effet');posq('UPDATE butchery_finished_lots SET expiry_date=? WHERE id=?',[$fx['expiry'],$fx['lot1']]);posq('UPDATE butchery_finished_lots SET expiry_date=? WHERE id=?',[date('Y-m-d',strtotime('+8 days')),$later]);
Auth::selectSite((string)$fx['farm']);posno(fn()=>$flow->posCheckout(poskey(array_diff_key($single,['operation_key'=>true])),$user),'Encaissement depuis un autre site refusé');Auth::selectSite((string)$fx['butchery']);
poscheck($flow->posTicket('incorrect')===null,'Référence de reçu invalide sans erreur fatale');
// Integer monetary rounding: 125 grams at 0.12 per kg gives 0.02, never 0.01.
$flow->posPrice(poskey(['item_id'=>$fx['item1'],'currency'=>'CDF','price'=>'0.12']),$user);
$round=poskey(['counter_id'=>$fx['counter'],'currency'=>'CDF','payment_method'=>'cash','received_amount'=>'0.02','customer_name'=>'Arrondi','lines'=>[['item_id'=>$fx['item1'],'quantity_kg'=>'0.125','unit_price'=>'0.12']]]);
$rounded=$flow->posCheckout($round,$user);poscheck($flow->posTicket($rounded)['total']==='0.02','Arrondi monétaire au centime calculé avec des entiers');$flow->posCancel($rounded,poskey(['reason'=>'Fin test arrondi','not_delivered'=>'1']),$user);
$before=possnapshot();$db->exec(file_get_contents(__DIR__.'/../database/040_add_butchery_pos.sql'));poscheck(possnapshot()===$before,'Migration réapplicable sans modifier les ventes ni les stocks');
// Read-only profile: consult receipts, never change prices or sell/cancel.
$db->beginTransaction();
try{
 $slug='pos-reader-'.bin2hex(random_bytes(5));posq("INSERT INTO roles(name,slug,status) VALUES('Lecture caisse',?,'active')",[$slug]);$role=(int)$db->lastInsertId();posq("INSERT INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE component='butchery' AND action='read'",[$role]);
 posq("INSERT INTO users(role_id,name,email,password,status) VALUES(?,'Lecteur caisse',?,'unused','active')",[$role,$slug.'@example.test']);$reader=(int)$db->lastInsertId();posq("INSERT INTO user_sites(user_id,site_id,is_default,status) VALUES(?,?,1,'active')",[$reader,$fx['butchery']]);posq("INSERT INTO user_role_assignments(user_id,role_id,site_id,assignment_type,starts_at,approval_status) VALUES(?,?,?,'direct',NOW(),'approved')",[$reader,$role,$fx['butchery']]);Auth::login(posq('SELECT * FROM users WHERE id=?',[$reader])->fetch());Auth::selectSite((string)$fx['butchery']);
 poscheck($flow->posTicket($ticket)['status']==='cancelled','Lecture seule : reçu consultable');
 posno(fn()=>$flow->posCheckout($single,Auth::user()),'Lecture seule : encaissement refusé');posno(fn()=>$flow->posPrice(poskey(['item_id'=>$fx['item1'],'currency'=>'USD','price'=>'1']),Auth::user()),'Lecture seule : tarif refusé');posno(fn()=>$flow->posCancel($ticket,$cancel,Auth::user()),'Lecture seule : annulation refusée');
}finally{if($db->inTransaction())$db->rollBack();Auth::login($user);Auth::selectSite((string)$fx['butchery']);}
// Keep the same standard prices available for browser coverage.
DagrilIntegrationTest::finish();
