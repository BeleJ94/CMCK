<?php

class WeighbridgeTransportController extends Controller
{
    public function index()
    {
        $model=$this->model('WeighbridgeTransport');
        $this->view('weighbridge-transports.index',['title'=>'Transports pont-bascule','transports'=>$model->allDetailed(),'references'=>$model->references(),'errors'=>flash('errors')?:[],'success'=>flash('success'),'error'=>flash('error')],'layouts.main');
    }

    public function store()
    {
        if(!verify_csrf($_POST['_token']??'')){flash('error','Session expirée.');redirect('weighbridge-transports');}
        $data=['origin_type'=>trim($_POST['origin_type']??''),'origin_site_id'=>trim($_POST['origin_site_id']??''),'supplier_id'=>trim($_POST['supplier_id']??''),'product_id'=>trim($_POST['product_id']??''),'truck_plate_number'=>strtoupper(trim($_POST['truck_plate_number']??'')),'driver_name'=>trim($_POST['driver_name']??''),'driver_phone'=>trim($_POST['driver_phone']??''),'shipped_quantity_kg'=>trim($_POST['shipped_quantity_kg']??''),'route_description'=>trim($_POST['route_description']??''),'toll_amount'=>trim($_POST['toll_amount']??'0'),'toll_details'=>trim($_POST['toll_details']??'')];
        $errors=[];
        if($data['origin_type']!=='external_supplier'){$errors['origin_type']='Les BT agricoles doivent être créés et expédiés depuis Agriculture → Transports.';}
        if($data['origin_type']==='external_supplier'&&!ctype_digit($data['supplier_id'])){$errors['supplier_id']='Fournisseur obligatoire.';}
        foreach(['product_id','truck_plate_number','shipped_quantity_kg'] as $field){if($data[$field]===''){$errors[$field]='Champ obligatoire.';}}
        if(!is_numeric($data['shipped_quantity_kg'])||(float)$data['shipped_quantity_kg']<=0){$errors['shipped_quantity_kg']='Quantité expédiée invalide.';}
        if(!is_numeric($data['toll_amount'])||(float)$data['toll_amount']<0){$errors['toll_amount']='Montant de péage invalide.';}
        if($errors){flash('errors',$errors);redirect('weighbridge-transports');}
        try{$this->model('WeighbridgeTransport')->createTransport($data,Auth::user());flash('success','Transport fournisseur enregistré. Vous pouvez maintenant réceptionner son BT au pont-bascule.');}catch(Exception $e){flash('error',$e->getMessage());}
        redirect('weighbridge-transports');
    }
}
