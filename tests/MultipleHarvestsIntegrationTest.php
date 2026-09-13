<?php
// Reuse isolated harvest fixtures and exercise their correction protections first.
require __DIR__.'/HarvestUpdateIntegrationTest.php';
$first=$id;$firstStock=sql('SELECT * FROM agricultural_stocks WHERE harvest_id=?',[$first])->fetch();
$second=$m->createHarvest(array_replace($base,['gross_weight_kg'=>2100]),$user);
$third=$m->createHarvest(array_replace($base,['gross_weight_kg'=>3100]),$user);
check(count(array_unique([$first,$second,$third]))===3,'Trois récoltes distinctes pour la même campagne/parcelle');
check((int)sql('SELECT COUNT(DISTINCT harvest_number) FROM agricultural_harvests WHERE campaign_plot_id=?',[$cp])->fetchColumn()===3,'Numéros de récolte distincts');
$stock2=$m->validateHarvest($second,$user);$stock3=$m->validateHarvest($third,$user);
check(count(array_unique([$firstStock['id'],$stock2,$stock3]))===3,'Un stock distinct pour chaque récolte validée');
check((float)sql('SELECT physical_quantity_kg FROM agricultural_stocks WHERE id=?',[$stock2])->fetchColumn()===2000.0&&(float)sql('SELECT physical_quantity_kg FROM agricultural_stocks WHERE id=?',[$stock3])->fetchColumn()===3000.0,'Quantités de lots indépendantes');
check($firstStock===sql('SELECT * FROM agricultural_stocks WHERE harvest_id=?',[$first])->fetch(),'Stock et réservations du premier lot inchangés');
$rows=$db->query("SELECT cp.campaign_id,SUM(h.net_weight_kg)/1000 tons FROM agricultural_campaign_plots cp JOIN agricultural_harvests h ON h.campaign_plot_id=cp.id WHERE h.status='validated' AND h.deleted_at IS NULL AND cp.campaign_id=".(int)$campaign.' GROUP BY cp.campaign_id')->fetch();
check((float)$rows['tons']===6.3,'Production cumulée : 1,3 + 2 + 3 = 6,3 tonnes');
$edit=array_replace($base,['version'=>Agriculture::harvestVersion(sql('SELECT * FROM agricultural_harvests WHERE id=?',[$second])->fetch()),'gross_weight_kg'=>2200,'reason'=>'Correction du deuxième lot']);$m->updateHarvest($second,$edit,$user);
check((float)sql('SELECT physical_quantity_kg FROM agricultural_stocks WHERE id=?',[$stock2])->fetchColumn()===2100.0&&(float)sql('SELECT physical_quantity_kg FROM agricultural_stocks WHERE id=?',[$stock3])->fetchColumn()===3000.0,'Correction du deuxième lot sans effet sur le troisième');
check($firstStock===sql('SELECT * FROM agricultural_stocks WHERE harvest_id=?',[$first])->fetch(),'Correction sans effet sur les réservations du premier lot');
echo "Tests de récoltes multiples terminés.\n";
