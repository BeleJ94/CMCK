-- Managers may record and validate their own operations on their assigned farm.
-- Site assignments remain explicit; this migration assigns no user to a role or site.
INSERT INTO roles(name,slug,is_sensitive,is_system,status)
SELECT 'Responsable élevage','responsable-elevage',0,0,'active'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE slug='responsable-elevage');
INSERT INTO roles(name,slug,is_sensitive,is_system,status)
SELECT 'Responsable ferme','responsable-ferme',0,0,'active'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE slug='responsable-ferme');
INSERT INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.action IN ('read','create','update','validate')
WHERE ((r.slug='responsable-elevage' AND p.component='livestock')
    OR (r.slug='responsable-ferme' AND p.component IN ('agriculture','livestock')))
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=r.id AND rp.permission_id=p.id);
