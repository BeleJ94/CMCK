<?php
require_once __DIR__.'/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
$name=(string)$db->query('SELECT DATABASE()')->fetchColumn();
DagrilIntegrationTest::check((bool)preg_match('/_(test|testing)$/i',$name),'La suite est isolée dans '.$name);

$before=(int)DagrilIntegrationTest::scalar('SELECT COUNT(*) FROM products');
try{$db->beginTransaction();$q=$db->prepare("INSERT INTO products(name,code,category,unit,status) VALUES('Rollback SQL','TST-ROLLBACK-SQL','finished_product','kg','active')");$q->execute();$db->prepare('INSERT INTO finished_stocks(site_id,product_id,bag_format_id,quantity_bags,total_weight_kg,status) VALUES(999999,?,999999,1,1,\'active\')')->execute([$db->lastInsertId()]);$db->commit();}catch(Exception$e){if($db->inTransaction())$db->rollBack();}
DagrilIntegrationTest::check((int)DagrilIntegrationTest::scalar('SELECT COUNT(*) FROM products')===$before,'Une erreur SQL annule toute la transaction');
DagrilIntegrationTest::check((int)DagrilIntegrationTest::scalar("SELECT COUNT(*) FROM products WHERE code='TST-ROLLBACK-SQL'")===0,'Aucune donnée partielle après rollback');

$unauthorized=DagrilIntegrationTest::row("SELECT u.*,r.name role_name,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='agent-pont-bascule' AND u.status='active' LIMIT 1");
$sites=$db->query("SELECT id FROM sites WHERE code IN('FARM-MUT','FARM-DIK') ORDER BY code")->fetchAll(PDO::FETCH_COLUMN);
if($unauthorized&&count($sites)===2){Auth::login($unauthorized);$_SESSION['current_site_id']=(int)$sites[0];DagrilIntegrationTest::expectException(function()use($sites){Auth::requireSiteAccess((int)$sites[1]);},'Accès à un site non autorisé refusé côté serveur');}
else DagrilIntegrationTest::check(false,'Fixtures utilisateur/sites pour le contrôle d’accès');
DagrilIntegrationTest::finish();
