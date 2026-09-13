-- A prepared exit stays pending and has no inventory effect until validation.
ALTER TABLE weighings
    ADD COLUMN exit_draft JSON NULL,
    ADD COLUMN exit_prepared_by INT UNSIGNED NULL,
    ADD COLUMN exit_prepared_at DATETIME NULL;
