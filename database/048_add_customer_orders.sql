-- Distribution commerciale, lot 2 : commandes clients, réservation au site de production (minoterie, pelletisation),
-- transferts affectés vers le bureau principal et stock client au bureau.
-- Les sacs réservés pour un client utilisent finished_stocks.reserved_bags : aucune autre sortie ne peut les prendre.

CREATE TABLE customer_orders (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_number VARCHAR(30) NOT NULL,
 customer_id INT UNSIGNED NOT NULL,
 destination_site_id INT UNSIGNED NOT NULL,
 currency ENUM('USD','CDF') NOT NULL DEFAULT 'USD',
 exchange_rate DECIMAL(15,4) NULL,
 requested_date DATE NULL,
 notes VARCHAR(500) NULL,
 status ENUM('draft','validated','closed','cancelled') NOT NULL DEFAULT 'draft',
 created_by INT UNSIGNED NOT NULL,
 validated_by INT UNSIGNED NULL,
 validated_at DATETIME NULL,
 cancelled_by INT UNSIGNED NULL,
 cancelled_at DATETIME NULL,
 cancel_reason VARCHAR(255) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_customer_orders_number(order_number),
 KEY idx_customer_orders_status(status,validated_at),
 CONSTRAINT fk_corders_customer FOREIGN KEY(customer_id) REFERENCES customers(id),
 CONSTRAINT fk_corders_destination FOREIGN KEY(destination_site_id) REFERENCES sites(id),
 CONSTRAINT fk_corders_creator FOREIGN KEY(created_by) REFERENCES users(id),
 CONSTRAINT fk_corders_validator FOREIGN KEY(validated_by) REFERENCES users(id),
 CONSTRAINT fk_corders_canceller FOREIGN KEY(cancelled_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une ligne par produit et format ; le prix en USD est figé à l'enregistrement de la commande.
CREATE TABLE customer_order_lines (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id INT UNSIGNED NOT NULL,
 product_id INT UNSIGNED NOT NULL,
 bag_format_id INT UNSIGNED NOT NULL,
 source_site_id INT UNSIGNED NOT NULL,
 quantity_bags INT UNSIGNED NOT NULL,
 unit_price_usd DECIMAL(14,2) NOT NULL,
 delivered_bags INT UNSIGNED NOT NULL DEFAULT 0,
 UNIQUE KEY uq_corder_lines_product(order_id,product_id,bag_format_id),
 KEY idx_corder_lines_source(source_site_id,product_id,bag_format_id),
 CONSTRAINT fk_corder_lines_order FOREIGN KEY(order_id) REFERENCES customer_orders(id),
 CONSTRAINT fk_corder_lines_product FOREIGN KEY(product_id) REFERENCES products(id),
 CONSTRAINT fk_corder_lines_format FOREIGN KEY(bag_format_id) REFERENCES bag_formats(id),
 CONSTRAINT fk_corder_lines_source FOREIGN KEY(source_site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sacs réservés pour une ligne : au site de production (source) ou au bureau principal (office).
CREATE TABLE customer_order_allocations (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_line_id INT UNSIGNED NOT NULL,
 finished_stock_id INT UNSIGNED NOT NULL,
 site_id INT UNSIGNED NOT NULL,
 stage ENUM('source','office') NOT NULL,
 quantity_bags INT UNSIGNED NOT NULL DEFAULT 0,
 quantity_kg DECIMAL(14,3) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_corder_allocations(order_line_id,finished_stock_id,stage),
 KEY idx_corder_allocations_stock(finished_stock_id),
 CONSTRAINT fk_corder_alloc_line FOREIGN KEY(order_line_id) REFERENCES customer_order_lines(id),
 CONSTRAINT fk_corder_alloc_stock FOREIGN KEY(finished_stock_id) REFERENCES finished_stocks(id),
 CONSTRAINT fk_corder_alloc_site FOREIGN KEY(site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des sacs d'une commande : base du relevé de commande.
CREATE TABLE customer_order_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id INT UNSIGNED NOT NULL,
 order_line_id INT UNSIGNED NOT NULL,
 movement_type ENUM('reserve','release','transfer_out','transfer_back','office_in','rejected') NOT NULL,
 quantity_bags INT UNSIGNED NOT NULL,
 quantity_kg DECIMAL(14,3) NOT NULL,
 finished_stock_id INT UNSIGNED NULL,
 transfer_id BIGINT UNSIGNED NULL,
 notes VARCHAR(255) NULL,
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_corder_movements_order(order_id,id),
 CONSTRAINT fk_corder_mov_order FOREIGN KEY(order_id) REFERENCES customer_orders(id),
 CONSTRAINT fk_corder_mov_line FOREIGN KEY(order_line_id) REFERENCES customer_order_lines(id),
 CONSTRAINT fk_corder_mov_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transferts affectés à une commande : leurs sacs restent ceux du client jusqu'au bureau principal.
ALTER TABLE stock_transfers ADD COLUMN customer_order_id INT UNSIGNED NULL AFTER transfer_type,
 ADD CONSTRAINT fk_stock_transfers_corder FOREIGN KEY(customer_order_id) REFERENCES customer_orders(id);
ALTER TABLE stock_transfer_items ADD COLUMN customer_order_line_id INT UNSIGNED NULL AFTER source_finished_stock_id,
 ADD CONSTRAINT fk_stock_transfer_items_cline FOREIGN KEY(customer_order_line_id) REFERENCES customer_order_lines(id);
