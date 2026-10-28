<?php
require_once dirname(__DIR__).'/services/CustomerService.php';

class CustomerController extends Controller
{
    public function index()
    {
        $this->view('customers/index',array_merge((new CustomerService())->directory(),['title'=>'Distribution · clients','success'=>flash('success'),'error'=>flash('error')]),'layouts.main');
    }
    public function store(){ $this->run(fn($s,$u)=>$s->create($_POST,$u),'Client créé. Sa fiche est disponible dans le répertoire.'); }
    public function update($id){ $this->run(fn($s,$u)=>$s->update($id,$_POST,$u),'Fiche client enregistrée.'); }
    public function status($id){ $this->run(fn($s,$u)=>$s->setStatus($id,$_POST,$u),($_POST['status']??'')==='blocked'?'Client bloqué : il ne pourra plus passer de commande.':'Client débloqué.'); }

    private function run(callable $callback,string $message)
    {
        $ajax=strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest';
        if(!verify_csrf($_POST['_token']??'')){if($ajax)return $this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page.'],419);flash('error','Session expirée.');redirect('customers');}
        try{$callback(new CustomerService(),Auth::user());if($ajax)return $this->json(['ok'=>true,'message'=>$message,'refresh_url'=>base_url('customers')]);flash('success',$message);}
        catch(Throwable $e){$message=$e instanceof PDOException?'Enregistrement impossible. Actualisez la page et vérifiez les informations saisies.':$e->getMessage();if($ajax)return $this->json(['ok'=>false,'message'=>$message],422);flash('error',$message);}
        redirect('customers');
    }
}
