<?php
require_once __DIR__.'/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once __DIR__.'/../../app/services/PigService.php';
$u=DagrilIntegrationTest::loginRole('administrateur');
$site=(int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='FARM-MUT'");
Auth::selectSite((string)$site);
if(($argv[1]??'')==='cleanup'){
    $facility=(int)($argv[2]??0);
    $safe=DagrilIntegrationTest::row("SELECT id FROM livestock_facilities WHERE id=? AND code LIKE 'PIG-BROWSER-%'",[$facility]);
    if(!$safe)throw new RuntimeException('Fixture inconnue.');
    $ids=$db->query('SELECT id FROM livestock_batches WHERE facility_id='.$facility)->fetchAll(PDO::FETCH_COLUMN);
    foreach($ids as $id){
        $db->prepare("DELETE FROM activity_logs WHERE entity_type='livestock_batches' AND entity_id=? AND action LIKE 'pig_%'")->execute([$id]);
        $db->prepare('DELETE FROM livestock_head_movements WHERE batch_id=?')->execute([$id]);
        $db->prepare('DELETE FROM livestock_pig_events WHERE batch_id=?')->execute([$id]);
    }
    foreach($ids as $id)$db->prepare('DELETE FROM livestock_pig_profiles WHERE batch_id=?')->execute([$id]);
    foreach($ids as $id)$db->prepare('DELETE FROM livestock_batches WHERE id=?')->execute([$id]);
    $db->prepare('DELETE FROM livestock_facilities WHERE id=?')->execute([$facility]);
    echo "Fixture nettoyée.\n";
}else{
    $s=new PigService($db);
    if(!$s->available())throw new RuntimeException('Migration 036 requise en base de test.');
    $code='PIG-BROWSER-'.bin2hex(random_bytes(4));
    $db->prepare("INSERT INTO livestock_facilities(site_id,code,name,facility_type,capacity_heads,status) VALUES(?,?,?,'housing',200,'active')")->execute([$site,$code,$code]);
    $facility=(int)$db->lastInsertId();
    $base=['site_id'=>$site,'facility_id'=>$facility,'sex'=>'female','born_on'=>date('Y-m-d',strtotime('-4 months')),'heads'=>'20','submission_key'=>bin2hex(random_bytes(16))];
    $adult=$s->register($base,$u);$row=null;foreach($s->directory() as $r)if((int)$r['id']===$adult)$row=$r;
    $breeder=$s->orient($adult,['version'=>$row['version'],'heads'=>'4','purpose'=>'breeding','reason'=>'Fixture navigateur'],$u);
    $young=$s->register(array_replace($base,['sex'=>'male','born_on'=>date('Y-m-d',strtotime('-2 months')),'heads'=>'5','submission_key'=>bin2hex(random_bytes(16))]),$u);
    $db->prepare("UPDATE livestock_pig_profiles SET confirmed_group='A' WHERE batch_id=?")->execute([$young]);
    echo json_encode(['site'=>$site,'facility'=>$facility,'facilityName'=>$code,'adult'=>$adult,'breeder'=>$breeder,'young'=>$young,'born_on'=>date('Y-m-d')]);
}
if(ob_get_level())ob_end_flush();
