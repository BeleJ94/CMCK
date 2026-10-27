-- Volailles : poulets de chair et pondeuses, de la réception fournisseur ou de l'éclosion jusqu'au comptoir.
-- Les poulets sont abattus et pesés à la ferme, ou envoyés vivants à la boucherie. La boucherie suit les pièces
-- en plus des kilos, et chaque article se vend au kilo ou à la pièce selon son paramétrage.

-- Profil avicole de chaque lot : type (chair ou ponte) et origine (fournisseur, éclosion ou lot antérieur).
CREATE TABLE poultry_lot_profiles (
 batch_id BIGINT UNSIGNED PRIMARY KEY,
 flock_type ENUM('broiler','layer') NOT NULL,
 origin ENUM('supplier','hatch','existing') NOT NULL,
 supplier_id INT UNSIGNED NULL,
 mother_batch_id BIGINT UNSIGNED NULL,
 receipt_number VARCHAR(60) NULL,
 delivery_reference VARCHAR(120) NULL,
 received_at DATETIME NOT NULL,
 age_days INT UNSIGNED NULL,
 live_gross_kg DECIMAL(12,3) NULL,
 live_tare_kg DECIMAL(12,3) NULL,
 live_weight_kg DECIMAL(12,3) NULL,
 tare_note VARCHAR(120) NULL,
 notes VARCHAR(500) NULL,
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_poultry_profile_receipt(receipt_number),
 KEY idx_poultry_profile_type(flock_type),
 KEY idx_poultry_profile_mother(mother_batch_id),
 CONSTRAINT fk_poultry_profile_batch FOREIGN KEY(batch_id) REFERENCES livestock_batches(id),
 CONSTRAINT fk_poultry_profile_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id),
 CONSTRAINT fk_poultry_profile_mother FOREIGN KEY(mother_batch_id) REFERENCES livestock_batches(id),
 CONSTRAINT fk_poultry_profile_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Les lots de poulets existants deviennent des lots antérieurs : ponte pour l'objectif « ponte », chair sinon.
INSERT INTO poultry_lot_profiles(batch_id,flock_type,origin,received_at,created_by)
 SELECT b.id,IF(b.purpose='laying','layer','broiler'),'existing',b.started_at,b.created_by
 FROM livestock_batches b JOIN livestock_species s ON s.id=b.species_id
 WHERE s.code='POULET' AND b.deleted_at IS NULL;

-- Saisies journalières : œufs ramassés (pondeuses) et oiseaux morts. Plusieurs saisies par jour sont possibles.
CREATE TABLE poultry_daily_records (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 batch_id BIGINT UNSIGNED NOT NULL,
 recorded_on DATE NOT NULL,
 eggs_count INT UNSIGNED NOT NULL DEFAULT 0,
 mortality_heads INT UNSIGNED NOT NULL DEFAULT 0,
 notes VARCHAR(255) NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_poultry_daily_batch(batch_id,recorded_on),
 CONSTRAINT fk_poultry_daily_batch FOREIGN KEY(batch_id) REFERENCES livestock_batches(id),
 CONSTRAINT fk_poultry_daily_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Abattage à la ferme : article, DLC et oiseaux trouvés morts lors de l'abattage.
-- Le nombre de carcasses (pièces) est le nombre de têtes abattues de la conversion.
CREATE TABLE poultry_slaughter_details (
 conversion_id BIGINT UNSIGNED PRIMARY KEY,
 item_id INT UNSIGNED NOT NULL,
 expiry_date DATE NOT NULL,
 dead_heads INT UNSIGNED NOT NULL DEFAULT 0,
 CONSTRAINT fk_poultry_slaughter_conversion FOREIGN KEY(conversion_id) REFERENCES livestock_conversions(id),
 CONSTRAINT fk_poultry_slaughter_item FOREIGN KEY(item_id) REFERENCES butchery_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Envoi de poulets vivants à la boucherie : têtes et poids vif pesé à la ferme (brut et tare des cages).
CREATE TABLE poultry_live_shipments (
 transfer_id BIGINT UNSIGNED PRIMARY KEY,
 item_id INT UNSIGNED NOT NULL,
 gross_kg DECIMAL(12,3) NOT NULL,
 tare_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
 tare_note VARCHAR(120) NULL,
 expiry_date DATE NOT NULL,
 CONSTRAINT fk_poultry_live_transfer FOREIGN KEY(transfer_id) REFERENCES livestock_transfers(id),
 CONSTRAINT fk_poultry_live_item FOREIGN KEY(item_id) REFERENCES butchery_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nombre de carcasses envoyées avec le poids.
ALTER TABLE livestock_transfers ADD COLUMN quantity_pieces INT UNSIGNED NULL AFTER quantity_kg;

-- Boucherie : un article suivi en pièces garde aussi ses kilos ; « unit » indique s'il se vend au kilo ou à la pièce.
ALTER TABLE butchery_items
 ADD COLUMN track_pieces TINYINT(1) NOT NULL DEFAULT 0 AFTER unit,
 ADD COLUMN is_live TINYINT(1) NOT NULL DEFAULT 0 AFTER track_pieces;
ALTER TABLE butchery_receipts
 ADD COLUMN sent_pieces INT UNSIGNED NULL AFTER sent_weight_kg,
 ADD COLUMN received_pieces INT UNSIGNED NULL AFTER received_weight_kg;
ALTER TABLE butchery_raw_lots
 ADD COLUMN quantity_pieces INT UNSIGNED NULL AFTER reserved_kg,
 ADD COLUMN reserved_pieces INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity_pieces;
ALTER TABLE butchery_raw_movements ADD COLUMN quantity_pieces INT UNSIGNED NULL AFTER quantity_kg;
-- Les poulets vivants reçus forment directement un lot vendable, rattaché à leur réception.
ALTER TABLE butchery_finished_lots
 MODIFY order_output_id BIGINT UNSIGNED NULL,
 ADD COLUMN receipt_id BIGINT UNSIGNED NULL AFTER order_output_id,
 ADD COLUMN quantity_pieces INT UNSIGNED NULL AFTER reserved_kg,
 ADD COLUMN reserved_pieces INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity_pieces,
 ADD UNIQUE KEY uq_bfl_receipt(receipt_id),
 ADD CONSTRAINT fk_bfl_receipt FOREIGN KEY(receipt_id) REFERENCES butchery_receipts(id);
ALTER TABLE butchery_finished_movements
 MODIFY movement_type ENUM('production_in','sale_out','reserve','transfer_out','release','reversal','receipt_in','slaughter_out') NOT NULL,
 ADD COLUMN quantity_pieces INT UNSIGNED NULL AFTER quantity_kg;
ALTER TABLE butchery_counter_stocks ADD COLUMN quantity_pieces INT UNSIGNED NULL AFTER quantity_kg;
ALTER TABLE butchery_counter_movements ADD COLUMN quantity_pieces INT UNSIGNED NULL AFTER quantity_kg;
ALTER TABLE butchery_order_consumptions ADD COLUMN quantity_pieces INT UNSIGNED NULL AFTER quantity_kg;
ALTER TABLE butchery_order_outputs ADD COLUMN quantity_pieces INT UNSIGNED NULL AFTER quantity_kg;
ALTER TABLE butchery_output_allocations ADD COLUMN counter_pieces INT UNSIGNED NULL AFTER counter_quantity_kg;
ALTER TABLE butchery_pos_prices ADD COLUMN price_unit ENUM('kg','piece') NOT NULL DEFAULT 'kg' AFTER price;
ALTER TABLE butchery_pos_lines
 ADD COLUMN quantity_pieces INT UNSIGNED NULL AFTER quantity_kg,
 ADD COLUMN price_unit ENUM('kg','piece') NOT NULL DEFAULT 'kg' AFTER unit_price;
ALTER TABLE butchery_sales ADD COLUMN quantity_pieces INT UNSIGNED NULL AFTER quantity_kg;

-- Abattage de poulets vivants à la boucherie : il transforme des pièces du lot vivant en carcasses au froid.
CREATE TABLE poultry_butchery_slaughters (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 site_id INT UNSIGNED NOT NULL,
 live_lot_id BIGINT UNSIGNED NOT NULL,
 raw_lot_id BIGINT UNSIGNED NOT NULL,
 slaughter_number VARCHAR(100) NOT NULL,
 heads INT UNSIGNED NOT NULL,
 dead_heads INT UNSIGNED NOT NULL DEFAULT 0,
 live_kg DECIMAL(14,3) NOT NULL,
 gross_weight_kg DECIMAL(14,3) NOT NULL,
 tare_weight_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
 net_weight_kg DECIMAL(14,3) NOT NULL,
 tare_note VARCHAR(120) NULL,
 slaughtered_at DATETIME NOT NULL,
 expiry_date DATE NOT NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_pbs_number(slaughter_number),
 UNIQUE KEY uq_pbs_raw(raw_lot_id),
 KEY idx_pbs_live(live_lot_id),
 CONSTRAINT fk_pbs_site FOREIGN KEY(site_id) REFERENCES sites(id),
 CONSTRAINT fk_pbs_live FOREIGN KEY(live_lot_id) REFERENCES butchery_finished_lots(id),
 CONSTRAINT fk_pbs_raw FOREIGN KEY(raw_lot_id) REFERENCES butchery_raw_lots(id),
 CONSTRAINT fk_pbs_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Articles volaille : carcasses suivies en pièces, poulet vif, poulets et poules entiers vendus à la pièce.
UPDATE butchery_items SET name='Carcasse de poulet' WHERE code='MP-VOLAILLE' AND name='Volaille';
INSERT IGNORE INTO butchery_items(code,name,item_kind,unit,track_pieces,is_live,default_shelf_life_days) VALUES
 ('MP-POULE','Carcasse de poule','raw_material','kg',1,0,4),
 ('VOL-VIF','Poulet vif','finished_product','piece',1,1,30),
 ('VOL-ENTIER','Poulet entier','finished_product','piece',1,0,4),
 ('POULE-ENTIERE','Poule entière','finished_product','piece',1,0,4);
UPDATE butchery_items SET track_pieces=1 WHERE code IN ('MP-VOLAILLE','MP-POULE','VOL-VIF','VOL-ENTIER','POULE-ENTIERE');
UPDATE butchery_items SET is_live=1 WHERE code='VOL-VIF';

-- Fiche de découpe des poules, comme celle des poulets (DEC-VOLAILLE).
INSERT IGNORE INTO butchery_recipes(code,name,status,created_by)
 SELECT 'DEC-POULE','Découpe de poule','active',MIN(id) FROM users WHERE deleted_at IS NULL HAVING MIN(id) IS NOT NULL;
INSERT IGNORE INTO butchery_recipe_versions(recipe_id,version_number,status,effective_from,created_by)
 SELECT r.id,1,'active',CURDATE(),r.created_by FROM butchery_recipes r WHERE r.code='DEC-POULE';
INSERT IGNORE INTO butchery_recipe_components(recipe_version_id,item_id,component_type,percentage)
 SELECT v.id,i.id,'raw_material',100 FROM butchery_recipe_versions v JOIN butchery_recipes r ON r.id=v.recipe_id
 JOIN butchery_items i ON i.code='MP-POULE' WHERE r.code='DEC-POULE' AND v.version_number=1;
