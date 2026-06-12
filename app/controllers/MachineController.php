<?php

class MachineController extends Controller
{
    private $statuses = ['active', 'inactive', 'pending', 'validated', 'cancelled'];
    private $types = ['main', 'waste'];

    public function index()
    {
        $this->view('machines.index', [
            'title' => 'Machines',
            'machines' => $this->model('Machine')->allWithPerformance(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function create()
    {
        $this->view('machines.create', [
            'title' => 'Nouvelle machine',
            'machine' => $this->old(),
            'errors' => flash('errors') ?: [],
        ], 'layouts.main');
    }

    public function store()
    {
        $this->ensureCsrf('machines/create');
        $data = $this->input();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('errors', $errors);
            $_SESSION['old_machine'] = $data;
            redirect('machines/create');
        }

        $id = $this->model('Machine')->createMachine($data);
        $this->model('ActivityLog')->record('create', 'machines', 'machines', $id, 'Creation machine.', null, $data);
        flash('success', 'Machine ajoutee avec succes.');
        redirect('machines');
    }

    public function edit($id)
    {
        $machine = $this->model('Machine')->findActive($id);

        if (!$machine) {
            flash('error', 'Machine introuvable.');
            redirect('machines');
        }

        $this->view('machines.edit', [
            'title' => 'Modifier machine',
            'machine' => $this->old($machine),
            'errors' => flash('errors') ?: [],
        ], 'layouts.main');
    }

    public function update($id)
    {
        $this->ensureCsrf('machines/' . $id . '/edit');
        $machineModel = $this->model('Machine');
        $machine = $machineModel->findActive($id);

        if (!$machine) {
            flash('error', 'Machine introuvable.');
            redirect('machines');
        }

        $data = $this->input();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('errors', $errors);
            $_SESSION['old_machine'] = $data;
            redirect('machines/' . $id . '/edit');
        }

        $machineModel->updateMachine($id, $data);
        $this->model('ActivityLog')->record('update', 'machines', 'machines', $id, 'Modification machine.', $machine, $data);
        flash('success', 'Machine modifiee avec succes.');
        redirect('machines');
    }

    public function toggle($id)
    {
        $this->ensureCsrf('machines');
        $machineModel = $this->model('Machine');
        $machine = $machineModel->findActive($id);
        $machineModel->toggleStatus($id);
        $updated = $machineModel->findActive($id);
        $this->model('ActivityLog')->record('update', 'machines', 'machines', $id, 'Changement statut machine.', $machine ?: null, $updated ?: null);
        flash('success', 'Statut machine mis a jour.');
        redirect('machines');
    }

    private function input()
    {
        return [
            'name' => trim($_POST['name'] ?? ''),
            'machine_type' => trim($_POST['machine_type'] ?? 'main'),
            'capacity_kg_hour' => trim($_POST['capacity_kg_hour'] ?? ''),
            'status' => trim($_POST['status'] ?? 'active'),
        ];
    }

    private function validate(array $data)
    {
        $errors = [];

        if ($data['name'] === '' || strlen($data['name']) < 2) {
            $errors['name'] = 'Le nom de la machine est obligatoire.';
        }

        if (!in_array($data['machine_type'], $this->types, true)) {
            $errors['machine_type'] = 'Le type machine est invalide.';
        }

        if ($data['capacity_kg_hour'] !== '' && (!is_numeric($data['capacity_kg_hour']) || (float) $data['capacity_kg_hour'] < 0)) {
            $errors['capacity_kg_hour'] = 'La capacite horaire doit etre positive.';
        }

        if (!in_array($data['status'], $this->statuses, true)) {
            $errors['status'] = 'Le statut selectionne est invalide.';
        }

        return $errors;
    }

    private function old(array $fallback = [])
    {
        Auth::start();
        $old = $_SESSION['old_machine'] ?? null;
        unset($_SESSION['old_machine']);

        return $old ?: array_merge([
            'name' => '',
            'machine_type' => 'main',
            'capacity_kg_hour' => '',
            'status' => 'active',
        ], $fallback);
    }

    private function ensureCsrf($redirect)
    {
        if (!verify_csrf($_POST['_token'] ?? '')) {
            flash('error', 'Session expiree. Veuillez reessayer.');
            redirect($redirect);
        }
    }
}
