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
            'entry' => $this->oldEntry(),
            'errors' => flash('errors') ?: [],
        ], 'layouts.main');
    }

    public function storeEntry()
    {
        $this->ensureCsrf('weighings/entry');
        $data = [
            'supplier_id' => trim($_POST['supplier_id'] ?? ''),
            'truck_plate_number' => strtoupper(trim($_POST['truck_plate_number'] ?? '')),
            'driver_name' => trim($_POST['driver_name'] ?? ''),
            'driver_phone' => trim($_POST['driver_phone'] ?? ''),
            'product_id' => trim($_POST['product_id'] ?? ''),
            'poids_brut' => trim($_POST['poids_brut'] ?? ''),
        ];
        $errors = $this->validateEntry($data);

        if (!empty($errors)) {
            flash('errors', $errors);
            $_SESSION['old_weighing_entry'] = $data;
            redirect('weighings/entry');
        }

        $this->model('Weighing')->createEntry($data, Auth::user());
        flash('success', 'Pesee entree enregistree. Camion en attente de dechargement.');
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
            (new PdfService())->stream('Ticket de pesee', $html, 'ticket-pesee-' . $this->slug($weighing['reference']) . '.pdf', 'portrait');
            return;
        }

        $this->view('weighings.ticket', [
            'title' => 'Ticket de pesee',
            'weighing' => $weighing,
        ], 'layouts.main');
    }

    private function slug($value)
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value));
        return trim($slug, '-') ?: 'document';
    }

    private function validateEntry(array $data)
    {
        $errors = [];

        foreach (['supplier_id' => 'fournisseur', 'product_id' => 'produit'] as $field => $label) {
            if ($data[$field] === '' || !ctype_digit((string) $data[$field])) {
                $errors[$field] = 'Le champ ' . $label . ' est obligatoire.';
            }
        }

        if ($data['truck_plate_number'] === '') {
            $errors['truck_plate_number'] = 'La plaque du camion est obligatoire.';
        } elseif (strlen($data['truck_plate_number']) > 50) {
            $errors['truck_plate_number'] = 'La plaque du camion ne doit pas depasser 50 caracteres.';
        }

        if ($data['driver_name'] !== '' && strlen($data['driver_name']) > 150) {
            $errors['driver_name'] = 'Le nom du chauffeur ne doit pas depasser 150 caracteres.';
        }

        if ($data['driver_phone'] !== '' && strlen($data['driver_phone']) > 50) {
            $errors['driver_phone'] = 'Le telephone du chauffeur ne doit pas depasser 50 caracteres.';
        }

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

        return $errors;
    }

    private function oldEntry()
    {
        Auth::start();
        $old = $_SESSION['old_weighing_entry'] ?? null;
        unset($_SESSION['old_weighing_entry']);

        return $old ?: [
            'supplier_id' => '',
            'truck_plate_number' => '',
            'driver_name' => '',
            'driver_phone' => '',
            'product_id' => '',
            'poids_brut' => '',
        ];
    }

    private function oldExit()
    {
        Auth::start();
        $old = $_SESSION['old_weighing_exit'] ?? null;
        unset($_SESSION['old_weighing_exit']);

        return $old ?: ['poids_tare' => '', 'silo_id' => ''];
    }

    private function ensureCsrf($redirect)
    {
        if (!verify_csrf($_POST['_token'] ?? '')) {
            flash('error', 'Session expiree. Veuillez reessayer.');
            redirect($redirect);
        }
    }
}
