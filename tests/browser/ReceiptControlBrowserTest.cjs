const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const helper=path.join(__dirname,'../integration/ReceiptControlFixture.php');
const base=process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8110';
function fixtureCommand(args=[]){return JSON.parse(execFileSync('php',[helper,...args],{env:process.env,encoding:'utf8'}));}

(async()=>{
 const fixture=fixtureCommand();
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
  const errors=[];let posts=0;
  page.on('pageerror',e=>errors.push(e.message));
  page.on('request',request=>{if(request.method()==='POST')posts++;});
  await page.goto(base+'/butchery/receipts?status=pending');
  await page.waitForFunction(()=>document.querySelector('[data-flow-directory]')?.dataset.ready==='1');
  await page.locator('[data-flow-search]').fill(fixture.receiptNumber);
  const row=page.locator(`[data-receipt-id="${fixture.receiptId}"]`);
  await row.locator('summary').click();
  const trigger=row.locator(`[data-workspace-modal-open="flow-receive-${fixture.receiptId}"]`);
  const modal=page.locator(`#flow-receive-${fixture.receiptId}`),form=modal.locator('form');
  const visual=form.locator('[name=visual_conform]'),documents=form.locator('[name=documents_conform]'),temperature=form.locator('[name=temperature_c]');
  const progress=form.locator('[data-receipt-control-progress]'),submit=form.locator('button[type=submit]');
  async function open(){await trigger.click();await modal.waitFor({state:'visible'});await page.waitForFunction(id=>document.querySelector(`#flow-receive-${id} [data-receipt-control-progress]`)?.textContent.includes('0 / 2'),fixture.receiptId);}
  await open();
  const summary=await form.locator('.receipt-control-summary').innerText();
  for(const text of [fixture.receiptNumber,fixture.transferReference,fixture.source,fixture.batchNumber,'2,000'])assert(summary.includes(text),'Récapitulatif complet : '+text);
  assert.equal(await temperature.getAttribute('required'),null,'Température facultative');
  assert.equal(await temperature.getAttribute('aria-describedby'),`flow-receive-${fixture.receiptId}-temperature-help`);
  assert(await submit.isDisabled(),'La réception exige les deux contrôles');
  await visual.check();assert.match(await progress.innerText(),/1\s*\/\s*2/);assert(await submit.isDisabled());
  await documents.check();assert.match(await progress.innerText(),/2\s*\/\s*2/);assert(await submit.isEnabled());
  await temperature.fill('101');assert(!(await temperature.evaluate(el=>el.checkValidity())),'101 °C doit être invalide');
  await temperature.fill('0');assert(await temperature.evaluate(el=>el.checkValidity()),'Zéro est une température valide');
  await page.keyboard.press('Escape');assert(!(await modal.isVisible()));
  await open();
  assert(!(await visual.isChecked())&&!(await documents.isChecked()),'Réouverture : contrôles à vérifier de nouveau');
  assert.equal(await temperature.inputValue(),'','Réouverture : mesure réinitialisée');
  assert(await submit.isDisabled());
  const exception=form.locator('.receipt-control-exception');await exception.locator('summary').click();
  assert.match(await exception.innerText(),/conserver la réception en attente/);
  assert.match(await exception.locator('a').getAttribute('href'),/butchery\/history\/receipts$/);
  await exception.getByRole('button',{name:'Garder en attente'}).click();
  assert(!(await modal.isVisible()));assert.equal(posts,0,'Garder en attente ne crée aucun mouvement');
  assert(await row.isVisible(),'La réception reste à contrôler');
  for(const width of [390,320]){
   await page.setViewportSize({width,height:900});await open();
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Page sans débordement à '+width+' px');
   assert(await modal.evaluate(el=>el.scrollWidth<=el.clientWidth+1),'Fenêtre sans débordement à '+width+' px');
   assert(await form.locator('.receipt-control-panel').evaluate(el=>el.scrollWidth<=el.clientWidth+1),'Formulaire sans débordement à '+width+' px');
   for(const check of [visual,documents])assert(await check.locator('..').evaluate(el=>el.getBoundingClientRect().height>=44),'Grande cible tactile');
   await page.keyboard.press('Escape');
  }
  await page.setViewportSize({width:1440,height:1000});await open();
  await visual.check();await documents.check();await temperature.fill('0');
  const action=await form.getAttribute('action');
  const response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url()===action);
  await submit.click();await page.locator('[data-action-dialog-confirm]').click();
  const payload=await(await response).json();assert(payload.ok,payload.message);
  await page.locator('body.is-loading').waitFor({state:'hidden'});
  await page.waitForFunction(id=>!document.querySelector(`[data-receipt-id="${id}"]`),fixture.receiptId);
  const verified=fixtureCommand(['verify',String(fixture.receiptId)]);
  assert.equal(verified.temperature,0);assert.equal(verified.quantity,2);assert.equal(verified.transferStatus,'received');
  assert.deepEqual(errors,[]);
  console.log('OK : récapitulatif, contrôles 0/2→2/2, température zéro, validation, attente sans écriture, réouverture, mobile 390/320 et entrée au froid traçable.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
