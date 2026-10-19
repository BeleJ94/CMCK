<?php
require_once __DIR__.'/integration/TestBootstrap.php';
$db = DagrilIntegrationTest::boot();
require_once __DIR__.'/../app/services/PigService.php';
if (in_array('--migrate', $argv ?? [], true)) {
    $exists = (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='livestock_pig_profiles'")->fetchColumn();
    if (!$exists) $db->exec(file_get_contents(__DIR__.'/../database/036_add_pig_lifecycle.sql'));
}
$service = new PigService($db);
if (!$service->available()) throw new RuntimeException('Appliquez la migration 036 sur cette base de test.');
$user = DagrilIntegrationTest::loginRole('administrateur');
$site = (int)DagrilIntegrationTest::scalar("SELECT id FROM sites WHERE code='FARM-MUT'");
Auth::selectSite((string)$site);
$db->prepare("INSERT INTO livestock_facilities(site_id,code,name,facility_type,capacity_heads,status) VALUES(?,?,?,'housing',100,'active')")->execute([$site,'PIG-TEST-'.bin2hex(random_bytes(4)),'Test porcherie']);
$facility = (int)$db->lastInsertId();
$ids = [];
function pigRow($id) { global $service; foreach($service->directory() as $r) if((int)$r['id']===(int)$id) return $r; throw new RuntimeException('Lot introuvable.'); }
try {
    $data = ['site_id'=>$site,'facility_id'=>$facility,'sex'=>'female','born_on'=>date('Y-m-d',strtotime('-4 months')),'heads'=>'12','submission_key'=>bin2hex(random_bytes(16))];
    $id = $service->register($data,$user); $ids[]=$id;
    DagrilIntegrationTest::check($service->register($data,$user)===$id,'MySQL : soumission répétée sans doublon');
    $selected = $service->orient($id,['version'=>pigRow($id)['version'],'heads'=>'4','purpose'=>'breeding','reason'=>str_repeat('a',500)],$user); $ids[]=$selected;
    DagrilIntegrationTest::check((int)pigRow($id)['current_heads']===8&&(int)pigRow($selected)['current_heads']===4,'MySQL : sélection partielle conserve 12 animaux');
    $birth = $service->register(array_replace($data,['origin'=>'birth','mother_batch_id'=>$selected,'sex'=>'male','born_on'=>date('Y-m-d'),'heads'=>'3','submission_key'=>bin2hex(random_bytes(16))]),$user); $ids[]=$birth;
    DagrilIntegrationTest::check(pigRow($birth)['confirmed_group']==='A'&&(int)pigRow($selected)['current_heads']===4,'MySQL : naissances en A avec filiation et mères conservées');
    $db->prepare("UPDATE livestock_pig_profiles SET confirmed_group='A' WHERE batch_id=?")->execute([$id]);
    $r=pigRow($id);
    DagrilIntegrationTest::check($r['pending']&&$r['expected_group']==='C','MySQL : passage attendu calculé sans mutation');
    $service->confirmGroup($id,['version'=>$r['version'],'target_group'=>'C','field_checked'=>'1'],$user);
    DagrilIntegrationTest::check(!pigRow($id)['pending'],'MySQL : confirmation retire le passage en attente');
    $turned=$service->orient($selected,['version'=>pigRow($selected)['version'],'heads'=>'1','purpose'=>'fattening','reason'=>'Réforme'],$user);$ids[]=$turned;
    DagrilIntegrationTest::check((int)pigRow($selected)['current_heads']===3&&pigRow($turned)['purpose']==='fattening','MySQL : reproductrices réorientables vers abattage');
    $before=(int)DagrilIntegrationTest::scalar('SELECT SUM(current_heads) FROM livestock_batches WHERE facility_id=?',[$facility]);
    DagrilIntegrationTest::expectException(fn()=>$service->register(array_replace($data,['heads'=>'101','submission_key'=>bin2hex(random_bytes(16))]),$user),'MySQL : capacité insuffisante refuse l’entrée');
    DagrilIntegrationTest::check((int)DagrilIntegrationTest::scalar('SELECT SUM(current_heads) FROM livestock_batches WHERE facility_id=?',[$facility])===$before,'MySQL : refus atomique');
    require_once __DIR__.'/../app/models/Livestock.php';
    $livestock=new Livestock();
    $conversion=['batch_id'=>$birth,'conversion_type'=>'slaughter','input_heads'=>1,'gross_weight_kg'=>30,'tare_weight_kg'=>0,'converted_at'=>date('Y-m-d H:i:s')];
    DagrilIntegrationTest::expectException(fn()=>$livestock->createConversion($conversion,$user),'Route élevage existante : abattage avant C refusé');
    $service->assertExitAllowed($selected,'slaughter');
    DagrilIntegrationTest::check(true,'Reproductrices en C autorisées pour abattage');
    DagrilIntegrationTest::expectException(fn()=>$livestock->createTransfer(['site_id'=>$site,'batch_id'=>$selected,'source_unit'=>'head','quantity_heads'=>1],$user),'Route élevage existante : transfert de porcs vivants refusé');
    // Also render the real page to catch template errors and verify counts/forms.
    require_once __DIR__.'/../app/core/Controller.php';
    require_once __DIR__.'/../app/controllers/PigController.php';
    ob_start(); (new PigController())->index(); $html=ob_get_clean();
    DagrilIntegrationTest::check(strpos($html,'data-pig-directory')!==false&&strpos($html,'pig-register')!==false&&strpos($html,'Warning:')===false,'MySQL : page et formulaires rendus sans avertissement');
} finally {
    // Every test batch is owned by this unique facility, including a partially failed assertion.
    $ids=$db->query('SELECT id FROM livestock_batches WHERE facility_id='.$facility)->fetchAll(PDO::FETCH_COLUMN);
    foreach($ids as $id){
        $db->prepare("DELETE FROM activity_logs WHERE entity_type='livestock_batches' AND entity_id=? AND action LIKE 'pig_%'")->execute([$id]);
        $db->prepare('DELETE FROM livestock_head_movements WHERE batch_id=?')->execute([$id]);
        $db->prepare('DELETE FROM livestock_pig_events WHERE batch_id=?')->execute([$id]);
    }
    foreach($ids as $id) $db->prepare('DELETE FROM livestock_pig_profiles WHERE batch_id=?')->execute([$id]);
    foreach($ids as $id) $db->prepare('DELETE FROM livestock_batches WHERE id=?')->execute([$id]);
    $db->prepare('DELETE FROM livestock_facilities WHERE id=?')->execute([$facility]);
}
DagrilIntegrationTest::finish();
