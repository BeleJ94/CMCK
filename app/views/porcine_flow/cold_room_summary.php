<?php
// Presentation only: quantities and eligibility come from the existing directory.
$coldRows=[];
$coldStatuses=['available'=>'Disponible','reserved'=>'Avec réservation','quarantine'=>'En quarantaine','rejected'=>'Refusé','cancelled'=>'Annulé','in_transit'=>'En transit','expired'=>'DLC dépassée','depleted'=>'Épuisé'];
foreach(['raw'=>$raw,'finished'=>$finished] as$kind=>$lots){
    foreach($lots as$lot){
        // Live poultry is not kept in the cold room: it is shown on the transformation page.
        if(!empty($lot['is_live']))continue;
        $physical=(float)($kind==='raw'?$lot['quantity_kg']:$lot['cold_kg']);
        if($physical<=0)continue;
        // Stocks counted in pieces (poultry) also show their pieces; NULL otherwise.
        $pieces=isset($lot['quantity_pieces'])?(int)($kind==='raw'?$lot['quantity_pieces']:$lot['cold_pieces']):null;
        $reserved=(float)$lot['reserved_kg'];
        $usable=in_array($lot['status'],['available','reserved'],true)&&$lot['expiry_date']>=date('Y-m-d');
        $available=$usable?max(0,round($kind==='raw'?$physical-$reserved:(float)$lot['cold_available_kg'],3)):0;
        $state=$stockState($lot);
        $coldRows[]=array_merge($lot,[
            'key'=>$kind.'-'.$lot['id'],'kind'=>$kind,'article'=>$kind.'-'.$lot['item_id'],
            'kind_label'=>$kind==='raw'?'Carcasses et matières':'Produit transformé',
            'physical'=>$physical,'reserved'=>$reserved,'available'=>$available,
            'pieces'=>$pieces,'available_pieces'=>$pieces===null?null:($usable?max(0,$kind==='raw'?$pieces-(int)$lot['reserved_pieces']:(int)$lot['cold_available_pieces']):0),
            'state'=>$state,'state_label'=>$coldStatuses[$state]??'Indisponible',
            'expired'=>$lot['expiry_date']<date('Y-m-d'),
        ]);
    }
}
$coldRawRows=array_filter($coldRows,static fn($row)=>$row['kind']==='raw');
$coldFinishedRows=array_filter($coldRows,static fn($row)=>$row['kind']==='finished');
$coldUsableRows=array_filter($coldRows,static fn($row)=>$row['available']>0);
$coldExpiredRows=array_filter($coldRows,static fn($row)=>$row['expired']);
$coldKpis=[
    'raw'=>['title'=>'Carcasses et matières au froid','value'=>$kg(array_sum(array_column($coldRawRows,'physical'))).' kg','icon'=>'bi-box-seam','tone'=>'','meta'=>'Poids physique · réservations incluses','rows'=>$coldRawRows,'note'=>'Poids des carcasses et matières présents au froid, y compris les quantités réservées et les lots bloqués. Les réservations font partie de ce poids.'],
    'finished'=>['title'=>'Produits transformés au froid','value'=>$kg(array_sum(array_column($coldFinishedRows,'physical'))).' kg','icon'=>'bi-snow','tone'=>'','meta'=>'Poids physique · hors comptoirs','rows'=>$coldFinishedRows,'note'=>'Poids des produits transformés encore en chambre froide. Les quantités déjà placées aux comptoirs sont exclues. Les réservations et les lots bloqués restent inclus.'],
    'available'=>['title'=>'Poids disponible','value'=>$kg(array_sum(array_column($coldUsableRows,'available'))).' kg','icon'=>'bi-check2-circle','tone'=>'success','meta'=>'Hors réservations et lots bloqués','rows'=>$coldUsableRows,'note'=>'Poids non réservé des lots disponibles au froid dont la date limite de consommation n’est pas dépassée. Les lots en quarantaine, refusés, annulés ou en transit sont exclus.'],
    'expired'=>['title'=>'Lots à DLC dépassée','value'=>count($coldExpiredRows),'icon'=>'bi-exclamation-triangle','tone'=>count($coldExpiredRows)?'warning':'','meta'=>'Lots encore présents au froid','rows'=>$coldExpiredRows,'note'=>'Lots dont la date limite de consommation est antérieure à aujourd’hui et qui ont encore du poids au froid. Leur quantité disponible est égale à zéro.'],
];
$coldCanSupply=$canOperate&&Auth::can('butchery','update',$site);
$coldCanTransform=$canOperate&&Auth::can('butchery','create',$site);
$coldArticles=[];
foreach($coldRows as$row){
    $key=$row['article'];
    if(!isset($coldArticles[$key]))$coldArticles[$key]=['key'=>$key,'kind'=>$row['kind'],'name'=>$row['item_name'],'physical'=>0,'reserved'=>0,'available'=>0,'pieces'=>null,'available_pieces'=>null,'lots'=>0,'expired'=>0,'search'=>[]];
    foreach(['physical','reserved','available'] as$weight)$coldArticles[$key][$weight]=round($coldArticles[$key][$weight]+$row[$weight],3);
    if($row['pieces']!==null)foreach(['pieces','available_pieces'] as$count)$coldArticles[$key][$count]=(int)$coldArticles[$key][$count]+(int)$row[$count];
    $coldArticles[$key]['lots']++;
    $coldArticles[$key]['expired']+=(int)$row['expired'];
    $coldArticles[$key]['search'][]=$row['lot_number'].' '.($row['batch_number']??'');
}
uasort($coldArticles,static fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
?>
<div class="flow-summary ui-kpi-grid" aria-label="Suivi de la chambre froide">
<?php foreach($coldKpis as$key=>$kpi):?>
<button type="button" class="ui-kpi<?=$kpi['tone']?' ui-kpi--'.e($kpi['tone']):''?>" data-workspace-modal-open="cold-room-kpi-<?=e($key)?>" aria-haspopup="dialog" aria-controls="cold-room-kpi-<?=e($key)?>"><span class="ui-kpi__icon"><i class="bi <?=e($kpi['icon'])?>" aria-hidden="true"></i></span><span class="ui-kpi__label"><?=e($kpi['title'])?></span><strong class="ui-kpi__value"><?=e($kpi['value'])?></strong><span class="ui-kpi__meta"><?=e($kpi['meta'])?> <i class="bi bi-arrow-right" aria-hidden="true"></i></span></button>
<?php endforeach;?>
</div>
