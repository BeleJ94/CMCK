<?php
if(!preg_match('/_(test|testing)$/',getenv('DAGRIL_DB_DATABASE')?:''))exit('Test database required');
require __DIR__.'/../app/helpers/functions.php';require __DIR__.'/../app/core/Database.php';require __DIR__.'/../app/core/Model.php';require __DIR__.'/../app/core/Auth.php';require __DIR__.'/../app/models/Butchery.php';
Auth::start();$db=Database::getInstance()->connection();$u=$db->query("SELECT u.*,r.slug role_slug,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='administrateur' AND u.status='active' AND u.deleted_at IS NULL LIMIT 1")->fetch();Auth::login($u);$site=$db->query("SELECT id FROM sites WHERE code='BOUCH'")->fetchColumn();Auth::selectSite((string)$site);$m=new Butchery();
$db->beginTransaction();try{
$recipe=$db->query("SELECT v.id FROM butchery_recipe_versions v JOIN butchery_recipes r ON r.id=v.recipe_id WHERE r.code='DEC-PORC' AND v.status='active'")->fetchColumn();if(!$recipe)throw new RuntimeException('Exécuter setup_butchery_recipes sur la base de test.');
$ids=[];foreach(['MP-PORC','MP-BOEUF'] as $code){$item=$db->query("SELECT id FROM butchery_items WHERE code=".$db->quote($code))->fetchColumn();$q=$db->prepare("INSERT INTO butchery_raw_lots(site_id,item_id,lot_number,production_date,expiry_date,quantity_kg,reserved_kg,unit_cost,status) VALUES(?,?,?,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 2 DAY),100,0,5,'available')");$q->execute([$site,$item,'RECIPE-'.bin2hex(random_bytes(6))]);$ids[]=$db->lastInsertId();}
$data=['recipe_version_id'=>$recipe,'planned_input_kg'=>10,'production_date'=>date('Y-m-d'),'raw_lot_id'=>$ids[1]];
$before=$db->query('SELECT COUNT(*) FROM butchery_orders')->fetchColumn();
try{$m->createOrder($data,$u);throw new LogicException('Matière incorrecte acceptée');}catch(RuntimeException $e){if($e instanceof LogicException)throw $e;}
if($before!=$db->query('SELECT COUNT(*) FROM butchery_orders')->fetchColumn())throw new RuntimeException('Ordre incorrect créé');
$data['raw_lot_id']=$ids[0];$id=$m->createOrder($data,$u);if(!$id)throw new RuntimeException('Matière correcte refusée');
$products=$db->query("SELECT COUNT(*) FROM butchery_items WHERE code IN('MORCEAUX-PORC','MORCEAUX-BOEUF','MORCEAUX-VOLAILLE','POISSON-PREPARE') AND default_shelf_life_days IS NULL")->fetchColumn();if((int)$products!==4)throw new RuntimeException('Produits ou DLC par défaut incorrects');
$finishedItem=$db->query("SELECT id FROM butchery_items WHERE code='MORCEAUX-PORC'")->fetchColumn();
$q=$db->prepare("INSERT INTO butchery_order_outputs(order_id,item_id,output_type,quantity_kg,expiry_date) VALUES(?,?,'finished_product',50,DATE_ADD(CURDATE(),INTERVAL 2 DAY))");$q->execute([$id,$finishedItem]);$output=$db->lastInsertId();
$q=$db->prepare("INSERT INTO butchery_finished_lots(site_id,item_id,order_output_id,lot_number,production_date,expiry_date,quantity_kg,reserved_kg,unit_cost,status) VALUES(?,?,?, ?,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 2 DAY),50,0,5,'available')");$q->execute([$site,$finishedItem,$output,'TEST-FIN-'.bin2hex(random_bytes(4))]);$finishedId=$db->lastInsertId();
$dest=$db->query("SELECT id FROM sites WHERE code='DEP-DEC'")->fetchColumn();
$q=$db->prepare("INSERT INTO butchery_transfers(finished_lot_id,source_site_id,destination_site_id,transfer_number,quantity_kg,status,created_by) VALUES(?,?,?, ?,10,'draft',?)");$q->execute([$finishedId,$site,$dest,'TEST-BTR-'.bin2hex(random_bytes(4)),$u['id']]);
$q=$db->prepare("INSERT INTO butchery_sales(site_id,sale_number,raw_lot_id,customer_name,quantity_kg,unit_price,sold_at,status,created_by) VALUES(?,?,?,'Client test sorties',2,5,NOW(),'validated',?)");$q->execute([$site,'TEST-SALE-'.bin2hex(random_bytes(4)),$ids[0],$u['id']]);
$section='production';$success=null;$error=null;$siteRequired=false;$receipts=[];$slaughters=[];$sales=[];$history=[];
extract($m->dashboard());$productionLines=$m->productionLines();ob_start();require __DIR__.'/../app/views/butchery/index.php';$html=ob_get_clean();file_put_contents(sys_get_temp_dir().'/dagril-production-workspace.html',$html);
$section='stocks';extract($m->dashboard());ob_start();require __DIR__.'/../app/views/butchery/index.php';$html=ob_get_clean();file_put_contents(sys_get_temp_dir().'/dagril-stocks-workspace.html',$html);
$section='outgoing';extract($m->dashboard());$sales=$m->recentSales();ob_start();require __DIR__.'/../app/views/butchery/index.php';$html=ob_get_clean();file_put_contents(sys_get_temp_dir().'/dagril-outgoing-workspace.html',$html);
$db->rollBack();echo "OK : lot incompatible refusé sans ordre créé, lot compatible accepté, quatre produits sans DLC imposée.\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
