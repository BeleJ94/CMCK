<?php
class MachineController extends Controller
{
    public function index()
    {
        $period=$_GET['period']??'all';if(!in_array($period,['month','year','all'],true))$period='all';
        $from=$period==='month'?date('Y-m-01'):($period==='year'?date('Y-01-01'):null);
        $this->view('machines.index',['title'=>'Machines','machines'=>$this->model('Machine')->allWithPerformance($from,$period==='all'?null:date('Y-m-d')),'period'=>$period,'openEditor'=>$_GET['edit']??(!empty($_GET['create'])?'new':''),'success'=>flash('success'),'error'=>flash('error')],'layouts.main');
    }
    public function create(){$_GET['create']=1;$this->index();}
    public function edit($id){$_GET['edit']=$id;$this->index();}
    public function store(){$this->save();}
    public function update($id){$this->save($id);}
    private function save($id=null)
    {
        $this->run(function()use($id){
            $data=['site_id'=>trim($_POST['site_id']??''),'name'=>trim($_POST['name']??''),'machine_type'=>trim($_POST['machine_type']??''),'capacity_kg_hour'=>trim($_POST['capacity_kg_hour']??''),'status'=>trim($_POST['status']??'')];
            if(mb_strlen($data['name'])<2||mb_strlen($data['name'])>150)throw new RuntimeException('Le nom doit contenir entre 2 et 150 caractères.');
            if(!in_array($data['machine_type'],['main','waste'],true))throw new RuntimeException('Choisissez un type de machine valide.');
            if(!in_array($data['status'],['active','inactive','pending','validated','cancelled'],true))throw new RuntimeException('Choisissez un statut valide.');
            $cap=$data['capacity_kg_hour'];if($cap!==''&&(!is_numeric($cap)||!is_finite((float)$cap)||(float)$cap<=0||(float)$cap>999999999.999))throw new RuntimeException('La capacité doit être supérieure à zéro, ou laissée vide si elle est inconnue.');
            $model=$this->model('Machine');$before=$id?$model->findActive($id):null;
            if($id)$model->updateMachine($id,$data);else $id=$model->createMachine($data);
            $this->model('ActivityLog')->record($before?'update':'create','machines','machines',$id,$before?'Modification machine.':'Création machine.',$before,$data);
        },'Machine enregistrée.');
    }
    public function toggle($id)
    {
        $this->run(function()use($id){$model=$this->model('Machine');$before=$model->findActive($id);$model->toggleStatus($id);$this->model('ActivityLog')->record('update','machines','machines',$id,'Changement de statut.',$before,$model->findActive($id));},'Statut de la machine mis à jour.');
    }
    private function run(callable $action,$message)
    {
        $ajax=strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest';
        if(!verify_csrf($_POST['_token']??'')){if($ajax)return $this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page.'],419);flash('error','Session expirée.');redirect('machines');}
        $db=Database::getInstance()->connection();
        try{$db->beginTransaction();$action();$db->commit();if($ajax)return $this->json(['ok'=>true,'message'=>$message,'refresh_url'=>base_url('machines?period='.(in_array($_POST['_period']??'',['month','year','all'],true)?$_POST['_period']:'all'))]);flash('success',$message);}
        catch(Exception $e){if($db->inTransaction())$db->rollBack();$message=$e instanceof PDOException?'Enregistrement impossible. Vérifiez les données ou contactez l’administrateur.':$e->getMessage();if($ajax)return $this->json(['ok'=>false,'message'=>$message],422);flash('error',$message);}
        redirect('machines');
    }
}
