<?php
// The coloured polygon is an area proportion, not a geographical boundary.
$plotVisualSequence=($plotVisualSequence??0)+1;$visualId='plot-use-'.$plotVisualSequence;
$usedArea=$mainPlan?(float)$mainPlan['actual_area_ha']:0;$physicalArea=(float)$plot['total_area_ha'];
$usePercent=$physicalArea>0?100*$usedArea/$physicalArea:0;$fraction=max(0,min(1,$usePercent/100));
$harvestTarget=$mainPlan?$usedArea*(float)$mainPlan['target_tons_per_ha']:0;
$harvestActual=$mainPlan?($plotValidated[$mainPlan['id']]??0):0;
$harvestPercent=$harvestTarget>0?100*$harvestActual/$harvestTarget:null;
$plotMaize=stripos(iconv('UTF-8','ASCII//TRANSLIT',$mainPlan['variety_name']??''),'mais')!==false;
$usagePolygon='43,124 '.(43+195*$fraction).','.(124-52*$fraction).' '.(190+195*$fraction).','.(179-52*$fraction).' 190,179';
$visualNumber=fn($n)=>number_format((float)$n,2,',',' ');
?>
<svg class="plot-illustration" viewBox="0 0 420 190" aria-hidden="true" focusable="false">
    <defs><clipPath id="<?=e($visualId)?>"><polygon points="<?=e($usagePolygon)?>"/></clipPath></defs>
    <rect width="420" height="190" fill="#eaf3f5"/><circle cx="347" cy="34" r="19" fill="#f4d891"/>
    <path d="M0 88Q75 36 162 85T420 60V190H0Z" fill="#d4e5db"/>
    <path d="M0 108Q160 66 420 109V190H0Z" fill="#b2cdb9"/>
    <path d="M43 124L238 72L385 127L190 179Z" fill="#c5ac7b" stroke="#fff" stroke-width="3"/>
    <g clip-path="url(#<?=e($visualId)?>)">
        <path d="M43 124L238 72L385 127L190 179Z" fill="#719a65"/>
        <?php for($row=0;$row<6;$row++):$x=69+$row*25;$y=125-$row*7;?>
        <path d="M<?=$x?> <?=$y?>l137 51" stroke="#426f4c" stroke-width="4"/>
        <?php for($col=0;$col<5;$col++):$cx=$x+12+$col*25;$cy=$y+4+$col*9;?>
        <path d="M<?=$cx?> <?=$cy?>v-<?=$plotMaize?16:10?>m0 5q-8-10-10-5m10 8q8-11 11-6" fill="none" stroke="#2f6846" stroke-width="2" stroke-linecap="round"/>
        <?php if($plotMaize):?><path d="M<?=$cx?> <?=$cy-18?>v5" stroke="#e8c770" stroke-width="4" stroke-linecap="round"/><?php endif;?>
        <?php endfor;endfor;?>
    </g>
    <path d="M21 153L21 130M32 158V134M15 137L39 146" stroke="#8a795f" stroke-width="3"/>
    <path d="M374 82v21" stroke="#7c8066" stroke-width="5"/><circle cx="374" cy="74" r="16" fill="#5e917b"/><circle cx="362" cy="80" r="12" fill="#5e917b"/>
    <rect x="151" y="28" width="118" height="24" rx="12" fill="#fff"/>
    <text x="210" y="44" text-anchor="middle" fill="#226342" font-size="11" font-family="sans-serif" font-weight="600"><?=$mainPlan?e($visualNumber($usePercent)).' % exploités':'À planifier'?></text>
    <g transform="translate(316 133)">
        <rect x="-6" y="-7" width="87" height="54" rx="8" fill="#fff" fill-opacity=".94"/>
        <path d="M6 5L9 0H24L27 5L24 10Q37 38 17 38Q-3 38 9 10ZM39 5L42 0H57L60 5L57 10Q70 38 50 38Q30 38 42 10Z" fill="<?=$harvestActual>0?'#e6bc5e':'#f2eee3'?>" stroke="#af8b42" stroke-width="1.5"/>
        <path d="M10 10H24M43 10H57M17 18V29M50 18V29" stroke="#af8b42" stroke-width="2"/>
    </g>
</svg>
<div class="plot-image-metrics">
    <div class="plot-image-metric" data-plot-use="<?=e($usePercent)?>"><div><span><i class="bi bi-bounding-box" aria-hidden="true"></i> Exploitation</span><strong><?=$mainPlan?e($visualNumber($usedArea)).' / '.e($visualNumber($physicalArea)).' ha':'Aucune planification'?></strong></div><progress max="100" value="<?=e(min(100,max(0,$usePercent)))?>" aria-label="Surface exploitée : <?=e($visualNumber($usePercent))?> %"></progress><?php if($usePercent>100):?><small>Surface déclarée supérieure à la superficie physique.</small><?php endif;?></div>
    <div class="plot-image-metric is-harvest" data-plot-harvest="<?=e($harvestActual)?>"><div><span><i class="bi bi-basket" aria-hidden="true"></i> Récolte validée</span><strong><?=e($visualNumber($harvestActual))?> t<?=$harvestTarget>0?' / '.e($visualNumber($harvestTarget)).' t':''?></strong></div><?php if($harvestPercent!==null):?><div class="plot-harvest-progress"><progress max="100" value="<?=e(min(100,max(0,$harvestPercent)))?>" aria-label="Récolte validée : <?=e($visualNumber($harvestPercent))?> % de l’objectif"></progress><small><?=e($visualNumber($harvestPercent))?> %</small></div><?php else:?><small><?=$mainPlan?'Objectif non défini':'Aucune campagne affichée'?></small><?php endif;?></div>
</div>
