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
 const price=item=>units(data.prices.find(p=>String(p.item_id)===String(item)&&p.currency===currency.value)?.price||0,2);
 const products=()=>data.catalog.filter(item=>String(item.counter_id)===counter.value);
 const product=item=>products().find(row=>String(row.item_id)===String(item));
 function save(){try{sessionStorage.setItem(storeKey,JSON.stringify({counter:counter.value,currency:currency.value,basket,pending}));}catch(_){}}
 function message(text){error.textContent=text;error.hidden=!text;}
 try{const saved=JSON.parse(sessionStorage.getItem(storeKey)||'null');if(saved){if([...counter.options].some(o=>o.value===saved.counter))counter.value=saved.counter;if([...currency.options].some(o=>o.value===saved.currency))currency.value=saved.currency;basket=saved.basket&&typeof saved.basket==='object'&&!Array.isArray(saved.basket)?saved.basket:{};pending=saved.pending||null;}}catch(_){}
 if(!counter.value&&counter.options.length===2)counter.value=counter.options[1].value;
 if(pending&&payment){payment.elements.customer_name.value=pending.customer_name;payment.elements.payment_method.value=pending.payment_method;payment.elements.received_amount.value=pending.received_amount;}
 function totals(){let total=0,valid=Object.keys(basket).length>0;for(const[id,weight]of Object.entries(basket)){const grams=units(weight,3),p=product(id),cents=price(id);if(!p||!grams||grams>units(p.quantity_kg,3)||grams>999999999||!cents)valid=false;if(grams&&cents)total+=amount(grams,cents);}return{total,valid};}
 function update(){
  const {total,valid}=totals(),locked=inFlight||Boolean(pending),hasItems=Object.keys(basket).length>0;
  find('[data-pos-total]').textContent=money(total);find('[data-pos-mobile-total]').textContent=money(total);find('[data-pos-basket-empty]').hidden=hasItems;
  panel.querySelectorAll('[data-pos-line-total]').forEach(el=>{const id=el.dataset.posLineTotal;el.textContent=money(amount(units(basket[id]||0,3),price(id)));});
  counter.disabled=locked||hasItems;currency.disabled=locked||hasItems;find('[data-pos-clear]').disabled=locked||!hasItems;find('[data-pos-refresh]').disabled=locked;
  find('[data-pos-mobile-total]').parentElement.disabled=!hasItems;
  panel.querySelectorAll('[data-pos-item],[data-pos-remove],[data-pos-quantity]').forEach(el=>el.disabled=locked||panel.dataset.canSell!=='1'||Boolean(el.dataset.posItem&&!price(el.dataset.posItem)));
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
   button.append(image,node('strong',item.name),node('span',cents?money(cents)+' / kg':'Tarif à renseigner','pos-product-price'),node('small',Number(item.quantity_kg).toLocaleString('fr-FR',{maximumFractionDigits:3})+' kg disponibles'));
   button.disabled=!cents||panel.dataset.canSell!=='1'||Boolean(pending)||inFlight;card.append(button);area.append(card);
   button.addEventListener('click',()=>{
    chosen=item;const dialog=find('[data-pos-weight]'),form=dialog.querySelector('form');form.reset();form.elements.quantity.max=decimal(Math.max(0,units(item.quantity_kg,3)-units(basket[item.item_id]||0,3)),3);
    dialog.querySelector('h3').textContent=item.name;find('[data-pos-weight-price]').textContent=money(price(item.item_id))+' / kg · '+item.quantity_kg+' kg au comptoir';find('[data-pos-weight-total]').textContent='';dialog.showModal();form.elements.quantity.focus();
   });
  }
  const empty=find('[data-pos-empty]');empty.hidden=count>0;empty.textContent=!counter.value?'Choisissez un comptoir pour commencer.':products().length?'Aucun article pour cette recherche.':'Ce comptoir n’a aucun produit disponible. Approvisionnez-le depuis la chambre froide.';
 }
 function renderBasket(){
  const area=find('[data-pos-lines]');area.replaceChildren();
  for(const[id,weight]of Object.entries(basket)){
   const p=product(id),row=node('div',undefined,'pos-basket-line'),top=node('div',undefined,'pos-basket-line-title'),remove=node('button','×','btn-secondary');remove.type='button';remove.dataset.posRemove=id;remove.setAttribute('aria-label','Retirer '+(p?.name||'cet article'));
   top.append(node('strong',p?.name||'Article indisponible'),remove);const label=node('label'),input=node('input');input.type='number';input.min='0.001';input.max=p?p.quantity_kg:'0';input.step='0.001';input.inputMode='decimal';input.value=weight;input.dataset.posQuantity=id;label.append(node('span','Poids (kg)'),input);
   const pricing=node('div',undefined,'pos-basket-price'),subtotal=node('strong',money(amount(units(weight,3),price(id))));subtotal.dataset.posLineTotal=id;pricing.append(node('span',price(id)?money(price(id))+' / kg':'Tarif indisponible'),subtotal);
   row.append(top,label,pricing);area.append(row);
   input.addEventListener('input',()=>{basket[id]=input.value;input.setCustomValidity(units(input.value,3)>units(p?.quantity_kg||0,3)?'Stock du comptoir insuffisant.':'');update();});
   remove.addEventListener('click',()=>{delete basket[id];renderBasket();renderProducts();});
  }update();
 }
 async function refresh(){
  const response=await fetch(panel.dataset.catalogUrl,{headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin',cache:'no-store'});const payload=await response.json();if(!response.ok||!payload.ok)throw Error(payload.message||'Impossible d’actualiser les produits.');data={catalog:payload.catalog,prices:payload.prices};renderProducts();renderBasket();
 }
 counter.addEventListener('change',()=>{message('');renderProducts();renderBasket();});currency.addEventListener('change',()=>{renderProducts();renderBasket();});search.addEventListener('input',renderProducts);
 find('[data-pos-clear]').addEventListener('click',()=>{if(inFlight||pending)return;basket={};message('');if(payment)payment.reset();renderBasket();renderProducts();});
 find('[data-pos-refresh]').addEventListener('click',async()=>{message('');try{await refresh();}catch(e){message(e.message);}});
 find('[data-pos-show-basket]').addEventListener('click',()=>find('[data-pos-basket-title],#pos-basket-title').scrollIntoView({behavior:'smooth',block:'start'}));
 const weightDialog=find('[data-pos-weight]'),addForm=weightDialog.querySelector('form');
 find('[data-pos-weight-close]').addEventListener('click',()=>weightDialog.close());
 addForm.elements.quantity.addEventListener('input',()=>{const grams=units(addForm.elements.quantity.value,3);find('[data-pos-weight-total]').textContent=money(amount(grams,price(chosen?.item_id)));});
 addForm.addEventListener('submit',event=>{event.preventDefault();event.stopPropagation();if(!chosen||pending||inFlight||!addForm.reportValidity())return;const grams=units(addForm.elements.quantity.value,3)+units(basket[chosen.item_id]||0,3);if(!grams||grams>units(chosen.quantity_kg,3))return;basket[chosen.item_id]=decimal(grams,3);weightDialog.close();renderBasket();renderProducts();});
 const receipt=find('#pos-receipt'),frame=find('[data-pos-receipt-frame]'),print=find('[data-pos-print]');
 function renderReceipt(){if(!receiptUrl)return;const url=new URL(receiptUrl,window.location.href);url.searchParams.set('paper',find('[data-pos-paper]').value);find('[data-pos-open]').href=url.href;const pdf=new URL(url);pdf.searchParams.set('export','pdf');find('[data-pos-pdf]').href=pdf.href;print.disabled=true;find('[data-pos-receipt-error]').hidden=true;frame.src=url.href;}
 frame.addEventListener('load',()=>{let ready=false;try{ready=Boolean(frame.contentDocument.querySelector('[data-pos-receipt]'));if(ready)frame.contentDocument.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();receipt.querySelector('[data-workspace-modal-close]').click();}});}catch(_){}print.disabled=!ready;find('[data-pos-receipt-error]').hidden=ready;});
 print.addEventListener('click',()=>{if(!print.disabled){frame.contentWindow.focus();frame.contentWindow.print();}});find('[data-pos-paper]').addEventListener('change',renderReceipt);
 if(payment){
  payment.addEventListener('input',update);payment.addEventListener('change',update);
  payment.addEventListener('submit',async event=>{
   event.preventDefault();event.stopPropagation();if(inFlight)return;
   if(!pending){if(!payment.reportValidity()||!totals().valid)return;const {total}=totals();const received=units(payment.elements.received_amount.value,2);if(received<total)return;
    const bytes=new Uint8Array(16);crypto.getRandomValues(bytes);pending={operation_key:Array.from(bytes,b=>b.toString(16).padStart(2,'0')).join(''),counter_id:counter.value,currency:currency.value,customer_name:payment.elements.customer_name.value.trim()||'Client comptoir',payment_method:payment.elements.payment_method.value,received_amount:decimal(received),lines:Object.entries(basket).map(([id,quantity])=>({item_id:id,quantity_kg:decimal(units(quantity,3),3),unit_price:decimal(price(id))}))};
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
 if(prices){const fillPrice=()=>{const existing=data.prices.find(p=>String(p.item_id)===prices.elements.item_id.value&&p.currency===prices.elements.currency.value);prices.elements.price.value=existing?.price||'';find('[data-pos-price-current]').textContent=existing?'Tarif actuel : '+existing.price+' '+existing.currency+' / kg':'Aucun tarif enregistré dans cette devise.';};prices.addEventListener('change',event=>{if(event.target.matches('[name=item_id],[name=currency]'))fillPrice();});find('#pos-prices').addEventListener('workspace:opened',fillPrice);}
 renderProducts();renderBasket();if(pending)message('Un encaissement reste à confirmer. Réessayez le même encaissement avant de commencer une nouvelle vente.');
};})();
