-- DAGRIL ERP - traçabilité transversale (migration additive et réexécutable)
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='weighbridge_transports') AND EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='agricultural_transports') AND NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='weighbridge_transports' AND column_name='agricultural_transport_id'),'ALTER TABLE weighbridge_transports ADD COLUMN agricultural_transport_id BIGINT UNSIGNED NULL AFTER origin_site_id, ADD KEY idx_wbt_agricultural_transport(agricultural_transport_id), ADD CONSTRAINT fk_wbt_agricultural_transport FOREIGN KEY(agricultural_transport_id) REFERENCES agricultural_transports(id)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Reprise uniquement des références textuelles strictement uniques.
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='weighbridge_transports' AND column_name='agricultural_transport_id') AND EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='agricultural_transports'),"UPDATE weighbridge_transports w JOIN agricultural_transports a ON a.transport_number COLLATE utf8mb4_unicode_ci=w.transport_reference COLLATE utf8mb4_unicode_ci LEFT JOIN (SELECT transport_number,COUNT(*) n FROM agricultural_transports GROUP BY transport_number) x ON x.transport_number COLLATE utf8mb4_unicode_ci=a.transport_number COLLATE utf8mb4_unicode_ci SET w.agricultural_transport_id=a.id WHERE w.agricultural_transport_id IS NULL AND w.origin_type='internal_farm' AND x.n=1",'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO permissions(component,action,description) VALUES
('traceability','read','Rechercher et consulter la traçabilité'),
('traceability','create','Créer un lien de traçabilité'),
('traceability','validate','Valider un lien de traçabilité'),
('traceability','update','Corriger un lien de traçabilité'),
('traceability','delete','Annuler un lien de traçabilité'),
('traceability','administer','Administrer la traçabilité');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component='traceability' AND p.action='read' WHERE r.slug IN('administrateur','administrateur-systeme','direction','directeur-general','directeur-financier','auditeur','directeur-site','responsable-site','gestionnaire-stock');
