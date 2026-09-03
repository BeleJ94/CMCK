<?php

class DocumentController extends Controller
{
    public function index(){ $this->view('documents.index',['title'=>'Référentiel documentaire','documents'=>$this->model('Document')->allDetailed(),'success'=>flash('success'),'error'=>flash('error')],'layouts.main'); }
    public function show($id){$doc=$this->model('Document')->findDetailed((int)$id);if(!$doc){http_response_code(404);echo'Document introuvable.';return;}$this->view('documents.show',['title'=>$doc['type_name'],'document'=>$doc,'success'=>flash('success'),'error'=>flash('error')],'layouts.main');}
    public function attach($id){$this->csrf();try{$this->model('Document')->attach((int)$id,$_FILES['attachment']??[],Auth::user());flash('success','Pièce jointe ajoutée.');}catch(Exception $e){flash('error',$e->getMessage());}redirect('documents/'.$id);}
    public function download($id){$attachment=$this->model('Document')->attachment((int)$id);if(!$attachment){http_response_code(404);echo'Pièce jointe introuvable.';return;}$base=realpath(dirname(__DIR__,2).'/storage/uploads');$file=realpath(dirname(__DIR__,2).'/storage/uploads/'.$attachment['storage_path']);if(!$base||!$file||strpos($file,$base.DIRECTORY_SEPARATOR)!==0||!is_file($file)){http_response_code(404);echo'Fichier introuvable.';return;}header('Content-Type: '.$attachment['mime_type']);header('Content-Length: '.filesize($file));header('Content-Disposition: attachment; filename="'.str_replace(['"','\r','\n'],'',basename($attachment['original_name'])).'"');readfile($file);}
    public function submit($id){$this->workflow($id,'submit');} public function requestApproval($id){$this->workflow($id,'request_approval');}
    public function approve($id){$this->workflow($id,'approve');} public function reject($id){$this->workflow($id,'reject');}
    public function start($id){$this->workflow($id,'start');} public function receive($id){$this->workflow($id,'receive');}
    public function close($id){$this->workflow($id,'close');} public function cancel($id){$this->workflow($id,'cancel');}
    private function workflow($id,$action){$this->csrf();try{$doc=$this->model('Document')->findDetailed((int)$id);if(!$doc||!$doc['workflow_instance_id']){throw new RuntimeException('Workflow documentaire introuvable.');}require_once dirname(__DIR__).'/services/WorkflowService.php';(new WorkflowService())->transition($doc['workflow_instance_id'],$action,Auth::user(),trim($_POST['reason']??''));flash('success','Transition enregistrée.');}catch(Exception $e){flash('error',$e->getMessage());}redirect('documents/'.$id);}
    private function csrf(){if(!verify_csrf($_POST['_token']??'')){throw new RuntimeException('Jeton CSRF invalide.');}}
}
