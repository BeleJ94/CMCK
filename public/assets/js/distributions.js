(function(){
'use strict';
var fmt=function(n,d){return Number(n).toLocaleString('fr-FR',{minimumFractionDigits:d||0,maximumFractionDigits:d||0});};
// Sober confirmation of a client exit: what leaves, from where, to whom (same look as the transfer confirmation).
var dialog=null;
function confirmExit(form,content){
 if(!dialog){
  dialog=document.createElement('dialog');dialog.className='op-confirm';dialog.setAttribute('aria-labelledby','exit-confirm-title');dialog.setAttribute('data-exit-confirm-dialog','');
  dialog.innerHTML='<form method="dialog"><h2 id="exit-confirm-title"></h2><p class="op-confirm__lead"></p><ul class="op-confirm__lines"></ul><p class="op-confirm__total"></p><p class="op-confirm__note"></p><footer><button type="submit" value="cancel" class="btn-secondary">Modifier</button><button type="submit" value="ok" class="btn-primary" data-exit-confirm-ok></button></footer></form>';
  document.body.appendChild(dialog);
  // Escape only closes the confirmation, not the drawer underneath.
  dialog.addEventListener('keydown',function(event){if(event.key==='Escape')event.stopPropagation();});
 }
 dialog.querySelector('h2').textContent=content.title;dialog.querySelector('.op-confirm__lead').textContent=content.lead;
 var list=dialog.querySelector('.op-confirm__lines');list.replaceChildren();
 content.lines.forEach(function(line){var li=document.createElement('li'),strong=document.createElement('strong');strong.textContent=line[0];li.appendChild(strong);if(line[1]){var small=document.createElement('small');small.textContent=line[1];li.appendChild(small);}list.appendChild(li);});
 dialog.querySelector('.op-confirm__total').textContent=content.total;dialog.querySelector('.op-confirm__note').textContent=content.note;dialog.querySelector('[data-exit-confirm-ok]').textContent=content.ok;
 dialog.onclose=function(){if(dialog.returnValue==='ok'){form.dataset.confirmed='true';form.requestSubmit();}else form.querySelector('[type=submit]').focus();};
 dialog.returnValue='';dialog.showModal();dialog.querySelector('[data-exit-confirm-ok]').focus();
}
// Validated, confirmed, then posted: the page comes back with the message and the note to print.
function bindSubmit(form,error,summary){
 var button=form.querySelector('[type=submit]');
 form.addEventListener('submit',async function(event){
  event.preventDefault();if(form.dataset.busy==='true')return;
  var problem=summary.check();if(problem){error.textContent=problem;error.hidden=false;error.scrollIntoView({block:'nearest'});return;}
  if(!form.reportValidity())return;
  if(form.dataset.confirmed!=='true'){error.hidden=true;confirmExit(form,summary.confirm());return;}
  form.dataset.confirmed='';form.dataset.busy='true';button.disabled=true;error.hidden=true;
  try{
   var response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
   if(!(response.headers.get('content-type')||'').includes('application/json'))throw new Error('Réponse non confirmée. Vérifiez votre session et l’historique avant de réessayer.');
   var result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Sortie refusée.');
   location.assign(result.redirect);
  }catch(e){error.textContent=e.message;error.hidden=false;error.scrollIntoView({block:'nearest'});form.dataset.busy='false';button.disabled=false;}
 });
 form.addEventListener('reset',function(){setTimeout(function(){error.hidden=true;form.dataset.confirmed='';summary.sync();},0);});
}

// Delivery of an order: the chosen place sets how many bags are ready per line.
function initDelivery(form){
 var error=form.querySelector('[data-delivery-error]'),out=form.querySelector('[data-delivery-summary]'),rows=Array.from(form.querySelectorAll('[data-delivery-line]'));
 var source=function(){return form.querySelector('[name=source]:checked');};
 var mode=function(){var m=form.querySelector('[name=delivery_mode]:checked');return m?m.value:'pickup';};
 function totals(){var t={bags:0,kg:0,usd:0,lines:[]};rows.forEach(function(row){var n=Number(row.querySelector('[data-line-input]').value)||0;if(n<=0)return;var kg=n*Number(row.dataset.weight),usd=n*Number(row.dataset.price);t.bags+=n;t.kg+=kg;t.usd+=usd;t.lines.push([row.dataset.label+' · '+fmt(n)+' sac(s)',fmt(kg)+' kg · '+fmt(usd,2)+' USD'+(n<Number(row.dataset.toDeliver)?' · reste '+fmt(Number(row.dataset.toDeliver)-n)+' à livrer':'')]);});return t;}
 function amount(usd){var rate=Number(form.dataset.rate);return fmt(usd,2)+' USD'+(form.dataset.currency==='CDF'&&rate?' · '+fmt(usd*rate)+' CDF':'');}
 function place(){
  var picked=source(),ready={};try{ready=picked?JSON.parse(picked.dataset.ready||'{}'):{};}catch(e){}
  rows.forEach(function(row){var here=Number(ready[row.dataset.deliveryLine]||0),max=Math.min(here,Number(row.dataset.toDeliver)),input=row.querySelector('[data-line-input]');
   row.querySelector('[data-line-ready]').textContent=fmt(here);row.querySelector('[data-line-place]').textContent='Prêts à '+(picked?picked.dataset.site:'');row.querySelector('[data-line-max]').textContent=fmt(max);
   input.max=max;input.value=max;input.disabled=!max;row.classList.toggle('is-empty',!max);row.querySelectorAll('[data-line-fill],[data-line-step]').forEach(function(b){b.disabled=!max;});});
  sync();
 }
 function transport(){
  var truck=mode()==='company_truck',vehicle=form.querySelector('[data-vehicle]'),driver=form.querySelector('[data-driver]');
  vehicle.required=truck;driver.required=truck;
  form.querySelector('[data-vehicle-label]').textContent=truck?'Camion de l’entreprise *':'Véhicule du client';form.querySelector('[data-driver-label]').textContent=truck?'Chauffeur *':'Chauffeur';
  vehicle.placeholder=truck?'Immatriculation':'Immatriculation (facultatif)';driver.placeholder=truck?'Nom du chauffeur':'Facultatif';
 }
 function sync(){
  rows.forEach(function(row){var input=row.querySelector('[data-line-input]'),n=Number(input.value)||0;
   row.querySelector('[data-line-total]').textContent=input.disabled?'Rien de prêt ici pour ce produit':(n?fmt(n*Number(row.dataset.weight))+' kg · '+fmt(n*Number(row.dataset.price),2)+' USD':'Pas livré cette fois');});
  var t=totals();out.textContent=t.bags?'Total à livrer : '+fmt(t.bags)+' sac(s) · '+fmt(t.kg)+' kg · '+amount(t.usd):'Indiquez les sacs à remettre au client.';
 }
 form.addEventListener('change',function(event){if(event.target.name==='source')place();else if(event.target.name==='delivery_mode')transport();});
 form.addEventListener('input',sync);
 // « Tout » proposes every bag ready here; − and + adjust one bag at a time, within what is ready.
 form.addEventListener('click',function(event){var button=event.target.closest('[data-line-fill],[data-line-step]');if(!button)return;var input=button.closest('[data-delivery-line]').querySelector('[data-line-input]');
  input.value=button.dataset.lineFill?input.max:Math.max(0,Math.min(Number(input.max),(Number(input.value)||0)+Number(button.dataset.lineStep)));sync();});
 form.addEventListener('reset',function(){setTimeout(function(){place();transport();},0);});
 bindSubmit(form,error,{sync:sync,
  check:function(){return totals().bags?'':'Indiquez au moins un sac à livrer.';},
  confirm:function(){var t=totals(),picked=source(),truck=mode()==='company_truck',el=form.elements,phone=el.receiver_phone.value.trim();
   return {title:'Confirmer la livraison',ok:'Confirmer la livraison',lines:t.lines,
    lead:form.dataset.customer+' · '+form.dataset.order+' · depuis '+picked.dataset.site+' ('+picked.dataset.stage.toLowerCase()+')',
    total:'Total : '+fmt(t.bags)+' sac(s) · '+fmt(t.kg)+' kg · '+amount(t.usd)+(t.bags>=Number(form.dataset.toDeliver)?' · la commande sera soldée':''),
    note:(truck?'Camion '+el.vehicle_reference.value.trim()+', chauffeur '+el.driver_name.value.trim():'Enlèvement par le client'+(el.vehicle_reference.value.trim()?' (véhicule '+el.vehicle_reference.value.trim()+')':''))+'. Reçu par '+el.receiver_name.value.trim()+(phone?' ('+phone+')':'')+'. Les sacs sortent du stock de '+picked.dataset.site+' et un bon de livraison est émis.'};}});
 // The drawer resets the form when it opens: the bags ready at the default place are its default values.
 place();rows.forEach(function(row){var input=row.querySelector('[data-line-input]');input.defaultValue=input.value;});transport();
}

// Exit without an order: a registered client fills the recipient, the lot sets the maximum.
function initExit(form){
 var lot=form.elements.finished_stock_id,qty=form.elements.quantity_bags,customer=form.elements.customer_id,recipient=form.elements.recipient_name,error=form.querySelector('[data-distribution-error]'),out=form.querySelector('[data-distribution-summary]'),fill=form.querySelector('[data-exit-fill]'),button=form.querySelector('[type=submit]');
 var picked=function(){var o=lot.selectedOptions[0];return o&&o.value?o:null;};
 function who(){var c=customer&&customer.value?customer.selectedOptions[0]:null;var box=form.querySelector('[data-exit-recipient]');box.hidden=!!c;recipient.required=!c;recipient.disabled=!!c;return c?c.textContent.split(' · ')[0]:recipient.value.trim();}
 function sync(){var o=picked();qty.disabled=!o;fill.disabled=!o;button.disabled=!o||form.dataset.busy==='true';out.hidden=!o;
  if(o){qty.max=o.dataset.bags;var n=Number(qty.value||0);out.textContent='Libre : '+fmt(o.dataset.bags)+' sacs · Sortie : '+fmt(n*Number(o.dataset.weight))+' kg'+(n&&n<Number(o.dataset.bags)?' · il restera '+fmt(Number(o.dataset.bags)-n)+' sac(s) sur le lot':'');}else qty.removeAttribute('max');}
 lot.addEventListener('change',function(){qty.value='';sync();});qty.addEventListener('input',sync);
 if(customer)customer.addEventListener('change',who);
 fill.addEventListener('click',function(){var o=picked();if(o){qty.value=o.dataset.bags;sync();}});
 bindSubmit(form,error,{sync:function(){who();sync();},
  check:function(){return picked()?'':'Choisissez le lot à sortir.';},
  confirm:function(){var o=picked(),n=Number(qty.value),transporter=form.elements.transporter.value.trim();
   return {title:'Confirmer la sortie',ok:'Confirmer la sortie',lead:who()+' · depuis '+o.dataset.site,lines:[[o.dataset.label+' · '+fmt(n)+' sac(s)',o.dataset.lot]],
    total:'Total : '+fmt(n)+' sac(s) · '+fmt(n*Number(o.dataset.weight))+' kg',note:(transporter?'Transport : '+transporter+'. ':'')+'La sortie est validée et déduite du stock immédiatement ; un bon de sortie est émis.'};}});
 who();sync();
}

// Preview of a note: the PDF itself in a frame, so that what is seen is what is downloaded and printed.
function initNotePreview(root){
 var modal=(root||document).querySelector('[data-note-preview]');if(!modal||modal.dataset.ready)return;modal.dataset.ready='true';
 var frame=modal.querySelector('[data-note-frame]'),loading=modal.querySelector('[data-note-loading]'),timer=null;
 function shown(){clearTimeout(timer);loading.hidden=true;frame.classList.add('is-ready');}
 frame.addEventListener('load',function(){if(frame.getAttribute('src'))shown();});
 modal.addEventListener('workspace:opened',function(event){
  var trigger=event.detail&&event.detail.trigger;if(!trigger||!trigger.dataset.noteUrl)return;
  modal.querySelector('[data-note-title]').textContent=trigger.dataset.noteTitle||'Bon';
  modal.querySelector('[data-note-tab]').href=trigger.dataset.noteUrl;modal.querySelector('[data-note-pdf]').href=trigger.dataset.noteDownload||trigger.dataset.noteUrl;
  // The PDF fits the width, without the thumbnails panel.
  var src=trigger.dataset.noteUrl+'#navpanes=0&view=FitH';
  if(frame.getAttribute('src')!==src){loading.hidden=false;frame.classList.remove('is-ready');frame.src=src;timer=setTimeout(shown,4000);}
 });
 // The browser's PDF viewer prints the note; when the frame refuses, the PDF opens in a new tab to be printed there.
 modal.querySelector('[data-note-print]').addEventListener('click',function(){
  try{frame.contentWindow.focus();frame.contentWindow.print();}catch(e){window.open(modal.querySelector('[data-note-tab]').href,'_blank','noopener');}
 });
}

// A link such as /distributions#deliver-12 or /sales/orders#order-detail-12 opens that drawer once.
function openFromHash(root){
 var id=String(location.hash||'').slice(1);if(!/^(deliver|order-detail)-[0-9]+$/.test(id))return;
 var trigger=(root||document).querySelector('[data-workspace-modal-open="'+id+'"]');if(!trigger)return;
 history.replaceState(null,'',location.pathname+location.search);trigger.click();
}
window.initDistributions=function(root){
 (root||document).querySelectorAll('[data-delivery-form]').forEach(function(form){if(form.dataset.ready)return;form.dataset.ready='true';initDelivery(form);});
 (root||document).querySelectorAll('[data-distribution-editor]').forEach(function(form){if(form.dataset.ready)return;form.dataset.ready='true';initExit(form);});
 initNotePreview(root);openFromHash(root);
};
})();
