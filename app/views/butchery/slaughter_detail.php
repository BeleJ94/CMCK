<?php
$stockLabels=['available'=>'Disponible','reserved'=>'Réservé','depleted'=>'Épuisé','expired'=>'DLC dépassée','quarantine'=>'En quarantaine','rejected'=>'Refusé','cancelled'=>'Annulé'];
$liveLabels=['available'=>'Disponible','partially_slaughtered'=>'Partiellement abattu','depleted'=>'Épuisé','quarantine'=>'En quarantaine','rejected'=>'Refusé'];
$stockExpired=$entry['expiry_date'] && $entry['expiry_date']<date('Y-m-d');
$groups=[
    'Origine et traçabilité'=>[
        'Site de traitement'=>$entry['site_name'] ?: '—', 'Provenance'=>$entry['source_site_name'] ?: '—',
        'Espèce'=>$entry['species_name'] ?: '—','Lot d’élevage'=>$entry['batch_number'] ?: '—',
        'Lot vivant'=>$entry['lot_number'],'Réception source'=>$entry['receipt_number'] ?: '—',
        'Réceptionné le'=>$historyDate($entry['received_at']),'Transfert source'=>$entry['transfer_number'] ?: '—',
        'Véhicule'=>$entry['truck_plate'] ?: '—','Chauffeur'=>$entry['driver_name'] ?: '—',
    ],
    'Suivi de l’opération'=>[
        'Abattage effectué le'=>$historyDate($entry['slaughtered_at']),'Enregistré le'=>$historyDate($entry['created_at']),
        'Enregistré par'=>$entry['creator_name'] ?: '—','Validé le'=>$historyDate($entry['validated_at']),
        'Validé par'=>$entry['validator_name'] ?: '—','Matière obtenue'=>$entry['item_name'] ?: '—',
    ],
];
if($entry['resulting_lot']) {
    $groups['Lot de viande créé']=[
        'Référence du lot'=>$entry['resulting_lot'],
        'Date de production'=>$entry['production_date'] ? date('d/m/Y',strtotime($entry['production_date'])) : '—',
        'Date limite de consommation'=>$entry['expiry_date'] ? date('d/m/Y',strtotime($entry['expiry_date'])).($stockExpired?' · Dépassée':'') : '—',
        'Statut actuel'=>$stockLabels[$entry['stock_status']] ?? ($entry['stock_status'] ?: '—'),
        'Quantité initiale issue de cet abattage'=>$historyKg($entry['net_weight_kg']),
        'Stock physique actuel'=>$historyKg($entry['stock_quantity_kg']),
        'Quantité réservée'=>$historyKg($entry['reserved_kg']),
        'Coût unitaire / kg'=>$entry['unit_cost']!==null?number_format((float)$entry['unit_cost'],4,',',' '):'—',
        'Valeur initiale calculée'=>$entry['unit_cost']!==null?number_format((float)$entry['net_weight_kg']*(float)$entry['unit_cost'],2,',',' '):'—',
    ];
}
$groups['Contrôle sanitaire à la réception']=[
    'Décision'=>$entry['sanitary_decision']===null?'Non renseignée':($entry['sanitary_decision']==='accept'?'Acceptée':'Refusée'),
    'Contrôlé le'=>$historyDate($entry['controlled_at']), 'Contrôlé par'=>$entry['inspector_name'] ?: '—',
    'Observations'=>$entry['sanitary_notes'] ?: '—',
];
?>
<div class="butchery-detail-banner"><div><small>Abattage</small><strong><?=e($reference)?></strong></div><span class="butchery-status butchery-status-<?=e($tone)?>"><?=e($label)?></span></div>
<div class="butchery-detail-balance">
    <div><span>Animaux abattus</span><strong><?=e($entry['input_heads'])?> têtes</strong></div>
    <div><span>Poids brut</span><strong><?=e($historyKg($entry['gross_weight_kg']))?></strong></div>
    <div><span>Tare</span><strong><?=e($historyKg($entry['tare_weight_kg']))?></strong></div>
    <div><span>Poids net</span><strong><?=e($historyKg($entry['net_weight_kg']))?></strong></div>
</div>
<?php if(!$entry['resulting_lot']):?><p class="butchery-detail-note"><?= $status==='submitted'?'Le stock de viande sera créé après validation.':'Aucun lot de viande lié à cet abattage.' ?></p><?php endif;?>
<?php foreach($groups as $heading=>$fields): ?>
<section class="butchery-detail-section"><h3><?=e($heading)?></h3><dl class="butchery-history-details"><?php foreach($fields as $name=>$value):?><div><dt><?=e($name)?></dt><dd><?=e($value)?></dd></div><?php endforeach;?></dl></section>
<?php endforeach;?>
<p class="butchery-detail-note">Situation actuelle du lot vivant : <?=e($entry['available_heads'])?> têtes restantes · <?=e($liveLabels[$entry['live_status']] ?? $entry['live_status'])?>. Les soldes actuels tiennent compte des opérations ultérieures.</p>
