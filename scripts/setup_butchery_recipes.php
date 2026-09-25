<?php
// Idempotent, additive reference data setup. Never changes stocks or existing recipes.
require __DIR__.'/../app/helpers/functions.php';
require __DIR__.'/../app/core/Database.php';
$db=Database::getInstance()->connection();
$catalog=[
 ['DEC-PORC','Découpe de porc','MP-PORC','MORCEAUX-PORC','Morceaux de porc'],
 ['DEC-BOEUF','Découpe de bœuf','MP-BOEUF','MORCEAUX-BOEUF','Morceaux de bœuf'],
 ['DEC-VOLAILLE','Découpe de volaille','MP-VOLAILLE','MORCEAUX-VOLAILLE','Morceaux de volaille'],
 ['PREP-POISSON','Préparation de poisson','MP-POISSON','POISSON-PREPARE','Poisson préparé'],
];
$q=function($sql,$params=[])use($db){$s=$db->prepare($sql);$s->execute($params);return $s;};
$db->beginTransaction();
try{
 $actor=(int)$q("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='administrateur' AND u.status='active' AND u.deleted_at IS NULL ORDER BY u.id LIMIT 1")->fetchColumn();
 if(!$actor)throw new RuntimeException('Administrateur actif requis pour attribuer les fiches initiales.');
 $created=[];
 foreach($catalog as [$code,$name,$rawCode,$productCode,$productName]){
  $raw=$q("SELECT id FROM butchery_items WHERE code=? AND item_kind='raw_material' AND status='active' AND deleted_at IS NULL",[$rawCode])->fetchColumn();
  if(!$raw)throw new RuntimeException('Matière introuvable : '.$rawCode);
  $product=$q('SELECT * FROM butchery_items WHERE code=? FOR UPDATE',[$productCode])->fetch();
  if($product&&($product['item_kind']!=='finished_product'||$product['status']!=='active'||$product['deleted_at']))throw new RuntimeException('Article existant incompatible : '.$productCode);
  if(!$product){$q("INSERT INTO butchery_items(code,name,item_kind,unit,default_shelf_life_days,status) VALUES(?,?,'finished_product','kg',NULL,'active')",[$productCode,$productName]);$created[]=$productCode;}
  $recipe=$q('SELECT id FROM butchery_recipes WHERE code=? FOR UPDATE',[$code])->fetchColumn();
  if($recipe)continue;
  $q("INSERT INTO butchery_recipes(code,name,status,created_by) VALUES(?,?,'active',?)",[$code,$name,$actor]);$recipe=(int)$db->lastInsertId();
  $q("INSERT INTO butchery_recipe_versions(recipe_id,version_number,status,effective_from,created_by) VALUES(?,1,'active',CURDATE(),?)",[$recipe,$actor]);$version=(int)$db->lastInsertId();
  $q("INSERT INTO butchery_recipe_components(recipe_version_id,item_id,component_type,percentage) VALUES(?,?,'raw_material',100)",[$version,$raw]);$created[]=$code;
 }
 if($created)$q("INSERT INTO activity_logs(user_id,action,module,description,new_values,user_agent) VALUES(NULL,'setup_recipes','butchery',?,?, 'CLI setup_butchery_recipes')",['Initialisation des fiches de transformation et produits distincts, sans modification de stock.',json_encode(['created'=>$created,'reference_author_id'=>$actor],JSON_UNESCAPED_UNICODE)]);
 $db->commit();echo $created?'Créés : '.implode(', ',$created)."\n":"Références déjà présentes ; aucune modification.\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
