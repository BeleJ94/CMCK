(function(){
'use strict';
// Sober confirmation before a shipment or a receipt is recorded: what leaves or arrives, lot by lot.
var confirmDialog=null;
function confirmOperation(form){
 var fmt=function(n){return Number(n).toLocaleString('fr-FR',{maximumFractionDigits:0});};
 if(!confirmDialog){
  confirmDialog=document.createElement('dialog');confirmDialog.className='op-confirm';confirmDialog.setAttribute('aria-labelledby','op-confirm-title');confirmDialog.setAttribute('data-op-confirm-dialog','');
  confirmDialog.innerHTML='<form method="dialog"><h2 id="op-confirm-title"></h2><p class="op-confirm__lead"></p><ul class="op-confirm__lines"></ul><p class="op-confirm__total"></p><p class="op-confirm__note"></p><footer><button type="submit" value="cancel" class="btn-secondary">Modifier</button><button type="submit" value="ok" class="btn-primary" data-op-confirm-ok></button></footer></form>';
  document.body.appendChild(confirmDialog);
  // Escape only closes the confirmation, not the drawer underneath.
  confirmDialog.addEventListener('keydown',function(event){if(event.key==='Escape')event.stopPropagation();});
 }
 var ship=form.matches('[data-transfer-ship]'),d=form.dataset,lines=confirmDialog.querySelector('.op-confirm__lines');lines.replaceChildren();
 var add=function(title,detail,warning){var li=document.createElement('li');var strong=document.createElement('strong');strong.textContent=title;li.appendChild(strong);if(detail){var small=document.createElement('small');small.textContent=detail;li.appendChild(small);}if(warning)li.className='is-warning';lines.appendChild(li);};
 var total='',note='';
 if(ship){
  var bags=0,kg=0,left=0;
  form.querySelectorAll('[data-op-group]').forEach(function(group){
   var lots=Array.from(group.querySelectorAll('[data-op-lot]')).map(function(lot){return {label:lot.dataset.lotLabel,bags:Number(lot.querySelector('input').value)||0,max:Number(lot.querySelector('input').max)};});
   var sum=lots.reduce(function(n,l){return n+l.bags;},0),weight=Number(group.dataset.weight||0);left+=lots.reduce(function(n,l){return n+l.max;},0)-sum;
   if(!sum)return;bags+=sum;kg+=sum*weight;
   add(group.dataset.label+' · '+fmt(sum)+' sac(s)'+(weight?' · '+fmt(sum*weight)+' kg':''),lots.length>1?lots.filter(function(l){return l.bags;}).map(function(l){return l.label+' : '+fmt(l.bags);}).join(' · '):'');
  });
  confirmDialog.querySelector('h2').textContent='Confirmer l’expédition';
  confirmDialog.querySelector('.op-confirm__lead').textContent=d.sourceName+' → '+d.destinationName+' · véhicule '+(form.elements.vehicle_reference.value.trim()||'non renseigné')+(d.order?' · '+d.order:'');
  total='Total : '+fmt(bags)+' sac(s)'+(kg?' · '+fmt(kg)+' kg':'')+(left>0?' · '+fmt(left)+' restera à expédier':'');
  note='Les sacs quittent le stock de '+d.sourceName+' et passent « en route » jusqu’à leur réception.';
  confirmDialog.querySelector('[data-op-confirm-ok]').textContent='Confirmer l’expédition';
 }else{
  var accepted=0,rejected=0,expected=0;
  form.querySelectorAll('[data-receive-left]').forEach(function(row){
   var a=Number(row.querySelector('[data-accepted]').value)||0,r=Number(row.querySelector('[data-rejected]').value)||0,reason=(row.querySelector('[name^="quality_notes"]')||{}).value||'';expected+=Number(row.dataset.receiveLeft);
   if(!a&&!r)return;accepted+=a;rejected+=r;
   var group=row.closest('[data-op-group]');
   add((group?group.dataset.label+' · ':'')+row.dataset.lotLabel,fmt(a)+' accepté(s)'+(r?' · '+fmt(r)+' refusé(s)'+(reason.trim()?' : '+reason.trim():''):''),r>0);
  });
  confirmDialog.querySelector('h2').textContent='Confirmer la réception';
  confirmDialog.querySelector('.op-confirm__lead').textContent=d.shipment+' · à '+d.destinationName+(d.order?' · '+d.order:'');
  var rest=Math.max(0,expected-accepted-rejected);
  total='Total : '+fmt(accepted)+' accepté(s)'+(rejected?' · '+fmt(rejected)+' refusé(s)':'')+(rest?' · '+fmt(rest)+' encore attendu(s)':'');
  note='Les sacs acceptés entrent dans le stock de '+d.destinationName+(d.order?', réservés au client':'')+'.'+(rejected?' Les sacs refusés feront l’objet d’un écart et d’un retour vers '+d.sourceName+'.':'');
  confirmDialog.querySelector('[data-op-confirm-ok]').textContent='Confirmer la réception';
 }
 confirmDialog.querySelector('.op-confirm__total').textContent=total;confirmDialog.querySelector('.op-confirm__note').textContent=note;
 confirmDialog.onclose=function(){if(confirmDialog.returnValue==='ok'){form.dataset.confirmed='true';form.requestSubmit();}else form.querySelector('[type=submit]').focus();};
 confirmDialog.returnValue='';confirmDialog.showModal();confirmDialog.querySelector('[data-op-confirm-ok]').focus();
}
window.initTransfers=function(root){
(root||document).querySelectorAll('[data-transfer-operation]').forEach(function(form){
 if(form.dataset.ready)return;form.dataset.ready='true';
 var error=form.querySelector('[data-transfer-error]'),button=form.querySelector('[type=submit]');
 function validate(){form.querySelectorAll('[data-receive-left]').forEach(function(row){var a=row.querySelector('[data-accepted]'),r=row.querySelector('[data-rejected]');a.setCustomValidity(Number(a.value)+Number(r.value)>Number(row.dataset.receiveLeft)?'Le total accepté et refusé dépasse la quantité restante.':'');});}
 form.addEventListener('input',validate);
 form.addEventListener('reset',function(){setTimeout(function(){error.hidden=true;validate();},0);});
 form.addEventListener('submit',async function(event){event.preventDefault();if(form.dataset.busy==='true')return;validate();if(!form.reportValidity())return;
 var inputs=Array.from(form.querySelectorAll('input[type=number]'));if(!inputs.some(function(i){return Number(i.value)>0;})){error.textContent='Saisissez au moins une quantité.';error.hidden=false;return;}
 if(form.matches('[data-transfer-ship],[data-transfer-receive]')&&form.dataset.confirmed!=='true'){error.hidden=true;confirmOperation(form);return;}
 form.dataset.confirmed='';
 var data=new FormData(form);
 // Omit untouched receipt lines so that partial receipts remain possible.
 form.querySelectorAll('[data-receive-left]').forEach(function(row){if(!Array.from(row.querySelectorAll('input[type=number]')).some(function(i){return Number(i.value)>0;}))row.querySelectorAll('input').forEach(function(i){data.delete(i.name);});});
 form.dataset.busy='true';button.disabled=true;error.hidden=true;
 try{var response=await fetch(form.action,{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});if(!(response.headers.get('content-type')||'').includes('application/json'))throw new Error('Réponse non confirmée. Vérifiez votre session et le dossier avant de réessayer.');var result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Opération refusée.');location.assign(result.redirect);}
 catch(e){error.textContent=e.message;error.hidden=false;error.scrollIntoView({block:'nearest'});button.disabled=false;form.dataset.busy='false';}
 });
});
var fmt=function(n){return Number(n).toLocaleString('fr-FR',{maximumFractionDigits:0});};
// Shipment: one quantity per product and format, spread over its lots oldest first; each lot can still be adjusted.
(root||document).querySelectorAll('[data-transfer-ship]').forEach(function(form){
 if(form.dataset.shipReady)return;form.dataset.shipReady='true';
 var groups=Array.from(form.querySelectorAll('[data-op-group]')),summary=form.querySelector('[data-op-summary]'),submit=form.querySelector('[data-op-submit]');
 function lots(group){return Array.from(group.querySelectorAll('[data-op-lot-input]'));}
 function spread(group,value){var left=Math.max(0,Math.floor(Number(value)||0));lots(group).forEach(function(input){var take=Math.min(left,Number(input.max));input.value=take;left-=take;});}
 function sync(){
  var bags=0,kg=0,rest=0;
  groups.forEach(function(group){
   var total=group.querySelector('[data-op-total]'),sum=lots(group).reduce(function(n,input){return n+Math.max(0,Number(input.value)||0);},0),max=Number(total.max);
   if(document.activeElement!==total)total.value=sum;
   total.setCustomValidity(Number(total.value)>max?'Au plus '+fmt(max)+' sacs restent à expédier.':'');
   bags+=sum;kg+=sum*Number(group.dataset.weight||0);rest+=Math.max(0,max-sum);
  });
  summary.textContent=bags?'Expédition : '+fmt(bags)+' sac(s)'+(kg?' · '+fmt(kg)+' kg':'')+(rest?' · '+fmt(rest)+' restera à expédier.':' · tout part.'):'Aucun sac à expédier : saisissez une quantité.';
  summary.classList.toggle('is-empty',!bags);submit.textContent=bags?'Expédier '+fmt(bags)+' sac(s)':'Confirmer l’expédition';
 }
 form.addEventListener('input',function(event){var group=event.target.closest('[data-op-group]');if(group&&event.target.matches('[data-op-total]'))spread(group,event.target.value);sync();});
 form.addEventListener('click',function(event){var button=event.target.closest('[data-op-fill]');if(!button)return;var group=button.closest('[data-op-group]'),total=group.querySelector('[data-op-total]');total.value=button.dataset.opFill==='all'?total.max:0;spread(group,total.value);sync();});
 form.addEventListener('reset',function(){setTimeout(sync,0);});
 sync();
});
// Receipt: everything accepted by default; a rejection lowers the accepted bags and asks for its reason.
(root||document).querySelectorAll('[data-transfer-receive]').forEach(function(form){
 if(form.dataset.receiveReady)return;form.dataset.receiveReady='true';
 var rows=Array.from(form.querySelectorAll('[data-receive-left]')),summary=form.querySelector('[data-op-summary]'),submit=form.querySelector('[data-op-submit]');
 function sync(){
  var accepted=0,rejected=0,expected=0;
  rows.forEach(function(row){var a=Number(row.querySelector('[data-accepted]').value)||0,r=Number(row.querySelector('[data-rejected]').value)||0;expected+=Number(row.dataset.receiveLeft);accepted+=a;rejected+=r;row.querySelector('[data-op-note]').hidden=r<=0;row.classList.toggle('has-rejection',r>0);});
  var rest=Math.max(0,expected-accepted-rejected);
  summary.textContent=accepted||rejected?'Réception : '+fmt(accepted)+' accepté(s)'+(rejected?' · '+fmt(rejected)+' refusé(s), renvoyé(s) au départ':'')+(rest?' · '+fmt(rest)+' encore attendu(s).':'.'):'Aucun sac saisi.';
  summary.classList.toggle('is-empty',!(accepted||rejected));submit.textContent=accepted||rejected?'Enregistrer '+fmt(accepted+rejected)+' sac(s)':'Enregistrer la réception';
 }
 form.addEventListener('input',function(event){
  var row=event.target.closest('[data-receive-left]');
  if(row&&event.target.matches('[data-rejected]')){var a=row.querySelector('[data-accepted]'),left=Number(row.dataset.receiveLeft),r=Math.min(left,Math.max(0,Number(event.target.value)||0));if(Number(a.value)+r>left)a.value=left-r;a.setCustomValidity('');event.target.setCustomValidity('');}
  sync();
 });
 form.addEventListener('click',function(event){var button=event.target.closest('[data-op-fill-all]');if(!button)return;rows.forEach(function(row){row.querySelector('[data-accepted]').value=button.dataset.opFillAll==='accept'?row.dataset.receiveLeft:0;row.querySelector('[data-rejected]').value=0;row.querySelectorAll('input[type=number]').forEach(function(input){input.setCustomValidity('');});});sync();});
 form.addEventListener('reset',function(){setTimeout(sync,0);});
 sync();
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
