<?php

class User extends Model
{
    protected $table = 'users';

    public function findByEmail($email)
    {
        $statement = $this->query(
            'SELECT users.*, roles.name AS role_name, roles.slug AS role_slug
             FROM users
             LEFT JOIN roles ON roles.id = users.role_id
             WHERE users.email = :email
               AND users.deleted_at IS NULL
               AND users.status = :status
             LIMIT 1',
            [
                'email' => $email,
                'status' => 'active',
            ]
        );

        return $statement->fetch();
    }

    public function administrationData()
    {
        $users = $this->query(
            "SELECT u.id,u.name,u.email,u.phone,u.status,u.last_login_at,u.created_at,
                    r.name legacy_role_name,
                    GROUP_CONCAT(DISTINCT CONCAT(s.code,' — ',s.name) ORDER BY s.code SEPARATOR '||') site_names,
                    GROUP_CONCAT(DISTINCT CASE WHEN us.status='active' AND us.deleted_at IS NULL THEN s.id END ORDER BY s.id) site_ids,
                    COUNT(DISTINCT CASE WHEN ura.approval_status='approved' AND ura.starts_at<=NOW()
                         AND (ura.expires_at IS NULL OR ura.expires_at>NOW()) AND ura.deleted_at IS NULL THEN ura.id END) active_roles
             FROM users u
             LEFT JOIN roles r ON r.id=u.role_id
             LEFT JOIN user_sites us ON us.user_id=u.id AND us.status='active' AND us.deleted_at IS NULL
             LEFT JOIN sites s ON s.id=us.site_id AND s.deleted_at IS NULL
             LEFT JOIN user_role_assignments ura ON ura.user_id=u.id
             WHERE u.deleted_at IS NULL
             GROUP BY u.id,u.name,u.email,u.phone,u.status,u.last_login_at,u.created_at,r.name
             ORDER BY u.name,u.id"
        )->fetchAll();

        $sites = $this->query("SELECT id,code,name FROM sites WHERE status='active' AND deleted_at IS NULL ORDER BY code")->fetchAll();
        $stats = ['total'=>count($users),'active'=>0,'pending'=>0,'without_role'=>0];
        foreach ($users as $user) {
            if ($user['status']==='active') { $stats['active']++; }
            if ($user['status']==='pending') { $stats['pending']++; }
            if ((int)$user['active_roles']===0) { $stats['without_role']++; }
        }
        return ['users'=>$users,'sites'=>$sites,'stats'=>$stats];
    }

    public function createAccount(array $data, array $actor)
    {
        $clean = $this->validateAccount($data, false);
        $this->db->beginTransaction();
        try {
            if ($this->query('SELECT id FROM users WHERE email=:email LIMIT 1 FOR UPDATE',['email'=>$clean['email']])->fetch()) {
                throw new RuntimeException('Cette adresse e-mail est déjà utilisée.');
            }
            $this->query("INSERT INTO users(role_id,name,email,password,phone,status) VALUES(NULL,:name,:email,:password,:phone,:status)",[
                'name'=>$clean['name'],'email'=>$clean['email'],'password'=>password_hash($clean['password'],PASSWORD_DEFAULT),'phone'=>$clean['phone'],'status'=>$clean['status']
            ]);
            $id=(int)$this->db->lastInsertId();
            $this->syncSites($id,$clean['site_ids']);
            $this->logActivity('user_create','users','users',$id,'Compte utilisateur créé sans permission métier.',null,['name'=>$clean['name'],'email'=>$clean['email'],'status'=>$clean['status'],'site_ids'=>$clean['site_ids']],$actor);
            $this->db->commit();
            return $id;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateAccount($id, array $data, array $actor)
    {
        $clean = $this->validateAccount($data, true);
        $this->db->beginTransaction();
        try {
            $old=$this->query('SELECT id,name,email,phone,status FROM users WHERE id=:id AND deleted_at IS NULL FOR UPDATE',['id'=>$id])->fetch();
            if (!$old) { throw new RuntimeException('Utilisateur introuvable.'); }
            if ((int)$id===(int)$actor['id'] && $clean['status']!=='active') { throw new RuntimeException('Vous ne pouvez pas désactiver votre propre compte.'); }
            if ($this->query('SELECT id FROM users WHERE email=:email AND id<>:id AND deleted_at IS NULL LIMIT 1',['email'=>$clean['email'],'id'=>$id])->fetch()) {
                throw new RuntimeException('Cette adresse e-mail est déjà utilisée.');
            }
            $params=['id'=>$id,'name'=>$clean['name'],'email'=>$clean['email'],'phone'=>$clean['phone'],'status'=>$clean['status']];
            $passwordSql='';
            if ($clean['password']!=='') { $passwordSql=',password=:password';$params['password']=password_hash($clean['password'],PASSWORD_DEFAULT); }
            $this->query("UPDATE users SET name=:name,email=:email,phone=:phone,status=:status{$passwordSql} WHERE id=:id",$params);
            $this->syncSites((int)$id,$clean['site_ids']);
            $this->logActivity('user_update','users','users',$id,'Compte utilisateur modifié.',$old,['name'=>$clean['name'],'email'=>$clean['email'],'phone'=>$clean['phone'],'status'=>$clean['status'],'site_ids'=>$clean['site_ids'],'password_changed'=>$clean['password']!==''],$actor);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function validateAccount(array $data, $editing)
    {
        $name=trim($data['name']??'');$email=strtolower(trim($data['email']??''));$phone=trim($data['phone']??'');$password=(string)($data['password']??'');
        $status=in_array($data['status']??'', ['active','inactive','pending','validated','cancelled'],true)?$data['status']:'pending';
        if ($name==='' || mb_strlen($name)>150) { throw new RuntimeException('Le nom est obligatoire et limité à 150 caractères.'); }
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || mb_strlen($email)>190) { throw new RuntimeException('Adresse e-mail invalide.'); }
        if ((!$editing || $password!=='') && strlen($password)<8) { throw new RuntimeException('Le mot de passe doit contenir au moins 8 caractères.'); }
        if (mb_strlen($phone)>50) { throw new RuntimeException('Numéro de téléphone trop long.'); }
        $siteIds=array_values(array_unique(array_filter(array_map('intval',(array)($data['site_ids']??[])))));
        return ['name'=>$name,'email'=>$email,'phone'=>$phone?:null,'password'=>$password,'status'=>$status,'site_ids'=>$siteIds];
    }

    private function syncSites($userId, array $siteIds)
    {
        $allowed=[];
        if ($siteIds) {
            $marks=implode(',',array_fill(0,count($siteIds),'?'));
            $allowed=array_map('intval',$this->query("SELECT id FROM sites WHERE id IN ({$marks}) AND status='active' AND deleted_at IS NULL",$siteIds)->fetchAll(PDO::FETCH_COLUMN));
            if (count($allowed)!==count($siteIds)) { throw new RuntimeException('Un site sélectionné est invalide ou inactif.'); }
            if (!Auth::canViewConsolidated()) { foreach($allowed as$siteId){Auth::requireSiteAccess($siteId);} }
        }
        $this->query("UPDATE user_sites SET status='inactive',is_default=0 WHERE user_id=:user AND deleted_at IS NULL",['user'=>$userId]);
        foreach ($allowed as $index=>$siteId) {
            $this->query("INSERT INTO user_sites(user_id,site_id,is_default,status,deleted_at) VALUES(:user,:site,:default,'active',NULL) ON DUPLICATE KEY UPDATE is_default=VALUES(is_default),status='active',deleted_at=NULL",['user'=>$userId,'site'=>$siteId,'default'=>$index===0?1:0]);
        }
    }
}
