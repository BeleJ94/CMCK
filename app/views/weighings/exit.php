<div class="weighing-exit-workspace">
<header class="weighing-entry-heading"><div><p class="page-kicker">PONT-BASCULE · SORTIE</p><h2>Valider la livraison</h2><p>Après déchargement, pesez le camion vide et contrôlez la marchandise.</p></div><a href="<?=e(base_url('weighings/exit'))?>" class="btn-secondary">Camions en attente · <?=e(count($pending))?></a></header>
<?php if(!empty($success)):?><div class="app-alert app-alert-success" role="status"><?=e($success)?></div><?php endif;?>
<?php if(!empty($error)):?><div class="app-alert app-alert-error" role="alert"><?=e($error)?></div><?php endif;?>
<?php if(!empty($errors)):?><div class="app-alert app-alert-error" role="alert"><strong>Vérifiez les informations suivantes :</strong><ul><?php foreach($errors as$message):?><li><?=e($message)?></li><?php endforeach;?></ul></div><?php endif;?>
<?php if(empty($weighing)):?>
<section class="table-panel">
    <div class="panel-heading">
        <span class="panel-icon"><i class="bi bi-hourglass-split"></i></span>
        <div>
            <h3>Camions en attente</h3>
            <p>Choisissez le camion à peser après déchargement.</p>
        </div>
    </div>
    <div class="table-responsive">
        <table id="pendingWeighingsTable" class="enterprise-table">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Camion</th>
                    <th>Origine</th>
                    <th>Produit</th>
                    <th>Poids brut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $item): ?>
                    <tr>
                        <td><strong><?= e($item['reference']) ?></strong><?php if(!empty($item['exit_prepared_at'])):?><small class="weighing-cell-note">Sortie à valider</small><?php endif;?></td>
                        <td><?= e($item['plate_number']) ?></td>
                        <td><?= e($item['supplier_name']) ?></td>
                        <td><?= e($item['product_name']) ?></td>
                        <td><?= e(number_format((float) $item['poids_brut'], 0, ',', ' ')) ?> kg</td>
                        <td><a class="btn-table-action" href="<?= e(base_url('weighings/' . $item['id'] . '/exit')) ?>">Pesée de sortie</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if(!$pending):?><tr><td colspan="6">Aucun camion en attente de pesée de sortie.</td></tr><?php endif;?>
            </tbody>
        </table>
    </div>
</section>

<?php else:?>
<?php $exitSaved=!empty($weighing['exit_prepared_at']); $exitPending=$weighing['status']==='pending'; ?>
<?php if($exitPending):?>
<section class="exit-next-action" aria-labelledby="exitNextTitle">
<div><span class="exit-progress-label"><?= $exitSaved?'ÉTAPE 2 SUR 2 · VALIDATION':'ÉTAPE 1 SUR 2 · SAISIE' ?></span>
<h3 id="exitNextTitle"><?= $exitSaved?'Votre saisie est enregistrée. Il reste à valider.':'Renseignez la sortie, puis validez la livraison.' ?></h3>
<p><?php if(!empty($canValidateExit)):?><?= $exitSaved?'Vérifiez les données ci-dessous, puis cliquez sur le bouton de validation.':'Vous pouvez valider directement après la saisie, ou enregistrer pour reprendre plus tard.' ?> <span data-exit-effect><?= $exit['decision']==='reject'?'Le refus ne crédite pas le silo.':'La validation d’une livraison acceptée ajoute le poids net au silo choisi.' ?></span><?php else:?><?= $exitSaved?'Votre travail est sauvegardé. Un utilisateur habilité doit ouvrir cette pesée et valider la livraison. Vous pouvez quitter cette page.':'Enregistrez les données pour qu’un utilisateur habilité puisse ensuite valider cette pesée.' ?><?php endif;?></p>
<?php if($exitSaved):?><small>Dernier enregistrement : <?=e(date('d/m/Y à H:i',strtotime($weighing['exit_prepared_at'])))?></small><?php endif;?></div>
<?php if(!empty($canValidateExit)):?><button type="submit" form="weighingExitForm" name="exit_action" value="validate" class="btn-primary" data-exit-validate-label data-confirm="Vérifiez le poids net, le silo et la décision. Confirmer applique la décision et, si la livraison est acceptée, crédite le silo."><?= $exit['decision']==='reject'?'Confirmer le refus':'Valider et créditer le silo' ?></button><?php elseif($exitSaved):?><a href="<?=e(base_url('weighings'))?>" class="btn-secondary">Revenir aux pesées</a><?php endif;?>
</section>
<?php else:?><div class="app-alert" role="status">Cette pesée a déjà été traitée. <a href="<?=e(base_url('weighings/'.$weighing['id'].'/ticket'))?>">Consulter le ticket</a></div><?php endif;?>
<dl class="weighing-exit-context">
<?php foreach(['reference'=>'Pesée','bt_number'=>'Bon de transport','plate_number'=>'Camion','supplier_name'=>'Origine','product_name'=>'Produit'] as$key=>$label):?><div><dt><?=e($label)?></dt><dd><?=e($weighing[$key]??'—')?></dd></div><?php endforeach;?>
</dl>
<?php if($exitPending):?>
<section class="form-panel weighing-exit-panel">
<form id="weighingExitForm" method="post" action="<?=e(base_url('weighings/'.$weighing['id'].'/exit'))?>" class="enterprise-form" data-validate data-weighing-exit>
<?=csrf_field()?>
<section class="weighing-exit-section"><h3><b>1</b> Pesée après déchargement</h3><div class="weighing-exit-weights">
<label><span>Poids brut à l’entrée (kg)</span><input type="number" value="<?=e($weighing['poids_brut'])?>" data-poids-brut disabled></label>
<label><span>Tare du camion vide (kg) *</span><input type="number" inputmode="decimal" step="0.001" min="0" max="<?=e($weighing['poids_brut'])?>" name="poids_tare" value="<?=e($exit['poids_tare'])?>" data-poids-tare required placeholder="Poids mesuré à vide"></label>
<label class="weighing-exit-net"><span>Poids net calculé (kg)</span><input type="text" value="<?=e($exit['poids_tare']===''?'À calculer':number_format(max(0,(float)$weighing['poids_brut']-(float)$exit['poids_tare']),3,',',' '))?>" data-poids-net disabled><small>Poids brut − tare</small></label>
</div></section>
<section class="weighing-exit-section"><h3><b>2</b> Contrôle qualité</h3><div class="weighing-exit-quality">
<?php foreach(['humidity_percent'=>'Humidité mesurée (%)','impurities_percent'=>'Impuretés mesurées (%)'] as$name=>$label):?><label><span><?=e($label)?> *</span><input type="number" inputmode="decimal" step="0.001" min="0" name="<?=e($name)?>" value="<?=e($exit[$name])?>" required></label><?php endforeach;?>
</div><div class="weighing-exit-limits"><p>Seuils de contrôle</p><div class="weighing-exit-thresholds">
<?php foreach(['weight_tolerance_percent'=>'Tolérance poids (%)','max_humidity_percent'=>'Humidité maximale (%)','max_impurities_percent'=>'Impuretés maximales (%)'] as$name=>$label):?><label><span><?=e($label)?> *</span><input type="number" step="0.001" min="0" name="<?=e($name)?>" value="<?=e($exit[$name])?>" required></label><?php endforeach;?>
</div></div></section>
<section class="weighing-exit-section"><h3><b>3</b> Décision et destination</h3><div class="weighing-exit-decision">
<label><span>Décision *</span><select name="decision" required><option value="accept" <?=$exit['decision']==='accept'?'selected':''?>>Accepter la livraison</option><option value="reject" <?=$exit['decision']==='reject'?'selected':''?>>Refuser la livraison</option></select></label>
<label><span>Silo de destination *</span><select name="silo_id" required><option value="">Sélectionner un silo</option><?php foreach($silos as$silo):?><option value="<?=e($silo['id'])?>" <?=(string)$exit['silo_id']===(string)$silo['id']?'selected':''?>><?=e($silo['name'].' ('.$silo['code'].') — stock '.number_format((float)$silo['current_stock_kg'],3,',',' ').' kg')?></option><?php endforeach;?></select><?php if(!$silos):?><small>Aucun silo disponible sur ce site.</small><?php endif;?></label>
<label class="weighing-exit-notes"><span>Observations / motif</span><textarea name="quality_notes" rows="2" placeholder="Précisez toute anomalie ou le motif du refus."><?=e($exit['quality_notes'])?></textarea><small>Un motif est obligatoire en cas de refus. Précisez les écarts constatés.</small></label>
</div></section>
<footer class="weighing-exit-footer"><p><strong>Enregistrer ≠ valider.</strong><br>Enregistrer conserve votre saisie. Seule la validation d’une livraison acceptée crédite le silo.</p><div>
<?php if(!empty($canValidateExit)):?><button type="submit" name="exit_action" value="validate" class="btn-primary" data-exit-validate-label data-confirm="Vérifiez le poids net, le silo et la décision. Confirmer applique la décision et, si la livraison est acceptée, crédite le silo."><?= $exit['decision']==='reject'?'Confirmer le refus':'Valider et créditer le silo' ?></button><?php endif;?>
<?php if(!empty($canPrepareExit)):?><button type="submit" name="exit_action" value="prepare" formaction="<?=e(base_url('weighings/'.$weighing['id'].'/exit/prepare'))?>" class="<?=!empty($canValidateExit)?'btn-secondary':'btn-primary'?>" data-confirm="Cette action enregistre les données pour une validation ultérieure. Elle ne valide pas la livraison et ne modifie aucun stock silo."><?=!empty($canValidateExit)?'Enregistrer sans valider':($exitSaved?'Enregistrer les modifications':'Enregistrer pour validation')?></button><?php endif;?>
</div></footer>
</form></section>
<?php endif;?>
<?php endif;?>
</div>
