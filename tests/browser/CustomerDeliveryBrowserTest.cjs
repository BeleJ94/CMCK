// Commercial distribution, lot 3 (049): client exits hub, delivery of an order from the office or the mill, confirmation,
// note to print, cancellation, closure of the order and exit without an order. Run against the disposable server of travaux-agricoles-validation.md.
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const fixture=JSON.parse(execFileSync('php',[path.join(__dirname,'../integration/CustomerDeliveryBrowserFixture.php')],{env:process.env,encoding:'utf8'}));
const base=process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8107';
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  const drawer=page.locator(`#deliver-${fixture.order}`),form=drawer.locator('form');
  const line=id=>form.locator(`[data-delivery-line="${id}"]`),qty=id=>line(id).locator('[data-line-input]');
  const card=()=>page.locator(`[data-delivery-order="${fixture.order}"]`);
  const dialog=page.locator('[data-exit-confirm-dialog]');
  // Opening the drawer resets the form, then the ready bags of the chosen place are filled in.
  const filled=()=>page.waitForFunction(id=>{const input=document.querySelector(`[data-delivery-line="${id}"] [data-line-input]`);return input&&input.max!=='0';},fixture.l50);
  async function confirmAndWait(){const nav=page.waitForResponse(r=>r.request().isNavigationRequest()&&new URL(r.url()).pathname.endsWith('/distributions'));await dialog.locator('[data-exit-confirm-ok]').click();await nav;await page.waitForLoadState('load');}
  async function flowSave(form){const response=page.waitForResponse(r=>r.request().method()==='POST');await form.locator('button[type=submit]').click();await page.locator('[data-action-dialog-confirm]').click();const payload=await(await response).json();assert(payload.ok,payload.message);await page.locator('body.is-loading').waitFor({state:'hidden'});return payload.message;}

  // The order is ready: 20 bags at DECO, 10 + 10 straight from the mill.
  await page.goto(base+'/distributions');
  assert.match(await card().innerText(),/Prête · 40 sac\(s\)/);
  assert.match(await card().innerText(),/DECO · 20 prêt/);
  await card().getByRole('button',{name:'Livrer'}).click();
  await drawer.waitFor({state:'visible'});await filled();
  assert(await form.locator(`[name=source][value="office-${fixture.deco}"]`).isChecked(),'Le bureau principal est proposé en premier');
  assert.equal(await qty(fixture.l50).inputValue(),'20');assert.equal(await qty(fixture.l50).getAttribute('max'),'20');
  assert(await qty(fixture.l10).isDisabled(),'Rien de prêt au bureau pour les sacs de 10 kg');assert.match(await line(fixture.l10).innerText(),/Prêts à [^\n]*DECO\s*0[\s\S]*Rien de prêt ici pour ce produit/);
  assert.match(await form.locator('[data-delivery-summary]').innerText(),/20 sac\(s\) · 1\s000 kg · 700,00 USD/);
  // + stays within the bags ready here, − removes one bag, « Tout » proposes them all again.
  await line(fixture.l50).locator('[data-line-step="1"]').click();assert.equal(await qty(fixture.l50).inputValue(),'20');
  await line(fixture.l50).locator('[data-line-step="-1"]').click();assert.equal(await qty(fixture.l50).inputValue(),'19');
  assert.match(await line(fixture.l50).locator('[data-line-total]').innerText(),/950 kg · 665,00 USD/);
  await line(fixture.l50).getByRole('button',{name:'Tout (20)'}).click();assert.equal(await qty(fixture.l50).inputValue(),'20');

  // Straight from the mill: both lines ready.
  await form.locator(`label:has([name=source][value="source-${fixture.mino}"])`).click();
  assert.equal(await qty(fixture.l50).inputValue(),'10');assert.equal(await qty(fixture.l10).inputValue(),'10');
  assert.match(await form.locator('[data-delivery-summary]').innerText(),/20 sac\(s\) · 600 kg · 430,00 USD/);
  await qty(fixture.l10).fill('0');assert.match(await line(fixture.l10).locator('[data-line-total]').innerText(),/Pas livré cette fois/);
  assert.match(await form.locator('[data-delivery-summary]').innerText(),/10 sac\(s\) · 500 kg/);

  // Back to the office, 15 bags by company truck: vehicle and driver become required.
  await form.locator(`label:has([name=source][value="office-${fixture.deco}"])`).click();await qty(fixture.l50).fill('15');
  await form.locator('label:has([name=delivery_mode][value=company_truck])').click();
  assert.equal(await form.locator('[data-vehicle-label]').innerText(),'Camion de l’entreprise *');
  assert(await form.locator('[name=vehicle_reference]').evaluate(el=>el.required));
  await form.locator('[name=receiver_name]').fill('Jean Mavungu');
  await form.locator('[data-delivery-submit]').click();
  assert(!(await dialog.isVisible().catch(()=>false)),'Pas de confirmation tant que le camion n’est pas renseigné');
  await form.locator('[name=vehicle_reference]').fill('CAM-07');await form.locator('[name=driver_name]').fill('Paul Kasa');

  // A refusal from the server stays in the form, values kept.
  await page.route('**/distributions/deliveries',route=>route.fulfill({status:422,contentType:'application/json',body:JSON.stringify({success:false,message:'Refus simulé'})}));
  await form.locator('[data-delivery-submit]').click();await dialog.waitFor({state:'visible'});await dialog.locator('[data-exit-confirm-ok]').click();
  await form.locator('[data-delivery-error]').waitFor({state:'visible'});assert.equal(await form.locator('[data-delivery-error]').innerText(),'Refus simulé');assert.equal(await qty(fixture.l50).inputValue(),'15');
  await page.unroute('**/distributions/deliveries');

  // Sober confirmation, then « Modifier » goes back to the form.
  await form.locator('[data-delivery-submit]').click();await dialog.waitFor({state:'visible'});
  assert.equal(await dialog.locator('h2').innerText(),'Confirmer la livraison');
  assert.match(await dialog.locator('.op-confirm__lead').innerText(),new RegExp(fixture.clientName+' · '+fixture.orderNumber+' · depuis '));
  assert.match(await dialog.locator('.op-confirm__lines').innerText(),/15 sac\(s\)/);
  assert.match(await dialog.locator('.op-confirm__total').innerText(),/15 sac\(s\) · 750 kg · 525,00 USD/);
  assert.match(await dialog.locator('.op-confirm__note').innerText(),/Camion CAM-07, chauffeur Paul Kasa\. Reçu par Jean Mavungu/);
  await dialog.getByRole('button',{name:'Modifier'}).click();assert(await drawer.isVisible());
  await form.locator('[data-delivery-submit]').click();await dialog.waitFor({state:'visible'});await confirmAndWait();

  // Back on the hub: message, note to print, card and history updated.
  const banner=page.locator('.distribution-done');
  assert.match(await banner.innerText(),/15 sac\(s\) remis à /);
  // The note opens in a modal, as the PDF that is downloaded and printed.
  await banner.getByRole('button',{name:/Voir le bon de livraison BL\//}).click();
  const preview=page.locator('#note-preview');await preview.waitFor({state:'visible'});
  assert.match(await preview.locator('[data-note-title]').innerText(),/^Bon de livraison BL\//);
  const src=(await preview.locator('[data-note-frame]').getAttribute('src')).replace(/#.*$/,'');assert.match(src,/\/distributions\/deliveries\/\d+\/print$/);
  const note=await page.context().request.get(src);assert.equal(note.status(),200);assert.match(note.headers()['content-type'],/application\/pdf/);assert.match(note.headers()['content-disposition']||'',/^inline/);assert.equal((await note.body()).subarray(0,4).toString(),'%PDF');
  const download=await page.context().request.get(await preview.locator('[data-note-pdf]').getAttribute('href'));assert.match(download.headers()['content-disposition']||'',/^attachment; filename="bon-livraison-BL-/);
  assert.equal(await preview.locator('[data-note-tab]').getAttribute('href'),src);
  await page.keyboard.press('Escape');await preview.waitFor({state:'hidden'});
  assert.match(await card().innerText(),/15 \/ 30\s*livrés/);assert.match(await card().innerText(),/Prête · 25 sac\(s\)/);
  const history=page.locator('#distributionsTable');
  await page.locator('[data-conversion-filter=type]').selectOption('delivery');
  const row=history.locator('tbody tr',{hasText:fixture.clientName}).first();
  assert.equal(await row.getAttribute('data-type'),'delivery');assert.match(await row.innerText(),/Livrée/);
  const number=(await row.locator('.cell-main').first().innerText()).trim();

  // Details, then cancellation: the bags go back to the office, reserved for the order.
  await row.getByRole('button',{name:'Consulter '+number}).click();
  const detail=page.locator('.workspace-entity-modal.is-open');assert.match(await detail.innerText(),/Reçu par[\s\S]*Jean Mavungu/);
  await detail.getByRole('button',{name:'Annuler la livraison'}).click();
  const cancel=page.locator('.workspace-entity-modal.is-open form');await cancel.locator('[name=reason]').fill('Erreur de quantité');
  assert.match(await flowSave(cancel),/Livraison annulée/);
  await page.locator('[data-conversion-filter=type]').selectOption('delivery');
  assert.match(await history.locator('tbody tr',{hasText:number}).first().innerText(),/Annulée/);
  assert.match(await card().innerText(),/Prête · 40 sac\(s\)/);

  // From the orders page, « Livrer » opens the delivery form directly.
  await page.goto(base+'/sales/orders');
  const orderRow=page.locator(`tr[data-order-id="${fixture.order}"]`);
  await orderRow.getByRole('link',{name:'Livrer'}).click();
  await drawer.waitFor({state:'visible'});await filled();assert.equal(new URL(page.url()).hash,'');
  await form.locator(`label:has([name=source][value="source-${fixture.mino}"])`).click();await form.locator('[name=receiver_name]').fill('Chauffeur client');
  await form.locator('[data-delivery-submit]').click();await dialog.waitFor({state:'visible'});
  assert.match(await dialog.locator('.op-confirm__note').innerText(),/Enlèvement par le client/);await confirmAndWait();
  assert.match(await page.locator('.distribution-done').innerText(),/20 sac\(s\) remis à/);

  // Partly delivered order: closed from the orders page, the rest is released.
  await page.goto(base+'/sales/orders');
  await page.locator(`#order-menu-${fixture.order}-toggle`).click();await page.locator(`#order-menu-${fixture.order}`).getByRole('menuitem',{name:'Clôturer la commande'}).click();
  const close=page.locator(`#order-close-${fixture.order} form`);assert.match(await page.locator(`#order-close-${fixture.order}`).innerText(),/20 sac\(s\) livré\(s\) sur 40/);
  await close.locator('[name=reason]').fill('Le client renonce au reste');
  assert.match(await flowSave(close),/Commande clôturée/);
  assert.match(await page.locator(`tr[data-order-id="${fixture.order}"]`).innerText(),/Soldée[\s\S]*20 \/ 40 livrés/);
  await page.goto(base+'/distributions');assert.equal(await card().count(),0,'La commande soldée quitte les commandes à livrer');

  // Exit without an order to the registered client: the name comes from the client record.
  await page.locator('.ui-page-header').getByRole('button',{name:'Sortie sans commande'}).click();
  const exit=page.locator('#distribution-create form');
  await exit.locator('[name=customer_id]').selectOption(String(fixture.client));
  assert(await exit.locator('[data-exit-recipient]').isHidden(),'Destinataire libre masqué pour un client enregistré');
  const lot=await exit.locator('[name=finished_stock_id] option[value]:not([value=""])').first();await exit.locator('[name=finished_stock_id]').selectOption(await lot.getAttribute('value'));
  const free=Number(await lot.getAttribute('data-bags')),weight=Number(await lot.getAttribute('data-weight'));
  await exit.locator('[name=quantity_bags]').fill(String(free+1));assert.equal(await exit.locator('[name=quantity_bags]').evaluate(el=>el.checkValidity()),false);
  await exit.getByRole('button',{name:'Tout'}).click();assert.equal(await exit.locator('[name=quantity_bags]').inputValue(),String(free));
  await exit.locator('[name=quantity_bags]').fill('1');assert.match(await exit.locator('[data-distribution-summary]').innerText(),new RegExp('Sortie : '+weight+' kg'));
  await exit.locator('button[type=submit]').click();await dialog.waitFor({state:'visible'});
  assert.equal(await dialog.locator('h2').innerText(),'Confirmer la sortie');assert.match(await dialog.locator('.op-confirm__lead').innerText(),new RegExp('^'+fixture.clientName+' · depuis '));
  await confirmAndWait();
  assert.match(await page.locator('.distribution-done').innerText(),new RegExp('1 sac\\(s\\) remis à '+fixture.clientName+'[\\s\\S]*Voir le bon de sortie'));
  await page.locator('.distribution-done').getByRole('button',{name:/Voir le bon de sortie/}).click();await page.locator('#note-preview').waitFor({state:'visible'});
  const exitSrc=(await page.locator('#note-preview [data-note-frame]').getAttribute('src')).replace(/#.*$/,'');assert.match(exitSrc,/\/distributions\/\d+\/print\?export=pdf&preview=1$/);
  const exitNote=await page.context().request.get(exitSrc);assert.match(exitNote.headers()['content-type'],/application\/pdf/);assert.match(exitNote.headers()['content-disposition']||'',/^inline/);
  await page.keyboard.press('Escape');
  await page.locator('[data-conversion-filter=type]').selectOption('direct');assert.deepEqual([...new Set(await history.locator('tbody tr:visible').evaluateAll(rows=>rows.map(row=>row.dataset.type)))],['direct']);

  // Phones: the hub and the forms stay within the screen.
  await page.goto(base+'/distributions');
  for(const width of [390,320]){
   await page.setViewportSize({width,height:800});await page.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Hub sans défilement horizontal à '+width+' px');
   await page.locator('.ui-page-header').getByRole('button',{name:'Sortie sans commande'}).click();
   const box=await page.locator('#distribution-create footer').boundingBox();assert(box.x>=0&&box.x+box.width<=width+1,'Pied du formulaire visible à '+width+' px');
   assert(await page.locator('#distribution-create .transfer-op').evaluate(el=>el.scrollWidth<=el.clientWidth+1));await page.keyboard.press('Escape');
  }
  assert.deepEqual(errors,[]);
  console.log('OK : commandes à livrer, provenance, camion, refus, confirmation, bon à imprimer, annulation, lien depuis les commandes, clôture, sortie sans commande et mobile.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
