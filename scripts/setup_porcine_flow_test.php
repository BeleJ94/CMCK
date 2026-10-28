<?php
// Disposable MySQL schema. Copies reference data only from an existing test database.
$config=require __DIR__.'/../config/database.php';
$source=$config['database'];
if(!preg_match('/_(test|testing)$/i',$source))throw new RuntimeException('Source de test obligatoire.');
$db=new PDO('mysql:host='.$config['host'].';port='.$config['port'].';charset=utf8mb4',$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(($argv[1]??'')==='drop'){
    $name=$argv[2]??'';if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',$name))throw new RuntimeException('Nom de base temporaire invalide.');
    $db->exec('DROP DATABASE `'.$name.'`');echo "Base temporaire supprimée.\n";exit;
}
$name='dagril_porcin_'.bin2hex(random_bytes(6)).'_test';
$db->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try{
    $db->exec('USE `'.$name.'`');$db->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach($db->query('SHOW FULL TABLES FROM `'.$source.'` WHERE Table_type=\'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM) as $table){$table=$table[0];$ddl=$db->query('SHOW CREATE TABLE `'.$source.'`.`'.$table.'`')->fetch(PDO::FETCH_NUM)[1];$db->exec($ddl);}
    foreach(['sites','roles','users','user_sites','permissions','role_permissions','user_role_assignments','livestock_species','butchery_items','butchery_recipes','butchery_recipe_versions','butchery_recipe_components','document_types','document_numbering_rules','workflow_definitions','workflow_steps','site_types','products','bag_formats','empty_packaging_items'] as $table)$db->exec('INSERT INTO `'.$name.'`.`'.$table.'` SELECT * FROM `'.$source.'`.`'.$table.'`');
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
    if(!(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='livestock_pig_profiles'")->fetchColumn())$db->exec(file_get_contents(__DIR__.'/../database/036_add_pig_lifecycle.sql'));
    if(!(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='butchery_counters'")->fetchColumn())$db->exec(file_get_contents(__DIR__.'/../database/037_add_farm_carcasses_and_butchery_counters.sql'));
    if(!(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='butchery_pos_tickets'")->fetchColumn())$db->exec(file_get_contents(__DIR__.'/../database/040_add_butchery_pos.sql'));
    if(!(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='livestock_pig_groups'")->fetchColumn())$db->exec(file_get_contents(__DIR__.'/../database/041_add_pig_group_referential.sql'));
    if(!(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='livestock_pond_parcels'")->fetchColumn())$db->exec(file_get_contents(__DIR__.'/../database/042_add_fish_ponds_and_transit_weighing.sql'));
    if(!(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='cattle_slaughter_details'")->fetchColumn())$db->exec(file_get_contents(__DIR__.'/../database/043_add_cattle_flow.sql'));
    if(!(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='poultry_lot_profiles'")->fetchColumn())$db->exec(file_get_contents(__DIR__.'/../database/044_add_poultry_flow.sql'));
    $db->exec(file_get_contents(__DIR__.'/../database/045_add_missing_cutting_recipes.sql'));
    if(!(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='butchery_product_sources'")->fetchColumn())$db->exec(file_get_contents(__DIR__.'/../database/046_add_butchery_product_sources.sql'));
    if(!(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='customers'")->fetchColumn())$db->exec(file_get_contents(__DIR__.'/../database/047_add_customers_and_sales_settings.sql'));
    if(!(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='customer_orders'")->fetchColumn())$db->exec(file_get_contents(__DIR__.'/../database/048_add_customer_orders.sql'));
    if(!(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='customer_deliveries'")->fetchColumn())$db->exec(file_get_contents(__DIR__.'/../database/049_add_customer_deliveries.sql'));
    echo $name,PHP_EOL;
}catch(Throwable $e){$db->exec('DROP DATABASE `'.$name.'`');throw $e;}
