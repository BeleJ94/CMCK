// Transfer shipment and receipt forms: one quantity per product and format spread over its lots oldest first,
// shortcuts, live summary, rejection with its reason. Run against the disposable server of travaux-agricoles-validation.md.
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const fixture=JSON.parse(execFileSync('php',[path.join(__dirname,'../integration/TransferOperationsBrowserFixture.php')],{env:process.env,encoding:'utf8'}));
const base=process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8107';
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  // Both forms stay within the screen on phones.
  async function mobile(opener,modal){
   for(const width of [390,320]){
    await page.setViewportSize({width,height:900});await page.locator(opener).first().click();
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
    assert(await page.locator(modal+' .transfer-op').first().evaluate(el=>el.scrollWidth<=el.clientWidth+1),'Formulaire sans débordement à '+width+' px');await page.keyboard.press('Escape');
   }
   await page.setViewportSize({width:1440,height:1000});
  }
  const lots=form=>Promise.all(fixture.lots.map(()=>null)).then(()=>form.locator('[data-op-lot-input]').evaluateAll(inputs=>inputs.map(input=>Number(input.value))));
  await page.goto(base+'/transfers/'+fixture.transfer);
  await mobile('[data-workspace-modal-open="transfer-ship"]','#transfer-ship');

  // Shipment: everything proposed, one quantity for the three lots.
  await page.locator('[data-workspace-modal-open="transfer-ship"]').click();
  let form=page.locator('#transfer-ship form');
  assert.equal(await form.locator('[data-op-group]').count(),1,'Une seule ligne pour le même produit et format');
  const total=form.locator('[data-op-total]');
  assert.equal(await total.inputValue(),'118');assert.match(await form.locator('.transfer-op__row').innerText(),/3 lots[\s\S]*118[\s\S]*2\s950 kg/);
  assert.match(await form.locator('[data-op-summary]').innerText(),/118 sac\(s\) · 2\s950 kg · tout part/);
  await total.fill('100');assert.deepEqual(await lots(form),[82,18,0],'Répartition sur les lots les plus anciens');
  assert.match(await form.locator('[data-op-summary]').innerText(),/100 sac\(s\) · 2\s500 kg · 18 restera à expédier/);
  assert.equal((await form.locator('[data-op-submit]').innerText()).trim(),'Expédier 100 sac(s)');
  await form.locator('[data-op-fill="none"]').click();assert.equal(await total.inputValue(),'0');assert.match(await form.locator('[data-op-summary]').innerText(),/Aucun sac à expédier/);
  await form.locator('[data-op-fill="all"]').click();assert.deepEqual(await lots(form),[82,30,6]);
  await form.locator('.transfer-op__lots > summary').click();await form.locator('[data-op-lot-input]').nth(2).fill('0');
  assert.equal(await total.inputValue(),'112','Le total suit l’ajustement d’un lot');
  await form.locator('[name=vehicle_reference]').fill('CAM-25');
  // Confirmation summary: « Modifier » goes back to the form, then the shipment is confirmed.
  const dialog=page.locator('[data-op-confirm-dialog]');
  await form.locator('[data-op-submit]').click();await dialog.waitFor({state:'visible'});
  assert.match(await dialog.locator('h2').innerText(),/Confirmer l’expédition/);assert.match(await dialog.innerText(),/CAM-25/);
  assert.match(await dialog.locator('.op-confirm__lines').innerText(),/Farine de mais · Sac 25kg · 112 sac\(s\) · 2\s800 kg[\s\S]*: 82 · Lot #\d+ : 30/);
  assert.match(await dialog.locator('.op-confirm__total').innerText(),/112 sac\(s\) · 2\s800 kg · 6 restera à expédier/);
  await dialog.getByRole('button',{name:'Modifier'}).click();assert(await dialog.isHidden());assert(await form.isVisible(),'Retour au formulaire');
  let response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/ship'));
  await form.locator('[data-op-submit]').click();await dialog.locator('[data-op-confirm-ok]').click();assert.equal((await response).status(),200);await page.waitForURL(url=>url.pathname.endsWith('/transfers/'+fixture.transfer));await page.waitForLoadState('load');

  // Receipt: everything accepted; a rejection lowers the accepted bags and asks for its reason.
  await mobile('[data-workspace-modal-open^="transfer-receive-"]','[id^="transfer-receive-"]');
  await page.locator('[data-workspace-modal-open^="transfer-receive-"]').first().click();
  form=page.locator('[data-transfer-receive]').first();
  assert.deepEqual(await form.locator('[data-accepted]').evaluateAll(inputs=>inputs.map(input=>Number(input.value))),[82,30]);
  assert(await form.locator('[data-op-note]').first().isHidden(),'Pas de motif sans refus');
  await form.locator('[data-rejected]').nth(1).fill('5');
  assert.equal(await form.locator('[data-accepted]').nth(1).inputValue(),'25','Les refusés sont retirés des acceptés');
  assert(await form.locator('[data-op-note]').nth(1).isVisible());
  assert.match(await form.locator('[data-op-summary]').innerText(),/107 accepté\(s\) · 5 refusé\(s\), renvoyé\(s\) au départ/);
  await form.locator('[data-op-fill-all="none"]').click();assert.match(await form.locator('[data-op-summary]').innerText(),/Aucun sac saisi/);
  await form.locator('[data-op-fill-all="accept"]').click();await form.locator('[data-rejected]').nth(1).fill('5');await form.locator('[name^="quality_notes"]').nth(1).fill('Sacs déchirés');
  response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/receive'));
  await form.locator('[data-op-submit]').click();await page.locator('[data-op-confirm-dialog]').waitFor({state:'visible'});
  const receipt=page.locator('[data-op-confirm-dialog]');
  assert.match(await receipt.locator('h2').innerText(),/Confirmer la réception/);
  assert.match(await receipt.locator('.op-confirm__lines .is-warning').innerText(),/25 accepté\(s\) · 5 refusé\(s\) : Sacs déchirés/);
  assert.match(await receipt.locator('.op-confirm__total').innerText(),/107 accepté\(s\) · 5 refusé\(s\)/);assert.match(await receipt.locator('.op-confirm__note').innerText(),/retour vers Minoterie/);
  await receipt.locator('[data-op-confirm-ok]').click();assert.equal((await response).status(),200);await page.waitForURL(url=>url.pathname.endsWith('/transfers/'+fixture.transfer));await page.waitForLoadState('load');
  assert.match(await page.locator('body').innerText(),/Sacs déchirés|Non-conformit/);

  assert.deepEqual(errors,[]);
  console.log('OK : expédition en une saisie par produit répartie par lot, raccourcis Tout/0, total suivant les lots, réception acceptée par défaut, refus avec motif, confirmation récapitulative (Modifier / Confirmer), mobile 390/320.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
