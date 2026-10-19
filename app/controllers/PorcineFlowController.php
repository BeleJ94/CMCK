<?php
require_once dirname(__DIR__).'/services/PorcineFlowService.php';

class PorcineFlowController extends Controller
{
    private const RETURNS=['livestock/pigs/slaughters','livestock/pigs/shipments','butchery/receipts','butchery/cold-room','butchery/production','butchery/counters','butchery/pos/history'];
    public function slaughters(){ $this->farm('slaughters'); }
    public function shipments(){ $this->farm('shipments'); }
    private function farm(string $section)
    {
        $flow=new PorcineFlowService();$available=$flow->available();
        $this->view('porcine_flow/farm',['title'=>$section==='slaughters'?'Porcs · abattage à la ferme':'Porcs · carcasses et envois','section'=>$section,'available'=>$available,'rows'=>$available?(new PigService())->directory():[],'farm'=>$available?$flow->farmDirectory():['slaughters'=>[],'shipments'=>[]],'success'=>flash('success'),'error'=>flash('error')],'layouts.main');
    }
    public function home()
    {
        $flow=new PorcineFlowService();
        if(!$flow->available()){$this->view('porcine_flow/unavailable',['title'=>'Boucherie'],'layouts.main');return;}
        $this->view('porcine_flow/butchery_home',array_merge($flow->butcheryDirectory(),['title'=>'Boucherie · vue d’ensemble','success'=>flash('success'),'error'=>flash('error')]),'layouts.main');
    }
    public function receipts(){ $this->butchery('receipts'); }
    public function coldRoom(){ $this->butchery('cold-room'); }
    public function production(){ $this->butchery('production'); }
    public function counters()
    {
        $flow=new PorcineFlowService();
        if(!$flow->available()||!$flow->posAvailable()){$this->butchery('counters');return;}
        $this->view('porcine_flow/pos',array_merge($flow->butcheryDirectory(),$flow->posDirectory(),['title'=>'Boucherie · point de vente','success'=>flash('success'),'error'=>flash('error')]),'layouts.main');
    }
    public function posCheckout()
    {
        if(!verify_csrf($_POST['_token']??''))return $this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page.'],419);
        try{
            $flow=new PorcineFlowService();if(!$flow->posAvailable())throw new RuntimeException('La mise à niveau de la caisse n’est pas encore appliquée.');
            $id=$flow->posCheckout($_POST,Auth::user());return $this->json(['ok'=>true,'ticket_id'=>$id,'receipt_url'=>base_url('butchery/pos/tickets/'.$id),'message'=>'Vente encaissée.']);
        }catch(Throwable $e){
            $definitive=$e instanceof RuntimeException&&!($e instanceof PDOException);
            return $this->json(['ok'=>false,'message'=>$definitive?$e->getMessage():'Résultat de l’encaissement non confirmé. Réessayez le même encaissement.'],$definitive?422:503);
        }
    }
    public function posPrice(){ $this->run(fn($s,$u)=>$s->posPrice($_POST,$u),'Tarif enregistré.'); }
    public function posCancel($id){ $this->run(fn($s,$u)=>$s->posCancel($id,$_POST,$u),'Ticket annulé : stock rétabli au comptoir.'); }
    public function posCatalog()
    {
        header('Cache-Control: private, no-store');
        $flow=new PorcineFlowService();if(!$flow->posAvailable())return $this->json(['ok'=>false,'message'=>'Caisse indisponible.'],503);
        $data=$flow->posDirectory();return $this->json(['ok'=>true,'catalog'=>$data['posCatalog'],'prices'=>$data['posPrices']]);
    }
    public function posHistory()
    {
        $flow=new PorcineFlowService();if(!$flow->posAvailable()){http_response_code(503);echo 'La caisse doit être mise à niveau.';return;}
        $this->view('porcine_flow/pos_history',array_merge($flow->butcheryDirectory(),$flow->posDirectory(),['title'=>'Boucherie · reçus et ventes','success'=>flash('success'),'error'=>flash('error')]),'layouts.main');
    }
    public function posReport()
    {
        $flow=new PorcineFlowService();if(!$flow->posAvailable()){http_response_code(503);echo 'La caisse doit être mise à niveau.';return;}
        $export=$_GET['export']??'';
        if(!is_string($export)||!in_array($export,['','xlsx','pdf'],true)){http_response_code(400);echo 'Format d’export invalide.';return;}
        try{$report=$flow->posSalesReport($_GET);}catch(InvalidArgumentException $e){http_response_code(400);echo e($e->getMessage());return;}
        header('Cache-Control: private, no-store');$filename='ventes-boucherie-'.$report['filters']['from'].'-'.$report['filters']['to'];
        if($export==='xlsx'){
            $bytes=(new ButcherySalesReportService())->spreadsheet($report);header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$filename.'.xlsx"');echo $bytes;return;
        }
        if($export==='pdf'){$html=$this->renderViewToString('porcine_flow/pos_report_pdf',['report'=>$report]);(new PdfService())->stream('Rapport de ventes · Boucherie',$html,$filename.'.pdf','landscape');return;}
        $this->view('porcine_flow/pos_report',['title'=>'Boucherie · rapport de ventes','report'=>$report],'layouts.main');
    }
    public function posReceipt($id)
    {
        $flow=new PorcineFlowService();if(!$flow->posAvailable()){http_response_code(503);echo 'La caisse doit être mise à niveau.';return;}
        $ticket=$flow->posTicket($id);if(!$ticket){http_response_code(404);echo 'Reçu introuvable.';return;}
        header('Cache-Control: private, no-store');$paper=($_GET['paper']??'80')==='a4'?'a4':'80';$pdf=($_GET['export']??'')==='pdf';
        $data=['ticket'=>$ticket,'paper'=>$paper,'pdf'=>$pdf,'methods'=>$flow->posSettings()['payment_methods']];
        if($pdf){$html=$this->renderViewToString('porcine_flow/pos_receipt',$data);$size=$paper==='a4'?'A4':[0,0,226.77,max(450,320+count($ticket['lines'])*45)];$bytes=(new PdfService())->output('Reçu de vente',$html,'portrait',$size);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="ticket-'.(int)$ticket['id'].'.pdf"');echo $bytes;return;}
        $this->view('porcine_flow/pos_receipt',$data);
    }
    private function butchery(string $section)
    {
        $flow=new PorcineFlowService();$available=$flow->available();
        if(!$available){$this->view('porcine_flow/unavailable',['title'=>'Boucherie'],'layouts.main');return;}
        $data=$flow->butcheryDirectory();
        $allocations=[];foreach($data['allocations'] as $allocation)$allocations[$allocation['output_id']]=$allocation;$data['allocations']=$allocations;
        $data['productionLines']=$this->model('Butchery')->productionLines();
        $this->view('porcine_flow/butchery',array_merge($data,['title'=>['receipts'=>'Boucherie · réceptions','cold-room'=>'Boucherie · chambre froide','production'=>'Boucherie · transformation','counters'=>'Boucherie · comptoirs et ventes'][$section],'section'=>$section,'success'=>flash('success'),'error'=>flash('error')]),'layouts.main');
    }
    public function slaughter(){ $this->run(fn($s,$u)=>$s->slaughter($_POST,$u),'Abattage enregistré. Validez-le pour créer le stock de carcasses.'); }
    public function validateSlaughter($id){ $this->run(fn($s,$u)=>$s->validateSlaughter($id,$u),'Abattage validé : les animaux ont quitté l’effectif et les carcasses sont disponibles.'); }
    public function shipmentBtr($id)
    {
        $service=new PorcineFlowService();
        $shipment=$service->available()?$service->shipmentBtr($id):null;
        if(!$shipment){http_response_code(404);echo 'BTR introuvable ou hors de votre périmètre.';return;}
        header('Cache-Control: private, no-store');
        if(($_GET['export']??'')==='pdf'){
            $html=$this->renderViewToString('porcine_flow/shipment_btr',['shipment'=>$shipment,'pdfMode'=>true]);
            (new PdfService())->stream('Bon de transfert',$html,'btr-'.(int)$shipment['id'].'.pdf','portrait');return;
        }
        $this->view('porcine_flow/shipment_btr',['shipment'=>$shipment,'pdfMode'=>false]);
    }

    public function shipment(){ $this->run(fn($s,$u)=>$s->shipment($_POST,$u),'Envoi préparé. Faites-le approuver avant l’expédition.'); }
    public function approveShipment($id){ $this->run(fn($s,$u)=>$s->advanceShipment($id,'approve',$u),'Envoi approuvé : les carcasses sont réservées.'); }
    public function dispatchShipment($id){ $this->run(fn($s,$u)=>$s->advanceShipment($id,'dispatch',$u),'Carcasses expédiées. Elles sont attendues à la réception de la boucherie.'); }
    public function arrival(){ $this->run(fn($s,$u)=>$s->arrival($_POST,$u),'Arrivée enregistrée. Effectuez le contrôle sanitaire pour entrer les carcasses au froid.'); }
    public function receive(){ $this->run(fn($s,$u)=>$s->receive($_POST,$u),'Contrôles confirmés et réception effectuée : les carcasses sont en chambre froide.'); }
    public function transform(){ $this->run(fn($s,$u)=>$s->transform($_POST,$u),'Transformation enregistrée et matière réservée. Validez les produits et leur répartition.'); }
    public function validateTransform($id){ $this->run(fn($s,$u)=>$s->validateTransform($id,$_POST,$u),'Transformation validée : produits répartis entre chambre froide et comptoir.'); }
    public function move(){ $this->run(fn($s,$u)=>$s->move($_POST,$u),'Mouvement enregistré. Les stocks de chambre froide et de comptoir ont été actualisés.'); }
    public function sale(){ $this->run(fn($s,$u)=>$s->sell($_POST,$u),'Vente enregistrée. Le stock du comptoir a été diminué.'); }
    public function createCounter(){ $this->run(fn($s,$u)=>$s->createCounter($_POST,$u),'Comptoir créé. Vous pouvez y affecter des produits.'); }
    public function cancelTransform($id){ $this->run(fn($s,$u)=>$s->cancelTransform($id,$_POST,$u),'Transformation annulée. La matière réservée est de nouveau disponible.'); }
    public function cancelSlaughter($id){ $this->run(fn($s,$u)=>$s->cancelSlaughter($id,$_POST,$u),'Abattage en attente annulé. L’historique est conservé.'); }
    public function cancelShipment($id){ $this->run(fn($s,$u)=>$s->cancelShipment($id,$_POST,$u),'Envoi annulé et réservation libérée.'); }
    private function run(callable $callback,string $message)
    {
        $return=$_POST['_flow_return']??'livestock/pigs/slaughters';if(!is_string($return)||!in_array($return,self::RETURNS,true))$return=self::RETURNS[0];
        $ajax=strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest';
        if(!verify_csrf($_POST['_token']??'')){if($ajax)return $this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page.'],419);flash('error','Session expirée.');redirect($return);}
        try{$service=new PorcineFlowService();if(!$service->available())throw new RuntimeException('Ce parcours n’est pas encore activé.');$callback($service,Auth::user());if($ajax)return $this->json(['ok'=>true,'message'=>$message,'refresh_url'=>base_url($return)]);flash('success',$message);}
        catch(Throwable $e){$message=$e instanceof PDOException?'Enregistrement impossible. Actualisez la page et vérifiez les informations saisies.':$e->getMessage();if($ajax)return $this->json(['ok'=>false,'message'=>$message],422);flash('error',$message);}redirect($return);
    }
}
