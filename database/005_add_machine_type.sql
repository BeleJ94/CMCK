USE cmck_milltrack;

ALTER TABLE machines
  ADD COLUMN machine_type VARCHAR(80) NOT NULL DEFAULT 'main' AFTER code;

UPDATE machines
SET machine_type = CASE
  WHEN code LIKE 'WASTE%' OR name LIKE '%dechet%' OR name LIKE '%déchet%' THEN 'waste'
  ELSE 'main'
END;
