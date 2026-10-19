<?php

// This test only creates data inside a transaction on a disposable schema.
if (!preg_match('/^dagril_porcin_[a-f0-9]{12}_test$/D', getenv('DAGRIL_DB_DATABASE') ?: '')) {
    throw new RuntimeException('Base jetable porcine obligatoire.');
}
require_once __DIR__ . '/integration/TestBootstrap.php';
$db = DagrilIntegrationTest::boot();

function managerSql(string $sql, array $params = []): PDOStatement
{
    global $db;
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function managerCheck(bool $condition, string $message): void
{
    DagrilIntegrationTest::check($condition, $message);
}

function managerRole(string $slug): int
{
    $id = (int) managerSql('SELECT id FROM roles WHERE slug=? AND deleted_at IS NULL', [$slug])->fetchColumn();
    if (!$id) { throw new RuntimeException('Rôle de référence manquant : ' . $slug); }
    return $id;
}

function managerUser(string $label, array $sites): int
{
    global $db;
    managerSql("INSERT INTO users(role_id,name,email,password,status) VALUES(NULL,?,?,'unused','active')", [
        $label, 'self-validation-' . bin2hex(random_bytes(6)) . '@example.test',
    ]);
    $id = (int) $db->lastInsertId();
    foreach (array_values(array_unique($sites)) as $index => $site) {
        managerSql("INSERT INTO user_sites(user_id,site_id,is_default,status) VALUES(?,?,?,'active')", [$id, $site, $index === 0 ? 1 : 0]);
    }
    return $id;
}

function managerAssignment(int $user, int $role, ?int $site): int
{
    global $db;
    managerSql("INSERT INTO user_role_assignments(user_id,role_id,site_id,assignment_type,starts_at,approval_status) VALUES(?,?,?,'direct',NOW(),'approved')", [$user, $role, $site]);
    return (int) $db->lastInsertId();
}

function managerLogin(int $user, int $site): void
{
    $row = managerSql('SELECT * FROM users WHERE id=?', [$user])->fetch();
    Auth::login($row);
    Auth::selectSite((string) $site);
}

$db->beginTransaction();
try {
    $db->exec(file_get_contents(__DIR__ . '/../database/038_add_butchery_manager_role.sql'));
    $db->exec(file_get_contents(__DIR__ . '/../database/039_add_farm_manager_roles.sql'));
    $farm = (int) managerSql("SELECT id FROM sites WHERE code='FARM-DIK'")->fetchColumn();
    $otherFarm = (int) managerSql("SELECT id FROM sites WHERE code='FARM-MUT'")->fetchColumn();
    $butchery = (int) managerSql("SELECT id FROM sites WHERE code='BOUCH'")->fetchColumn();
    $industrial = (int) managerSql("SELECT id FROM sites WHERE code NOT LIKE 'FARM-%' AND code<>'BOUCH' AND status='active' AND deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
    if (!$farm || !$otherFarm || !$butchery || !$industrial) { throw new RuntimeException('Sites de référence manquants.'); }
    $sites = [$farm, $otherFarm, $butchery, $industrial];
    $livestockRole = managerRole('responsable-elevage');
    $farmRole = managerRole('responsable-ferme');
    $butcheryRole = managerRole('responsable-boucherie');
    $siteRole = managerRole('responsable-site');

    foreach (['responsable-elevage' => ['livestock'], 'responsable-ferme' => ['agriculture', 'livestock']] as $slug => $components) {
        foreach ($components as $component) {
            $count = (int) managerSql("SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.component=? AND p.action IN('read','create','update','validate')", [managerRole($slug), $component])->fetchColumn();
            managerCheck($count === 4, $slug . ' possède les droits de saisie et validation pour ' . $component);
        }
    }

    $livestock = managerUser('Responsable élevage sans rôle historique', $sites);
    $livestockAssignment = managerAssignment($livestock, $livestockRole, $farm);
    managerLogin($livestock, $farm);
    managerCheck(Auth::user()['role_id'] === null, 'Le responsable utilise une attribution sans rôle historique');
    managerCheck(Auth::canSelfValidate($livestock, 'livestock', $farm), 'Le responsable élevage peut valider sa propre opération sur sa ferme');
    managerCheck(!Auth::canSelfValidate($livestock), 'Un responsable sans contexte métier ne reçoit pas une exception globale');
    managerCheck(!Auth::canSelfValidate($livestock, 'agriculture', $farm), 'Le responsable élevage ne reçoit pas les droits du responsable agricole');
    managerCheck(!Auth::canSelfValidate($livestock, 'butchery', $butchery), 'Le responsable élevage ne reçoit pas les droits de boucherie');
    managerCheck(!Auth::canSelfValidate($livestock, 'livestock', $otherFarm), 'Une attribution élevage ne couvre pas une autre ferme pourtant accessible');
    managerCheck(!Auth::canSelfValidate($livestock + 1000000, 'livestock', $farm), 'L’exception ne peut pas être utilisée pour une autre identité');

    $farmManager = managerUser('Responsable ferme sans rôle historique', $sites);
    managerAssignment($farmManager, $farmRole, $farm);
    managerLogin($farmManager, $farm);
    managerCheck(Auth::canSelfValidate($farmManager, 'agriculture', $farm), 'Le responsable ferme peut valider ses récoltes et transports');
    managerCheck(Auth::canSelfValidate($farmManager, 'livestock', $farm), 'Le responsable ferme peut valider les opérations d’élevage');
    managerCheck(!Auth::canSelfValidate($farmManager, 'butchery', $farm), 'Le responsable ferme ne reçoit pas une exception boucherie');
    managerCheck(!Auth::canSelfValidate($farmManager, 'agriculture', $otherFarm), 'Le responsable ferme reste limité au site de son attribution');

    $butcher = managerUser('Responsable boucherie sans rôle historique', $sites);
    $butcheryAssignment = managerAssignment($butcher, $butcheryRole, $butchery);
    managerLogin($butcher, $butchery);
    managerCheck(Auth::canSelfValidate($butcher, 'butchery', $butchery), 'Le responsable boucherie peut contrôler et valider ses transformations');
    managerCheck(!Auth::canSelfValidate($butcher, 'livestock', $farm), 'Le responsable boucherie ne reçoit pas une exception élevage');
    managerCheck(!Auth::canSelfValidate($butcher, 'butchery', $farm), 'Le responsable boucherie ne déborde pas du site de son attribution');

    // Each invalid state must remove the exception, even while the session stays open.
    foreach ([
        'pending' => "approval_status='pending'",
        'revoked' => "approval_status='revoked'",
        'expired' => "expires_at=DATE_SUB(NOW(),INTERVAL 1 DAY)",
        'future' => "starts_at=DATE_ADD(NOW(),INTERVAL 1 DAY)",
        'deleted' => 'deleted_at=NOW()',
    ] as $label => $change) {
        managerSql("UPDATE user_role_assignments SET approval_status='approved',starts_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE),expires_at=NULL,deleted_at=NULL WHERE id=?", [$butcheryAssignment]);
        managerSql('UPDATE user_role_assignments SET ' . $change . ' WHERE id=?', [$butcheryAssignment]);
        managerCheck(!Auth::canSelfValidate($butcher, 'butchery', $butchery), 'Attribution ' . $label . ' : auto-validation refusée immédiatement');
    }
    managerSql("UPDATE user_role_assignments SET approval_status='approved',starts_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE),expires_at=NULL,deleted_at=NULL WHERE id=?", [$butcheryAssignment]);
    managerCheck(Auth::canSelfValidate($butcher, 'butchery', $butchery), 'La restauration d’une attribution active rétablit l’exception');
    managerSql("UPDATE roles SET status='inactive' WHERE id=?", [$butcheryRole]);
    managerCheck(!Auth::canSelfValidate($butcher, 'butchery', $butchery), 'Un rôle inactif ne permet aucune auto-validation');
    managerSql("UPDATE roles SET status='active',deleted_at=NOW() WHERE id=?", [$butcheryRole]);
    managerCheck(!Auth::canSelfValidate($butcher, 'butchery', $butchery), 'Un rôle supprimé ne permet aucune auto-validation');
    managerSql("UPDATE roles SET status='active',deleted_at=NULL WHERE id=?", [$butcheryRole]);

    // A manager title never replaces an effective validation permission.
    $validatePermission = (int) managerSql("SELECT id FROM permissions WHERE component='butchery' AND action='validate'")->fetchColumn();
    managerSql('DELETE FROM role_permissions WHERE role_id=? AND permission_id=?', [$butcheryRole, $validatePermission]);
    managerCheck(!Auth::canSelfValidate($butcher, 'butchery', $butchery), 'Le responsable sans droit effectif de validation est refusé');

    $suffix = bin2hex(random_bytes(4));
    managerSql("INSERT INTO roles(name,slug,status) VALUES('Opérateur ordinaire de test',?,'active')", ['self-operator-' . $suffix]);
    $operatorRole = (int) $db->lastInsertId();
    managerSql("INSERT INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE component IN('agriculture','livestock','butchery','production') AND action IN('read','create','update','validate')", [$operatorRole]);
    $operator = managerUser('Opérateur ayant saisie et validation', $sites);
    managerAssignment($operator, $operatorRole, null);
    managerLogin($operator, $butchery);
    managerCheck(Auth::can('butchery', 'create', $butchery) && Auth::can('butchery', 'validate', $butchery), 'L’opérateur ordinaire possède bien les permissions de saisie et validation');
    foreach (['agriculture' => $farm, 'livestock' => $farm, 'butchery' => $butchery, 'production' => $industrial] as $component => $site) {
        managerCheck(!Auth::canSelfValidate($operator, $component, $site), 'Les permissions seules ne donnent pas l’auto-validation pour ' . $component);
    }
    managerAssignment($butcher, $operatorRole, $butchery);
    managerLogin($butcher, $butchery);
    managerCheck(Auth::canSelfValidate($butcher, 'butchery', $butchery), 'Le droit de validation peut provenir d’un autre rôle actif sur le même site');
    managerSql('INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?)', [$butcheryRole, $validatePermission]);
    managerSql("UPDATE user_sites SET status='inactive' WHERE user_id=? AND site_id=?", [$butcher, $butchery]);
    managerCheck(!Auth::canSelfValidate($butcher, 'butchery', $butchery), 'L’accès utilisateur au site reste obligatoire malgré les rôles');
    managerSql("UPDATE user_sites SET status='active' WHERE user_id=? AND site_id=?", [$butcher, $butchery]);

    // Generic site managers only qualify in the farm/butchery modules of those sites.
    $siteManager = managerUser('Responsable de site de test', $sites);
    managerAssignment($siteManager, $siteRole, null);
    managerSql("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT ?,id FROM permissions WHERE component IN('agriculture','livestock','butchery','production') AND action='validate'", [$siteRole]);
    managerLogin($siteManager, $farm);
    managerCheck(Auth::canSelfValidate($siteManager, 'agriculture', $farm), 'Le responsable de site agricole peut valider ses opérations agricoles');
    managerCheck(Auth::canSelfValidate($siteManager, 'livestock', $farm), 'Le responsable de site agricole peut valider ses opérations d’élevage');
    managerCheck(Auth::canSelfValidate($siteManager, 'butchery', $butchery), 'Le responsable du site boucherie peut valider ses opérations de boucherie');
    managerCheck(!Auth::canSelfValidate($siteManager, 'butchery', $farm), 'Le responsable de site ne bénéficie pas de l’exception boucherie sur une ferme');
    managerCheck(!Auth::canSelfValidate($siteManager, 'agriculture', $butchery), 'Le responsable de site ne bénéficie pas de l’exception agricole à la boucherie');
    managerCheck(!Auth::canSelfValidate($siteManager, 'production', $industrial), 'L’exception responsable de site ne couvre pas la production industrielle');
    managerCheck(!Auth::canSelfValidate($siteManager, 'livestock', $industrial), 'Un site industriel ne bénéficie pas de l’exception élevage');

    // Both administrators work through effective RBAC assignments, not users.role_id.
    foreach (['administrateur', 'administrateur-systeme'] as $slug) {
        $admin = managerUser('Administrateur attribué ' . $slug, $sites);
        $adminRole = managerRole($slug);
        $adminAssignment = managerAssignment($admin, $adminRole, null);
        managerLogin($admin, $farm);
        managerCheck(Auth::user()['role_id'] === null && Auth::canSelfValidate($admin), $slug . ' attribué sans rôle historique conserve l’exception administrative');
        foreach (['livestock' => $farm, 'agriculture' => $farm, 'butchery' => $butchery, 'production' => $industrial] as $component => $site) {
            managerCheck(Auth::canSelfValidate($admin, $component, $site), $slug . ' peut valider sa création pour ' . $component);
        }
        managerSql("UPDATE user_role_assignments SET approval_status='pending' WHERE id=?", [$adminAssignment]);
        managerCheck(!Auth::canSelfValidate($admin, 'livestock', $farm), $slug . ' en attente ne donne pas de privilège administratif');
    }

    // A permitted manager can validate their own record; the ordinary user still cannot.
    managerLogin($livestock, $farm);
    $authorization = new AuthorizationService($db);
    $authorization->assertCanValidateRecord($livestock, 'livestock', ['site_id' => $farm, 'created_by' => $livestock]);
    managerCheck(true, 'La validation centralisée transmet le composant et le site de l’opération');
    managerLogin($operator, $farm);
    DagrilIntegrationTest::expectException(function () use ($authorization, $operator, $farm) {
        $authorization->assertCanValidateRecord($operator, 'livestock', ['site_id' => $farm, 'created_by' => $operator]);
    }, 'La validation centralisée maintient la séparation pour l’opérateur ordinaire');
    $authorization->assertCanValidateRecord($operator, 'livestock', ['site_id' => $farm, 'created_by' => $livestock]);
    managerCheck(true, 'La validation de la création d’un autre utilisateur reste possible');
} finally {
    if ($db->inTransaction()) { $db->rollBack(); }
}

DagrilIntegrationTest::finish();
