<?php

class PackagingController extends Controller
{
    public function index()
    {
        $model = $this->model('Packaging');

        $this->view('packaging.index', [
            'title' => 'Emballage',
            'availableBatches' => $model->availableBatches(),
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
            'availableBatches' => $model->availableBatches(),
            'bagFormats' => $model->bagFormats(),
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
            flash('errors', $errors);
            $_SESSION['old_packaging'] = $data;
            redirect('packaging/create');
        }

        try {
            $model->createPackaging($data, Auth::user());
            flash('success', 'Emballage valide avec succes.');
            redirect('packaging/history');
        } catch (Exception $exception) {
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
            'production_batch_id' => trim($_POST['production_batch_id'] ?? ''),
            'bag_format_id' => trim($_POST['bag_format_id'] ?? ''),
            'bags_count' => trim($_POST['bags_count'] ?? ''),
            'packaged_at' => trim($_POST['packaged_at'] ?? ''),
        ];
    }

    private function validate(array $data, Packaging $model)
    {
        $errors = [];

        if ($data['production_batch_id'] === '' || !ctype_digit((string) $data['production_batch_id'])) {
            $errors['production_batch_id'] = 'Le lot production est obligatoire.';
        }

        if ($data['bag_format_id'] === '' || !ctype_digit((string) $data['bag_format_id'])) {
            $errors['bag_format_id'] = 'Le format sac est obligatoire.';
        }

        if ($data['bags_count'] === '' || !ctype_digit((string) $data['bags_count']) || (int) $data['bags_count'] <= 0) {
            $errors['bags_count'] = 'Le nombre de sacs est obligatoire et positif.';
        }

        if ($data['packaged_at'] === '') {
            $errors['packaged_at'] = 'La date emballage est obligatoire.';
        }

        if (empty($errors['production_batch_id']) && empty($errors['bag_format_id']) && empty($errors['bags_count'])) {
            $batch = $model->findBatch($data['production_batch_id']);
            $format = $model->findBagFormat($data['bag_format_id']);

            if (!$batch) {
                $errors['production_batch_id'] = 'Lot production introuvable ou sans quantite disponible.';
            }

            if (!$format) {
                $errors['bag_format_id'] = 'Format sac introuvable.';
            }

            if ($batch && $format) {
                $totalWeight = (float) $format['weight_kg'] * (int) $data['bags_count'];
                if ($totalWeight > (float) $batch['available_quantity_kg']) {
                    $errors['bags_count'] = 'Impossible d emballer plus que la quantite disponible.';
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
            'production_batch_id' => '',
            'bag_format_id' => '',
            'bags_count' => '',
            'packaged_at' => date('Y-m-d\TH:i'),
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
