<?php
require_once dirname(__DIR__).'/services/CustomerOrderService.php';

class CustomerOrderController extends Controller
{
    public function index()
    {
        $this->view('sales/orders',array_merge((new CustomerOrderService())->directory(),['title'=>'Distribution · commandes clients','success'=>flash('success'),'error'=>flash('error')]),'layouts.main');
    }
    public function store(){ $this->run(fn($s,$u)=>$s->save($_POST,$u),'Commande enregistrée en brouillon. Faites-la valider pour réserver les sacs.'); }
    public function update($id){ $this->run(fn($s,$u)=>$s->save($_POST,$u,$id),'Brouillon de commande enregistré.'); }
    public function validate($id){ $this->run(fn($s,$u)=>$s->validate($id,$u),'Commande validée.'); }
    public function reserve($id){ $this->run(fn($s,$u)=>$s->reserveAvailable($id,$u),'Sacs disponibles réservés.'); }
    public function transfer($id){ $this->run(fn($s,$u)=>$s->prepareTransfer($id,$_POST,$u),'Envoi préparé.'); }
    public function close($id){ $this->run(fn($s,$u)=>$s->close($id,$_POST,$u),'Commande clôturée : le reste non livré est libéré.'); }
    public function cancel($id){ $this->run(fn($s,$u)=>$s->cancel($id,$_POST,$u),'Commande annulée : ses réservations sont libérées.'); }

    /** A service returning a sentence replaces the default success message (bags booked, transfer numbers). */
    private function run(callable $callback,string $message)
    {
        $ajax=strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest';
        if(!verify_csrf($_POST['_token']??'')){if($ajax)return $this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page.'],419);flash('error','Session expirée.');redirect('sales/orders');}
        try{$result=$callback(new CustomerOrderService(),Auth::user());if(is_string($result))$message=$result;if($ajax)return $this->json(['ok'=>true,'message'=>$message,'refresh_url'=>base_url('sales/orders')]);flash('success',$message);}
        catch(Throwable $e){$message=$e instanceof PDOException?'Enregistrement impossible. Actualisez la page et vérifiez les informations saisies.':$e->getMessage();if($ajax)return $this->json(['ok'=>false,'message'=>$message],422);flash('error',$message);}
        redirect('sales/orders');
    }
}
