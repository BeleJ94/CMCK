<?php
// Delivery note (BL) rendered by dompdf: the same PDF is previewed, downloaded and printed. Tables only, no flex or grid.
$d=$delivery;$int=static fn($v)=>number_format((int)$v,0,',',' ');$kg=static fn($v)=>number_format((float)$v,0,',',' ').' kg';$money=static fn($v,$digits=2)=>number_format((float)$v,$digits,',',' ');
$amount=array_sum(array_map(static fn($l)=>(int)$l['bags']*(float)$l['unit_price_usd'],$d['lines']));
$cdf=$d['currency']==='CDF'&&$d['exchange_rate']?$amount*(float)$d['exchange_rate']:null;
$modes=['pickup'=>'Enlèvement par le client','company_truck'=>'Camion de l’entreprise'];$stages=['office'=>'Bureau principal','source'=>'Site de production'];
$cancelled=$d['status']==='cancelled';
?>
<style>
    .bl { color: #162033; font-size: 10px; }
    .bl table.bl-layout { width: 100%; margin: 0; border-collapse: collapse; }
    .bl table.bl-layout td { padding: 0; border: 0; background: transparent; vertical-align: top; font-size: 10px; }
    .bl .bl-label { display: block; margin-bottom: 2px; color: #667085; font-size: 8px; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; }
    .bl .bl-number { display: block; color: #071527; font-size: 17px; font-weight: bold; }
    .bl .bl-muted { color: #667085; }
    .bl .bl-right { text-align: right; }
    .bl .bl-state { display: inline-block; margin-top: 4px; padding: 2px 8px; border-radius: 8px; color: #15803d; background: #e8f5ec; font-size: 8.5px; font-weight: bold; }
    .bl .bl-state-cancelled { color: #b42318; background: #fdecea; }
    .bl .bl-cancelled { margin: 10px 0 0; padding: 8px 10px; border: 1.5px solid #b42318; color: #b42318; font-weight: bold; text-align: center; }
    .bl table.bl-parties { margin-top: 14px; }
    .bl table.bl-parties td.bl-box { width: 49%; padding: 10px 12px; border: 1px solid #d9e1ea; background: #f9fbfd; }
    .bl table.bl-parties td.bl-gap { width: 2%; }
    .bl .bl-box strong { display: block; margin-bottom: 3px; color: #071527; font-size: 11.5px; }
    .bl .bl-box p { margin: 2px 0 0; color: #344054; font-size: 9.5px; line-height: 1.4; }
    .bl table.bl-lines { width: 100%; margin-top: 14px; border-collapse: collapse; }
    .bl table.bl-lines th { padding: 7px 8px; border: 1px solid #d9e1ea; color: #071527; background: #eef2f6; font-size: 8px; text-transform: uppercase; }
    .bl table.bl-lines td { padding: 8px; border: 1px solid #d9e1ea; background: #ffffff; font-size: 9.5px; }
    .bl table.bl-lines .num { text-align: right; }
    .bl table.bl-lines tfoot td { color: #071527; background: #eef2f6; font-weight: bold; }
    .bl .bl-cdf { display: block; color: #667085; font-size: 8.5px; font-weight: normal; }
    .bl .bl-note { margin: 10px 0 0; color: #667085; font-size: 8.5px; line-height: 1.45; }
    .bl table.bl-sign { margin-top: 34px; }
    .bl table.bl-sign td { width: 33.33%; padding: 0 8px; text-align: center; }
    .bl .bl-sign-line { height: 52px; border-bottom: 1px solid #071527; }
    .bl .bl-sign-label { display: block; margin-top: 5px; color: #071527; font-size: 9.5px; font-weight: bold; }
    .bl .bl-sign-name { display: block; color: #667085; font-size: 8.5px; }
</style>
<div class="bl">
    <table class="bl-layout"><tr>
        <td>
            <span class="bl-label">Bon de livraison client</span>
            <span class="bl-number"><?=e($d['document_number']?:$d['delivery_number'])?></span>
            <span class="bl-muted">Référence <?=e($d['delivery_number'])?> · commande <?=e($d['order_number'])?></span>
        </td>
        <td class="bl-right">
            <span class="bl-label">Remis le</span>
            <strong><?=e(date('d/m/Y à H:i',strtotime($d['delivered_at'])))?></strong><br>
            <span class="bl-state<?=$cancelled?' bl-state-cancelled':''?>"><?=$cancelled?'Annulé':'Livré'?></span>
        </td>
    </tr></table>
    <?php if($cancelled): ?><p class="bl-cancelled">LIVRAISON ANNULÉE<?=$d['cancel_reason']?' : '.e($d['cancel_reason']):''?></p><?php endif; ?>

    <table class="bl-layout bl-parties"><tr>
        <td class="bl-box">
            <span class="bl-label">Client</span>
            <strong><?=e($d['customer_name'])?></strong>
            <p>Code client : <?=e($d['customer_code'])?><?=$d['customer_phone']?' · '.e($d['customer_phone']):''?></p>
            <?php if($d['customer_address']||$d['customer_city']): ?><p><?=e(trim(($d['customer_address']?:'').' '.($d['customer_city']?:'')))?></p><?php endif; ?>
            <p>Reçu par : <b><?=e($d['receiver_name'])?></b><?=$d['receiver_phone']?' · '.e($d['receiver_phone']):''?></p>
        </td>
        <td class="bl-gap"></td>
        <td class="bl-box">
            <span class="bl-label">Départ et transport</span>
            <strong><?=e($d['site_name'])?></strong>
            <p><?=e($stages[$d['source_stage']]??'')?> · <?=e($d['site_code'])?></p>
            <p><?=e($modes[$d['delivery_mode']]??$d['delivery_mode'])?></p>
            <p>Véhicule : <?=e($d['vehicle_reference']?:'—')?><?=$d['driver_name']?' · chauffeur : '.e($d['driver_name']):''?></p>
        </td>
    </tr></table>

    <table class="bl-lines">
        <thead><tr><th>Produit</th><th>Format</th><th class="num">Sacs</th><th class="num">Poids</th><th class="num">Prix du sac</th><th class="num">Montant</th></tr></thead>
        <tbody><?php foreach($d['lines'] as $l): ?><tr><td><?=e($l['product_name'])?></td><td><?=e($l['format_name'])?></td><td class="num"><?=$int($l['bags'])?></td><td class="num"><?=e($kg($l['kg']))?></td><td class="num"><?=$money($l['unit_price_usd'])?> USD</td><td class="num"><?=$money((int)$l['bags']*(float)$l['unit_price_usd'])?> USD</td></tr><?php endforeach; ?></tbody>
        <tfoot><tr><td colspan="2">Total</td><td class="num"><?=$int(array_sum(array_column($d['lines'],'bags')))?></td><td class="num"><?=e($kg(array_sum(array_column($d['lines'],'kg'))))?></td><td></td><td class="num"><?=$money($amount)?> USD<?php if($cdf!==null): ?><span class="bl-cdf"><?=$money($cdf,0)?> CDF</span><?php endif; ?></td></tr></tfoot>
    </table>
    <?php if($d['notes']): ?><p class="bl-note"><b>Observations :</b> <?=e($d['notes'])?></p><?php endif; ?>
    <p class="bl-note">Prix figés à la commande<?=$cdf!==null?' ; montant en CDF au taux de la commande (1 USD = '.$money($d['exchange_rate']).' CDF)':''?>. Bon établi par <?=e($d['author'])?>.</p>

    <table class="bl-layout bl-sign"><tr>
        <td><div class="bl-sign-line"></div><span class="bl-sign-label">Magasin</span><span class="bl-sign-name">Nom et signature</span></td>
        <td><div class="bl-sign-line"></div><span class="bl-sign-label">Transporteur</span><span class="bl-sign-name"><?=e($d['driver_name']?:'Nom et signature')?></span></td>
        <td><div class="bl-sign-line"></div><span class="bl-sign-label">Client</span><span class="bl-sign-name"><?=e($d['receiver_name'])?></span></td>
    </tr></table>
</div>
