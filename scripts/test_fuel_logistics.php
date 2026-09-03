<?php
/* Contrôle exécutable sans données de production. Les tests d'intégration s'activent
   automatiquement dès que MariaDB et la migration 027 sont disponibles. */
$root=dirname(__DIR__);$fail=[];
function fl_ok($condition,$label){global$fail;echo($condition?'OK  - ':'FAIL - ').$label."\n";if(!$condition)$fail[]=$label;}
$model=file_get_contents($root.'/app/models/FuelLogistics.php');$migration=file_get_contents($root.'/database/027_add_fuel_logistics.sql');$routes=file_get_contents($root.'/public/index.php');
fl_ok(strpos($model,'SELECT * FROM fuel_stocks WHERE site_id=? AND fuel_type_id=? FOR UPDATE')!==false,'Verrouillage pessimiste du stock carburant');
fl_ok(strpos($model,"effect_key VARCHAR")===false&&strpos($migration,'effect_key VARCHAR(160) NOT NULL UNIQUE')!==false,'Clé d’effet unique dans le journal de stock');
fl_ok(strpos($model,"Le créateur ne peut pas valider sa commande")!==false&&strpos($model,"Le créateur ne peut pas valider sa mission")!==false,'Séparation créateur/validateur');
fl_ok(strpos($model,'meter_after')!==false&&strpos($model,'current_meter')!==false&&strpos($model,'chronologiquement incohérentes')!==false,'Contrôle chronologique des compteurs');
fl_ok(strpos($migration,'UNIQUE KEY uq_fl_proof(advance_id,proof_reference)')!==false,'Anti-double justification d’une avance');
fl_ok(strpos($model,'Stock de carburant disponible insuffisant')!==false&&strpos($model,'physical_quantity')!==false,'Blocage du stock disponible négatif');
fl_ok(strpos($routes,"['fuel-logistics','validate']")!==false&&strpos($routes,"['fuel-logistics','update']")!==false,'RBAC serveur sur validation et exécution');
fl_ok(strpos($model,"SUM(d.quantity) actual")!==false&&strpos($model,"SUM(COALESCE(d.normative_quantity,0)) normative")!==false,'KPIs réel contre normatif');
try{require_once $root.'/app/helpers/functions.php';require_once $root.'/app/core/Database.php';$db=Database::getInstance()->connection();$tables=(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN('fuel_stocks','fuel_stock_movements','fuel_distributions','logistics_missions','mission_advances','mission_justifications')")->fetchColumn();fl_ok($tables===6,'Migration 027 appliquée: tables critiques présentes');if($tables===6){$checks=$db->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND index_name IN('uq_fl_proof','uq_fl_stock','idx_fl_dist_asset')")->fetchColumn();fl_ok((int)$checks>=3,'Index d’unicité et de performance présents');}}
catch(Exception$e){echo"SKIP- Intégration MariaDB indisponible: ".$e->getMessage()."\n";}
if($fail)exit(1);echo"\nSocle Carburant & Logistique conforme aux invariants statiques.\n";
