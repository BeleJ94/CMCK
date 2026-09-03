CREATE TABLE IF NOT EXISTS production_variance_settings (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 site_id INT UNSIGNED NOT NULL,
 tolerance_percent DECIMAL(8,3) NOT NULL DEFAULT 2.000,
 updated_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_production_variance_site(site_id),
 CONSTRAINT fk_pvs_site FOREIGN KEY(site_id) REFERENCES sites(id),
 CONSTRAINT fk_pvs_user FOREIGN KEY(updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO production_variance_settings(site_id,tolerance_percent)
SELECT id,2.000 FROM sites WHERE code='MINO';
