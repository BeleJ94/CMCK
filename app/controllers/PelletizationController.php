<?php
class PelletizationController extends Controller
{
 public function index(){$m=$this->model('Pelletization');$this->view('pelletization.index',['title'=>'Pelletisation','refs'=>$m->references(),'compositions'=>$m->compositions(),'orders'=>$m->orders(),'transfers'=>$m->transfers(),'purchases'=>$m->purchases(),'packagingItems'=>$m->packagingItems(),'success'=>flash('success'),'error'=>flash('error')],'layouts.main');}
 public function wasteTransfer(){$this->go(function($m,$u){$m->createWasteTransfer($_POST,$u);});}public function shipTransfer($id){$this->go(function($m,$u)use($id){$m->shipWasteTransfer($id,$u);});}public function receiveTransfer($id){$this->go(function($m,$u)use($id){$m->receiveWasteTransfer($id,$u);});}
 public function additivePurchase(){$this->go(function($m,$u){$m->buyAdditive($_POST,$u);});}public function receiveAdditive($id){$this->go(function($m,$u)use($id){$m->receiveAdditive($id,$u);});}
 public function recipe(){$this->go(function($m,$u){$m->createRecipeVersion($_POST,$u);});}public function order(){$this->go(function($m,$u){$m->createOrder($_POST,$u);});}public function results($id){$this->go(function($m,$u)use($id){$m->submitResults($id,$_POST,$u);});}public function validateOrder($id){$this->go(function($m,$u)use($id){$m->validateOrder($id,$u);});}public function package($id){$this->go(function($m,$u)use($id){$m->package($id,$_POST,$u);});}
 private function go($fn){
  $ajax=strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest';
  if(!verify_csrf($_POST['_token']??'')){if($ajax)return $this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page.'],419);flash('error','Session expirée.');redirect('pelletization');}
  try{$fn($this->model('Pelletization'),Auth::user());if($ajax)return $this->json(['ok'=>true,'message'=>'Opération de pelletisation enregistrée.','refresh_url'=>base_url('pelletization')]);flash('success','Opération de pelletisation enregistrée.');}
  catch(Exception $e){$message=$e instanceof PDOException?'Enregistrement impossible. Vérifiez les champs et le site sélectionné.':$e->getMessage();if($ajax)return $this->json(['ok'=>false,'message'=>$message],422);flash('error',$message);}redirect('pelletization');
 }
}
