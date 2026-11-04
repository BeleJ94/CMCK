<?php

class WasteController extends Controller
{
    public function index()
    {
        $model = $this->model('Waste');

        $this->view('waste.index', [
            'title' => 'Issues de meunerie',
            'availableStock' => $model->totalAvailable(),
            'stockLines' => $model->stockLines(),
            'history' => $model->history(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function export()
    {
        $format = $_GET['format'] ?? '';
        if (!in_array($format, ['excel','pdf'], true)) { http_response_code(400); echo 'Format d’export invalide.'; return; }
        require_once dirname(__DIR__).'/services/WasteExportService.php';
        $service = new WasteExportService();
        $filters=[];
        foreach (['search','type','site','state'] as $key) $filters[$key]=is_string($_GET[$key]??null)?$_GET[$key]:($key==='state'?'usable':'');
        $lines=$service->filtered($this->model('Waste')->stockLines(),$filters);
        $states=['usable'=>'Stocks utilisables',''=>'Tous les stocks','available'=>'Disponible','buffer'=>'En tampon','empty'=>'Épuisé / indisponible'];
        $summary='Recherche : '.($filters['search']?:'Toutes').' · Type : '.($filters['type']?:'Tous').' · Site : '.($filters['site']?:'Tous les sites autorisés').' · Situation : '.($states[$filters['state']]??'Non reconnue');
        header('Cache-Control: private, no-store');
        $filename='dagril-stocks-issues-'.date('Ymd-His');
        if ($format==='excel') {
            $content=$service->xlsx($lines,$summary);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="'.$filename.'.xlsx"');
            echo $content;
            return;
        }
        $html=$this->renderViewToString('waste.export',['lines'=>$lines,'summary'=>$summary,'service'=>$service]);
        (new PdfService())->stream('Issues de meunerie', $html, $filename.'.pdf', 'landscape');
    }

    /**
     * L'ancienne saisie directe « traitement déchets » est fermée : les issues de meunerie
     * ne sont transformées que par un ordre de fabrication d'aliment bétail (pelletisation),
     * qui gère recette, additifs, coût, validation et ensachage.
     */
    public function process()
    {
        flash('success', 'La transformation des issues se fait désormais par un ordre de fabrication d’aliment bétail.');
        redirect('pelletization');
    }

    public function store()
    {
        $this->refuse('La saisie directe est fermée. Créez un ordre de fabrication d’aliment bétail.', 'pelletization');
    }

    public function history()
    {
        $model = $this->model('Waste');

        $this->view('waste.history', [
            'title' => 'Anciens traitements des issues',
            'availableStock' => $model->totalAvailable(),
            'history' => $model->history(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function buffer($id)
    {
        $this->stockAction(function () use ($id) { $this->model('Waste')->sendToBuffer($id, Auth::user()); }, 'Stock placé en tampon.');
    }

    public function sell()
    {
        $this->refuse('Les issues de meunerie ne se vendent pas en vrac : elles sont réservées à la fabrication d’aliment bétail.', 'waste');
    }

    private function refuse(string $message, string $redirect)
    {
        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            $this->json(['ok' => false, 'message' => $message], 410);
            return;
        }
        flash('error', $message);
        redirect($redirect);
    }

    private function stockAction(callable $action, string $message)
    {
        $ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
        try {
            if (!verify_csrf($_POST['_token'] ?? '')) throw new RuntimeException('Votre session a expiré. Actualisez la page puis réessayez.');
            $action();
            if ($ajax) { $this->json(['ok'=>true,'message'=>$message,'refresh_url'=>base_url('waste')]); return; }
            flash('success', $message);
        } catch (Exception $e) {
            if ($ajax) { $this->json(['ok'=>false,'message'=>$e->getMessage()],422); return; }
            flash('error', $e->getMessage());
        }
        redirect('waste');
    }
}
