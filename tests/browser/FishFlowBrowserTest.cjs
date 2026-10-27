// Fish farming and double weighing: stocking, final harvest, shipment, arrival weighing with a gap, control and report.
// Run against the disposable server described in travaux-agricoles-validation.md (base dagril_porcin_*_test with migration 042).
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const fixture=JSON.parse(execFileSync('php',[path.join(__dirname,'../integration/FishBrowserFixture.php')],{env:process.env,encoding:'utf8'}));
const base=process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8107';
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  const confirm=async()=>{await page.locator('[data-action-dialog-confirm]').click();};
  const parcel=()=>page.locator(`[data-fish-parcel="${fixture.parcel}"]`).first();
  await page.goto(base+'/livestock/fish');
  assert.match(await parcel().locator('.ui-status').textContent(),/Libre/);
  await parcel().locator('[data-workspace-modal-open="fish-stock"]').click();
  const stock=page.locator('#fish-stock form');
  assert.equal(await stock.locator('[name=parcel_id]').inputValue(),String(fixture.parcel),'Parcelle présélectionnée depuis sa carte');
  await stock.locator('[name=heads]').fill('500');await stock.locator('[name=stocked_weight_kg]').fill('2.5');
  await stock.locator('footer button[type=submit]').click();await confirm();
  await page.waitForFunction(id=>document.querySelector(`.fish-parcel[data-fish-parcel="${id}"]`)?.classList.contains('is-occupied'),fixture.parcel);
  assert.match(await parcel().locator('.ui-status').textContent(),/En élevage/);

  await parcel().locator('[data-workspace-modal-open="fish-harvest"]').click();
  const harvest=page.locator('#fish-harvest form');
  await harvest.locator('[name=heads]').fill('480');
  await harvest.locator('.fish-final-toggle').click();
  assert.match(await harvest.locator('[data-fish-harvest-note]').textContent(),/20 poisson\(s\) non pêché\(s\).*Survie du lot : 96/);
  await harvest.locator('.weighing-mode label:has([value=gross])').click();
  assert(await harvest.locator('[name=net_kg]').isDisabled()&&await harvest.locator('[name=gross_kg]').isVisible(),'Bascule net / brut − tare');
  await harvest.locator('[name=gross_kg]').fill('250');await harvest.locator('[name=tare_kg]').fill('10');await harvest.locator('[name=tare_note]').fill('Glace et caisses');
  assert.match(await harvest.locator('[data-weighing-result]').textContent(),/240,000 kg/,'Poids net calculé');
  await harvest.locator('footer button[type=submit]').click();await confirm();
  await page.waitForFunction(id=>/Pêche finale à valider/.test(document.querySelector(`.fish-parcel[data-fish-parcel="${id}"] .ui-status`)?.textContent||''),fixture.parcel);

  await page.goto(base+'/livestock/fish/harvests');
  const row=page.locator('table[aria-label="Pêches"] tbody tr',{hasText:fixture.pondName.split(' ').pop()}).first();
  assert.equal(await row.locator('details').count(),0,'Actions compactes : plus de formulaires dépliés dans la ligne');
  await row.locator('.row-menu-toggle').click();
  const menu=page.locator('.row-menu:popover-open');
  assert.deepEqual(await menu.locator('[role=menuitem]').allTextContents(),['Consulter la pêche','Annuler la pêche'],'Menu des actions secondaires');
  await page.waitForFunction(()=>document.activeElement?.matches('.row-menu [role=menuitem]'),null,{timeout:2000});
  assert(await menu.locator('[role=menuitem]').first().evaluate(e=>e===document.activeElement),'Focus sur la première action du menu');
  const [toggleBox,menuBox]=[await row.locator('.row-menu-toggle').boundingBox(),await menu.boundingBox()];
  assert(Math.abs(menuBox.y-(toggleBox.y+toggleBox.height))<=12&&Math.abs(menuBox.x+menuBox.width-(toggleBox.x+toggleBox.width))<=2,'Menu placé sous son bouton, aligné à droite');
  await page.keyboard.press('ArrowDown');assert(await menu.locator('[role=menuitem]').nth(1).evaluate(e=>e===document.activeElement),'Navigation au clavier dans le menu');
  await page.keyboard.press('ArrowUp');await page.keyboard.press('Enter');
  await page.locator('.flow-editor.is-open .detail-sheet').waitFor();
  assert.match(await page.locator('.flow-editor.is-open .detail-sheet').textContent(),/Poids brut[\s\S]*250,000 kg[\s\S]*Glace et caisses/,'Fiche de la pêche avec la pesée brut − tare');
  await page.keyboard.press('Escape');
  assert(await row.locator('.row-menu-toggle').evaluate(e=>e===document.activeElement),'Focus rendu au bouton du menu');
  await row.locator('form[action$="/validate"] button[type=submit]').click();await confirm();
  const validated=page.locator('table[aria-label="Pêches"] tbody tr',{hasText:'Validée'}).filter({hasText:'240,000'}).first();
  await validated.waitFor();
  await validated.locator('[data-workspace-modal-open="fish-shipment"]').click();
  const ship=page.locator('#fish-shipment form');
  assert.notEqual(await ship.locator('[name=weight_stock_id]').inputValue(),'','Pêche présélectionnée pour l’envoi');
  await ship.locator('[name=quantity_kg]').fill('240');await ship.locator('[name=truck_plate]').fill('FISH-UI');await ship.locator('[name=driver_name]').fill('Chauffeur navigateur');
  await ship.locator('footer button[type=submit]').click();await confirm();
  for(const action of ['approve','dispatch']){
   const shipment=page.locator('table[aria-label="Envois de poisson"] tbody tr',{hasText:'FISH-UI'}).first();await shipment.waitFor();
   await shipment.locator(`form[action$="/${action}"] button[type=submit]`).click();await confirm();
   await page.waitForTimeout(300);
  }
  assert.match(await page.locator('table[aria-label="Envois de poisson"] tbody tr',{hasText:'FISH-UI'}).first().textContent(),/En route/);
  const shipRow=page.locator('table[aria-label="Envois de poisson"] tbody tr',{hasText:'FISH-UI'}).first();
  await shipRow.locator('.row-menu-toggle').click();
  await page.locator('.row-menu:popover-open [role=menuitem]',{hasText:'Bon de transfert'}).click();
  const btr=page.locator('#btr-preview');await btr.waitFor({state:'visible'});
  await page.frameLocator('#btr-preview iframe').locator('.shipment-btr-document').waitFor();
  assert.match(await page.frameLocator('#btr-preview iframe').locator('.shipment-btr-document').textContent(),/Poisson · Ferme → Boucherie[\s\S]*240,000/,'Aperçu du BTR chargé dans la modale');
  assert.match(await btr.locator('[data-btr-title]').textContent(),/^BTR-POIS-/);
  assert.match(await btr.locator('[data-btr-pdf]').getAttribute('href'),/\/btr\?export=pdf$/,'Téléchargement PDF du BTR');
  await page.keyboard.press('Escape');await btr.waitFor({state:'hidden'});
  assert(await shipRow.locator('.row-menu-toggle').evaluate(e=>e===document.activeElement),'Focus rendu au menu de la ligne');

  await page.goto(base+'/butchery/receipts');
  const incoming=page.locator('tr[data-kind="incoming"]',{hasText:'FISH-UI'}).first();
  assert.match(await incoming.textContent(),/À peser[\s\S]*Tilapia|Tilapia[\s\S]*À peser/,'Réception : espèce et étape lisibles');
  await incoming.locator('.row-menu-toggle').click();
  assert.deepEqual(await page.locator('.row-menu:popover-open [role=menuitem]').allTextContents(),['Consulter l’envoi','Bon de transfert'],'Menu de la réception');
  await page.locator('.row-menu:popover-open [role=menuitem]',{hasText:'Bon de transfert'}).click();
  await page.frameLocator('#btr-preview iframe').locator('.shipment-btr-document').waitFor();
  assert.match(await page.frameLocator('#btr-preview iframe').locator('.shipment-btr-document').textContent(),/Poisson · Ferme → Boucherie/,'BTR consultable depuis la boucherie');
  await page.keyboard.press('Escape');await page.locator('#btr-preview').waitFor({state:'hidden'});
  await incoming.locator('[data-workspace-modal-open^="flow-arrival-"]').click();
  const arrival=page.locator('.flow-editor.is-open form');
  await arrival.locator('[name=net_kg]').fill('230');
  assert.match(await arrival.locator('[data-weighing-gap-value]').textContent(),/Perte de 10,000 kg \(4,17 %\)/,'Écart calculé en direct');
  assert(await arrival.locator('[name=gap_reason]').isVisible()&&await arrival.locator('[name=gap_reason]').evaluate(e=>e.required),'Motif exigé hors tolérance');
  await arrival.locator('[name=gap_reason]').fill('Égouttage pendant le transport');
  await arrival.locator('footer button[type=submit]').click();await confirm();
  const pending=page.locator('tr[data-kind="pending"]',{hasText:'230,000'}).first();await pending.waitFor();
  assert.match(await pending.textContent(),/Perte de 10,000 kg/);
  await pending.locator('.row-menu-toggle').click();await page.locator('.row-menu:popover-open [role=menuitem]',{hasText:'Consulter la réception'}).click();
  const receiptSheet=page.locator('.flow-editor.is-open .detail-sheet');await receiptSheet.waitFor();
  assert.match(await receiptSheet.textContent(),/Pesé à l’arrivée[\s\S]*230,000 kg[\s\S]*Égouttage pendant le transport/,'Fiche de la réception : double pesée et motif');
  await page.locator('.flow-editor.is-open footer [data-workspace-modal-open^="flow-receive-"]').click();
  const control=page.locator('.flow-editor.is-open form');
  for(const name of ['visual_conform','documents_conform'])await control.locator(`.receipt-control-check:has([name=${name}])`).click();
  assert(await control.locator('footer button[type=submit]').isDisabled(),'Entrée au froid bloquée tant que l’écart n’est pas validé');
  await control.locator('.receipt-control-check:has([name=gap_approved])').click();
  await control.locator('footer button[type=submit]').click();await confirm();
  await page.locator('tr[data-kind="pending"]',{hasText:'230,000'}).waitFor({state:'detached'});

  await page.goto(base+'/butchery/weighing-gaps');
  const report=page.locator('table[aria-label="Détail des écarts de pesée"] tbody tr',{hasText:'FISH-UI'}).first();
  assert.match(await report.textContent(),/Hors tolérance/);assert.match(await report.textContent(),/Égouttage pendant le transport/);
  await page.goto(base+'/livestock/fish');
  const lotRow=page.locator('table[aria-label="Bilan des lots de poissons terminés"] tbody tr',{hasText:fixture.pondName}).first();
  assert.match(await lotRow.textContent(),/96,0 %/,'Bilan : survie du lot terminé');
  await lotRow.locator('.row-menu-toggle').click();
  await page.waitForFunction(()=>document.activeElement?.matches('.row-menu [role=menuitem]'),null,{timeout:2000});
  await page.keyboard.press('Enter');
  const sheet=page.locator('.flow-editor.is-open .detail-sheet');await sheet.waitFor();
  assert.match(await sheet.textContent(),/Pêches du lot\s*1[\s\S]*480 poissons[\s\S]*Perte de 10,000 kg/,'Fiche bilan : pêches et écart de transport');
  await page.locator('.flow-editor.is-open footer [data-workspace-modal-open="fish-stock"]').click();
  assert.equal(await page.locator('#fish-stock [name=parcel_id]').inputValue(),String(fixture.parcel),'Remise en charge : parcelle présélectionnée');
  await page.keyboard.press('Escape');
  assert.deepEqual(errors,[]);

  for(const width of [390,320]){
   const mobile=await browser.newPage({viewport:{width,height:844},reducedMotion:'reduce'});
   for(const url of ['/livestock/fish','/livestock/fish/harvests','/butchery/receipts','/butchery/weighing-gaps']){
    await mobile.goto(base+url);
    assert(await mobile.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Pas de débordement à '+width+'px sur '+url);
   }
   await mobile.close();
  }
  console.log('Pisciculture et double pesée : parcours navigateur validé.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
