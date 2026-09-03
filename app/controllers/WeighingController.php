<?php

class WeighingController extends Controller
{
    public function index()
    {
        $model = $this->model('Weighing');

        $this->view('weighings.index', [
            'title' => 'Pont-bascule',
            'weighings' => $model->allWithRelations(),
            'pending' => $model->pending(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function entry()
    {
        $model = $this->model('Weighing');

        $this->view('weighings.entry', [
            'title' => 'Pesee entree',
            'suppliers' => $model->suppliers(),
            'products' => $model->products(),
            'transports' => $model->availableTransports(),
            'entry' => $this->oldEntry(),
            'errors' => flash('errors') ?: [],
        ], 'layouts.main');
    }

    public function storeEntry()
    {
        $this->ensureCsrf('weighings/entry');
        $data = [
            'transport_id' => trim($_POST['transport_id'] ?? ''),
            'poids_brut' => trim($_POST['poids_brut'] ?? ''),
        ];
        $errors = $this->validateEntry($data);

        if (!empty($errors)) {
            flash('errors', $errors);
            $_SESSION['old_weighing_entry'] = $data;
            redirect('weighings/entry');
        }

        try{$this->model('Weighing')->createEntry($data, Auth::user());flash('success', 'Pesée brute enregistrée. Aucun stock silo n’a été modifié.');}
        catch(Exception $exception){flash('error',$exception->getMessage());redirect('weighings/entry');}
        redirect('weighings');
    }

    public function exitList()
    {
        $model = $this->model('Weighing');

        $this->view('weighings.exit', [
            'title' => 'Pesee sortie',
            'pending' => $model->pending(),
            'weighing' => null,
            'silos' => [],
            'exit' => $this->oldExit(),
            'errors' => flash('errors') ?: [],
        ], 'layouts.main');
    }

    public function exitForm($id)
    {
        $model = $this->model('Weighing');
        $weighing = $model->findDetailed($id);

        if (!$weighing) {
            flash('error', 'Pesee introuvable.');
            redirect('weighings/exit');
        }

        if ($weighing['status'] === 'validated' && !Auth::hasRole(['administrateur'])) {
            flash('error', 'Cette pesee est deja validee et non modifiable.');
            redirect('weighings/' . $id . '/ticket');
        }

        $this->view('weighings.exit', [
            'title' => 'Pesee sortie',
            'pending' => $model->pending(),
            'weighing' => $weighing,
            'silos' => $model->silos(),
            'exit' => $this->oldExit(),
            'errors' => flash('errors') ?: [],
        ], 'layouts.main');
    }

    public function validateExit($id)
    {
        $this->ensureCsrf('weighings/' . $id . '/exit');
        $model = $this->model('Weighing');
        $weighing = $model->findDetailed($id);

        if (!$weighing) {
            flash('error', 'Pesee introuvable.');
            redirect('weighings/exit');
        }

        if ($weighing['status'] === 'validated' && !Auth::hasRole(['administrateur'])) {
            flash('error', 'Pesee validee non modifiable sans admin.');
            redirect('weighings/' . $id . '/ticket');
        }

        $data = [
            'poids_tare' => trim($_POST['poids_tare'] ?? ''),
            'silo_id' => trim($_POST['silo_id'] ?? ''),
            'humidity_percent'=>trim($_POST['humidity_percent']??''),'impurities_percent'=>trim($_POST['impurities_percent']??''),
            'weight_tolerance_percent'=>trim($_POST['weight_tolerance_percent']??'2'),'max_humidity_percent'=>trim($_POST['max_humidity_percent']??'14'),
            'max_impurities_percent'=>trim($_POST['max_impurities_percent']??'2'),'quality_notes'=>trim($_POST['quality_notes']??''),'decision'=>trim($_POST['decision']??'accept'),
        ];
        $errors = $this->validateExitData($data, $weighing);

        if (!empty($errors)) {
            flash('errors', $errors);
            $_SESSION['old_weighing_exit'] = $data;
            redirect('weighings/' . $id . '/exit');
        }

        try {
            $model->validateExit($id, $data, Auth::user());
            flash('success', 'Livraison validee et stock silo mis a jour.');
            redirect('weighings/' . $id . '/ticket');
        } catch (Exception $exception) {
            flash('error', $exception->getMessage());
            redirect('weighings/' . $id . '/exit');
        }
    }

    public function ticket($id)
    {
        $weighing = $this->model('Weighing')->findDetailed($id);

        if (!$weighing) {
            flash('error', 'Ticket introuvable.');
            redirect('weighings');
        }

        if (($_GET['export'] ?? '') === 'pdf') {
            $html = $this->renderViewToString('weighings.ticket', [
                'title' => 'Ticket de pesee',
                'weighing' => $weighing,
                'pdfMode' => true,
            ]);
            (new PdfService())->stream('Ticket de pesee', $html, 'ticket-pesee-' . $this->slug($weighing['official_document_number'] ?: $weighing['reference']) . '.pdf', 'portrait');
            return;
        }

        $this->view('weighings.ticket', [
            'title' => 'Ticket de pesee',
            'weighing' => $weighing,
        ], 'layouts.main');
    }

    public function progressReturn($id)
    {
        $this->ensureCsrf('weighings/'.$id.'/ticket');
        try{$this->model('Weighing')->progressReturn((int)$id,trim($_POST['return_action']??''),Auth::user());flash('success','Étape du retour enregistrée.');}
        catch(Exception$e){flash('error',$e->getMessage());}
        redirect('weighings/'.$id.'/ticket');
    }

    private function slug($value)
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value));
        return trim($slug, '-') ?: 'document';
    }

    private function validateEntry(array $data)
    {
        $errors = [];

        if ($data['transport_id'] === '' || !ctype_digit((string)$data['transport_id'])) {$errors['transport_id']='Un BT en transit est obligatoire.';}

        if ($data['poids_brut'] === '' || !is_numeric($data['poids_brut']) || (float) $data['poids_brut'] <= 0) {
            $errors['poids_brut'] = 'Le poids brut doit etre superieur a zero.';
        }

        return $errors;
    }

    private function validateExitData(array $data, array $weighing)
    {
        $errors = [];

        if ($data['poids_tare'] === '' || !is_numeric($data['poids_tare']) || (float) $data['poids_tare'] < 0) {
            $errors['poids_tare'] = 'Le poids tare est obligatoire.';
        } elseif ((float) $data['poids_tare'] > (float) $weighing['poids_brut']) {
            $errors['poids_tare'] = 'Le poids tare ne peut pas etre superieur au poids brut.';
        }

        if ($data['silo_id'] === '' || !ctype_digit((string) $data['silo_id'])) {
            $errors['silo_id'] = 'Le silo destination est obligatoire.';
        }
        foreach(['humidity_percent'=>'humidite','impurities_percent'=>'impuretes','weight_tolerance_percent'=>'tolerance','max_humidity_percent'=>'limite humidite','max_impurities_percent'=>'limite impuretes'] as $field=>$label){if($data[$field]===''||!is_numeric($data[$field])||(float)$data[$field]<0){$errors[$field]='La valeur '.$label.' est invalide.';}}
        if(!in_array($data['decision'],['accept','reject'],true)){$errors['decision']='Décision invalide.';}
        if($data['decision']==='reject'&&$data['quality_notes']===''){$errors['quality_notes']='Le motif du refus est obligatoire.';}

        return $errors;
    }

    private function oldEntry()
    {
        Auth::start();
        $old = $_SESSION['old_weighing_entry'] ?? null;
        unset($_SESSION['old_weighing_entry']);

        return $old ?: [
            'transport_id' => '',
            'poids_brut' => '',
        ];
    }

    private function oldExit()
    {
        Auth::start();
        $old = $_SESSION['old_weighing_exit'] ?? null;
        unset($_SESSION['old_weighing_exit']);

        return $old ?: ['poids_tare'=>'','silo_id'=>'','humidity_percent'=>'','impurities_percent'=>'','weight_tolerance_percent'=>'2','max_humidity_percent'=>'14','max_impurities_percent'=>'2','quality_notes'=>'','decision'=>'accept'];
    }

    private function ensureCsrf($redirect)
    {
        if (!verify_csrf($_POST['_token'] ?? '')) {
            flash('error', 'Session expiree. Veuillez reessayer.');
            redirect($redirect);
        }
    }
}
