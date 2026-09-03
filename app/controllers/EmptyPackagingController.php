<?php
class EmptyPackagingController extends Controller
{
 public function index(){$m=$this->model('EmptyPackaging');$this->view('empty_packaging.index',['title'=>'Emballages vides','stocks'=>$m->dashboard(),'references'=>$m->references(),'purchases'=>$m->pendingPurchases(),'requests'=>$m->pendingRequests(),'transfers'=>$m->pendingTransfers(),'success'=>flash('success'),'error'=>flash('error')],'layouts.main');}
 public function storePurchase(){$this->csrf();$this->act(function($m,$d,$u){return$m->createPurchase($d,$u);},$this->input());}
 public function approvePurchase($id){$this->csrf();$this->act(function($m,$d,$u)use($id){$m->approvePurchase($id,$u);});}
 public function receivePurchase($id){$this->csrf();$this->act(function($m,$d,$u)use($id){$m->receivePurchase($id,$u,$_POST['idempotency_key']??bin2hex(random_bytes(12)));});}
 public function storeRequest(){$this->csrf();$this->act(function($m,$d,$u){return$m->createRequest($d,$u);},$this->input());}
 public function approveRequest($id){$this->csrf();$this->act(function($m,$d,$u)use($id){$m->approveRequest($id,$u);});}
 public function issue($id){$this->csrf();$this->act(function($m,$d,$u)use($id){$m->issue($id,$u);});}
 public function defect(){$this->csrf();$this->act(function($m,$d,$u){$m->recordDefect($d,$u);},$this->input());}
 public function transfer(){$this->csrf();$this->act(function($m,$d,$u){$m->createTransfer($d,$u);},$this->input());}
 public function minimum($id){$this->csrf();$this->act(function($m,$d,$u)use($id){$m->setMinimum($id,$_POST['minimum_quantity']??0,$u);});}
 public function approveTransfer($id){$this->csrf();$this->act(function($m,$d,$u)use($id){$m->approveTransfer($id,$u);});}
 public function receiveTransfer($id){$this->csrf();$this->act(function($m,$d,$u)use($id){$m->receiveTransfer($id,$u);});}
 private function input(){return['supplier_id'=>trim($_POST['supplier_id']??''),'packaging_item_id'=>trim($_POST['packaging_item_id']??''),'quantity'=>trim($_POST['quantity']??''),'unit_cost'=>trim($_POST['unit_cost']??'0'),'needed_at'=>trim($_POST['needed_at']??date('Y-m-d H:i:s')),'justification'=>trim($_POST['justification']??''),'reason'=>trim($_POST['reason']??''),'destination_site_id'=>trim($_POST['destination_site_id']??'')];}
 private function act($fn,$data=[]){try{$fn($this->model('EmptyPackaging'),$data,Auth::user());flash('success','Opération d’emballages enregistrée.');}catch(Exception$e){flash('error',$e->getMessage());}redirect('empty-packaging');}
 private function csrf(){if(!verify_csrf($_POST['_token']??'')){flash('error','Session expirée.');redirect('empty-packaging');}}
}
