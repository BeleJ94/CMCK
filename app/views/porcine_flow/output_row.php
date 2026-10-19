<tr data-flow-output data-output-index="<?=e($outputIndex)?>">
<td class="transform-output-product"><label><span class="transform-output-label">Produit obtenu *</span><select name="outputs[<?=e($outputIndex)?>][item_id]" data-flow-product-select required><option value="">Choisir le produit</option><?php foreach($items as$item):?><option value="<?=e($item['id'])?>"><?=e($item['name'])?></option><?php endforeach;?></select></label></td>
<td><label><span class="transform-output-label">Quantité (kg) *</span><input type="number" name="outputs[<?=e($outputIndex)?>][quantity_kg]" min="0.001" step="0.001" required data-output-quantity></label></td>
<td><label><span class="transform-output-label">DLC du produit *</span><input type="date" name="outputs[<?=e($outputIndex)?>][expiry_date]" min="<?=date('Y-m-d')?>" required></label></td>
<td class="transform-output-remove"><button type="button" class="btn-secondary" data-flow-remove-output aria-label="Retirer ce produit" title="Retirer ce produit"><i class="bi bi-trash" aria-hidden="true"></i></button>
<label data-output-allocation="<?=e($outputIndex)?>"><span data-allocation-label>Quantité au comptoir (kg)</span><input type="number" name="outputs[<?=e($outputIndex)?>][counter_kg]" value="0" min="0" step="0.001" data-output-counter><small data-allocation-help>Le reste sera conservé au froid.</small></label>
</td></tr>
