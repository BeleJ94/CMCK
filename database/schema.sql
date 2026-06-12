CREATE DATABASE IF NOT EXISTS cmck_milltrack
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE cmck_milltrack;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS alerts;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS distributions;
DROP TABLE IF EXISTS finished_stocks;
DROP TABLE IF EXISTS packaging;
DROP TABLE IF EXISTS waste_processings;
DROP TABLE IF EXISTS waste_stocks;
DROP TABLE IF EXISTS production_batches;
DROP TABLE IF EXISTS machine_feeds;
DROP TABLE IF EXISTS silo_movements;
DROP TABLE IF EXISTS weighings;
DROP TABLE IF EXISTS bag_formats;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS machines;
DROP TABLE IF EXISTS silos;
DROP TABLE IF EXISTS trucks;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(50) NULL,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'active',
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE suppliers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  contact_name VARCHAR(150) NULL,
  phone VARCHAR(50) NULL,
  email VARCHAR(190) NULL,
  address TEXT NULL,
  rccm VARCHAR(100) NULL,
  id_nat VARCHAR(100) NULL,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trucks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT UNSIGNED NULL,
  plate_number VARCHAR(50) NOT NULL UNIQUE,
  driver_name VARCHAR(150) NULL,
  driver_phone VARCHAR(50) NULL,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_trucks_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  code VARCHAR(80) NOT NULL UNIQUE,
  category ENUM('raw_material', 'finished_product', 'waste') NOT NULL,
  unit VARCHAR(30) NOT NULL DEFAULT 'kg',
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE silos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  code VARCHAR(80) NOT NULL UNIQUE,
  product_id INT UNSIGNED NULL,
  capacity_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  current_stock_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  unit VARCHAR(30) NOT NULL DEFAULT 'kg',
  alert_threshold_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_silos_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE machines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  code VARCHAR(80) NOT NULL UNIQUE,
  machine_type VARCHAR(80) NOT NULL DEFAULT 'main',
  capacity_kg_hour DECIMAL(12,3) NULL,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bag_formats (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  weight_kg DECIMAL(10,3) NOT NULL,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE weighings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT UNSIGNED NOT NULL,
  truck_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  reference VARCHAR(100) NOT NULL UNIQUE,
  poids_brut DECIMAL(12,3) NOT NULL,
  poids_tare DECIMAL(12,3) NOT NULL,
  poids_net DECIMAL(12,3) NOT NULL,
  weighed_at DATETIME NOT NULL,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'pending',
  created_by INT UNSIGNED NULL,
  validated_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_weighings_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_weighings_truck FOREIGN KEY (truck_id) REFERENCES trucks(id),
  CONSTRAINT fk_weighings_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_weighings_created_by FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_weighings_validated_by FOREIGN KEY (validated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE silo_movements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  silo_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  weighing_id INT UNSIGNED NULL,
  movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
  quantity_kg DECIMAL(12,3) NOT NULL,
  stock_before_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  stock_after_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  movement_at DATETIME NOT NULL,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'validated',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_silo_movements_silo FOREIGN KEY (silo_id) REFERENCES silos(id),
  CONSTRAINT fk_silo_movements_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_silo_movements_weighing FOREIGN KEY (weighing_id) REFERENCES weighings(id),
  CONSTRAINT fk_silo_movements_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE machine_feeds (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_id INT UNSIGNED NOT NULL,
  silo_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  silo_movement_id INT UNSIGNED NULL,
  quantity_kg DECIMAL(12,3) NOT NULL,
  fed_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  observation TEXT NULL,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'pending',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_machine_feeds_machine FOREIGN KEY (machine_id) REFERENCES machines(id),
  CONSTRAINT fk_machine_feeds_silo FOREIGN KEY (silo_id) REFERENCES silos(id),
  CONSTRAINT fk_machine_feeds_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_machine_feeds_silo_movement FOREIGN KEY (silo_movement_id) REFERENCES silo_movements(id),
  CONSTRAINT fk_machine_feeds_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_batches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_feed_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  batch_number VARCHAR(100) NOT NULL UNIQUE,
  input_quantity_kg DECIMAL(12,3) NOT NULL,
  output_quantity_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  waste_quantity_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  started_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'pending',
  created_by INT UNSIGNED NULL,
  validated_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_production_batches_machine_feed FOREIGN KEY (machine_feed_id) REFERENCES machine_feeds(id),
  CONSTRAINT fk_production_batches_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_production_batches_created_by FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_production_batches_validated_by FOREIGN KEY (validated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE waste_stocks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  production_batch_id INT UNSIGNED NULL,
  quantity_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_waste_stocks_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_waste_stocks_production_batch FOREIGN KEY (production_batch_id) REFERENCES production_batches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE waste_processings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  waste_stock_id INT UNSIGNED NOT NULL,
  machine_id INT UNSIGNED NULL,
  input_quantity_kg DECIMAL(12,3) NOT NULL,
  output_quantity_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  processed_at DATETIME NOT NULL,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'pending',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_waste_processings_waste_stock FOREIGN KEY (waste_stock_id) REFERENCES waste_stocks(id),
  CONSTRAINT fk_waste_processings_machine FOREIGN KEY (machine_id) REFERENCES machines(id),
  CONSTRAINT fk_waste_processings_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE packaging (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  production_batch_id INT UNSIGNED NOT NULL,
  bag_format_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  bags_count INT UNSIGNED NOT NULL DEFAULT 0,
  total_weight_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  packaged_at DATETIME NOT NULL,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'pending',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_packaging_production_batch FOREIGN KEY (production_batch_id) REFERENCES production_batches(id),
  CONSTRAINT fk_packaging_bag_format FOREIGN KEY (bag_format_id) REFERENCES bag_formats(id),
  CONSTRAINT fk_packaging_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_packaging_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE finished_stocks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  bag_format_id INT UNSIGNED NOT NULL,
  packaging_id INT UNSIGNED NULL,
  quantity_bags INT UNSIGNED NOT NULL DEFAULT 0,
  total_weight_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_finished_stocks_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_finished_stocks_bag_format FOREIGN KEY (bag_format_id) REFERENCES bag_formats(id),
  CONSTRAINT fk_finished_stocks_packaging FOREIGN KEY (packaging_id) REFERENCES packaging(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE distributions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  finished_stock_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  bag_format_id INT UNSIGNED NOT NULL,
  recipient_name VARCHAR(180) NOT NULL,
  transporter VARCHAR(180) NULL,
  exit_voucher VARCHAR(100) NOT NULL UNIQUE,
  quantity_bags INT UNSIGNED NOT NULL,
  total_weight_kg DECIMAL(12,3) NOT NULL,
  distributed_at DATETIME NOT NULL,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'pending',
  created_by INT UNSIGNED NULL,
  validated_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_distributions_finished_stock FOREIGN KEY (finished_stock_id) REFERENCES finished_stocks(id),
  CONSTRAINT fk_distributions_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_distributions_bag_format FOREIGN KEY (bag_format_id) REFERENCES bag_formats(id),
  CONSTRAINT fk_distributions_created_by FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_distributions_validated_by FOREIGN KEY (validated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  finished_stock_id INT UNSIGNED NULL,
  distribution_id INT UNSIGNED NULL,
  movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
  quantity_bags INT UNSIGNED NOT NULL DEFAULT 0,
  quantity_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  stock_before_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  stock_after_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  movement_at DATETIME NOT NULL,
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'validated',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_stock_movements_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_stock_movements_finished_stock FOREIGN KEY (finished_stock_id) REFERENCES finished_stocks(id),
  CONSTRAINT fk_stock_movements_distribution FOREIGN KEY (distribution_id) REFERENCES distributions(id),
  CONSTRAINT fk_stock_movements_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alerts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  severity ENUM('info', 'warning', 'danger', 'success') NOT NULL DEFAULT 'info',
  status ENUM('active', 'inactive', 'pending', 'validated', 'cancelled') NOT NULL DEFAULT 'active',
  read_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_alerts_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  module VARCHAR(120) NULL,
  entity_type VARCHAR(120) NULL,
  entity_id BIGINT UNSIGNED NULL,
  description TEXT NULL,
  old_values LONGTEXT NULL,
  new_values LONGTEXT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_weighings_status ON weighings(status);
CREATE INDEX idx_weighings_weighed_at ON weighings(weighed_at);
CREATE INDEX idx_silo_movements_silo_date ON silo_movements(silo_id, movement_at);
CREATE INDEX idx_machine_feeds_machine_date ON machine_feeds(machine_id, fed_at);
CREATE INDEX idx_production_batches_status ON production_batches(status);
CREATE INDEX idx_finished_stocks_product_format ON finished_stocks(product_id, bag_format_id);
CREATE INDEX idx_stock_movements_product_date ON stock_movements(product_id, movement_at);
CREATE INDEX idx_activity_logs_user_date ON activity_logs(user_id, created_at);
CREATE INDEX idx_activity_logs_module_date ON activity_logs(module, created_at);
CREATE INDEX idx_activity_logs_action_date ON activity_logs(action, created_at);

DELIMITER $$

CREATE TRIGGER trg_weighings_before_insert
BEFORE INSERT ON weighings
FOR EACH ROW
BEGIN
  IF NEW.poids_tare > NEW.poids_brut THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'poids_tare must be less than or equal to poids_brut';
  END IF;

  IF NEW.poids_net <> NEW.poids_brut - NEW.poids_tare THEN
    SET NEW.poids_net = NEW.poids_brut - NEW.poids_tare;
  END IF;
END$$

CREATE TRIGGER trg_weighings_before_update
BEFORE UPDATE ON weighings
FOR EACH ROW
BEGIN
  IF NEW.poids_tare > NEW.poids_brut THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'poids_tare must be less than or equal to poids_brut';
  END IF;

  IF NEW.poids_net <> NEW.poids_brut - NEW.poids_tare THEN
    SET NEW.poids_net = NEW.poids_brut - NEW.poids_tare;
  END IF;
END$$

CREATE TRIGGER trg_silos_before_insert
BEFORE INSERT ON silos
FOR EACH ROW
BEGIN
  IF NEW.current_stock_kg < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'silo stock cannot be negative';
  END IF;
END$$

CREATE TRIGGER trg_silos_before_update
BEFORE UPDATE ON silos
FOR EACH ROW
BEGIN
  IF NEW.current_stock_kg < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'silo stock cannot be negative';
  END IF;
END$$

CREATE TRIGGER trg_silo_movements_before_insert
BEFORE INSERT ON silo_movements
FOR EACH ROW
BEGIN
  IF NEW.quantity_kg < 0 OR NEW.stock_before_kg < 0 OR NEW.stock_after_kg < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'silo movement quantities and stock cannot be negative';
  END IF;
END$$

CREATE TRIGGER trg_silo_movements_before_update
BEFORE UPDATE ON silo_movements
FOR EACH ROW
BEGIN
  IF NEW.quantity_kg < 0 OR NEW.stock_before_kg < 0 OR NEW.stock_after_kg < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'silo movement quantities and stock cannot be negative';
  END IF;
END$$

CREATE TRIGGER trg_finished_stocks_before_insert
BEFORE INSERT ON finished_stocks
FOR EACH ROW
BEGIN
  IF NEW.total_weight_kg < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'finished stock cannot be negative';
  END IF;
END$$

CREATE TRIGGER trg_finished_stocks_before_update
BEFORE UPDATE ON finished_stocks
FOR EACH ROW
BEGIN
  IF NEW.total_weight_kg < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'finished stock cannot be negative';
  END IF;
END$$

CREATE TRIGGER trg_stock_movements_before_insert
BEFORE INSERT ON stock_movements
FOR EACH ROW
BEGIN
  IF NEW.quantity_kg < 0 OR NEW.stock_before_kg < 0 OR NEW.stock_after_kg < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'finished stock movement quantities and stock cannot be negative';
  END IF;
END$$

CREATE TRIGGER trg_stock_movements_before_update
BEFORE UPDATE ON stock_movements
FOR EACH ROW
BEGIN
  IF NEW.quantity_kg < 0 OR NEW.stock_before_kg < 0 OR NEW.stock_after_kg < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'finished stock movement quantities and stock cannot be negative';
  END IF;
END$$

DELIMITER ;
