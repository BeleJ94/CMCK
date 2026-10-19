<?php
// Display-only stock fixtures. Never simulate ledger movements on a real database.
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base porcine jetable obligatoire.');
require_once __DIR__.'/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
$fixturePath=getenv('DAGRIL_FLOW_FIXTURE');
if(!$fixturePath||!is_file($fixturePath))throw new RuntimeException('Fixture du parcours porcin requise.');
$fixture=json_decode(file_get_contents($fixturePath),true,512,JSON_THROW_ON_ERROR);
if(($fixture['database']??'')!==getenv('DAGRIL_DB_DATABASE'))throw new RuntimeException('La fixture ne correspond pas à la base jetable.');
$user=DagrilIntegrationTest::loginRole('administrateur');
$site=(int)$fixture['butchery'];
$prefix='FROID-ÉTÉ-UI-'.strtoupper(bin2hex(random_bytes(4)));
$today=date('Y-m-d');$future=date('Y-m-d',strtotime('+5 days'));$yesterday=date('Y-m-d',strtotime('-1 day'));
$records=[];$excluded=[];
try{
    $db->beginTransaction();
    $rawItem=$db->prepare('SELECT item_id FROM butchery_raw_lots WHERE id=? AND site_id=?');$rawItem->execute([$fixture['raw'],$site]);$rawItemId=$rawItem->fetchColumn();
    $recipe=$db->query("SELECT id FROM butchery_recipe_versions WHERE status='active' ORDER BY id LIMIT 1")->fetchColumn();
    if(!$rawItemId||!$recipe)throw new RuntimeException('Matière et fiche de transformation de référence requises.');
    $rawInsert=$db->prepare('INSERT INTO butchery_raw_lots(site_id,item_id,livestock_batch_id,lot_number,production_date,expiry_date,quantity_kg,reserved_kg,unit_cost,status) VALUES(?,?,?,?,?,?,?,?,0,?)');
    for($i=1;$i<=14;$i++){
        $physical=[1=>9.5,2=>100,3=>12,14=>0][$i]??($i*10+0.125);
        $reserved=$i===2?30:($i===1?1:($i>7&&$i<14?($i%3):0));
        $status=[3=>'available',4=>'quarantine',5=>'rejected',6=>'cancelled',14=>'depleted'][$i]??($reserved>0?'reserved':'available');
        $expiry=$i===3?$yesterday:($i===7?$today:$future);
        $number=$prefix.'-MAT-'.str_pad((string)$i,2,'0',STR_PAD_LEFT);
        $rawInsert->execute([$site,$rawItemId,$fixture['adult'],$number,$today,$expiry,$physical,$reserved,$status]);
        $id=(int)$db->lastInsertId();$key='raw-'.$id;
        if($physical<=0){$excluded[]=$key;continue;}
        $usable=in_array($status,['available','reserved'],true)&&$expiry>=$today;
        $records[]=['key'=>$key,'id'=>$id,'kind'=>'raw','number'=>$number,'physical'=>$physical,'reserved'=>$reserved,'available'=>$usable?round($physical-$reserved,3):0,'date'=>$expiry,'state'=>$expiry<$today?'expired':$status];
    }
    $order=$db->prepare("INSERT INTO butchery_orders(site_id,recipe_version_id,order_number,planned_input_kg,actual_input_kg,output_kg,co_product_kg,loss_kg,yield_percent,production_date,status,created_by,flow_managed,validated_by,validated_at) VALUES(?,?,?,1000,1000,1000,0,0,100,?,'validated',?,1,?,CURRENT_TIMESTAMP)");
    $order->execute([$site,$recipe,$prefix.'-ORDRE',$today,$user['id'],$user['id']]);$orderId=(int)$db->lastInsertId();
    $output=$db->prepare("INSERT INTO butchery_order_outputs(order_id,item_id,output_type,quantity_kg,expiry_date) VALUES(?,?,'finished_product',?,?)");
    $finishedInsert=$db->prepare('INSERT INTO butchery_finished_lots(site_id,item_id,order_output_id,lot_number,production_date,expiry_date,quantity_kg,reserved_kg,unit_cost,status) VALUES(?,?,?,?,?,?,?,?,0,?)');
    $counterInsert=$db->prepare('INSERT INTO butchery_counter_stocks(counter_id,finished_lot_id,quantity_kg) VALUES(?,?,?)');
    for($i=1;$i<=13;$i++){
        $total=[1=>50,2=>12,3=>100,4=>40,13=>7][$i]??($i*10+0.5);
        $counter=[1=>20,2=>3,3=>40,4=>7,13=>7][$i]??($i+0.25);
        $reserved=[1=>5,2=>2,3=>0,4=>0,13=>0][$i]??($i%3);
        $status=$i===4?'cancelled':($reserved>0?'reserved':'available');$expiry=$i===3?$yesterday:$future;
        $number=$prefix.'-PROD-'.str_pad((string)$i,2,'0',STR_PAD_LEFT);
        $output->execute([$orderId,$fixture['item1'],$total,$expiry]);$outputId=(int)$db->lastInsertId();
        $finishedInsert->execute([$site,$fixture['item1'],$outputId,$number,$today,$expiry,$total,$reserved,$status]);
        $id=(int)$db->lastInsertId();$counterInsert->execute([$fixture['counter'],$id,$counter]);$key='finished-'.$id;
        $physical=round($total-$counter,3);
        if($physical<=0){$excluded[]=$key;continue;}
        $usable=in_array($status,['available','reserved'],true)&&$expiry>=$today;
        $records[]=['key'=>$key,'id'=>$id,'kind'=>'finished','number'=>$number,'physical'=>$physical,'reserved'=>$reserved,'available'=>$usable?round($physical-$reserved,3):0,'date'=>$expiry,'state'=>$expiry<$today?'expired':$status,'counter'=>$counter,'total'=>$total];
    }
    $db->commit();
    $all=$db->prepare("SELECT 'raw' kind,l.quantity_kg physical,l.reserved_kg reserved,l.quantity_kg-l.reserved_kg free,l.expiry_date,l.status FROM butchery_raw_lots l WHERE l.site_id=? AND l.deleted_at IS NULL AND l.quantity_kg>0 UNION ALL SELECT 'finished',l.quantity_kg-COALESCE(cs.quantity_kg,0),l.reserved_kg,l.quantity_kg-l.reserved_kg-COALESCE(cs.quantity_kg,0),l.expiry_date,l.status FROM butchery_finished_lots l LEFT JOIN(SELECT finished_lot_id,SUM(quantity_kg) quantity_kg FROM butchery_counter_stocks GROUP BY finished_lot_id) cs ON cs.finished_lot_id=l.id WHERE l.site_id=? AND l.deleted_at IS NULL AND l.quantity_kg-COALESCE(cs.quantity_kg,0)>0");
    $all->execute([$site,$site]);$kpis=['raw'=>0,'finished'=>0,'available'=>0,'expired'=>0];$kpiCounts=array_fill_keys(array_keys($kpis),0);
    foreach($all->fetchAll(PDO::FETCH_ASSOC)as$row){
        $kpis[$row['kind']]+=(float)$row['physical'];$kpiCounts[$row['kind']]++;
        if($row['expiry_date']<$today){$kpis['expired']++;$kpiCounts['expired']++;}
        if(in_array($row['status'],['available','reserved'],true)&&$row['expiry_date']>=$today&&(float)$row['free']>0){$kpis['available']+=(float)$row['free'];$kpiCounts['available']++;}
    }
    foreach($kpis as$key=>$value)$kpis[$key]=round($value,3);
    echo json_encode(['prefix'=>$prefix,'records'=>$records,'excluded'=>$excluded,'kpis'=>$kpis,'kpiCounts'=>$kpiCounts,'today'=>$today],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable$error){if($db->inTransaction())$db->rollBack();fwrite(STDERR,$error->getMessage().PHP_EOL);exit(1);}
if(ob_get_level())ob_end_flush();
