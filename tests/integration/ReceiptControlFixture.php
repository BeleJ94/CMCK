<?php
if(!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D',getenv('DAGRIL_DB_DATABASE')?:''))throw new RuntimeException('Base jetable porcine obligatoire.');
require_once __DIR__.'/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../../app/services/PorcineFlowService.php';

function receiptFixtureQuery(string $sql,array $params=[]){global $db;$query=$db->prepare($sql);$query->execute($params);return $query;}

try{
    if(($argv[1]??'')==='verify'){
        $id=$argv[2]??'';
        if(!ctype_digit($id)||(int)$id<1)throw new RuntimeException('Identifiant de réception requis.');
        $receipt=receiptFixtureQuery("SELECT r.*,t.status transfer_status,t.quantity_kg,t.batch_id,t.transfer_number FROM butchery_receipts r JOIN livestock_transfers t ON t.id=r.livestock_transfer_id WHERE r.id=? AND t.truck_plate LIKE 'RECEIPT-UI-%'",[$id])->fetch();
        if(!$receipt)throw new RuntimeException('Réception de test inconnue.');
        $control=receiptFixtureQuery('SELECT * FROM butchery_sanitary_controls WHERE receipt_id=?',[$id])->fetchAll();
        $raw=receiptFixtureQuery('SELECT * FROM butchery_raw_lots WHERE receipt_id=?',[$id])->fetchAll();
        if($receipt['status']!=='accepted'||$receipt['transfer_status']!=='received'||count($control)!==1||count($raw)!==1)throw new RuntimeException('Réception ou traçabilité incomplète.');
        if($control[0]['temperature_c']===null||(float)$control[0]['temperature_c']!==0.0||$control[0]['decision']!=='accept'||$control[0]['visual_status']!=='conform'||$control[0]['document_status']!=='conform')throw new RuntimeException('Les deux contrôles et la température zéro doivent être enregistrés.');
        if((float)$raw[0]['quantity_kg']!==2.0||(int)$raw[0]['livestock_batch_id']!==(int)$receipt['batch_id']||$raw[0]['status']!=='available')throw new RuntimeException('Entrée au froid de 2 kg non traçable au lot d’origine.');
        echo json_encode(['receiptId'=>(int)$id,'status'=>$receipt['status'],'transferStatus'=>$receipt['transfer_status'],'temperature'=>0,'rawId'=>(int)$raw[0]['id'],'quantity'=>2]);
    }else{
        $path=getenv('DAGRIL_FLOW_FIXTURE');
        if(!$path||!is_file($path))throw new RuntimeException('Fixture du parcours porcin requise.');
        $fixture=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        if(($fixture['database']??'')!==getenv('DAGRIL_DB_DATABASE'))throw new RuntimeException('La fixture doit appartenir à la base jetable courante.');
        $user=DagrilIntegrationTest::loginRole('administrateur');
        Auth::selectSite((string)$fixture['farm']);
        $stock=receiptFixtureQuery("SELECT w.id FROM livestock_weight_stocks w JOIN pig_slaughter_details d ON d.conversion_id=w.conversion_id WHERE w.site_id=? AND w.batch_id=? AND w.status IN ('available','reserved') AND w.quantity_kg-w.reserved_kg>=2 AND d.expiry_date>=CURRENT_DATE ORDER BY w.id LIMIT 1",[$fixture['farm'],$fixture['adult']])->fetchColumn();
        if(!$stock)throw new RuntimeException('Stock de carcasses de la fixture insuffisant.');
        $flow=new PorcineFlowService($db);
        $key=static fn($data=[])=>array_merge(['operation_key'=>bin2hex(random_bytes(16))],$data);
        $transfer=$flow->shipment($key(['weight_stock_id'=>(string)$stock,'quantity_kg'=>'2','truck_plate'=>'RECEIPT-UI-'.bin2hex(random_bytes(4)),'driver_name'=>'Contrôle réception navigateur']),$user);
        $flow->advanceShipment($transfer,'approve',$user);
        $flow->advanceShipment($transfer,'dispatch',$user);
        Auth::selectSite((string)$fixture['butchery']);
        $receipt=$flow->arrival($key(['transfer_id'=>(string)$transfer,'weighing_mode'=>'net','net_kg'=>'2']),$user);
        $record=receiptFixtureQuery('SELECT r.receipt_number,t.transfer_number,t.quantity_kg,b.batch_number,s.name source_name FROM butchery_receipts r JOIN livestock_transfers t ON t.id=r.livestock_transfer_id JOIN livestock_batches b ON b.id=t.batch_id JOIN sites s ON s.id=t.source_site_id WHERE r.id=?',[$receipt])->fetch();
        echo json_encode(['receiptId'=>$receipt,'receiptNumber'=>$record['receipt_number'],'transferId'=>$transfer,'transferReference'=>$record['transfer_number'],'source'=>$record['source_name'],'batchNumber'=>$record['batch_number'],'quantity'=>(float)$record['quantity_kg']],JSON_UNESCAPED_UNICODE);
    }
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
if(ob_get_level())ob_end_flush();
