<?php
class FuelLogisticsController extends Controller
{
 public function index(){$m=$this->model('FuelLogistics');$this->view('fuel_logistics.index',['title'=>'Carburant & Logistique','refs'=>$m->references(),'dashboard'=>$m->dashboard(),'success'=>flash('success'),'error'=>flash('error')],'layouts.main');}
 public function supplier(){$this->go(function($m,$u){$m->registerSupplier($_POST['supplier_id']);});}public function asset(){$this->go(function($m,$u){$m->createAsset($_POST,$u);});}public function person(){$this->go(function($m,$u){$m->createPerson($_POST);});}public function norm(){$this->go(function($m,$u){$m->createNorm($_POST,$u);});}public function route(){$this->go(function($m,$u){$m->createRoute($_POST);});}public function order(){$this->go(function($m,$u){$m->createOrder($_POST,$u);});}public function submit($id){$this->go(function($m,$u)use($id){$m->submitOrder($id,$u);});}public function approve($id){$this->go(function($m,$u)use($id){$m->approveOrder($id,$u);});}public function receive($id){$this->go(function($m,$u)use($id){$m->receiveOrder($id,$_POST['quantity'],$_POST['idempotency_key'],$u);});}public function distribution(){$this->go(function($m,$u){$m->distribute($_POST,$u);});}public function mission(){$this->go(function($m,$u){$m->createMission($_POST,$u);});}public function approveMission($id){$this->go(function($m,$u)use($id){$m->approveMission($id,$u);});}public function startMission($id){$this->go(function($m,$u)use($id){$m->startMission($id,$_POST);});}public function completeMission($id){$this->go(function($m,$u)use($id){$m->completeMission($id,$_POST);});}public function advance($id){$this->go(function($m,$u)use($id){$m->issueAdvance($id,$_POST['amount'],$u);});}public function justification(){$this->go(function($m,$u){$m->justify($_POST,$u);});}public function adjustment(){$this->go(function($m,$u){$m->adjust($_POST,$u);});}public function toll(){$this->go(function($m,$u){$m->addToll($_POST,$u);});}public function settle($id){$this->go(function($m,$u)use($id){$m->settleMission($id);});}
 private function go($call)
 {
  $ajax=($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest';
  $ok=false;$status=422;
  if(!verify_csrf($_POST['_token']??'')){$message='Session expirée. Rechargez la page.';$status=419;}
  else {try{$call($this->model('FuelLogistics'),Auth::user());$ok=true;$message='Opération enregistrée.';}catch(Exception $e){$message=$e->getMessage();}}
  if($ajax){http_response_code($ok?200:$status);header('Content-Type: application/json');if($ok)flash('success',$message);echo json_encode(['success'=>$ok,'message'=>$message,'redirect'=>base_url('fuel-logistics')]);return;}
  flash($ok?'success':'error',$message);redirect('fuel-logistics');
 }
}
