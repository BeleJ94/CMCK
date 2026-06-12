USE cmck_milltrack;

ALTER TABLE suppliers
  ADD COLUMN rccm VARCHAR(100) NULL AFTER address,
  ADD COLUMN id_nat VARCHAR(100) NULL AFTER rccm;
