(function(){'use strict';
window.initButchery=function(root){
const workspace=root.querySelector('[data-butchery-workspace]');if(!workspace||workspace.dataset.ready)return;workspace.dataset.ready='1';
workspace.querySelectorAll('table').forEach(t=>{if(!t.tBodies[0]?.rows.length){t.hidden=true;t.dataset.datatable='false';return;}const wrapper=document.createElement('div');wrapper.className='table-responsive';t.before(wrapper);wrapper.append(t);});
workspace.querySelectorAll('[data-butchery-form]').forEach(form=>{
 form.querySelectorAll('select').forEach(select=>{if(['lot_type','visual_status','document_status','decision'].includes(select.name)){select.required=true;return;}if(!select.querySelector('option[value=""]'))select.insertBefore(new Option('Sélectionner…',''),select.firstChild);if(select.required)select.value='';});
 function sync(){
 if(form.hasAttribute('data-production-results')){
     const read=name=>form.elements[name].value===''?0:form.elements[name].valueAsNumber;
     const input=read('raw_quantity_kg')+read('additive_quantity_kg'),output=read('output_kg')+read('co_product_kg');
     const balance=form.querySelector('[data-results-balance]');
     const ready=form.elements.raw_quantity_kg.value!==''&&form.elements.output_kg.value!==''&&Number.isFinite(input)&&Number.isFinite(output)&&input>0;
     balance.classList.toggle('is-invalid',ready&&output>input);
     balance.textContent=!ready?'Renseignez les quantités pour afficher le bilan.':output>input?'Les produits et coproduits dépassent la quantité consommée.':'Entrée : '+input.toLocaleString('fr-FR')+' kg · Sortie : '+output.toLocaleString('fr-FR')+' kg · Pertes : '+(input-output).toLocaleString('fr-FR',{maximumFractionDigits:3})+' kg · Rendement : '+(output/input*100).toLocaleString('fr-FR',{maximumFractionDigits:2})+' %';
 }

 if(form.hasAttribute('data-production-plan')){
     const recipe=form.elements.recipe_version_id.selectedOptions[0],recipeInfo=form.querySelector('[data-plan-recipe]');
     recipeInfo.hidden=!recipe?.value;
     recipeInfo.textContent=recipe?.value?recipe.dataset.recipeName+' · '+recipe.dataset.recipeCode+' · Version '+recipe.dataset.recipeVersion:'';
     const allowed=(recipe?.dataset.rawItems||'').split(',');
     Array.from(form.elements.raw_lot_id.options).forEach(option=>{if(!option.value)return;let data={};try{data=JSON.parse(option.dataset.planLot||'{}');}catch(e){}option.disabled=!!recipe?.value&&!allowed.includes(String(data.itemId));option.hidden=option.disabled;});
     if(form.elements.raw_lot_id.selectedOptions[0]?.disabled)form.elements.raw_lot_id.value='';
     let lot=null;try{lot=JSON.parse(form.elements.raw_lot_id.selectedOptions[0]?.dataset.planLot||'null');}catch(e){}
     const summary=form.querySelector('[data-plan-summary]'),quantity=form.elements.planned_input_kg,balance=form.querySelector('[data-plan-balance]');
     summary.hidden=!lot;
     summary.querySelectorAll('[data-plan-field]').forEach(field=>{field.textContent=lot?.[field.dataset.planField]??'';});
     const kg=value=>value.toLocaleString('fr-FR',{minimumFractionDigits:3,maximumFractionDigits:3})+' kg';
     summary.querySelector('[data-plan-available]').textContent=lot?kg(lot.available):'';
     if(lot)quantity.max=String(lot.available);else quantity.removeAttribute('max');
     balance.hidden=!lot||!Number.isFinite(quantity.valueAsNumber)||!quantity.validity.valid;
     balance.textContent=balance.hidden?'':'Solde prévisionnel après consommation : '+kg(Math.max(0,lot.available-quantity.valueAsNumber));
 }

 if(form.hasAttribute('data-slaughter-validation')){
     const cost=form.elements.unit_cost, total=form.querySelector('[data-slaughter-total]');
     const amount=Number(form.dataset.netWeight)*cost.valueAsNumber;
     if(total)total.textContent=cost.value!==''&&cost.validity.valid&&Number.isFinite(amount)?amount.toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2}):'—';
 }

 const liveLot=form.elements.live_lot_id;
 if(liveLot){
     let details=null;
     try{details=JSON.parse(liveLot.selectedOptions[0]?.dataset.liveDetails||'null');}catch(e){}
     const summary=form.querySelector('[data-live-summary]'),hint=form.querySelector('[data-live-hint]');
     if(summary){summary.hidden=!details;summary.querySelectorAll('[data-live-field]').forEach(field=>{field.textContent=details?.[field.dataset.liveField]??'—';});}
     if(hint){hint.hidden=!!details;hint.textContent=liveLot.options.length>1?'Sélectionnez un lot pour consulter sa provenance et le nombre de têtes disponibles.':'Aucun lot disponible. Réceptionnez et contrôlez un lot vivant pour commencer.';}
     const heads=form.elements.input_heads;
     if(details)heads.max=String(details.heads);else heads.removeAttribute('max');
 }
 const transfer=form.elements.livestock_transfer_id;
 if(transfer){
     const summary=form.querySelector('[data-transfer-summary]');
     const hint=form.querySelector('[data-transfer-hint]');
     let details=null;
     try{details=JSON.parse(transfer.selectedOptions[0]?.dataset.transferDetails||'null');}catch(e){}
     if(summary){
         summary.hidden=!details;
         summary.querySelectorAll('[data-transfer-field]').forEach(field=>{field.textContent=details?.[field.dataset.transferField]||'Non renseigné';});
     }
     if(hint){hint.hidden=!!details;hint.textContent=transfer.options.length>1?'Sélectionnez un transfert pour vérifier sa provenance, son lot et la quantité attendue.':'Aucun transfert à recevoir. Les transferts apparaissent ici après leur expédition depuis l’élevage.';}
     const costLabel=form.querySelector('[data-transfer-cost-label]');
     if(costLabel)costLabel.textContent=details?'Coût unitaire (par '+(details.unit==='head'?'tête':'kg')+')':'Coût unitaire';
 }

 if(form.elements.lot_type){const type=form.elements.lot_type.value,lot=form.elements.lot_id;Array.from(lot.options).forEach(o=>{if(o.value){o.disabled=o.dataset.lotType!==type;o.hidden=o.disabled;}});if(lot.selectedOptions[0]?.disabled)lot.value='';}
 if(form.hasAttribute('data-outgoing-form')){
     const select=form.elements.lot_id||form.elements.finished_lot_id;
     if(form.elements.lot_type){const type=form.elements.lot_type.value;if(form.dataset.previousType&&form.dataset.previousType!==type)select.value='';form.dataset.previousType=type;}
     let lot=null;try{lot=JSON.parse(select.selectedOptions[0]?.dataset.outgoingLot||'null');}catch(e){}
     const summary=form.querySelector('[data-outgoing-summary]'),balance=form.querySelector('[data-outgoing-balance]'),quantity=form.elements.quantity_kg;
     const number=value=>value.toLocaleString('fr-FR',{maximumFractionDigits:3});
     summary.hidden=!lot;summary.textContent=lot?lot.item+' · '+lot.lot+' · Disponible : '+number(lot.available)+' kg · DLC : '+lot.expiry:'';
     if(lot)quantity.max=String(lot.available);else quantity.removeAttribute('max');
     const valid=!!lot&&quantity.value!==''&&quantity.validity.valid&&Number.isFinite(quantity.valueAsNumber);
     balance.hidden=!valid;balance.textContent=valid?'Solde prévisionnel : '+number(Math.max(0,lot.available-quantity.valueAsNumber))+' kg':'';
     const price=form.elements.unit_price;if(valid&&price&&price.value!==''&&price.validity.valid&&Number.isFinite(price.valueAsNumber))balance.textContent+=' · Total : '+(quantity.valueAsNumber*price.valueAsNumber).toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2});
 }
 if(form.elements.decision){const reject=form.elements.decision.value==='reject';['item_id','production_date','expiry_date'].forEach(n=>{const field=form.elements[n];field.disabled=reject;field.required=!reject;field.closest('label').hidden=reject;});}
 const missing=Array.from(form.querySelectorAll('select[required]')).some(s=>!s.disabled&&!Array.from(s.options).some(o=>o.value&&!o.disabled));
 form.querySelector('[type=submit]').disabled=missing;
 let note=form.querySelector('[data-butchery-missing]');if(!note){note=document.createElement('p');note.dataset.butcheryMissing='';note.className='livestock-guide';form.querySelector('.feed-form-body').append(note);}
 note.hidden=!missing;note.textContent='Aucune référence disponible pour cette opération. Vérifiez les réceptions, les stocks ou les recettes dans l’activité correspondante.';
 if(form.hasAttribute('data-production-plan')&&missing){note.textContent=!Array.from(form.elements.recipe_version_id.options).some(o=>o.value)?'Une recette active est nécessaire pour planifier la fabrication.':'Aucun lot de matière disponible. Choisissez un lot compatible avec la fiche ou validez un abattage de cette matière.';}
 }
 if(form.matches('[data-slaughter-validation],[data-production-plan],[data-production-results],[data-outgoing-form]'))form.addEventListener('input',sync);
 form.addEventListener('change',sync);form.addEventListener('reset',()=>queueMicrotask(sync));sync();
});
};
})();
