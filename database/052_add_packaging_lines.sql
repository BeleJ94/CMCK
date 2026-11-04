-- Conditionnement par quantité globale : un emballage peut prélever la farine en vrac sur plusieurs lots
-- (le plus ancien d'abord). Chaque ligne garde le lot exact pour la traçabilité et les annulations.
CREATE TABLE IF NOT EXISTS packaging_lines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  packaging_id INT UNSIGNED NOT NULL,
  production_batch_id INT UNSIGNED NOT NULL,
  bulk_flour_stock_id BIGINT UNSIGNED NULL,
  quantity_kg DECIMAL(12,3) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_packaging_line_batch (packaging_id, production_batch_id),
  KEY idx_packaging_lines_batch (production_batch_id),
  CONSTRAINT fk_pkl_packaging FOREIGN KEY (packaging_id) REFERENCES packaging(id),
  CONSTRAINT fk_pkl_batch FOREIGN KEY (production_batch_id) REFERENCES production_batches(id),
  CONSTRAINT fk_pkl_bulk FOREIGN KEY (bulk_flour_stock_id) REFERENCES bulk_flour_stocks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reprise de l'existant : chaque ancien emballage devient une ligne sur son lot unique.
INSERT IGNORE INTO packaging_lines (packaging_id, production_batch_id, bulk_flour_stock_id, quantity_kg, created_at)
SELECT p.id, p.production_batch_id,
       (SELECT b.id FROM bulk_flour_stocks b WHERE b.production_batch_id = p.production_batch_id ORDER BY b.id LIMIT 1),
       p.total_weight_kg, p.created_at
FROM packaging p
WHERE p.production_batch_id IS NOT NULL;
