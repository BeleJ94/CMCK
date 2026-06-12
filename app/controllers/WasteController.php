<?php

class WasteController extends Controller
{
    public function index()
    {
        $model = $this->model('Waste');

        $this->view('waste.index', [
            'title' => 'Dechets',
            'availableStock' => $model->totalAvailable(),
            'stockLines' => $model->stockLines(),
            'history' => $model->history(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'layouts.main');
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
