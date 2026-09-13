<?php
require __DIR__.'/integration/TestBootstrap.php';$db=DagrilIntegrationTest::boot();$user=DagrilIntegrationTest::loginRole('administrateur','all');require dirname(__DIR__).'/app/models/MachineFeed.php';$m=new MachineFeed();$machine=$m->machinesForSelect()[0];$silo=array_values(array_filter($m->silosForSelect(),fn($s)=>(float)$s['current_stock_kg']>10))[0];
$data=['silo_id'=>$silo['id'],'machine_id'=>$machine['id'],'quantity_kg'=>'0.125','authorized_quantity_kg'=>'0.200','fed_at'=>date('Y-m-d\TH:i'),'ended_at'=>'','observation'=>'Test alimentation AJAX'];
$before=(float)DagrilIntegrationTest::scalar('SELECT current_stock_kg FROM silos WHERE id=?',[$silo['id']]);
$id=$m->createFeed($data,$user);$feed=$m->findDetailed($id);
DagrilIntegrationTest::check((int)$feed['site_id']===(int)$machine['site_id']&&Auth::currentSiteId()===null,'Création en vue consolidée sur le site de la machine, contexte conservé');
DagrilIntegrationTest::check(abs((float)DagrilIntegrationTest::scalar('SELECT current_stock_kg FROM silos WHERE id=?',[$silo['id']])-($before-.125))<.00001,'Déduction exacte de la quantité chargée, non de la quantité autorisée');
DagrilIntegrationTest::check($feed['batch_status']==='in_progress'&&$feed['bss_number'],'BSS émis et lot démarré atomiquement');
DagrilIntegrationTest::check((float)$feed['authorized_quantity_kg']===.2,'Quantité autorisée conservée');
$count=DagrilIntegrationTest::scalar('SELECT COUNT(*) FROM machine_feeds');$stock=DagrilIntegrationTest::scalar('SELECT current_stock_kg FROM silos WHERE id=?',[$silo['id']]);
foreach([['quantity_kg'=>$before+1],['quantity_kg'=>0],['quantity_kg'=>'abc'],['authorized_quantity_kg'=>INF],['fed_at'=>'2026-02-30T10:00'],['ended_at'=>'2000-01-01T00:00'],['machine_id'=>99999999],['silo_id'=>99999999]] as$patch)DagrilIntegrationTest::expectException(fn()=>$m->createFeed(array_replace($data,$patch),$user),'Refus des données invalides : '.implode(', ',array_keys($patch)));
$other=DagrilIntegrationTest::scalar('SELECT id FROM sites WHERE id<>? AND deleted_at IS NULL LIMIT 1',[$machine['site_id']]);
Auth::selectSite((string)$other);DagrilIntegrationTest::expectException(fn()=>$m->createFeed($data,$user),'Une machine hors du site courant est refusée');Auth::selectSite('all');
DagrilIntegrationTest::check($count===DagrilIntegrationTest::scalar('SELECT COUNT(*) FROM machine_feeds')&&$stock===DagrilIntegrationTest::scalar('SELECT current_stock_kg FROM silos WHERE id=?',[$silo['id']]),'Aucun mouvement ni alimentation après les refus');
DagrilIntegrationTest::finish();
