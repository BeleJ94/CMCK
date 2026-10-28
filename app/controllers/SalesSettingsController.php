<?php
require_once dirname(__DIR__).'/services/SalesSettingsService.php';

class SalesSettingsController extends Controller
{
    public function index()
    {
        $this->view('sales/settings',array_merge((new SalesSettingsService())->directory(),['title'=>'Distribution · tarifs et taux','success'=>flash('success'),'error'=>flash('error')]),'layouts.main');
    }
    public function rate(){ $this->run(fn($s,$u)=>$s->setRate($_POST,$u),'Taux du jour enregistré. Il s’applique aux prochaines opérations.'); }
    public function price(){ $this->run(fn($s,$u)=>$s->setPrices($_POST,$u),'Tarif enregistré. Il s’applique aux prochaines commandes et ventes.'); }
    public function office(){ $this->run(fn($s,$u)=>$s->setMainOffice($_POST,$u),'Dépôts du bureau principal enregistrés.'); }

    private function run(callable $callback,string $message)
    {
        $ajax=strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest';
        if(!verify_csrf($_POST['_token']??'')){if($ajax)return $this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page.'],419);flash('error','Session expirée.');redirect('sales/settings');}
        try{$callback(new SalesSettingsService(),Auth::user());if($ajax)return $this->json(['ok'=>true,'message'=>$message,'refresh_url'=>base_url('sales/settings')]);flash('success',$message);}
        catch(Throwable $e){$message=$e instanceof PDOException?'Enregistrement impossible. Actualisez la page et vérifiez les informations saisies.':$e->getMessage();if($ajax)return $this->json(['ok'=>false,'message'=>$message],422);flash('error',$message);}
        redirect('sales/settings');
    }
}
