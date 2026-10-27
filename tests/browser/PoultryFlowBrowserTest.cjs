// Poultry flow: supplier reception, hatching, daily record, farm slaughter, carcass and live shipments, counted arrival at the butchery and slaughter of live birds.
// Run against the disposable server described in travaux-agricoles-validation.md (base dagril_porcin_*_test with migration 044).
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const fixture=JSON.parse(execFileSync('php',[path.join(__dirname,'../integration/PoultryBrowserFixture.php')],{env:process.env,encoding:'utf8'}));
const base=process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8107';
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  const confirm=async()=>{await page.locator('[data-action-dialog-confirm]').click();};
  const lots=()=>page.locator('table[aria-label="Lots de volailles"] tbody tr');
  const menu=async(row,label)=>{await row.locator('.row-menu-toggle').click();await page.locator('.row-menu:popover-open [role=menuitem]',{hasText:label}).click();};
  await page.goto(base+'/livestock/poultry');

  // Supplier reception of broiler chicks.
  await page.locator('.ui-page-header [data-workspace-modal-open="poultry-receive"]').click();
  const receive=page.locator('#poultry-receive form');
  await receive.locator('.poultry-type-choice label:has([value=broiler])').click();
  await receive.locator('[name=site_id]').selectOption(String(fixture.site));
  await receive.locator('[name=supplier_id]').selectOption(String(fixture.supplier));
  await receive.locator('[name=facility_id]').selectOption(String(fixture.house));
  await receive.locator('[name=heads]').fill('300');await receive.locator('[name=age_days]').fill('1');await receive.locator('[name=delivery_reference]').fill('BL-POUS-UI');
  assert.match(await receive.locator('[data-poultry-receive-note]').textContent(),/900 place\(s\) libre\(s\)/,'Places libres du poulailler, pondeuses déduites');
  await receive.locator('.weighing-mode label:has([value=gross])').click();await receive.locator('[name=gross_kg]').fill('42');await receive.locator('[name=tare_kg]').fill('12');
  assert.match(await receive.locator('[data-poultry-receive-note]').textContent(),/Poids vif moyen : 0,1 kg par oiseau/,'Poids vif moyen des poussins');
  await receive.locator('footer button[type=submit]').click();await confirm();
  const broilerRow=lots().filter({hasText:fixture.supplierName}).filter({hasText:'Chair'}).first();await broilerRow.waitFor();
  assert.match(await broilerRow.textContent(),/J\+1[\s\S]*300/,'Lot de chair : J+1 et 300 poussins');

  // Daily record from the row: dead birds leave the lot at once.
  await broilerRow.locator('[data-workspace-modal-open="poultry-record"]').click();
  const record=page.locator('#poultry-record form');
  assert(await record.locator('[data-poultry-eggs]').isHidden(),'Pas d’œufs sur un lot de chair');
  await record.locator('[name=mortality_heads]').fill('4');await record.locator('[name=notes]').fill('Chaleur');
  await record.locator('footer button[type=submit]').click();await confirm();
  await page.waitForFunction(()=>Array.from(document.querySelectorAll('table[aria-label="Lots de volailles"] tbody tr')).some(row=>/Chair/.test(row.textContent)&&/4\s*mort/.test(row.textContent)));
  assert.match(await lots().filter({hasText:fixture.supplierName}).filter({hasText:'Chair'}).first().textContent(),/296/,'296 poussins après 4 morts');

  // Hatching from the laying lot of the fixture.
  const layerRow=lots().filter({hasText:'Ponte'}).filter({hasText:fixture.houseName}).first();
  await menu(layerRow,'Enregistrer une éclosion');
  const hatch=page.locator('#poultry-hatch form');
  assert.equal(await hatch.locator('[name=mother_batch_id]').inputValue(),String(fixture.layers),'Pondeuses présélectionnées');
  await hatch.locator('[name=facility_id]').selectOption(String(fixture.house));await hatch.locator('[name=heads]').fill('40');
  await hatch.locator('footer button[type=submit]').click();await confirm();
  await lots().filter({hasText:'Éclosion'}).filter({hasText:fixture.houseName}).first().waitFor();

  // Farm slaughter and live shipment from the broiler lot.
  await menu(lots().filter({hasText:fixture.supplierName}).filter({hasText:'Chair'}).first(),'Abattre des oiseaux');
  const slaughter=page.locator('#poultry-slaughter form');
  await slaughter.locator('[name=heads]').fill('100');await slaughter.locator('[name=dead_heads]').fill('2');await slaughter.locator('[name=net_kg]').fill('180');
  assert.match(await slaughter.locator('[data-poultry-slaughter-note]').textContent(),/1,8 kg par carcasse/,'Poids par carcasse');
  await slaughter.locator('footer button[type=submit]').click();await confirm();
  await page.waitForTimeout(300);
  await menu(lots().filter({hasText:fixture.supplierName}).filter({hasText:'Chair'}).first(),'Envoyer vivants');
  const live=page.locator('#poultry-live-shipment form');
  assert.match(await live.locator('[data-poultry-live-note]').textContent(),/194 poulet\(s\) disponible\(s\)/,'Poulets engagés dans l’abattage en attente déduits');
  await live.locator('[name=heads]').fill('50');await live.locator('.weighing-mode label:has([value=gross])').click();await live.locator('[name=gross_kg]').fill('110');await live.locator('[name=tare_kg]').fill('10');
  await live.locator('[name=truck_plate]').fill(fixture.livePlate);await live.locator('[name=driver_name]').fill('Chauffeur vif navigateur');
  await live.locator('footer button[type=submit]').click();await confirm();

  // Validation, carcass shipment counted in pieces, approvals and dispatch.
  await page.goto(base+'/livestock/poultry/slaughters');
  const pending=page.locator('table[aria-label="Abattages de volailles"] tbody tr',{hasText:'180,000'}).first();
  await pending.locator('form[action$="/validate"] button[type=submit]').click();await confirm();
  const validated=page.locator('table[aria-label="Abattages de volailles"] tbody tr',{hasText:'Validé'}).filter({hasText:'180,000'}).first();await validated.waitFor();
  await validated.locator('[data-workspace-modal-open="poultry-shipment"]').click();
  const ship=page.locator('#poultry-shipment form');
  await ship.locator('[name=quantity_pieces]').fill('40');
  assert.equal(await ship.locator('[name=quantity_kg]').inputValue(),'72.000','Poids proposé selon les carcasses');
  await ship.locator('[name=truck_plate]').fill(fixture.plate);await ship.locator('[name=driver_name]').fill('Chauffeur navigateur');
  await ship.locator('footer button[type=submit]').click();await confirm();
  for(const plate of [fixture.plate,fixture.livePlate])for(const action of ['approve','dispatch']){
   const row=page.locator('table[aria-label="Envois de volailles"] tbody tr',{hasText:plate}).first();await row.waitFor();
   await row.locator(`form[action$="/${action}"] button[type=submit]`).click();await confirm();await page.waitForTimeout(300);
  }
  const liveRow=page.locator('table[aria-label="Envois de volailles"] tbody tr',{hasText:fixture.livePlate}).first();
  assert.match(await liveRow.textContent(),/Poulets vivants[\s\S]*100,000[\s\S]*50 poulet\(s\)[\s\S]*En route/,'Envoi vivant : 50 poulets, 100 kg nets, en route');
  await liveRow.locator('.row-menu-toggle').click();
  await page.locator('.row-menu:popover-open [role=menuitem]',{hasText:'Bon de transfert'}).click();
  await page.frameLocator('#btr-preview iframe').locator('.shipment-btr-document').waitFor();
  assert.match(await page.frameLocator('#btr-preview iframe').locator('.shipment-btr-document').textContent(),/Poulets vivants[\s\S]*50 poulet\(s\) vivant\(s\)[\s\S]*Cages|Poulets vivants[\s\S]*50 poulet\(s\) vivant\(s\)/,'BTR des poulets vivants');
  await page.keyboard.press('Escape');

  // Butchery: counted and weighed arrival, then control and sale lot.
  await page.goto(base+'/butchery/receipts');
  const incoming=page.locator('tr[data-kind="incoming"]',{hasText:fixture.livePlate}).first();
  assert.match(await incoming.textContent(),/Poulet vivant[\s\S]*50 poulet\(s\)/,'Réception : poulets vivants attendus');
  await incoming.locator('[data-workspace-modal-open^="flow-arrival-"]').click();
  const arrival=page.locator('.flow-editor.is-open form');
  await arrival.locator('[name=received_pieces]').fill('49');
  assert.match(await arrival.locator('[data-weighing-pieces-note]').textContent(),/1 manquant\(s\) sur 50/,'Poulet manquant signalé');
  await arrival.locator('[name=net_kg]').fill('98');
  assert(await arrival.locator('[name=gap_reason]').isVisible(),'Motif demandé pour le poulet manquant, poids dans la tolérance');
  await arrival.locator('[name=gap_reason]').fill('Un poulet mort pendant le transport');
  await arrival.locator('footer button[type=submit]').click();await confirm();
  const control=page.locator('tr[data-kind="pending"]',{hasText:'98,000'}).first();await control.waitFor();
  await control.locator('[data-workspace-modal-open^="flow-receive-"]').click();
  const form=page.locator('.flow-editor.is-open form');
  assert.match(await form.textContent(),/49 sur 50[\s\S]*Mise en vente des poulets vivants/,'Contrôle : comptage et mise en vente');
  for(const name of ['visual_conform','documents_conform'])await form.locator(`.receipt-control-check:has([name=${name}])`).click();
  await form.locator('footer button[type=submit]').click();await confirm();
  await page.locator('tr[data-kind="pending"]',{hasText:'98,000'}).waitFor({state:'detached'});

  // Slaughter of live birds at the butchery.
  await page.goto(base+'/butchery/production');
  const liveLot=page.locator('table[aria-label="Lots de poulets vivants"] tbody tr').first();
  assert.match(await liveLot.textContent(),/49[\s\S]*98,000 kg/,'Lot vivant : 49 poulets en réserve');
  await liveLot.locator('[data-workspace-modal-open="poultry-butchery-slaughter"]').click();
  const kill=page.locator('#poultry-butchery-slaughter form');
  await kill.locator('[name=heads]').fill('7');await kill.locator('[name=net_kg]').fill('9.8');
  assert.match(await kill.locator('[data-poultry-butchery-note]').textContent(),/1,4 kg par carcasse, rendement estimé 70 %/,'Rendement sur le poids vif prélevé');
  await kill.locator('footer button[type=submit]').click();await confirm();
  await page.locator('table[aria-label="Abattages de poulets à la boucherie"] tbody tr',{hasText:'9,800'}).waitFor();
  assert.match(await page.locator('table[aria-label="Lots de poulets vivants"] tbody tr').first().textContent(),/42/,'42 poulets restent dans le lot vivant');
  assert.deepEqual(errors,[]);

  for(const width of [390,320]){
   const mobile=await browser.newPage({viewport:{width,height:844},reducedMotion:'reduce'});
   for(const url of ['/livestock/poultry','/livestock/poultry/slaughters','/butchery/production','/butchery/receipts']){
    await mobile.goto(base+url);
    assert(await mobile.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Pas de débordement à '+width+'px sur '+url);
   }
   await mobile.close();
  }
  console.log('Parcours volaille : parcours navigateur validé.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
