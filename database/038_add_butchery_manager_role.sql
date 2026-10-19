-- Responsable boucherie: saisie et validation, sans suppression ni administration.
INSERT INTO roles(name,slug,is_sensitive,is_system,status)
SELECT 'Responsable boucherie','responsable-boucherie',0,0,'active'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE slug='responsable-boucherie');
INSERT INTO role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.component='butchery' AND p.action IN ('read','create','update','validate')
WHERE r.slug='responsable-boucherie' AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=r.id AND rp.permission_id=p.id);
