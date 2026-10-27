<?php
require_once dirname(__DIR__).'/services/PoultryFlowService.php';

class PoultryController extends Controller
{
    private const RETURNS=['livestock/poultry','livestock/poultry/slaughters','butchery/production'];

    public function index() { $this->page('lots'); }
    public function slaughters() { $this->page('slaughters'); }

    private function page(string $section)
    {
        $poultry=new PoultryFlowService();$available=$poultry->poultryAvailable();
        $this->view('poultry.'.$section,array_merge($available?$poultry->directory():[],['title'=>$section==='lots'?'Volailles · lots et suivi':'Volailles · abattages et envois','section'=>$section,'available'=>$available,'success'=>flash('success'),'error'=>flash('error')]),'layouts.main');
    }

    public function receive() { $this->run(fn($s,$u)=>$s->receivePoultry($_POST,$u),'Réception enregistrée : le lot de volailles est installé dans le poulailler.'); }
    public function hatch() { $this->run(fn($s,$u)=>$s->hatch($_POST,$u),'Éclosion enregistrée : les poussins forment un nouveau lot de poulets de chair.'); }
    public function record() { $this->run(fn($s,$u)=>$s->record($_POST,$u),'Saisie du jour enregistrée.'); }
    public function slaughter() { $this->run(fn($s,$u)=>$s->slaughter($_POST,$u),'Abattage enregistré. Faites-le valider pour créer le stock de carcasses.'); }
    public function validateSlaughter($id) { $this->run(fn($s,$u)=>$s->validateSlaughter($id,$u),'Abattage validé : les oiseaux ont quitté l’effectif et les carcasses sont disponibles.'); }
    public function cancelSlaughter($id) { $this->run(fn($s,$u)=>$s->cancelSlaughter($id,$_POST,$u),'Abattage en attente annulé. L’historique est conservé.'); }
    public function shipment() { $this->run(fn($s,$u)=>$s->shipment($_POST,$u),'Envoi de carcasses préparé. Faites-le approuver avant l’expédition.'); }
    public function liveShipment() { $this->run(fn($s,$u)=>$s->liveShipment($_POST,$u),'Envoi de poulets vivants préparé. Faites-le approuver avant l’expédition.'); }
    public function approveShipment($id) { $this->run(fn($s,$u)=>$s->advanceShipment($id,'approve',$u),'Envoi approuvé.'); }
    public function dispatchShipment($id) { $this->run(fn($s,$u)=>$s->advanceShipment($id,'dispatch',$u),'Envoi expédié. Il sera compté et pesé à son arrivée à la boucherie.'); }
    public function cancelShipment($id) { $this->run(fn($s,$u)=>$s->cancelShipment($id,$_POST,$u),'Envoi annulé.'); }
    public function butcherySlaughter() { $this->run(fn($s,$u)=>$s->butcherySlaughter($_POST,$u),'Abattage enregistré : les carcasses sont en chambre froide, prêtes à transformer.'); }

    private function run(callable $callback,string $message)
    {
        $return=$_POST['_flow_return']??self::RETURNS[0];if(!is_string($return)||!in_array($return,self::RETURNS,true))$return=self::RETURNS[0];
        $ajax=strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest';
        if(!verify_csrf($_POST['_token']??'')){if($ajax)return $this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page.'],419);flash('error','Session expirée.');redirect($return);}
        try{$service=new PoultryFlowService();if(!$service->poultryAvailable())throw new RuntimeException('Le suivi des volailles n’est pas encore activé : appliquez la migration 044.');$callback($service,Auth::user());if($ajax)return $this->json(['ok'=>true,'message'=>$message,'refresh_url'=>base_url($return)]);flash('success',$message);}
        catch(Throwable $e){$message=$e instanceof PDOException?'Enregistrement impossible. Actualisez la page et vérifiez les informations saisies.':$e->getMessage();if($ajax)return $this->json(['ok'=>false,'message'=>$message],422);flash('error',$message);}redirect($return);
    }
}
