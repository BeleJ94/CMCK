CREATE TABLE IF NOT EXISTS butchery_pos_prices (
 site_id INT UNSIGNED NOT NULL,
 item_id INT UNSIGNED NOT NULL,
 currency CHAR(3) NOT NULL,
 price DECIMAL(14,2) NOT NULL,
 updated_by INT UNSIGNED NOT NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(site_id,item_id,currency),
 FOREIGN KEY(site_id) REFERENCES sites(id),
 FOREIGN KEY(item_id) REFERENCES butchery_items(id),
 FOREIGN KEY(updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS butchery_pos_tickets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 site_id INT UNSIGNED NOT NULL,
 counter_id INT UNSIGNED NOT NULL,
 ticket_number VARCHAR(100) NOT NULL UNIQUE,
 counter_name VARCHAR(150) NOT NULL,
 cashier_name VARCHAR(180) NOT NULL,
 customer_name VARCHAR(180) NOT NULL,
 currency CHAR(3) NOT NULL,
 total DECIMAL(18,2) NOT NULL,
 payment_method ENUM('cash','mobile_money','card','bank_transfer') NOT NULL,
 received_amount DECIMAL(18,2) NOT NULL,
 change_amount DECIMAL(18,2) NOT NULL,
 status ENUM('paid','cancelled') NOT NULL DEFAULT 'paid',
 created_by INT UNSIGNED NOT NULL,
 sold_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 cancelled_by INT UNSIGNED NULL,
 cancelled_at DATETIME NULL,
 cancellation_reason VARCHAR(500) NULL,
 INDEX(site_id,sold_at),
 FOREIGN KEY(site_id) REFERENCES sites(id),
 FOREIGN KEY(counter_id) REFERENCES butchery_counters(id),
 FOREIGN KEY(created_by) REFERENCES users(id),
 FOREIGN KEY(cancelled_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS butchery_pos_lines (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ticket_id BIGINT UNSIGNED NOT NULL,
 item_id INT UNSIGNED NOT NULL,
 item_name VARCHAR(160) NOT NULL,
 quantity_kg DECIMAL(14,3) NOT NULL,
 unit_price DECIMAL(14,2) NOT NULL,
 amount DECIMAL(18,2) NOT NULL,
 UNIQUE KEY(ticket_id,item_id),
 FOREIGN KEY(ticket_id) REFERENCES butchery_pos_tickets(id),
 FOREIGN KEY(item_id) REFERENCES butchery_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS butchery_pos_allocations (
 line_id BIGINT UNSIGNED NOT NULL,
 sale_id BIGINT UNSIGNED NOT NULL UNIQUE,
 PRIMARY KEY(line_id,sale_id),
 FOREIGN KEY(line_id) REFERENCES butchery_pos_lines(id),
 FOREIGN KEY(sale_id) REFERENCES butchery_sales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE butchery_counter_movements MODIFY movement_type ENUM('supply','return_cold','sale','sale_cancel') NOT NULL;
