<form data-butchery-form data-production-plan data-confirm="Planifier cette fabrication ?" method="post" action="<?=e(base_url('butchery/orders'))?>">
    <?=csrf_field()?><input type="hidden" name="_section" value="<?=e($section)?>">
    <div class="feed-form-body">
        <p data-feed-error class="app-alert app-alert-error" role="alert" hidden></p>
        <?php if(!$recipes):?><div class="livestock-guide">Aucune recette active n’est disponible. <?=Auth::can('butchery','administer')?'Créez une recette pour commencer.':'Demandez à un responsable habilité de créer une recette dans Boucherie → Recettes.'?></div><?php endif;?>
        <div class="form-grid">
            <label class="butchery-transfer-select"><span>Fiche de transformation *</span><select name="recipe_version_id" required><option value="">Choisir une recette…</option><?php foreach($recipes as $recipe):?><option value="<?=e($recipe['id'])?>" data-raw-items="<?=e($recipe['raw_item_ids'] ?? '')?>" data-recipe-name="<?=e($recipe['name'])?>" data-recipe-version="<?=e($recipe['version_number'])?>" data-recipe-code="<?=e($recipe['code'])?>"><?=e($recipe['name'].' — '.$recipe['code'].' · v'.$recipe['version_number'])?></option><?php endforeach;?></select></label>
            <?php if(Auth::can('butchery','administer')&&!$siteRequired):?><button type="button" class="btn-secondary butchery-create-recipe" data-workspace-modal-open="butchery-recipe-editor">Créer une recette</button><?php endif;?>
            <p class="butchery-plan-recipe" data-plan-recipe aria-live="polite" hidden></p>
            <label class="butchery-transfer-select"><span>Lot de matière première *</span><select name="raw_lot_id" required><option value="">Choisir un lot disponible…</option><?php foreach($raw as $lot):
                $available=max(0,(float)$lot['quantity_kg']-(float)$lot['reserved_kg']);
                if($lot['status']!=='available'||$available<=0||$lot['expiry_date']<date('Y-m-d'))continue;
                $lotDetails=['itemId'=>(int)$lot['item_id'],'reference'=>$lot['lot_number'],'item'=>$lot['item_name'],'stock'=>number_format((float)$lot['quantity_kg'],3,',',' ').' kg','reserved'=>number_format((float)$lot['reserved_kg'],3,',',' ').' kg','available'=>$available,'expiry'=>date('d/m/Y',strtotime($lot['expiry_date']))];
            ?><option value="<?=e($lot['id'])?>" data-plan-lot="<?=e(json_encode($lotDetails,JSON_UNESCAPED_UNICODE))?>"><?=e($lot['lot_number'].' — '.$lot['item_name'].' — '.number_format($available,3,',',' ').' kg')?></option><?php endforeach;?></select></label>
            <section class="butchery-transfer-summary butchery-transfer-preview" data-plan-summary hidden aria-label="Lot sélectionné" aria-live="polite">
                <header><div><small>Lot sélectionné</small><strong data-plan-field="reference"></strong></div></header>
                <dl><div><dt>Matière</dt><dd data-plan-field="item"></dd></div><div><dt>DLC</dt><dd data-plan-field="expiry"></dd></div><div><dt>Stock physique</dt><dd data-plan-field="stock"></dd></div><div><dt>Réservé</dt><dd data-plan-field="reserved"></dd></div><div class="butchery-transfer-quantity"><dt>Disponible</dt><dd data-plan-available></dd></div></dl>
            </section>
            <label><span>Quantité planifiée (kg) *</span><input name="planned_input_kg" type="number" step="0.001" min="0.001" required placeholder="Ex. 100" inputmode="decimal"></label>
            <label><span>Date de production *</span><input name="production_date" type="date" required value="<?=e(date('Y-m-d'))?>"></label>
        </div>
        <p class="butchery-plan-recipe" data-plan-balance aria-live="polite" hidden></p>
        <p class="butchery-validation-note">Cette étape crée l’ordre de fabrication sans réserver ni déduire le stock. Les quantités réellement consommées seront saisies avec les résultats.</p>
    </div>
    <footer><button type="button" class="btn-secondary" data-workspace-modal-close>Annuler</button><button type="submit" class="btn-primary">Planifier la fabrication</button></footer>
</form>
