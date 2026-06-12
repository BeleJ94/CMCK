<?php

class User extends Model
{
    protected $table = 'users';

    public function findByEmail($email)
    {
        $statement = $this->query(
            'SELECT users.*, roles.name AS role_name, roles.slug AS role_slug
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
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
}
