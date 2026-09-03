-- Gestion spécialisée des emballages vides - aucune donnée existante supprimée.
ALTER TABLE bag_formats ADD COLUMN code VARCHAR(50) NULL AFTER id;
ALTER TABLE bag_formats ADD UNIQUE KEY uq_bag_formats_code(code);

INSERT INTO bag_formats(code,name,weight_kg,status)
SELECT 'EMB-F10','Sac farine 10 kg',10,'active' WHERE NOT EXISTS(SELECT 1 FROM bag_formats WHERE code='EMB-F10');
INSERT INTO bag_formats(code,name,weight_kg,status)
SELECT 'EMB-F23','Sac farine 23 kg',23,'active' WHERE NOT EXISTS(SELECT 1 FROM bag_formats WHERE code='EMB-F23');
INSERT INTO bag_formats(code,name,weight_kg,status)
SELECT 'EMB-F50','Sac farine 50 kg',50,'active' WHERE NOT EXISTS(SELECT 1 FROM bag_formats WHERE code='EMB-F50');
INSERT INTO bag_formats(code,name,weight_kg,status)
SELECT 'EMB-A30','Sac aliment bétail 30 kg',30,'active' WHERE NOT EXISTS(SELECT 1 FROM bag_formats WHERE code='EMB-A30');
INSERT INTO bag_formats(code,name,weight_kg,status)
SELECT 'EMB-A50','Sac aliment bétail 50 kg',50,'active' WHERE NOT EXISTS(SELECT 1 FROM bag_formats WHERE code='EMB-A50');

CREATE TABLE empty_packaging_items(
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, bag_format_id INT UNSIGNED NOT NULL,
 code VARCHAR(50) NOT NULL UNIQUE,name VARCHAR(150) NOT NULL,target_product_code VARCHAR(50) NOT NULL,
 unit_capacity_kg DECIMAL(10,3) NOT NULL,status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,deleted_at TIMESTAMP NULL,
 CONSTRAINT fk_epi_format FOREIGN KEY(bag_format_id) REFERENCES bag_formats(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO empty_packaging_items(bag_format_id,code,name,target_product_code,unit_capacity_kg)
SELECT id,code,name,IF(code LIKE 'EMB-F%','FARINE-MAIS','ALIMENT-BETAIL'),weight_kg FROM bag_formats WHERE code IN('EMB-F10','EMB-F23','EMB-F50','EMB-A30','EMB-A50');

CREATE TABLE empty_packaging_stocks(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,site_id INT UNSIGNED NOT NULL,packaging_item_id INT UNSIGNED NOT NULL,
 physical_quantity INT UNSIGNED NOT NULL DEFAULT 0,reserved_quantity INT UNSIGNED NOT NULL DEFAULT 0,operational_quantity INT UNSIGNED NOT NULL DEFAULT 0,
 minimum_quantity INT UNSIGNED NOT NULL DEFAULT 0,average_unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,deleted_at TIMESTAMP NULL,
 UNIQUE KEY uq_eps_site_item(site_id,packaging_item_id),CONSTRAINT fk_eps_site FOREIGN KEY(site_id) REFERENCES sites(id),CONSTRAINT fk_eps_item FOREIGN KEY(packaging_item_id) REFERENCES empty_packaging_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE empty_packaging_movements(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,stock_id BIGINT UNSIGNED NOT NULL,movement_type ENUM('purchase_receipt','issue','consumption','transfer_out','transfer_in','defective','reversal') NOT NULL,
 quantity INT UNSIGNED NOT NULL,stock_before INT UNSIGNED NOT NULL,stock_after INT UNSIGNED NOT NULL,unit_cost DECIMAL(14,4) NULL,
 reference_type VARCHAR(80) NOT NULL,reference_id BIGINT UNSIGNED NOT NULL,movement_at DATETIME NOT NULL,created_by INT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_epm_effect(reference_type,reference_id,movement_type,stock_id),KEY idx_epm_stock_date(stock_id,movement_at),
 CONSTRAINT fk_epm_stock FOREIGN KEY(stock_id) REFERENCES empty_packaging_stocks(id),CONSTRAINT fk_epm_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE empty_packaging_purchases(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,site_id INT UNSIGNED NOT NULL,supplier_id INT UNSIGNED NOT NULL,purchase_number VARCHAR(100) NOT NULL UNIQUE,status ENUM('draft','submitted','approved','partially_received','received','cancelled') DEFAULT 'draft',ordered_at DATETIME NOT NULL,created_by INT UNSIGNED NOT NULL,approved_by INT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,deleted_at TIMESTAMP NULL,CONSTRAINT fk_epp_site FOREIGN KEY(site_id) REFERENCES sites(id),CONSTRAINT fk_epp_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id),CONSTRAINT fk_epp_creator FOREIGN KEY(created_by) REFERENCES users(id),CONSTRAINT fk_epp_approver FOREIGN KEY(approved_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE empty_packaging_purchase_lines(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,purchase_id BIGINT UNSIGNED NOT NULL,packaging_item_id INT UNSIGNED NOT NULL,ordered_quantity INT UNSIGNED NOT NULL,received_quantity INT UNSIGNED NOT NULL DEFAULT 0,unit_cost DECIMAL(14,4) NOT NULL,CONSTRAINT fk_eppl_purchase FOREIGN KEY(purchase_id) REFERENCES empty_packaging_purchases(id),CONSTRAINT fk_eppl_item FOREIGN KEY(packaging_item_id) REFERENCES empty_packaging_items(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE empty_packaging_receipts(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,purchase_id BIGINT UNSIGNED NOT NULL,receipt_number VARCHAR(100) NOT NULL UNIQUE,idempotency_key VARCHAR(100) NOT NULL UNIQUE,received_at DATETIME NOT NULL,status ENUM('received','cancelled') NOT NULL DEFAULT 'received',received_by INT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_epr_purchase FOREIGN KEY(purchase_id) REFERENCES empty_packaging_purchases(id),CONSTRAINT fk_epr_user FOREIGN KEY(received_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE empty_packaging_receipt_lines(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,receipt_id BIGINT UNSIGNED NOT NULL,purchase_line_id BIGINT UNSIGNED NOT NULL,quantity INT UNSIGNED NOT NULL,defective_quantity INT UNSIGNED NOT NULL DEFAULT 0,CONSTRAINT fk_eprl_receipt FOREIGN KEY(receipt_id) REFERENCES empty_packaging_receipts(id),CONSTRAINT fk_eprl_line FOREIGN KEY(purchase_line_id) REFERENCES empty_packaging_purchase_lines(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE empty_packaging_requests(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,site_id INT UNSIGNED NOT NULL,request_number VARCHAR(100) NOT NULL UNIQUE,status ENUM('draft','submitted','approved','partially_issued','fulfilled','rejected','cancelled') NOT NULL DEFAULT 'draft',needed_at DATETIME NOT NULL,justification TEXT NULL,created_by INT UNSIGNED NOT NULL,approved_by INT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,deleted_at TIMESTAMP NULL,CONSTRAINT fk_epreq_site FOREIGN KEY(site_id) REFERENCES sites(id),CONSTRAINT fk_epreq_creator FOREIGN KEY(created_by) REFERENCES users(id),CONSTRAINT fk_epreq_approver FOREIGN KEY(approved_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE empty_packaging_request_lines(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,request_id BIGINT UNSIGNED NOT NULL,packaging_item_id INT UNSIGNED NOT NULL,requested_quantity INT UNSIGNED NOT NULL,issued_quantity INT UNSIGNED NOT NULL DEFAULT 0,CONSTRAINT fk_epreql_request FOREIGN KEY(request_id) REFERENCES empty_packaging_requests(id),CONSTRAINT fk_epreql_item FOREIGN KEY(packaging_item_id) REFERENCES empty_packaging_items(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE empty_packaging_issues(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,request_id BIGINT UNSIGNED NOT NULL,issue_number VARCHAR(100) NOT NULL UNIQUE,status ENUM('validated','cancelled') DEFAULT 'validated',issued_at DATETIME NOT NULL,issued_by INT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_epi_req FOREIGN KEY(request_id) REFERENCES empty_packaging_requests(id),CONSTRAINT fk_epi_user FOREIGN KEY(issued_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE empty_packaging_issue_lines(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,issue_id BIGINT UNSIGNED NOT NULL,request_line_id BIGINT UNSIGNED NOT NULL,quantity INT UNSIGNED NOT NULL,CONSTRAINT fk_epil_issue FOREIGN KEY(issue_id) REFERENCES empty_packaging_issues(id),CONSTRAINT fk_epil_line FOREIGN KEY(request_line_id) REFERENCES empty_packaging_request_lines(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE empty_packaging_transfers(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,transfer_number VARCHAR(100) NOT NULL UNIQUE,source_site_id INT UNSIGNED NOT NULL,destination_site_id INT UNSIGNED NOT NULL,status ENUM('draft','approved','in_transit','received','cancelled') DEFAULT 'draft',created_by INT UNSIGNED NOT NULL,approved_by INT UNSIGNED NULL,shipped_at DATETIME NULL,received_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,deleted_at TIMESTAMP NULL,CONSTRAINT fk_ept_source FOREIGN KEY(source_site_id) REFERENCES sites(id),CONSTRAINT fk_ept_dest FOREIGN KEY(destination_site_id) REFERENCES sites(id),CONSTRAINT fk_ept_creator FOREIGN KEY(created_by) REFERENCES users(id),CONSTRAINT fk_ept_approver FOREIGN KEY(approved_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE empty_packaging_transfer_lines(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,transfer_id BIGINT UNSIGNED NOT NULL,packaging_item_id INT UNSIGNED NOT NULL,quantity INT UNSIGNED NOT NULL,CONSTRAINT fk_eptl_transfer FOREIGN KEY(transfer_id) REFERENCES empty_packaging_transfers(id),CONSTRAINT fk_eptl_item FOREIGN KEY(packaging_item_id) REFERENCES empty_packaging_items(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE empty_packaging_defects(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,site_id INT UNSIGNED NOT NULL,packaging_item_id INT UNSIGNED NOT NULL,quantity INT UNSIGNED NOT NULL,reason TEXT NOT NULL,detected_at DATETIME NOT NULL,created_by INT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_epd_site FOREIGN KEY(site_id) REFERENCES sites(id),CONSTRAINT fk_epd_item FOREIGN KEY(packaging_item_id) REFERENCES empty_packaging_items(id),CONSTRAINT fk_epd_user FOREIGN KEY(created_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE packaging ADD COLUMN packaging_item_id INT UNSIGNED NULL AFTER bag_format_id;
ALTER TABLE packaging ADD KEY idx_packaging_empty_item(packaging_item_id),ADD CONSTRAINT fk_packaging_empty_item FOREIGN KEY(packaging_item_id) REFERENCES empty_packaging_items(id);

INSERT IGNORE INTO permissions(component,action,description) VALUES
('empty-packaging','read','Consulter emballages vides'),('empty-packaging','create','Créer opération emballages'),('empty-packaging','validate','Valider opération emballages'),('empty-packaging','update','Modifier emballages'),('empty-packaging','delete','Supprimer emballages'),('empty-packaging','administer','Administrer emballages');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component='empty-packaging' WHERE r.slug='administrateur';
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component='empty-packaging' AND p.action IN('read','create','update') WHERE r.slug IN('agent-emballage','gestionnaire-stock','operateur-production');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component='empty-packaging' AND p.action IN('read','validate') WHERE r.slug IN('direction','directeur-general','directeur-site','responsable-site');
