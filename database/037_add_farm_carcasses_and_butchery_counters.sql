-- Run after 036. Existing finished stock starts entirely in the cold room.
ALTER TABLE butchery_orders ADD COLUMN flow_managed TINYINT(1) NOT NULL DEFAULT 0;
CREATE INDEX idx_lt_batch_status ON livestock_transfers(batch_id,status);
ALTER TABLE livestock_transfers DROP INDEX uq_lt_batch_active;

CREATE TABLE pig_slaughter_details (
 conversion_id BIGINT UNSIGNED PRIMARY KEY,
 item_id INT UNSIGNED NOT NULL,
 expiry_date DATE NOT NULL,
 CONSTRAINT fk_psd_conversion FOREIGN KEY(conversion_id) REFERENCES livestock_conversions(id),
 CONSTRAINT fk_psd_item FOREIGN KEY(item_id) REFERENCES butchery_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE butchery_counters (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 site_id INT UNSIGNED NOT NULL,
 code VARCHAR(60) NOT NULL,
 name VARCHAR(150) NOT NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_bc_site_code(site_id,code),
 CONSTRAINT fk_bc_site FOREIGN KEY(site_id) REFERENCES sites(id),
 CONSTRAINT fk_bc_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE butchery_counter_stocks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 counter_id INT UNSIGNED NOT NULL,
 finished_lot_id BIGINT UNSIGNED NOT NULL,
 quantity_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
 UNIQUE KEY uq_bcs_lot(counter_id,finished_lot_id),
 CONSTRAINT fk_bcs_counter FOREIGN KEY(counter_id) REFERENCES butchery_counters(id),
 CONSTRAINT fk_bcs_lot FOREIGN KEY(finished_lot_id) REFERENCES butchery_finished_lots(id),
 CONSTRAINT chk_bcs_positive CHECK(quantity_kg>=0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE butchery_output_allocations (
 output_id BIGINT UNSIGNED PRIMARY KEY,
 counter_id INT UNSIGNED NOT NULL,
 counter_quantity_kg DECIMAL(14,3) NOT NULL,
 CONSTRAINT fk_boa_output FOREIGN KEY(output_id) REFERENCES butchery_order_outputs(id),
 CONSTRAINT fk_boa_counter FOREIGN KEY(counter_id) REFERENCES butchery_counters(id),
 CONSTRAINT chk_boa_positive CHECK(counter_quantity_kg>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE livestock_butchery_operations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 operation_key CHAR(32) NOT NULL UNIQUE,
 action VARCHAR(50) NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 site_id INT UNSIGNED NOT NULL,
 created_by INT UNSIGNED NOT NULL,
 result_id BIGINT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_lbo_site FOREIGN KEY(site_id) REFERENCES sites(id),
 CONSTRAINT fk_lbo_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE butchery_counter_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 counter_stock_id BIGINT UNSIGNED NOT NULL,
 operation_id BIGINT UNSIGNED NOT NULL,
 movement_type ENUM('supply','return_cold','sale') NOT NULL,
 quantity_kg DECIMAL(14,3) NOT NULL,
 before_kg DECIMAL(14,3) NOT NULL,
 after_kg DECIMAL(14,3) NOT NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_bcm_effect(counter_stock_id,operation_id,movement_type),
 CONSTRAINT fk_bcm_stock FOREIGN KEY(counter_stock_id) REFERENCES butchery_counter_stocks(id),
 CONSTRAINT fk_bcm_op FOREIGN KEY(operation_id) REFERENCES livestock_butchery_operations(id),
 CONSTRAINT fk_bcm_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE butchery_sales ADD COLUMN counter_stock_id BIGINT UNSIGNED NULL,
 ADD CONSTRAINT fk_bsale_counter_stock FOREIGN KEY(counter_stock_id) REFERENCES butchery_counter_stocks(id);

INSERT IGNORE INTO butchery_counters(site_id,code,name)
 SELECT id,'PRINCIPAL','Comptoir principal' FROM sites WHERE code='BOUCH' AND deleted_at IS NULL;
INSERT IGNORE INTO butchery_items(code,name,item_kind,unit)
 VALUES('PORC-COTELETTE','Côtelettes de porc','finished_product','kg'),('PORC-MAKOSO','Makoso','finished_product','kg');
INSERT IGNORE INTO butchery_recipes(code,name,status,created_by)
 SELECT 'DEC-PORC-FERME','Découpe de carcasse de porc','active',MIN(id) FROM users WHERE deleted_at IS NULL HAVING MIN(id) IS NOT NULL;
INSERT IGNORE INTO butchery_recipe_versions(recipe_id,version_number,status,effective_from,created_by)
 SELECT r.id,1,'active',CURDATE(),r.created_by FROM butchery_recipes r WHERE r.code='DEC-PORC-FERME';
INSERT IGNORE INTO butchery_recipe_components(recipe_version_id,item_id,component_type,percentage)
 SELECT v.id,i.id,'raw_material',100 FROM butchery_recipe_versions v JOIN butchery_recipes r ON r.id=v.recipe_id
 JOIN butchery_items i ON i.code='MP-PORC' WHERE r.code='DEC-PORC-FERME' AND v.version_number=1;
