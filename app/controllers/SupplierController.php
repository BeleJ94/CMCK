<?php

class SupplierController extends Controller
{
    private $statuses = ['active', 'inactive', 'pending', 'validated', 'cancelled'];

    public function index()
    {
        $supplierModel = $this->model('Supplier');

        $this->view('suppliers.index', [
            'title' => 'Fournisseurs',
            'suppliers' => $supplierModel->active(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function create()
    {
        $this->view('suppliers.create', [
            'title' => 'Nouveau fournisseur',
            'supplier' => $this->old(),
            'errors' => flash('errors') ?: [],
        ], 'layouts.main');
    }

    public function store()
    {
        $this->ensureCsrf();
        $data = $this->input();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('errors', $errors);
            $_SESSION['old_supplier'] = $data;
            redirect('suppliers/create');
        }

        $id = $this->model('Supplier')->createSupplier($data);
        $this->model('ActivityLog')->record('create', 'fournisseurs', 'suppliers', $id, 'Creation fournisseur.', null, $data);
        flash('success', 'Fournisseur ajoute avec succes.');
        redirect('suppliers');
    }

    public function edit($id)
    {
        $supplier = $this->model('Supplier')->findActive($id);

        if (!$supplier) {
            flash('error', 'Fournisseur introuvable.');
            redirect('suppliers');
        }

        $this->view('suppliers.edit', [
            'title' => 'Modifier fournisseur',
            'supplier' => $this->old($supplier),
            'errors' => flash('errors') ?: [],
        ], 'layouts.main');
    }

    public function update($id)
    {
        $this->ensureCsrf();
        $supplierModel = $this->model('Supplier');
        $supplier = $supplierModel->findActive($id);

        if (!$supplier) {
            flash('error', 'Fournisseur introuvable.');
            redirect('suppliers');
        }

        $data = $this->input();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            flash('errors', $errors);
            $_SESSION['old_supplier'] = $data;
            redirect('suppliers/' . $id . '/edit');
        }

        $supplierModel->updateSupplier($id, $data);
        $this->model('ActivityLog')->record('update', 'fournisseurs', 'suppliers', $id, 'Modification fournisseur.', $supplier, $data);
        flash('success', 'Fournisseur modifie avec succes.');
        redirect('suppliers');
    }

    public function destroy($id)
    {
        $this->ensureCsrf();
        $supplierModel = $this->model('Supplier');
        $supplier = $supplierModel->findActive($id);
        $supplierModel->softDelete($id);
        $this->model('ActivityLog')->record('delete', 'fournisseurs', 'suppliers', $id, 'Suppression fournisseur.', $supplier ?: null, ['deleted_at' => date('Y-m-d H:i:s'), 'status' => 'inactive']);
        flash('success', 'Fournisseur supprime avec succes.');
        redirect('suppliers');
    }

    private function input()
    {
        return [
            'name' => trim($_POST['name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'rccm' => trim($_POST['rccm'] ?? ''),
            'id_nat' => trim($_POST['id_nat'] ?? ''),
            'status' => trim($_POST['status'] ?? 'active'),
        ];
    }

    private function validate(array $data)
    {
        $errors = [];

        if ($data['name'] === '' || strlen($data['name']) < 2) {
            $errors['name'] = 'Le nom du fournisseur est obligatoire.';
        }

        if ($data['phone'] !== '' && !preg_match('/^[0-9 +().-]{6,30}$/', $data['phone'])) {
            $errors['phone'] = 'Le telephone est invalide.';
        }

        if ($data['address'] === '') {
            $errors['address'] = 'L adresse est obligatoire.';
        }

        if ($data['rccm'] === '') {
            $errors['rccm'] = 'Le RCCM est obligatoire.';
        }

        if ($data['id_nat'] === '') {
            $errors['id_nat'] = 'L ID Nat est obligatoire.';
        }

        if (!in_array($data['status'], $this->statuses, true)) {
            $errors['status'] = 'Le statut selectionne est invalide.';
        }

        return $errors;
    }

    private function old(array $fallback = [])
    {
        Auth::start();
        $old = $_SESSION['old_supplier'] ?? null;
        unset($_SESSION['old_supplier']);

        return $old ?: array_merge([
            'name' => '',
            'phone' => '',
            'address' => '',
            'rccm' => '',
            'id_nat' => '',
            'status' => 'active',
        ], $fallback);
    }

    private function ensureCsrf()
    {
        if (!verify_csrf($_POST['_token'] ?? '')) {
            flash('error', 'Session expiree. Veuillez reessayer.');
            redirect('suppliers');
        }
    }
}
