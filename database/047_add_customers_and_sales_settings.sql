-- Distribution commerciale, lot 1 : bureau principal, fiches clients, taux du jour et tarifs.
-- Le bureau principal regroupe les dépôts DEVCO et DECO : il recevra le stock affecté aux clients et accueillera le comptoir.

ALTER TABLE sites ADD COLUMN main_office TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
UPDATE sites SET main_office=1 WHERE code IN ('DEP-DEV','DEP-DEC') AND deleted_at IS NULL;

-- Clients entreprises, et particuliers réguliers qui souhaitent un compte.
CREATE TABLE customers (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(30) NOT NULL,
 customer_type ENUM('company','individual') NOT NULL DEFAULT 'company',
 name VARCHAR(180) NOT NULL,
 trade_name VARCHAR(180) NULL,
 rccm VARCHAR(100) NULL,
 id_nat VARCHAR(100) NULL,
 nif VARCHAR(100) NULL,
 contact_name VARCHAR(150) NULL,
 contact_role VARCHAR(100) NULL,
 phone VARCHAR(50) NOT NULL,
 email VARCHAR(190) NULL,
 address VARCHAR(255) NULL,
 city VARCHAR(120) NULL,
 billing_currency ENUM('USD','CDF') NOT NULL DEFAULT 'USD',
 notes VARCHAR(500) NULL,
 status ENUM('active','blocked') NOT NULL DEFAULT 'active',
 blocked_reason VARCHAR(255) NULL,
 blocked_at DATETIME NULL,
 blocked_by INT UNSIGNED NULL,
 created_by INT UNSIGNED NOT NULL,
 updated_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 deleted_at TIMESTAMP NULL,
 UNIQUE KEY uq_customers_code(code),
 UNIQUE KEY uq_customers_rccm(rccm),
 UNIQUE KEY uq_customers_id_nat(id_nat),
 UNIQUE KEY uq_customers_nif(nif),
 KEY idx_customers_name(name),
 CONSTRAINT fk_customers_creator FOREIGN KEY(created_by) REFERENCES users(id),
 CONSTRAINT fk_customers_editor FOREIGN KEY(updated_by) REFERENCES users(id),
 CONSTRAINT fk_customers_blocker FOREIGN KEY(blocked_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Taux du jour : 1 USD = rate CDF. Un nouveau taux remplace le précédent, l'historique est conservé.
CREATE TABLE exchange_rates (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 currency_from CHAR(3) NOT NULL DEFAULT 'USD',
 currency_to CHAR(3) NOT NULL DEFAULT 'CDF',
 rate DECIMAL(15,4) NOT NULL,
 effective_at DATETIME NOT NULL,
 notes VARCHAR(255) NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_exchange_rates_effective(currency_from,currency_to,effective_at),
 CONSTRAINT fk_exchange_rates_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tarifs en USD par produit, format de sac et canal (entreprises ou comptoir). Le dernier tarif en vigueur s'applique.
CREATE TABLE sale_prices (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_id INT UNSIGNED NOT NULL,
 bag_format_id INT UNSIGNED NOT NULL,
 channel ENUM('company','counter') NOT NULL,
 price_usd DECIMAL(14,2) NOT NULL,
 effective_at DATETIME NOT NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_sale_prices_current(product_id,bag_format_id,channel,effective_at),
 CONSTRAINT fk_sale_prices_product FOREIGN KEY(product_id) REFERENCES products(id),
 CONSTRAINT fk_sale_prices_format FOREIGN KEY(bag_format_id) REFERENCES bag_formats(id),
 CONSTRAINT fk_sale_prices_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
