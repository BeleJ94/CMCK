<?php

class AccessControl extends Model
{
    protected $table = 'user_role_assignments';

    public function dashboardData()
    {
        return [
            'roles' => $this->query("SELECT * FROM roles WHERE deleted_at IS NULL AND status='active' ORDER BY name")->fetchAll(),
            'users' => $this->query("SELECT id,name,email FROM users WHERE deleted_at IS NULL ORDER BY name")->fetchAll(),
            'sites' => $this->query("SELECT id,code,name FROM sites WHERE deleted_at IS NULL AND status='active' ORDER BY name")->fetchAll(),
            'assignments' => $this->query("SELECT ura.*,u.name user_name,r.name role_name,r.is_sensitive,s.code site_code,requester.name requester_name,approver.name approver_name FROM user_role_assignments ura INNER JOIN users u ON u.id=ura.user_id INNER JOIN roles r ON r.id=ura.role_id LEFT JOIN sites s ON s.id=ura.site_id LEFT JOIN users requester ON requester.id=ura.requested_by LEFT JOIN users approver ON approver.id=ura.approved_by WHERE ura.deleted_at IS NULL ORDER BY ura.created_at DESC")->fetchAll(),
            'permissions' => $this->query("SELECT p.*,GROUP_CONCAT(rp.role_id) role_ids FROM permissions p LEFT JOIN role_permissions rp ON rp.permission_id=p.id GROUP BY p.id ORDER BY p.component,p.action")->fetchAll(),
        ];
    }

    public function createAssignment(array $data, array $actor)
    {
        $this->db->beginTransaction();
        try {
            $role = $this->query("SELECT * FROM roles WHERE id=:id AND status='active' AND deleted_at IS NULL FOR UPDATE", ['id'=>$data['role_id']])->fetch();
            if (!$role) { throw new RuntimeException('Role introuvable.'); }
            if (!empty($data['site_id'])) { Auth::requireSiteAccess($data['site_id']); }
            $startsAt = $data['starts_at'] ?: date('Y-m-d H:i:s');
            $expiresAt = $data['expires_at'] ?: null;
            if ($expiresAt !== null && strtotime($expiresAt) <= strtotime($startsAt)) { throw new RuntimeException('La date expiration doit suivre la date de debut.'); }
            if ($data['assignment_type'] === 'delegation' && $expiresAt === null) { throw new RuntimeException('Une delegation doit avoir une expiration.'); }
            $status = !empty($role['is_sensitive']) ? 'pending' : 'approved';
            $approvedBy = $status === 'approved' ? ($actor['id'] ?? null) : null;
            $this->query("INSERT INTO user_role_assignments (user_id,role_id,site_id,assignment_type,starts_at,expires_at,approval_status,requested_by,approved_by,approved_at,reason) VALUES (:user_id,:role_id,:site_id,:assignment_type,:starts_at,:expires_at,:approval_status,:requested_by,:approved_by," . ($approvedBy ? 'NOW()' : 'NULL') . ",:reason)", [
                'user_id'=>$data['user_id'],'role_id'=>$data['role_id'],'site_id'=>$data['site_id'] ?: null,'assignment_type'=>$data['assignment_type'],'starts_at'=>$startsAt,'expires_at'=>$expiresAt,'approval_status'=>$status,'requested_by'=>$actor['id'] ?? null,'approved_by'=>$approvedBy,'reason'=>$data['reason'] ?: null
            ]);
            $id = $this->db->lastInsertId();
            $this->event($id, $actor['id'] ?? null, 'requested', 'Attribution demandee');
            if ($status === 'approved') { $this->event($id, $actor['id'] ?? null, 'approved', 'Role non sensible approuve lors de attribution'); }
            $this->logActivity('rbac_assign', 'rbac', 'user_role_assignments', $id, 'Attribution de role.', null, $data, $actor);
            $this->db->commit(); return $id;
        } catch (Exception $e) { $this->db->rollBack(); throw $e; }
    }

    public function decide($id, $decision, array $actor)
    {
        $this->db->beginTransaction();
        try {
            $row=$this->query("SELECT * FROM user_role_assignments WHERE id=:id AND deleted_at IS NULL FOR UPDATE",['id'=>$id])->fetch();
            if (!$row || $row['approval_status']!=='pending') { throw new RuntimeException('Attribution non disponible pour approbation.'); }
            if ((int)$row['requested_by']===(int)$actor['id']) { throw new RuntimeException('Une deuxieme personne doit approuver ce role sensible.'); }
            $status=$decision==='approve'?'approved':'rejected';
            $this->query("UPDATE user_role_assignments SET approval_status=:status,approved_by=:actor,approved_at=NOW() WHERE id=:id",['status'=>$status,'actor'=>$actor['id'],'id'=>$id]);
            $this->event($id,$actor['id'],$status,$status==='approved'?'Deuxieme approbation':'Attribution rejetee');
            $this->logActivity('rbac_'.$status,'rbac','user_role_assignments',$id,'Decision sur attribution RBAC.',$row,['approval_status'=>$status],$actor);
            $this->db->commit();
        } catch(Exception $e){$this->db->rollBack();throw $e;}
    }

    public function revoke($id, array $actor)
    {
        $this->db->beginTransaction();
        try {
            $row=$this->query("SELECT * FROM user_role_assignments WHERE id=:id AND deleted_at IS NULL FOR UPDATE",['id'=>$id])->fetch();
            if(!$row){throw new RuntimeException('Attribution introuvable.');}
            if($row['approval_status']==='revoked'){throw new RuntimeException('Attribution deja revoquee.');}
            $this->query("UPDATE user_role_assignments SET approval_status='revoked',revoked_by=:actor,revoked_at=NOW() WHERE id=:id",['actor'=>$actor['id'],'id'=>$id]);
            $this->event($id,$actor['id'],'revoked','Revocation manuelle');
            $this->logActivity('rbac_revoke','rbac','user_role_assignments',$id,'Revocation RBAC.',$row,['approval_status'=>'revoked'],$actor);
            $this->db->commit();
        } catch(Exception $e){$this->db->rollBack();throw $e;}
    }

    public function syncRolePermissions($roleId, array $permissionIds, array $actor)
    {
        $this->db->beginTransaction();
        try {
            $role=$this->query('SELECT * FROM roles WHERE id=:id FOR UPDATE',['id'=>$roleId])->fetch(); if(!$role){throw new RuntimeException('Role introuvable.');}
            $old=$this->query('SELECT permission_id FROM role_permissions WHERE role_id=:id',['id'=>$roleId])->fetchAll(PDO::FETCH_COLUMN);
            $this->query('DELETE FROM role_permissions WHERE role_id=:id',['id'=>$roleId]);
            foreach(array_unique(array_map('intval',$permissionIds)) as $permissionId){$this->query('INSERT INTO role_permissions(role_id,permission_id,created_by) VALUES(:role,:permission,:actor)',['role'=>$roleId,'permission'=>$permissionId,'actor'=>$actor['id']]);}
            $this->logActivity('rbac_permissions','rbac','roles',$roleId,'Matrice de permissions modifiee.',['permissions'=>$old],['permissions'=>$permissionIds],$actor);
            $this->db->commit();
        }catch(Exception $e){$this->db->rollBack();throw $e;}
    }

    private function event($id,$actor,$type,$details){$this->query('INSERT INTO rbac_approval_events(assignment_id,actor_id,event_type,details) VALUES(:id,:actor,:type,:details)',['id'=>$id,'actor'=>$actor,'type'=>$type,'details'=>$details]);}
}
