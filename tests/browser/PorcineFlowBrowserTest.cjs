const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');const fs=require('node:fs');
const fixture=JSON.parse(fs.readFileSync(process.env.DAGRIL_FLOW_FIXTURE,'utf8'));
const base=process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8108';
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  async function save(form){
   await form.evaluate(e=>{const details=e.closest('details');if(details)details.open=true;});
   const action=await form.getAttribute('action');const response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url()===action);
   await form.locator('button[type=submit]').click();await page.locator('[data-action-dialog-confirm]').click();const r=await response;const payload=await r.json();assert(payload.ok,payload.message);await page.locator('body.is-loading').waitFor({state:'hidden'});await page.waitForFunction(()=>document.querySelector('[data-flow-directory]')?.dataset.ready==='1');
  }
  async function previewBtr(row,status){
   await row.locator('[data-btr-preview]').click();
   await page.waitForFunction(()=>!document.querySelector('[data-btr-print]').disabled);
   assert.match(await page.frameLocator('[data-btr-frame]').locator('.btr-status').innerText(),status);
   await page.keyboard.press('Escape');
  }
  async function sourceLot(form,id){
   const lot=form.locator(`[data-transform-lot]:has(input[value="${id}"])`);await form.locator('[name=source_item_id]').selectOption(await lot.getAttribute('data-item'));
   await form.locator('.transform-lots > summary').click();await form.locator('[name=lot_mode]').selectOption('manual');
   for(const check of await form.locator('[name="source_lot_ids[]"]:enabled').all())await check.uncheck();
   await lot.locator('input').check();
  }
  await page.goto(base+'/livestock/pigs/slaughters');
  assert.equal(await page.locator('.sidebar a[href$="/butchery/slaughters"]').count(),0);
  await page.locator('[data-workspace-modal-open=flow-slaughter]').click();let form=page.locator('#flow-slaughter form');
  await form.locator('[data-slaughter-picker-open]').click();await form.locator(`[name=_batch_choice][value="${fixture.adult}"]`).check();await form.locator('[name=heads]').fill('1');await form.locator('[name=net_kg]').fill('5');await form.locator('[name=expiry_date]').fill(fixture.expiry);await save(form);
  const slaughter=page.locator('[data-slaughter-id]').first();const slaughterId=await slaughter.getAttribute('data-slaughter-id');const slaughterNumber=await slaughter.locator('td:first-child strong').textContent();
  await save(page.locator(`form[action$="/slaughters/${slaughterId}/validate"]`));
  await page.locator('.sidebar a[href$="/livestock/pigs/shipments"]').click();await page.locator('[data-workspace-modal-open=flow-shipment]').click();form=page.locator('#flow-shipment form');
  const option=form.locator('[name=weight_stock_id] option').filter({hasText:slaughterNumber});await form.locator('[name=weight_stock_id]').selectOption(await option.getAttribute('value'));await form.locator('[name=quantity_kg]').fill('5');await form.locator('[name=truck_plate]').fill('BROWSER-01');await form.locator('[name=driver_name]').fill('Chauffeur navigateur');await save(form);
  const shipment=page.locator('[data-shipment-id]').first();const shipmentId=await shipment.getAttribute('data-shipment-id');
  await previewBtr(shipment,/BROUILLON/);
  await save(page.locator(`form[action$="/shipments/${shipmentId}/approve"]`));await previewBtr(shipment,/APPROUVÉ/);
  await save(page.locator(`form[action$="/shipments/${shipmentId}/dispatch"]`));await previewBtr(shipment,/EXPÉDIÉ/);
  await shipment.locator('summary').click();await shipment.locator('a[href$="/butchery/receipts"]').click();
  // Arrival weighing at the butchery (migration 042), then the sanitary control.
  await page.locator(`[data-transfer-id="${shipmentId}"] [data-workspace-modal-open="flow-arrival-${shipmentId}"]`).click();const arrival=page.locator(`#flow-arrival-${shipmentId} form`);await arrival.locator('[name=net_kg]').fill('5');await save(arrival);
  const pending=page.locator('[data-receipt-id]').first();const receiptId=await pending.getAttribute('data-receipt-id');await pending.locator(`[data-workspace-modal-open="flow-receive-${receiptId}"]`).click();form=page.locator(`#flow-receive-${receiptId} form`);
  await form.locator('[name=visual_conform]').check();await form.locator('[name=documents_conform]').check();await form.locator('[name=temperature_c]').fill('4');await save(form);
  await page.locator('.sidebar a[href$="/butchery/production"]').click();await page.locator('[data-workspace-modal-open=flow-transform]').click();form=page.locator('#flow-transform form');
  const sourceId=await form.locator('[data-transform-lot][data-quantity="5"] input').last().getAttribute('value');await sourceLot(form,sourceId);await form.locator('[name=quantity_kg]').fill('5');
  let line=form.locator('[data-flow-output]').first();await line.locator('select').selectOption(String(fixture.item1));await line.locator('[data-output-quantity]').fill('3');await line.locator('[type=date]').fill(fixture.expiry);
  await form.locator('[data-flow-add-output]').click();line=form.locator('[data-flow-output]').last();await line.locator('select').selectOption(String(fixture.item2));await line.locator('[data-output-quantity]').fill('1');await line.locator('[type=date]').fill(fixture.expiry);await form.locator('[data-transform-distribute]').check();await form.locator('[data-output-counter]').first().fill('2');await form.locator('[name=counter_id]').selectOption(String(fixture.counter));assert.match(await form.locator('[data-flow-balance]').textContent(),/1 kg d’écart/);await form.locator('[name=loss_confirmed]').check();await save(form);
  const order=page.locator('[data-order-id]').first();const orderId=await order.getAttribute('data-order-id');await order.locator('[popovertarget]').click();const validated=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/transformations/'+orderId+'/validate'));await page.locator('#production-actions-'+orderId).getByRole('button',{name:'Valider',exact:true}).click();await page.locator('[data-action-dialog-confirm]').click();assert((await(await validated).json()).ok);await page.locator('body.is-loading').waitFor({state:'hidden'});
  await page.locator('.sidebar a[href$="/butchery/counters"]').click();await page.locator('[data-workspace-modal-open=pos-prices]').click();form=page.locator('#pos-prices form');await form.locator('[name=item_id]').selectOption(String(fixture.item1));await form.locator('[name=currency]').selectOption('USD');await form.locator('[name=price]').fill('10');await save(form);
  await page.locator('[data-pos-counter]').selectOption(String(fixture.counter));await page.locator('[data-pos-item="'+fixture.item1+'"]').click();await page.locator('[data-pos-weight] [name=quantity]').fill('1');await page.locator('[data-pos-weight]').getByRole('button',{name:'Ajouter au panier',exact:true}).click();form=page.locator('[data-pos-payment]');await form.locator('[name=received_amount]').fill('10');await form.locator('[name=customer_name]').fill('Vente navigateur');const checkout=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/pos/checkout'));await page.locator('[data-pos-checkout]').click();assert((await(await checkout).json()).ok);await page.locator('#pos-receipt.is-open').waitFor();await page.locator('#pos-receipt').getByRole('button',{name:'Nouvelle vente',exact:true}).click();await page.getByRole('link',{name:'Reçus et ventes',exact:true}).click();assert(await page.locator('td').filter({hasText:'Vente navigateur'}).first().isVisible());
  // Refused write retains the complete multi-product form.
  await page.goto(base+'/butchery/production');await page.locator('[data-workspace-modal-open=flow-transform]').click();form=page.locator('#flow-transform form');
  await sourceLot(form,fixture.raw);await form.locator('[name=quantity_kg]').fill('1');line=form.locator('[data-flow-output]').first();await line.locator('select').selectOption(String(fixture.item1));await line.locator('[data-output-quantity]').fill('2');await line.locator('[type=date]').fill(fixture.expiry);assert(!(await form.locator('[name=quantity_kg]').evaluate(e=>e.checkValidity())));await page.keyboard.press('Escape');
  for(const width of [390,320]){
   await page.setViewportSize({width,height:900});
   for(const path of ['/livestock/pigs','/livestock/pigs/slaughters','/livestock/pigs/shipments','/butchery/receipts','/butchery/cold-room','/butchery/production','/butchery/counters']){
    await page.goto(base+path);await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth+1,{},{timeout:4000});
    assert(!(await page.locator('body').textContent()).includes('Warning:'));
    if(path==='/butchery/counters'||path==='/butchery/cold-room')await page.screenshot({path:`/private/tmp/dagril-flow-${path.split('/').pop()}-${width}.png`,fullPage:true});
    const opener=page.locator('[data-workspace-modal-open]').first();if(await opener.count()){await opener.click();assert(await page.locator('.workspace-entity-modal.is-open').isVisible());assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await page.keyboard.press('Escape');}
   }
  }
  assert.deepEqual(errors,[]);console.log('OK : parcours navigateur complet ferme → carcasses → réception → transformation multiple → comptoir → vente ; sept écrans sur mobile 390/320 px ; aucune erreur JavaScript.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
