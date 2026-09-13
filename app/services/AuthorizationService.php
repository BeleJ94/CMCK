<?php

class AuthorizationService
{
    private $db;
    private static $expirationChecked = false;

    public function __construct(PDO $db = null)
    {
        $this->db = $db ?: Database::getInstance()->connection();
    }

    public function can($userId, $component, $action, $siteId = null)
    {
        $requestedComponent=$component;
        if (!self::$expirationChecked) {
            self::$expirationChecked = true;
            $this->expireDelegations();
        }
        if (!$userId || !in_array($action, ['read', 'create', 'validate', 'update', 'delete', 'administer'], true)) {
            return false;
        }
        list($component,$action)=$this->compatiblePermission($component,$action);

        $sql = "SELECT 1
                FROM user_role_assignments ura
                INNER JOIN roles r ON r.id = ura.role_id
                INNER JOIN role_permissions rp ON rp.role_id = r.id
                INNER JOIN permissions p ON p.id = rp.permission_id
                WHERE ura.user_id = :user_id
                  AND p.component = :component AND p.action = :action
                  AND ura.approval_status = 'approved'
                  AND ura.deleted_at IS NULL AND r.deleted_at IS NULL AND r.status = 'active'
                  AND ura.starts_at <= NOW()
                  AND (ura.expires_at IS NULL OR ura.expires_at > NOW())";
        $params = ['user_id' => $userId, 'component' => $component, 'action' => $action];

        if ($siteId !== null) {
            $sql .= ' AND (ura.site_id IS NULL OR ura.site_id = :site_id)';
            $params['site_id'] = $siteId;
        }

        $sql .= ' LIMIT 1';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $allowed=(bool)$statement->fetchColumn();
        if(!$allowed&&$requestedComponent==='analytics'&&$component==='reports'&&$action==='read'){$legacy=$this->db->prepare("SELECT 1 FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=:user AND r.slug IN('administrateur','direction') AND u.status='active' AND u.deleted_at IS NULL LIMIT 1");$legacy->execute(['user'=>$userId]);$allowed=(bool)$legacy->fetchColumn();}
        return $allowed;
    }

    public function assertAllowed($userId, $component, $action, $siteId = null)
    {
        if (!$this->can($userId, $component, $action, $siteId)) {
            http_response_code(403);
            throw new RuntimeException('Permission refusee: ' . $component . '.' . $action . '.');
        }
    }

    public function canGlobally($userId, $component, $action)
    {
        list($component,$action)=$this->compatiblePermission($component,$action);
        $statement=$this->db->prepare("SELECT 1 FROM user_role_assignments ura INNER JOIN roles r ON r.id=ura.role_id INNER JOIN role_permissions rp ON rp.role_id=r.id INNER JOIN permissions p ON p.id=rp.permission_id WHERE ura.user_id=:user_id AND ura.site_id IS NULL AND ura.approval_status='approved' AND ura.deleted_at IS NULL AND ura.starts_at<=NOW() AND (ura.expires_at IS NULL OR ura.expires_at>NOW()) AND r.status='active' AND r.deleted_at IS NULL AND p.component=:component AND p.action=:action LIMIT 1");
        $statement->execute(['user_id'=>$userId,'component'=>$component,'action'=>$action]);
        return (bool)$statement->fetchColumn();
    }

    private function compatiblePermission($component,$action)
    {
        if($component!=='analytics'||$action!=='read'){return[$component,$action];}
        $statement=$this->db->prepare("SELECT COUNT(*) FROM permissions WHERE component='analytics' AND action='read'");
        $statement->execute();
        return (int)$statement->fetchColumn()>0?[$component,$action]:['reports','read'];
    }

    public function assertCanValidateRecord($userId, $component, array $record, $creatorField = 'created_by')
    {
        $siteId = isset($record['site_id']) ? (int) $record['site_id'] : null;
        $this->assertAllowed($userId, $component, 'validate', $siteId);
        if (!empty($record[$creatorField]) && (int) $record[$creatorField] === (int) $userId && !Auth::canSelfValidate($userId)) {
            throw new RuntimeException('Separation des taches: le createur ne peut pas valider cette operation.');
        }
    }

    public function expireDelegations()
    {
        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) { $this->db->beginTransaction(); }
        try {
            $rows = $this->db->query("SELECT id FROM user_role_assignments WHERE assignment_type='delegation' AND approval_status='approved' AND expires_at IS NOT NULL AND expires_at <= NOW() FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
            if ($rows) {
                $ids = implode(',', array_map('intval', $rows));
                $this->db->exec("UPDATE user_role_assignments SET approval_status='revoked', revoked_at=NOW(), reason=CONCAT(COALESCE(reason,''), ' [expiration automatique]') WHERE id IN ({$ids})");
                $this->db->exec("INSERT INTO rbac_approval_events (assignment_id,event_type,details) SELECT id,'expired','Delegation expiree automatiquement' FROM user_role_assignments WHERE id IN ({$ids})");
                $this->db->exec("INSERT INTO activity_logs (user_id,site_id,action,module,entity_type,entity_id,description,user_agent) SELECT NULL,site_id,'rbac_expire','rbac','user_role_assignments',id,'Expiration automatique de delegation','SYSTEM' FROM user_role_assignments WHERE id IN ({$ids})");
            }
            if ($ownTransaction) { $this->db->commit(); }
            return count($rows);
        } catch (Exception $exception) {
            if ($ownTransaction && $this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }
    }
}
