<?php

class AccessControlController extends Controller
{
    public function index()
    {
        $data=$this->model('AccessControl')->dashboardData();
        $data['title']='Roles et permissions'; $data['success']=flash('success'); $data['error']=flash('error');
        $this->view('access_control.index',$data,'layouts.main');
    }

    public function storeAssignment()
    {
        $this->csrf();
        try {
            $this->model('AccessControl')->createAssignment([
                'user_id'=>(int)($_POST['user_id']??0),'role_id'=>(int)($_POST['role_id']??0),'site_id'=>trim($_POST['site_id']??''),
                'assignment_type'=>in_array($_POST['assignment_type']??'', ['direct','delegation'],true)?$_POST['assignment_type']:'direct',
                'starts_at'=>$this->date($_POST['starts_at']??''),'expires_at'=>$this->date($_POST['expires_at']??''),'reason'=>trim($_POST['reason']??'')
            ],Auth::user()); flash('success','Attribution enregistree.');
        }catch(Exception $e){flash('error',$e->getMessage());}
        redirect('access-control');
    }

    public function approve($id){$this->decision($id,'approve');}
    public function reject($id){$this->decision($id,'reject');}
    private function decision($id,$decision){$this->csrf();try{$this->model('AccessControl')->decide((int)$id,$decision,Auth::user());flash('success','Decision enregistree.');}catch(Exception $e){flash('error',$e->getMessage());}redirect('access-control');}
    public function revoke($id){$this->csrf();try{$this->model('AccessControl')->revoke((int)$id,Auth::user());flash('success','Attribution revoquee.');}catch(Exception $e){flash('error',$e->getMessage());}redirect('access-control');}
    public function updatePermissions($id){$this->csrf();try{$this->model('AccessControl')->syncRolePermissions((int)$id,$_POST['permission_ids']??[],Auth::user());flash('success','Permissions mises a jour.');}catch(Exception $e){flash('error',$e->getMessage());}redirect('access-control');}
    private function csrf(){if(!verify_csrf($_POST['_token']??'')){throw new RuntimeException('Jeton CSRF invalide.');}}
    private function date($value){$value=trim($value);return $value===''?null:date('Y-m-d H:i:s',strtotime($value));}
}
