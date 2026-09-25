<?php
require __DIR__.'/../app/helpers/functions.php';
require __DIR__.'/../app/core/Controller.php';
require __DIR__.'/../app/controllers/FuelLogisticsController.php';
class Auth {
 public static function start(){}
 public static function can($component,$action){return true;}
 public static function sites(){return [];}
}
class FuelDashboardFixture {
 public $unsettled=7;
 public function references(){return array_fill_keys(['allSuppliers','assets','centers','fuelTypes','machines','people','routes','suppliers','trucks'],[]);}
 public function dashboard(){return ['stocks'=>[],'orders'=>[],'missions'=>[],'distributions'=>[],'kpis'=>['unsettled'=>$this->unsettled,'consumption'=>[],'tolls'=>[]]];}
}
class FuelViewControllerTest extends FuelLogisticsController {
 public $fixture;
 protected function model($name){return $this->fixture;}
 protected function view($view,array $data=[],$layout=null){parent::view($view,$data,null);}
}
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
$controller=new FuelViewControllerTest();$controller->fixture=new FuelDashboardFixture();
foreach([7,0] as $count){
 $controller->fixture->unsettled=$count;
 ob_start();try{$controller->index();$html=ob_get_contents();}finally{ob_end_clean();}
 if(strpos($html,'<strong>'.$count.'</strong>')===false)throw new RuntimeException('Dashboard KPI missing');
 if(strpos($html,'KPIs de consommation')===false)throw new RuntimeException('View did not finish rendering');
}
restore_error_handler();
echo "OK : contrôleur et moteur de vues réels, indicateurs transmis et tableau de bord vide sans avertissement.\n";
