(function(){'use strict';
window.initButcheryPos=function(root){
 const panel=root.matches?.('[data-butchery-pos]')?root:root.querySelector('[data-butchery-pos]');if(!panel||panel.dataset.posReady)return;panel.dataset.posReady='1';
 let data=JSON.parse(panel.querySelector('[data-pos-data]').textContent),basket={},pending=null,inFlight=false,chosen=null,receiptUrl='';
 const find=selector=>panel.querySelector(selector),counter=find('[data-pos-counter]'),currency=find('[data-pos-currency]'),search=find('[data-pos-search]'),payment=find('[data-pos-payment]'),error=find('[data-pos-error]');
 const storeKey='dagril-pos:'+panel.dataset.siteScope+':'+panel.dataset.user;
 const normalize=text=>String(text).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
 const units=(value,places)=>{const text=String(value).replace(',','.');if(!new RegExp('^\\d{1,12}(?:\\.\\d{1,'+places+'})?$').test(text))return 0;const [whole,part='']=text.split('.');return Number(whole)*10**places+Number(part.padEnd(places,'0'));};
 const decimal=(value,places=2)=>(value/10**places).toFixed(places);
 const money=cents=>(cents/100).toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2})+' '+currency.value;
 const amount=(grams,price)=>Number((BigInt(grams)*BigInt(price)+500n)/1000n);
 const node=(tag,text,className)=>{const el=document.createElement(tag);if(text!==undefined)el.textContent=text;if(className)el.className=className;return el;};
 const products=()=>data.catalog.filter(item=>String(item.counter_id)===counter.value);
 const product=item=>products().find(row=>String(row.item_id)===String(item));
 const article=item=>product(item)||(data.items||[]).find(row=>String(row.id)===String(item));
 // Each article sells by the kilo or by the piece; a price kept in the other mode must be entered again.
 const unitOf=item=>article(item)?.unit==='piece'?'piece':'kg',counted=item=>Number(article(item)?.track_pieces)===1;
 const price=item=>{const row=data.prices.find(p=>String(p.item_id)===String(item)&&p.currency===currency.value);return row&&(row.price_unit||'kg')===unitOf(item)?units(row.price,2):0;};
 const per=item=>unitOf(item)==='piece'?' / pièce':' / kg';
 const plain=(value,digits=3)=>Number(value||0).toLocaleString('fr-FR',{maximumFractionDigits:digits});
 // Basket lines keep the weighed kilos and the counted pieces; baskets saved before kept the kilos only.
 const line=id=>{const value=basket[id];return value&&typeof value==='object'?{kg:String(value.kg||''),pieces:String(value.pieces||'')}:{kg:String(value||''),pieces:''};};
 const lineAmount=id=>{const cents=price(id),l=line(id);if(!cents)return 0;return unitOf(id)==='piece'?(Number(l.pieces)||0)*cents:amount(units(l.kg,3),cents);};
 function lineValid(id){
  const p=product(id),l=line(id);if(!p||!price(id))return false;
  if(counted(id)){const pieces=Number(l.pieces);if(!Number.isInteger(pieces)||pieces<1||pieces>Number(p.quantity_pieces||0))return false;}
  if(unitOf(id)==='kg'){const grams=units(l.kg,3);if(!grams||grams>999999999||(!counted(id)&&grams>units(p.quantity_kg,3)))return false;}
  return true;
 }
 function save(){try{sessionStorage.setItem(storeKey,JSON.stringify({counter:counter.value,currency:currency.value,basket,pending}));}catch(_){}}
 function message(text){error.textContent=text;error.hidden=!text;}
 try{const saved=JSON.parse(sessionStorage.getItem(storeKey)||'null');if(saved){if([...counter.options].some(o=>o.value===saved.counter))counter.value=saved.counter;if([...currency.options].some(o=>o.value===saved.currency))currency.value=saved.currency;basket=saved.basket&&typeof saved.basket==='object'&&!Array.isArray(saved.basket)?saved.basket:{};pending=saved.pending||null;}}catch(_){}
 if(!counter.value&&counter.options.length===2)counter.value=counter.options[1].value;
 if(pending&&payment){payment.elements.customer_name.value=pending.customer_name;payment.elements.payment_method.value=pending.payment_method;payment.elements.received_amount.value=pending.received_amount;}
 function totals(){let total=0,valid=Object.keys(basket).length>0;for(const id of Object.keys(basket)){if(!lineValid(id))valid=false;total+=lineAmount(id);}return{total,valid};}
 function update(){
  const {total,valid}=totals(),locked=inFlight||Boolean(pending),hasItems=Object.keys(basket).length>0;
  find('[data-pos-total]').textContent=money(total);find('[data-pos-mobile-total]').textContent=money(total);find('[data-pos-basket-empty]').hidden=hasItems;
  panel.querySelectorAll('[data-pos-line-total]').forEach(el=>{el.textContent=money(lineAmount(el.dataset.posLineTotal));});
  counter.disabled=locked||hasItems;currency.disabled=locked||hasItems;find('[data-pos-clear]').disabled=locked||!hasItems;find('[data-pos-refresh]').disabled=locked;
  find('[data-pos-mobile-total]').parentElement.disabled=!hasItems;
  panel.querySelectorAll('[data-pos-item],[data-pos-remove],[data-pos-quantity],[data-pos-pieces]').forEach(el=>el.disabled=locked||panel.dataset.canSell!=='1'||Boolean(el.dataset.posItem&&!price(el.dataset.posItem)));
  if(payment){
   const cash=payment.elements.payment_method.value==='cash';payment.elements.received_amount.readOnly=!cash||locked;
   if(!cash&&!locked)payment.elements.received_amount.value=decimal(total);
   payment.elements.payment_method.disabled=locked;payment.elements.customer_name.readOnly=locked;
   const received=units(payment.elements.received_amount.value,2),enough=received>=total;
   find('[data-pos-change]').textContent=cash?(enough?'Monnaie à rendre : '+money(received-total):'Reste à recevoir : '+money(total-received)):'Paiement exact : '+money(total);
   find('[data-pos-payment-help]').hidden=cash;
   const button=find('[data-pos-checkout]');button.disabled=inFlight||(!pending&&(!valid||!enough||(!cash&&received!==total)));
   button.textContent=inFlight?'Encaissement en cours…':pending?'Réessayer le même encaissement':'Confirmer et émettre le reçu';
  }
  save();
 }
 function renderProducts(){
  const area=find('[data-pos-products]');area.replaceChildren();let count=0;
  for(const item of products()){
   if(!normalize(item.name).includes(normalize(search.value.trim())))continue;count++;
   const cents=price(item.item_id),card=node('article',undefined,'pos-product'),button=node('button',undefined,'pos-product-button');button.type='button';button.dataset.posItem=item.item_id;
   const image=node('img');image.src=panel.dataset.productImage;image.alt='';image.width=120;image.height=75;
   button.append(image,node('strong',item.name),node('span',cents?money(cents)+per(item.item_id):'Tarif à renseigner','pos-product-price'),node('small',counted(item.item_id)?plain(item.quantity_pieces,0)+' pièce(s) · '+plain(item.quantity_kg)+' kg':plain(item.quantity_kg)+' kg disponibles'));
   button.disabled=!cents||panel.dataset.canSell!=='1'||Boolean(pending)||inFlight;card.append(button);area.append(card);
   button.addEventListener('click',()=>{
    chosen=item;const dialog=find('[data-pos-weight]'),form=dialog.querySelector('form'),id=item.item_id,byKg=unitOf(id)==='kg',byPiece=counted(id),l=line(id);form.reset();
    // Articles sold by the kilo are weighed; articles followed in pieces are counted too.
    find('[data-pos-kg-field]').hidden=!byKg;form.elements.quantity.disabled=!byKg;form.elements.quantity.required=byKg;
    find('[data-pos-pieces-field]').hidden=!byPiece;form.elements.pieces.disabled=!byPiece;form.elements.pieces.required=byPiece;
    form.elements.quantity.max=byPiece?'999999.999':decimal(Math.max(0,units(item.quantity_kg,3)-units(l.kg,3)),3);form.elements.pieces.max=String(Math.max(0,Number(item.quantity_pieces||0)-(Number(l.pieces)||0)));
    dialog.querySelector('h3').textContent=item.name;find('[data-pos-weight-price]').textContent=money(price(id))+per(id)+' · '+(byPiece?plain(item.quantity_pieces,0)+' pièce(s) au comptoir':plain(item.quantity_kg)+' kg au comptoir');find('[data-pos-weight-total]').textContent='';dialog.showModal();(byKg?form.elements.quantity:form.elements.pieces).focus();
   });
  }
  const empty=find('[data-pos-empty]');empty.hidden=count>0;empty.textContent=!counter.value?'Choisissez un comptoir pour commencer.':products().length?'Aucun article pour cette recherche.':'Ce comptoir n’a aucun produit disponible. Approvisionnez-le depuis la chambre froide.';
 }
 function renderBasket(){
  const area=find('[data-pos-lines]');area.replaceChildren();
  for(const id of Object.keys(basket)){
   const p=product(id),l=line(id),row=node('div',undefined,'pos-basket-line'),top=node('div',undefined,'pos-basket-line-title'),remove=node('button','×','btn-secondary');remove.type='button';remove.dataset.posRemove=id;remove.setAttribute('aria-label','Retirer '+(p?.name||'cet article'));
   top.append(node('strong',p?.name||'Article indisponible'),remove);row.append(top);
   const field=(text,attributes,value,check)=>{const label=node('label'),input=node('input');Object.assign(input,attributes);input.value=value;label.append(node('span',text),input);row.append(label);input.addEventListener('input',()=>{basket[id]=Object.assign(line(id),{[attributes.name]:input.value});input.setCustomValidity(check(input.value));update();});return input;};
   if(unitOf(id)==='kg')field('Poids (kg)',{type:'number',name:'kg',min:'0.001',max:counted(id)?'999999.999':(p?p.quantity_kg:'0'),step:'0.001',inputMode:'decimal'},l.kg,value=>!counted(id)&&units(value,3)>units(p?.quantity_kg||0,3)?'Stock du comptoir insuffisant.':'').dataset.posQuantity=id;
   if(counted(id))field('Pièces',{type:'number',name:'pieces',min:'1',max:String(p?.quantity_pieces||0),step:'1',inputMode:'numeric'},l.pieces,value=>Number(value)>Number(p?.quantity_pieces||0)?'Pièces du comptoir insuffisantes.':'').dataset.posPieces=id;
   const pricing=node('div',undefined,'pos-basket-price'),subtotal=node('strong',money(lineAmount(id)));subtotal.dataset.posLineTotal=id;pricing.append(node('span',price(id)?money(price(id))+per(id):'Tarif indisponible'),subtotal);
   row.append(pricing);area.append(row);
   remove.addEventListener('click',()=>{delete basket[id];renderBasket();renderProducts();});
  }update();
 }
 async function refresh(){
  const response=await fetch(panel.dataset.catalogUrl,{headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin',cache:'no-store'});const payload=await response.json();if(!response.ok||!payload.ok)throw Error(payload.message||'Impossible d’actualiser les produits.');data={catalog:payload.catalog,prices:payload.prices,items:payload.items||data.items};renderProducts();renderBasket();
 }
 counter.addEventListener('change',()=>{message('');renderProducts();renderBasket();});currency.addEventListener('change',()=>{renderProducts();renderBasket();});search.addEventListener('input',renderProducts);
 find('[data-pos-clear]').addEventListener('click',()=>{if(inFlight||pending)return;basket={};message('');if(payment)payment.reset();renderBasket();renderProducts();});
 find('[data-pos-refresh]').addEventListener('click',async()=>{message('');try{await refresh();}catch(e){message(e.message);}});
 find('[data-pos-show-basket]').addEventListener('click',()=>find('[data-pos-basket-title],#pos-basket-title').scrollIntoView({behavior:'smooth',block:'start'}));
 const weightDialog=find('[data-pos-weight]'),addForm=weightDialog.querySelector('form');
 find('[data-pos-weight-close]').addEventListener('click',()=>weightDialog.close());
 addForm.addEventListener('input',()=>{if(!chosen)return;const id=chosen.item_id,cents=price(id);find('[data-pos-weight-total]').textContent=money(unitOf(id)==='piece'?(Number(addForm.elements.pieces.value)||0)*cents:amount(units(addForm.elements.quantity.value,3),cents));});
 addForm.addEventListener('submit',event=>{
  event.preventDefault();event.stopPropagation();if(!chosen||pending||inFlight||!addForm.reportValidity())return;
  const id=chosen.item_id,l=line(id),next={kg:l.kg,pieces:l.pieces};
  if(unitOf(id)==='kg'){const grams=units(addForm.elements.quantity.value,3)+units(l.kg,3);if(!grams||(!counted(id)&&grams>units(chosen.quantity_kg,3)))return;next.kg=decimal(grams,3);}
  if(counted(id)){const pieces=(Number(addForm.elements.pieces.value)||0)+(Number(l.pieces)||0);if(pieces<1||pieces>Number(chosen.quantity_pieces||0))return;next.pieces=String(pieces);}
  basket[id]=next;weightDialog.close();renderBasket();renderProducts();
 });
 const receipt=find('#pos-receipt'),frame=find('[data-pos-receipt-frame]'),print=find('[data-pos-print]');
 function renderReceipt(){if(!receiptUrl)return;const url=new URL(receiptUrl,window.location.href);url.searchParams.set('paper',find('[data-pos-paper]').value);find('[data-pos-open]').href=url.href;const pdf=new URL(url);pdf.searchParams.set('export','pdf');find('[data-pos-pdf]').href=pdf.href;print.disabled=true;find('[data-pos-receipt-error]').hidden=true;frame.src=url.href;}
 frame.addEventListener('load',()=>{let ready=false;try{ready=Boolean(frame.contentDocument.querySelector('[data-pos-receipt]'));if(ready)frame.contentDocument.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();receipt.querySelector('[data-workspace-modal-close]').click();}});}catch(_){}print.disabled=!ready;find('[data-pos-receipt-error]').hidden=ready;});
 print.addEventListener('click',()=>{if(!print.disabled){frame.contentWindow.focus();frame.contentWindow.print();}});find('[data-pos-paper]').addEventListener('change',renderReceipt);
 if(payment){
  payment.addEventListener('input',update);payment.addEventListener('change',update);
  payment.addEventListener('submit',async event=>{
   event.preventDefault();event.stopPropagation();if(inFlight)return;
   if(!pending){if(!payment.reportValidity()||!totals().valid)return;const {total}=totals();const received=units(payment.elements.received_amount.value,2);if(received<total)return;
    const bytes=new Uint8Array(16);crypto.getRandomValues(bytes);pending={operation_key:Array.from(bytes,b=>b.toString(16).padStart(2,'0')).join(''),counter_id:counter.value,currency:currency.value,customer_name:payment.elements.customer_name.value.trim()||'Client comptoir',payment_method:payment.elements.payment_method.value,received_amount:decimal(received),lines:Object.keys(basket).map(id=>{const l=line(id),entry={item_id:id};if(unitOf(id)==='kg')entry.quantity_kg=decimal(units(l.kg,3),3);if(counted(id))entry.quantity_pieces=String(Number(l.pieces));entry.unit_price=decimal(price(id));return entry;})};
   }
   inFlight=true;message('');update();renderProducts();
   const body=new URLSearchParams({_token:payment.elements._token.value,...Object.fromEntries(Object.entries(pending).filter(([key])=>key!=='lines'))});pending.lines.forEach((line,index)=>Object.entries(line).forEach(([key,value])=>body.set('lines['+index+']['+key+']',value)));
   try{
    const response=await fetch(panel.dataset.checkoutUrl,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body,credentials:'same-origin'});const result=await response.json();
    if(!response.ok||!result.ok){if(response.status===422){pending=null;await refresh();}throw Error(result.message||'Encaissement non confirmé. Réessayez le même encaissement.');}
    receiptUrl=result.receipt_url;pending=null;basket={};payment.reset();save();renderReceipt();find('[data-pos-receipt-trigger]').click();
    try{await refresh();}catch(_){message('Vente encaissée. Actualisez les produits avant la prochaine vente.');data.catalog=[];renderProducts();}
   }catch(e){message(pending?'Résultat non confirmé. Le panier est conservé : réessayez le même encaissement pour éviter une double vente. '+e.message:e.message);}
   finally{inFlight=false;renderBasket();renderProducts();}
  });
 }
 const prices=find('#pos-prices form');
 if(prices){
  // Articles followed in pieces choose their sale mode; the others sell by the kilo.
  const syncMode=()=>{const item=(data.items||[]).find(row=>String(row.id)===prices.elements.item_id.value),mode=prices.elements.price_unit,field=find('[data-pos-mode-field]'),choice=Number(item?.track_pieces)===1;field.hidden=!choice;mode.disabled=!choice;if(!choice)mode.value='kg';find('[data-pos-price-label]').textContent=mode.value==='piece'?'Prix à la pièce *':'Prix par kilo *';return item;};
  const fillPrice=()=>{const item=syncMode();if(item)prices.elements.price_unit.value=item.unit==='piece'?'piece':'kg';syncMode();const existing=data.prices.find(p=>String(p.item_id)===prices.elements.item_id.value&&p.currency===prices.elements.currency.value),mode=prices.elements.price_unit.value,stale=existing&&(existing.price_unit||'kg')!==mode;prices.elements.price.value=existing&&!stale?existing.price:'';find('[data-pos-price-current]').textContent=!existing?'Aucun tarif enregistré dans cette devise.':stale?'Le tarif enregistré ('+existing.price+' '+existing.currency+(existing.price_unit==='piece'?' / pièce':' / kg')+') ne correspond plus au mode de vente : saisissez le nouveau prix.':'Tarif actuel : '+existing.price+' '+existing.currency+(mode==='piece'?' / pièce':' / kg');};
  prices.addEventListener('change',event=>{if(event.target.matches('[name=item_id],[name=currency]'))fillPrice();else if(event.target.matches('[name=price_unit]')){syncMode();const existing=data.prices.find(p=>String(p.item_id)===prices.elements.item_id.value&&p.currency===prices.elements.currency.value);prices.elements.price.value=existing&&(existing.price_unit||'kg')===prices.elements.price_unit.value?existing.price:'';find('[data-pos-price-current]').textContent=prices.elements.price_unit.value==='piece'?'Vendu à la pièce : le caissier compte les pièces.':'Vendu au kilo : le caissier pèse l’article'+(Number((data.items||[]).find(row=>String(row.id)===prices.elements.item_id.value)?.track_pieces)===1?' et compte les pièces.':'.');}});
  find('#pos-prices').addEventListener('workspace:opened',fillPrice);
 }
 renderProducts();renderBasket();if(pending)message('Un encaissement reste à confirmer. Réessayez le même encaissement avant de commencer une nouvelle vente.');
};})();
