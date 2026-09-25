<?php
$section = $section ?? 'receipts';
$butcherySections = [
    'receipts' => ['box-arrow-in-down', 'Réceptions'],
    'slaughters' => ['clipboard-check', 'Abattage'],
    'production' => ['gear', 'Fabrication'],
    'stocks' => ['boxes', 'Stocks et DLC'],
    'outgoing' => ['truck', 'Ventes et transferts'],
    'recipes' => ['journal-text', 'Fiches de transformation'],
];
$butcheryGuides = [
    'receipts' => 'Recevez une livraison, puis contrôlez-la pour rendre le lot disponible.',
    'slaughters' => 'Enregistrez l’abattage, puis validez le poids et la DLC pour créer le stock de viande.',
    'production' => 'Planifiez une fabrication, saisissez les résultats, puis validez les produits obtenus.',
    'stocks' => 'Consultez les quantités et les dates limites de consommation. Les lots sont classés par DLC.',
    'outgoing' => 'Enregistrez une vente ou préparez, approuvez et expédiez un transfert vers DECO.',
    'recipes' => 'Préparez les recettes utilisées en fabrication. Une nouvelle version conserve les anciennes productions.',
];
$countStatus = static function ($rows, $statuses) {
    return count(array_filter($rows, static function ($row) use ($statuses) { return in_array($row['status'], $statuses, true); }));
};
$availableWeight = static function ($rows) {
    $total = 0;
    foreach ($rows as $row) {
        if ($row['status'] === 'available' && $row['expiry_date'] >= date('Y-m-d')) {
            $total += max(0, (float)$row['quantity_kg'] - (float)$row['reserved_kg']);
        }
    }
    return number_format($total, 3, ',', ' ') . ' kg';
};
$expiring = 0;
foreach (array_merge($raw, $finished) as $lot) {
    if ((float)$lot['quantity_kg'] > 0 && in_array($lot['status'], ['available', 'reserved'], true) && $lot['expiry_date'] >= date('Y-m-d') && $lot['expiry_date'] <= date('Y-m-d', strtotime('+3 days'))) $expiring++;
}
$availableLive = array_values(array_filter($live, static function ($lot) { return (int)$lot['available_heads'] > 0 && in_array($lot['status'], ['available','partially_slaughtered'], true); }));
$heads = array_sum(array_column($availableLive, 'available_heads'));
$butcheryMetrics = [
    'receipts' => [[count($incoming), 'Transferts à recevoir'], [count($receipts), 'Contrôles à effectuer']],
    'slaughters' => [[$heads, 'Têtes disponibles'], [count($slaughters), 'Abattages à valider']],
    'production' => [[$countStatus($orders, ['in_progress']), 'Résultats à saisir'], [$countStatus($orders, ['results_submitted']), 'Fabrications à valider']],
    'stocks' => [[$availableWeight($raw), 'Matières disponibles'], [$availableWeight($finished), 'Produits finis disponibles'], [$expiring, 'Lots : DLC sous 3 jours']],
    'outgoing' => [[$countStatus($transfers, ['draft']), 'Transferts à approuver'], [$countStatus($transfers, ['approved']), 'Transferts à expédier']],
    'recipes' => [[count($recipes), 'Fiches actives']],
];
$butcheryStatuses=['available'=>'Disponible','reserved'=>'Réservé','expired'=>'DLC dépassée','depleted'=>'Épuisé','in_progress'=>'En cours','results_submitted'=>'À valider','validated'=>'Validé','draft'=>'Brouillon','approved'=>'Approuvé','in_transit'=>'En transit','cancelled'=>'Annulé','accepted'=>'Accepté','rejected'=>'Refusé','sanitary_pending'=>'À contrôler'];
$butcheryModals=[];
$butcheryDrawer=static function($title,$form,$permission,$buttonLabel=null,$modalId=null) use (&$butcheryModals,$siteRequired){
 if(!Auth::can('butchery',$permission))return;
 $buttonLabel=$buttonLabel??$title;
 $id=$modalId?:'butchery-editor-'.count($butcheryModals);
 if($siteRequired){echo '<button class="btn-secondary" type="button" disabled title="Sélectionnez le site BOUCH">'.e($buttonLabel).'</button>';return;}
 echo '<button class="'.($permission==='create'?'btn-primary':'btn-secondary').'" type="button" data-workspace-modal-open="'.e($id).'" aria-label="'.e($title).'"><i class="bi bi-pencil-square" aria-hidden="true"></i> '.e($buttonLabel).'</button>';
 $butcheryModals[]='<section id="'.e($id).'" class="entity-modal workspace-entity-modal feed-editor livestock-editor conversion-editor butchery-editor" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="'.e($id).'-title"><header><h2 id="'.e($id).'-title">'.e($title).'</h2><button class="modal-close" type="button" data-workspace-modal-close aria-label="Fermer">×</button></header>'.$form.'</section>';
};
