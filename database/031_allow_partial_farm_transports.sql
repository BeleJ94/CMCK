-- A harvest stock can supply several trucks. Quantities are reserved on approval.
-- Preserve the foreign-key index while removing the one-transport-per-stock limit.
ALTER TABLE agricultural_transports
    ADD INDEX idx_ag_transport_stock_status (stock_id, status, deleted_at),
    DROP INDEX uq_ag_transport_stock;
