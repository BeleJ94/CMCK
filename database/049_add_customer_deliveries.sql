-- Distribution commerciale, lot 3 : livraisons des commandes clients.
-- Une livraison (bon de livraison) remet au client des sacs réservés pour sa commande, depuis le bureau principal
-- ou directement depuis le site de production. Elle sort les sacs du stock et solde la commande quand tout est livré.

CREATE TABLE customer_deliveries (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 delivery_number VARCHAR(30) NOT NULL,
 order_id INT UNSIGNED NOT NULL,
 customer_id INT UNSIGNED NOT NULL,
 site_id INT UNSIGNED NOT NULL,
 source_stage ENUM('office','source') NOT NULL,
 delivery_mode ENUM('pickup','company_truck') NOT NULL,
 vehicle_reference VARCHAR(60) NULL,
 driver_name VARCHAR(150) NULL,
 receiver_name VARCHAR(150) NOT NULL,
 receiver_phone VARCHAR(50) NULL,
 delivered_at DATETIME NOT NULL,
 notes VARCHAR(500) NULL,
 status ENUM('validated','cancelled') NOT NULL DEFAULT 'validated',
 created_by INT UNSIGNED NOT NULL,
 cancelled_by INT UNSIGNED NULL,
 cancelled_at DATETIME NULL,
 cancel_reason VARCHAR(255) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_customer_deliveries_number(delivery_number),
 KEY idx_customer_deliveries_order(order_id),
 KEY idx_customer_deliveries_date(delivered_at),
 CONSTRAINT fk_cdeliveries_order FOREIGN KEY(order_id) REFERENCES customer_orders(id),
 CONSTRAINT fk_cdeliveries_customer FOREIGN KEY(customer_id) REFERENCES customers(id),
 CONSTRAINT fk_cdeliveries_site FOREIGN KEY(site_id) REFERENCES sites(id),
 CONSTRAINT fk_cdeliveries_creator FOREIGN KEY(created_by) REFERENCES users(id),
 CONSTRAINT fk_cdeliveries_canceller FOREIGN KEY(cancelled_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sacs livrés, lot par lot, au prix figé de la ligne de commande.
CREATE TABLE customer_delivery_lines (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 delivery_id INT UNSIGNED NOT NULL,
 order_line_id INT UNSIGNED NOT NULL,
 finished_stock_id INT UNSIGNED NOT NULL,
 quantity_bags INT UNSIGNED NOT NULL,
 quantity_kg DECIMAL(14,3) NOT NULL,
 unit_price_usd DECIMAL(14,2) NOT NULL,
 KEY idx_cdelivery_lines_delivery(delivery_id),
 CONSTRAINT fk_cdelivery_lines_delivery FOREIGN KEY(delivery_id) REFERENCES customer_deliveries(id),
 CONSTRAINT fk_cdelivery_lines_line FOREIGN KEY(order_line_id) REFERENCES customer_order_lines(id),
 CONSTRAINT fk_cdelivery_lines_stock FOREIGN KEY(finished_stock_id) REFERENCES finished_stocks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des sacs d'une commande : livraisons et leurs annulations, clôture du reste.
ALTER TABLE customer_order_movements MODIFY movement_type ENUM('reserve','release','transfer_out','transfer_back','office_in','rejected','delivered','delivery_cancelled') NOT NULL;
ALTER TABLE customer_orders ADD COLUMN closed_by INT UNSIGNED NULL AFTER cancel_reason, ADD COLUMN closed_at DATETIME NULL AFTER closed_by, ADD COLUMN close_reason VARCHAR(255) NULL AFTER closed_at,
 ADD CONSTRAINT fk_corders_closer FOREIGN KEY(closed_by) REFERENCES users(id);

-- Mouvements de stock fini d'une livraison, et client enregistré d'une sortie sans commande.
ALTER TABLE stock_movements ADD COLUMN customer_delivery_id INT UNSIGNED NULL AFTER distribution_id,
 ADD CONSTRAINT fk_stock_movements_cdelivery FOREIGN KEY(customer_delivery_id) REFERENCES customer_deliveries(id);
ALTER TABLE distributions ADD COLUMN customer_id INT UNSIGNED NULL AFTER site_id,
 ADD CONSTRAINT fk_distributions_customer FOREIGN KEY(customer_id) REFERENCES customers(id);
