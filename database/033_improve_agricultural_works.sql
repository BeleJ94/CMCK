-- Run once before deploying the agricultural works editor.
ALTER TABLE agricultural_works
    ADD COLUMN responsible_name VARCHAR(160) NULL,
    ADD COLUMN currency CHAR(3) NULL,
    ADD COLUMN other_cost_reason VARCHAR(500) NULL,
    ADD COLUMN revision INT UNSIGNED NOT NULL DEFAULT 1,
    ADD COLUMN submission_key VARCHAR(64) NULL,
    ADD UNIQUE KEY uq_ag_work_submission (submission_key);
ALTER TABLE agricultural_work_labor ADD COLUMN unit_rate DECIMAL(14,4) NULL;
ALTER TABLE agricultural_work_equipment ADD COLUMN unit_rate DECIMAL(14,4) NULL;
UPDATE agricultural_work_labor SET unit_rate=cost/days_worked WHERE days_worked>0;
UPDATE agricultural_work_equipment SET unit_rate=cost/hours_used WHERE hours_used>0;
CREATE TABLE agricultural_work_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_id BIGINT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NOT NULL,
    action VARCHAR(20) NOT NULL,
    reason VARCHAR(500) NULL,
    old_values JSON NULL,
    new_values JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ag_work_history_work FOREIGN KEY(work_id) REFERENCES agricultural_works(id),
    CONSTRAINT fk_ag_work_history_actor FOREIGN KEY(actor_id) REFERENCES users(id),
    KEY idx_ag_work_history (work_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
