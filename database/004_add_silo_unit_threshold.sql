USE cmck_milltrack;

ALTER TABLE silos
  ADD COLUMN unit VARCHAR(30) NOT NULL DEFAULT 'kg' AFTER current_stock_kg,
  ADD COLUMN alert_threshold_kg DECIMAL(12,3) NOT NULL DEFAULT 0 AFTER unit;
