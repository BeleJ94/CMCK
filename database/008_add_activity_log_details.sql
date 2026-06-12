USE cmck_milltrack;

ALTER TABLE activity_logs
  ADD COLUMN module VARCHAR(120) NULL AFTER action,
  ADD COLUMN old_values LONGTEXT NULL AFTER description,
  ADD COLUMN new_values LONGTEXT NULL AFTER old_values;

CREATE INDEX idx_activity_logs_module_date ON activity_logs(module, created_at);
CREATE INDEX idx_activity_logs_action_date ON activity_logs(action, created_at);
