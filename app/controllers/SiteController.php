<?php

class SiteController extends Controller
{
    public function index()
    {
        $model = $this->model('Site');
        $this->view('sites.index', [
            'title' => 'Administration multi-sites',
            'sites' => $model->allDetailed(),
            'types' => $model->types(),
            'locations' => $model->referenceRows('storage_locations'),
            'costCenters' => $model->referenceRows('cost_centers'),
            'units' => $model->referenceRows('operational_units'),
            'users' => $model->usersWithAssignments(),
            'activeSites' => $model->activeSites(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function create()
    {
        $model = $this->model('Site');
        $this->view('sites.form', [
            'title' => 'Nouveau site', 'site' => $this->old(), 'types' => $model->types(),
            'errors' => flash('errors') ?: [], 'action' => base_url('sites'), 'isEdit' => false,
        ], 'layouts.main');
    }

    public function store()
    {
        $this->ensureCsrf('sites/create');
        $model = $this->model('Site');
        $data = $this->siteInput();
        $errors = $this->validateSite($data, $model);
        if ($errors) {
            flash('errors', $errors); $_SESSION['old_site'] = $data; redirect('sites/create');
        }
        $model->createSite($data, Auth::user());
        flash('success', 'Site cree avec succes.');
        redirect('sites');
    }

    public function edit($id)
    {
        $model = $this->model('Site');
        $site = $model->findDetailed($id);
        if (!$site) { flash('error', 'Site introuvable.'); redirect('sites'); }
        $this->view('sites.form', [
            'title' => 'Modifier site', 'site' => $this->old($site), 'types' => $model->types(),
            'errors' => flash('errors') ?: [], 'action' => base_url('sites/' . $id . '/update'), 'isEdit' => true,
        ], 'layouts.main');
    }

    public function update($id)
    {
        $this->ensureCsrf('sites/' . $id . '/edit');
        $model = $this->model('Site');
        $data = $this->siteInput();
        $errors = $this->validateSite($data, $model, $id);
        if ($errors) {
            flash('errors', $errors); $_SESSION['old_site'] = $data; redirect('sites/' . $id . '/edit');
        }
        $model->updateSite($id, $data, Auth::user());
        flash('success', 'Site modifie avec succes.');
        redirect('sites');
    }

    public function storeReference($type)
    {
        $this->ensureCsrf('sites');
        $tables = ['location' => 'storage_locations', 'cost-center' => 'cost_centers', 'unit' => 'operational_units'];
        if ($type === 'site-type') {
            $code = strtoupper(trim($_POST['code'] ?? '')); $name = trim($_POST['name'] ?? '');
            if ($code === '' || $name === '') { flash('error', 'Code et nom du type sont obligatoires.'); redirect('sites'); }
            try { $this->model('Site')->createType(['code' => $code, 'name' => $name], Auth::user()); flash('success', 'Type de site ajoute.'); }
            catch (Exception $exception) { flash('error', $exception->getMessage()); }
            redirect('sites');
        }
        if (!isset($tables[$type])) { http_response_code(404); return; }
        $data = [
            'site_id' => trim($_POST['site_id'] ?? ''), 'code' => strtoupper(trim($_POST['code'] ?? '')),
            'name' => trim($_POST['name'] ?? ''), 'status' => 'active',
            'location_type' => trim($_POST['location_type'] ?? 'warehouse'),
            'cost_center_id' => trim($_POST['cost_center_id'] ?? ''),
        ];
        if (!ctype_digit($data['site_id']) || $data['code'] === '' || $data['name'] === '') {
            flash('error', 'Site, code et nom sont obligatoires.'); redirect('sites');
        }
        try {
            $this->model('Site')->createReference($tables[$type], $data, Auth::user());
            flash('success', 'Referentiel ajoute avec succes.');
        } catch (Exception $exception) { flash('error', $exception->getMessage()); }
        redirect('sites');
    }

    public function assignUser($id)
    {
        $this->ensureCsrf('sites');
        $siteIds = array_filter((array) ($_POST['site_ids'] ?? []), function ($id) { return ctype_digit((string) $id); });
        $defaultSiteId = trim($_POST['default_site_id'] ?? '');
        try {
            $this->model('Site')->syncUserSites($id, $siteIds, $defaultSiteId, Auth::user());
            flash('success', 'Affectations utilisateur mises a jour.');
        } catch (Exception $exception) { flash('error', $exception->getMessage()); }
        redirect('sites');
    }

    public function selectContext()
    {
        $this->ensureCsrf(Auth::homePathFor(Auth::user()));
        try {
            Auth::selectSite(trim($_POST['site_id'] ?? ''));
            flash('success', 'Contexte de site mis a jour.');
        } catch (Exception $exception) { flash('error', $exception->getMessage()); }
        redirect(($_POST['_return_to'] ?? '') === 'weighings/entry' ? 'weighings/entry' : Auth::homePathFor(Auth::user()));
    }

    private function siteInput()
    {
        return [
            'site_type_id' => trim($_POST['site_type_id'] ?? ''), 'code' => strtoupper(trim($_POST['code'] ?? '')),
            'name' => trim($_POST['name'] ?? ''), 'description' => trim($_POST['description'] ?? ''),
            'status' => trim($_POST['status'] ?? 'active'),
        ];
    }

    private function validateSite(array $data, Site $model, $ignoreId = null)
    {
        $errors = [];
        if (!ctype_digit($data['site_type_id'])) { $errors['site_type_id'] = 'Le type de site est obligatoire.'; }
        if ($data['code'] === '' || !preg_match('/^[A-Z0-9-]{2,50}$/', $data['code'])) { $errors['code'] = 'Code invalide.'; }
        elseif ($model->codeExists($data['code'], $ignoreId)) { $errors['code'] = 'Ce code existe deja.'; }
        if (strlen($data['name']) < 2) { $errors['name'] = 'Le nom est obligatoire.'; }
        if (!in_array($data['status'], ['active', 'inactive'], true)) { $errors['status'] = 'Statut invalide.'; }
        return $errors;
    }

    private function old(array $fallback = [])
    {
        Auth::start(); $old = $_SESSION['old_site'] ?? null; unset($_SESSION['old_site']);
        return $old ?: array_merge(['site_type_id' => '', 'code' => '', 'name' => '', 'description' => '', 'status' => 'active'], $fallback);
    }

    private function ensureCsrf($redirect)
    {
        if (!verify_csrf($_POST['_token'] ?? '')) { flash('error', 'Session expiree. Veuillez reessayer.'); redirect($redirect); }
    }
}
