<?php
class BudgetController extends Controller
{
 public function index(){$m=$this->model('Budget');$rows=$m->report();foreach($rows as&$r)$r['projection']=$r['elapsed_days']>0?(float)$r['realized']/(float)$r['elapsed_days']*(float)$r['period_days']:0;$this->view('budgets.index',['title'=>'Planification budgétaire','refs'=>$m->references(),'budgets'=>$m->budgets(),'lines'=>$m->activeLines(),'approvals'=>$m->approvals(),'report'=>$rows,'success'=>flash('success'),'error'=>flash('error')],'layouts.main');}
 public function year(){$this->go(function($m,$u){$m->createFiscalYear($_POST,$u);});}public function budget(){$this->go(function($m,$u){$m->createBudget($_POST,$u);});}public function submit($id){$this->go(function($m,$u)use($id){$m->submit($id,$u);});}public function df($id){$this->go(function($m,$u)use($id){$m->approveDf($id,$u);});}public function dg($id){$this->go(function($m,$u)use($id){$m->approveDg($id,$u);});}public function revise($id){$this->go(function($m,$u)use($id){$m->revise($id,$_POST,$u);});}public function commitment(){$this->go(function($m,$u){$m->createCommitment($_POST,$u);});}public function expense(){$this->go(function($m,$u){$m->createExpense($_POST,$u);});}public function expenseDf($id){$this->go(function($m,$u)use($id){$m->approveExpense($id,$u);});}public function derogation($id){$this->go(function($m,$u)use($id){$m->approveDerogation($id,$u);});}public function delegation(){$this->go(function($m,$u){$m->requestDelegation($_POST,$u);});}public function approveDelegation($id){$this->go(function($m,$u)use($id){$m->approveDelegation($id,$u);});}
 private function go($f)
 {
  $ajax=($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest';$ok=false;$status=422;
  if(!verify_csrf($_POST['_token']??'')){$message='Session expirée. Rechargez la page.';$status=419;}
  else{try{$f($this->model('Budget'),Auth::user());$ok=true;$message='Opération budgétaire enregistrée.';}catch(Exception $e){$message=$e->getMessage();}}
  if($ajax){http_response_code($ok?200:$status);header('Content-Type: application/json');if($ok)flash('success',$message);echo json_encode(['success'=>$ok,'message'=>$message,'redirect'=>base_url('budgets')]);return;}
  flash($ok?'success':'error',$message);redirect('budgets');
 }
}
