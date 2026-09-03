USE cmck_milltrack;

ALTER TABLE finished_stocks ADD COLUMN reserved_bags INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity_bags,
 ADD COLUMN reserved_weight_kg DECIMAL(12,3) UNSIGNED NOT NULL DEFAULT 0 AFTER total_weight_kg,
 ADD KEY idx_finished_stock_available(site_id,product_id,bag_format_id,status,reserved_bags);

CREATE TABLE stock_transfers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, transfer_number VARCHAR(100) NOT NULL UNIQUE, transfer_type ENUM('inter_site','internal') NOT NULL DEFAULT 'inter_site',
 source_site_id INT UNSIGNED NOT NULL,destination_site_id INT UNSIGNED NOT NULL,status ENUM('draft','submitted','approved','reserved','partially_shipped','in_transit','partially_received','quality_control','partially_accepted','accepted','rejected','return_pending','returned','closed','cancelled') NOT NULL DEFAULT 'draft',
 requested_at DATETIME NOT NULL,submitted_at DATETIME NULL,approved_at DATETIME NULL,closed_at DATETIME NULL,created_by INT UNSIGNED NOT NULL,approved_by INT UNSIGNED NULL,notes TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,deleted_at TIMESTAMP NULL DEFAULT NULL,
 KEY idx_transfer_source(source_site_id,status),KEY idx_transfer_destination(destination_site_id,status),
 CONSTRAINT fk_transfer_source FOREIGN KEY(source_site_id) REFERENCES sites(id),CONSTRAINT fk_transfer_destination FOREIGN KEY(destination_site_id) REFERENCES sites(id),
 CONSTRAINT fk_transfer_creator FOREIGN KEY(created_by) REFERENCES users(id),CONSTRAINT fk_transfer_approver FOREIGN KEY(approved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_transfer_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,transfer_id BIGINT UNSIGNED NOT NULL,source_finished_stock_id INT UNSIGNED NOT NULL,product_id INT UNSIGNED NOT NULL,bag_format_id INT UNSIGNED NOT NULL,
 requested_bags INT UNSIGNED NOT NULL,requested_kg DECIMAL(12,3) UNSIGNED NOT NULL,approved_bags INT UNSIGNED NOT NULL DEFAULT 0,approved_kg DECIMAL(12,3) UNSIGNED NOT NULL DEFAULT 0,
 reserved_bags INT UNSIGNED NOT NULL DEFAULT 0,reserved_kg DECIMAL(12,3) UNSIGNED NOT NULL DEFAULT 0,shipped_bags INT UNSIGNED NOT NULL DEFAULT 0,shipped_kg DECIMAL(12,3) UNSIGNED NOT NULL DEFAULT 0,
 received_bags INT UNSIGNED NOT NULL DEFAULT 0,received_kg DECIMAL(12,3) UNSIGNED NOT NULL DEFAULT 0,accepted_bags INT UNSIGNED NOT NULL DEFAULT 0,accepted_kg DECIMAL(12,3) UNSIGNED NOT NULL DEFAULT 0,
 rejected_bags INT UNSIGNED NOT NULL DEFAULT 0,rejected_kg DECIMAL(12,3) UNSIGNED NOT NULL DEFAULT 0,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_transfer_stock(transfer_id,source_finished_stock_id),CONSTRAINT fk_ti_transfer FOREIGN KEY(transfer_id) REFERENCES stock_transfers(id),
 CONSTRAINT fk_ti_stock FOREIGN KEY(source_finished_stock_id) REFERENCES finished_stocks(id),CONSTRAINT fk_ti_product FOREIGN KEY(product_id) REFERENCES products(id),CONSTRAINT fk_ti_format FOREIGN KEY(bag_format_id) REFERENCES bag_formats(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_transfer_reservations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,transfer_item_id BIGINT UNSIGNED NOT NULL,quantity_bags INT UNSIGNED NOT NULL,quantity_kg DECIMAL(12,3) UNSIGNED NOT NULL,
 status ENUM('active','consumed','released') NOT NULL DEFAULT 'active',created_by INT UNSIGNED NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,consumed_at DATETIME NULL,released_at DATETIME NULL,
 KEY idx_reservation_item(transfer_item_id,status),CONSTRAINT fk_tr_item FOREIGN KEY(transfer_item_id) REFERENCES stock_transfer_items(id),CONSTRAINT fk_tr_creator FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transfer_shipments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,transfer_id BIGINT UNSIGNED NOT NULL,shipment_number VARCHAR(100) NOT NULL UNIQUE,status ENUM('draft','dispatched','partially_received','received','return_pending','returned') NOT NULL DEFAULT 'draft',
 vehicle_reference VARCHAR(100) NULL,dispatched_at DATETIME NULL,created_by INT UNSIGNED NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_ts_transfer FOREIGN KEY(transfer_id) REFERENCES stock_transfers(id),CONSTRAINT fk_ts_creator FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE transfer_shipment_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,shipment_id BIGINT UNSIGNED NOT NULL,transfer_item_id BIGINT UNSIGNED NOT NULL,quantity_bags INT UNSIGNED NOT NULL,quantity_kg DECIMAL(12,3) UNSIGNED NOT NULL,
 received_bags INT UNSIGNED NOT NULL DEFAULT 0,received_kg DECIMAL(12,3) UNSIGNED NOT NULL DEFAULT 0,UNIQUE KEY uq_shipment_transfer_item(shipment_id,transfer_item_id),
 CONSTRAINT fk_tsi_shipment FOREIGN KEY(shipment_id) REFERENCES transfer_shipments(id),CONSTRAINT fk_tsi_item FOREIGN KEY(transfer_item_id) REFERENCES stock_transfer_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transfer_receipts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,transfer_id BIGINT UNSIGNED NOT NULL,shipment_id BIGINT UNSIGNED NOT NULL,receipt_number VARCHAR(100) NOT NULL UNIQUE,idempotency_key CHAR(64) NOT NULL UNIQUE,
 status ENUM('accepted','partially_accepted','rejected') NOT NULL,received_at DATETIME NOT NULL,quality_notes TEXT NULL,received_by INT UNSIGNED NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_receipt_shipment(shipment_id),CONSTRAINT fk_trec_transfer FOREIGN KEY(transfer_id) REFERENCES stock_transfers(id),CONSTRAINT fk_trec_shipment FOREIGN KEY(shipment_id) REFERENCES transfer_shipments(id),CONSTRAINT fk_trec_user FOREIGN KEY(received_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE transfer_receipt_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,receipt_id BIGINT UNSIGNED NOT NULL,shipment_item_id BIGINT UNSIGNED NOT NULL,received_bags INT UNSIGNED NOT NULL,received_kg DECIMAL(12,3) UNSIGNED NOT NULL,
 accepted_bags INT UNSIGNED NOT NULL,accepted_kg DECIMAL(12,3) UNSIGNED NOT NULL,rejected_bags INT UNSIGNED NOT NULL,rejected_kg DECIMAL(12,3) UNSIGNED NOT NULL,quality_status ENUM('conform','non_conform','rejected') NOT NULL,quality_notes VARCHAR(500) NULL,
 UNIQUE KEY uq_receipt_shipment_item(receipt_id,shipment_item_id),CONSTRAINT fk_tri_receipt FOREIGN KEY(receipt_id) REFERENCES transfer_receipts(id),CONSTRAINT fk_tri_shipment_item FOREIGN KEY(shipment_item_id) REFERENCES transfer_shipment_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transfer_non_conformities (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,transfer_id BIGINT UNSIGNED NOT NULL,receipt_id BIGINT UNSIGNED NOT NULL,receipt_item_id BIGINT UNSIGNED NULL,reference VARCHAR(100) NOT NULL UNIQUE,
 discrepancy_type ENUM('quantity','quality','quantity_and_quality') NOT NULL,expected_bags INT UNSIGNED NOT NULL,received_bags INT UNSIGNED NOT NULL,rejected_bags INT UNSIGNED NOT NULL DEFAULT 0,description VARCHAR(500) NOT NULL,
 status ENUM('open','resolved','closed') NOT NULL DEFAULT 'open',created_by INT UNSIGNED NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_tnc_transfer FOREIGN KEY(transfer_id) REFERENCES stock_transfers(id),CONSTRAINT fk_tnc_receipt FOREIGN KEY(receipt_id) REFERENCES transfer_receipts(id),CONSTRAINT fk_tnc_receipt_item FOREIGN KEY(receipt_item_id) REFERENCES transfer_receipt_items(id),CONSTRAINT fk_tnc_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transfer_returns (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,transfer_id BIGINT UNSIGNED NOT NULL,receipt_id BIGINT UNSIGNED NOT NULL,return_number VARCHAR(100) NOT NULL UNIQUE,status ENUM('planned','dispatched','received','closed') NOT NULL DEFAULT 'planned',
 planned_at DATETIME NOT NULL,dispatched_at DATETIME NULL,received_at DATETIME NULL,created_by INT UNSIGNED NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_tret_transfer FOREIGN KEY(transfer_id) REFERENCES stock_transfers(id),CONSTRAINT fk_tret_receipt FOREIGN KEY(receipt_id) REFERENCES transfer_receipts(id),CONSTRAINT fk_tret_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE transfer_return_items(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,return_id BIGINT UNSIGNED NOT NULL,transfer_item_id BIGINT UNSIGNED NOT NULL,quantity_bags INT UNSIGNED NOT NULL,quantity_kg DECIMAL(12,3) UNSIGNED NOT NULL,CONSTRAINT fk_treti_return FOREIGN KEY(return_id) REFERENCES transfer_returns(id),CONSTRAINT fk_treti_item FOREIGN KEY(transfer_item_id) REFERENCES stock_transfer_items(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_transfer_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,transfer_id BIGINT UNSIGNED NOT NULL,transfer_item_id BIGINT UNSIGNED NOT NULL,site_id INT UNSIGNED NOT NULL,movement_type ENUM('reserve','release','dispatch','transit_in','transit_out','receipt','reject','return_dispatch','return_receipt') NOT NULL,
 quantity_bags INT UNSIGNED NOT NULL,quantity_kg DECIMAL(12,3) UNSIGNED NOT NULL,physical_before_kg DECIMAL(12,3) UNSIGNED NOT NULL,physical_after_kg DECIMAL(12,3) UNSIGNED NOT NULL,reserved_before_kg DECIMAL(12,3) UNSIGNED NOT NULL,reserved_after_kg DECIMAL(12,3) UNSIGNED NOT NULL,transit_before_kg DECIMAL(12,3) UNSIGNED NOT NULL,transit_after_kg DECIMAL(12,3) UNSIGNED NOT NULL,
 source_type VARCHAR(50) NOT NULL,source_id BIGINT UNSIGNED NOT NULL,created_by INT UNSIGNED NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_stm_transfer(transfer_id,created_at),CONSTRAINT fk_stm_transfer FOREIGN KEY(transfer_id) REFERENCES stock_transfers(id),CONSTRAINT fk_stm_item FOREIGN KEY(transfer_item_id) REFERENCES stock_transfer_items(id),CONSTRAINT fk_stm_site FOREIGN KEY(site_id) REFERENCES sites(id),CONSTRAINT fk_stm_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE stock_transfer_history(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,transfer_id BIGINT UNSIGNED NOT NULL,old_status VARCHAR(40) NULL,new_status VARCHAR(40) NOT NULL,action VARCHAR(50) NOT NULL,actor_id INT UNSIGNED NOT NULL,reason VARCHAR(500) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_sth_transfer FOREIGN KEY(transfer_id) REFERENCES stock_transfers(id),CONSTRAINT fk_sth_actor FOREIGN KEY(actor_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions(component,action,description) VALUES ('transfers','read','Consulter les transferts'),('transfers','create','Créer les transferts'),('transfers','validate','Valider et réserver'),('transfers','update','Expédier et réceptionner'),('transfers','delete','Annuler avant expédition'),('transfers','administer','Administrer les transferts');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.slug IN('administrateur','administrateur-systeme');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component='transfers' AND p.action IN('read','validate','update') WHERE r.slug IN('direction','directeur-general','directeur-site','responsable-site');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component='transfers' AND p.action IN('read','create','update') WHERE r.slug IN('gestionnaire-stock','agent-distribution','chauffeur-logistique');
