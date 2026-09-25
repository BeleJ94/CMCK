<?php
class LivestockController extends Controller
{
 public function index(){$this->page('species');}
 public function lots(){$this->page('batches');}
 public function speciesLots($id){$this->page('batches',(int)$id);}
 public function daily(){$this->page('daily');}
 public function conversionsPage(){$this->page('conversions');}
 public function transfersPage(){$this->page('transfers');}
 public function facilitiesPage(){$this->page('setup');}
 private function page($space,$speciesId=null){
 $pages=[
 'species'=>['Élevage par espèce','heart-pulse','Choisissez une espèce, puis un lot pour enregistrer une action.',['batches','feedings','weighings','poultry']],
 'batches'=>['Lots d’élevage','heart-pulse','Choisissez un lot pour consulter son effectif et enregistrer une action.',['batches','feedings','weighings','poultry']],
 'daily'=>['Suivi quotidien','calendar-check','Enregistrez l’alimentation, les pesées et la production avicole.',['feedings','weighings','poultry']],
 'conversions'=>['Conversions pesées','arrow-repeat','Préparez et validez le passage des animaux vivants vers un stock pesé.',['conversions']],
 'transfers'=>['Transferts vers la boucherie','truck','Préparez les bons de transfert, puis suivez leur approbation et leur expédition.',['transfers']],
 'setup'=>['Bâtiments et étangs','building','Gérez les unités d’accueil du site sélectionné.',['facilities']]];
 [$title,$icon,$help,$modals]=$pages[$space];$m=$this->model('Livestock');$refs=$m->references();$batches=$m->batches();$selectedSpecies=null;
 if($speciesId!==null){foreach($refs['species'] as $species)if((int)$species['id']===$speciesId)$selectedSpecies=$species;
 if(!$selectedSpecies){foreach($batches as $batch)if((int)$batch['species_id']===$speciesId){$selectedSpecies=['id'=>$speciesId,'name'=>$batch['species_name']];break;}}
 if(!$selectedSpecies){http_response_code(404);echo 'Espèce introuvable.';return;}
 $batches=array_values(array_filter($batches,fn($batch)=>(int)$batch['species_id']===$speciesId));$title='Lots · '.$selectedSpecies['name'];$help='Consultez les lots de cette espèce dans le périmètre du site sélectionné.';
 }

 $this->view('livestock.index',['title'=>$title,'space'=>$space,'pageIcon'=>$icon,'pageHelp'=>$help,'modalKinds'=>$modals,'selectedSpecies'=>$selectedSpecies,'refs'=>$refs,'formRefs'=>$m->formReferences(),'batches'=>$batches,'dailyHistory'=>$space==='daily'?$m->dailyHistory():[],'conversions'=>in_array($space,['conversions','transfers'],true)?$m->conversions():[],'transfers'=>$space==='transfers'?$m->transfers():[],'success'=>flash('success'),'error'=>flash('error')],'layouts.main');
 }
 public function updateFacility($id){$this->go(function($m,$u)use($id){$m->updateFacility($id,$_POST,$u);});}
 public function facility(){$this->go(function($m){$m->createFacility($_POST);});}public function batch(){$this->go(function($m,$u){$m->createBatch($_POST,$u);});}public function feed(){$this->go(function($m,$u){$m->feed($_POST,$u);});}public function weigh(){$this->go(function($m,$u){$m->weigh($_POST,$u);});}public function poultry(){$this->go(function($m,$u){$m->poultry($_POST,$u);});}public function conversion(){$this->go(function($m,$u){$m->createConversion($_POST,$u);});}public function fishCatch(){$this->go(function($m,$u){$m->createFishCatch($_POST,$u);});}public function validateConversion($id){$this->go(function($m,$u)use($id){$m->validateConversion($id,$u);});}public function transfer(){$this->go(function($m,$u){$m->createTransfer($_POST,$u);});}public function approveTransfer($id){$this->go(function($m,$u)use($id){$m->approveTransfer($id,$u);});}public function dispatchTransfer($id){$this->go(function($m,$u)use($id){$m->dispatchTransfer($id,$u);});}
 private function go($f){
 $returnPath=$_POST['_livestock_return']??'livestock';if(!preg_match('~^livestock/species/[1-9][0-9]*$~',$returnPath)&&!in_array($returnPath,['livestock/lots','livestock','livestock/daily','livestock/conversions','livestock/transfers','livestock/facilities'],true))$returnPath='livestock';
 $ajax=strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest';
 if(!verify_csrf($_POST['_token']??'')){if($ajax){$this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page puis réessayez.'],419);return;}flash('error','Session expirée.');redirect($returnPath);}
 try{$f($this->model('Livestock'),Auth::user());if($ajax)return $this->json(['ok'=>true,'message'=>'Opération d’élevage enregistrée.','refresh_url'=>base_url($returnPath)]);flash('success','Opération d’élevage enregistrée.');}
 catch(Exception $e){$message=$e instanceof PDOException?'Enregistrement impossible. Vérifiez les références, les dates et les valeurs saisies. Un code d’unité doit être unique.':$e->getMessage();if($ajax)return $this->json(['ok'=>false,'message'=>$message],422);flash('error',$message);}redirect($returnPath);
 }
}
