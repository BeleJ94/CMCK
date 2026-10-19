const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const fixtureScript=path.join(__dirname,'../integration/PigBrowserFixture.php');
const fixture=JSON.parse(execFileSync('php',[fixtureScript],{encoding:'utf8'}));
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.goto((process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8110')+'/livestock/pigs/slaughters');
  await page.locator('[data-workspace-modal-open="flow-slaughter"]').click();
  const form=page.locator('#flow-slaughter form'), search=form.locator('[data-slaughter-lot-search]');
  assert.equal(await form.locator(`[name=_batch_choice][value="${fixture.young}"]`).count(),0,'Le lot A en attente est exclu');
  await form.locator('[data-slaughter-picker-open]').click();
  await search.fill(fixture.facilityName);
  assert.equal(await form.locator('[data-slaughter-choice]:visible').count(),2);
  const breeder=form.locator(`[name=_batch_choice][value="${fixture.breeder}"]`);
  await breeder.check();
  assert.equal(await form.locator('[name=batch_id]').inputValue(),String(fixture.breeder));
  assert.equal(await form.locator('[name=heads]').getAttribute('max'),'4');
  assert(await form.locator('[data-slaughter-breeder-warning]').isVisible());
  await form.locator('[name=heads]').fill('5');
  assert(!(await form.locator('[name=heads]').evaluate(e=>e.checkValidity())));
  assert(!(await form.locator('dialog').isVisible()),'La sélection ferme le sélecteur');
  assert(await page.locator('#flow-slaughter').isVisible(),'Le formulaire reste ouvert');
  await form.locator('[data-slaughter-picker-open]').click();
  await search.fill('aucun-resultat');
  assert(await breeder.isVisible(),'La sélection reste visible pendant la recherche');
  assert(await form.locator('[data-slaughter-no-match]').isVisible());
  await search.fill(fixture.facilityName);
  await search.fill(String(fixture.adult));
  await search.press('Enter');
  assert.equal(await form.locator('[name=batch_id]').inputValue(),String(fixture.adult),'Entrée choisit un lot sans soumettre le formulaire');
  assert(!(await form.locator('[data-slaughter-breeder-warning]').isVisible()));
  assert.equal(await form.locator('[name=heads]').getAttribute('max'),'16');
  await form.locator('[data-slaughter-picker-open]').click();
  for(const width of [390,320]){
   await page.setViewportSize({width,height:900});
   assert(!(await form.locator('[data-slaughter-choice]:visible').evaluateAll(rows=>rows.some(row=>row.scrollWidth>row.clientWidth+1))),'Pas de débordement mobile');
  }
  await page.keyboard.press('Escape');
  assert(await page.locator('#flow-slaughter').isVisible(),'Échap ferme seulement le sélecteur');
  assert(await form.locator('[data-slaughter-picker-open]').evaluate(e=>e===document.activeElement));
  await page.keyboard.press('Escape');
  await page.locator('[data-workspace-modal-open="flow-slaughter"]').click();
  assert.equal(await search.inputValue(),'');
  assert.equal(await form.locator('[name=_batch_choice]:checked').count(),0);
  assert.equal(await form.locator('[name=batch_id]').inputValue(),'');
  assert.deepEqual(errors,[]);
  console.log('OK : recherche, sélection, reproducteurs, effectifs maximum, exclusion des lots non éligibles, réinitialisation et mobile.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{execFileSync('php',[fixtureScript,'cleanup',String(fixture.facility)]);});
