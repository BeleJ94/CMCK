<?php
/** Weighing input and transit gap rules, without database. */
require_once __DIR__.'/../app/core/Model.php';
require_once __DIR__.'/../app/services/TransitWeighingService.php';

$checks = 0;
function check($condition,$message) { global $checks; if (!$condition) throw new RuntimeException('ÉCHEC : '.$message); $checks++; echo "OK : $message\n"; }
function rejected($callback,$message,$expected) { try { $callback(); } catch (RuntimeException $e) { check(strpos($e->getMessage(),$expected) !== false,$message.' · « '.$e->getMessage().' »'); return; } throw new RuntimeException('ÉCHEC : '.$message); }

$net = TransitWeighingService::parse(['weighing_mode'=>'net','net_kg'=>'146.5'], 'Poids reçu');
check($net === ['mode'=>'net','gross'=>146.5,'tare'=>0.0,'net'=>146.5,'tare_note'=>null], 'Poids net saisi directement');
$gross = TransitWeighingService::parse(['weighing_mode'=>'gross','gross_kg'=>'158','tare_kg'=>'12,25','tare_note'=>'  Glace et caisses '], 'Poids reçu');
check($gross['net'] === 145.75 && $gross['tare'] === 12.25 && $gross['tare_note'] === 'Glace et caisses', 'Brut − tare : net calculé, virgule décimale acceptée, nature de la tare conservée');
check(TransitWeighingService::parse(['weighing_mode'=>'gross','gross_kg'=>'10','tare_kg'=>'0'])['net'] === 10.0, 'Tare nulle acceptée');
check(TransitWeighingService::parse(['net_kg'=>'5'])['mode'] === 'net', 'Mode net par défaut');
rejected(fn()=>TransitWeighingService::parse(['weighing_mode'=>'gross','gross_kg'=>'10','tare_kg'=>'10']), 'Tare égale au brut refusée', 'inférieure au poids brut');
rejected(fn()=>TransitWeighingService::parse(['weighing_mode'=>'net','net_kg'=>'0'], 'Poids reçu'), 'Poids nul refusé', 'Poids reçu net');
rejected(fn()=>TransitWeighingService::parse(['weighing_mode'=>'net','net_kg'=>'1.2345']), 'Plus de 3 décimales refusées', '3 décimales');
rejected(fn()=>TransitWeighingService::parse(['weighing_mode'=>'net','net_kg'=>'-4']), 'Poids négatif refusé', 'kg');
rejected(fn()=>TransitWeighingService::parse(['weighing_mode'=>'gross','gross_kg'=>'10']), 'Tare manquante refusée', 'Tare');
rejected(fn()=>TransitWeighingService::parse(['weighing_mode'=>'auto','net_kg'=>'4']), 'Mode inconnu refusé', 'Mode de pesée');
rejected(fn()=>TransitWeighingService::parse(['weighing_mode'=>'gross','gross_kg'=>'10','tare_kg'=>'1','tare_note'=>str_repeat('x',121)]), 'Nature de la tare trop longue refusée', '120 caractères');

$loss = TransitWeighingService::gap(150, 146);
check($loss === ['kg'=>4.0,'pct'=>2.667], 'Perte : 4 kg, soit 2,667 % du poids envoyé');
check(!TransitWeighingService::exceeds($loss, 3.0) && TransitWeighingService::exceeds($loss, 2.5), 'Comparaison à la tolérance');
check(TransitWeighingService::exceeds(['kg'=>3.0,'pct'=>3.0], 3.0) === false, 'Écart égal à la tolérance accepté');
check(TransitWeighingService::exceeds(['kg'=>0.0,'pct'=>0.0], null) === false && TransitWeighingService::exceeds(['kg'=>0.001,'pct'=>0.001], null), 'Sans tolérance : seul un écart nul passe');
$excess = TransitWeighingService::gap(100, 106);
check($excess['kg'] === -6.0 && TransitWeighingService::exceeds($excess, 5.0), 'Excédent au-delà de la tolérance également signalé');
check(TransitWeighingService::describe($loss) === 'Perte de 4,000 kg (2,67 %)' && TransitWeighingService::describe($excess) === 'Excédent de 6,000 kg (6,00 %)' && TransitWeighingService::describe(TransitWeighingService::gap(10, 10)) === 'Aucun écart', 'Libellés lisibles des écarts');
echo "$checks vérifications réussies.\n";
