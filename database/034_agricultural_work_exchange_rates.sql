-- USD is the default for historical amounts with no currency.
-- Existing CDF amounts retain their currency; their historical rate must be entered.
ALTER TABLE agricultural_works ADD COLUMN exchange_rate DECIMAL(15,6) NULL;
UPDATE agricultural_works SET currency='USD' WHERE currency IS NULL OR TRIM(currency)='';
UPDATE agricultural_works SET exchange_rate=1 WHERE currency='USD';
ALTER TABLE agricultural_works MODIFY COLUMN currency CHAR(3) NOT NULL DEFAULT 'USD';
ALTER TABLE agricultural_works ALTER COLUMN exchange_rate SET DEFAULT 1;
