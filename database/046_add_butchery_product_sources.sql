-- Référentiel des produits de transformation : pour chaque matière (carcasse, poisson), les produits qu'on peut en obtenir.
-- Le formulaire de transformation ne propose que les produits de la matière choisie, et le serveur refuse les autres.

CREATE TABLE butchery_product_sources (
 product_item_id INT UNSIGNED NOT NULL,
 raw_item_id INT UNSIGNED NOT NULL,
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(product_item_id,raw_item_id),
 KEY idx_bps_raw(raw_item_id),
 CONSTRAINT fk_bps_product FOREIGN KEY(product_item_id) REFERENCES butchery_items(id),
 CONSTRAINT fk_bps_raw FOREIGN KEY(raw_item_id) REFERENCES butchery_items(id),
 CONSTRAINT fk_bps_user FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Produits connus de chaque matière.
INSERT IGNORE INTO butchery_product_sources(product_item_id,raw_item_id)
 SELECT p.id,r.id FROM (
  SELECT 'MORCEAUX-PORC' product,'MP-PORC' raw UNION ALL SELECT 'PORC-COTELETTE','MP-PORC' UNION ALL SELECT 'PORC-MAKOSO','MP-PORC'
  UNION ALL SELECT 'ABATS','MP-PORC' UNION ALL SELECT 'MORCEAUX-BOEUF','MP-BOEUF' UNION ALL SELECT 'ABATS','MP-BOEUF'
  UNION ALL SELECT 'VOL-ENTIER','MP-VOLAILLE' UNION ALL SELECT 'MORCEAUX-VOLAILLE','MP-VOLAILLE'
  UNION ALL SELECT 'POULE-ENTIERE','MP-POULE' UNION ALL SELECT 'MORCEAUX-VOLAILLE','MP-POULE'
  UNION ALL SELECT 'POISSON-PREPARE','MP-POISSON') k
 JOIN butchery_items p ON p.code=k.product AND p.item_kind IN ('finished_product','co_product') AND p.deleted_at IS NULL
 JOIN butchery_items r ON r.code=k.raw AND r.item_kind='raw_material' AND r.deleted_at IS NULL;

-- Et les produits déjà obtenus de chaque matière lors des transformations passées.
INSERT IGNORE INTO butchery_product_sources(product_item_id,raw_item_id)
 SELECT DISTINCT o.item_id,c.item_id FROM butchery_order_consumptions c
 JOIN butchery_order_outputs o ON o.order_id=c.order_id
 JOIN butchery_items r ON r.id=c.item_id AND r.item_kind='raw_material' AND r.deleted_at IS NULL
 JOIN butchery_items p ON p.id=o.item_id AND p.item_kind IN ('finished_product','co_product') AND p.deleted_at IS NULL
 WHERE c.consumption_type='raw_material';
