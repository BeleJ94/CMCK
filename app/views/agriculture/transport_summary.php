<?php
$transportCounts=['draft'=>0,'approved'=>0,'in_transit'=>0,'received'=>0];
$transitKg=0;
foreach($transports as$transport){
    if(isset($transportCounts[$transport['status']]))$transportCounts[$transport['status']]++;
    if($transport['status']==='in_transit')$transitKg+=(float)$transport['quantity_kg'];
}
$transportMetrics=[
 ['draft','bi-file-earmark-text','À valider','Bon(s) en brouillon'],
 ['approved','bi-check2-circle','Prêts à expédier','Stock réservé'],
 ['in_transit','bi-truck','En transit',number_format($transitKg/1000,2,',',' ').' t en acheminement'],
 ['received','bi-box-seam','Réceptionnés','Acheminement terminé'],
];
?>
<section class="transport-summary" aria-label="Synthèse des transports">
<?php foreach($transportMetrics as$metric):?>
<article class="transport-metric transport-metric-<?=e($metric[0])?>"><i class="bi <?=e($metric[1])?>" aria-hidden="true"></i><div><span><?=e($metric[2])?></span><strong><?=e($transportCounts[$metric[0]])?></strong><small><?=e($metric[3])?></small></div></article>
<?php endforeach;?>
</section>
<div class="transport-process" aria-label="Étapes du transport"><span><b>1</b> Créer le BT</span><i class="bi bi-chevron-right" aria-hidden="true"></i><span><b>2</b> Valider et réserver</span><i class="bi bi-chevron-right" aria-hidden="true"></i><span><b>3</b> Expédier</span><i class="bi bi-chevron-right" aria-hidden="true"></i><span><b>4</b> Réceptionner au pont-bascule</span></div>
