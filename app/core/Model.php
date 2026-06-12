<?php

abstract class Model
{
    protected $db;
    protected $table;
    protected $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance()->connection();
    }

    public function all()
    {
        $statement = $this->db->query("SELECT * FROM {$this->table}");

        return $statement->fetchAll();
    }

    public function find($id)
    {
        $statement = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
        $statement->execute(['id' => $id]);

        return $statement->fetch();
    }

    protected function query($sql, array $params = [])
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    protected function logActivity($action, $module, $entityType = null, $entityId = null, $description = null, array $oldValues = null, array $newValues = null, array $user = null)
    {
        $user = $user ?: (class_exists('Auth') ? Auth::user() : null);

        $this->query(
            "INSERT INTO activity_logs (
                user_id, action, module, entity_type, entity_id, description,
                old_values, new_values, ip_address, user_agent
             ) VALUES (
                :user_id, :action, :module, :entity_type, :entity_id, :description,
                :old_values, :new_values, :ip_address, :user_agent
             )",
            [
                'user_id' => $user['id'] ?? null,
                'action' => $action,
                'module' => $module,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description,
                'old_values' => $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE),
                'new_values' => $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255),
            ]
        );
    }
}
