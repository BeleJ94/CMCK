// Cattle flow: supplier reception, farm slaughter, validation, shipment, BTR preview, arrival weighing and control.
// Run against the disposable server described in travaux-agricoles-validation.md (base dagril_porcin_*_test with migration 043).
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const fixture=JSON.parse(execFileSync('php',[path.join(__dirname,'../integration/CattleBrowserFixture.php')],{env:process.env,encoding:'utf8'}));
const base=process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8107';
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  const confirm=async()=>{await page.locator('[data-action-dialog-confirm]').click();};
  await page.goto(base+'/livestock/cattle');
  await page.locator('[data-workspace-modal-open="cattle-receive"]').first().click();
  const receive=page.locator('#cattle-receive form');
  await receive.locator('[name=site_id]').selectOption(String(fixture.site));
  await receive.locator('[name=supplier_id]').selectOption(String(fixture.supplier));
  await receive.locator('[name=facility_id]').selectOption(String(fixture.barn));
  await receive.locator('[name=heads]').fill('6');
  await receive.locator('[name=delivery_reference]').fill('BL-UI-42');
  assert.match(await receive.locator('[data-cattle-receive-note]').textContent(),/50 place\(s\) libre\(s\)/,'Places libres du bâtiment');
  await receive.locator('.weighing-mode label:has([value=gross])').click();
  assert.match(await receive.locator('.weighing-mode label:has([value=gross]) small').textContent(),/camion ou bétaillère/,'Tare adaptée au poids vif');
  await receive.locator('[name=gross_kg]').fill('4000');await receive.locator('[name=tare_kg]').fill('1600');await receive.locator('[name=tare_note]').fill('Camion');
  assert.match(await receive.locator('[data-cattle-receive-note]').textContent(),/Poids vif moyen : 400 kg par tête/,'Poids vif moyen calculé');
  await receive.locator('footer button[type=submit]').click();await confirm();
  const lotRow=page.locator('table[aria-label="Lots de bovins"] tbody tr',{hasText:fixture.supplierName}).first();
  await lotRow.waitFor();
  assert.match(await lotRow.textContent(),/BL BL-UI-42[\s\S]*6[\s\S]*400,0/,'Lot : bon de livraison, effectif et poids vif moyen');

  await lotRow.locator('[data-workspace-modal-open="cattle-slaughter"]').click();
  const slaughter=page.locator('#cattle-slaughter form');
  assert.notEqual(await slaughter.locator('[name=batch_id]').inputValue(),'','Lot présélectionné depuis sa ligne');
  await slaughter.locator('[name=heads]').fill('2');await slaughter.locator('[name=net_kg]').fill('520');
  assert.match(await slaughter.locator('[data-cattle-slaughter-note]').textContent(),/260 kg par carcasse, rendement carcasse estimé 65 %/,'Poids par carcasse et rendement estimés');
  await slaughter.locator('footer button[type=submit]').click();await confirm();

  await page.goto(base+'/livestock/cattle/slaughters');
  const pending=page.locator('table[aria-label="Abattages de bovins"] tbody tr',{hasText:'520,000'}).first();
  await pending.locator('form[action$="/validate"] button[type=submit]').click();await confirm();
  const validated=page.locator('table[aria-label="Abattages de bovins"] tbody tr',{hasText:'Validé'}).filter({hasText:'520,000'}).first();await validated.waitFor();
  await validated.locator('[data-workspace-modal-open="cattle-shipment"]').click();
  const ship=page.locator('#cattle-shipment form');
  assert.notEqual(await ship.locator('[name=weight_stock_id]').inputValue(),'','Carcasses présélectionnées pour l’envoi');
  await ship.locator('[name=quantity_kg]').fill('520');await ship.locator('[name=truck_plate]').fill(fixture.plate);await ship.locator('[name=driver_name]').fill('Chauffeur navigateur');
  await ship.locator('footer button[type=submit]').click();await confirm();
  for(const action of ['approve','dispatch']){
   const row=page.locator('table[aria-label="Envois de carcasses de bœuf"] tbody tr',{hasText:fixture.plate}).first();await row.waitFor();
   await row.locator(`form[action$="/${action}"] button[type=submit]`).click();await confirm();await page.waitForTimeout(300);
  }
  const shipment=page.locator('table[aria-label="Envois de carcasses de bœuf"] tbody tr',{hasText:fixture.plate}).first();
  assert.match(await shipment.textContent(),/En route/);
  await shipment.locator('.row-menu-toggle').click();
  await page.locator('.row-menu:popover-open [role=menuitem]',{hasText:'Bon de transfert'}).click();
  await page.frameLocator('#btr-preview iframe').locator('.shipment-btr-document').waitFor();
  assert.match(await page.frameLocator('#btr-preview iframe').locator('.shipment-btr-document').textContent(),/Carcasses de bœuf[\s\S]*520,000/,'BTR des carcasses de bœuf');
  await page.keyboard.press('Escape');

  await page.goto(base+'/butchery/receipts');
  const incoming=page.locator('tr[data-kind="incoming"]',{hasText:fixture.plate}).first();
  assert.match(await incoming.textContent(),/Bovin/,'Réception : espèce Bovin');
  await incoming.locator('[data-workspace-modal-open^="flow-arrival-"]').click();
  const arrival=page.locator('.flow-editor.is-open form');
  await arrival.locator('[name=net_kg]').fill('512');
  assert.match(await arrival.locator('[data-weighing-gap-value]').textContent(),/Perte de 8,000 kg \(1,54 %\)/);
  await arrival.locator('[name=gap_reason]').fill('Ressuage des carcasses');
  await arrival.locator('footer button[type=submit]').click();await confirm();
  const control=page.locator('tr[data-kind="pending"]',{hasText:'512,000'}).first();await control.waitFor();
  await control.locator('[data-workspace-modal-open^="flow-receive-"]').click();
  const form=page.locator('.flow-editor.is-open form');
  for(const name of ['visual_conform','documents_conform','gap_approved'])await form.locator(`.receipt-control-check:has([name=${name}])`).click();
  await form.locator('footer button[type=submit]').click();await confirm();
  await page.locator('tr[data-kind="pending"]',{hasText:'512,000'}).waitFor({state:'detached'});

  await page.goto(base+'/livestock/cattle');
  const finished=page.locator('table[aria-label="Lots de bovins"] tbody tr',{hasText:fixture.supplierName}).first();
  await finished.locator('.row-menu-toggle').click();
  await page.waitForFunction(()=>document.activeElement?.matches('.row-menu [role=menuitem]'),null,{timeout:2000});
  await page.keyboard.press('Enter');
  const sheet=page.locator('.flow-editor.is-open .detail-sheet');await sheet.waitFor();
  assert.match(await sheet.textContent(),/Rendement carcasse\s*65,0 %[\s\S]*BL-UI-42[\s\S]*Abattus à la ferme\s*2/,'Fiche du lot : rendement, réception et effectifs');
  await page.keyboard.press('Escape');
  assert.deepEqual(errors,[]);

  for(const width of [390,320]){
   const mobile=await browser.newPage({viewport:{width,height:844},reducedMotion:'reduce'});
   for(const url of ['/livestock/cattle','/livestock/cattle/slaughters','/butchery/receipts']){
    await mobile.goto(base+url);
    assert(await mobile.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Pas de débordement à '+width+'px sur '+url);
   }
   await mobile.close();
  }
  console.log('Parcours bovin : parcours navigateur validé.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
