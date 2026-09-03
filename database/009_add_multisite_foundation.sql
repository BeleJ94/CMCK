USE cmck_milltrack;

-- Socle multi-sites DAGRIL ERP. Migration non destructive a executer une seule fois.
CREATE TABLE site_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  code VARCHAR(50) NOT NULL UNIQUE,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sites (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_type_id INT UNSIGNED NOT NULL,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_sites_type FOREIGN KEY (site_type_id) REFERENCES site_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE storage_locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id INT UNSIGNED NOT NULL,
  code VARCHAR(80) NOT NULL,
  name VARCHAR(180) NOT NULL,
  location_type ENUM('warehouse', 'silo', 'yard', 'cold_room', 'production', 'other') NOT NULL DEFAULT 'warehouse',
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uq_storage_locations_site_code (site_id, code),
  CONSTRAINT fk_storage_locations_site FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cost_centers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id INT UNSIGNED NOT NULL,
  code VARCHAR(80) NOT NULL,
  name VARCHAR(180) NOT NULL,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uq_cost_centers_site_code (site_id, code),
  CONSTRAINT fk_cost_centers_site FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE operational_units (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id INT UNSIGNED NOT NULL,
  cost_center_id INT UNSIGNED NULL,
  code VARCHAR(80) NOT NULL,
  name VARCHAR(180) NOT NULL,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uq_operational_units_site_code (site_id, code),
  CONSTRAINT fk_operational_units_site FOREIGN KEY (site_id) REFERENCES sites(id),
  CONSTRAINT fk_operational_units_cost_center FOREIGN KEY (cost_center_id) REFERENCES cost_centers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sites (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  site_id INT UNSIGNED NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uq_user_sites_user_site (user_id, site_id),
  KEY idx_user_sites_site_status (site_id, status, deleted_at),
  CONSTRAINT fk_user_sites_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_user_sites_site FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_types (name, code) VALUES
  ('Ferme', 'FARM'),
  ('Installation de stockage', 'STORAGE'),
  ('Unite industrielle', 'INDUSTRIAL'),
  ('Depot', 'DEPOT'),
  ('Unite commerciale', 'COMMERCIAL');

INSERT INTO sites (site_type_id, code, name) VALUES
  ((SELECT id FROM site_types WHERE code = 'FARM'), 'FARM-MUT', 'Ferme MUTALA'),
  ((SELECT id FROM site_types WHERE code = 'FARM'), 'FARM-DIK', 'Ferme DIKAPA'),
  ((SELECT id FROM site_types WHERE code = 'STORAGE'), 'SILO', 'Silos'),
  ((SELECT id FROM site_types WHERE code = 'INDUSTRIAL'), 'MINO', 'Minoterie ROOF'),
  ((SELECT id FROM site_types WHERE code = 'INDUSTRIAL'), 'PELL', 'Pelletisation'),
  ((SELECT id FROM site_types WHERE code = 'COMMERCIAL'), 'BOUCH', 'Boucherie'),
  ((SELECT id FROM site_types WHERE code = 'DEPOT'), 'DEP-DEV', 'Depot DEVCO'),
  ((SELECT id FROM site_types WHERE code = 'DEPOT'), 'DEP-DEC', 'Depot DECO');

INSERT INTO storage_locations (site_id, code, name, location_type)
SELECT id, CONCAT(code, '-PRINC'), CONCAT(name, ' - Emplacement principal'),
       CASE WHEN code = 'SILO' THEN 'silo' WHEN code IN ('MINO', 'PELL') THEN 'production' ELSE 'warehouse' END
FROM sites;

INSERT INTO cost_centers (site_id, code, name)
SELECT id, CONCAT('CC-', code), CONCAT('Centre de cout ', name) FROM sites;

INSERT INTO operational_units (site_id, cost_center_id, code, name)
SELECT s.id, c.id, CONCAT('UO-', s.code), CONCAT('Unite operationnelle ', s.name)
FROM sites s
INNER JOIN cost_centers c ON c.site_id = s.id;

ALTER TABLE silos ADD COLUMN site_id INT UNSIGNED NULL AFTER id;
ALTER TABLE machines ADD COLUMN site_id INT UNSIGNED NULL AFTER id;
ALTER TABLE weighings ADD COLUMN site_id INT UNSIGNED NULL AFTER id;
ALTER TABLE silo_movements ADD COLUMN site_id INT UNSIGNED NULL AFTER id;
ALTER TABLE machine_feeds ADD COLUMN site_id INT UNSIGNED NULL AFTER id;
ALTER TABLE production_batches ADD COLUMN site_id INT UNSIGNED NULL AFTER id;
ALTER TABLE waste_stocks ADD COLUMN site_id INT UNSIGNED NULL AFTER id;
ALTER TABLE waste_processings ADD COLUMN site_id INT UNSIGNED NULL AFTER id;
ALTER TABLE packaging ADD COLUMN site_id INT UNSIGNED NULL AFTER id;
ALTER TABLE finished_stocks ADD COLUMN site_id INT UNSIGNED NULL AFTER id;
ALTER TABLE distributions ADD COLUMN site_id INT UNSIGNED NULL AFTER id;
ALTER TABLE stock_movements ADD COLUMN site_id INT UNSIGNED NULL AFTER id;
ALTER TABLE alerts ADD COLUMN site_id INT UNSIGNED NULL AFTER user_id;
ALTER TABLE activity_logs ADD COLUMN site_id INT UNSIGNED NULL AFTER user_id;

-- Rattachement controle des donnees historiques.
UPDATE silos SET site_id = (SELECT id FROM sites WHERE code = 'SILO') WHERE site_id IS NULL;
UPDATE weighings SET site_id = (SELECT id FROM sites WHERE code = 'SILO') WHERE site_id IS NULL;
UPDATE silo_movements SET site_id = (SELECT id FROM sites WHERE code = 'SILO') WHERE site_id IS NULL;
UPDATE machines SET site_id = (SELECT id FROM sites WHERE code = CASE WHEN machine_type = 'waste' THEN 'PELL' ELSE 'MINO' END) WHERE site_id IS NULL;
UPDATE machine_feeds mf INNER JOIN machines m ON m.id = mf.machine_id SET mf.site_id = m.site_id WHERE mf.site_id IS NULL;
UPDATE production_batches pb INNER JOIN machine_feeds mf ON mf.id = pb.machine_feed_id SET pb.site_id = mf.site_id WHERE pb.site_id IS NULL;
UPDATE waste_stocks SET site_id = (SELECT id FROM sites WHERE code = 'PELL') WHERE site_id IS NULL;
UPDATE waste_processings wp INNER JOIN machines m ON m.id = wp.machine_id SET wp.site_id = m.site_id WHERE wp.site_id IS NULL;
UPDATE packaging p INNER JOIN production_batches pb ON pb.id = p.production_batch_id SET p.site_id = pb.site_id WHERE p.site_id IS NULL;
UPDATE finished_stocks fs INNER JOIN packaging p ON p.id = fs.packaging_id SET fs.site_id = p.site_id WHERE fs.site_id IS NULL;
UPDATE distributions d INNER JOIN finished_stocks fs ON fs.id = d.finished_stock_id SET d.site_id = fs.site_id WHERE d.site_id IS NULL;
UPDATE stock_movements sm
LEFT JOIN finished_stocks fs ON fs.id = sm.finished_stock_id
LEFT JOIN distributions d ON d.id = sm.distribution_id
LEFT JOIN products p ON p.id = sm.product_id
SET sm.site_id = COALESCE(fs.site_id, d.site_id,
  (SELECT id FROM sites WHERE code = CASE WHEN p.code = 'ALIMENT-BETAIL' THEN 'PELL' ELSE 'MINO' END))
WHERE sm.site_id IS NULL;

ALTER TABLE silos MODIFY site_id INT UNSIGNED NOT NULL;
ALTER TABLE machines MODIFY site_id INT UNSIGNED NOT NULL;
ALTER TABLE weighings MODIFY site_id INT UNSIGNED NOT NULL;
ALTER TABLE silo_movements MODIFY site_id INT UNSIGNED NOT NULL;
ALTER TABLE machine_feeds MODIFY site_id INT UNSIGNED NOT NULL;
ALTER TABLE production_batches MODIFY site_id INT UNSIGNED NOT NULL;
ALTER TABLE waste_stocks MODIFY site_id INT UNSIGNED NOT NULL;
ALTER TABLE waste_processings MODIFY site_id INT UNSIGNED NOT NULL;
ALTER TABLE packaging MODIFY site_id INT UNSIGNED NOT NULL;
ALTER TABLE finished_stocks MODIFY site_id INT UNSIGNED NOT NULL;
ALTER TABLE distributions MODIFY site_id INT UNSIGNED NOT NULL;
ALTER TABLE stock_movements MODIFY site_id INT UNSIGNED NOT NULL;

ALTER TABLE silos ADD KEY idx_silos_site_status (site_id, status, deleted_at), ADD CONSTRAINT fk_silos_site FOREIGN KEY (site_id) REFERENCES sites(id);
ALTER TABLE machines ADD KEY idx_machines_site_status (site_id, status, deleted_at), ADD CONSTRAINT fk_machines_site FOREIGN KEY (site_id) REFERENCES sites(id);
ALTER TABLE weighings ADD KEY idx_weighings_site_date (site_id, weighed_at), ADD CONSTRAINT fk_weighings_site FOREIGN KEY (site_id) REFERENCES sites(id);
ALTER TABLE silo_movements ADD KEY idx_silo_movements_site_date (site_id, movement_at), ADD CONSTRAINT fk_silo_movements_site FOREIGN KEY (site_id) REFERENCES sites(id);
ALTER TABLE machine_feeds ADD KEY idx_machine_feeds_site_date (site_id, fed_at), ADD CONSTRAINT fk_machine_feeds_site FOREIGN KEY (site_id) REFERENCES sites(id);
ALTER TABLE production_batches ADD KEY idx_production_batches_site_date (site_id, started_at), ADD CONSTRAINT fk_production_batches_site FOREIGN KEY (site_id) REFERENCES sites(id);
ALTER TABLE waste_stocks ADD KEY idx_waste_stocks_site_status (site_id, status, deleted_at), ADD CONSTRAINT fk_waste_stocks_site FOREIGN KEY (site_id) REFERENCES sites(id);
ALTER TABLE waste_processings ADD KEY idx_waste_processings_site_date (site_id, processed_at), ADD CONSTRAINT fk_waste_processings_site FOREIGN KEY (site_id) REFERENCES sites(id);
ALTER TABLE packaging ADD KEY idx_packaging_site_date (site_id, packaged_at), ADD CONSTRAINT fk_packaging_site FOREIGN KEY (site_id) REFERENCES sites(id);
ALTER TABLE finished_stocks ADD KEY idx_finished_stocks_site_product (site_id, product_id, bag_format_id), ADD CONSTRAINT fk_finished_stocks_site FOREIGN KEY (site_id) REFERENCES sites(id);
ALTER TABLE distributions ADD KEY idx_distributions_site_date (site_id, distributed_at), ADD CONSTRAINT fk_distributions_site FOREIGN KEY (site_id) REFERENCES sites(id);
ALTER TABLE stock_movements ADD KEY idx_stock_movements_site_product_date (site_id, product_id, movement_at), ADD CONSTRAINT fk_stock_movements_site FOREIGN KEY (site_id) REFERENCES sites(id);
ALTER TABLE alerts ADD KEY idx_alerts_site_status (site_id, status, read_at), ADD CONSTRAINT fk_alerts_site FOREIGN KEY (site_id) REFERENCES sites(id);
ALTER TABLE activity_logs ADD KEY idx_activity_logs_site_date (site_id, created_at), ADD CONSTRAINT fk_activity_logs_site FOREIGN KEY (site_id) REFERENCES sites(id);

-- Direction et administration ont une vue consolidee; les agents recoivent leur perimetre initial.
INSERT INTO user_sites (user_id, site_id, is_default)
SELECT u.id, s.id, CASE WHEN s.code = 'MINO' THEN 1 ELSE 0 END
FROM users u
INNER JOIN roles r ON r.id = u.role_id
CROSS JOIN sites s
WHERE r.slug IN ('administrateur', 'direction');

INSERT INTO user_sites (user_id, site_id, is_default)
SELECT u.id, s.id, 1
FROM users u
INNER JOIN roles r ON r.id = u.role_id
INNER JOIN sites s ON s.code = CASE
  WHEN r.slug IN ('agent-pont-bascule', 'agent-silo') THEN 'SILO'
  WHEN r.slug IN ('agent-production', 'agent-emballage') THEN 'MINO'
  WHEN r.slug = 'agent-distribution' THEN 'MINO'
  ELSE 'MINO'
END
WHERE r.slug NOT IN ('administrateur', 'direction');

INSERT INTO user_sites (user_id, site_id, is_default)
SELECT u.id, s.id, 0
FROM users u
INNER JOIN roles r ON r.id = u.role_id AND r.slug = 'agent-production'
INNER JOIN sites s ON s.code = 'PELL';

INSERT INTO user_sites (user_id, site_id, is_default)
SELECT u.id, s.id, 0
FROM users u
INNER JOIN roles r ON r.id = u.role_id AND r.slug = 'agent-silo'
INNER JOIN sites s ON s.code = 'MINO';
