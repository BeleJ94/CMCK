<?php
class TransferController extends Controller
{
 public function index()
 {
  $model=$this->model('Transfer');
  $transfers=$model->allDetailed();
  $this->view('transfers.index',['title'=>'Transferts inter-sites','transfers'=>$transfers,'transferItems'=>$model->directoryItems($transfers),'references'=>Auth::can('transfers','create')?$model->references():[],'success'=>flash('success'),'error'=>flash('error')],'layouts.main');
 }
 public function create(){$this->view('transfers.create',array_merge(['title'=>'Nouvelle demande de transfert','errors'=>flash('errors')?:[]],$this->model('Transfer')->references()),'layouts.main');}
 public function store()
 {
  $ajax=($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest';
  try {
   $this->csrf();
   $id=$this->service()->create(['transfer_type'=>$_POST['transfer_type']??'inter_site','source_site_id'=>(int)($_POST['source_site_id']??0),'destination_site_id'=>(int)($_POST['destination_site_id']??0),'notes'=>trim($_POST['notes']??''),'items'=>[['finished_stock_id'=>(int)($_POST['finished_stock_id']??0),'quantity_bags'=>(int)($_POST['quantity_bags']??0)]]],Auth::user());
   flash('success','Demande créée. Vous pouvez maintenant la soumettre pour validation.');
   if($ajax){header('Content-Type: application/json');echo json_encode(['success'=>true,'ok'=>true,'redirect'=>base_url('transfers/'.$id),'redirect_url'=>base_url('transfers/'.$id)]);return;}
   redirect('transfers/'.$id);
  }catch(Exception $e){
   if($ajax){http_response_code(422);header('Content-Type: application/json');echo json_encode(['success'=>false,'message'=>$e->getMessage()]);return;}
   flash('error',$e->getMessage());redirect('transfers/create');
  }
 }
 public function show($id){$t=$this->model('Transfer')->findDetailed((int)$id);if(!$t){http_response_code(404);echo'Transfert introuvable.';return;}$this->view('transfers.show',['title'=>$t['transfer_number'],'transfer'=>$t,'success'=>flash('success'),'error'=>flash('error')],'layouts.main');}
 public function submit($id){$this->act($id,'submit');}public function approve($id){$this->act($id,'approve');}public function reserve($id){$this->act($id,'reserve');}public function close($id){$this->act($id,'close');}
 public function ship($id){$q=[];foreach($_POST['quantity_bags']??[]as$k=>$v){$q[(int)$k]=(int)$v;}try{$this->csrf();$this->service()->ship((int)$id,$q,Auth::user(),trim($_POST['vehicle_reference']??''));$this->operationResponse($id,'Expédition enregistrée.');}catch(Exception$e){$this->operationResponse($id,$e->getMessage(),false);}}
 public function receive($id){$lines=[];foreach($_POST['accepted_bags']??[]as$k=>$v){$lines[(int)$k]=['accepted_bags'=>(int)$v,'rejected_bags'=>(int)($_POST['rejected_bags'][$k]??0),'quality_notes'=>trim($_POST['quality_notes'][$k]??'')];}try{$this->csrf();$this->service()->receive((int)$_POST['shipment_id'],hash('sha256',trim($_POST['idempotency_key']??'')),$lines,Auth::user(),trim($_POST['notes']??''));$this->operationResponse($id,'Réception enregistrée.');}catch(Exception$e){$this->operationResponse($id,$e->getMessage(),false);}}
 public function dispatchReturn($id,$returnId){$this->csrf();try{$this->service()->dispatchReturn((int)$returnId,Auth::user());flash('success','Retour expédié.');}catch(Exception$e){flash('error',$e->getMessage());}redirect('transfers/'.$id);}public function receiveReturn($id,$returnId){$this->csrf();try{$this->service()->receiveReturn((int)$returnId,Auth::user());flash('success','Retour reçu au site source.');}catch(Exception$e){flash('error',$e->getMessage());}redirect('transfers/'.$id);}
 private function operationResponse($id,$message,$ok=true)
 {
  if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest'){
   if(!$ok)http_response_code(422);
   else flash('success',$message);
   header('Content-Type: application/json');echo json_encode(['success'=>$ok,'message'=>$message,'redirect'=>base_url('transfers/'.$id)]);return;
  }
  flash($ok?'success':'error',$message);redirect('transfers/'.$id);
 }
 private function act($id,$action){$this->csrf();try{$this->service()->{$action}((int)$id,Auth::user());flash('success','Action enregistrée.');}catch(Exception$e){flash('error',$e->getMessage());}redirect('transfers/'.$id);}private function service(){require_once dirname(__DIR__).'/services/TransferService.php';return new TransferService();}private function csrf(){if(!verify_csrf($_POST['_token']??'')){throw new RuntimeException('Jeton CSRF invalide.');}}
}
