-- Fiches de découpe du bœuf, du poulet et du poisson.
-- Jusqu'ici, seul le script scripts/setup_butchery_recipes.php les créait : une base où il n'a pas été lancé
-- refusait la transformation de ces carcasses (« Créez une fiche de découpe pour cette matière »).
-- Une matière qui a déjà une fiche active n'en reçoit pas d'autre ; les fiches existantes ne sont pas modifiées.

INSERT IGNORE INTO butchery_items(code,name,item_kind,unit) VALUES
 ('MORCEAUX-BOEUF','Morceaux de bœuf','finished_product','kg'),
 ('MORCEAUX-VOLAILLE','Morceaux de volaille','finished_product','kg'),
 ('POISSON-PREPARE','Poisson préparé','finished_product','kg');

INSERT IGNORE INTO butchery_recipes(code,name,status,created_by)
 SELECT f.code,f.name,'active',(SELECT MIN(id) FROM users WHERE deleted_at IS NULL)
 FROM (SELECT 'DEC-BOEUF' code,'Découpe de bœuf' name,'MP-BOEUF' raw_code
       UNION ALL SELECT 'DEC-VOLAILLE','Découpe de volaille','MP-VOLAILLE'
       UNION ALL SELECT 'PREP-POISSON','Préparation de poisson','MP-POISSON') f
 JOIN butchery_items i ON i.code=f.raw_code AND i.item_kind='raw_material' AND i.deleted_at IS NULL
 WHERE EXISTS(SELECT 1 FROM users WHERE deleted_at IS NULL)
 AND NOT EXISTS(SELECT 1 FROM butchery_recipe_components c
  JOIN butchery_recipe_versions v ON v.id=c.recipe_version_id
  JOIN butchery_recipes r ON r.id=v.recipe_id
  WHERE c.item_id=i.id AND c.component_type='raw_material' AND v.status='active' AND r.status='active' AND r.deleted_at IS NULL);

-- Version 1 et matière à 100 %, seulement pour les fiches qui viennent d'être créées (sans version).
INSERT IGNORE INTO butchery_recipe_versions(recipe_id,version_number,status,effective_from,created_by)
 SELECT r.id,1,'active',CURDATE(),r.created_by FROM butchery_recipes r
 WHERE r.code IN ('DEC-BOEUF','DEC-VOLAILLE','PREP-POISSON')
 AND NOT EXISTS(SELECT 1 FROM butchery_recipe_versions v WHERE v.recipe_id=r.id);
INSERT IGNORE INTO butchery_recipe_components(recipe_version_id,item_id,component_type,percentage)
 SELECT v.id,i.id,'raw_material',100 FROM butchery_recipe_versions v JOIN butchery_recipes r ON r.id=v.recipe_id
 JOIN butchery_items i ON i.code=CASE r.code WHEN 'DEC-BOEUF' THEN 'MP-BOEUF' WHEN 'DEC-VOLAILLE' THEN 'MP-VOLAILLE' ELSE 'MP-POISSON' END
 WHERE r.code IN ('DEC-BOEUF','DEC-VOLAILLE','PREP-POISSON') AND v.version_number=1
 AND NOT EXISTS(SELECT 1 FROM butchery_recipe_components c WHERE c.recipe_version_id=v.id);
