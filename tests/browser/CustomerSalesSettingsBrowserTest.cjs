// Commercial distribution, lot 1 (047): client records (creation, duplicate refused, edition, blocking), rate of the day and price lists.
// Run against the disposable server described in travaux-agricoles-validation.md (base dagril_porcin_*_test with migration 047).
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const base=process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8107';
const suffix=Math.random().toString(16).slice(2,8).toUpperCase();
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  async function submit(form){
   const action=await form.getAttribute('action');const response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url()===action);
   await form.locator('button[type=submit]').click();await page.locator('[data-action-dialog-confirm]').click();return(await response).json();
  }
  async function save(form){const payload=await submit(form);assert(payload.ok,payload.message);await page.locator('body.is-loading').waitFor({state:'hidden'});}
  async function menu(id,label){await page.locator(`#${id}-toggle`).click();await page.locator(`#${id}`).getByRole('menuitem',{name:label}).click();}
  const name='Grossiste '+suffix,row=()=>page.locator('tr[data-customer-id]',{hasText:name});

  // New client: legal identifiers only for companies.
  await page.goto(base+'/customers');
  await page.locator('.ui-page-header [data-workspace-modal-open="customer-new"]').click();
  let form=page.locator('#customer-new form');
  await form.locator('[name=customer_type][value=individual]').check({force:true});
  assert(await form.locator('.customer-legal').isHidden(),'Pas d’identifiants légaux pour un particulier');
  assert.equal((await form.locator('[data-customer-name-label]').innerText()).trim(),'Nom et prénom *');
  await form.locator('[name=customer_type][value=company]').check({force:true});
  assert(await form.locator('.customer-legal').isVisible());
  await form.locator('[name=name]').fill(name);await form.locator('[name=phone]').fill('+243 81 555 0101');await form.locator('[name=nif]').fill('nif-'+suffix);await form.locator('[name=city]').fill('Kinshasa');
  await save(form);
  assert.match(await row().innerText(),new RegExp('NIF NIF-'+suffix));assert.match(await row().innerText(),/CLI-\d{4}/);
  const id=await row().getAttribute('data-customer-id');

  // A second client with the same NIF is refused; the form keeps what was typed.
  await page.locator('.ui-page-header [data-workspace-modal-open="customer-new"]').click();form=page.locator('#customer-new form');
  await form.locator('[name=name]').fill('Autre '+suffix);await form.locator('[name=phone]').fill('0810000000');await form.locator('[name=nif]').fill('NIF-'+suffix);
  const refused=await submit(form);assert(!refused.ok);
  await form.locator('[data-feed-error]').waitFor({state:'visible'});assert.match(await form.locator('[data-feed-error]').innerText(),/NIF est déjà enregistré/);
  assert.equal(await form.locator('[name=name]').inputValue(),'Autre '+suffix);await page.keyboard.press('Escape');

  // Edition, then blocking with a reason and unblocking.
  await row().getByRole('button',{name:'Modifier'}).click();form=page.locator(`#customer-edit-${id} form`);
  await form.locator('[name=city]').fill('Lubumbashi');await save(form);assert.match(await row().innerText(),/Lubumbashi/);
  await menu('customer-menu-'+id,'Bloquer le client');form=page.locator(`#customer-block-${id} form`);
  await form.locator('[name=reason]').fill('Litige facture');await save(form);
  assert.match(await row().innerText(),/Bloqué/);assert.match(await row().innerText(),/Litige facture/);
  await menu('customer-menu-'+id,'Consulter la fiche');assert.match(await page.locator(`#customer-detail-${id}`).innerText(),/Litige facture/);await page.keyboard.press('Escape');
  await menu('customer-menu-'+id,'Débloquer le client');await save(page.locator(`#customer-unblock-${id} form`));assert.match(await row().innerText(),/Actif/);

  // Rate of the day: a large variation must be confirmed.
  await page.goto(base+'/sales/settings');
  let rateForm=page.locator('[data-sales-rate-form]');
  if(!await rateForm.getAttribute('data-current-rate')){await rateForm.locator('[name=rate]').fill('2800');await save(rateForm);rateForm=page.locator('[data-sales-rate-form]');}
  const current=Number(await rateForm.getAttribute('data-current-rate'));
  assert(await rateForm.locator('[data-feed-error]').isHidden()&&await rateForm.locator('[data-rate-confirm]').isHidden(),'Ni erreur ni confirmation au chargement');
  await rateForm.locator('[name=rate]').fill(String(Math.round(current*1.2)));
  assert.match(await rateForm.locator('[data-rate-variation]').innerText(),/Variation : \+20,0 %.*Variation importante/);
  assert(await rateForm.locator('[data-rate-confirm]').isVisible());assert(!(await rateForm.evaluate(el=>el.checkValidity())),'Confirmation obligatoire');
  await rateForm.locator('[name=confirm_variation]').check();await save(rateForm);
  assert.match(await page.locator('.sales-rate__current').innerText(),new RegExp(Math.round(current*1.2).toLocaleString('fr-FR').replace(/\s/g,'\\s')));

  // Price of the 10 kg flour bag, with its CDF equivalent while typing.
  const price=page.locator('tr[data-price-key]',{hasText:'Sac farine 10 kg'});const key=await price.getAttribute('data-price-key');
  await price.getByRole('button',{name:'Modifier'}).click();form=page.locator(`#sales-price-${key} form`);
  await form.locator('[name=price_company]').fill('12');await form.locator('[name=price_counter]').fill('13.5');
  assert.match(await form.locator('[name=price_company]').locator('xpath=ancestor::label').locator('[data-price-cdf]').innerText(),/≈ .* CDF au taux du jour/);
  await save(form);
  assert.match(await page.locator('tr[data-price-key]',{hasText:'Sac farine 10 kg'}).innerText(),/12,00 USD[\s\S]*13,50 USD/);

  for(const width of [390,320]){
   await page.setViewportSize({width,height:900});
   for(const path of ['/customers','/sales/settings']){await page.goto(base+path);await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth+1,{},{timeout:4000});}
   await page.goto(base+'/customers');await page.locator('.ui-page-header [data-workspace-modal-open="customer-new"]').click();
   assert(await page.locator('#customer-new').isVisible());assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await page.keyboard.press('Escape');
  }
  assert.deepEqual(errors,[]);
  console.log('OK : fiche client (entreprise ou particulier), doublon de NIF refusé sans perte de saisie, modification, blocage motivé et déblocage, taux du jour avec variation confirmée, tarifs avec équivalent CDF, mobile 390/320.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
