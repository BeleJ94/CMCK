-- Transport ferme → silo par quantité globale : un bon de transport (BT) peut charger plusieurs récoltes
-- d'un même produit et d'une même variété, les plus anciennes d'abord. Chaque ligne garde la récolte exacte
-- (réservation, expédition, traçabilité).
CREATE TABLE IF NOT EXISTS agricultural_transport_lines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transport_id BIGINT UNSIGNED NOT NULL,
  stock_id BIGINT UNSIGNED NOT NULL,
  quantity_kg DECIMAL(12,3) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_agri_transport_line (transport_id, stock_id),
  KEY idx_agri_transport_lines_stock (stock_id),
  CONSTRAINT fk_atl_transport FOREIGN KEY (transport_id) REFERENCES agricultural_transports(id),
  CONSTRAINT fk_atl_stock FOREIGN KEY (stock_id) REFERENCES agricultural_stocks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reprise de l'existant : chaque ancien BT devient une ligne sur sa récolte unique.
INSERT IGNORE INTO agricultural_transport_lines (transport_id, stock_id, quantity_kg, created_at)
SELECT t.id, t.stock_id, t.quantity_kg, t.created_at FROM agricultural_transports t WHERE t.stock_id IS NOT NULL;
