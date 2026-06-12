<?php

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    public function actions()
    {
        return [
            'login' => 'Connexion',
            'create' => 'Creation',
            'update' => 'Modification',
            'delete' => 'Suppression',
            'validate_weighing' => 'Validation pesee',
            'stock_movement' => 'Mouvement stock',
            'production' => 'Production',
            'distribution' => 'Distribution',
        ];
    }

    public function modules()
    {
        return $this->query(
            "SELECT DISTINCT module
             FROM activity_logs
             WHERE module IS NOT NULL
             ORDER BY module ASC"
        )->fetchAll();
    }

    public function filtered(array $filters = [])
    {
        $params = [];
        $where = "WHERE activity_logs.id IS NOT NULL";

        if (!empty($filters['action']) && isset($this->actions()[$filters['action']])) {
            $where .= " AND activity_logs.action = :action";
            $params['action'] = $filters['action'];
        }

        if (!empty($filters['module'])) {
            $where .= " AND activity_logs.module = :module";
            $params['module'] = $filters['module'];
        }

        if (!empty($filters['start_date'])) {
            $where .= " AND DATE(activity_logs.created_at) >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where .= " AND DATE(activity_logs.created_at) <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        return $this->query(
            "SELECT activity_logs.*,
                    users.name AS user_name,
                    users.email AS user_email
             FROM activity_logs
             LEFT JOIN users ON users.id = activity_logs.user_id
             {$where}
             ORDER BY activity_logs.created_at DESC, activity_logs.id DESC
             LIMIT 300",
            $params
        )->fetchAll();
    }

    public function stats()
    {
        $row = $this->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
                SUM(CASE WHEN action = 'login' THEN 1 ELSE 0 END) AS logins,
                SUM(CASE WHEN action IN ('stock_movement', 'production', 'distribution', 'validate_weighing') THEN 1 ELSE 0 END) AS operations
             FROM activity_logs"
        )->fetch();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'today' => (int) ($row['today'] ?? 0),
            'logins' => (int) ($row['logins'] ?? 0),
            'operations' => (int) ($row['operations'] ?? 0),
        ];
    }

    public function record($action, $module, $entityType = null, $entityId = null, $description = null, array $oldValues = null, array $newValues = null, array $user = null)
    {
        $this->logActivity($action, $module, $entityType, $entityId, $description, $oldValues, $newValues, $user);
    }
}
