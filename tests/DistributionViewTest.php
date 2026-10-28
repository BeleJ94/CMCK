<?php
// Client exits hub rendered without a database: orders to deliver, one history of BL and BS, escaping and totals.
require __DIR__.'/../app/helpers/functions.php';
class Auth{public static function start(){}public static function can($component,$action){return true;}public static function user(){return ['id'=>1,'name'=>'Agent'];}}
class Model{}
require __DIR__.'/../app/services/CustomerDeliveryService.php';
$fail=static function($message){fwrite(STDERR,$message.PHP_EOL);exit(1);};
$today=date('Y-m-d');
$success='Livraison BL-2026-0003 enregistrée.';$error=null;$exitDone=null;$deliveryDone='3';
$availableStocks=[['id'=>1,'site_code'=>'SRC','site_name'=>'Minoterie','stock_date'=>'2026-09-01 08:00:00','product_name'=>'Farine','format_name'=>'25 kg','quantity_bags'=>30,'total_weight_kg'=>750,'weight_kg'=>25]];
$customers=[['id'=>4,'code'=>'CLI-0004','name'=>'Client <b>gras</b>','phone'=>null]];
$distributions=[];for($i=1;$i<=6;$i++)$distributions[]=['id'=>$i,'site_code'=>'SRC','product_name'=>'Farine','format_name'=>'25 kg','quantity_bags'=>2,'total_weight_kg'=>50,'status'=>$i%2?'validated':'cancelled','distributed_at'=>$today.' 0'.$i.':00:00','recipient_name'=>'Client test','exit_voucher'=>'BS-TEST-'.$i,'official_document_number'=>null,'finished_stock_id'=>1,'transporter'=>'<script>unsafe</script>','agent_name'=>'Agent','validator_name'=>'Responsable','customer_id'=>null];
$line=['id'=>11,'order_id'=>7,'product_name'=>'Farine','format_name'=>'Sac 50 kg','weight_kg'=>50,'unit_price_usd'=>35,'quantity_bags'=>60,'delivered_bags'=>20,'to_deliver'=>40];
$order=['id'=>7,'order_number'=>'BC-2026-0007','customer_code'=>'CLI-0001','customer_name'=>'Grossiste <i>Matadi</i>','customer_phone'=>'0810000001','customer_status'=>'active','blocked_reason'=>null,'currency'=>'CDF','exchange_rate'=>2800,'lines'=>[$line],
 'options'=>['office-3'=>['stage'=>'office','site_id'=>3,'site_name'=>'Dépôt DECO','lines'=>[11=>15],'bags'=>15],'source-1'=>['stage'=>'source','site_id'=>1,'site_name'=>'Minoterie','lines'=>[11=>10],'bags'=>10]],
 'quantity_bags'=>60,'delivered_bags'=>20,'source_bags'=>10,'office_bags'=>15,'transit_bags'=>15,'remaining_bags'=>0,'to_deliver'=>40,'ready_bags'=>25,'amount_usd'=>2100];
$waiting=['id'=>8,'order_number'=>'BC-2026-0008','customer_code'=>'CLI-0002','customer_name'=>'Boutique','customer_phone'=>null,'customer_status'=>'active','blocked_reason'=>null,'currency'=>'USD','exchange_rate'=>null,'lines'=>[['order_id'=>8,'id'=>12]+$line],'options'=>[],
 'quantity_bags'=>60,'delivered_bags'=>0,'source_bags'=>0,'office_bags'=>0,'transit_bags'=>20,'remaining_bags'=>40,'to_deliver'=>60,'ready_bags'=>0,'amount_usd'=>2100];
$deliveryRow=['id'=>3,'delivery_number'=>'BL-2026-0003','document_number'=>'BL/DEP-DEC/2026/000003','order_id'=>7,'order_number'=>'BC-2026-0007','order_status'=>'validated','order_close_reason'=>null,'customer_code'=>'CLI-0001','customer_name'=>'Grossiste <i>Matadi</i>','site_id'=>3,'site_name'=>'Dépôt DECO','source_stage'=>'office','delivery_mode'=>'company_truck','vehicle_reference'=>'CAM-01','driver_name'=>'Paul','receiver_name'=>'<img src=x>','receiver_phone'=>null,'delivered_at'=>$today.' 09:30:00','notes'=>null,'status'=>'validated','currency'=>'CDF','exchange_rate'=>2800,'author'=>'Agent','created_at'=>$today.' 09:31:00','cancelled_at'=>null,'canceller_name'=>null,'cancel_reason'=>null,'bags'=>20,'kg'=>1000,'amount_usd'=>700,'lines'=>[['product_name'=>'Farine','format_name'=>'Sac 50 kg','bags'=>20,'kg'=>1000]]];
$blocked=['id'=>9,'order_number'=>'BC-2026-0009','customer_code'=>'CLI-0003','customer_name'=>'Client bloqué','customer_status'=>'blocked','blocked_reason'=>'Impayés <b>2026</b>']+$order;
$delivery=['ready'=>true,'orders'=>[$order,$waiting,$blocked],'deliveries'=>[$deliveryRow,['id'=>2,'status'=>'cancelled','document_number'=>null,'delivered_at'=>$today.' 08:00:00']+$deliveryRow],'canDeliver'=>true,'canCancel'=>true];
ob_start();require __DIR__.'/../app/views/distributions/index.php';$html=ob_get_clean();
foreach(['<script>unsafe</script>','<i>Matadi</i>','<img src=x>','<b>gras</b>'] as $unsafe)if(strpos($html,$unsafe)!==false)$fail('Texte non échappé : '.$unsafe);
if(strpos($html,'Warning:')!==false||strpos($html,'Notice:')!==false)$fail('Avertissement PHP dans le rendu');
// Today: 3 validated exits (6 bags, 150 kg) and one delivery (20 bags, 1 000 kg); the cancelled ones are left out.
if(!preg_match('/Sorti aujourd’hui<\/span><strong class="ui-kpi__value">26 <small>/u',$html)||strpos($html,'4 bon(s) · 1 150 kg')===false)$fail('Totaux du jour faux ou annulations comptées');
if(!preg_match('/Prêt à livrer<\/span><strong class="ui-kpi__value">25 </u',$html))$fail('Sacs prêts à livrer faux');
if(substr_count($html,'data-type="delivery"')!==2||substr_count($html,'data-type="direct"')!==6)$fail('Historique unique BL et BS incomplet');
if(strpos($html,'id="deliver-7"')===false||strpos($html,'id="deliver-8"')!==false)$fail('Seule la commande prête a un formulaire de livraison');
if(strpos($html,'value="office-3" required checked')===false||strpos($html,'name="bags[11]"')===false)$fail('Provenance ou lignes de livraison absentes');
if(!preg_match('/name="bags\[11\]" min="0" max="15" step="1" value="15"/',$html)||strpos($html,'Prêts à Dépôt DECO</span> <strong data-line-ready>15</strong>')===false||strpos($html,'Total à livrer : 15 sac(s) · 750 kg · 525,00 USD')===false)$fail('Quantités proposées absentes du rendu');
if(strpos($html,'id="deliver-9"')!==false||strpos($html,'Livraison suspendue : Impayés &lt;b&gt;2026&lt;/b&gt;.')===false)$fail('Client bloqué : livraison proposée ou motif absent');
if(strpos($html,'20 en route vers le bureau · 40 à produire')===false)$fail('Commande en attente sans explication');
if(strpos($html,'distributions/deliveries/3/print')===false||strpos($html,'id="delivery-cancel-3"')===false||strpos($html,'id="delivery-cancel-2"')!==false)$fail('Impression ou annulation des livraisons incorrecte');
if(strpos($html,'Voir le bon de livraison BL/DEP-DEC/2026/000003')===false||strpos($html,'id="note-preview"')===false||strpos($html,'print?export=pdf&amp;preview=1')===false)$fail('Bandeau après livraison ou aperçu du bon absent');
if(strpos($html,'name="customer_id"')===false||strpos($html,'<optgroup label="Minoterie">')===false)$fail('Sortie sans commande : client ou lots absents');
file_put_contents(sys_get_temp_dir().'/dagril-distributions.html',$html);echo "OK : commandes à livrer, historique BL et BS, totaux du jour hors annulations, échappement.\n";
