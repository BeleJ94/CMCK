<?php

class SiloController extends Controller
{
    public function administration()
    {
        $model = $this->model('Silo');
        $this->view('silos.administration', [
            'title' => 'Gestion des silos', 'silos' => $model->administrationRows(),
            'sites' => $model->administrationSites(), 'success' => flash('success'), 'error' => flash('error'),
        ], 'layouts.main');
    }

    public function create()
    {
        $this->configurationForm();
    }

    public function edit($id)
    {
        $this->configurationForm($id);
    }

    private function configurationForm($id = null)
    {
        $model = $this->model('Silo');
        try {
            $silo = $id === null ? ['site_id' => Auth::currentSiteId(), 'alert_threshold_kg' => '0'] : $model->administrationRecord($id);
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage()); redirect('silo-administration');
        }
        $old = flash('silo_input_' . ($id ?: 'new'));
        if (is_array($old)) { $silo = array_merge($silo, $old); }
        $this->view('silos.form', [
            'title' => $id === null ? 'Nouveau silo' : 'Modifier le silo', 'silo' => $silo,
            'sites' => $model->administrationSites(), 'products' => $model->administrationProducts(),
            'error' => flash('error'), 'id' => $id,
        ], 'layouts.main');
    }

    public function store()
    {
        $this->saveConfiguration();
    }

    public function update($id)
    {
        $this->saveConfiguration($id);
    }

    private function saveConfiguration($id = null)
    {
        $returnPath = $id === null ? 'silo-administration/create' : 'silo-administration/' . $id . '/edit';
        $this->configurationCsrf($returnPath);
        $data = [];
        foreach (['name', 'code', 'site_id', 'product_id', 'capacity_kg', 'alert_threshold_kg'] as $key) {
            $data[$key] = is_scalar($_POST[$key] ?? '') ? trim((string) ($_POST[$key] ?? '')) : '';
        }
        try {
            $this->model('Silo')->saveSilo($data, Auth::user(), $id);
            flash('success', $id === null ? 'Silo créé avec succès.' : 'Silo modifié avec succès.');
        } catch (Exception $exception) {
            flash('silo_input_' . ($id ?: 'new'), $data);
            flash('error', $exception instanceof PDOException ? 'Impossible d’enregistrer le silo. Veuillez réessayer.' : $exception->getMessage());
            redirect($returnPath);
        }
        redirect('silo-administration');
    }

    public function setStatus($id)
    {
        $this->configurationCsrf('silo-administration');
        try {
            $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
            $this->model('Silo')->changeStatus($id, $status, Auth::user());
            flash('success', $status === 'active' ? 'Silo activé.' : 'Silo désactivé.');
        } catch (Exception $exception) {
            flash('error', $exception instanceof PDOException ? 'Impossible de modifier le statut du silo.' : $exception->getMessage());
        }
        redirect('silo-administration');
    }

    private function configurationCsrf($path)
    {
        $token = is_string($_POST['_token'] ?? null) ? $_POST['_token'] : '';
        if (!verify_csrf($token)) { flash('error', 'Session expirée. Veuillez réessayer.'); redirect($path); }
    }

    public function index()
    {
        $model = $this->model('Silo');
        require_once dirname(__DIR__) . '/services/SiloDashboardService.php';
        $dashboard = SiloDashboardService::build($model->allWithStats(), $_GET);
        $siteId = Auth::currentSiteId();
        $siteLabel = $siteId === null ? 'Tous les sites' : 'Site courant';
        foreach (Auth::sites() as $site) {
            if ((int) $site['id'] === $siteId) { $siteLabel = $site['name']; break; }
        }
        $this->view('silos.index', array_merge($dashboard, [
            'title' => 'Pilotage des silos', 'siteLabel' => $siteLabel,
            'updatedAt' => date('H:i'), 'recentMovements' => $model->movements(null, 6),
            'success' => flash('success'),
            'error' => flash('error'),
        ]), 'layouts.main');
    }

    public function show($id)
    {
        $model = $this->model('Silo');
        $silo = $model->findDetailed($id);

        if (!$silo) {
            flash('error', 'Silo introuvable.');
            redirect('silos');
        }

        require_once dirname(__DIR__) . '/services/SiloDashboardService.php';
        $dashboard = SiloDashboardService::build([$silo], ['unit' => $_GET['unit'] ?? 'kg']);
        $tab = isset($_GET['tab']) && is_string($_GET['tab']) && in_array($_GET['tab'], ['movements', 'entries', 'exits'], true) ? $_GET['tab'] : 'movements';
        $movements = $model->movements($id, 10);
        $rows = $tab === 'entries' ? $model->entriesByDelivery($id, 10) : ($tab === 'exits' ? $model->exitsToMachines($id, 10) : $movements);
        $this->view('silos.show', [
            'title' => $silo['name'] . ' — Détail du silo',
            'silo' => $dashboard['silos'][0], 'unit' => $dashboard['filters']['unit'],
            'tab' => $tab, 'rows' => $rows, 'latestMovement' => $movements[0] ?? null,
            'updatedAt' => date('H:i'),
        ], 'layouts.main');
    }

    public function movements()
    {
        $model = $this->model('Silo');

        $this->view('silos.movements', [
            'title' => 'Mouvements silos',
            'movements' => $model->movements(),
            'entries' => $model->entriesByDelivery(),
            'exits' => $model->exitsToMachines(),
        ], 'layouts.main');
    }
}
