<?php

class WasteController extends Controller
{
    public function index()
    {
        $model = $this->model('Waste');

        $this->view('waste.index', [
            'title' => 'Déchets et coproduits',
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
        $filename='dagril-stocks-dechets-'.date('Ymd-His');
        if ($format==='excel') {
            $content=$service->xlsx($lines,$summary);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="'.$filename.'.xlsx"');
            echo $content;
            return;
        }
        $html=$this->renderViewToString('waste.export',['lines'=>$lines,'summary'=>$summary,'service'=>$service]);
        (new PdfService())->stream('Déchets et coproduits', $html, $filename.'.pdf', 'landscape');
    }

    public function process()
    {
        $model = $this->model('Waste');

        $this->view('waste.process', [
            'title' => 'Traitement dechets',
            'availableStock' => $model->totalAvailable(),
            'machines' => $model->wasteMachines(),
            'processing' => $this->old(),
            'errors' => flash('errors') ?: [],
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function store()
    {
        $this->ensureCsrf('waste/process');

        $model = $this->model('Waste');
        $data = $this->input();
        $errors = $this->validate($data, $model);

        if (!empty($errors)) {
            flash('errors', $errors);
            $_SESSION['old_waste_processing'] = $data;
            redirect('waste/process');
        }

        try {
            $model->processWaste($data, Auth::user());
            flash('success', 'Traitement dechets valide avec succes.');
            redirect('waste/history');
        } catch (Exception $exception) {
            flash('error', $exception->getMessage());
            $_SESSION['old_waste_processing'] = $data;
            redirect('waste/process');
        }
    }

    public function history()
    {
        $model = $this->model('Waste');

        $this->view('waste.history', [
            'title' => 'Historique dechets',
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
        $this->stockAction(function () {
            $quantity = $_POST['quantity_kg'] ?? '';
            $price = $_POST['unit_price'] ?? '';
            $customer = trim($_POST['customer_name'] ?? '');
            if (!is_numeric($quantity) || !is_finite((float)$quantity) || (float)$quantity < 0.001) throw new RuntimeException('Saisissez une quantité positive, au minimum 0,001 kg.');
            if (!is_numeric($price) || !is_finite((float)$price) || (float)$price < 0) throw new RuntimeException('Saisissez un prix unitaire positif ou nul.');
            if ($customer === '' || mb_strlen($customer) > 190) throw new RuntimeException('Renseignez le nom du client (190 caractères maximum).');
            $this->model('Waste')->sell(['waste_stock_id'=>$_POST['waste_stock_id']??'', 'quantity_kg'=>$quantity, 'customer_name'=>$customer, 'unit_price'=>$price], Auth::user());
        }, 'Vente brute enregistrée.');
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

    private function input()
    {
        return [
            'machine_id' => trim($_POST['machine_id'] ?? ''),
            'input_quantity_kg' => trim($_POST['input_quantity_kg'] ?? ''),
            'output_quantity_kg' => trim($_POST['output_quantity_kg'] ?? ''),
            'processed_at' => trim($_POST['processed_at'] ?? ''),
        ];
    }

    private function validate(array $data, Waste $model)
    {
        $errors = [];
        $available = $model->totalAvailable();

        if ($data['machine_id'] === '' || !ctype_digit((string) $data['machine_id'])) {
            $errors['machine_id'] = 'La machine dechets est obligatoire.';
        }

        if ($data['input_quantity_kg'] === '' || !is_numeric($data['input_quantity_kg']) || (float) $data['input_quantity_kg'] <= 0) {
            $errors['input_quantity_kg'] = 'La quantite dechets traitee est obligatoire et positive.';
        } elseif ((float) $data['input_quantity_kg'] > $available) {
            $errors['input_quantity_kg'] = 'Impossible de traiter plus que le stock dechets disponible.';
        }

        if ($data['output_quantity_kg'] === '' || !is_numeric($data['output_quantity_kg']) || (float) $data['output_quantity_kg'] < 0) {
            $errors['output_quantity_kg'] = 'La quantite aliment betail produite est obligatoire et positive.';
        }

        if (empty($errors['input_quantity_kg']) && empty($errors['output_quantity_kg'])) {
            if ((float) $data['output_quantity_kg'] > (float) $data['input_quantity_kg']) {
                $errors['output_quantity_kg'] = 'L aliment betail produit ne peut pas depasser la quantite dechets traitee.';
            }
        }

        if ($data['processed_at'] === '') {
            $errors['processed_at'] = 'La date de traitement est obligatoire.';
        }

        return $errors;
    }

    private function old()
    {
        Auth::start();
        $old = $_SESSION['old_waste_processing'] ?? null;
        unset($_SESSION['old_waste_processing']);

        return $old ?: [
            'machine_id' => '',
            'input_quantity_kg' => '',
            'output_quantity_kg' => '',
            'processed_at' => date('Y-m-d\TH:i'),
        ];
    }

    private function ensureCsrf($redirect)
    {
        if (!verify_csrf($_POST['_token'] ?? '')) {
            flash('error', 'Session expiree. Veuillez reessayer.');
            redirect($redirect);
        }
    }
}
