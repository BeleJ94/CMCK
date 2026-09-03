USE cmck_milltrack;

ALTER TABLE roles
  ADD COLUMN is_sensitive TINYINT(1) NOT NULL DEFAULT 0 AFTER slug,
  ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER is_sensitive;

ALTER TABLE users MODIFY role_id INT UNSIGNED NULL;

CREATE TABLE permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  component VARCHAR(100) NOT NULL,
  action ENUM('read','create','validate','update','delete','administer') NOT NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_permissions_component_action (component, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id),
  CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id),
  CONSTRAINT fk_role_permissions_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_role_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  site_id INT UNSIGNED NULL,
  assignment_type ENUM('direct','delegation','legacy') NOT NULL DEFAULT 'direct',
  starts_at DATETIME NOT NULL,
  expires_at DATETIME NULL,
  approval_status ENUM('pending','approved','rejected','revoked') NOT NULL DEFAULT 'pending',
  requested_by INT UNSIGNED NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  revoked_by INT UNSIGNED NULL,
  revoked_at DATETIME NULL,
  reason VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  KEY idx_ura_effective (user_id, site_id, approval_status, starts_at, expires_at, deleted_at),
  KEY idx_ura_role (role_id, approval_status),
  CONSTRAINT fk_ura_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_ura_role FOREIGN KEY (role_id) REFERENCES roles(id),
  CONSTRAINT fk_ura_site FOREIGN KEY (site_id) REFERENCES sites(id),
  CONSTRAINT fk_ura_requester FOREIGN KEY (requested_by) REFERENCES users(id),
  CONSTRAINT fk_ura_approver FOREIGN KEY (approved_by) REFERENCES users(id),
  CONSTRAINT fk_ura_revoker FOREIGN KEY (revoked_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rbac_approval_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id BIGINT UNSIGNED NOT NULL,
  actor_id INT UNSIGNED NULL,
  event_type ENUM('requested','approved','rejected','revoked','expired') NOT NULL,
  details VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_rbac_events_assignment (assignment_id, created_at),
  CONSTRAINT fk_rbac_events_assignment FOREIGN KEY (assignment_id) REFERENCES user_role_assignments(id),
  CONSTRAINT fk_rbac_events_actor FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, slug, is_sensitive, is_system, status) VALUES
('Administrateur système','administrateur-systeme',1,1,'active'),
('Directeur général','directeur-general',1,1,'active'),
('Directeur financier','directeur-financier',1,1,'active'),
('Auditeur','auditeur',0,1,'active'),
('Comptable','comptable',0,1,'active'),
('Directeur de site','directeur-site',1,1,'active'),
('Responsable de site','responsable-site',1,1,'active'),
('Gestionnaire de stock','gestionnaire-stock',0,1,'active'),
('Opérateur de production','operateur-production',0,1,'active'),
('Chauffeur/Logistique','chauffeur-logistique',0,1,'active'),
('Opérateur de saisie','operateur-saisie',0,1,'active')
ON DUPLICATE KEY UPDATE name=VALUES(name), is_sensitive=VALUES(is_sensitive), is_system=1, status='active';

UPDATE roles SET is_sensitive=1, is_system=1 WHERE slug IN ('administrateur','direction');

INSERT INTO permissions (component, action, description)
SELECT c.component, a.action, CONCAT(a.action, ' ', c.component)
FROM (
 SELECT 'dashboard' component UNION ALL SELECT 'reports' UNION ALL SELECT 'suppliers' UNION ALL
 SELECT 'trucks' UNION ALL SELECT 'weighings' UNION ALL SELECT 'silos' UNION ALL SELECT 'machines' UNION ALL
 SELECT 'machine-feeds' UNION ALL SELECT 'production' UNION ALL SELECT 'waste' UNION ALL SELECT 'packaging' UNION ALL
 SELECT 'finished-stocks' UNION ALL SELECT 'distributions' UNION ALL SELECT 'alerts' UNION ALL
 SELECT 'activity-logs' UNION ALL SELECT 'sites' UNION ALL SELECT 'users' UNION ALL SELECT 'rbac'
) c CROSS JOIN (
 SELECT 'read' action UNION ALL SELECT 'create' UNION ALL SELECT 'validate' UNION ALL
 SELECT 'update' UNION ALL SELECT 'delete' UNION ALL SELECT 'administer'
) a;

-- Administrateurs historiques et cible : droits complets.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('administrateur','administrateur-systeme');

-- Directions : lecture consolidee ; DG peut valider, DF administre les rapports financiers.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('direction','directeur-general','directeur-financier','auditeur') AND p.action='read';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.action='validate'
WHERE r.slug IN ('direction','directeur-general') AND p.component IN ('weighings','production','distributions');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.action IN ('read','create','update','validate')
WHERE r.slug='directeur-financier' AND p.component IN ('reports','finished-stocks','distributions');

-- Encadrement de site.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('directeur-site','responsable-site') AND p.component NOT IN ('rbac','users','sites','activity-logs') AND p.action IN ('read','create','update','validate');

-- Profils operationnels cibles.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component IN ('silos','finished-stocks','machine-feeds','packaging','distributions') AND p.action IN ('read','create','update')
WHERE r.slug='gestionnaire-stock';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component IN ('machines','machine-feeds','production','waste','packaging') AND p.action IN ('read','create','update')
WHERE r.slug='operateur-production';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component IN ('trucks','weighings','distributions') AND p.action IN ('read','create')
WHERE r.slug='chauffeur-logistique';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug='operateur-saisie' AND p.action IN ('read','create') AND p.component NOT IN ('rbac','users','sites','activity-logs');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component IN ('reports','suppliers','trucks') AND p.action IN ('read','create','update')
WHERE r.slug='comptable';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component='dashboard' AND p.action='read'
WHERE r.slug IN ('directeur-site','responsable-site','gestionnaire-stock','operateur-production','chauffeur-logistique','operateur-saisie','comptable');

-- Compatibilite fonctionnelle des roles historiques.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component IN ('dashboard','suppliers','trucks','weighings') AND p.action IN ('read','create','update','validate') WHERE r.slug='agent-pont-bascule';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component IN ('dashboard','silos','machine-feeds') AND p.action IN ('read','create') WHERE r.slug='agent-silo';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component IN ('dashboard','machines','machine-feeds','production','waste') AND p.action IN ('read','create','update','validate') WHERE r.slug='agent-production';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component IN ('dashboard','packaging','finished-stocks') AND p.action IN ('read','create') WHERE r.slug='agent-emballage';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component IN ('dashboard','finished-stocks','distributions') AND p.action IN ('read','create') WHERE r.slug='agent-distribution';

-- Reprise controlee : une affectation historique approuvee par site existant.
INSERT INTO user_role_assignments (user_id, role_id, site_id, assignment_type, starts_at, approval_status, requested_by, approved_by, approved_at, reason)
SELECT u.id, u.role_id, us.site_id, 'legacy', u.created_at, 'approved', u.id, u.id, NOW(), 'Reprise automatique du role historique'
FROM users u INNER JOIN user_sites us ON us.user_id=u.id AND us.status='active' AND us.deleted_at IS NULL
WHERE u.role_id IS NOT NULL AND u.deleted_at IS NULL;

INSERT INTO user_role_assignments (user_id, role_id, site_id, assignment_type, starts_at, approval_status, requested_by, approved_by, approved_at, reason)
SELECT u.id,u.role_id,NULL,'legacy',u.created_at,'approved',u.id,u.id,NOW(),'Acces consolide historique'
FROM users u INNER JOIN roles r ON r.id=u.role_id
WHERE r.slug IN ('administrateur','direction') AND u.deleted_at IS NULL;

INSERT INTO rbac_approval_events (assignment_id, actor_id, event_type, details)
SELECT id, approved_by, 'approved', 'Reprise migration 010' FROM user_role_assignments WHERE assignment_type='legacy';
