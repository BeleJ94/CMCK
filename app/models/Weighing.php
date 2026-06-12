<?php

class Weighing extends Model
{
    protected $table = 'weighings';

    public function allWithRelations()
    {
        return $this->query(
            "SELECT weighings.*,
                    suppliers.name AS supplier_name,
                    trucks.plate_number,
                    trucks.driver_name,
                    products.name AS product_name
             FROM weighings
             INNER JOIN suppliers ON suppliers.id = weighings.supplier_id
             INNER JOIN trucks ON trucks.id = weighings.truck_id
             INNER JOIN products ON products.id = weighings.product_id
             WHERE weighings.deleted_at IS NULL
             ORDER BY weighings.weighed_at DESC"
        )->fetchAll();
    }

    public function pending()
    {
        return $this->query(
            "SELECT weighings.*,
                    suppliers.name AS supplier_name,
                    trucks.plate_number,
                    trucks.driver_name,
                    products.name AS product_name
             FROM weighings
             INNER JOIN suppliers ON suppliers.id = weighings.supplier_id
             INNER JOIN trucks ON trucks.id = weighings.truck_id
             INNER JOIN products ON products.id = weighings.product_id
             WHERE weighings.status = 'pending'
               AND weighings.deleted_at IS NULL
             ORDER BY weighings.weighed_at ASC"
        )->fetchAll();
    }

    public function findDetailed($id)
    {
        return $this->query(
            "SELECT weighings.*,
                    suppliers.name AS supplier_name,
                    trucks.plate_number,
                    trucks.driver_name,
                    trucks.driver_phone,
                    products.name AS product_name,
                    users.name AS agent_name,
                    validators.name AS validator_name,
                    silo_movements.silo_id,
                    silos.name AS silo_name,
                    silos.code AS silo_code
             FROM weighings
             INNER JOIN suppliers ON suppliers.id = weighings.supplier_id
             INNER JOIN trucks ON trucks.id = weighings.truck_id
             INNER JOIN products ON products.id = weighings.product_id
             LEFT JOIN users ON users.id = weighings.created_by
             LEFT JOIN users validators ON validators.id = weighings.validated_by
             LEFT JOIN silo_movements ON silo_movements.weighing_id = weighings.id AND silo_movements.deleted_at IS NULL
             LEFT JOIN silos ON silos.id = silo_movements.silo_id
             WHERE weighings.id = :id
               AND weighings.deleted_at IS NULL
             LIMIT 1",
            ['id' => $id]
        )->fetch();
    }

    public function suppliers()
    {
        return $this->query(
            "SELECT id, name
             FROM suppliers
             WHERE deleted_at IS NULL
               AND status IN ('active', 'validated')
             ORDER BY name ASC"
        )->fetchAll();
    }

    public function trucks()
    {
        return $this->query(
            "SELECT trucks.id, trucks.plate_number, trucks.driver_name, trucks.supplier_id, suppliers.name AS supplier_name
             FROM trucks
             LEFT JOIN suppliers ON suppliers.id = trucks.supplier_id
             WHERE trucks.deleted_at IS NULL
               AND trucks.status IN ('active', 'validated')
             ORDER BY trucks.plate_number ASC"
        )->fetchAll();
    }

    public function products()
    {
        return $this->query(
            "SELECT id, name
             FROM products
             WHERE deleted_at IS NULL
               AND status IN ('active', 'validated')
               AND category = 'raw_material'
             ORDER BY name ASC"
        )->fetchAll();
    }

    public function silos()
    {
        return $this->query(
            "SELECT id, name, code, current_stock_kg
             FROM silos
             WHERE deleted_at IS NULL
               AND status IN ('active', 'validated')
             ORDER BY name ASC"
        )->fetchAll();
    }

    public function createEntry(array $data, array $user)
    {
        $this->db->beginTransaction();

        try {
            $truckId = $this->findOrCreateTruck($data);

            $this->query(
                "INSERT INTO weighings (
                    supplier_id, truck_id, product_id, reference, poids_brut, poids_tare, poids_net,
                    weighed_at, status, created_by
                 ) VALUES (
                    :supplier_id, :truck_id, :product_id, :reference, :poids_brut, 0, 0,
                    NOW(), 'pending', :created_by
                 )",
                [
                    'supplier_id' => $data['supplier_id'],
                    'truck_id' => $truckId,
                    'product_id' => $data['product_id'],
                    'reference' => $this->reference(),
                    'poids_brut' => $data['poids_brut'],
                    'created_by' => $user['id'] ?? null,
                ]
            );

            $id = $this->db->lastInsertId();
            $this->logActivity('create', 'pont-bascule', 'weighings', $id, 'Creation pesee entree.', null, array_merge($data, ['truck_id' => $truckId]), $user);
            $this->db->commit();

            return $id;
        } catch (Exception $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function validateExit($id, array $data, array $user)
    {
        $this->db->beginTransaction();

        try {
            $weighing = $this->query(
                "SELECT *
                 FROM weighings
                 WHERE id = :id
                   AND deleted_at IS NULL
                 FOR UPDATE",
                ['id' => $id]
            )->fetch();

            if (!$weighing) {
                throw new RuntimeException('Pesee introuvable.');
            }

            if ($weighing['status'] === 'validated') {
                throw new RuntimeException('Cette pesee est deja validee.');
            }

            $silo = $this->query(
                "SELECT *
                 FROM silos
                 WHERE id = :id
                   AND deleted_at IS NULL
                 FOR UPDATE",
                ['id' => $data['silo_id']]
            )->fetch();

            if (!$silo) {
                throw new RuntimeException('Silo destination introuvable.');
            }

            $poidsNet = (float) $weighing['poids_brut'] - (float) $data['poids_tare'];
            $stockBefore = (float) $silo['current_stock_kg'];
            $stockAfter = $stockBefore + $poidsNet;

            $this->query(
                "UPDATE weighings
                 SET poids_tare = :poids_tare,
                     poids_net = :poids_net,
                     status = 'validated',
                     validated_by = :validated_by
                 WHERE id = :id",
                [
                    'poids_tare' => $data['poids_tare'],
                    'poids_net' => $poidsNet,
                    'validated_by' => $user['id'] ?? null,
                    'id' => $id,
                ]
            );

            $this->query(
                "INSERT INTO silo_movements (
                    silo_id, product_id, weighing_id, movement_type, quantity_kg,
                    stock_before_kg, stock_after_kg, movement_at, status, created_by
                 ) VALUES (
                    :silo_id, :product_id, :weighing_id, 'in', :quantity_kg,
                    :stock_before_kg, :stock_after_kg, NOW(), 'validated', :created_by
                 )",
                [
                    'silo_id' => $data['silo_id'],
                    'product_id' => $weighing['product_id'],
                    'weighing_id' => $id,
                    'quantity_kg' => $poidsNet,
                    'stock_before_kg' => $stockBefore,
                    'stock_after_kg' => $stockAfter,
                    'created_by' => $user['id'] ?? null,
                ]
            );
            $movementId = $this->db->lastInsertId();

            $this->query(
                "UPDATE silos
                 SET current_stock_kg = :stock
                 WHERE id = :id",
                [
                    'stock' => $stockAfter,
                    'id' => $data['silo_id'],
                ]
            );

            $this->logActivity(
                'validate_weighing',
                'pont-bascule',
                'weighings',
                $id,
                'Validation pesee et entree silo.',
                $weighing,
                [
                    'poids_tare' => $data['poids_tare'],
                    'poids_net' => $poidsNet,
                    'status' => 'validated',
                    'silo_id' => $data['silo_id'],
                ],
                $user
            );
            $this->logActivity(
                'stock_movement',
                'silos',
                'silo_movements',
                $movementId,
                'Mouvement stock silo entree depuis pesee.',
                ['silo_id' => $data['silo_id'], 'stock_kg' => $stockBefore],
                ['silo_id' => $data['silo_id'], 'stock_kg' => $stockAfter, 'quantity_kg' => $poidsNet],
                $user
            );

            $this->db->commit();
        } catch (Exception $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function reference()
    {
        return 'PB-' . date('Ymd-His') . '-' . random_int(100, 999);
    }

    private function findOrCreateTruck(array $data)
    {
        $plateNumber = strtoupper(trim($data['truck_plate_number'] ?? ''));
        $driverName = trim($data['driver_name'] ?? '');
        $driverPhone = trim($data['driver_phone'] ?? '');

        $truck = $this->query(
            "SELECT *
             FROM trucks
             WHERE plate_number = :plate_number
               AND deleted_at IS NULL
             LIMIT 1",
            ['plate_number' => $plateNumber]
        )->fetch();

        if ($truck) {
            $this->query(
                "UPDATE trucks
                 SET supplier_id = :supplier_id,
                     driver_name = :driver_name,
                     driver_phone = :driver_phone,
                     status = 'active'
                 WHERE id = :id",
                [
                    'supplier_id' => $data['supplier_id'],
                    'driver_name' => $driverName !== '' ? $driverName : $truck['driver_name'],
                    'driver_phone' => $driverPhone !== '' ? $driverPhone : $truck['driver_phone'],
                    'id' => $truck['id'],
                ]
            );

            return (int) $truck['id'];
        }

        $this->query(
            "INSERT INTO trucks (supplier_id, plate_number, driver_name, driver_phone, status)
             VALUES (:supplier_id, :plate_number, :driver_name, :driver_phone, 'active')",
            [
                'supplier_id' => $data['supplier_id'],
                'plate_number' => $plateNumber,
                'driver_name' => $driverName,
                'driver_phone' => $driverPhone,
            ]
        );

        return (int) $this->db->lastInsertId();
    }
}
