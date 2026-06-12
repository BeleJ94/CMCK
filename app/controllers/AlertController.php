<?php

class AlertController extends Controller
{
    public function index()
    {
        $model = $this->model('Alert');
        $model->generateSystemAlerts();
        $filters = $this->filters($model);

        $this->view('alerts.index', [
            'title' => 'Alertes',
            'alerts' => $model->filtered($filters),
            'filters' => $filters,
            'types' => $model->types(),
            'levels' => $model->levels(),
            'stats' => $model->stats(),
            'success' => flash('success'),
            'error' => flash('error'),
        ], 'layouts.main');
    }

    public function markRead($id)
    {
        $this->ensureCsrf();
        $this->model('Alert')->markAsRead($id);
        flash('success', 'Alerte marquee comme lue.');
        redirect($this->returnPath());
    }

    public function markAllRead()
    {
        $this->ensureCsrf();
        $this->model('Alert')->markAllAsRead();
        flash('success', 'Toutes les alertes actives ont ete marquees comme lues.');
        redirect($this->returnPath());
    }

    private function filters(Alert $model)
    {
        $type = $_GET['type'] ?? '';
        $level = $_GET['level'] ?? '';

        return [
            'type' => isset($model->types()[$type]) ? $type : '',
            'level' => in_array($level, $model->levels(), true) ? $level : '',
        ];
    }

    private function returnPath()
    {
        $query = [];

        foreach (['type', 'level'] as $key) {
            if (!empty($_POST[$key])) {
                $query[$key] = $_POST[$key];
            }
        }

        return 'alerts' . ($query ? '?' . http_build_query($query) : '');
    }

    private function ensureCsrf()
    {
        if (!verify_csrf($_POST['_token'] ?? '')) {
            flash('error', 'Session expiree. Veuillez reessayer.');
            redirect('alerts');
        }
    }
}
