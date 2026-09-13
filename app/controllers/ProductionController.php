<?php
class ProductionController extends Controller
{
 public function index(){
  $model=$this->model('ProductionBatch');$batches=$model->allDetailed();$tolerances=[];$lines=$model->directoryWasteLines();foreach($batches as&$batch){$batch['waste_lines']=$lines[$batch['id']]??[];$tolerances[$batch['site_id']]=$tolerances[$batch['site_id']]??$model->tolerance($batch['site_id']);$batch['tolerance']=$tolerances[$batch['site_id']];$batch['version']=ProductionBatch::version($batch);}unset($batch);
  $this->view('production.index',['title'=>'Production farine','batches'=>$batches,'wasteTypes'=>$model->wasteTypes(),'tolerance'=>$model->tolerance(),'openBatch'=>$_GET['batch']??'','openDetail'=>$_GET['detail']??'','success'=>flash('success'),'error'=>flash('error')],'layouts.main');
 }
 public function export(){
  $format=$_GET['format']??'';if(!in_array($format,['excel','pdf'],true)){http_response_code(400);echo 'Format invalide.';return;}
  require_once dirname(__DIR__).'/services/ProductionExportService.php';
  $f=[];foreach(['search','state','machine','silo','from','to','date-kind'] as$key)$f[$key]=is_string($_GET[$key]??null)?$_GET[$key]:'';
  $service=new ProductionExportService();$all=$this->model('ProductionBatch')->allDetailed();$batches=$service->filtered($all,$f);
  $machine='Toutes';$silo='Tous';foreach($all as$b){if($f['machine']===$b['machine_code'])$machine=$b['machine_name'];if($f['silo']===(string)$b['silo_id'])$silo=$b['silo_name'];}
  $states=['todo'=>'À produire']+ProductionExportService::STATES;
  $summary='Recherche : '.($f['search']?:'Toutes').' · Machine : '.$machine.' · Silo : '.$silo.' · Étape : '.($states[$f['state']]??'Toutes').' · '.($f['date-kind']==='end'?'Fin de production':'Début du lot').' : '.($f['from']?:'Sans début').' au '.($f['to']?:'Sans fin');
  $rows=$service->rows($batches);$totals=$service->totals($batches);$filename='dagril-production-'.date('Ymd-His');header('Cache-Control: private, no-store');
  if($format==='excel'){
   require_once dirname(__DIR__).'/services/SpreadsheetExportService.php';
   $sheet=[['DAGRIL · Production farine'],['Exporté le '.date('d/m/Y H:i').' · Périmètre des sites autorisés'],[$summary],[], $service->headers()];
   $sheet=array_merge($sheet,$rows);$sheet[]=['Synthèse : '.$totals['lots'].' lots, dont '.$totals['validated'].' validés · Chargé : '.$totals['loaded'].' kg · Farine validée : '.$totals['flour'].' kg · Déchets validés : '.$totals['waste'].' kg'];
   $content=(new SpreadsheetExportService())->build($sheet,[21,21,38,30,22,24,18,18,18,18,25]);header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$filename.'.xlsx"');echo $content;return;
  }
  $html=$this->renderViewToString('production.export',['rows'=>$rows,'headers'=>$service->headers(),'summary'=>$summary,'totals'=>$totals]);(new PdfService())->stream('Production farine',$html,$filename.'.pdf','landscape');
 }
 public function create(){$_GET['batch']=$_GET['batch']??'first';$this->index();}
 public function show($id){$_GET['detail']=$id;$this->index();}
 public function store(){ $this->run(function(){
  $m=$this->model('ProductionBatch');$id=$_POST['production_batch_id']??'';$b=$m->findDetailed($id);if(!$b)throw new RuntimeException('Lot introuvable sur votre périmètre.');Auth::requirePermission('production','update',$b['site_id']);
  if(empty($_POST['version']))throw new RuntimeException('Actualisez la liste avant de saisir les résultats.');
  $m->submitResults(['production_batch_id'=>$id,'version'=>$_POST['version'],'output_quantity_kg'=>$_POST['output_quantity_kg']??'','waste_lines'=>is_array($_POST['waste_lines']??null)?$_POST['waste_lines']:[],'variance_tolerance_percent'=>$m->tolerance($b['site_id']),'variance_justification'=>trim($_POST['variance_justification']??''),'correction_reason'=>trim($_POST['correction_reason']??''),'ended_at'=>$_POST['ended_at']??''],Auth::user());
 },'Résultats enregistrés. Le lot attend sa validation ; aucun stock de farine ou de déchets n’a encore été créé.');}
 public function validateBatch($id){$this->run(function()use($id){if(empty($_POST['version']))throw new RuntimeException('Actualisez la liste avant de valider.');$this->model('ProductionBatch')->validateProduction(['production_batch_id'=>$id,'version'=>$_POST['version']],Auth::user());},'Production validée. La farine en vrac et les déchets sont disponibles.');}
 public function updateTolerance(){ $this->run(function(){Auth::requirePermission('production','administer');$this->model('ProductionBatch')->updateTolerance($_POST['tolerance_percent']??'',Auth::user());},'Tolérance mise à jour.'); }
 private function run(callable $action,$message){
  $ajax=strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest';
  if(!verify_csrf($_POST['_token']??'')){if($ajax)return $this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page.'],419);flash('error','Session expirée.');redirect('production');}
  try{$action();if($ajax)return $this->json(['ok'=>true,'message'=>$message,'refresh_url'=>base_url('production')]);flash('success',$message);}
  catch(Exception $e){$message=$e instanceof PDOException?'Enregistrement impossible. Vérifiez les données ou contactez l’administrateur.':$e->getMessage();if($ajax)return $this->json(['ok'=>false,'message'=>$message],422);flash('error',$message);}redirect('production');
 }
}
