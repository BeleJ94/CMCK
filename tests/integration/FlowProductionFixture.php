<?php
// Display fixtures only: no stock movements or business workflow are simulated here.
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base porcine jetable obligatoire.');
require_once __DIR__.'/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
$fixturePath=getenv('DAGRIL_FLOW_FIXTURE');
if(!$fixturePath||!is_file($fixturePath))throw new RuntimeException('DAGRIL_FLOW_FIXTURE doit désigner la fixture du parcours porcin.');
$fixture=json_decode(file_get_contents($fixturePath),true,512,JSON_THROW_ON_ERROR);
if(($fixture['database']??'')!==getenv('DAGRIL_DB_DATABASE'))throw new RuntimeException('La fixture ne correspond pas à la base jetable.');
$user=DagrilIntegrationTest::loginRole('administrateur');
$site=(int)$fixture['butchery'];
$recipe=$db->query("SELECT id FROM butchery_recipe_versions WHERE status='active' ORDER BY id LIMIT 1")->fetchColumn();
if(!$recipe)throw new RuntimeException('Une fiche de transformation active est requise.');
$prefix='DÉCOUPE-UI-'.strtoupper(bin2hex(random_bytes(4)));
$orders=[];
try{
    $db->beginTransaction();
    $insert=$db->prepare('INSERT INTO butchery_orders(site_id,recipe_version_id,order_number,planned_input_kg,actual_input_kg,output_kg,co_product_kg,loss_kg,yield_percent,production_date,status,created_by,flow_managed,validated_by,validated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $output=$db->prepare('INSERT INTO butchery_order_outputs(order_id,item_id,output_type,quantity_kg,expiry_date) VALUES(?,?,?,?,?)');
    $input=$db->prepare("INSERT INTO butchery_order_consumptions(order_id,raw_lot_id,item_id,consumption_type,quantity_kg,unit_cost) SELECT ?,id,item_id,'raw_material',?,unit_cost FROM butchery_raw_lots WHERE id=? AND site_id=?");
    for($i=1;$i<=25;$i++){
        $status=$i<=11?'results_submitted':($i<=23?'validated':($i===24?'in_progress':'cancelled'));
        $managed=$i===24?0:1;
        $quantity=[1=>9.5,2=>100,3=>12][$i]??($i*10+0.125);
        $main=round($quantity*0.75,3);$co=round($quantity*0.1,3);$obtained=round($main+$co,3);$loss=round($quantity-$obtained,3);
        $reference=$prefix.'-'.str_pad((string)$i,2,'0',STR_PAD_LEFT);
        $day=date('Y-m-d',strtotime('-'.$i.' days'));
        $insert->execute([$site,$recipe,$reference,$quantity,$quantity,$main,$co,$loss,round($obtained/$quantity*100,3),$day,$status,$user['id'],$managed,$status==='validated'?$user['id']:null,$status==='validated'?date('Y-m-d H:i:s'):null]);
        $id=(int)$db->lastInsertId();
        $output->execute([$id,$fixture['item1'],'finished_product',$main,$fixture['expiry']]);
        $output->execute([$id,$fixture['item2'],'co_product',$co,$fixture['expiry']]);
        $input->execute([$id,$quantity,$fixture['raw'],$site]);
        $orders[]=['id'=>$id,'reference'=>$reference,'status'=>$status,'managed'=>$managed,'date'=>$day,'input'=>$quantity,'output'=>$obtained,'loss'=>$loss];
    }
    $db->commit();
    $query=$db->prepare("SELECT SUM(status='results_submitted') pending,SUM(status='validated') validated,COALESCE(SUM(IF(status='validated',actual_input_kg,0)),0) input,COALESCE(SUM(IF(status='validated',output_kg+co_product_kg,0)),0) output FROM butchery_orders WHERE site_id=? AND deleted_at IS NULL");
    $query->execute([$site]);
    echo json_encode(['prefix'=>$prefix,'orders'=>$orders,'kpis'=>$query->fetch(PDO::FETCH_ASSOC)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $error){
    if($db->inTransaction())$db->rollBack();
    fwrite(STDERR,$error->getMessage().PHP_EOL);exit(1);
}
if(ob_get_level())ob_end_flush();
