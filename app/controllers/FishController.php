<?php
require_once dirname(__DIR__).'/services/FishFlowService.php';

class FishController extends Controller
{
    private const RETURNS=['livestock/fish','livestock/fish/harvests'];

    public function index() { $this->page('ponds'); }
    public function harvests() { $this->page('harvests'); }

    private function page(string $section)
    {
        $fish=new FishFlowService();$available=$fish->fishAvailable();
        $this->view('fish.'.$section,array_merge($available?$fish->directory():[],['title'=>$section==='ponds'?'Poissons · étangs et lots':'Poissons · pêches et envois','section'=>$section,'available'=>$available,'success'=>flash('success'),'error'=>flash('error')]),'layouts.main');
    }

    public function createParcel() { $this->run(fn($s,$u)=>$s->createParcel($_POST,$u),'Parcelle ajoutée à l’étang.'); }
    public function updateParcel($id) { $this->run(fn($s,$u)=>$s->updateParcel($id,$_POST,$u),'Parcelle mise à jour.'); }
    public function stock() { $this->run(fn($s,$u)=>$s->stock($_POST,$u),'Mise en charge enregistrée : le lot est en élevage dans la parcelle.'); }
    public function harvest() { $this->run(fn($s,$u)=>$s->harvest($_POST,$u),'Pêche enregistrée. Faites-la valider pour créer le stock pesé.'); }
    public function validateHarvest($id) { $this->run(fn($s,$u)=>$s->validateHarvest($id,$_POST,$u),'Pêche validée : le poisson pesé est disponible pour un envoi à la boucherie.'); }
    public function cancelHarvest($id) { $this->run(fn($s,$u)=>$s->cancelHarvest($id,$_POST,$u),'Pêche en attente annulée. L’historique est conservé.'); }
    public function shipment() { $this->run(fn($s,$u)=>$s->shipment($_POST,$u),'Envoi préparé. Faites-le approuver avant l’expédition.'); }
    public function approveShipment($id) { $this->run(fn($s,$u)=>$s->advanceShipment($id,'approve',$u),'Envoi approuvé : le poisson est réservé.'); }
    public function dispatchShipment($id) { $this->run(fn($s,$u)=>$s->advanceShipment($id,'dispatch',$u),'Poisson expédié. Il sera pesé à son arrivée à la boucherie.'); }
    public function cancelShipment($id) { $this->run(fn($s,$u)=>$s->cancelShipment($id,$_POST,$u),'Envoi annulé et réservation libérée.'); }

    private function run(callable $callback,string $message)
    {
        $return=$_POST['_flow_return']??self::RETURNS[0];if(!is_string($return)||!in_array($return,self::RETURNS,true))$return=self::RETURNS[0];
        $ajax=strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest';
        if(!verify_csrf($_POST['_token']??'')){if($ajax)return $this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page.'],419);flash('error','Session expirée.');redirect($return);}
        try{$service=new FishFlowService();if(!$service->fishAvailable())throw new RuntimeException('Le suivi piscicole n’est pas encore activé : appliquez la migration 042.');$callback($service,Auth::user());if($ajax)return $this->json(['ok'=>true,'message'=>$message,'refresh_url'=>base_url($return)]);flash('success',$message);}
        catch(Throwable $e){$message=$e instanceof PDOException?'Enregistrement impossible. Actualisez la page et vérifiez les informations saisies.':$e->getMessage();if($ajax)return $this->json(['ok'=>false,'message'=>$message],422);flash('error',$message);}redirect($return);
    }
}
