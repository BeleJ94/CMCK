<?php
$root=dirname(__DIR__);$service=file_get_contents($root.'/app/services/KpiService.php');$router=file_get_contents($root.'/public/index.php');$alerts=file_get_contents($root.'/app/models/Alert.php');$fail=[];
$authorization=file_get_contents($root.'/app/services/AuthorizationService.php');
function an_ok($ok,$label){global$fail;echo($ok?'OK  - ':'FAIL - ').$label."\n";if(!$ok)$fail[]=$label;}
$required=['agricultural_yield','roof_yield','production_variance','mortality_rate','gmq','fish_yield','available_stock','transit_stock','stock_days','breakage_rate','waste_available','pellet_yield','cost_price','budget_consumption','fuel_consumption','pending_movements','open_nonconformities'];foreach($required as$code)an_ok(strpos($service,"'".$code."'")!==false,'KPI disponible: '.$code);
an_ok(strpos($service,"h.status='validated'")!==false&&strpos($service,"b.status='validated'")!==false&&strpos($service,"o.status='validated'")!==false,'Agriculture, ROOF et pelletisation limités aux validations');
an_ok(strpos($service,"f.status='validated'")!==false&&strpos($service,"e.status='approved'")!==false,'Pisciculture et dépenses limitées aux validations');
an_ok(strpos($service,"Auth::can('analytics','read'")!==false&&strpos($service,'Auth::can($component,\'read\'')!==false,'Double contrôle RBAC analytics et module');
an_ok(strpos($service,"Auth::canViewConsolidated()")!==false&&strpos($service,"Auth::canAccessSite")!==false,'Consolidation et accès site contrôlés côté serveur');
an_ok(strpos($router,"/analytics/{code}")!==false,'Rapport explicatif routé pour chaque KPI');
an_ok(strpos($alerts,"production_batches.status = 'validated'")!==false,'Alertes de rendement calculées sur production validée');
an_ok(strpos($alerts,'generateOperationalAlerts')!==false,'Alertes carburant, NC et mouvements en attente activées');
an_ok(strpos($authorization,"['reports','read']")!==false&&strpos($authorization,"component='analytics' AND action='read'")!==false,'Compatibilité reports.read limitée à une migration 028 absente');
an_ok(strpos($service,"if(\$this->siteId!==null)\$q['site']=\$this->siteId;\$rows=array_merge(\$rows,\$this->rows(\"SELECT n.reference")!==false,'Le KPI des non-conformités transmet le paramètre de site à PDO');
foreach(explode("\n",$service)as$line){if(strpos($line,"case'")===false)continue;preg_match_all('/:(start|end|site|period_start|period_end)\\b/',$line,$matches);foreach(array_count_values($matches[1])as$name=>$count)an_ok($count===1,'Paramètre PDO unique : '.$name.' dans la formule KPI');}
try{require_once $root.'/app/helpers/functions.php';require_once $root.'/app/core/Database.php';$db=Database::getInstance()->connection();$columns=(int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='alerts' AND column_name IN('entity_type','entity_id','detail_path','resolved_at','resolved_by')")->fetchColumn();an_ok($columns===5,'Migration 028 appliquée');}catch(Exception$e){echo'SKIP- Intégration MariaDB indisponible: '.$e->getMessage()."\n";}
if($fail)exit(1);echo"\nPilotage analytique conforme aux invariants statiques.\n";
