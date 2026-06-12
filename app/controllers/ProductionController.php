<?php

class ProductionController extends Controller
{
    public function index()
    {
        $model = $this->model('ProductionBatch');

        $this->view('production.index', [
            'title' => 'Production farine',
            'batches' => $model->allDetailed(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function create()
    {
        $model = $this->model('ProductionBatch');

        $this->view('production.create', [
            'title' => 'Nouvelle production',
            'pendingBatches' => $model->pendingForSelect(),
            'production' => $this->old(),
            'errors' => flash('errors') ?: [],
        ], 'layouts.main');
    }

    public function store()
    {
        $this->ensureCsrf('production/create');
        $model = $this->model('ProductionBatch');
        $data = $this->input();
        $data = $this->withCalculatedWaste($data, $model);
        $errors = $this->validate($data, $model);

        if (!empty($errors)) {
            flash('errors', $errors);
            $_SESSION['old_production'] = $data;
            redirect('production/create');
        }

        try {
            $model->validateProduction($data, Auth::user());
            flash('success', 'Production farine validee avec succes.');
            redirect('production/' . $data['production_batch_id']);
        } catch (Exception $exception) {
            flash('error', $exception->getMessage());
            $_SESSION['old_production'] = $data;
            redirect('production/create');
        }
    }

    public function show($id)
    {
        $batch = $this->model('ProductionBatch')->findDetailed($id);

        if (!$batch) {
            flash('error', 'Lot de production introuvable.');
            redirect('production');
        }

        $this->view('production.show', [
            'title' => 'Detail production',
            'batch' => $batch,
            'yield' => $this->yieldRate($batch),
        ], 'layouts.main');
    }

    private function input()
    {
        return [
            'production_batch_id' => trim($_POST['production_batch_id'] ?? ''),
            'output_quantity_kg' => trim($_POST['output_quantity_kg'] ?? ''),
            'waste_quantity_kg' => trim($_POST['waste_quantity_kg'] ?? ''),
            'ended_at' => trim($_POST['ended_at'] ?? ''),
        ];
    }

    private function validate(array $data, ProductionBatch $model)
    {
        $errors = [];

        if ($data['production_batch_id'] === '' || !ctype_digit((string) $data['production_batch_id'])) {
            $errors['production_batch_id'] = 'Le lot de traitement est obligatoire.';
            return $errors;
        }

        $batch = $model->findDetailed($data['production_batch_id']);

        if (!$batch) {
            $errors['production_batch_id'] = 'Lot de traitement introuvable.';
            return $errors;
        }

        if ($batch['status'] === 'validated') {
            $errors['production_batch_id'] = 'Ce lot est deja valide.';
        }

        foreach (['output_quantity_kg' => 'quantite bon produit', 'waste_quantity_kg' => 'quantite dechets'] as $field => $label) {
            if ($data[$field] === '' || !is_numeric($data[$field]) || (float) $data[$field] < 0) {
                $errors[$field] = 'La ' . $label . ' est obligatoire et positive.';
            }
        }

        if (empty($errors['output_quantity_kg']) && empty($errors['waste_quantity_kg'])) {
            if ((float) $data['output_quantity_kg'] + (float) $data['waste_quantity_kg'] > (float) $batch['input_quantity_kg']) {
                $errors['output_quantity_kg'] = 'Bon produit + dechets ne doit pas depasser la quantite traitee.';
            }
        }

        if ($data['ended_at'] === '') {
            $errors['ended_at'] = 'La date de production est obligatoire.';
        }

        return $errors;
    }

    private function withCalculatedWaste(array $data, ProductionBatch $model)
    {
        if ($data['production_batch_id'] === '' || !ctype_digit((string) $data['production_batch_id']) || !is_numeric($data['output_quantity_kg'])) {
            return $data;
        }

        $batch = $model->findDetailed($data['production_batch_id']);

        if (!$batch) {
            return $data;
        }

        $treated = (float) $batch['input_quantity_kg'];
        $good = (float) $data['output_quantity_kg'];
        $data['waste_quantity_kg'] = (string) max($treated - $good, 0);

        return $data;
    }

    private function old()
    {
        Auth::start();
        $old = $_SESSION['old_production'] ?? null;
        unset($_SESSION['old_production']);

        return $old ?: [
            'production_batch_id' => '',
            'output_quantity_kg' => '',
            'waste_quantity_kg' => '',
            'ended_at' => date('Y-m-d\TH:i'),
        ];
    }

    private function yieldRate(array $batch)
    {
        if ((float) $batch['input_quantity_kg'] <= 0) {
            return 0;
        }

        return ((float) $batch['output_quantity_kg'] / (float) $batch['input_quantity_kg']) * 100;
    }

    private function ensureCsrf($redirect)
    {
        if (!verify_csrf($_POST['_token'] ?? '')) {
            flash('error', 'Session expiree. Veuillez reessayer.');
            redirect($redirect);
        }
    }
}
