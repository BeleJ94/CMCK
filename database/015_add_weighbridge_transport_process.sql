-- DAGRIL ERP - transport pont-bascule et contrôle qualité (non destructif)

CREATE TABLE IF NOT EXISTS weighbridge_transports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id INT UNSIGNED NOT NULL,
  origin_type ENUM('external_supplier','internal_farm') NOT NULL,
  origin_site_id INT UNSIGNED NULL,
  supplier_id INT UNSIGNED NULL,
  product_id INT UNSIGNED NOT NULL,
  truck_id INT UNSIGNED NOT NULL,
  transport_reference VARCHAR(100) NOT NULL UNIQUE,
  shipped_quantity_kg DECIMAL(12,3) NOT NULL,
  route_description VARCHAR(500) NULL,
  toll_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  toll_details VARCHAR(500) NULL,
  status ENUM('draft','loading','in_transit','arrived','completed','rejected','return_pending','returned','cancelled') NOT NULL DEFAULT 'draft',
  loaded_at DATETIME NULL,
  dispatched_at DATETIME NULL,
  arrived_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  KEY idx_wbt_site_status (site_id,status),
  KEY idx_wbt_origin_site (origin_site_id),
  CONSTRAINT fk_wbt_site FOREIGN KEY (site_id) REFERENCES sites(id),
  CONSTRAINT fk_wbt_origin_site FOREIGN KEY (origin_site_id) REFERENCES sites(id),
  CONSTRAINT fk_wbt_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_wbt_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_wbt_truck FOREIGN KEY (truck_id) REFERENCES trucks(id),
  CONSTRAINT fk_wbt_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE weighings MODIFY supplier_id INT UNSIGNED NULL;
ALTER TABLE weighings
  ADD COLUMN transport_id BIGINT UNSIGNED NULL AFTER site_id,
  ADD COLUMN destination_silo_id INT UNSIGNED NULL AFTER product_id,
  ADD COLUMN shipped_quantity_kg DECIMAL(12,3) NULL AFTER poids_net,
  ADD COLUMN weight_variance_kg DECIMAL(12,3) NULL AFTER shipped_quantity_kg,
  ADD COLUMN weight_variance_percent DECIMAL(8,3) NULL AFTER weight_variance_kg,
  ADD COLUMN weight_tolerance_percent DECIMAL(8,3) NOT NULL DEFAULT 2.000 AFTER weight_variance_percent,
  ADD COLUMN humidity_percent DECIMAL(6,3) NULL AFTER weight_tolerance_percent,
  ADD COLUMN impurities_percent DECIMAL(6,3) NULL AFTER humidity_percent,
  ADD COLUMN max_humidity_percent DECIMAL(6,3) NOT NULL DEFAULT 14.000 AFTER impurities_percent,
  ADD COLUMN max_impurities_percent DECIMAL(6,3) NOT NULL DEFAULT 2.000 AFTER max_humidity_percent,
  ADD COLUMN conformity_status ENUM('pending','conform','non_conform','rejected') NOT NULL DEFAULT 'pending' AFTER max_impurities_percent,
  ADD COLUMN quality_notes TEXT NULL AFTER conformity_status,
  ADD COLUMN quality_checked_by INT UNSIGNED NULL AFTER quality_notes,
  ADD COLUMN quality_checked_at DATETIME NULL AFTER quality_checked_by,
  ADD COLUMN unloaded_at DATETIME NULL AFTER weighed_at,
  ADD KEY idx_weighings_transport (transport_id),
  ADD KEY idx_weighings_silo_status (destination_silo_id,status),
  ADD CONSTRAINT fk_weighings_transport FOREIGN KEY (transport_id) REFERENCES weighbridge_transports(id),
  ADD CONSTRAINT fk_weighings_destination_silo FOREIGN KEY (destination_silo_id) REFERENCES silos(id),
  ADD CONSTRAINT fk_weighings_quality_user FOREIGN KEY (quality_checked_by) REFERENCES users(id);

ALTER TABLE weighings MODIFY status ENUM('active','inactive','pending','validated','rejected','return_pending','returned','cancelled') NOT NULL DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS weighbridge_non_conformities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  weighing_id INT UNSIGNED NOT NULL,
  reference VARCHAR(100) NOT NULL UNIQUE,
  discrepancy_type ENUM('weight','quality','weight_and_quality') NOT NULL,
  shipped_quantity_kg DECIMAL(12,3) NULL,
  net_quantity_kg DECIMAL(12,3) NOT NULL,
  variance_kg DECIMAL(12,3) NULL,
  humidity_percent DECIMAL(6,3) NULL,
  impurities_percent DECIMAL(6,3) NULL,
  description TEXT NOT NULL,
  status ENUM('open','accepted_with_reservation','return_pending','closed','cancelled') NOT NULL DEFAULT 'open',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  KEY idx_wbnc_weighing (weighing_id,status),
  CONSTRAINT fk_wbnc_weighing FOREIGN KEY (weighing_id) REFERENCES weighings(id),
  CONSTRAINT fk_wbnc_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS weighbridge_returns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  weighing_id INT UNSIGNED NOT NULL,
  return_number VARCHAR(100) NOT NULL UNIQUE,
  reason TEXT NOT NULL,
  status ENUM('planned','dispatched','received','closed','cancelled') NOT NULL DEFAULT 'planned',
  planned_at DATETIME NOT NULL,
  dispatched_at DATETIME NULL,
  received_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uq_wbr_active_weighing (weighing_id),
  CONSTRAINT fk_wbr_weighing FOREIGN KEY (weighing_id) REFERENCES weighings(id),
  CONSTRAINT fk_wbr_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les tickets historiques restent externes et conformes à leur état déjà validé.
UPDATE weighings w
LEFT JOIN silo_movements sm ON sm.weighing_id=w.id AND sm.deleted_at IS NULL
SET w.destination_silo_id=sm.silo_id,
    w.shipped_quantity_kg=w.poids_net,
    w.weight_variance_kg=0,
    w.weight_variance_percent=0,
    w.conformity_status=IF(w.status='validated','conform','pending')
WHERE w.transport_id IS NULL;
