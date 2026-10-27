<?php
require_once dirname(__DIR__).'/services/CattleFlowService.php';

class CattleController extends Controller
{
    private const RETURNS=['livestock/cattle','livestock/cattle/slaughters'];

    public function index() { $this->page('lots'); }
    public function slaughters() { $this->page('slaughters'); }

    private function page(string $section)
    {
        $cattle=new CattleFlowService();$available=$cattle->cattleAvailable();
        $this->view('cattle.'.$section,array_merge($available?$cattle->directory():[],['title'=>$section==='lots'?'Bœufs · réceptions et lots':'Bœufs · abattages et envois','section'=>$section,'available'=>$available,'success'=>flash('success'),'error'=>flash('error')]),'layouts.main');
    }

    public function receive() { $this->run(fn($s,$u)=>$s->receiveCattle($_POST,$u),'Réception enregistrée : le lot de bovins est en élevage dans le bâtiment.'); }
    public function slaughter() { $this->run(fn($s,$u)=>$s->slaughter($_POST,$u),'Abattage enregistré. Faites-le valider pour créer le stock de carcasses.'); }
    public function validateSlaughter($id) { $this->run(fn($s,$u)=>$s->validateSlaughter($id,$u),'Abattage validé : les bovins ont quitté l’effectif et les carcasses sont disponibles.'); }
    public function cancelSlaughter($id) { $this->run(fn($s,$u)=>$s->cancelSlaughter($id,$_POST,$u),'Abattage en attente annulé. L’historique est conservé.'); }
    public function shipment() { $this->run(fn($s,$u)=>$s->shipment($_POST,$u),'Envoi préparé. Faites-le approuver avant l’expédition.'); }
    public function approveShipment($id) { $this->run(fn($s,$u)=>$s->advanceShipment($id,'approve',$u),'Envoi approuvé : les carcasses sont réservées.'); }
    public function dispatchShipment($id) { $this->run(fn($s,$u)=>$s->advanceShipment($id,'dispatch',$u),'Carcasses expédiées. Elles seront pesées à leur arrivée à la boucherie.'); }
    public function cancelShipment($id) { $this->run(fn($s,$u)=>$s->cancelShipment($id,$_POST,$u),'Envoi annulé et réservation libérée.'); }

    private function run(callable $callback,string $message)
    {
        $return=$_POST['_flow_return']??self::RETURNS[0];if(!is_string($return)||!in_array($return,self::RETURNS,true))$return=self::RETURNS[0];
        $ajax=strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest';
        if(!verify_csrf($_POST['_token']??'')){if($ajax)return $this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page.'],419);flash('error','Session expirée.');redirect($return);}
        try{$service=new CattleFlowService();if(!$service->cattleAvailable())throw new RuntimeException('Le suivi des bovins n’est pas encore activé : appliquez la migration 043.');$callback($service,Auth::user());if($ajax)return $this->json(['ok'=>true,'message'=>$message,'refresh_url'=>base_url($return)]);flash('success',$message);}
        catch(Throwable $e){$message=$e instanceof PDOException?'Enregistrement impossible. Actualisez la page et vérifiez les informations saisies.':$e->getMessage();if($ajax)return $this->json(['ok'=>false,'message'=>$message],422);flash('error',$message);}redirect($return);
    }
}
