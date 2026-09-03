USE cmck_milltrack;

ALTER TABLE documents MODIFY status ENUM('draft','submitted','pending_approval','approved','in_progress','received','rejected','cancelled','closed','pending_validation','validated') NOT NULL DEFAULT 'draft';
UPDATE documents SET status='approved' WHERE status='validated';

CREATE TABLE workflow_definitions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, document_type_id INT UNSIGNED NULL, site_id INT UNSIGNED NULL,
 min_amount DECIMAL(15,2) NULL, max_amount DECIMAL(15,2) NULL, approval_levels TINYINT UNSIGNED NOT NULL DEFAULT 1,
 priority INT NOT NULL DEFAULT 0, status ENUM('active','inactive') NOT NULL DEFAULT 'active', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, deleted_at TIMESTAMP NULL DEFAULT NULL,
 KEY idx_workflow_rule(document_type_id,site_id,status,priority), CONSTRAINT fk_wd_type FOREIGN KEY(document_type_id) REFERENCES document_types(id),
 CONSTRAINT fk_wd_site FOREIGN KEY(site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_steps (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, workflow_definition_id INT UNSIGNED NOT NULL, level_number TINYINT UNSIGNED NOT NULL,
 name VARCHAR(150) NOT NULL, required_role_id INT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_workflow_step(workflow_definition_id,level_number), CONSTRAINT fk_ws_definition FOREIGN KEY(workflow_definition_id) REFERENCES workflow_definitions(id),
 CONSTRAINT fk_ws_role FOREIGN KEY(required_role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_instances (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, workflow_definition_id INT UNSIGNED NULL, document_id BIGINT UNSIGNED NOT NULL,
 current_state ENUM('draft','submitted','pending_approval','approved','in_progress','received','rejected','cancelled','closed') NOT NULL DEFAULT 'draft',
 current_level TINYINT UNSIGNED NOT NULL DEFAULT 0, required_levels TINYINT UNSIGNED NOT NULL DEFAULT 1, amount DECIMAL(15,2) NOT NULL DEFAULT 0,
 created_by INT UNSIGNED NULL, completed_at DATETIME NULL, attempt_number INT UNSIGNED NOT NULL DEFAULT 1, version INT UNSIGNED NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_workflow_document(document_id), KEY idx_workflow_state(current_state,updated_at),
 CONSTRAINT fk_wi_definition FOREIGN KEY(workflow_definition_id) REFERENCES workflow_definitions(id), CONSTRAINT fk_wi_document FOREIGN KEY(document_id) REFERENCES documents(id),
 CONSTRAINT fk_wi_creator FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_level_approvals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, workflow_instance_id BIGINT UNSIGNED NOT NULL, level_number TINYINT UNSIGNED NOT NULL,
 attempt_number INT UNSIGNED NOT NULL DEFAULT 1, approver_id INT UNSIGNED NOT NULL, delegated_by INT UNSIGNED NULL, decision ENUM('approved','rejected') NOT NULL, reason VARCHAR(500) NULL,
 decided_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_workflow_level_decision(workflow_instance_id,attempt_number,level_number),
 CONSTRAINT fk_wla_instance FOREIGN KEY(workflow_instance_id) REFERENCES workflow_instances(id), CONSTRAINT fk_wla_approver FOREIGN KEY(approver_id) REFERENCES users(id),
 CONSTRAINT fk_wla_delegator FOREIGN KEY(delegated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_transitions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, workflow_instance_id BIGINT UNSIGNED NOT NULL, from_state VARCHAR(40) NULL, to_state VARCHAR(40) NOT NULL,
 action VARCHAR(50) NOT NULL, level_number TINYINT UNSIGNED NULL, actor_id INT UNSIGNED NULL, reason VARCHAR(500) NULL,
 metadata TEXT NULL, transitioned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_wt_instance(workflow_instance_id,transitioned_at),
 CONSTRAINT fk_wt_instance FOREIGN KEY(workflow_instance_id) REFERENCES workflow_instances(id), CONSTRAINT fk_wt_actor FOREIGN KEY(actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_delegations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, delegator_id INT UNSIGNED NOT NULL, delegate_id INT UNSIGNED NOT NULL,
 document_type_id INT UNSIGNED NULL, site_id INT UNSIGNED NULL, starts_at DATETIME NOT NULL, expires_at DATETIME NOT NULL,
 status ENUM('active','revoked') NOT NULL DEFAULT 'active', reason VARCHAR(255) NULL, created_by INT UNSIGNED NULL,
 revoked_by INT UNSIGNED NULL, revoked_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, deleted_at TIMESTAMP NULL DEFAULT NULL,
 KEY idx_workflow_delegation(delegate_id,status,starts_at,expires_at), CONSTRAINT fk_wdel_from FOREIGN KEY(delegator_id) REFERENCES users(id),
 CONSTRAINT fk_wdel_to FOREIGN KEY(delegate_id) REFERENCES users(id), CONSTRAINT fk_wdel_type FOREIGN KEY(document_type_id) REFERENCES document_types(id),
 CONSTRAINT fk_wdel_site FOREIGN KEY(site_id) REFERENCES sites(id), CONSTRAINT fk_wdel_creator FOREIGN KEY(created_by) REFERENCES users(id),
 CONSTRAINT fk_wdel_revoker FOREIGN KEY(revoked_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, workflow_instance_id BIGINT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
 event_type VARCHAR(50) NOT NULL, title VARCHAR(190) NOT NULL, message VARCHAR(500) NOT NULL, read_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_wn_user(user_id,read_at,created_at),
 CONSTRAINT fk_wn_instance FOREIGN KEY(workflow_instance_id) REFERENCES workflow_instances(id), CONSTRAINT fk_wn_user FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO workflow_definitions(name,document_type_id,approval_levels,priority)
SELECT CONCAT('Validation ',display_code),id,1,0 FROM document_types;
INSERT INTO workflow_steps(workflow_definition_id,level_number,name)
SELECT id,1,'Approbation niveau 1' FROM workflow_definitions;

INSERT INTO workflow_instances(workflow_definition_id,document_id,current_state,current_level,required_levels,amount,created_by,completed_at)
SELECT wd.id,d.id,d.status,CASE WHEN d.status IN('approved','closed') THEN wd.approval_levels ELSE 0 END,wd.approval_levels,0,d.created_by,CASE WHEN d.status IN('approved','closed') THEN COALESCE(d.validated_at,d.updated_at,d.created_at) ELSE NULL END
FROM documents d LEFT JOIN workflow_definitions wd ON wd.document_type_id=d.document_type_id AND wd.site_id IS NULL AND wd.deleted_at IS NULL;
INSERT INTO workflow_transitions(workflow_instance_id,from_state,to_state,action,actor_id,reason,transitioned_at)
SELECT wi.id,NULL,wi.current_state,'migration',COALESCE(d.validated_by,d.created_by),'Reprise migration 012',d.created_at FROM workflow_instances wi JOIN documents d ON d.id=wi.document_id;

INSERT INTO permissions(component,action,description) VALUES
('workflows','read','Consulter les validations'),('workflows','create','Soumettre une opération'),('workflows','validate','Approuver ou refuser'),
('workflows','update','Faire progresser une opération'),('workflows','delete','Annuler une opération'),('workflows','administer','Configurer les workflows');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.slug IN('administrateur','administrateur-systeme');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component='workflows' AND p.action IN('read','validate','update') WHERE r.slug IN('direction','directeur-general','directeur-site','responsable-site');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component='workflows' AND p.action IN('read','create','update') WHERE r.slug IN('agent-pont-bascule','agent-silo','agent-production','agent-emballage','agent-distribution','gestionnaire-stock','operateur-production','chauffeur-logistique','operateur-saisie');
