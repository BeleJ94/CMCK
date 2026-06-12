<?php

class ActivityLogController extends Controller
{
    public function index()
    {
        $model = $this->model('ActivityLog');
        $filters = $this->filters($model);

        $this->view('activity_logs.index', [
            'title' => 'Journal d activite',
            'logs' => $model->filtered($filters),
            'filters' => $filters,
            'actions' => $model->actions(),
            'modules' => $model->modules(),
            'stats' => $model->stats(),
        ], 'layouts.main');
    }

    private function filters(ActivityLog $model)
    {
        $today = date('Y-m-d');
        $start = $_GET['start_date'] ?? '';
        $end = $_GET['end_date'] ?? '';
        $action = $_GET['action'] ?? '';
        $module = trim($_GET['module'] ?? '');

        return [
            'start_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ? $start : '',
            'end_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) ? $end : '',
            'action' => isset($model->actions()[$action]) ? $action : '',
            'module' => $module,
            'today' => $today,
        ];
    }
}
