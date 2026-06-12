<?php

class TruckController extends Controller
{
    private $statuses = ['active', 'inactive', 'pending', 'validated', 'cancelled'];

    public function index()
    {
        $truckModel = $this->model('Truck');

        $this->view('trucks.index', [
            'title' => 'Camions',
            'trucks' => $truckModel->active(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function create()
    {
        $truckModel = $this->model('Truck');

        $this->view('trucks.create', [
            'title' => 'Nouveau camion',
            'truck' => $this->old(),
            'suppliers' => $truckModel->suppliersForSelect(),
            'errors' => flash('errors') ?: [],
        ], 'layouts.main');
    }

    public function store()
    {
        $this->ensureCsrf();
        $truckModel = $this->model('Truck');
        $data = $this->input();
        $errors = $this->validate($data, $truckModel);

        if (!empty($errors)) {
            flash('errors', $errors);
            $_SESSION['old_truck'] = $data;
            redirect('trucks/create');
        }

        $id = $truckModel->createTruck($data);
        $this->model('ActivityLog')->record('create', 'camions', 'trucks', $id, 'Creation camion.', null, $data);
        flash('success', 'Camion ajoute avec succes.');
        redirect('trucks');
    }

    public function edit($id)
    {
        $truckModel = $this->model('Truck');
        $truck = $truckModel->findActive($id);

        if (!$truck) {
            flash('error', 'Camion introuvable.');
            redirect('trucks');
        }

        $this->view('trucks.edit', [
            'title' => 'Modifier camion',
            'truck' => $this->old($truck),
            'suppliers' => $truckModel->suppliersForSelect(),
            'errors' => flash('errors') ?: [],
        ], 'layouts.main');
    }

    public function update($id)
    {
        $this->ensureCsrf();
        $truckModel = $this->model('Truck');
        $truck = $truckModel->findActive($id);

        if (!$truck) {
            flash('error', 'Camion introuvable.');
            redirect('trucks');
        }

        $data = $this->input();
        $errors = $this->validate($data, $truckModel, $id);

        if (!empty($errors)) {
            flash('errors', $errors);
            $_SESSION['old_truck'] = $data;
            redirect('trucks/' . $id . '/edit');
        }

        $truckModel->updateTruck($id, $data);
        $this->model('ActivityLog')->record('update', 'camions', 'trucks', $id, 'Modification camion.', $truck, $data);
        flash('success', 'Camion modifie avec succes.');
        redirect('trucks');
    }

    public function destroy($id)
    {
        $this->ensureCsrf();
        $truckModel = $this->model('Truck');
        $truck = $truckModel->findActive($id);
        $truckModel->softDelete($id);
        $this->model('ActivityLog')->record('delete', 'camions', 'trucks', $id, 'Suppression camion.', $truck ?: null, ['deleted_at' => date('Y-m-d H:i:s'), 'status' => 'inactive']);
        flash('success', 'Camion supprime avec succes.');
        redirect('trucks');
    }

    private function input()
    {
        return [
            'supplier_id' => trim($_POST['supplier_id'] ?? ''),
            'plate_number' => strtoupper(trim($_POST['plate_number'] ?? '')),
            'driver_name' => trim($_POST['driver_name'] ?? ''),
            'driver_phone' => trim($_POST['driver_phone'] ?? ''),
            'status' => trim($_POST['status'] ?? 'active'),
        ];
    }

    private function validate(array $data, Truck $truckModel, $ignoreId = null)
    {
        $errors = [];

        if ($data['plate_number'] === '' || strlen($data['plate_number']) < 3) {
            $errors['plate_number'] = 'La plaque est obligatoire.';
        } elseif ($truckModel->plateExists($data['plate_number'], $ignoreId)) {
            $errors['plate_number'] = 'Cette plaque existe deja.';
        }

        if ($data['driver_name'] === '' || strlen($data['driver_name']) < 2) {
            $errors['driver_name'] = 'Le nom du chauffeur est obligatoire.';
        }

        if ($data['driver_phone'] !== '' && !preg_match('/^[0-9 +().-]{6,30}$/', $data['driver_phone'])) {
            $errors['driver_phone'] = 'Le telephone chauffeur est invalide.';
        }

        if ($data['supplier_id'] === '' || !ctype_digit((string) $data['supplier_id'])) {
            $errors['supplier_id'] = 'Le fournisseur associe est obligatoire.';
        }

        if (!in_array($data['status'], $this->statuses, true)) {
            $errors['status'] = 'Le statut selectionne est invalide.';
        }

        return $errors;
    }

    private function old(array $fallback = [])
    {
        Auth::start();
        $old = $_SESSION['old_truck'] ?? null;
        unset($_SESSION['old_truck']);

        return $old ?: array_merge([
            'supplier_id' => '',
            'plate_number' => '',
            'driver_name' => '',
            'driver_phone' => '',
            'status' => 'active',
        ], $fallback);
    }

    private function ensureCsrf()
    {
        if (!verify_csrf($_POST['_token'] ?? '')) {
            flash('error', 'Session expiree. Veuillez reessayer.');
            redirect('trucks');
        }
    }
}
