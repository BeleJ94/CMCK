<form data-butchery-form data-slaughter-validation data-net-weight="<?=e($s['net_weight_kg'])?>" data-confirm="Valider cet abattage et créer le lot de viande disponible en stock ?" method="post" action="<?=e(base_url('butchery/slaughters/'.$s['id'].'/validate'))?>">
    <?=csrf_field()?>
    <input type="hidden" name="_section" value="<?=e($section)?>">
    <div class="feed-form-body">
        <p data-feed-error class="app-alert app-alert-error" role="alert" hidden></p>
        <section class="butchery-validation-summary" aria-label="Abattage à valider">
            <div class="butchery-validation-reference"><strong><?=e($s['slaughter_number'])?></strong><span class="butchery-status butchery-status-pending">À valider</span></div>
            <p><?=e($s['item_name'] ?: 'Matière première')?> · <?=e(date('d/m/Y à H:i',strtotime($s['slaughtered_at'])))?></p>
            <dl><div><dt>Lot vivant</dt><dd><?=e($s['lot_number'])?></dd></div><div><dt>Animaux abattus</dt><dd><?=e($s['input_heads'])?> têtes</dd></div></dl>
            <div class="butchery-validation-weights">
                <div><span>Poids brut</span><strong><?=e(number_format((float)$s['gross_weight_kg'],3,',',' '))?> kg</strong></div>
                <div><span>Tare</span><strong><?=e(number_format((float)$s['tare_weight_kg'],3,',',' '))?> kg</strong></div>
                <div><span>Poids net à stocker</span><strong><?=e(number_format((float)$s['net_weight_kg'],3,',',' '))?> kg</strong></div>
            </div>
        </section>
        <div class="form-grid butchery-validation-fields">
            <label><span>Date limite de consommation *</span><input type="date" name="expiry_date" required min="<?=e(date('Y-m-d'))?>" aria-describedby="slaughter-expiry-<?=e($s['id'])?>"><small id="slaughter-expiry-<?=e($s['id'])?>">Aujourd’hui ou une date ultérieure.</small></label>
            <label><span>Coût unitaire / kg</span><input type="number" step="0.0001" name="unit_cost" min="0" placeholder="Ex. 5,25" inputmode="decimal" aria-describedby="slaughter-cost-<?=e($s['id'])?>"><small id="slaughter-cost-<?=e($s['id'])?>">Facultatif. Sans saisie, le coût est enregistré à 0.</small></label>
        </div>
        <div class="butchery-validation-total" aria-live="polite"><span>Coût total estimé <small>Poids net × coût par kg</small></span><strong data-slaughter-total>—</strong></div>
        <p class="butchery-validation-note"><i class="bi bi-info-circle" aria-hidden="true"></i> La validation déduit les têtes du lot vivant et crée le stock de viande avec la DLC renseignée.</p>
    </div>
    <footer><button type="button" class="btn-secondary" data-workspace-modal-close>Annuler</button><button type="submit" class="btn-primary">Valider et créer le stock</button></footer>
</form>
