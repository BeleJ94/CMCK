<?php

class Supplier extends Model
{
    protected $table = 'suppliers';

    public function active()
    {
        return $this->query(
            "SELECT id, name, phone, address, rccm, id_nat, status, created_at
             FROM suppliers
             WHERE deleted_at IS NULL
             ORDER BY name ASC"
        )->fetchAll();
    }

    public function findActive($id)
    {
        return $this->query(
            "SELECT id, name, phone, address, rccm, id_nat, status
             FROM suppliers
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1",
            ['id' => $id]
        )->fetch();
    }

    public function createSupplier(array $data)
    {
        $this->query(
            "INSERT INTO suppliers (name, phone, address, rccm, id_nat, status)
             VALUES (:name, :phone, :address, :rccm, :id_nat, :status)",
            $this->payload($data)
        );

        return $this->db->lastInsertId();
    }

    public function updateSupplier($id, array $data)
    {
        $payload = $this->payload($data);
        $payload['id'] = $id;

        $this->query(
            "UPDATE suppliers
             SET name = :name,
                 phone = :phone,
                 address = :address,
                 rccm = :rccm,
                 id_nat = :id_nat,
                 status = :status
             WHERE id = :id AND deleted_at IS NULL",
            $payload
        );
    }

    public function softDelete($id)
    {
        $this->query(
            "UPDATE suppliers
             SET deleted_at = NOW(), status = 'inactive'
             WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
    }

    private function payload(array $data)
    {
        return [
            'name' => $data['name'],
            'phone' => $data['phone'] ?: null,
            'address' => $data['address'] ?: null,
            'rccm' => $data['rccm'] ?: null,
            'id_nat' => $data['id_nat'] ?: null,
            'status' => $data['status'],
        ];
    }
}
