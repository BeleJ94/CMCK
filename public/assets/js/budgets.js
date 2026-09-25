(function(){'use strict';window.initBudgets=function(root){
(root||document).querySelectorAll('.budget-tabs').forEach(function(nav){if(nav.dataset.ready)return;nav.dataset.ready='true';var tabs=Array.from(nav.querySelectorAll('[data-budget-tab]')),workspace=nav.closest('.budget-workspace');
function select(tab){tabs.forEach(function(t){var active=t===tab;t.setAttribute('aria-selected',String(active));t.tabIndex=active?0:-1;});workspace.querySelectorAll('[data-budget-panel]').forEach(function(panel){panel.hidden=panel.dataset.budgetPanel!==tab.dataset.budgetTab;});}
tabs.forEach(function(tab,index){tab.addEventListener('click',function(){select(tab);});tab.addEventListener('keydown',function(e){var next;if(e.key==='ArrowRight')next=(index+1)%tabs.length;if(e.key==='ArrowLeft')next=(index+tabs.length-1)%tabs.length;if(e.key==='Home')next=0;if(e.key==='End')next=tabs.length-1;if(next!==undefined){e.preventDefault();select(tabs[next]);tabs[next].focus();}});});
});
(root||document).querySelectorAll('[data-budget-form]').forEach(function(form){if(form.dataset.ready)return;form.dataset.ready='true';var error=form.querySelector('[data-budget-error]'),button=form.querySelector('[type=submit]');
if(form.hasAttribute('data-budget-revision')){
 var amount=form.elements.amount,difference=form.querySelector('[data-budget-difference]'),current=Number(form.dataset.currentAmount);
 function previewRevision(){var value=Number(amount.value),delta=value-current;difference.textContent=!amount.value||!Number.isFinite(value)||value<=0?'Saisissez un montant positif.':delta===0?'Montant inchangé':(delta>0?'Hausse de ':'Baisse de ')+Math.abs(delta).toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2})+(current>0?' ('+(Math.abs(delta)/current*100).toLocaleString('fr-FR',{maximumFractionDigits:1})+' %)':'');}
 amount.addEventListener('input',previewRevision);form.addEventListener('reset',function(){setTimeout(previewRevision,0);});previewRevision();
}
function filterOptions(field,parent,attribute){if(!field||!parent)return;Array.from(field.options).forEach(function(o){o.disabled=o.hidden=o.dataset[attribute]!==parent.value;});if(field.selectedOptions[0]&&field.selectedOptions[0].disabled)field.value='';field.required=true;}
function dependencies(){filterOptions(form.elements.period_id,form.elements.fiscal_year_id,'year');filterOptions(form.elements.cost_center_id,form.elements.site_id,'site');}
form.addEventListener('change',dependencies);form.addEventListener('reset',function(){setTimeout(dependencies,0);});dependencies();
form.querySelectorAll('select').forEach(function(field){if(!field.options.length){field.add(new Option('Aucune référence disponible',''));field.disabled=true;button.disabled=true;}});
form.addEventListener('reset',function(){error.hidden=true;});
form.addEventListener('submit',async function(e){e.preventDefault();if(form.dataset.busy==='true'||!form.reportValidity())return;form.dataset.busy='true';button.disabled=true;error.hidden=true;
try{var response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});if(!(response.headers.get('content-type')||'').includes('application/json'))throw new Error('Réponse non confirmée. Vérifiez votre session et le résultat avant de réessayer.');var result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Opération refusée.');location.assign(result.redirect);}
catch(e){error.textContent=e.message;error.hidden=false;error.scrollIntoView({block:'nearest'});form.dataset.busy='false';button.disabled=false;}});
});};})();
