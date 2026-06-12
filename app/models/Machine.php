<?php

class Machine extends Model
{
    protected $table = 'machines';

    public function allWithPerformance()
    {
        return $this->query(
            "SELECT machines.*,
                    COALESCE(SUM(machine_feeds.quantity_kg), 0) AS fed_quantity_kg,
                    COALESCE(SUM(production_batches.output_quantity_kg), 0) AS output_quantity_kg,
                    COALESCE(AVG(CASE
                        WHEN production_batches.input_quantity_kg > 0
                        THEN (production_batches.output_quantity_kg / production_batches.input_quantity_kg) * 100
                        ELSE NULL
                    END), 0) AS yield_rate,
                    COUNT(DISTINCT production_batches.id) AS batches_count
             FROM machines
             LEFT JOIN machine_feeds ON machine_feeds.machine_id = machines.id
                AND machine_feeds.deleted_at IS NULL
             LEFT JOIN production_batches ON production_batches.machine_feed_id = machine_feeds.id
                AND production_batches.deleted_at IS NULL
             WHERE machines.deleted_at IS NULL
             GROUP BY machines.id
             ORDER BY machines.name ASC"
        )->fetchAll();
    }

    public function findActive($id)
    {
        return $this->query(
            "SELECT id, name, code, machine_type, capacity_kg_hour, status
             FROM machines
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1",
            ['id' => $id]
        )->fetch();
    }

    public function createMachine(array $data)
    {
        $this->query(
            "INSERT INTO machines (name, code, machine_type, capacity_kg_hour, status)
             VALUES (:name, :code, :machine_type, :capacity_kg_hour, :status)",
            [
                'name' => $data['name'],
                'code' => $this->uniqueCode($data['name']),
                'machine_type' => $data['machine_type'],
                'capacity_kg_hour' => $data['capacity_kg_hour'] ?: null,
                'status' => $data['status'],
            ]
        );

        return $this->db->lastInsertId();
    }

    public function updateMachine($id, array $data)
    {
        $this->query(
            "UPDATE machines
             SET name = :name,
                 machine_type = :machine_type,
                 capacity_kg_hour = :capacity_kg_hour,
                 status = :status
             WHERE id = :id AND deleted_at IS NULL",
            [
                'name' => $data['name'],
                'machine_type' => $data['machine_type'],
                'capacity_kg_hour' => $data['capacity_kg_hour'] ?: null,
                'status' => $data['status'],
                'id' => $id,
            ]
        );
    }

    public function toggleStatus($id)
    {
        $this->query(
            "UPDATE machines
             SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END
             WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        );
    }

    private function uniqueCode($name)
    {
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', trim($name)));
        $base = trim($base, '-') ?: 'MACHINE';
        $code = substr($base, 0, 60);
        $suffix = 1;

        while ($this->codeExists($code)) {
            $code = substr($base, 0, 55) . '-' . $suffix;
            $suffix++;
        }

        return $code;
    }

    private function codeExists($code)
    {
        $row = $this->query(
            "SELECT COUNT(*) AS total FROM machines WHERE code = :code",
            ['code' => $code]
        )->fetch();

        return (int) $row['total'] > 0;
    }
}
