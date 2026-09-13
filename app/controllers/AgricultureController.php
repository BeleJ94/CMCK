<?php
class AgricultureController extends Controller
{
    public function index(){$this->directory('overview');}
    public function campaigns(){$this->directory('campaigns');}
    public function plots(){$this->directory('plots');}
    public function planning(){$this->directory('planning');}
    public function inputs(){$this->directory('inputs');}
    public function works(){require_once dirname(__DIR__).'/services/AgriculturalWorkService.php';$this->view('agriculture.works',['title'=>'Travaux agricoles','workData'=>(new AgriculturalWorkService())->directory()],'layouts.main');}
    public function workData(){require_once dirname(__DIR__).'/services/AgriculturalWorkService.php';$this->json((new AgriculturalWorkService())->directory());}
    public function correctWork($id){$this->run(function($m,$u)use($id){require_once dirname(__DIR__).'/services/AgriculturalWorkService.php';(new AgriculturalWorkService())->save($_POST,$u,$id);},'Travail corrigé. Le motif et les anciennes valeurs sont conservés.');}
    public function cancelWork($id){$this->run(function($m,$u)use($id){require_once dirname(__DIR__).'/services/AgriculturalWorkService.php';(new AgriculturalWorkService())->cancel($id,$_POST,$u);},'Travail annulé et exclu des totaux. Son historique est conservé.');}
    public function harvests(){$this->directory('harvests');}
    public function stocks(){$this->directory('stocks');}
    public function transports(){$this->directory('transports');}
    public function workers(){$this->directory('workers');}
    public function equipmentDirectory(){$this->directory('equipment');}
    public function updateCampaign($id){$this->run(function($m,$u)use($id){$m->updateCampaign($id,$_POST,$u);},'Campagne modifiée. Les changements sont conservés dans le journal d’activité.');}
    public function campaign(){$this->run(function($m,$u){flash('created_campaign_id',$m->createCampaign($_POST,$u));});}
    public function plot(){$this->run(function($m){$m->createPlot($_POST);});}
    public function planPlot(){$this->run(function($m){$m->planPlot($_POST);});}
    public function input(){$this->run(function($m,$u){$m->allocateInput($_POST,$u);});}
    public function worker(){$this->run(function($m){$m->createWorker($_POST);});}
    public function equipment(){$this->run(function($m){$m->createEquipment($_POST);});}
    public function work(){$this->run(function($m,$u){$m->recordWork($_POST,$u);});}
    public function harvest(){$this->run(function($m,$u){$m->createHarvest($_POST,$u);});}
    public function updateHarvest($id){$this->run(function($m,$u)use($id){$m->updateHarvest($id,$_POST,$u);},'Récolte modifiée. Les stocks concernés et le journal d’activité ont été actualisés.');}
    public function validateHarvest($id){$this->run(function($m,$u)use($id){$m->validateHarvest($id,$u);});}
    public function transport(){$this->run(function($m,$u){$m->createTransport($_POST,$u);});}
    public function approveTransport($id){$this->run(function($m,$u)use($id){$m->approveTransport($id,$u);});}
    public function dispatchTransport($id){$this->run(function($m,$u)use($id){$m->dispatchTransport($id,$u);}, 'BT expédié et disponible au pont-bascule du site Silos.');}
    private function directory($section){$m=$this->model('Agriculture');$titles=['overview'=>'Vue d’ensemble agricole','campaigns'=>'Campagnes agricoles','plots'=>'Parcelles agricoles','planning'=>'Planification agricole','inputs'=>'Intrants agricoles','works'=>'Travaux agricoles','harvests'=>'Récoltes agricoles','stocks'=>'Stocks agricoles','transports'=>'Transports agricoles','workers'=>'Main-d’œuvre agricole','equipment'=>'Matériels agricoles'];$this->view('agriculture.index',['title'=>$titles[$section],'section'=>$section,'refs'=>$m->references(),'suggestedCodes'=>$m->suggestedCodes(),'campaigns'=>$m->campaigns(),'plots'=>$m->plots(),'campaignPlots'=>$m->campaignPlots(),'inputAllocations'=>$m->inputAllocations(),'works'=>$m->works(),'harvests'=>$m->harvests(),'stocks'=>$m->stocks(),'transports'=>$m->transports(),'workers'=>$m->workers(),'equipmentList'=>$m->equipmentList(),'success'=>flash('success'),'error'=>flash('error')],'layouts.main');}
    private function run($callback, $successMessage = 'Opération agricole enregistrée.')
    {
        $return=$_POST['_return_to']??'agriculture';
        $allowed=['agriculture','agriculture/campaigns','agriculture/plots','agriculture/planning','agriculture/inputs','agriculture/works','agriculture/harvests','agriculture/stocks','agriculture/transports','agriculture/workers','agriculture/equipment'];
        if(!in_array($return,$allowed,true))$return='agriculture';
        $ajax=strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest';
        if(!verify_csrf($_POST['_token']??'')){
            if($ajax){$this->jsonResponse(false,'Session expirée. Rechargez la page avant de recommencer.',$return,419);}
            flash('error','Session expirée.');redirect($return);
        }
        try{
            $callback($this->model('Agriculture'),Auth::user());
            if($ajax){$this->jsonResponse(true,$successMessage,$return);}
            flash('success',$successMessage);
        }catch(Exception$e){
            if($ajax){$this->jsonResponse(false,$e->getMessage(),$return,422);}
            flash('error',$e->getMessage());
        }
        redirect($return);
    }

    private function jsonResponse($ok,$message,$return,$status=200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>(bool)$ok,'message'=>$message,'refresh_url'=>base_url($return)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
}
