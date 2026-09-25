(function(){'use strict';window.initLivestock=function(root){
 const panel=root.matches?.('[data-livestock-directory]')?root:root.querySelector('[data-livestock-directory]');if(!panel||panel.dataset.ready)return;panel.dataset.ready='1';
 const directory=panel.querySelector('[data-lot-directory]');
 if(directory){
 const cards=Array.from(directory.querySelectorAll('[data-lot-card]')),filters=Array.from(directory.querySelectorAll('[data-lot-filter]')),search=directory.querySelector('[data-lot-search]');let page=1;const size=12;
 const stateKey='livestock-lots:'+location.pathname+':'+panel.dataset.siteScope;
 function saveLots(){try{sessionStorage.setItem(stateKey,JSON.stringify({search:search.value,filters:filters.map(f=>f.value),page,view:directory.querySelector('[data-lot-view][aria-pressed=true]')?.dataset.lotView||'cards'}));}catch(e){}}
 try{const saved=JSON.parse(sessionStorage.getItem(stateKey)||'null');if(saved){search.value=saved.search||'';filters.forEach((f,i)=>f.value=saved.filters?.[i]||'');page=Math.max(1,Number(saved.page)||1);if(saved.view==='table'){directory.querySelector('[data-lot-cards]').hidden=true;directory.querySelector('[data-lot-table]').hidden=false;directory.querySelectorAll('[data-lot-view]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.lotView==='table')));}}}catch(e){}
 function renderLots(){const query=search.value.trim().toLocaleLowerCase('fr');const matches=cards.filter(card=>card.dataset.search.toLocaleLowerCase('fr').includes(query)&&filters.every(f=>!f.value||card.dataset[f.dataset.lotFilter]===f.value));const pages=Math.max(1,Math.ceil(matches.length/size));page=Math.min(page,pages);const shown=new Set(matches.slice((page-1)*size,page*size).map(c=>c.dataset.lotCard));cards.forEach(c=>c.hidden=!shown.has(c.dataset.lotCard));directory.querySelectorAll('[data-lot-row]').forEach(r=>r.hidden=!shown.has(r.dataset.lotRow));directory.querySelector('[data-lot-empty]').hidden=matches.length>0||cards.length===0;directory.querySelector('[data-lot-count]').textContent=matches.length+' lot(s) sur '+cards.length;directory.querySelector('[data-lot-page]').textContent='Page '+page+' / '+pages;directory.querySelector('[data-lot-prev]').disabled=page<=1;directory.querySelector('[data-lot-next]').disabled=page>=pages;saveLots();}
 filters.forEach(f=>f.addEventListener('change',()=>{page=1;renderLots();}));search.addEventListener('input',()=>{page=1;renderLots();});directory.querySelector('[data-lot-reset]').addEventListener('click',()=>{search.value='';filters.forEach(f=>f.value='');page=1;renderLots();});directory.querySelector('[data-lot-prev]').addEventListener('click',()=>{page--;renderLots();});directory.querySelector('[data-lot-next]').addEventListener('click',()=>{page++;renderLots();});directory.querySelectorAll('[data-lot-view]').forEach(button=>button.addEventListener('click',()=>{directory.querySelector('[data-lot-cards]').hidden=button.dataset.lotView!=='cards';directory.querySelector('[data-lot-table]').hidden=button.dataset.lotView!=='table';directory.querySelectorAll('[data-lot-view]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));saveLots();}));renderLots();
 }
 const batches=JSON.parse(panel.querySelector('[data-livestock-batches]').textContent);
 const daily=panel.querySelector('[data-livestock-space="daily"]');
 if(daily){
 const lots=JSON.parse(daily.querySelector('[data-daily-lots]').textContent),site=daily.querySelector('[data-daily-site]'),lot=daily.querySelector('[data-daily-lot]'),table=daily.querySelector('[data-daily-table]');
 const key='livestock-daily:'+panel.dataset.siteScope;
 let saved={};try{saved=JSON.parse(sessionStorage.getItem(key)||'{}');}catch(e){}
 if(saved.site&&Array.from(site.options).some(o=>o.value===saved.site))site.value=saved.site;
 function selectLot(){
 const b=lots.find(b=>String(b.id)===lot.value);
 daily.querySelector('[data-daily-history]').hidden=!b;
 daily.querySelector('[data-daily-actions]').hidden=!b||!b.can_update||b.status!=='active'||Number(b.current_heads)<=0;
 daily.querySelectorAll('[data-daily-action]').forEach(button=>{button.dataset.livestockBatch=b?String(b.id):'';button.hidden=button.dataset.dailyAction==='poultry'&&b?.category!=='poultry';});
 daily.querySelector('[data-daily-context]').textContent=b?b.species_name+' · '+b.facility_name+' · '+b.current_heads+' animaux vivants'+(b.status!=='active'?' · Lot clôturé : consultation uniquement':''):!site.value?'Sélectionnez un site et un lot pour consulter son suivi.':lot.options.length===1?'Aucun lot sur ce site. Créez un lot depuis Élevage par espèce.':'Choisissez le lot à suivre.';
 table.dataset.selectedBatch=b?String(b.id):'';
 table.dispatchEvent(new Event('daily-filter'));
 try{sessionStorage.setItem(key,JSON.stringify({site:site.value,lot:lot.value}));}catch(e){}
 }
 function selectSite(preselect){
 lot.replaceChildren(new Option('Choisir un lot',''));
 lots.filter(b=>String(b.site_id)===site.value).forEach(b=>lot.add(new Option(b.batch_number+' — '+b.species_name,String(b.id))));
 lot.disabled=!site.value||lot.options.length===1;
 if(preselect&&Array.from(lot.options).some(o=>o.value===preselect))lot.value=preselect;
 selectLot();
 }
 site.addEventListener('change',()=>selectSite(''));lot.addEventListener('change',selectLot);
 selectSite(saved.lot);
 }
 const returnPath='livestock'+(location.pathname.split('/livestock')[1]||'').replace(/\/$/,'');
 panel.querySelectorAll('form[method=post]').forEach(form=>{const field=document.createElement('input');field.type='hidden';field.name='_livestock_return';field.value=returnPath;form.appendChild(field);});
 panel.querySelectorAll('table').forEach(t=>{const w=document.createElement('div');w.className='table-responsive';t.before(w);w.append(t);});
 function sync(form){
 const site=form.elements.site_id;
 const category=form.elements.species_id?.selectedOptions[0]?.dataset.category;
 const expectedType={livestock:'housing',poultry:'poultry_house',fish:'pond'}[category];
 for(const name of ['batch_id','facility_id','weight_stock_id']){
 const field=form.elements[name];if(!field)continue;
 if(!Array.from(field.options).some(o=>o.value===''))field.insertBefore(new Option('Sélectionner une référence',''),field.firstChild);
 Array.from(field.options).forEach(option=>{if(!option.value)return;option.disabled=!site.value||option.dataset.site!==site.value||(name==='facility_id'&&(!expectedType||option.dataset.type!==expectedType))||(name==='weight_stock_id'&&option.dataset.batch!==form.elements.batch_id.value);option.hidden=option.disabled;});
 if(field.selectedOptions[0]?.disabled)field.value='';
 }
 const batch=batches.find(b=>String(b.id)===form.elements.batch_id?.value),context=form.querySelector('[data-livestock-context]');context.textContent=batch?batch.species_name+' · '+batch.facility_name+' · '+batch.current_heads+' têtes vivantes.':'';
 const kind=form.dataset.kind;
 if(kind==='batches'){
 const fish=category==='fish';
 [['initial_average_weight_kg',!!category&&!fish],['initial_total_weight_kg',fish]].forEach(([name,visible])=>{const field=form.elements[name];field.closest('label').hidden=!visible;field.disabled=!visible;if(!visible)field.value='';});
 form.elements.facility_id.closest('label').querySelector('span').textContent=fish?'Étang *':category==='poultry'?'Poulailler *':'Bâtiment d’élevage *';
 Array.from(form.elements.purpose.options).forEach(option=>{option.disabled=option.value==='laying'&&category!=='poultry';option.hidden=option.disabled;});
 if(form.elements.purpose.selectedOptions[0]?.disabled)form.elements.purpose.value='meat';
 context.textContent=!category?'Choisissez une espèce pour afficher les champs utiles.':fish?'Le poids demandé est le total des poissons introduits.':'Le poids demandé est celui d’un seul animal à l’entrée du lot.';
 }

 if(kind==='transfers'){const kg=form.elements.source_unit.value==='kg';['quantity_heads','weight_stock_id','quantity_kg'].forEach(name=>{const field=form.elements[name],active=name==='quantity_heads'?!kg:kg;field.closest('label').hidden=!active;field.disabled=!active;field.required=active;});}
 if(kind==='transfers'){
 const summary=form.querySelector('[data-transfer-summary]'),kg=form.elements.source_unit.value==='kg';
 if(summary){summary.hidden=!batch;if(batch){
 const num=n=>Number(n).toLocaleString('fr-FR',{maximumFractionDigits:3});
 summary.querySelector('[data-transfer-identity]').textContent=batch.species_name+' · '+batch.facility_name;
 summary.querySelector('[data-transfer-code]').textContent=batch.batch_number;
 summary.querySelector('[data-transfer-start]').textContent=batch.started_at?batch.started_at.slice(0,10).split('-').reverse().join('/'):'Non renseignée';
 summary.querySelector('[data-transfer-heads]').textContent=num(batch.current_heads)+' têtes';
 summary.querySelector('[data-transfer-average]').textContent=batch.current_average_weight_kg?num(batch.current_average_weight_kg)+' kg':'Non renseigné';
 const stocks=Array.from(form.elements.weight_stock_id.options).filter(o=>o.value&&!o.disabled);
 summary.querySelector('[data-transfer-availability]').textContent=kg?(stocks.length?stocks.length+' stock(s) pesé(s) sélectionnable(s) · '+num(stocks.reduce((total,o)=>total+Number(o.dataset.available||0),0))+' kg disponibles.':'Aucun stock pesé sélectionnable pour ce lot. Une conversion validée est nécessaire ; les stocks réservés ne sont pas proposés.'):'Transfert en animaux vivants. Le poids moyen est indicatif : il ne constitue pas un stock en kilogrammes.';
 if(batch.status!=='active')summary.querySelector('[data-transfer-availability]').textContent='Ce lot est inactif : le transfert ne peut pas être créé.';
 }}
 const stock=form.elements.weight_stock_id.selectedOptions[0];
 if(kg&&stock?.value)form.elements.quantity_kg.max=stock.dataset.available;else form.elements.quantity_kg.removeAttribute('max');
 const quantity=Number(kg?form.elements.quantity_kg.value:form.elements.quantity_heads.value);
 const limit=kg?Number(stock?.dataset.available||0):Number(batch?.current_heads||0);
 const quantityField=kg?form.elements.quantity_kg:form.elements.quantity_heads;
 quantityField.setCustomValidity(quantity>limit&&batch?'La quantité dépasse les '+limit.toLocaleString('fr-FR')+(kg?' kg disponibles.':' animaux vivants.'):'');
 const review=form.querySelector('[data-transfer-review]');
 if(review)review.textContent=batch&&quantity>0&&quantity<=limit&&batch.status==='active'?'À préparer : '+quantity.toLocaleString('fr-FR')+(kg?' kg':' animaux')+' · '+batch.species_name+' · '+batch.batch_number+' → Boucherie.':quantity>limit&&batch?'Quantité supérieure au disponible.':batch?.status&&batch.status!=='active'?'Choisissez un lot actif.':'Choisissez un lot et une quantité.';
 context.textContent=kg&&stock?.value?'Stock sélectionné : '+Number(stock.dataset.available).toLocaleString('fr-FR')+' kg disponibles.':'';
 }
 if(kind==='facilities'||kind==='facility-edit'){const field=form.elements.area_m2;field.required=form.elements.facility_type.value==='pond';field.closest('label').hidden=!field.required;}
 if(batch){['sample_heads','input_heads','quantity_heads'].forEach(n=>{if(form.elements[n])form.elements[n].max=batch.current_heads;});}
 if(kind==='conversions'){const gross=Number(form.elements.gross_weight_kg.value),tare=Number(form.elements.tare_weight_kg.value);form.elements.tare_weight_kg.setCustomValidity(tare<0||tare>=gross?'La tare doit être inférieure au poids brut.':'');const preview=form.querySelector('[data-conversion-net]');if(preview)preview.textContent=gross>0&&gross>tare?(gross-tare).toLocaleString('fr-FR',{maximumFractionDigits:3})+' kg':'—';}
 if(kind==='weighings'&&batch){const heads=Number(form.elements.sample_heads.value),weight=Number(form.elements.total_weight_kg.value);if(heads>0&&weight>0)context.textContent+=' Poids moyen : '+(weight/heads).toLocaleString('fr-FR',{maximumFractionDigits:3})+' kg / animal.';}
 if(kind==='poultry'&&batch){const out=Number(form.elements.mortality_heads.value)+Number(form.elements.sold_heads.value);context.textContent+=' Effectif après enregistrement : '+Math.max(0,Number(batch.current_heads)-out)+' volailles.';form.elements.sold_heads.setCustomValidity(out>Number(batch.current_heads)?'Le total des décès et ventes dépasse l’effectif vivant.':'');}
 const missing=Array.from(form.querySelectorAll('select[required]')).some(s=>!Array.from(s.options).some(o=>o.value&&!o.disabled));form.querySelector('[type=submit]').disabled=missing||(kind==='transfers'&&batch&&batch.status!=='active');if(missing&&kind==='batches')context.textContent=!site.value?'Sélectionnez un site.':!category?'Sélectionnez une espèce.':'Aucune unité compatible sur ce site. Créez '+(category==='fish'?'un étang':category==='poultry'?'un poulailler':'un bâtiment d’élevage')+' dans Bâtiments et étangs.';else if(missing&&kind==='transfers')context.textContent=!site.value?'Sélectionnez le site de départ.':!batch?'Sélectionnez un lot de ce site.':'Aucun stock pesé sélectionnable pour ce lot. Choisissez un autre lot ou vérifiez ses conversions et réservations.';else if(missing)context.textContent='Aucune référence disponible pour cette opération. Créez d’abord une unité et un lot sur le site concerné.';
 }
 function generateCode(form){
 const input=form.querySelector('[data-livestock-code]');if(!input)return;
 const prefix={housing:'BAT',poultry_house:'POUL',pond:'ETG'}[form.elements.facility_type.value]||'BAT';
 const date=new Date(),stamp=date.getFullYear()+String(date.getMonth()+1).padStart(2,'0')+String(date.getDate()).padStart(2,'0');
 const bytes=new Uint8Array(3);crypto.getRandomValues(bytes);
 input.value=prefix+'-'+stamp+'-'+Array.from(bytes,n=>n.toString(16).padStart(2,'0')).join('').toUpperCase();input.dataset.generated=input.value;
 }
 panel.querySelectorAll('[data-livestock-form]').forEach(f=>{const code=f.querySelector('[data-livestock-code]');if(code){f.addEventListener('reset',()=>queueMicrotask(()=>generateCode(f)));f.querySelector('[data-livestock-code-generate]').addEventListener('click',()=>{generateCode(f);code.focus();});f.elements.facility_type.addEventListener('change',()=>{const current=f.querySelector('[data-livestock-code]');if(current.value===current.dataset.generated)generateCode(f);});}
f.querySelectorAll('select[required]').forEach(select=>{if(!select.options.length){select.add(new Option('Aucune référence disponible',''));select.disabled=true;}});f.addEventListener('input',()=>sync(f));f.addEventListener('change',()=>sync(f));sync(f);});
 panel.addEventListener('click',e=>{const b=e.target.closest('[data-workspace-modal-open]');if(b)setTimeout(()=>{const f=panel.querySelector('#'+CSS.escape(b.dataset.workspaceModalOpen)+' form[data-livestock-form]');if(f){if(f.elements.species_id&&(b.dataset.livestockSpecies||panel.dataset.selectedSpecies))f.elements.species_id.value=b.dataset.livestockSpecies||panel.dataset.selectedSpecies;if(f.elements.batch_id&&b.dataset.livestockBatch){const selected=batches.find(x=>String(x.id)===b.dataset.livestockBatch);if(selected)f.elements.site_id.value=String(selected.site_id);f.elements.batch_id.value=b.dataset.livestockBatch;}f.querySelector('[data-feed-error]').hidden=true;sync(f);}},0);});
 const selected=new URLSearchParams(location.search).get('batch');
 if(selected&&batches.some(batch=>String(batch.id)===selected)){
 const kind=location.pathname.endsWith('/conversions')?'conversions':location.pathname.endsWith('/transfers')?'transfers':null;
 const trigger=kind?panel.querySelector('[data-workspace-modal-open="livestock-'+kind+'"]'):null;
 if(trigger){trigger.dataset.livestockBatch=selected;setTimeout(()=>trigger.click(),0);}
 }
};})();
