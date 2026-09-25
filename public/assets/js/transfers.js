(function(){
'use strict';
window.initTransfers=function(root){
(root||document).querySelectorAll('[data-transfer-operation]').forEach(function(form){
 if(form.dataset.ready)return;form.dataset.ready='true';
 var error=form.querySelector('[data-transfer-error]'),button=form.querySelector('[type=submit]');
 function validate(){form.querySelectorAll('[data-receive-left]').forEach(function(row){var a=row.querySelector('[data-accepted]'),r=row.querySelector('[data-rejected]');a.setCustomValidity(Number(a.value)+Number(r.value)>Number(row.dataset.receiveLeft)?'Le total accepté et refusé dépasse la quantité restante.':'');});}
 form.addEventListener('input',validate);
 form.addEventListener('reset',function(){setTimeout(function(){error.hidden=true;validate();},0);});
 form.addEventListener('submit',async function(event){event.preventDefault();if(form.dataset.busy==='true')return;validate();if(!form.reportValidity())return;
 var inputs=Array.from(form.querySelectorAll('input[type=number]'));if(!inputs.some(function(i){return Number(i.value)>0;})){error.textContent='Saisissez au moins une quantité.';error.hidden=false;return;}
 var data=new FormData(form);
 // Omit untouched receipt lines so that partial receipts remain possible.
 form.querySelectorAll('[data-receive-left]').forEach(function(row){if(!Array.from(row.querySelectorAll('input[type=number]')).some(function(i){return Number(i.value)>0;}))row.querySelectorAll('input').forEach(function(i){data.delete(i.name);});});
 form.dataset.busy='true';button.disabled=true;error.hidden=true;
 try{var response=await fetch(form.action,{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});if(!(response.headers.get('content-type')||'').includes('application/json'))throw new Error('Réponse non confirmée. Vérifiez votre session et le dossier avant de réessayer.');var result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Opération refusée.');location.assign(result.redirect);}
 catch(e){error.textContent=e.message;error.hidden=false;error.scrollIntoView({block:'nearest'});button.disabled=false;form.dataset.busy='false';}
 });
});
(root||document).querySelectorAll('[data-transfer-create-form]').forEach(function(form){
 if(form.dataset.ready)return;form.dataset.ready='true';
 var source=form.elements.source_site_id,dest=form.elements.destination_site_id,type=form.elements.transfer_type,lot=form.elements.finished_stock_id,qty=form.elements.quantity_bags,submit=form.querySelector('[type=submit]'),error=form.querySelector('[data-transfer-error]');
 var kind=form.querySelector('[data-stock-kind-filter]'),balanceBox=form.querySelector('[data-stock-balances]'),balances=balanceBox?JSON.parse(balanceBox.dataset.stockBalances):[];
 function syncBalances(){
  if(!kind)return;
  form.querySelectorAll('[data-balance-site]').forEach(function(row){
   var match=balances.find(function(b){return String(b.site_id)===row.dataset.balanceSite&&String(b.product_id)+':'+String(b.bag_format_id)===kind.value;});
   ['physical','reserved','available'].forEach(function(k){row.querySelector('[data-site-'+k+']').textContent=Number(match?match[k]:0).toLocaleString('fr-FR');});
   row.querySelector('button').setAttribute('aria-pressed',String(row.dataset.balanceSite===source.value));
  });
 }
 form.querySelectorAll('[data-select-source]').forEach(function(button){button.addEventListener('click',function(){source.value=button.dataset.selectSource;source.dispatchEvent(new Event('change',{bubbles:true}));});});
 function sync(){
  syncBalances();
  var count=0;Array.from(lot.options).forEach(function(o){if(!o.value)return;o.hidden=o.disabled=o.dataset.site!==source.value||(kind&&o.dataset.stockKind!==kind.value);if(!o.disabled)count++;});
  if(lot.selectedOptions[0]&&lot.selectedOptions[0].disabled)lot.value='';
  Array.from(dest.options).forEach(function(o){o.disabled=!!o.value&&type.value==='inter_site'&&o.value===source.value;});
  if(dest.selectedOptions[0]&&dest.selectedOptions[0].disabled)dest.value='';
  lot.disabled=!source.value||!count;
  var help=form.querySelector('[data-transfer-help]');help.hidden=!source.value||count>0;help.textContent='Aucun lot disponible sur ce site.';
  lot.options[0].textContent=source.value?'Choisir un lot…':'Choisir d’abord le départ…';
  var o=lot.selectedOptions[0],valid=o&&o.value&&!o.disabled,available=valid?Number(o.dataset.available):0;
  form.querySelector('[data-transfer-preview]').hidden=!valid;qty.disabled=!valid;submit.disabled=!valid||form.dataset.busy==='true';
  qty.setCustomValidity('');
  if(valid){qty.max=available;var n=Number(qty.value||0);if(n>available)qty.setCustomValidity('La quantité dépasse les '+available+' sacs disponibles.');
   var values={available:available+' sacs',balance:qty.value?(available-n)+' sacs (prévision)':'—'};
   Object.keys(values).forEach(function(k){form.querySelector('[data-transfer-'+k+']').textContent=values[k];});
  }else qty.removeAttribute('max');
 }
 form.addEventListener('change',function(e){if(e.target===source||e.target===lot||e.target===kind)qty.value='';sync();});form.addEventListener('input',sync);
 form.addEventListener('reset',function(){setTimeout(function(){error.hidden=true;form.querySelectorAll('details').forEach(function(d){d.open=d.hasAttribute('data-site-balances');});sync();},0);});sync();
 form.addEventListener('submit',async function(e){e.preventDefault();if(form.dataset.busy==='true')return;sync();if(!form.reportValidity()||lot.disabled||!lot.value)return;
  error.hidden=true;form.dataset.busy='true';submit.disabled=true;submit.textContent='Création en cours…';
  try{var response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});if(!(response.headers.get('content-type')||'').includes('application/json'))throw new Error('La réponse du serveur n’a pas pu être confirmée. Vérifiez votre session et le résultat avant de réessayer.');var result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'La demande n’a pas été enregistrée.');window.location.assign(result.redirect);}
  catch(err){error.textContent=err.message||'Connexion interrompue. Vérifiez le résultat avant de réessayer.';error.hidden=false;error.scrollIntoView({block:'nearest'});form.dataset.busy='false';submit.textContent='Créer la demande';sync();}
 });
});
};
})();
