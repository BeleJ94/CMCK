<?php

class Truck extends Model
{
    protected $table = 'trucks';

    public function active()
    {
        return $this->query(
            "SELECT trucks.id,
                    trucks.plate_number,
                    trucks.driver_name,
                    trucks.driver_phone,
                    trucks.status,
                    trucks.supplier_id,
                    suppliers.name AS supplier_name
             FROM trucks
             LEFT JOIN suppliers ON suppliers.id = trucks.supplier_id
             WHERE trucks.deleted_at IS NULL
             ORDER BY trucks.plate_number ASC"
        )->fetchAll();
    }

    public function findActive($id)
    {
        return $this->query(
            "SELECT id, supplier_id, plate_number, driver_name, driver_phone, status
             FROM trucks
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1",
            ['id' => $id]
        )->fetch();
    }

    public function plateExists($plateNumber, $ignoreId = null)
    {
        $sql = "SELECT COUNT(*) AS total
                FROM trucks
                WHERE plate_number = :plate_number
                  AND deleted_at IS NULL";
        $params = ['plate_number' => $plateNumber];

        if ($ignoreId !== null) {
            $sql .= " AND id <> :id";
            $params['id'] = $ignoreId;
        }

        $row = $this->query($sql, $params)->fetch();

        return (int) $row['total'] > 0;
    }

    public function suppliersForSelect()
    {
        return $this->query(
            "SELECT id, name
             FROM suppliers
             WHERE deleted_at IS NULL
               AND status IN ('active', 'validated')
             ORDER BY name ASC"
        )->fetchAll();
    }

    public function createTruck(array $data)
    {
        $this->query(
            "INSERT INTO trucks (supplier_id, plate_number, driver_name, driver_phone, status)
             VALUES (:supplier_id, :plate_number, :driver_name, :driver_phone, :status)",
            $this->payload($data)
        );

        return $this->db->lastInsertId();
    }

    public function updateTruck($id, array $data)
    {
        $payload = $this->payload($data);
        $payload['id'] = $id;

        $this->query(
            "UPDATE trucks
             SET supplier_id = :supplier_id,
                 plate_number = :plate_number,
                 driver_name = :driver_name,
                 driver_phone = :driver_phone,
                 status = :status
             WHERE id = :id AND deleted_at IS NULL",
            $payload
        );
    }

    public function softDelete($id)
    {
        $this->query(
            "UPDATE trucks
             SET deleted_at = NOW(), status = 'inactive'
             WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
    }

    private function payload(array $data)
    {
        return [
            'supplier_id' => $data['supplier_id'] ?: null,
            'plate_number' => strtoupper($data['plate_number']),
            'driver_name' => $data['driver_name'] ?: null,
            'driver_phone' => $data['driver_phone'] ?: null,
            'status' => $data['status'],
        ];
    }
}
