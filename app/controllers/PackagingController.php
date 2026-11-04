<?php

class PackagingController extends Controller
{
    public function index()
    {
        $model = $this->model('Packaging');

        $this->view('packaging.index', [
            'title' => 'Conditionnement',
            'bagFormats' => $model->bagFormats(),
            'workshopStocks' => $model->workshopStocks(),
            'packaging' => $this->old(),
            'errors' => [],
            'availableProducts' => $model->availableProducts(),
            'history' => $model->history(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function create()
    {
        $model = $this->model('Packaging');

        $this->view('packaging.create', [
            'title' => 'Nouvel emballage',
            'availableProducts' => $model->availableProducts(),
            'bagFormats' => $model->bagFormats(),
            'workshopStocks' => $model->workshopStocks(),
            'packaging' => $this->old(),
            'errors' => flash('errors') ?: [],
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function store()
    {
        $this->ensureCsrf('packaging/create');

        $model = $this->model('Packaging');
        $data = $this->input();
        $errors = $this->validate($data, $model);

        if (!empty($errors)) {
            if ($this->isAjax()) return $this->json(['ok'=>false,'message'=>implode(' ', $errors)], 422);
            flash('errors', $errors);
            $_SESSION['old_packaging'] = $data;
            redirect('packaging/create');
        }

        try {
            $model->createPackaging($data, Auth::user());
            if ($this->isAjax()) return $this->json(['ok'=>true,'message'=>'Conditionnement enregistré. Les stocks ont été mis à jour.','refresh_url'=>base_url('packaging')]);
            flash('success', 'Emballage valide avec succes.');
            redirect('packaging/history');
        } catch (Exception $exception) {
            if ($this->isAjax()) return $this->json(['ok'=>false,'message'=>$exception instanceof PDOException ? 'Enregistrement impossible. Vérifiez les données puis réessayez.' : $exception->getMessage()], 422);
            flash('error', $exception->getMessage());
            $_SESSION['old_packaging'] = $data;
            redirect('packaging/create');
        }
    }

    public function history()
    {
        $model = $this->model('Packaging');

        $this->view('packaging.history', [
            'title' => 'Historique emballage',
            'history' => $model->history(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'layouts.main');
    }

    private function input()
    {
        return [
            'stock_key' => trim($_POST['stock_key'] ?? ''),
            'bag_format_id' => trim($_POST['bag_format_id'] ?? ''),
            'packaging_item_id'=>trim($_POST['packaging_item_id']??''),
            'bags_count' => trim($_POST['bags_count'] ?? ''),
            'packaged_at' => trim($_POST['packaged_at'] ?? ''),
        ];
    }

    private function validate(array $data, Packaging $model)
    {
        $errors = [];

        if (Packaging::parseStockKey($data['stock_key']) === null) {
            $errors['stock_key'] = 'Choisissez la farine en vrac à conditionner.';
        }

        if ($data['packaging_item_id'] === '' || !ctype_digit((string) $data['packaging_item_id'])) {
            $errors['packaging_item_id'] = 'Le format de sac vide est obligatoire.';
        }

        if ($data['bags_count'] === '' || !ctype_digit((string) $data['bags_count']) || (int) $data['bags_count'] <= 0) {
            $errors['bags_count'] = 'Le nombre de sacs est obligatoire et positif.';
        }

        $date = DateTime::createFromFormat('!Y-m-d\TH:i', $data['packaged_at']);
        if (!$date || $date->format('Y-m-d\TH:i') !== $data['packaged_at']) {
            $errors['packaged_at'] = 'Saisissez une date et une heure valides pour le conditionnement.';
        }

        if (empty($errors['stock_key']) && empty($errors['packaging_item_id']) && empty($errors['bags_count'])) {
            $batch = $model->findAvailableProduct($data['stock_key']);
            $formats=$model->bagFormats();$format=null;foreach($formats as$f){if((string)$f['packaging_item_id']===(string)$data['packaging_item_id']){$format=$f;break;}}

            if (!$batch) {
                $errors['stock_key'] = 'Aucune farine en vrac disponible pour ce produit sur ce site.';
            }

            if (!$format) {
                $errors['bag_format_id'] = 'Format sac introuvable.';
            }

            if ($batch && $format) {
                $totalWeight = (float) $format['weight_kg'] * (int) $data['bags_count'];
                if ($totalWeight > (float) $batch['available_quantity_kg']) {
                    $errors['bags_count'] = sprintf('Impossible d’emballer plus que la farine en vrac disponible (%s kg au total).', number_format((float) $batch['available_quantity_kg'], 0, ',', ' '));
                }
            }
        }

        return $errors;
    }

    private function old()
    {
        Auth::start();
        $old = $_SESSION['old_packaging'] ?? null;
        unset($_SESSION['old_packaging']);

        return $old ?: [
            'stock_key' => '',
            'bag_format_id' => '',
            'packaging_item_id'=>'',
            'bags_count' => '',
            'packaged_at' => date('Y-m-d\TH:i'),
        ];
    }

    private function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    private function ensureCsrf($redirect)
    {
        if (!verify_csrf($_POST['_token'] ?? '')) {
            if ($this->isAjax()) { $this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page puis réessayez.'],419); exit; }
            flash('error', 'Session expiree. Veuillez reessayer.');
            redirect($redirect);
        }
    }
}
