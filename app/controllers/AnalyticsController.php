<?php
require_once dirname(__DIR__).'/services/KpiService.php';
class AnalyticsController extends Controller
{
 public function index(){$service=new KpiService(null,$_GET);$this->render('analytics.index',['title'=>'Pilotage intégré','filters'=>$service->filters(),'kpis'=>$service->summary(),'sites'=>$this->sites(),'generatedAt'=>date('d/m/Y H:i:s'),'detailCode'=>null,'detailRows'=>[]],'pilotage-dagril');}
 public function detail($code){$service=new KpiService(null,$_GET);$catalog=$service->catalog();if(!isset($catalog[$code])){http_response_code(404);echo'Indicateur introuvable';return;}$this->render('analytics.index',['title'=>$catalog[$code]['label'],'filters'=>$service->filters(),'kpis'=>$service->summary(),'sites'=>$this->sites(),'generatedAt'=>date('d/m/Y H:i:s'),'detailCode'=>$code,'detailMeta'=>$catalog[$code],'detailRows'=>$service->detail($code)],'kpi-'.$code);}
 private function sites(){if(Auth::canViewConsolidated())return Database::getInstance()->connection()->query("SELECT id,code,name FROM sites WHERE status='active' AND deleted_at IS NULL ORDER BY code")->fetchAll();return Auth::sites();}
 private function render($view,array$data,$filename){$export=$_GET['export']??'';if($export==='excel'){header('Content-Type: application/vnd.ms-excel; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'.xls"');header('Pragma: no-cache');$data['exportMode']='excel';$this->view($view,$data);return;}if($export==='pdf'){$data['exportMode']='pdf';$html=$this->renderViewToString($view,$data);(new PdfService())->stream($data['title'],$html,$filename.'.pdf');return;}$this->view($view,$data,'layouts.main');}
}
