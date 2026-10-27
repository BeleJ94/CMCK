(function(){'use strict';
let previousDirectory=null;
window.initPorcineFlow=function(root){
 const panel=root.matches?.('[data-flow-directory]')?root:root.querySelector('[data-flow-directory]');if(!panel||panel.dataset.ready)return;panel.dataset.ready='1';
 const normalize=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('fr');
 const articleScope=panel.querySelector('[data-flow-article]');
 if(articleScope){
  const cards=Array.from(panel.querySelectorAll('[data-cold-card]'));
  const articleSearch=panel.querySelector('[data-cold-search]'),category=panel.querySelector('[data-cold-kind]');
  const storageKey='porcine-flow-filters:'+panel.dataset.siteScope+':'+panel.dataset.pageScope;
  try{const saved=JSON.parse(sessionStorage.getItem(storageKey)||'{}');articleSearch.value=saved.search||'';category.value=saved.kind||'';}catch(_){}
  const url=new URL(window.location.href);
  if(url.searchParams.has('q')||url.searchParams.has('kind')){articleSearch.value=url.searchParams.get('q')||'';category.value=url.searchParams.get('kind')||'';}
  function filterArticles(){
   const query=normalize(articleSearch.value.trim());
   cards.forEach(card=>{card.hidden=Boolean(category.value&&card.dataset.kind!==category.value)||!normalize(card.dataset.search).includes(query);});
   const count=cards.filter(card=>!card.hidden).length;
   panel.querySelector('[data-cold-count]').textContent=count+' article(s) sur '+cards.length;
   panel.querySelector('[data-cold-empty]').hidden=count>0||cards.length===0;
   panel.querySelectorAll('[data-cold-category]').forEach(section=>{
    const visible=cards.filter(card=>card.dataset.kind===section.dataset.coldCategory&&!card.hidden);
    section.hidden=visible.length===0;
    const weight=visible.reduce((total,card)=>total+Number(card.querySelector('[data-cold-physical]').dataset.coldPhysical),0);
    section.querySelector('[data-cold-category-summary]').textContent=visible.length+' article(s) · '+weight.toLocaleString('fr-FR',{minimumFractionDigits:3,maximumFractionDigits:3})+' kg au froid';
   });
   try{sessionStorage.setItem(storageKey,JSON.stringify({search:articleSearch.value,kind:category.value}));}catch(_){}
   const next=new URL(window.location.href);for(const [key,value] of [['q',articleSearch.value.trim()],['kind',category.value]]){if(value)next.searchParams.set(key,value);else next.searchParams.delete(key);}next.searchParams.delete('status');window.history.replaceState(window.history.state,'',next);
  }
  articleSearch.addEventListener('input',filterArticles);category.addEventListener('change',filterArticles);
  panel.querySelector('[data-cold-reset]').addEventListener('click',()=>{articleSearch.value='';category.value='';filterArticles();articleSearch.focus();});
  filterArticles();
 }
 const search=panel.querySelector('[data-flow-search]'),state=panel.querySelector('[data-flow-state]'),kind=panel.querySelector('[data-flow-kind]');
 if(search&&state){
  const items=Array.from(panel.querySelectorAll('[data-flow-item]'));
  const pageSize=panel.querySelector('[data-flow-page-size]');
  let pageNumber=1,sortKey='date',sortDirection=panel.dataset.pageScope==='butchery-cold-room'?1:-1;
  const receiptPage=panel.dataset.pageScope==='butchery-receipts';
  const storageKey='porcine-flow-filters:'+panel.dataset.siteScope+':'+panel.dataset.pageScope+(articleScope?':lots':'');
  const snapshot=new Map(items.map(item=>[item.dataset.flowKey,item.textContent.replace(/\s+/g,' ').trim()]));
  const changed=new Set(previousDirectory?.scope===storageKey?items.filter(item=>previousDirectory.rows.get(item.dataset.flowKey)!==snapshot.get(item.dataset.flowKey)).map(item=>item.dataset.flowKey):[]);
  previousDirectory={scope:storageKey,rows:snapshot};
  const location=new URL(window.location.href);
  try{const saved=JSON.parse(sessionStorage.getItem(storageKey)||'{}');search.value=saved.search||'';state.value=saved.status||'';if(kind)kind.value=saved.kind||'';if(receiptPage&&!state.value&&['incoming','pending'].includes(saved.kind))state.value=saved.kind;}catch(_){}
  function readUrl(url){search.value=url.searchParams.get('q')||'';state.value=url.searchParams.get('status')||(receiptPage&&['incoming','pending'].includes(url.searchParams.get('kind'))?url.searchParams.get('kind'):'');if(kind)kind.value=url.searchParams.get('kind')||'';}
  if(!articleScope&&['q','status','kind'].some(key=>location.searchParams.has(key)))readUrl(location);
  function render(updateUrl=false){
   const query=normalize(search.value.trim()),selectedKind=kind?.value||'';let count=0;
   const scoped=articleScope?items.filter(item=>item.dataset.article===articleScope.value):items;
   items.forEach(item=>{const states=(item.dataset.state||'').split(' ');item.hidden=Boolean((articleScope&&item.dataset.article!==articleScope.value)||(state.value&&!states.includes(state.value))||(selectedKind&&item.dataset.kind!==selectedKind)||!normalize(item.dataset.search).includes(query));if(!item.hidden)count++;});
   const matched=items.filter(item=>!item.hidden);
   if(pageSize){
    const compare=new Intl.Collator('fr',{numeric:true,sensitivity:'base'});
    matched.sort((a,b)=>sortDirection*(['heads','weight','input','output','loss','physical','reserved','available'].includes(sortKey)?Number(a.dataset[sortKey])-Number(b.dataset[sortKey]):compare.compare(a.dataset[sortKey],b.dataset[sortKey])));
    const size=Number(pageSize.value),pages=Math.max(1,Math.ceil(count/size));pageNumber=Math.min(pageNumber,pages);
    const body=panel.querySelector('[data-flow-table-body]');
    matched.forEach((item,index)=>{body.appendChild(item);item.hidden=index<(pageNumber-1)*size||index>=pageNumber*size;});
    panel.querySelector('[data-flow-page-info]').textContent=count?((pageNumber-1)*size+1)+'–'+Math.min(pageNumber*size,count)+' sur '+count+(receiptPage?' réception(s)':panel.dataset.pageScope==='farm-shipments'?' élément(s)':panel.dataset.pageScope==='butchery-production'?' transformation(s)':panel.dataset.pageScope==='butchery-cold-room'?' lot(s)':' abattage(s)'):'0 résultat';
    panel.querySelector('[data-flow-page-label]').textContent='Page '+pageNumber+' / '+pages;
    panel.querySelector('[data-flow-page="-1"]').disabled=pageNumber===1;
    panel.querySelector('[data-flow-page="1"]').disabled=pageNumber===pages;
    panel.querySelectorAll('[data-flow-sort]').forEach(button=>button.closest('th').setAttribute('aria-sort',button.dataset.flowSort===sortKey?(sortDirection===1?'ascending':'descending'):'none'));
   }
   const active=Number(Boolean(query))+Number(Boolean(state.value))+Number(Boolean(selectedKind));
   panel.querySelector('[data-flow-count]').textContent=count+(articleScope?' lot(s) sur ':' élément(s) sur ')+scoped.length;
   panel.querySelector('[data-flow-filter-count]').textContent=active?active+' filtre(s) actif(s)':articleScope?'Tous les lots de cet article':'Tout le périmètre';
   panel.querySelector('[data-flow-filter-empty]').hidden=count>0||(!active&&scoped.length===0);
   panel.querySelector('[data-flow-filter-update]').hidden=!scoped.some(item=>!matched.includes(item)&&changed.has(item.dataset.flowKey));
   panel.querySelectorAll('[data-flow-heading-kind],[data-flow-kind-container]').forEach(section=>{const category=section.dataset.flowHeadingKind||section.dataset.flowKindContainer;section.hidden=Boolean(active&&!items.some(item=>item.dataset.kind===category&&!item.hidden));});
   panel.querySelectorAll('[data-flow-filter-link]').forEach(link=>{const target=new URL(link.href);const same=target.pathname===window.location.pathname;const selected=same&&target.searchParams.get('status')=== (state.value||null)&&target.searchParams.get('kind')===(selectedKind||null)&&!query;link.classList.toggle('is-active',selected);});
   try{sessionStorage.setItem(storageKey,JSON.stringify({search:search.value,status:state.value,kind:selectedKind}));}catch(_){}
   if(updateUrl&&!articleScope){const url=new URL(window.location.href);for(const [key,value] of [['q',search.value.trim()],['status',state.value],['kind',selectedKind]]){if(value)url.searchParams.set(key,value);else url.searchParams.delete(key);}window.history.replaceState(window.history.state,'',url);}
  }
  search.addEventListener('input',()=>{pageNumber=1;render(true);});state.addEventListener('change',()=>{pageNumber=1;render(true);});kind?.addEventListener('change',()=>{pageNumber=1;render(true);});
  function resetFilters(){pageNumber=1;search.value='';state.value='';if(kind)kind.value='';render(true);search.focus();}
  panel.querySelector('[data-flow-reset]').addEventListener('click',resetFilters);
  panel.querySelector('[data-flow-reveal-results]').addEventListener('click',resetFilters);
  panel.querySelectorAll('[data-flow-filter-link]').forEach(link=>link.addEventListener('click',event=>{if(event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;const target=new URL(link.href);if(target.pathname!==window.location.pathname)return;event.preventDefault();pageNumber=1;readUrl(target);render(true);search.focus({preventScroll:true});}));
  pageSize?.addEventListener('change',()=>{pageNumber=1;render();});
  panel.querySelectorAll('[data-flow-page]').forEach(button=>button.addEventListener('click',()=>{pageNumber+=Number(button.dataset.flowPage);render();}));
  panel.querySelectorAll('[data-flow-sort]').forEach(button=>button.addEventListener('click',()=>{sortDirection=sortKey===button.dataset.flowSort?-sortDirection:1;sortKey=button.dataset.flowSort;pageNumber=1;render();}));
  if(articleScope){
   panel.querySelector('#cold-room-article-detail').addEventListener('workspace:opened',event=>{
    const modal=event.currentTarget;
    if(modal.dataset.resume==='1'){delete modal.dataset.resume;render();return;}
    const trigger=event.detail.trigger,card=trigger?.closest('[data-cold-card]');if(!card)return;
    articleScope.value=trigger.dataset.coldArticle;
    modal.querySelector('[data-cold-article-title]').textContent=card.dataset.articleName;
    modal.querySelector('[data-cold-article-summary]').textContent=trigger.dataset.coldSummary;
    search.value='';state.value='';pageSize.value='10';pageNumber=1;sortKey='date';sortDirection=1;render();
   });
  }
  render(true);
 }
 const kpiDialog=panel.querySelector('#flow-kpi-detail');
 if(kpiDialog){
  const records=Array.from(kpiDialog.querySelectorAll('[data-kpi-record]'));
  const kpiSearch=kpiDialog.querySelector('[data-kpi-search]'),size=kpiDialog.querySelector('[data-kpi-size]');
  let category='',currentPage=1,sort='date',direction=-1;
  function renderKpi(){
   const scope=records.filter(row=>row.dataset.category===category);
   const found=scope.filter(row=>normalize(row.dataset.search).includes(normalize(kpiSearch.value.trim())));
   const compare=new Intl.Collator('fr',{numeric:true,sensitivity:'base'});
   found.sort((a,b)=>direction*(sort==='heads'||sort==='weight'?Number(a.dataset[sort])-Number(b.dataset[sort]):compare.compare(a.dataset[sort],b.dataset[sort])));
   const pages=Math.max(1,Math.ceil(found.length/Number(size.value)));currentPage=Math.min(currentPage,pages);
   records.forEach(row=>row.hidden=true);
   const body=kpiDialog.querySelector('tbody');found.forEach((row,index)=>{body.appendChild(row);row.hidden=index<(currentPage-1)*Number(size.value)||index>=currentPage*Number(size.value);});
   kpiDialog.querySelector('[data-kpi-total]').textContent=scope.length+' opération(s) · '+scope.reduce((sum,row)=>sum+Number(row.dataset.weight),0).toLocaleString('fr-FR',{maximumFractionDigits:3})+' kg';
   kpiDialog.querySelector('[data-kpi-empty]').hidden=found.length>0;
   kpiDialog.querySelector('[data-kpi-count]').textContent=found.length?((currentPage-1)*Number(size.value)+1)+'–'+Math.min(currentPage*Number(size.value),found.length)+' sur '+found.length:'0 résultat';
   kpiDialog.querySelector('[data-kpi-page-label]').textContent='Page '+currentPage+' / '+pages;
   kpiDialog.querySelector('[data-kpi-page="-1"]').disabled=currentPage===1;
   kpiDialog.querySelector('[data-kpi-page="1"]').disabled=currentPage===pages;
   kpiDialog.querySelectorAll('[data-kpi-sort]').forEach(button=>button.closest('th').setAttribute('aria-sort',button.dataset.kpiSort===sort?(direction===1?'ascending':'descending'):'none'));
  }
  panel.querySelectorAll('[data-flow-kpi]').forEach(button=>button.addEventListener('click',()=>{
   category=button.dataset.flowKpi;currentPage=1;kpiSearch.value='';size.value='10';sort='date';direction=-1;
   kpiDialog.querySelector('#flow-kpi-title').textContent=button.querySelector('.ui-kpi__label').textContent;renderKpi();
  }));
  kpiSearch.addEventListener('input',()=>{currentPage=1;renderKpi();});size.addEventListener('change',()=>{currentPage=1;renderKpi();});
  kpiDialog.querySelectorAll('[data-kpi-page]').forEach(button=>button.addEventListener('click',()=>{currentPage+=Number(button.dataset.kpiPage);renderKpi();}));
  kpiDialog.querySelectorAll('[data-kpi-sort]').forEach(button=>button.addEventListener('click',()=>{direction=sort===button.dataset.kpiSort?-direction:1;sort=button.dataset.kpiSort;currentPage=1;renderKpi();}));
 }
 panel.querySelectorAll('[data-production-actions],[data-flow-actions]').forEach(menu=>{
  const toggle=panel.querySelector('#'+menu.dataset.toggleId);
  function positionMenu(height){const rect=toggle.getBoundingClientRect(),width=menu.offsetWidth||190;menu.style.left=Math.max(8,Math.min(innerWidth-width-8,rect.right-width))+'px';menu.style.top=Math.max(8,rect.bottom+height+8<=innerHeight?rect.bottom+6:rect.top-height-6)+'px';}
  menu.addEventListener('beforetoggle',event=>{toggle.setAttribute('aria-expanded',String(event.newState==='open'));if(event.newState==='open')positionMenu(menu.querySelectorAll('button').length*50+12);});
  menu.addEventListener('toggle',event=>{
   const open=event.newState==='open';toggle.setAttribute('aria-expanded',String(open));
   if(open){positionMenu(menu.getBoundingClientRect().height);menu.querySelector('button')?.focus({preventScroll:true});}
  });
  menu.addEventListener('click',event=>{const button=event.target.closest('[data-workspace-modal-open]');if(!button)return;button.dataset.workspaceReturnFocus=toggle.id;menu.hidePopover();});
  menu.addEventListener('keydown',event=>{if(!['ArrowDown','ArrowUp','Home','End'].includes(event.key))return;event.preventDefault();const choices=Array.from(menu.querySelectorAll('button'));let index=choices.indexOf(document.activeElement);index=event.key==='Home'?0:event.key==='End'?choices.length-1:(index+(event.key==='ArrowDown'?1:-1)+choices.length)%choices.length;choices[index]?.focus();});
 });
 panel.addEventListener('keydown',event=>{
  if(event.key!=='Escape')return;
  const menu=panel.querySelector('[data-flow-actions]:popover-open,[data-production-actions]:popover-open');if(!menu)return;
  event.preventDefault();event.stopPropagation();menu.hidePopover();panel.querySelector('#'+menu.dataset.toggleId)?.focus({preventScroll:true});
 },true);
 if(panel.dataset.pageScope==='butchery-cold-room'){
  const articleModal=panel.querySelector('#cold-room-article-detail');
  if(articleModal){
   panel.querySelectorAll('.cold-room-detail,#flow-supply').forEach(modal=>{
    modal.addEventListener('workspace:opened',event=>{
     const trigger=event.detail.trigger;
     modal.dataset.coldReturnArticle=trigger?.closest('#cold-room-article-detail')?articleScope.value:'';
     modal.dataset.coldReturnFocus=trigger?.dataset.workspaceReturnFocus||'';
    });
    modal.addEventListener('workspace:closed',()=>{
     if(!document.contains(panel)||!modal.dataset.coldReturnArticle)return;
     const trigger=panel.querySelector('[data-cold-article="'+modal.dataset.coldReturnArticle+'"]');if(!trigger)return;
     articleModal.dataset.resume='1';trigger.click();
     const returnFocus=modal.dataset.coldReturnFocus?panel.querySelector('#'+modal.dataset.coldReturnFocus):null;
     if(returnFocus)setTimeout(()=>{if(articleModal.classList.contains('is-open'))returnFocus.focus({preventScroll:true});},0);
    });
   });
  }
  panel.querySelector('#flow-supply')?.addEventListener('workspace:opened',event=>{
   // The shared modal has reset the form; apply the row selection before interaction.
   const form=event.currentTarget.querySelector('form');if(!form)return;
   form.elements.finished_lot_id.value=event.detail.trigger?.dataset.coldSupply||'';
   form.elements.quantity_kg.value='';
   form.elements.finished_lot_id.dispatchEvent(new Event('change',{bubbles:true}));
  });
 }
 const btrPreview=panel.querySelector('#flow-shipment-btr');
 if(btrPreview){
  const frame=btrPreview.querySelector('[data-btr-frame]'),print=btrPreview.querySelector('[data-btr-print]');
  const loading=btrPreview.querySelector('[data-btr-loading]'),error=btrPreview.querySelector('[data-btr-error]');
  panel.querySelectorAll('[data-btr-preview]').forEach(trigger=>trigger.addEventListener('click',()=>{
   btrPreview.querySelector('[data-btr-preview-reference]').textContent=trigger.dataset.btrReference;
   const url=new URL(trigger.dataset.btrUrl,window.location.href);
   btrPreview.querySelector('[data-btr-open]').href=url.href;
   const pdf=new URL(url);pdf.searchParams.set('export','pdf');btrPreview.querySelector('[data-btr-download]').href=pdf.href;
   print.disabled=true;frame.hidden=true;loading.hidden=false;error.hidden=true;btrPreview.setAttribute('aria-busy','true');frame.src=url.href;
  }));
  frame.addEventListener('load',()=>{
   let ready=false;try{ready=Boolean(frame.contentDocument?.querySelector('.shipment-btr-document'));}catch(_){}
   loading.hidden=true;error.hidden=ready;frame.hidden=!ready;print.disabled=!ready;btrPreview.setAttribute('aria-busy','false');
   if(ready)frame.contentDocument.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();btrPreview.querySelector('[data-workspace-modal-close]').click();}});
  });
  frame.addEventListener('error',()=>{loading.hidden=true;error.hidden=false;print.disabled=true;btrPreview.setAttribute('aria-busy','false');});
  print.addEventListener('click',()=>{if(!print.disabled){frame.contentWindow.focus();frame.contentWindow.print();}});
 }
 function initSourceSearch(root){
  root.querySelectorAll('select[name="batch_id"],select[name="weight_stock_id"],select[name="raw_lot_id"],select[name="counter_stock_id"],select[name="finished_lot_id"],select[data-flow-product-select]').forEach(select=>{
   if(select.closest('[data-slaughter-lot-picker],[data-transformation-form]')||select.dataset.searchReady||select.options.length<9)return;
   select.dataset.searchReady='1';
   const label=select.closest('label');if(!label)return;
   const searchLabel=document.createElement('label');searchLabel.className=label.className;
   const title=document.createElement('span');title.textContent='Rechercher dans les choix';
   const input=document.createElement('input');input.type='search';input.placeholder='Saisir un lot, un produit ou un comptoir…';input.autocomplete='off';
   const feedback=document.createElement('small');feedback.setAttribute('aria-live','polite');
   searchLabel.append(title,input,feedback);label.before(searchLabel);
   function filterOptions(){const query=normalize(input.value.trim());let matches=0;Array.from(select.options).forEach(option=>{if(!option.value)return;const match=normalize(option.textContent).includes(query);option.hidden=!match&&!option.selected;if(match&&!option.disabled)matches++;});feedback.textContent=query?matches+' choix correspondant(s)':'Les choix restent disponibles dans la liste ci-dessous.';}
   input.addEventListener('input',filterOptions);select.addEventListener('change',filterOptions);select.form.addEventListener('reset',()=>queueMicrotask(()=>{input.value='';filterOptions();}));filterOptions();
  });
 }
 const fr=(grams,digits=3)=>(grams/1000).toLocaleString('fr-FR',{maximumFractionDigits:digits});
 // Kilos following pieces taken from a stock: proportional in grams, the last pieces take the remainder (as on the server).
 const shareGrams=(grams,pieces,taken)=>taken>=pieces?grams:Math.floor(grams*taken/pieces);
 function syncTransformation(form){
  const area=form.querySelector('[data-transformation-form]');if(!area)return;
  const source=form.elements.source_item_id,quantity=form.elements.quantity_kg,day=form.elements.production_date.value;
  // Carcasses counted in pieces (poultry) leave by pieces: their kilos come from the lots.
  const counted=source.selectedOptions[0]?.dataset.pieces==='1',piecesInput=form.elements.quantity_pieces,piecesField=form.querySelector('[data-transform-pieces]');
  piecesField.hidden=!counted;piecesInput.disabled=!counted;piecesInput.required=counted;quantity.readOnly=counted;quantity.required=!counted;
  form.querySelector('[data-transform-kg-label]').textContent=counted?'Poids correspondant (kg)':'Quantité sortie (kg) *';
  const manual=form.elements.lot_mode.value==='manual';
  const lots=Array.from(form.querySelectorAll('[data-transform-lot]'));
  const eligible=lots.filter(lot=>lot.dataset.item===source.value&&lot.dataset.production<=day);
  const used=lot=>!manual||lot.querySelector('input').checked;
  let available=0,availablePieces=0;
  lots.forEach(lot=>{const check=lot.querySelector('input');const matches=eligible.includes(lot);lot.hidden=!matches;check.disabled=!matches||!manual;lot.querySelector('[data-transform-take]').textContent='';if(matches&&used(lot)){available+=Math.round(Number(lot.dataset.quantity)*1000);availablePieces+=Number(lot.dataset.pieces||0);}});
  let requested;
  if(counted){
   let remaining=Math.max(0,Math.floor(Number(piecesInput.value||0))),grams=0;
   eligible.forEach(lot=>{const free=Number(lot.dataset.pieces||0),lotGrams=Math.round(Number(lot.dataset.quantity)*1000),take=used(lot)?Math.min(remaining,free):0,taken=take?shareGrams(lotGrams,free,take):0;remaining-=take;grams+=taken;lot.querySelector('[data-transform-take]').textContent=take?'Prélèvement proposé : '+take+' pièce(s), '+fr(taken)+' kg':'Aucun prélèvement proposé';});
   requested=grams;quantity.value=grams?(grams/1000).toFixed(3):'';piecesInput.max=String(availablePieces);quantity.removeAttribute('max');
   piecesInput.setCustomValidity(remaining>0?'Pièces disponibles insuffisantes dans les lots choisis.':'');
  }else{
   requested=Math.max(0,Math.round(Number(quantity.value||0)*1000));let remaining=requested;
   eligible.forEach(lot=>{const take=used(lot)?Math.min(remaining,Math.round(Number(lot.dataset.quantity)*1000)):0;remaining-=take;lot.querySelector('[data-transform-take]').textContent=take?'Prélèvement proposé : '+fr(take)+' kg':'Aucun prélèvement proposé';});
   quantity.max=String(available/1000);
  }
  if(source.value&&manual&&!eligible.some(lot=>lot.querySelector('input').checked))(counted?piecesInput:quantity).setCustomValidity('Choisissez au moins un lot ou revenez au choix automatique.');
  form.querySelector('[data-transform-available]').textContent=source.value?(counted?availablePieces.toLocaleString('fr-FR')+' pièce(s), ':'')+fr(available)+' kg disponibles'+(manual?' dans les lots cochés.':' · Lots choisis automatiquement par DLC la plus proche.'):'Choisissez le produit. Les lots seront proposés automatiquement, par DLC la plus proche.';
  const distribute=form.querySelector('[data-transform-distribute]').checked;
  form.querySelector('[data-transform-destinations]').hidden=!distribute;
  form.elements.counter_id.disabled=!distribute;form.elements.counter_id.required=distribute;
  const rows=Array.from(form.querySelectorAll('[data-flow-output]'));let total=0,counter=0;
  rows.forEach(row=>{
   const obtained=row.querySelector('[data-output-quantity]'),product=row.querySelector('[data-flow-product-select]');
   // Whole birds and other products counted in pieces: pieces obtained, and pieces sent to the counter.
   const tracked=product.selectedOptions[0]?.dataset.pieces==='1',piecesLabel=row.querySelector('[data-output-pieces]'),pieces=piecesLabel.querySelector('input');
   piecesLabel.hidden=!tracked;pieces.disabled=!tracked;pieces.required=tracked;
   const allocation=form.querySelector('[data-output-allocation="'+row.dataset.outputIndex+'"]');
   form.querySelector('[data-transform-allocations]').appendChild(allocation);
   const toCounter=allocation.querySelector('[data-output-counter]'),toCounterPieces=allocation.querySelector('[data-output-counter-pieces]');
   toCounter.hidden=tracked;toCounter.disabled=!distribute||tracked;toCounter.required=distribute&&!tracked;toCounter.max=obtained.value||'0';
   toCounterPieces.hidden=!tracked;toCounterPieces.disabled=!distribute||!tracked;toCounterPieces.required=distribute&&tracked;toCounterPieces.max=pieces.value||'0';
   allocation.querySelector('[data-allocation-label]').textContent=(product.value?product.selectedOptions[0].textContent:'Produit à choisir')+(tracked?' · Au comptoir (pièces)':' · Au comptoir (kg)');
   const outputGrams=Math.round(Number(obtained.value||0)*1000),outputPieces=Math.floor(Number(pieces.value||0));
   const counterPieces=distribute&&tracked?Math.min(outputPieces,Math.floor(Number(toCounterPieces.value||0))):0;
   const counterGrams=!distribute?0:(tracked?(outputPieces?shareGrams(outputGrams,outputPieces,counterPieces):0):Math.round(Number(toCounter.value||0)*1000));
   allocation.querySelector('[data-allocation-help]').textContent=tracked?'Au froid : '+Math.max(0,outputPieces-counterPieces).toLocaleString('fr-FR')+' pièce(s) sur '+outputPieces.toLocaleString('fr-FR')+', soit '+fr(Math.max(0,outputGrams-counterGrams))+' kg.':'Au froid : '+fr(Math.max(0,outputGrams-counterGrams))+' kg sur '+fr(outputGrams)+' kg obtenus.';
   total+=outputGrams;counter+=counterGrams;
   row.querySelector('[data-flow-remove-output]').disabled=rows.length===1;
  });
  if(total>requested)(counted?piecesInput:quantity).setCustomValidity('Le poids des produits dépasse la quantité sortie.');
  const gap=Math.max(0,requested-total),balance=form.querySelector('[data-flow-balance]');
  balance.textContent=(counted?Math.floor(Number(piecesInput.value||0)).toLocaleString('fr-FR')+' pièce(s), ':'')+fr(requested)+' kg sortis → '+fr(total)+' kg obtenus → '+fr(gap)+' kg '+(gap?'d’écart à confirmer':'de pertes')+'. Au froid : '+fr(Math.max(0,total-counter))+' kg · Au comptoir : '+fr(counter)+' kg.';
  balance.classList.toggle('flow-invalid',total>requested);
  const loss=form.elements.loss_confirmed,hasGap=gap>0;
  const signature=JSON.stringify([source.value,quantity.value,counted?piecesInput.value:'',day,manual,eligible.filter(used).map(lot=>lot.querySelector('input').value),rows.map(row=>[row.querySelector('select').value,row.querySelector('[data-output-quantity]').value,row.querySelector('[data-output-pieces] input').value,row.querySelector('[type=date]').value]),counter]);
  if(area.dataset.lossSignature!==signature)loss.checked=false;area.dataset.lossSignature=signature;
  loss.required=hasGap;loss.disabled=!hasGap;form.querySelector('[data-transform-loss]').hidden=!hasGap;
  form.querySelector('[data-flow-add-output]').disabled=rows.length>=30;
 }
 panel.querySelectorAll('[data-flow-form]').forEach(form=>{
  let next=1;
  function sync(){
   const source=form.elements.batch_id||form.elements.weight_stock_id||form.elements.raw_lot_id||form.elements.counter_stock_id||form.elements.finished_lot_id;
   const max=source?.selectedOptions[0]?.dataset.max;const quantity=form.elements.heads||form.elements.quantity_kg;
   if(quantity){quantity.setCustomValidity('');if(max)quantity.max=max;else quantity.removeAttribute('max');}
   if(form.elements.quantity_pieces)form.elements.quantity_pieces.setCustomValidity('');
   // Lots counted in pieces (poultry) move by pieces; their kilos follow on the server.
   const pieceField=form.querySelector('[data-flow-pieces]'),counted=Boolean(pieceField&&source?.selectedOptions[0]?.dataset.pieces);
   if(pieceField){const input=pieceField.querySelector('input');pieceField.hidden=!counted;input.disabled=!counted;input.required=counted;if(counted)input.max=source.selectedOptions[0].dataset.pieces;form.querySelector('[data-flow-kg]').hidden=counted;quantity.disabled=counted;quantity.required=!counted;}
   const preview=form.querySelector('[data-flow-preview]');if(preview&&counted){const available=Number(source.selectedOptions[0].dataset.pieces),q=Number(form.elements.quantity_pieces.value||0);preview.textContent='Disponible : '+available.toLocaleString('fr-FR')+' pièce(s), '+Number(max).toLocaleString('fr-FR')+' kg'+(q>0?' · Reste prévu : '+Math.max(0,available-q).toLocaleString('fr-FR')+' pièce(s). Les kilos suivent les pièces.':'');}else if(preview){const q=Number(quantity?.value||0);const price=Number(form.elements.unit_price?.value||0);preview.textContent=max?'Disponible : '+Number(max).toLocaleString('fr-FR')+(form.elements.heads?' porcs':' kg')+(q>0?' · Reste prévu : '+Math.max(0,Number(max)-q).toLocaleString('fr-FR'):'')+(price>0&&q>0?' · Total de la vente : '+(q*price).toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2}):''):'Choisissez le lot ou le produit concerné.';}
   const balance=form.querySelector('[data-flow-balance]');if(balance&&!form.querySelector('[data-transformation-form]')){
    const rows=Array.from(form.querySelectorAll('[data-flow-output]'));
    const cold=rows.reduce((n,r)=>n+Number(r.querySelector('[data-output-cold]').value||0),0),counter=rows.reduce((n,r)=>n+Number(r.querySelector('[data-output-counter]').value||0),0),input=Number(form.elements.quantity_kg.value||0),total=cold+counter;
    form.elements.counter_id.required=counter>0;
    if(quantity)quantity.setCustomValidity(total>input?'Le poids des produits dépasse la quantité de carcasse consommée.':'');
    balance.textContent='Produits : '+total.toLocaleString('fr-FR',{maximumFractionDigits:3})+' kg · Au froid : '+cold.toLocaleString('fr-FR')+' kg · Au comptoir : '+counter.toLocaleString('fr-FR')+' kg · Pertes : '+Math.max(0,input-total).toLocaleString('fr-FR',{maximumFractionDigits:3})+' kg';
    balance.classList.toggle('flow-invalid',total>input);
    rows.forEach(row=>{row.querySelector('[data-flow-remove-output]').disabled=rows.length===1;});
   }
   syncTransformation(form);
   const receiptControl=form.querySelector('[data-receipt-control]');let controlsReady=true;
   if(receiptControl){
    const checks=Array.from(receiptControl.querySelectorAll('.receipt-control-check input[type=checkbox]'));
    const completed=checks.filter(check=>check.checked).length;controlsReady=completed===checks.length;
    checks.forEach(check=>check.closest('.receipt-control-check').classList.toggle('is-checked',check.checked));
    const progress=receiptControl.querySelector('[data-receipt-control-progress]');progress.textContent=completed+' / '+checks.length+' contrôles confirmés';progress.classList.toggle('is-complete',controlsReady);
   }
   const missing=Array.from(form.querySelectorAll('select[required]')).some(s=>!Array.from(s.options).some(o=>o.value&&!o.disabled));const submit=form.querySelector('[type=submit]');if(submit)submit.disabled=missing||!controlsReady||form.querySelector('[data-weighing-locked]')!==null;
  }
  form.querySelector('[data-flow-add-output]')?.addEventListener('click',()=>{const template=form.querySelector('[data-flow-output-template]');const wrap=form.querySelector('[data-flow-outputs]');wrap.insertAdjacentHTML('beforeend',template.innerHTML.replaceAll('__INDEX__',String(next++)));initSourceSearch(wrap);sync();});
  form.addEventListener('click',event=>{const remove=event.target.closest('[data-flow-remove-output]');if(remove){const row=remove.closest('[data-flow-output]');form.querySelector('[data-output-allocation="'+row.dataset.outputIndex+'"]')?.remove();row.remove();sync();}});
  const picker=form.querySelector('[data-slaughter-lot-picker]');
  if(picker){
   const select=form.elements.batch_id, input=picker.querySelector('[data-slaughter-lot-search]');
   const choices=Array.from(picker.querySelectorAll('[data-slaughter-choice]'));
   picker.querySelector('[data-slaughter-fallback]').hidden=true;
   picker.querySelector('[data-slaughter-picker-ui]').hidden=false;
   select.required=false;
   const dialog=picker.querySelector('dialog'), trigger=picker.querySelector('[data-slaughter-picker-open]');
   trigger.setAttribute('aria-required','true');
   function openPicker(){input.value='';refreshChoices();dialog.showModal();input.focus();}
   trigger.addEventListener('click',openPicker);
   picker.querySelector('[data-slaughter-picker-close]').addEventListener('click',()=>dialog.close());
   dialog.addEventListener('close',()=>trigger.focus({preventScroll:true}));
   dialog.addEventListener('keydown',event=>{event.stopPropagation();if(event.key==='Escape'){event.preventDefault();dialog.close();}else if(event.key==='Enter'&&event.target===input){event.preventDefault();const choice=choices.find(choice=>normalize(choice.dataset.search).includes(normalize(input.value.trim())));choice?.querySelector('input').click();}});
   dialog.addEventListener('click',event=>{if(event.target.matches('[name="_batch_choice"]')&&event.target.checked&&select.value===event.target.value){dialog.close();}if(event.target===dialog){const rect=dialog.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)dialog.close();}});
   form.addEventListener('submit',event=>{if(!select.value){event.preventDefault();event.stopImmediatePropagation();openPicker();}},true);
   function refreshChoices(){
    const query=normalize(input.value.trim());let found=0;
    choices.forEach(choice=>{
     const radio=choice.querySelector('input');
     radio.checked=radio.value===select.value;
     choice.classList.toggle('is-selected',radio.checked);
     const match=normalize(choice.dataset.search).includes(query);
     choice.hidden=!match&&!radio.checked;if(match)found++;
    });
    picker.querySelector('[data-slaughter-lot-count]').textContent=choices.length ? found+' lot(s) correspondant(s) sur '+choices.length : 'Aucun lot validé dans un groupe autorisé à l’abattage. Vérifiez les effectifs et les passages de groupe.';
    picker.querySelector('[data-slaughter-no-match]').hidden=found>0||choices.length===0;
    picker.querySelector('[data-slaughter-selected-label]').textContent=select.value?select.selectedOptions[0].textContent:'Rechercher et choisir un lot…';
    trigger.classList.toggle('has-selection',Boolean(select.value));
    picker.querySelector('[data-slaughter-breeder-warning]').hidden=!choices.some(choice=>{const radio=choice.querySelector('input');return radio.checked&&radio.dataset.breeder==='1';});
   }
   input.addEventListener('input',refreshChoices);
   picker.addEventListener('change',event=>{if(event.target.matches('[name="_batch_choice"]')){select.value=event.target.value;select.dispatchEvent(new Event('change',{bubbles:true}));if(dialog.open)dialog.close();}refreshChoices();});
   form.addEventListener('reset',()=>queueMicrotask(()=>{input.value='';refreshChoices();}));
   refreshChoices();
  }
  initSourceSearch(form);form.addEventListener('input',sync);form.addEventListener('change',sync);
  form.addEventListener('reset',()=>queueMicrotask(()=>{const error=form.querySelector('[data-feed-error]');if(error)error.hidden=true;if(form.querySelector('[data-transformation-form]')){Array.from(form.querySelectorAll('[data-flow-output]')).slice(1).forEach(row=>{form.querySelector('[data-output-allocation="'+row.dataset.outputIndex+'"]')?.remove();row.remove();});form.querySelectorAll('details').forEach(detail=>detail.open=false);}sync();}));sync();
 });
};})();
