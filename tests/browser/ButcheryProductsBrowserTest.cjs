// Products referential (046): products per material, creation, material card, and filtered products in the transformation form.
// Run against the disposable server described in travaux-agricoles-validation.md (base dagril_porcin_*_test with migration 046).
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const fixture=JSON.parse(execFileSync('php',[path.join(__dirname,'../integration/ButcheryProductsBrowserFixture.php')],{env:process.env,encoding:'utf8'}));
const base=process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8107';
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  async function save(form){
   const action=await form.getAttribute('action');const response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url()===action);
   await form.locator('button[type=submit]').click();await page.locator('[data-action-dialog-confirm]').click();const payload=await(await response).json();assert(payload.ok,payload.message);
   await page.locator('body.is-loading').waitFor({state:'hidden'});await page.waitForFunction(()=>document.querySelector('[data-butchery-products]')?.dataset.ready==='1');
  }
  const options=async select=>(await select.locator('option').allTextContents()).filter(text=>!/^(Choisir|Choisissez|Aucun)/.test(text));
  const name='Filet de porc '+fixture.suffix.toLowerCase();

  // The referential: one card per material, catalog filters.
  await page.goto(base+'/butchery/products');
  assert.equal(await page.locator('.sidebar a[href$="/butchery/products"].active').count(),1,'Lien « Produits » actif dans le menu');
  const porc=page.locator(`[data-product-material="${fixture.porc}"]`);
  assert.match(await porc.innerText(),/Côtelettes de porc/);assert.match(await porc.innerText(),/\d+,000 kg disponibles en chambre froide/);
  assert.doesNotMatch(await page.locator(`[data-product-material="${fixture.boeuf}"]`).innerText(),/Côtelettes/);
  await page.locator('[data-product-material-filter]').selectOption(String(fixture.boeuf));
  const visible=await page.locator('[data-product-row]:not([hidden]) td:first-child strong').allTextContents();
  assert(visible.includes('Morceaux de bœuf')&&visible.includes('Abats')&&!visible.includes('Côtelettes de porc'),'Filtre par matière : produits du bœuf seulement');
  await page.locator('[data-product-search]').fill('makoso');assert.equal(await page.locator('[data-product-row]:not([hidden])').count(),0,'Makoso ne vient pas du bœuf');
  await page.locator('[data-product-material-filter]').selectOption('');assert.equal(await page.locator('[data-product-row]:not([hidden])').count(),1);assert(await page.locator('[data-product-empty]').isHidden());
  await page.locator('[data-product-search]').fill('');

  // New product: code proposed from the name, at least one material, recommended DLC.
  await page.locator('[data-workspace-modal-open="butchery-product-new"]').first().click();
  let form=page.locator('#butchery-product-new form');
  await form.locator('[name=name]').fill(name);
  assert.match(await form.locator('[data-product-code-help]').innerText(),new RegExp('FILET-DE-PORC-'+fixture.suffix));
  assert.equal((await form.locator('[data-product-code-badge]').innerText()).trim(),'Automatique');
  assert(!(await form.evaluate(el=>el.checkValidity())),'Matière d’origine obligatoire');
  await form.locator(`[name="source_ids[]"][value="${fixture.porc}"]`).check({force:true});
  assert.match(await form.locator('[data-product-sources-note]').innerText(),/1 matière/);
  await form.locator('[name=default_shelf_life_days]').fill('5');
  assert(await form.evaluate(el=>el.checkValidity()));
  await save(form);
  assert.match(await porc.innerText(),new RegExp(name));

  // Material card: the beef no longer gives offal.
  await page.locator(`[data-workspace-modal-open="butchery-material-${fixture.boeuf}"]`).click();
  form=page.locator(`#butchery-material-${fixture.boeuf} form`);
  await form.locator(`[name="product_ids[]"][value="${fixture.abats}"]`).uncheck({force:true});
  assert.match(await form.locator('[data-material-selected]').innerText(),/1 produit\(s\) coché\(s\)/);
  await save(form);
  assert.doesNotMatch(await page.locator(`[data-product-material="${fixture.boeuf}"]`).innerText(),/Abats/);
  assert.match(await page.locator('.pig-ref-history').innerText(),/retrait de « Abats »/);

  // Transformation form: only the products of the chosen material.
  await page.goto(base+'/butchery/production');await page.locator('[data-workspace-modal-open=flow-transform]').click();
  form=page.locator('#flow-transform form');const product=form.locator('[data-flow-output]').first().locator('select');
  assert.deepEqual(await options(product),[],'Aucun produit avant le choix de la matière');
  assert.match(await form.locator('[data-transform-products-hint]').innerText(),/Choisissez d’abord le produit source/);
  await form.locator('[name=source_item_id]').selectOption(String(fixture.porc));
  let offered=await options(product);
  assert(offered.includes('Côtelettes de porc')&&offered.includes(name)&&offered.includes('Abats')&&!offered.includes('Morceaux de bœuf'),'Porc : seulement les produits du porc');
  await product.selectOption({label:name});
  const expected=new Date();expected.setDate(expected.getDate()+5);
  assert.equal(await form.locator('[data-flow-output]').first().locator('[type=date]').inputValue(),expected.getFullYear()+'-'+String(expected.getMonth()+1).padStart(2,'0')+'-'+String(expected.getDate()).padStart(2,'0'),'DLC proposée à 5 jours');
  await form.locator('[data-flow-add-output]').click();
  assert.deepEqual(await options(form.locator('[data-flow-output]').last().locator('select')),offered,'Ligne ajoutée : mêmes produits');
  await form.locator('[name=source_item_id]').selectOption(String(fixture.boeuf));
  offered=await options(product);
  assert(offered.includes('Morceaux de bœuf')&&!offered.includes('Abats')&&!offered.includes('Côtelettes de porc'),'Bœuf : seulement ses produits, sans les abats retirés');
  assert.equal(await product.inputValue(),'','Produit du porc retiré après le changement de matière');
  assert.match(await form.locator('[data-transform-products-hint]').innerText(),/produit\(s\) paramétré\(s\) pour Carcasse de bœuf/);
  await page.keyboard.press('Escape');

  for(const width of [390,320]){
   await page.setViewportSize({width,height:900});await page.goto(base+'/butchery/products');
   await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth+1,{},{timeout:4000});
   await page.locator(`[data-workspace-modal-open="butchery-material-${fixture.porc}"]`).click();assert(await page.locator('.workspace-entity-modal.is-open').isVisible());
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await page.keyboard.press('Escape');
  }
  assert.deepEqual(errors,[]);
  console.log('OK : référentiel par matière, filtres du catalogue, création avec code proposé, choix des produits d’une matière, formulaire de transformation filtré, DLC proposée, mobile 390/320.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
