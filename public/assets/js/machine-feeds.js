(function(){
'use strict';
window.initMachineFeeds=function(root){
var page=root.matches&&root.matches('[data-machine-feed-page]')?root:root.querySelector('[data-machine-feed-page]');if(!page||page.dataset.ready)return;page.dataset.ready='1';
var search=page.querySelector('[data-feed-search]'),state=page.querySelector('[data-feed-state]'),rows=Array.from(page.querySelectorAll('[data-feed-row]')),current=1;
var normalize=function(s){return s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();};
function filter(){var found=rows.filter(function(r){return normalize(r.dataset.search).includes(normalize(search.value))&&(!state.value||r.dataset.state===state.value);}),pages=Math.max(1,Math.ceil(found.length/10));current=Math.min(current,pages);rows.forEach(function(r){r.hidden=true;});found.slice((current-1)*10,current*10).forEach(function(r){r.hidden=false;});page.querySelector('[data-feed-empty]').hidden=found.length>0;page.querySelector('[data-feed-count]').textContent=found.length+' alimentation(s)';page.querySelector('[data-feed-pagination]').textContent=current+' / '+pages;page.querySelector('[data-feed-page="-1"]').disabled=current===1;page.querySelector('[data-feed-page="1"]').disabled=current===pages;}
search.addEventListener('input',function(){current=1;filter();});state.addEventListener('change',function(){current=1;filter();});page.querySelector('[data-feed-reset]').addEventListener('click',function(){search.value='';state.value='';current=1;filter();});page.querySelectorAll('[data-feed-page]').forEach(function(b){b.addEventListener('click',function(){current+=Number(b.dataset.feedPage);filter();});});filter();
var drawer=page.querySelector('[data-feed-drawer]'),returnFocus=null;
page.addEventListener('click',function(event){
var trigger=event.target.closest('[data-feed-detail-open]');
if(trigger){if(event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;var template=Array.from(page.querySelectorAll('[data-feed-detail]')).find(function(t){return t.dataset.feedDetail===trigger.dataset.feedDetailOpen;});if(!template)return;event.preventDefault();returnFocus=trigger;drawer.replaceChildren(template.content.cloneNode(true));drawer.showModal();drawer.querySelector('[data-feed-detail-close]').focus({preventScroll:true});}
if(event.target.closest('[data-feed-detail-close]'))drawer.close();
});
drawer.addEventListener('click',function(event){if(event.target===drawer){var rect=drawer.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)drawer.close();}});
drawer.addEventListener('close',function(){if(returnFocus&&returnFocus.isConnected)returnFocus.focus({preventScroll:true});});
var form=page.querySelector('[data-feed-form]');if(!form)return;
var kg=function(n){return Number(n).toLocaleString('fr-FR',{minimumFractionDigits:3,maximumFractionDigits:3})+' kg';};
function update(){var silo=form.elements.silo_id.selectedOptions[0],machine=form.elements.machine_id.selectedOptions[0],qty=form.elements.quantity_kg,stock=Number(silo.dataset.stock),context=form.querySelector('[data-feed-context]');qty.setCustomValidity('');form.elements.ended_at.setCustomValidity('');
if(silo.value&&Number(qty.value)>stock)qty.setCustomValidity('Stock insuffisant : '+kg(stock)+' disponibles dans ce silo.');
if(form.elements.ended_at.value&&form.elements.fed_at.value&&form.elements.ended_at.value<form.elements.fed_at.value)form.elements.ended_at.setCustomValidity('La fin doit être postérieure ou égale au début.');
context.hidden=!silo.value&&!machine.value;context.textContent=[silo.value?silo.dataset.product+' · Stock disponible : '+kg(stock):'',machine.value?'Site de production : '+machine.dataset.site:'',silo.value&&qty.value&&Number(qty.value)>0&&Number(qty.value)<=stock?'Stock après chargement : '+kg(stock-Number(qty.value)):''].filter(Boolean).join(' — ');
}
form.addEventListener('input',update);form.addEventListener('change',update);form.addEventListener('reset',function(){form.querySelector('[data-feed-error]').hidden=true;setTimeout(update,0);});update();
if(page.dataset.openCreate==='1'){page.dataset.openCreate='0';setTimeout(function(){page.querySelector('[data-workspace-modal-open="machineFeedModal"]').click();},0);}
};
})();
