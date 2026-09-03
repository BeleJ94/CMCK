<?php
require_once dirname(__DIR__).'/tests/integration/TestBootstrap.php';
$db=DagrilIntegrationTest::boot();
require_once dirname(__DIR__).'/app/services/CancellationService.php';

$distribution=DagrilIntegrationTest::row("SELECT d.*,fs.total_weight_kg stock_before,fs.quantity_bags bags_before FROM distributions d JOIN finished_stocks fs ON fs.id=d.finished_stock_id WHERE d.status='validated' AND fs.deleted_at IS NULL ORDER BY d.id LIMIT 1");
if(!$distribution){fwrite(STDERR,"[ECHEC] Une distribution validée est requise dans les fixtures.\n");exit(1);}
$admin=DagrilIntegrationTest::loginRole('administrateur',$distribution['site_id']);
$service=new CancellationService($db);
$request=$service->request('distribution',$distribution['id'],'Correction intégration: distribution validée erronée',$admin);
DagrilIntegrationTest::check(DagrilIntegrationTest::scalar('SELECT status FROM cancellation_requests WHERE id=?',[$request])==='pending_approval','Annulation validée soumise à deuxième approbation');
DagrilIntegrationTest::check((float)DagrilIntegrationTest::scalar('SELECT total_weight_kg FROM finished_stocks WHERE id=?',[$distribution['finished_stock_id']])===(float)$distribution['stock_before'],'Aucun effet stock avant approbation');
$approver=DagrilIntegrationTest::loginRole('direction',$distribution['site_id']);
$service->approve($request,$approver);
$expected=(float)$distribution['stock_before']+(float)$distribution['total_weight_kg'];
DagrilIntegrationTest::check(DagrilIntegrationTest::scalar('SELECT status FROM distributions WHERE id=?',[$distribution['id']])==='cancelled','Statut original annulé sans suppression');
DagrilIntegrationTest::check(abs((float)DagrilIntegrationTest::scalar('SELECT total_weight_kg FROM finished_stocks WHERE id=?',[$distribution['finished_stock_id']])-$expected)<0.001,'Stock restauré par contre-opération');
DagrilIntegrationTest::check((int)DagrilIntegrationTest::scalar('SELECT COUNT(*) FROM cancellation_stock_effects e JOIN cancellation_reversals r ON r.id=e.reversal_id WHERE r.request_id=?',[$request])>0,'Effet de stock inverse journalisé');
DagrilIntegrationTest::check((int)DagrilIntegrationTest::scalar("SELECT COUNT(*) FROM stock_movements WHERE distribution_id=? AND movement_type='adjustment'",[$distribution['id']])>0,'Mouvement de correction créé');
DagrilIntegrationTest::check((int)DagrilIntegrationTest::scalar("SELECT COUNT(*) FROM activity_logs WHERE module='cancellations' AND entity_id=?",[$distribution['id']])>0,'Journal d’audit créé');
DagrilIntegrationTest::check((int)DagrilIntegrationTest::scalar("SELECT COUNT(*) FROM documents WHERE entity_type='distributions' AND entity_id=? AND status<>'cancelled'",[$distribution['id']])===0,'Documents associés annulés');
DagrilIntegrationTest::expectException(function()use($service,$distribution,$admin){$service->request('distribution',$distribution['id'],'Deuxième annulation interdite',$admin);},'Deuxième annulation refusée et idempotence préservée');
DagrilIntegrationTest::check((int)DagrilIntegrationTest::scalar('SELECT COUNT(*) FROM cancellation_reversals WHERE entity_type=? AND entity_id=?',['distribution',$distribution['id']])===1,'Une seule contre-opération existe');
DagrilIntegrationTest::finish();
