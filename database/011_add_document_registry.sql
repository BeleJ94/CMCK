USE cmck_milltrack;

CREATE TABLE document_types (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(20) NOT NULL UNIQUE, display_code VARCHAR(20) NOT NULL,
 name VARCHAR(150) NOT NULL, entity_type VARCHAR(100) NULL, status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, deleted_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_numbering_rules (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, document_type_id INT UNSIGNED NOT NULL, site_id INT UNSIGNED NULL,
 pattern VARCHAR(150) NOT NULL DEFAULT '{TYPE}/{SITE}/{YEAR}/{SEQ:6}', period_type ENUM('year','month','none') NOT NULL DEFAULT 'year',
 sequence_length TINYINT UNSIGNED NOT NULL DEFAULT 6, reset_each_period TINYINT(1) NOT NULL DEFAULT 1,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, deleted_at TIMESTAMP NULL DEFAULT NULL,
 KEY idx_dnr_lookup(document_type_id,site_id,status,deleted_at), CONSTRAINT fk_dnr_type FOREIGN KEY(document_type_id) REFERENCES document_types(id),
 CONSTRAINT fk_dnr_site FOREIGN KEY(site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_number_counters (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, numbering_rule_id INT UNSIGNED NOT NULL, site_id INT UNSIGNED NOT NULL,
 period_key VARCHAR(20) NOT NULL, last_sequence BIGINT UNSIGNED NOT NULL DEFAULT 0, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_document_counter(numbering_rule_id,site_id,period_key), CONSTRAINT fk_dnc_rule FOREIGN KEY(numbering_rule_id) REFERENCES document_numbering_rules(id),
 CONSTRAINT fk_dnc_site FOREIGN KEY(site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, document_type_id INT UNSIGNED NOT NULL, document_number VARCHAR(190) NOT NULL,
 site_id INT UNSIGNED NOT NULL, period_key VARCHAR(20) NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id BIGINT UNSIGNED NOT NULL,
 status ENUM('draft','pending_validation','validated','cancelled') NOT NULL DEFAULT 'draft', document_date DATETIME NOT NULL,
 created_by INT UNSIGNED NULL, validated_by INT UNSIGNED NULL, validated_at DATETIME NULL, cancelled_by INT UNSIGNED NULL, cancelled_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, deleted_at TIMESTAMP NULL DEFAULT NULL,
 UNIQUE KEY uq_documents_number(document_number), UNIQUE KEY uq_documents_entity_type(document_type_id,entity_type,entity_id),
 KEY idx_documents_site_date(site_id,document_date,status), KEY idx_documents_entity(entity_type,entity_id),
 CONSTRAINT fk_documents_type FOREIGN KEY(document_type_id) REFERENCES document_types(id), CONSTRAINT fk_documents_site FOREIGN KEY(site_id) REFERENCES sites(id),
 CONSTRAINT fk_documents_creator FOREIGN KEY(created_by) REFERENCES users(id), CONSTRAINT fk_documents_validator FOREIGN KEY(validated_by) REFERENCES users(id),
 CONSTRAINT fk_documents_canceller FOREIGN KEY(cancelled_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_attachments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, document_id BIGINT UNSIGNED NOT NULL, original_name VARCHAR(255) NOT NULL,
 storage_path VARCHAR(500) NOT NULL, mime_type VARCHAR(150) NOT NULL, file_size BIGINT UNSIGNED NOT NULL, checksum_sha256 CHAR(64) NOT NULL,
 uploaded_by INT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, deleted_at TIMESTAMP NULL DEFAULT NULL,
 KEY idx_document_attachments(document_id,deleted_at), CONSTRAINT fk_da_document FOREIGN KEY(document_id) REFERENCES documents(id),
 CONSTRAINT fk_da_uploader FOREIGN KEY(uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_status_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, document_id BIGINT UNSIGNED NOT NULL, old_status VARCHAR(40) NULL, new_status VARCHAR(40) NOT NULL,
 changed_by INT UNSIGNED NULL, reason VARCHAR(255) NULL, changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_document_history(document_id,changed_at), CONSTRAINT fk_dsh_document FOREIGN KEY(document_id) REFERENCES documents(id),
 CONSTRAINT fk_dsh_user FOREIGN KEY(changed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO document_types(code,display_code,name,entity_type) VALUES
('BT','BT','Bon de transport',NULL),('BRS','BRS','Bon de réception silo','weighings'),('BSS','BSS','Bon de sortie silo','silo_movements'),
('BR','BR','Bon de réception',NULL),('BS','BS','Bon de sortie','distributions'),('BTR','BTR','Bon de transfert',NULL),
('BTI','BTI','Bon de transfert interne',NULL),('BL','BL','Bon de livraison',NULL),('BRA','BRA','Bon de réception achat',NULL),
('BC','BC','Bon de commande',NULL),('BRET','BRet','Bon de retour',NULL),('OF','OF','Ordre de fabrication','production_batches'),
('DAE','DAE','Demande approvisionnement en emballages',NULL),('BSE','BSE','Bon de sortie emballages',NULL),
('OM','OM','Ordre de mission',NULL),('NC','NC','Rapport de non-conformité',NULL);

INSERT INTO document_numbering_rules(document_type_id,pattern,period_type,sequence_length)
SELECT id,'{TYPE}/{SITE}/{YEAR}/{SEQ:6}','year',6 FROM document_types;

INSERT INTO documents(document_type_id,document_number,site_id,period_key,entity_type,entity_id,status,document_date,created_by,validated_by,validated_at)
SELECT dt.id,CONCAT('BRS/',s.code,'/',YEAR(w.weighed_at),'/',LPAD(w.id,6,'0')),w.site_id,YEAR(w.weighed_at),'weighings',w.id,'validated',w.weighed_at,w.created_by,w.validated_by,w.updated_at
FROM weighings w JOIN document_types dt ON dt.code='BRS' JOIN sites s ON s.id=w.site_id WHERE w.status='validated' AND w.deleted_at IS NULL;
INSERT INTO documents(document_type_id,document_number,site_id,period_key,entity_type,entity_id,status,document_date,created_by,validated_by,validated_at)
SELECT dt.id,CONCAT('BS/',s.code,'/',YEAR(d.distributed_at),'/',LPAD(d.id,6,'0')),d.site_id,YEAR(d.distributed_at),'distributions',d.id,'validated',d.distributed_at,d.created_by,d.validated_by,d.updated_at
FROM distributions d JOIN document_types dt ON dt.code='BS' JOIN sites s ON s.id=d.site_id WHERE d.deleted_at IS NULL;
INSERT INTO documents(document_type_id,document_number,site_id,period_key,entity_type,entity_id,status,document_date,created_by,validated_by,validated_at)
SELECT dt.id,CONCAT('OF/',s.code,'/',YEAR(pb.started_at),'/',LPAD(pb.id,6,'0')),pb.site_id,YEAR(pb.started_at),'production_batches',pb.id,'validated',pb.started_at,pb.created_by,pb.validated_by,pb.ended_at
FROM production_batches pb JOIN document_types dt ON dt.code='OF' JOIN sites s ON s.id=pb.site_id WHERE pb.status='validated' AND pb.deleted_at IS NULL;
INSERT INTO document_status_history(document_id,old_status,new_status,changed_by,reason) SELECT id,NULL,status,COALESCE(validated_by,created_by),'Reprise migration 011' FROM documents;

INSERT INTO document_number_counters(numbering_rule_id,site_id,period_key,last_sequence)
SELECT r.id,d.site_id,d.period_key,MAX(d.entity_id) FROM documents d JOIN document_numbering_rules r ON r.document_type_id=d.document_type_id AND r.site_id IS NULL GROUP BY r.id,d.site_id,d.period_key;

INSERT INTO permissions(component,action,description) VALUES
('documents','read','Consulter les documents'),('documents','create','Créer les documents'),('documents','validate','Valider les documents'),
('documents','update','Modifier les documents'),('documents','delete','Annuler les documents'),('documents','administer','Configurer le référentiel documentaire');
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.slug IN('administrateur','administrateur-systeme');
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component='documents' AND p.action='read' WHERE r.slug IN('direction','directeur-general','directeur-financier','auditeur','comptable','directeur-site','responsable-site');
INSERT IGNORE INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component='documents' AND p.action IN('read','create') WHERE r.slug IN('agent-pont-bascule','agent-silo','agent-production','agent-emballage','agent-distribution','gestionnaire-stock','operateur-production','chauffeur-logistique','operateur-saisie');
