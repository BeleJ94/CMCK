<?php

class Machine extends Model
{
    protected $table = 'machines';

    public function allWithPerformance($from = null, $to = null)
    {
        $params=[];$scope=Auth::siteClause('machines.site_id',$params);
        $machines=$this->query("SELECT machines.*,sites.name site_name FROM machines JOIN sites ON sites.id=machines.site_id WHERE machines.deleted_at IS NULL{$scope} ORDER BY machines.name",$params)->fetchAll();
        $rows=[];foreach($machines as$m){$rows[$m['id']]=array_merge($m,['fed_quantity_kg'=>0.0,'output_quantity_kg'=>0.0,'validated_input_kg'=>0.0,'yield_rate'=>null,'batches_count'=>0,'open_count'=>0,'open_quantity_kg'=>0.0,'open_operations'=>[],'history'=>[]]);}
        $feeds=$this->query("SELECT mf.id,mf.machine_id,mf.quantity_kg,mf.fed_at,mf.status,pb.id batch_id,pb.batch_number,pb.status batch_status,pb.actual_input_quantity_kg AS input_quantity_kg,pb.output_quantity_kg,s.name source_name FROM machine_feeds mf JOIN machines ON machines.id=mf.machine_id JOIN silos s ON s.id=mf.silo_id LEFT JOIN production_batches pb ON pb.machine_feed_id=mf.id AND pb.deleted_at IS NULL WHERE machines.deleted_at IS NULL AND mf.deleted_at IS NULL AND mf.status<>'cancelled'{$scope} ORDER BY mf.fed_at DESC,mf.id DESC",$params)->fetchAll();
        $seen=[];
        foreach($feeds as$f){if(!isset($rows[$f['machine_id']]))continue;$m=&$rows[$f['machine_id']];
            if(in_array($f['batch_status'],['pending','in_progress','results_submitted','pending_additional_approval'],true)){
                $m['open_count']++;$m['open_quantity_kg']+=(float)$f['input_quantity_kg'];
                $m['open_operations'][]=['date'=>$f['fed_at'],'reference'=>$f['batch_number'],'source'=>$f['source_name'],'quantity'=>$f['input_quantity_kg'],'status'=>$f['batch_status']];
            }
            $day=substr($f['fed_at'],0,10);if(($from&&$day<$from)||($to&&$day>$to))continue;
            if(!isset($seen[$f['id']])){$m['fed_quantity_kg']+=(float)$f['quantity_kg'];$seen[$f['id']]=true;}
            if($f['batch_id']&&$f['batch_status']!=='cancelled')$m['batches_count']++;
            if($f['batch_status']==='validated'){$m['output_quantity_kg']+=(float)$f['output_quantity_kg'];$m['validated_input_kg']+=(float)$f['input_quantity_kg'];}
            if(count($m['history'])<10)$m['history'][]=['date'=>$f['fed_at'],'reference'=>$f['batch_number']?:'Sans lot','source'=>$f['source_name'],'quantity'=>$f['quantity_kg'],'status'=>$f['batch_status']?:$f['status']];
        }
        unset($m);
        $waste=$this->query("SELECT wp.* FROM waste_processings wp JOIN machines ON machines.id=wp.machine_id WHERE machines.deleted_at IS NULL AND wp.deleted_at IS NULL AND wp.status<>'cancelled'{$scope} ORDER BY wp.processed_at DESC,wp.id DESC",$params)->fetchAll();
        foreach($waste as$w){if(!isset($rows[$w['machine_id']])||$rows[$w['machine_id']]['machine_type']!=='waste')continue;$m=&$rows[$w['machine_id']];if($w['status']==='pending'){$m['open_count']++;$m['open_quantity_kg']+=(float)$w['input_quantity_kg'];$m['open_operations'][]=['date'=>$w['processed_at'],'reference'=>'Traitement #'.$w['id'],'source'=>'Déchets','quantity'=>$w['input_quantity_kg'],'status'=>$w['status']];}
            $day=substr($w['processed_at'],0,10);if(($from&&$day<$from)||($to&&$day>$to))continue;
            $m['fed_quantity_kg']+=(float)$w['input_quantity_kg'];$m['batches_count']++;
            if($w['status']==='validated'){$m['output_quantity_kg']+=(float)$w['output_quantity_kg'];$m['validated_input_kg']+=(float)$w['input_quantity_kg'];}
            if(count($m['history'])<10)$m['history'][]=['date'=>$w['processed_at'],'reference'=>'Traitement #'.$w['id'],'source'=>'Déchets','quantity'=>$w['input_quantity_kg'],'status'=>$w['status']];
        }
        unset($m);foreach($rows as&$m){if($m['validated_input_kg']>0)$m['yield_rate']=100*$m['output_quantity_kg']/$m['validated_input_kg'];}unset($m);
        return array_values($rows);
    }

    public function findActive($id)
    {
        $params = ['id' => $id];
        $siteClause = Auth::siteClause('site_id', $params);
        return $this->query(
            "SELECT id, site_id, name, code, machine_type, capacity_kg_hour, status
             FROM machines
             WHERE id = :id AND deleted_at IS NULL{$siteClause}
             LIMIT 1",
            $params
        )->fetch();
    }

    public function createMachine(array $data)
    {
        $siteId = Auth::currentSiteId() ?? ($data['site_id'] ?? null);
        Auth::requireSiteAccess($siteId);
        Auth::requirePermission('machines','create',$siteId);
        $this->query(
            "INSERT INTO machines (site_id, name, code, machine_type, capacity_kg_hour, status)
             VALUES (:site_id, :name, :code, :machine_type, :capacity_kg_hour, :status)",
            [
                'site_id' => $siteId, 'name' => $data['name'],
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
        $existing=$this->findActive($id);if(!$existing)throw new RuntimeException('Machine introuvable sur ce périmètre.');
        Auth::requireSiteAccess($existing['site_id']);Auth::requirePermission('machines','update',$existing['site_id']);
        $params = [
            'name' => $data['name'], 'machine_type' => $data['machine_type'],
            'capacity_kg_hour' => $data['capacity_kg_hour'] ?: null, 'status' => $data['status'], 'id' => $id,
        ];
        $siteClause = Auth::siteClause('site_id', $params);
        $this->query(
            "UPDATE machines
             SET name = :name,
                 machine_type = :machine_type,
                 capacity_kg_hour = :capacity_kg_hour,
                 status = :status
             WHERE id = :id AND deleted_at IS NULL{$siteClause}",
            $params
        );
    }

    public function toggleStatus($id)
    {
        $existing=$this->findActive($id);if(!$existing)throw new RuntimeException('Machine introuvable sur ce périmètre.');
        Auth::requireSiteAccess($existing['site_id']);Auth::requirePermission('machines','update',$existing['site_id']);
        $params = ['id' => $id];
        $siteClause = Auth::siteClause('site_id', $params);
        $this->query(
            "UPDATE machines
             SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END
             WHERE id = :id AND deleted_at IS NULL{$siteClause}",
            $params
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
