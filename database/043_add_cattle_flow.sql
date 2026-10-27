-- Bovins : réception de fournisseurs à la ferme, abattage pesé à la ferme, puis envoi à la boucherie
-- avec double pesée, comme pour les porcs. Les lots bovins existants sont conservés.

-- Une réception fournisseur crée un lot de bovins dans un bâtiment de la ferme.
CREATE TABLE cattle_receipts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 batch_id BIGINT UNSIGNED NOT NULL,
 site_id INT UNSIGNED NOT NULL,
 supplier_id INT UNSIGNED NOT NULL,
 receipt_number VARCHAR(60) NOT NULL,
 delivery_reference VARCHAR(120) NULL,
 received_at DATETIME NOT NULL,
 heads INT UNSIGNED NOT NULL,
 live_gross_kg DECIMAL(12,3) NULL,
 live_tare_kg DECIMAL(12,3) NULL,
 live_weight_kg DECIMAL(12,3) NULL,
 tare_note VARCHAR(120) NULL,
 notes VARCHAR(500) NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_cattle_receipt_batch(batch_id),
 UNIQUE KEY uq_cattle_receipt_number(receipt_number),
 KEY idx_cattle_receipt_supplier(supplier_id,received_at),
 CONSTRAINT fk_cattle_receipt_batch FOREIGN KEY(batch_id) REFERENCES livestock_batches(id),
 CONSTRAINT fk_cattle_receipt_site FOREIGN KEY(site_id) REFERENCES sites(id),
 CONSTRAINT fk_cattle_receipt_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id),
 CONSTRAINT fk_cattle_receipt_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Abattage bovin : article boucherie et DLC des carcasses, comme pig_slaughter_details.
CREATE TABLE cattle_slaughter_details (
 conversion_id BIGINT UNSIGNED PRIMARY KEY,
 item_id INT UNSIGNED NOT NULL,
 expiry_date DATE NOT NULL,
 CONSTRAINT fk_cattle_slaughter_conversion FOREIGN KEY(conversion_id) REFERENCES livestock_conversions(id),
 CONSTRAINT fk_cattle_slaughter_item FOREIGN KEY(item_id) REFERENCES butchery_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
