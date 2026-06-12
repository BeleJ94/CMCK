SET @schema_name = DATABASE();

SET @has_transporter = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'distributions'
    AND COLUMN_NAME = 'transporter'
);

SET @sql = IF(
  @has_transporter = 0,
  'ALTER TABLE distributions ADD COLUMN transporter VARCHAR(180) NULL AFTER recipient_name',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_exit_voucher = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'distributions'
    AND COLUMN_NAME = 'exit_voucher'
);

SET @sql = IF(
  @has_exit_voucher = 0,
  'ALTER TABLE distributions ADD COLUMN exit_voucher VARCHAR(100) NULL AFTER transporter',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE distributions
SET exit_voucher = CONCAT('BS-', YEAR(distributed_at), '-', LPAD(id, 4, '0'))
WHERE exit_voucher IS NULL OR exit_voucher = '';

SET @has_exit_voucher_unique = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @schema_name
    AND TABLE_NAME = 'distributions'
    AND INDEX_NAME = 'uq_distributions_exit_voucher'
);

SET @sql = IF(
  @has_exit_voucher_unique = 0,
  'ALTER TABLE distributions ADD UNIQUE KEY uq_distributions_exit_voucher (exit_voucher)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
