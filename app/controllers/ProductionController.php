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
            'tolerance'=>$model->tolerance(),
        ], 'layouts.main');
    }

    public function create()
    {
        $model = $this->model('ProductionBatch');

        $this->view('production.create', [
            'title' => 'Nouvelle production',
            'pendingBatches' => $model->pendingForSelect(),
            'wasteTypes'=>$model->wasteTypes(),
            'production' => $this->old(),
            'errors' => flash('errors') ?: [],
            'tolerance'=>$model->tolerance(),
            'siteRequired'=>Auth::currentSiteId() === null,
        ], 'layouts.main');
    }

    public function store()
    {
        $this->ensureCsrf('production/create');
        $model = $this->model('ProductionBatch');
        $data = $this->input();
        $data['variance_tolerance_percent']=$model->tolerance();
        $errors = $this->validate($data, $model);

        if (!empty($errors)) {
            flash('errors', $errors);
            $_SESSION['old_production'] = $data;
            redirect('production/create');
        }

        try {
            $model->submitResults($data, Auth::user());
            flash('success', 'Résultats enregistrés. Le lot attend une validation indépendante.');
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

    public function validateBatch($id)
    {
        $this->ensureCsrf('production/'.$id);
        try{$this->model('ProductionBatch')->validateProduction(['production_batch_id'=>$id],Auth::user());flash('success','Production validée; farine et déchets sont disponibles.');}
        catch(Exception$e){flash('error',$e->getMessage());}
        redirect('production/'.$id);
    }

    public function updateTolerance()
    {
        $this->ensureCsrf('production');
        try{$this->model('ProductionBatch')->updateTolerance($_POST['tolerance_percent']??'',Auth::user());flash('success','Tolérance de production mise à jour.');}catch(Exception$e){flash('error',$e->getMessage());}
        redirect('production');
    }

    private function input()
    {
        return [
            'production_batch_id' => trim($_POST['production_batch_id'] ?? ''),
            'output_quantity_kg' => trim($_POST['output_quantity_kg'] ?? ''),
            'waste_quantity_kg' => trim($_POST['waste_quantity_kg'] ?? ''),
            'waste_lines'=>is_array($_POST['waste_lines']??null)?$_POST['waste_lines']:[],
            'variance_tolerance_percent'=>'2',
            'variance_justification'=>trim($_POST['variance_justification']??''),
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

        foreach (['output_quantity_kg' => 'quantite de farine'] as $field => $label) {
            if ($data[$field] === '' || !is_numeric($data[$field]) || (float) $data[$field] < 0) {
                $errors[$field] = 'La ' . $label . ' est obligatoire et positive.';
            }
        }

        foreach($data['waste_lines'] as$type=>$qty){if(!ctype_digit((string)$type)||!is_numeric($qty)||(float)$qty<0){$errors['waste_lines']='Les quantités de déchets doivent être positives.';}}
        if(!is_numeric($data['variance_tolerance_percent'])||(float)$data['variance_tolerance_percent']<0){$errors['variance_tolerance_percent']='Tolérance invalide.';}

        if ($data['ended_at'] === '') {
            $errors['ended_at'] = 'La date de production est obligatoire.';
        }

        return $errors;
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
            'waste_lines'=>[],
            'variance_tolerance_percent'=>'2',
            'variance_justification'=>'',
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
