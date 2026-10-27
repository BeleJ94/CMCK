-- Pisciculture par étang et parcelle, pesée d’arrivée à la boucherie et tolérances d’écart de transport.
-- Les lots, pêches et réceptions existants sont conservés.

-- Un étang comporte une ou plusieurs parcelles ; chaque parcelle reçoit un lot à la fois.
CREATE TABLE livestock_pond_parcels (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 facility_id BIGINT UNSIGNED NOT NULL,
 code VARCHAR(20) NOT NULL,
 name VARCHAR(120) NOT NULL,
 area_m2 DECIMAL(12,3) NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_pond_parcel_code(facility_id,code),
 CONSTRAINT fk_pond_parcel_facility FOREIGN KEY(facility_id) REFERENCES livestock_facilities(id),
 CONSTRAINT fk_pond_parcel_creator FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chaque étang existant devient une parcelle unique couvrant toute sa surface.
INSERT INTO livestock_pond_parcels(facility_id,code,name,area_m2)
SELECT id,'P1','Parcelle 1',area_m2 FROM livestock_facilities WHERE facility_type='pond' AND deleted_at IS NULL;

ALTER TABLE livestock_batches
 ADD COLUMN parcel_id BIGINT UNSIGNED NULL AFTER facility_id,
 ADD CONSTRAINT fk_batch_parcel FOREIGN KEY(parcel_id) REFERENCES livestock_pond_parcels(id);

UPDATE livestock_batches b JOIN livestock_species s ON s.id=b.species_id
JOIN livestock_pond_parcels p ON p.facility_id=b.facility_id AND p.code='P1'
SET b.parcel_id=p.id WHERE s.category='fish';

-- Pêche : article boucherie, DLC, pêche finale (vidange) et nature de la tare.
ALTER TABLE fish_catches
 ADD COLUMN item_id INT UNSIGNED NULL AFTER conversion_id,
 ADD COLUMN expiry_date DATE NULL AFTER caught_at,
 ADD COLUMN final_harvest TINYINT(1) NOT NULL DEFAULT 0 AFTER expiry_date,
 ADD COLUMN tare_note VARCHAR(120) NULL AFTER tare_weight_kg,
 ADD CONSTRAINT fk_fc_item FOREIGN KEY(item_id) REFERENCES butchery_items(id);

ALTER TABLE livestock_conversions ADD COLUMN tare_note VARCHAR(120) NULL AFTER net_weight_kg;

-- Pesée d’arrivée : le stock boucherie part du poids reçu, l’écart avec le poids envoyé est conservé.
ALTER TABLE butchery_receipts
 ADD COLUMN sent_weight_kg DECIMAL(14,3) NULL AFTER received_weight_kg,
 ADD COLUMN received_gross_kg DECIMAL(14,3) NULL AFTER sent_weight_kg,
 ADD COLUMN received_tare_kg DECIMAL(14,3) NULL AFTER received_gross_kg,
 ADD COLUMN tare_note VARCHAR(120) NULL AFTER received_tare_kg,
 ADD COLUMN weight_gap_kg DECIMAL(14,3) NULL AFTER tare_note,
 ADD COLUMN weight_gap_pct DECIMAL(8,3) NULL AFTER weight_gap_kg,
 ADD COLUMN tolerance_pct DECIMAL(5,2) NULL AFTER weight_gap_pct,
 ADD COLUMN gap_reason VARCHAR(500) NULL AFTER tolerance_pct,
 ADD COLUMN gap_approved_by INT UNSIGNED NULL AFTER gap_reason,
 ADD COLUMN gap_approved_at DATETIME NULL AFTER gap_approved_by,
 ADD CONSTRAINT fk_receipt_gap_approver FOREIGN KEY(gap_approved_by) REFERENCES users(id);

-- Réceptions antérieures : poids envoyé connu, pesée d’arrivée non réalisée (écart laissé vide).
UPDATE butchery_receipts r JOIN livestock_transfers t ON t.id=r.livestock_transfer_id
SET r.sent_weight_kg=t.quantity_kg WHERE r.source_type='internal_btr' AND t.source_unit='kg';

-- Écart toléré par espèce et rôle chargé de valider un dépassement (vide : tout validateur boucherie).
CREATE TABLE livestock_transit_tolerances (
 species_id INT UNSIGNED PRIMARY KEY,
 tolerance_pct DECIMAL(5,2) NOT NULL,
 approver_role_id INT UNSIGNED NULL,
 updated_by INT UNSIGNED NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_tolerance_species FOREIGN KEY(species_id) REFERENCES livestock_species(id),
 CONSTRAINT fk_tolerance_role FOREIGN KEY(approver_role_id) REFERENCES roles(id),
 CONSTRAINT fk_tolerance_editor FOREIGN KEY(updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
