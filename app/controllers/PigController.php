<?php
require_once dirname(__DIR__).'/services/PigService.php';

class PigController extends Controller
{
    public function index()
    {
        $service = new PigService();
        $available = $service->available();
        $rows = $available ? $service->directory() : [];
        if (isset($_GET['export'])) {
            $format=$_GET['export'];
            if(!in_array($format,['excel','pdf'],true)){http_response_code(400);echo 'Format invalide.';return;}
            require_once dirname(__DIR__).'/services/PigExportService.php';
            $exportService=new PigExportService();
            try {$report=$exportService->report($rows,$_GET);} catch(InvalidArgumentException $e){http_response_code(400);echo 'Filtres invalides.';return;}
            $filename='dagril-porcs-effectifs-'.date('Ymd-His');
            header('Cache-Control: private, no-store');
            if($format==='excel'){
                $content=$exportService->spreadsheet($report);
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="'.$filename.'.xlsx"');
                echo $content;return;
            }
            $report['exportService']=$exportService;
            (new PdfService())->stream('Élevage porcin · Effectifs',$this->renderViewToString('pigs.export',$report),$filename.'.pdf','landscape');return;
        }
        $refs = $this->model('Livestock')->formReferences();
        $this->view('pigs.index', ['title'=>'Porcs · effectifs et groupes','available'=>$available,'rows'=>$rows,'facilities'=>$refs['facilities'],'success'=>flash('success'),'error'=>flash('error')], 'layouts.main');
    }

    public function register() { $this->run('register',null,'Porcs enregistrés. Les effectifs ont été actualisés.'); }
    public function identify($id) { $this->run('identify',$id,'Lot renseigné. Les effectifs mâles et femelles ont été actualisés.'); }
    public function confirmGroup($id) { $this->run('confirmGroup',$id,'Passage de groupe validé après vérification sur le terrain.'); }
    public function orient($id) { $this->run('orient',$id,'Orientation enregistrée. Les effectifs ont été répartis sans double comptage.'); }

    private function run(string $action, $id, string $message)
    {
        $ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
        if (!verify_csrf($_POST['_token'] ?? '')) {
            if ($ajax) return $this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page.'],419);
            flash('error','Session expirée.'); redirect('livestock/pigs');
        }
        try {
            $service = new PigService();
            if (!$service->available()) throw new RuntimeException('Le suivi porcin n’est pas encore activé.');
            if ($id === null) $service->$action($_POST,Auth::user());
            else $service->$action($id,$_POST,Auth::user());
            if ($ajax) return $this->json(['ok'=>true,'message'=>$message,'refresh_url'=>base_url('livestock/pigs')]);
            flash('success',$message);
        } catch (Throwable $e) {
            $message = $e instanceof PDOException ? 'Enregistrement impossible. Actualisez la page et vérifiez les informations saisies.' : $e->getMessage();
            if ($ajax) return $this->json(['ok'=>false,'message'=>$message],422);
            flash('error',$message);
        }
        redirect('livestock/pigs');
    }
}
