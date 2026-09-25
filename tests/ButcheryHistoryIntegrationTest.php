<?php
if (!preg_match('/_(test|testing)$/', getenv('DAGRIL_DB_DATABASE') ?: '')) exit("Test database required\n");
require __DIR__.'/../app/helpers/functions.php';
require __DIR__.'/../app/core/Database.php';
require __DIR__.'/../app/core/Model.php';
require __DIR__.'/../app/core/Auth.php';
require __DIR__.'/../app/models/Butchery.php';
Auth::start();
$db = Database::getInstance()->connection();
$user = $db->query("SELECT u.*,r.slug role_slug,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='administrateur' AND u.status='active' AND u.deleted_at IS NULL LIMIT 1")->fetch();
Auth::login($user); Auth::selectSite('all');
$model = new Butchery();
$site = (int)$db->query("SELECT id FROM sites WHERE code='BOUCH'")->fetchColumn();
$other = (int)$db->query("SELECT id FROM sites WHERE code<>'BOUCH' LIMIT 1")->fetchColumn();
$item = (int)$db->query("SELECT id FROM butchery_items WHERE item_kind='raw_material' LIMIT 1")->fetchColumn();
$prefix = 'HISTORY-'.bin2hex(random_bytes(4));
function historyAssert($condition,$message) { if (!$condition) throw new RuntimeException($message); }
function historyInsert($db,$sql,$params) { $q=$db->prepare($sql);$q->execute($params);return (int)$db->lastInsertId(); }
$db->beginTransaction();
try {
    $ids=[];
    for ($i=0;$i<13;$i++) {
        $ids[]=historyInsert($db,"INSERT INTO butchery_receipts(site_id,source_type,receipt_number,source_reference,received_unit,received_weight_kg,status,received_at,created_by) VALUES(?,'external_bra',?,?,'kg',25,?,?,?)",[$i===12?$other:$site,$prefix.'-'.$i,$prefix.'-SOURCE-'.$i,['sanitary_pending','accepted','rejected','cancelled'][$i%4],sprintf('2026-09-%02d 10:00:00',$i+1),$user['id']]);
    }
    historyInsert($db,"INSERT INTO butchery_sanitary_controls(receipt_id,temperature_c,visual_status,document_status,decision,notes,controlled_at,controlled_by) VALUES(?,0,'conform','conform','accept',?,NOW(),?)",[$ids[1],'<script>unsafe</script>',$user['id']]);
    $live=historyInsert($db,"INSERT INTO butchery_live_lots(site_id,receipt_id,lot_number,available_heads,status,received_at) VALUES(?,?,?,10,'available',NOW())",[$site,$ids[1],$prefix.'-LIVE']);
    $slaughterIds=[];
    foreach (['submitted','validated','cancelled'] as $i=>$status) {
        $slaughterIds[]=historyInsert($db,"INSERT INTO butchery_slaughters(live_lot_id,raw_item_id,slaughter_number,input_heads,gross_weight_kg,tare_weight_kg,net_weight_kg,status,slaughtered_at,created_by) VALUES(?,?,?,2,50,5,45,?,NOW(),?)",[$live,$item,$prefix.'-ABAT-'.$i,$status,$user['id']]);
    }
    $receipts=$model->receiptHistory();
    $rows=array_values(array_filter($receipts,static function($r)use($ids){return in_array($r['id'],$ids);}));
    historyAssert(count($rows)===12,'Historique limité au site BOUCH, tous statuts');
    historyAssert((int)$rows[0]['id']===$ids[11],'Tri du plus récent au plus ancien');
    $controlled=array_values(array_filter($rows,static function($r)use($ids){return (int)$r['id']===$ids[1];}))[0];
    historyAssert((float)$controlled['temperature_c']===0.0 && $controlled['inspector_name']===$user['name'],'Contrôle sanitaire et température zéro conservés');
    $slaughters=array_values(array_filter($model->slaughterHistory(),static function($r)use($slaughterIds){return in_array($r['id'],$slaughterIds);}));
    historyAssert(count($slaughters)===3,'Abattages en attente, validés et annulés conservés');
    foreach (['receipts'=>$rows,'slaughters'=>$slaughters] as $section=>$history) {
        $butcheryModals=[];ob_start();require __DIR__.'/../app/views/butchery/history.php';$html=ob_get_clean().implode('',$butcheryModals);
        historyAssert(strpos($html,'data-workspace-modal-open')!==false,'Détails consultables');
        historyAssert(strpos($html,'<script>unsafe</script>')===false,'Texte échappé');
        file_put_contents(sys_get_temp_dir().'/dagril-history-'.$section.'.html',$html);
    }
    $section='slaughters';$success=null;$error=null;$siteRequired=false;
    extract($model->dashboard());$receipts=$model->pendingReceipts();$slaughters=$model->pendingSlaughters();$sales=[];$history=$model->slaughterHistory();
    ob_start();require __DIR__.'/../app/views/butchery/index.php';$workspace=ob_get_clean();
    file_put_contents(sys_get_temp_dir().'/dagril-slaughter-workspace.html',$workspace);
    $db->rollBack();
    echo "OK : périmètre site, tous statuts, ordre chronologique, contrôles sanitaires, détails échappés. Fixtures annulées.\n";
} catch (Throwable $e) { if($db->inTransaction())$db->rollBack();throw $e; }
