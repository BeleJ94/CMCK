(function(){'use strict';window.initFuelLogistics=function(root){
(root||document).querySelectorAll('.fuel-tabs').forEach(function(nav){if(nav.dataset.ready||!nav.querySelector('[data-fuel-tab]'))return;nav.dataset.ready='true';var tabs=Array.from(nav.querySelectorAll('[data-fuel-tab]')),workspace=nav.closest('.fuel-workspace');
function select(tab){tabs.forEach(function(t){var active=t===tab;t.setAttribute('aria-selected',String(active));t.tabIndex=active?0:-1;});workspace.querySelectorAll('[data-fuel-panel]').forEach(function(panel){panel.hidden=panel.dataset.fuelPanel!==tab.dataset.fuelTab;});}
tabs.forEach(function(tab,index){tab.addEventListener('click',function(){select(tab);});tab.addEventListener('keydown',function(e){var next;if(e.key==='ArrowRight')next=(index+1)%tabs.length;if(e.key==='ArrowLeft')next=(index+tabs.length-1)%tabs.length;if(e.key==='Home')next=0;if(e.key==='End')next=tabs.length-1;if(next!==undefined){e.preventDefault();select(tabs[next]);tabs[next].focus();}});});
});
(root||document).querySelectorAll('[data-fuel-form]').forEach(function(form){if(form.dataset.ready)return;form.dataset.ready='true';var error=form.querySelector('[data-fuel-error]'),button=form.querySelector('[type=submit]');
form.querySelectorAll('select').forEach(function(field){if(!field.options.length){field.add(new Option('Aucune référence disponible',''));field.disabled=true;button.disabled=true;}});
form.addEventListener('reset',function(){error.hidden=true;});
form.addEventListener('submit',async function(e){e.preventDefault();if(form.dataset.busy==='true'||!form.reportValidity())return;form.dataset.busy='true';button.disabled=true;error.hidden=true;
try{var response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});if(!(response.headers.get('content-type')||'').includes('application/json'))throw new Error('Réponse non confirmée. Vérifiez votre session et le résultat avant de réessayer.');var result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||'Opération refusée.');location.assign(result.redirect);}
catch(e){error.textContent=e.message;error.hidden=false;error.scrollIntoView({block:'nearest'});form.dataset.busy='false';button.disabled=false;}});
});};})();
