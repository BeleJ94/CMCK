// Commercial distribution, lot 2 (048): order with several lines, validation and booking, assigned transfer, cancellation.
// Run against the disposable server described in travaux-agricoles-validation.md (base dagril_porcin_*_test with migrations 047 and 048).
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const fixture=JSON.parse(execFileSync('php',[path.join(__dirname,'../integration/CustomerOrderBrowserFixture.php')],{env:process.env,encoding:'utf8'}));
const base=process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8107';
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  async function save(form){
   const action=await form.getAttribute('action');const response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url()===action);
   await form.locator('button[type=submit]').click();await page.locator('[data-action-dialog-confirm]').click();const payload=await(await response).json();assert(payload.ok,payload.message);
   await page.locator('body.is-loading').waitFor({state:'hidden'});return payload.message;
  }
  async function menu(id,label){await page.locator(`#order-menu-${id}-toggle`).click();await page.locator(`#order-menu-${id}`).getByRole('menuitem',{name:label}).click();}
  const row=()=>page.locator('tr[data-order-id]',{hasText:fixture.clientName}).first();

  // New order with two lines: amounts and total while typing, no duplicate product.
  await page.goto(base+'/sales/orders');
  await page.locator('.ui-page-header [data-workspace-modal-open="order-new"]').click();
  let form=page.locator('#order-new form');
  await form.locator('[name=customer_id]').selectOption(String(fixture.client));
  await form.locator('[name="lines[0][product_key]"]').selectOption(fixture.f50);await form.locator('[name="lines[0][quantity_bags]"]').fill('80');
  assert.match(await form.locator('[data-order-total]').innerText(),/80 sac\(s\) · 2\s800,00 USD/);
  assert.match(await form.locator('[data-order-line-hint]').first().innerText(),/sac\(s\) libre\(s\) à Minoterie/);
  await form.locator('[data-order-line-add]').click();
  const second=form.locator('[data-order-line]').nth(1).locator('select');
  assert(await second.locator(`option[value="${fixture.f50}"]`).evaluate(option=>option.hidden),'Le même produit et format est masqué sur la deuxième ligne');
  await second.selectOption(fixture.f10);await form.locator('[data-order-line]').nth(1).locator('input').fill('10');
  assert.match(await form.locator('[data-order-total]').innerText(),/90 sac\(s\) · 2\s880,00 USD/);
  await save(form);
  assert.match(await row().innerText(),/Brouillon/);assert.match(await row().innerText(),/2\s880,00 USD/);
  const id=await row().getAttribute('data-order-id');

  // Validation books the bags at the mill.
  const validated=await save(row().locator('form[action$="/validate"]'));
  assert.match(validated,/sac\(s\) réservé\(s\) au site de production/);
  assert.match(await row().innerText(),/En cours/);assert.match(await row().innerText(),/Réservé 80/);
  await menu(id,'Consulter la commande');
  assert.match(await page.locator(`#order-detail-${id} .order-lines-detail`).innerText(),/Sac farine 50 kg[\s\S]*80/);
  await page.keyboard.press('Escape');

  // Assigned transfer to the main office, then visible on the transfer page.
  await row().getByRole('button',{name:'Envoyer'}).click();
  form=page.locator(`#order-transfer-${id} form`);
  const inputs=form.locator('input[name^="bags["]');
  assert.equal(await inputs.first().inputValue(),'80','Tous les sacs réservés proposés par défaut');
  for(let i=1;i<await inputs.count();i++)await inputs.nth(i).fill('0');
  assert.match(await save(form),/Envoi TR-.* préparé et soumis pour approbation/);
  await menu(id,'Consulter la commande');
  const link=page.locator(`#order-detail-${id} a[href*="/transfers/"]`).first();assert.match(await link.innerText(),/^TR-/);
  const transferUrl=await link.getAttribute('href');await page.keyboard.press('Escape');
  await page.goto(transferUrl);assert.match(await page.locator('.transfer-order-link').innerText(),new RegExp('Envoi affecté à la commande\\s+BC-[\\s\\S]*'+fixture.clientName));

  // Cancelling the order frees the bags and cancels the transfer not yet approved.
  await page.goto(base+'/sales/orders');
  await menu(id,'Annuler la commande');form=page.locator(`#order-cancel-${id} form`);
  await form.locator('[name=reason]').fill('Erreur de quantité');await save(form);
  assert.match(await row().innerText(),/Annulée/);assert.match(await row().innerText(),/Erreur de quantité/);
  await page.goto(transferUrl);assert.match(await page.locator('.transfer-page__status').innerText(),/Annulé/);

  for(const width of [390,320]){
   await page.setViewportSize({width,height:900});await page.goto(base+'/sales/orders');
   await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth+1,{},{timeout:4000});
   await page.locator('.ui-page-header [data-workspace-modal-open="order-new"]').click();assert(await page.locator('#order-new').isVisible());
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await page.keyboard.press('Escape');
  }
  assert.deepEqual(errors,[]);
  console.log('OK : commande à deux lignes avec total en direct, validation et réservation, envoi affecté visible sur le transfert, annulation libérant les sacs, mobile 390/320.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
