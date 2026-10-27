// Pig carcasses and shipments page: stock table, shipment preparation from a stock, approval, transfer note, detail and cancellation drawers.
// Run against the disposable server described in travaux-agricoles-validation.md (base dagril_porcin_*_test with migration 042).
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const fixture=JSON.parse(execFileSync('php',[path.join(__dirname,'../integration/PigShipmentsBrowserFixture.php')],{env:process.env,encoding:'utf8'}));
const base=process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8107';
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  async function save(form){
   const action=await form.getAttribute('action');const response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url()===action);
   await form.locator('button[type=submit]').click();await page.locator('[data-action-dialog-confirm]').click();const payload=await(await response).json();assert(payload.ok,payload.message);
   await page.locator('body.is-loading').waitFor({state:'hidden'});await page.waitForFunction(()=>document.querySelector('[data-flow-directory]')?.dataset.ready==='1');
  }
  async function menu(id,label){await page.locator(`#${id}-toggle`).click();await page.locator(`#${id}`).getByRole('menuitem',{name:label}).click();}
  const stock=()=>page.locator(`[data-stock-id="${fixture.stock}"]`);
  const shipment=()=>page.locator('[data-shipment-id]',{hasText:fixture.plate});

  await page.goto(base+'/livestock/pigs/shipments');
  for(const label of ['Carcasses à envoyer','Envois à traiter','En route','Écart ferme → boucherie'])assert.equal(await page.locator('.ui-kpi__label',{hasText:label}).count(),1,label);
  assert.match(await stock().innerText(),/150,000/);assert.match(await stock().innerText(),/Disponible/);assert.match(await stock().innerText(),/Dans 4 j/);

  // Stock sheet, then a shipment prepared from it: the stock is preselected.
  await menu('pig-stock-menu-'+fixture.stock,'Consulter le stock');
  let sheet=page.locator(`#pig-stock-detail-${fixture.stock}`);
  assert.match(await sheet.innerText(),new RegExp(fixture.facility));assert.match(await sheet.innerText(),/25,0 kg par carcasse/);assert.match(await sheet.innerText(),/Aucun envoi pour ce stock/);
  await sheet.getByRole('button',{name:'Préparer un envoi'}).click();
  let form=page.locator('#flow-shipment form');
  assert.equal(await form.locator('[name=weight_stock_id]').inputValue(),String(fixture.stock),'Stock présélectionné');
  await form.locator('[name=quantity_kg]').fill('151');assert(!(await form.locator('[name=quantity_kg]').evaluate(el=>el.checkValidity())),'Quantité limitée au stock disponible');
  await form.locator('[name=quantity_kg]').fill('60');assert.match(await form.locator('[data-flow-preview]').innerText(),/Disponible : 150 kg · Reste prévu : 90/);
  await form.locator('[name=truck_plate]').fill(fixture.plate);await form.locator('[name=driver_name]').fill('Chauffeur envois');
  await save(form);
  assert.match(await shipment().innerText(),/À approuver/);assert.match(await shipment().innerText(),/60,000/);

  // Approval from the row: the carcasses are reserved.
  await save(shipment().locator('form[action$="/approve"]'));
  assert.match(await shipment().innerText(),/À expédier/);
  assert.match(await stock().innerText(),/60,000 réservés/);assert.match(await stock().innerText(),/90,000/);

  // Transfer note and shipment sheet from the row menu.
  const id=await shipment().getAttribute('data-shipment-id');
  await menu('pig-shipment-menu-'+id,'Bon de transfert');
  const btr=page.locator('#btr-preview');await btr.locator('[data-btr-frame].is-ready').waitFor();
  assert.match(await btr.locator('[data-btr-title]').innerText(),/^BTR-PORC-/);
  const btrFrame=page.frameLocator('#btr-preview [data-btr-frame]');
  assert.match(await btrFrame.locator('.shipment-btr-document').innerText(),new RegExp(fixture.plate));assert.match(await btrFrame.locator('.btr-status').innerText(),/APPROUVÉ/);
  const doc=await(await page.locator('#btr-preview [data-btr-frame]').elementHandle()).contentFrame();
  await doc.evaluate(()=>{window.print=()=>{window.__btrPrinted=true;};});
  await btr.locator('[data-btr-print]').click();assert(await doc.evaluate(()=>window.__btrPrinted===true),'Le bouton imprime seulement le document');
  const pdf=await page.request.get(await btr.locator('[data-btr-pdf]').getAttribute('href'));
  assert.equal(pdf.status(),200);assert.match(pdf.headers()['content-type'],/application\/pdf/);assert.equal((await pdf.body()).subarray(0,5).toString(),'%PDF-');
  await doc.locator('body').press('Escape');assert(!(await btr.isVisible()),'Échap ferme aussi depuis l’aperçu');
  await menu('pig-shipment-menu-'+id,'Consulter l’envoi');
  sheet=page.locator(`#pig-shipment-detail-${id}`);
  assert.match(await sheet.innerText(),/Double pesée/i);assert.match(await sheet.innerText(),/En attente|Non pesé/);assert.match(await sheet.innerText(),new RegExp(fixture.conversion));
  assert.equal(await sheet.locator('form[action$="/dispatch"]').count(),1,'Expédition proposée dans la fiche');
  await page.keyboard.press('Escape');

  // Cancellation with a reason frees the reservation.
  await menu('pig-shipment-menu-'+id,'Annuler l’envoi');
  form=page.locator(`#pig-shipment-cancel-${id} form`);await form.locator('[name=reason]').fill('Camion indisponible');await save(form);
  assert.match(await shipment().innerText(),/Annulé/);assert.doesNotMatch(await stock().innerText(),/réservés/);
  await menu('pig-stock-menu-'+fixture.stock,'Consulter le stock');
  assert.match(await page.locator(`#pig-stock-detail-${fixture.stock}`).innerText(),/Annulé/);await page.keyboard.press('Escape');

  for(const width of [390,320]){
   await page.setViewportSize({width,height:900});await page.goto(base+'/livestock/pigs/shipments');
   await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth+1,{},{timeout:4000});
   await menu('pig-stock-menu-'+fixture.stock,'Consulter le stock');assert(await page.locator('.workspace-entity-modal.is-open').isVisible());
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await page.keyboard.press('Escape');
   await menu('pig-shipment-menu-'+id,'Bon de transfert');await page.locator('#btr-preview [data-btr-frame].is-ready').waitFor();
   assert(await page.locator('#btr-preview').evaluate(e=>e.scrollWidth<=e.clientWidth+1),'Aperçu du BTR sans débordement horizontal');await page.keyboard.press('Escape');
  }
  assert.deepEqual(errors,[]);
  console.log('OK : stock de carcasses, envoi préparé depuis un stock, approbation et réservation, bon de transfert (impression, PDF, Échap), fiches, annulation motivée, mobile 390/320.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
