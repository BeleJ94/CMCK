<?php

class Silo extends Model
{
    protected $table = 'silos';

    public function administrationSites()
    {
        return array_values(array_filter(Auth::sites(), function ($site) {
            return Auth::can('silos', 'administer', $site['id']);
        }));
    }

    public function administrationRows()
    {
        return array_values(array_filter($this->allWithStats(), function ($silo) {
            return Auth::canAccessSite($silo['site_id']) && Auth::can('silos', 'administer', $silo['site_id']);
        }));
    }

    public function administrationProducts()
    {
        return $this->query("SELECT id, name FROM products WHERE category = 'raw_material'
            AND status IN ('active', 'validated') AND deleted_at IS NULL ORDER BY name")->fetchAll();
    }

    public function administrationRecord($id)
    {
        $silo = $this->findDetailed($id);
        if (!$silo) { throw new RuntimeException('Silo introuvable dans le site sélectionné.'); }
        $this->assertAdministrationSite($silo['site_id']);
        return $silo;
    }

    public function saveSilo(array $input, array $user, $id = null)
    {
        $data = $this->validateConfiguration($input);
        $this->db->beginTransaction();
        try {
            $old = $id === null ? null : $this->lockAdministrationRecord($id);
            $this->assertAdministrationSite($data['site_id']);
            if (!$this->query("SELECT id FROM sites WHERE id = :id AND status = 'active' AND deleted_at IS NULL FOR UPDATE", ['id' => $data['site_id']])->fetch()) {
                throw new RuntimeException('Le site sélectionné est indisponible.');
            }
            if ($data['product_id'] !== null && !$this->query("SELECT id FROM products WHERE id = :id AND category = 'raw_material' AND status IN ('active', 'validated') AND deleted_at IS NULL FOR UPDATE", ['id' => $data['product_id']])->fetch()) {
                throw new RuntimeException('Sélectionnez une matière première active.');
            }
            if ($this->query('SELECT id FROM silos WHERE code = :code AND id <> :id', ['code' => $data['code'], 'id' => $id ?: 0])->fetch()) {
                throw new RuntimeException('Ce code de silo existe déjà.');
            }
            if ($old) {
                if ((float) $data['capacity_kg'] < (float) $old['current_stock_kg']) {
                    throw new RuntimeException('La capacité ne peut pas être inférieure au stock présent.');
                }
                $reassigned = (int) $old['site_id'] !== (int) $data['site_id'] || (string) $old['product_id'] !== (string) $data['product_id'];
                if ($reassigned && (float) $old['current_stock_kg'] != 0) {
                    throw new RuntimeException('Videz le silo avant de changer son site ou son produit.');
                }
                $this->query('UPDATE silos SET name = :name, code = :code, site_id = :site_id,
                    product_id = :product_id, capacity_kg = :capacity_kg, alert_threshold_kg = :alert_threshold_kg
                    WHERE id = :id', array_merge($data, ['id' => $id]));
            } else {
                $this->query("INSERT INTO silos (name, code, site_id, product_id, capacity_kg, alert_threshold_kg, current_stock_kg, unit, status)
                    VALUES (:name, :code, :site_id, :product_id, :capacity_kg, :alert_threshold_kg, 0, 'kg', 'active')", $data);
                $id = (int) $this->db->lastInsertId();
            }
            $new = $this->query('SELECT * FROM silos WHERE id = :id', ['id' => $id])->fetch();
            $this->auditConfiguration($old ? 'update' : 'create', $id, $old, $new, $user);
            $this->db->commit();
            return $id;
        } catch (Exception $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) == 1062) {
                throw new RuntimeException('Ce code de silo existe déjà.');
            }
            throw $exception;
        }
    }

    public function changeStatus($id, $status, array $user)
    {
        if (!in_array($status, ['active', 'inactive'], true)) { throw new RuntimeException('Statut invalide.'); }
        $this->db->beginTransaction();
        try {
            $old = $this->lockAdministrationRecord($id);
            if ($status === 'inactive' && (float) $old['current_stock_kg'] != 0) {
                throw new RuntimeException('Videz le silo avant de le désactiver.');
            }
            if ($old['status'] !== $status) {
                $this->query('UPDATE silos SET status = :status WHERE id = :id', ['status' => $status, 'id' => $id]);
                $this->auditConfiguration('update_status', $id, $old, array_merge($old, ['status' => $status]), $user);
            }
            $this->db->commit();
        } catch (Exception $exception) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $exception;
        }
    }

    private function validateConfiguration(array $input)
    {
        $data = [];
        foreach (['name', 'code', 'site_id', 'product_id', 'capacity_kg', 'alert_threshold_kg'] as $key) {
            if (isset($input[$key]) && !is_scalar($input[$key])) { throw new RuntimeException('Champ invalide : ' . $key); }
            $data[$key] = trim((string) ($input[$key] ?? ''));
        }
        $data['code'] = strtoupper($data['code']);
        if ($data['name'] === '' || mb_strlen($data['name']) > 120) { throw new RuntimeException('Le nom est obligatoire (120 caractères maximum).'); }
        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{0,79}$/D', $data['code'])) { throw new RuntimeException('Code obligatoire : 80 lettres, chiffres, tirets ou underscores maximum.'); }
        if (!ctype_digit($data['site_id']) || (int) $data['site_id'] < 1) { throw new RuntimeException('Le site est obligatoire.'); }
        if ($data['product_id'] !== '' && (!ctype_digit($data['product_id']) || (int) $data['product_id'] < 1)) { throw new RuntimeException('Produit invalide.'); }
        $data['product_id'] = $data['product_id'] === '' ? null : $data['product_id'];
        foreach (['capacity_kg', 'alert_threshold_kg'] as $key) {
            if (!preg_match('/^\d{1,9}(\.\d{1,3})?$/D', $data[$key])) { throw new RuntimeException('Les quantités doivent être positives, avec trois décimales maximum.'); }
        }
        if ((float) $data['capacity_kg'] <= 0) { throw new RuntimeException('La capacité doit être supérieure à zéro.'); }
        if ((float) $data['alert_threshold_kg'] > (float) $data['capacity_kg']) { throw new RuntimeException('Le seuil d’alerte ne peut pas dépasser la capacité.'); }
        return $data;
    }

    private function assertAdministrationSite($siteId)
    {
        Auth::requireSiteAccess($siteId);
        Auth::requirePermission('silos', 'administer', $siteId);
    }

    private function lockAdministrationRecord($id)
    {
        $params = ['id' => $id];
        $scope = Auth::siteClause('site_id', $params);
        $silo = $this->query("SELECT * FROM silos WHERE id = :id AND deleted_at IS NULL{$scope} FOR UPDATE", $params)->fetch();
        if (!$silo) { throw new RuntimeException('Silo introuvable dans le site sélectionné.'); }
        $this->assertAdministrationSite($silo['site_id']);
        return $silo;
    }

    private function auditConfiguration($action, $id, $old, array $new, array $user)
    {
        $this->query("INSERT INTO activity_logs (user_id, site_id, action, module, entity_type, entity_id,
            description, old_values, new_values, ip_address, user_agent)
            VALUES (:user, :site, :action, 'silos', 'silos', :id, :description, :old, :new, :ip, :agent)", [
            'user' => $user['id'], 'site' => $new['site_id'], 'action' => $action, 'id' => $id,
            'description' => 'Administration du silo ' . $new['code'] . '.',
            'old' => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
            'new' => json_encode($new, JSON_UNESCAPED_UNICODE),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null, 'agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255),
        ]);
    }

    public function allWithStats()
    {
        $params = [];
        $siteClause = Auth::siteClause('silos.site_id', $params);
        return $this->query(
            "SELECT silos.*,
                    products.name AS product_name,
                    sites.name AS site_name,
                    sites.code AS site_code,
                    CASE
                        WHEN silos.capacity_kg > 0 THEN (silos.current_stock_kg / silos.capacity_kg) * 100
                        ELSE 0
                    END AS fill_rate
             FROM silos
             LEFT JOIN products ON products.id = silos.product_id
             LEFT JOIN sites ON sites.id = silos.site_id
             WHERE silos.deleted_at IS NULL{$siteClause}
             ORDER BY silos.name ASC", $params
        )->fetchAll();
    }

    public function findDetailed($id)
    {
        $params = ['id' => $id];
        $siteClause = Auth::siteClause('silos.site_id', $params);
        return $this->query(
            "SELECT silos.*,
                    products.name AS product_name,
                    sites.name AS site_name,
                    sites.code AS site_code,
                    CASE
                        WHEN silos.capacity_kg > 0 THEN (silos.current_stock_kg / silos.capacity_kg) * 100
                        ELSE 0
                    END AS fill_rate
             FROM silos
             LEFT JOIN products ON products.id = silos.product_id
             LEFT JOIN sites ON sites.id = silos.site_id
             WHERE silos.id = :id
               AND silos.deleted_at IS NULL{$siteClause}
             LIMIT 1",
            $params
        )->fetch();
    }

    public function movements($siloId = null, $limit = null)
    {
        $params = [];
        $where = "WHERE silo_movements.deleted_at IS NULL";
        $where .= Auth::siteClause('silo_movements.site_id', $params);

        if ($siloId !== null) {
            $where .= " AND silo_movements.silo_id = :silo_id";
            $params['silo_id'] = $siloId;
        }

        return $this->query(
            "SELECT silo_movements.*,
                    silos.name AS silo_name,
                    silos.code AS silo_code,
                    products.name AS product_name,
                    weighings.reference AS weighing_reference,
                    users.name AS created_by_name
             FROM silo_movements
             INNER JOIN silos ON silos.id = silo_movements.silo_id
             INNER JOIN products ON products.id = silo_movements.product_id
             LEFT JOIN weighings ON weighings.id = silo_movements.weighing_id
             LEFT JOIN users ON users.id = silo_movements.created_by
             {$where}
             ORDER BY silo_movements.movement_at DESC, silo_movements.id DESC" . ($limit === null ? '' : ' LIMIT ' . max(1, min(100, (int) $limit))),
            $params
        )->fetchAll();
    }

    public function entriesByDelivery($siloId = null, $limit = null)
    {
        $params = [];
        $where = "WHERE silo_movements.deleted_at IS NULL
                    AND silo_movements.movement_type = 'in'
                    AND silo_movements.weighing_id IS NOT NULL";
        $where .= Auth::siteClause('silo_movements.site_id', $params);

        if ($siloId !== null) {
            $where .= " AND silo_movements.silo_id = :silo_id";
            $params['silo_id'] = $siloId;
        }

        return $this->query(
            "SELECT silo_movements.*,
                    silos.name AS silo_name,
                    suppliers.name AS supplier_name,
                    trucks.plate_number,
                    weighings.reference AS weighing_reference
             FROM silo_movements
             INNER JOIN silos ON silos.id = silo_movements.silo_id
             INNER JOIN weighings ON weighings.id = silo_movements.weighing_id
             LEFT JOIN suppliers ON suppliers.id = weighings.supplier_id
             INNER JOIN trucks ON trucks.id = weighings.truck_id
             {$where}
             ORDER BY silo_movements.movement_at DESC, silo_movements.id DESC" . ($limit === null ? '' : ' LIMIT ' . max(1, min(100, (int) $limit))),
            $params
        )->fetchAll();
    }

    public function exitsToMachines($siloId = null, $limit = null)
    {
        $params = [];
        $where = "WHERE machine_feeds.deleted_at IS NULL";
        $where .= Auth::siteClause('machine_feeds.site_id', $params);

        if ($siloId !== null) {
            $where .= " AND machine_feeds.silo_id = :silo_id";
            $params['silo_id'] = $siloId;
        }

        return $this->query(
            "SELECT machine_feeds.*,
                    silos.name AS silo_name,
                    machines.name AS machine_name,
                    products.name AS product_name,
                    users.name AS created_by_name
             FROM machine_feeds
             INNER JOIN silos ON silos.id = machine_feeds.silo_id
             INNER JOIN machines ON machines.id = machine_feeds.machine_id
             INNER JOIN products ON products.id = machine_feeds.product_id
             LEFT JOIN users ON users.id = machine_feeds.created_by
             {$where}
             ORDER BY machine_feeds.fed_at DESC, machine_feeds.id DESC" . ($limit === null ? '' : ' LIMIT ' . max(1, min(100, (int) $limit))),
            $params
        )->fetchAll();
    }

    public function alerts()
    {
        return array_values(array_filter($this->allWithStats(), function ($silo) {
            return $this->isLowStock($silo) || $this->isAlmostFull($silo) || (float) $silo['current_stock_kg'] > (float) $silo['capacity_kg'];
        }));
    }

    public function isLowStock(array $silo)
    {
        return (float) $silo['alert_threshold_kg'] > 0
            && (float) $silo['current_stock_kg'] <= (float) $silo['alert_threshold_kg'];
    }

    public function isAlmostFull(array $silo)
    {
        return (float) $silo['fill_rate'] >= 90;
    }
}
