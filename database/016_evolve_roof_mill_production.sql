-- DAGRIL ERP - processus de production Minoterie ROOF (migration additive)

ALTER TABLE machine_feeds
  ADD COLUMN authorized_quantity_kg DECIMAL(12,3) NULL AFTER quantity_kg,
  ADD COLUMN bss_document_id BIGINT UNSIGNED NULL AFTER silo_movement_id,
  ADD KEY idx_machine_feeds_bss (bss_document_id),
  ADD CONSTRAINT fk_machine_feeds_bss FOREIGN KEY (bss_document_id) REFERENCES documents(id);
UPDATE machine_feeds SET authorized_quantity_kg=quantity_kg WHERE authorized_quantity_kg IS NULL;
ALTER TABLE machine_feeds MODIFY authorized_quantity_kg DECIMAL(12,3) NOT NULL;

ALTER TABLE production_batches
  ADD COLUMN actual_input_quantity_kg DECIMAL(12,3) NULL AFTER input_quantity_kg,
  ADD COLUMN yield_percent DECIMAL(8,3) NULL AFTER waste_quantity_kg,
  ADD COLUMN variance_quantity_kg DECIMAL(12,3) NULL AFTER yield_percent,
  ADD COLUMN variance_percent DECIMAL(8,3) NULL AFTER variance_quantity_kg,
  ADD COLUMN variance_tolerance_percent DECIMAL(8,3) NOT NULL DEFAULT 2.000 AFTER variance_percent,
  ADD COLUMN variance_justification TEXT NULL AFTER variance_tolerance_percent,
  ADD COLUMN results_submitted_by INT UNSIGNED NULL AFTER variance_justification,
  ADD COLUMN results_submitted_at DATETIME NULL AFTER results_submitted_by,
  ADD COLUMN additional_approved_by INT UNSIGNED NULL AFTER results_submitted_at,
  ADD COLUMN additional_approved_at DATETIME NULL AFTER additional_approved_by,
  ADD CONSTRAINT fk_pb_results_user FOREIGN KEY (results_submitted_by) REFERENCES users(id),
  ADD CONSTRAINT fk_pb_additional_approver FOREIGN KEY (additional_approved_by) REFERENCES users(id);
UPDATE production_batches
SET actual_input_quantity_kg=input_quantity_kg,
    yield_percent=CASE WHEN input_quantity_kg>0 THEN output_quantity_kg/input_quantity_kg*100 ELSE 0 END,
    variance_quantity_kg=input_quantity_kg-output_quantity_kg-waste_quantity_kg,
    variance_percent=CASE WHEN input_quantity_kg>0 THEN (input_quantity_kg-output_quantity_kg-waste_quantity_kg)/input_quantity_kg*100 ELSE 0 END
WHERE actual_input_quantity_kg IS NULL;
ALTER TABLE production_batches MODIFY actual_input_quantity_kg DECIMAL(12,3) NOT NULL;
ALTER TABLE production_batches MODIFY status ENUM('active','inactive','pending','in_progress','results_submitted','pending_additional_approval','validated','cancelled') NOT NULL DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS waste_types (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(50) NOT NULL UNIQUE,
 name VARCHAR(120) NOT NULL,
 product_id INT UNSIGNED NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 deleted_at TIMESTAMP NULL DEFAULT NULL,
 CONSTRAINT fk_waste_types_product FOREIGN KEY(product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO waste_types(code,name,product_id)
SELECT 'SON','Son',id FROM products WHERE code='DECHETS-MAIS';
INSERT IGNORE INTO waste_types(code,name,product_id)
SELECT 'ISSUES','Issues et criblures',id FROM products WHERE code='DECHETS-MAIS';
INSERT IGNORE INTO waste_types(code,name,product_id)
SELECT 'POUSSIERE','Poussière',id FROM products WHERE code='DECHETS-MAIS';

CREATE TABLE IF NOT EXISTS production_waste_lines (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 production_batch_id INT UNSIGNED NOT NULL,
 waste_type_id INT UNSIGNED NOT NULL,
 quantity_kg DECIMAL(12,3) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 deleted_at TIMESTAMP NULL DEFAULT NULL,
 UNIQUE KEY uq_batch_waste_type(production_batch_id,waste_type_id),
 CONSTRAINT fk_pwl_batch FOREIGN KEY(production_batch_id) REFERENCES production_batches(id),
 CONSTRAINT fk_pwl_type FOREIGN KEY(waste_type_id) REFERENCES waste_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bulk_flour_stocks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 site_id INT UNSIGNED NOT NULL,
 product_id INT UNSIGNED NOT NULL,
 production_batch_id INT UNSIGNED NOT NULL,
 quantity_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
 status ENUM('active','depleted','cancelled') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 deleted_at TIMESTAMP NULL DEFAULT NULL,
 UNIQUE KEY uq_bulk_flour_batch(production_batch_id),
 KEY idx_bulk_flour_site_product(site_id,product_id,status),
 CONSTRAINT fk_bfs_site FOREIGN KEY(site_id) REFERENCES sites(id),
 CONSTRAINT fk_bfs_product FOREIGN KEY(product_id) REFERENCES products(id),
 CONSTRAINT fk_bfs_batch FOREIGN KEY(production_batch_id) REFERENCES production_batches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO bulk_flour_stocks(site_id,product_id,production_batch_id,quantity_kg,status)
SELECT pb.site_id,pb.product_id,pb.id,GREATEST(pb.output_quantity_kg-COALESCE(SUM(p.total_weight_kg),0),0),
 CASE WHEN pb.output_quantity_kg-COALESCE(SUM(p.total_weight_kg),0)>0 THEN 'active' ELSE 'depleted' END
FROM production_batches pb LEFT JOIN packaging p ON p.production_batch_id=pb.id AND p.deleted_at IS NULL
WHERE pb.status='validated' GROUP BY pb.id;

ALTER TABLE waste_stocks ADD COLUMN waste_type_id INT UNSIGNED NULL AFTER product_id;
ALTER TABLE waste_stocks ADD KEY idx_waste_stocks_type_lot (waste_type_id,production_batch_id), ADD CONSTRAINT fk_waste_stocks_type FOREIGN KEY(waste_type_id) REFERENCES waste_types(id);
UPDATE waste_stocks ws SET waste_type_id=(SELECT id FROM waste_types WHERE code='SON' LIMIT 1) WHERE waste_type_id IS NULL;

INSERT IGNORE INTO production_waste_lines(production_batch_id,waste_type_id,quantity_kg)
SELECT pb.id,wt.id,pb.waste_quantity_kg FROM production_batches pb JOIN waste_types wt ON wt.code='SON' WHERE pb.waste_quantity_kg>0;

INSERT INTO machines(site_id,name,code,machine_type,capacity_kg_hour,status)
SELECT s.id,'ROOF-1','ROOF-1','main',NULL,'active' FROM sites s WHERE s.code='MINO' AND NOT EXISTS(SELECT 1 FROM machines WHERE code='ROOF-1');
INSERT INTO machines(site_id,name,code,machine_type,capacity_kg_hour,status)
SELECT s.id,'ROOF-2','ROOF-2','main',NULL,'active' FROM sites s WHERE s.code='MINO' AND NOT EXISTS(SELECT 1 FROM machines WHERE code='ROOF-2');
INSERT INTO machines(site_id,name,code,machine_type,capacity_kg_hour,status)
SELECT s.id,'ROOF-3','ROOF-3','main',NULL,'active' FROM sites s WHERE s.code='MINO' AND NOT EXISTS(SELECT 1 FROM machines WHERE code='ROOF-3');
