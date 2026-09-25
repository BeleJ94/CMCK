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

    public function resolve($id){$this->ensureCsrf();try{$this->model('Alert')->resolve($id,Auth::user());flash('success','Alerte résolue et journalisée.');}catch(Exception$e){flash('error',$e->getMessage());}redirect($this->returnPath());}

    private function filters(Alert $model)
    {
        $type = $_GET['type'] ?? '';
        $level = $_GET['level'] ?? '';

        return [
            'state' => in_array($_GET['state'] ?? '', ['active', 'resolved', 'unread'], true) ? $_GET['state'] : '',
            'type' => isset($model->types()[$type]) ? $type : '',
            'level' => in_array($level, $model->levels(), true) ? $level : '',
            'start_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['start_date']??'')?$_GET['start_date']:date('Y-m-01'),
            'end_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['end_date']??'')?$_GET['end_date']:date('Y-m-d'),
        ];
    }

    private function returnPath()
    {
        $query = [];

        foreach (['type', 'level', 'state', 'start_date', 'end_date'] as $key) {
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
