<?php

class Site extends Model
{
    protected $table = 'sites';

    public function allDetailed()
    {
        return $this->query(
            "SELECT sites.*, site_types.name AS type_name, site_types.code AS type_code,
                    (SELECT COUNT(*) FROM user_sites WHERE user_sites.site_id = sites.id AND user_sites.status = 'active' AND user_sites.deleted_at IS NULL) AS users_count,
                    (SELECT COUNT(*) FROM storage_locations WHERE storage_locations.site_id = sites.id AND storage_locations.deleted_at IS NULL) AS locations_count
             FROM sites
             INNER JOIN site_types ON site_types.id = sites.site_type_id
             WHERE sites.deleted_at IS NULL
             ORDER BY sites.code ASC"
        )->fetchAll();
    }

    public function activeSites()
    {
        return $this->query(
            "SELECT id, code, name FROM sites WHERE status = 'active' AND deleted_at IS NULL ORDER BY name ASC"
        )->fetchAll();
    }

    public function types()
    {
        return $this->query(
            "SELECT id, code, name FROM site_types WHERE status = 'active' AND deleted_at IS NULL ORDER BY name ASC"
        )->fetchAll();
    }

    public function createType(array $data, array $user)
    {
        $this->query(
            "INSERT INTO site_types (code, name, status) VALUES (:code, :name, 'active')",
            ['code' => strtoupper(trim($data['code'])), 'name' => trim($data['name'])]
        );
        $id = (int) $this->db->lastInsertId();
        $this->logActivity('create', 'sites', 'site_types', $id, 'Creation type de site.', null, $data, $user);
        return $id;
    }

    public function findDetailed($id)
    {
        return $this->query(
            "SELECT sites.*, site_types.name AS type_name
             FROM sites INNER JOIN site_types ON site_types.id = sites.site_type_id
             WHERE sites.id = :id AND sites.deleted_at IS NULL LIMIT 1",
            ['id' => $id]
        )->fetch();
    }

    public function codeExists($code, $ignoreId = null)
    {
        $sql = "SELECT COUNT(*) FROM sites WHERE code = :code AND deleted_at IS NULL";
        $params = ['code' => $code];
        if ($ignoreId !== null) {
            $sql .= " AND id <> :ignore_id";
            $params['ignore_id'] = $ignoreId;
        }
        return (int) $this->query($sql, $params)->fetchColumn() > 0;
    }

    public function createSite(array $data, array $user)
    {
        $this->db->beginTransaction();
        try {
            $this->query(
                "INSERT INTO sites (site_type_id, code, name, description, status)
                 VALUES (:site_type_id, :code, :name, :description, :status)",
                $this->payload($data)
            );
            $id = (int) $this->db->lastInsertId();
            $this->query(
                "INSERT INTO user_sites (user_id, site_id, is_default, status)
                 SELECT users.id, :site_id, 0, 'active' FROM users
                 INNER JOIN roles ON roles.id = users.role_id
                 WHERE roles.slug IN ('administrateur', 'direction')
                   AND users.deleted_at IS NULL
                 ON DUPLICATE KEY UPDATE status = 'active', deleted_at = NULL",
                ['site_id' => $id]
            );
            $this->logActivity('create', 'sites', 'sites', $id, 'Creation site.', null, $data, $user);
            $this->db->commit();
            return $id;
        } catch (Exception $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function updateSite($id, array $data, array $user)
    {
        $this->db->beginTransaction();
        try {
            $old = $this->query("SELECT * FROM sites WHERE id = :id AND deleted_at IS NULL FOR UPDATE", ['id' => $id])->fetch();
            if (!$old) {
                throw new RuntimeException('Site introuvable.');
            }
            $payload = $this->payload($data);
            $payload['id'] = $id;
            $this->query(
                "UPDATE sites SET site_type_id = :site_type_id, code = :code, name = :name,
                 description = :description, status = :status WHERE id = :id AND deleted_at IS NULL",
                $payload
            );
            $this->logActivity('update', 'sites', 'sites', $id, 'Modification site.', $old, $data, $user);
            $this->db->commit();
        } catch (Exception $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function referenceRows($table)
    {
        $allowed = ['storage_locations', 'cost_centers', 'operational_units'];
        if (!in_array($table, $allowed, true)) {
            throw new InvalidArgumentException('Referentiel invalide.');
        }
        $costJoin = $table === 'operational_units'
            ? ' LEFT JOIN cost_centers ON cost_centers.id = operational_units.cost_center_id'
            : '';
        $costSelect = $table === 'operational_units' ? ', cost_centers.name AS cost_center_name' : '';
        return $this->query(
            "SELECT {$table}.*, sites.code AS site_code, sites.name AS site_name{$costSelect}
             FROM {$table} INNER JOIN sites ON sites.id = {$table}.site_id{$costJoin}
             WHERE {$table}.deleted_at IS NULL ORDER BY sites.code, {$table}.name"
        )->fetchAll();
    }

    public function createReference($table, array $data, array $user)
    {
        $definitions = [
            'storage_locations' => ['extra' => 'location_type', 'default' => 'warehouse'],
            'cost_centers' => ['extra' => null, 'default' => null],
            'operational_units' => ['extra' => 'cost_center_id', 'default' => null],
        ];
        if (!isset($definitions[$table])) {
            throw new InvalidArgumentException('Referentiel invalide.');
        }
        Auth::requireSiteAccess($data['site_id']);
        $definition = $definitions[$table];
        $columns = 'site_id, code, name, status';
        $values = ':site_id, :code, :name, :status';
        $params = [
            'site_id' => $data['site_id'], 'code' => $data['code'], 'name' => $data['name'], 'status' => $data['status'],
        ];
        if ($definition['extra']) {
            $columns .= ', ' . $definition['extra'];
            $values .= ', :extra';
            $params['extra'] = $data[$definition['extra']] ?: $definition['default'];
        }
        if ($table === 'operational_units' && $params['extra']) {
            $validCostCenter = (int) $this->query(
                "SELECT COUNT(*) FROM cost_centers WHERE id = :id AND site_id = :site_id AND status = 'active' AND deleted_at IS NULL",
                ['id' => $params['extra'], 'site_id' => $data['site_id']]
            )->fetchColumn();
            if (!$validCostCenter) { throw new RuntimeException('Le centre de cout doit appartenir au site selectionne.'); }
        }
        $this->query("INSERT INTO {$table} ({$columns}) VALUES ({$values})", $params);
        $id = (int) $this->db->lastInsertId();
        $this->logActivity('create', 'sites', $table, $id, 'Creation referentiel multisite.', null, $data, $user);
        return $id;
    }

    public function usersWithAssignments()
    {
        $users = $this->query(
            "SELECT users.id, users.name, users.email, roles.name AS role_name, roles.slug AS role_slug
             FROM users INNER JOIN roles ON roles.id = users.role_id
             WHERE users.deleted_at IS NULL ORDER BY users.name"
        )->fetchAll();
        $assignments = $this->query(
            "SELECT user_id, site_id, is_default FROM user_sites
             WHERE status = 'active' AND deleted_at IS NULL"
        )->fetchAll();
        foreach ($users as &$user) {
            $user['site_ids'] = [];
            $user['default_site_id'] = null;
            foreach ($assignments as $assignment) {
                if ((int) $assignment['user_id'] === (int) $user['id']) {
                    $user['site_ids'][] = (int) $assignment['site_id'];
                    if ($assignment['is_default']) {
                        $user['default_site_id'] = (int) $assignment['site_id'];
                    }
                }
            }
        }
        unset($user);
        return $users;
    }

    public function syncUserSites($userId, array $siteIds, $defaultSiteId, array $actor)
    {
        $this->db->beginTransaction();
        try {
            $user = $this->query("SELECT id FROM users WHERE id = :id AND deleted_at IS NULL FOR UPDATE", ['id' => $userId])->fetch();
            if (!$user || !$siteIds) {
                throw new RuntimeException('Utilisateur introuvable ou aucun site selectionne.');
            }
            $siteIds = array_values(array_unique(array_map('intval', $siteIds)));
            if (!in_array((int) $defaultSiteId, $siteIds, true)) {
                throw new RuntimeException('Le site par defaut doit appartenir aux sites affectes.');
            }
            $placeholders = implode(',', array_fill(0, count($siteIds), '?'));
            $validCount = (int) $this->query(
                "SELECT COUNT(*) FROM sites WHERE id IN ({$placeholders}) AND status = 'active' AND deleted_at IS NULL",
                $siteIds
            )->fetchColumn();
            if ($validCount !== count($siteIds)) {
                throw new RuntimeException('Un des sites selectionnes est invalide.');
            }
            $this->query("UPDATE user_sites SET status = 'inactive', deleted_at = NOW(), is_default = 0 WHERE user_id = :user_id AND deleted_at IS NULL", ['user_id' => $userId]);
            foreach ($siteIds as $siteId) {
                $this->query(
                    "INSERT INTO user_sites (user_id, site_id, is_default, status, deleted_at)
                     VALUES (:user_id, :site_id, :is_default, 'active', NULL)
                     ON DUPLICATE KEY UPDATE is_default = VALUES(is_default), status = 'active', deleted_at = NULL",
                    ['user_id' => $userId, 'site_id' => $siteId, 'is_default' => $siteId === (int) $defaultSiteId ? 1 : 0]
                );
            }
            $this->logActivity('update', 'sites', 'user_sites', $userId, 'Mise a jour affectations utilisateur.', null, ['site_ids' => $siteIds, 'default_site_id' => (int) $defaultSiteId], $actor);
            $this->db->commit();
        } catch (Exception $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function payload(array $data)
    {
        return [
            'site_type_id' => $data['site_type_id'],
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'description' => trim($data['description']) ?: null,
            'status' => $data['status'],
        ];
    }
}
