CREATE TABLE IF NOT EXISTS bulk_flour_stock_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 bulk_flour_stock_id BIGINT UNSIGNED NOT NULL,
 production_batch_id INT UNSIGNED NOT NULL,
 packaging_id INT UNSIGNED NULL,
 movement_type ENUM('production_in','packaging_out','reversal') NOT NULL,
 quantity_kg DECIMAL(12,3) NOT NULL,
 stock_before_kg DECIMAL(12,3) NOT NULL,
 stock_after_kg DECIMAL(12,3) NOT NULL,
 movement_at DATETIME NOT NULL,
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_bfsm_stock_date(bulk_flour_stock_id,movement_at),
 CONSTRAINT fk_bfsm_stock FOREIGN KEY(bulk_flour_stock_id) REFERENCES bulk_flour_stocks(id),
 CONSTRAINT fk_bfsm_batch FOREIGN KEY(production_batch_id) REFERENCES production_batches(id),
 CONSTRAINT fk_bfsm_packaging FOREIGN KEY(packaging_id) REFERENCES packaging(id),
 CONSTRAINT fk_bfsm_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO bulk_flour_stock_movements(bulk_flour_stock_id,production_batch_id,movement_type,quantity_kg,stock_before_kg,stock_after_kg,movement_at,created_by)
SELECT bfs.id,bfs.production_batch_id,'production_in',pb.output_quantity_kg,0,pb.output_quantity_kg,COALESCE(pb.ended_at,pb.started_at),pb.validated_by
FROM bulk_flour_stocks bfs JOIN production_batches pb ON pb.id=bfs.production_batch_id
WHERE NOT EXISTS(SELECT 1 FROM bulk_flour_stock_movements m WHERE m.bulk_flour_stock_id=bfs.id);
