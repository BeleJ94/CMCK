const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const fixtureScript=path.join(__dirname,'../integration/PigBrowserFixture.php');
const fixture=JSON.parse(execFileSync('php',[fixtureScript],{env:process.env,encoding:'utf8'}));
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto((process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8107')+'/livestock/pigs');
  await page.goto((process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8107')+'/livestock/pigs?filter=A');
  assert(!(await page.locator('#pig-group-detail').isVisible()),'Un filtre dans URL ne doit pas ouvrir la modale');
  assert.equal(await page.locator('[data-pig-filter]').inputValue(),'A');
  await page.reload();
  assert(!(await page.locator('#pig-group-detail').isVisible()),'Le rechargement garde la modale fermee');
  assert.equal(await page.locator('.pig-group-overview').count(),3);
  assert.equal(await page.locator('[data-pig-lot]:visible').count(),0);
  await page.locator('[data-pig-group-button="A"]').click();
  assert(await page.locator('#pig-group-detail').isVisible());
  assert.equal(await page.locator('[data-pig-filter]').inputValue(),'A');
  await page.keyboard.press('Escape');
  assert(await page.locator('[data-pig-group-button="A"]').evaluate(e=>e===document.activeElement));
  if(!(await page.locator('#pig-group-detail').isVisible()))await page.locator('[data-pig-consult]').click();
  if(await page.locator('[data-pig-reset]').isVisible())await page.locator('[data-pig-reset]').click();
  await page.locator('[data-pig-search]').fill(fixture.facilityName);
  assert.equal(await page.locator('[data-pig-lot]:visible').count(),3);
  async function expectSummary(values) {
   for (const [filter,value] of Object.entries(values)) assert.equal(await page.locator(`[data-pig-summary-filter="${filter}"] .ui-kpi__value`).textContent(),String(value));
  }
  await expectSummary({'':25,male:5,female:20,breeding:4});
  await page.locator('[data-pig-filter]').selectOption('C');
  await page.locator('[data-pig-search]').fill(fixture.facilityName);
  await expectSummary({'':20,male:0,female:20,breeding:4});
  for(const format of ['excel','pdf']) {
   const link=page.locator(`[data-pig-export="${format}"]`);
   const url=await link.getAttribute('href');
   assert.equal(new URL(url).searchParams.get('filter'),'C');
   assert.equal(new URL(url).searchParams.get('q'),fixture.facilityName);
   const response=await page.request.get(url);
   assert.equal(response.status(),200);
   const content=await response.body();
   assert.equal(content.subarray(0,format==='excel'?2:5).toString(),format==='excel'?'PK':'%PDF-');
   assert.match(response.headers()['content-disposition'],/attachment/);
  }

  await page.locator('[data-pig-summary-filter="breeding"]').click();
  assert.equal(await page.locator('[data-pig-filter]').inputValue(),'C');
  assert.equal(await page.locator('[data-pig-search]').inputValue(),fixture.facilityName);
  assert.equal(await page.locator('[data-pig-lot]:visible').count(),1);
  assert.equal(await page.locator('[data-pig-lot]:visible').getAttribute('data-pig-lot'),String(fixture.breeder));
  await expectSummary({'':20,male:0,female:20,breeding:4});
  await page.reload();
  assert(!(await page.locator('#pig-group-detail').isVisible()));
  await page.locator('[data-pig-consult]').click();
  assert.equal(await page.locator('[data-pig-lot]:visible').count(),1);
  assert.equal(await page.locator('[data-pig-summary-filter="breeding"]').getAttribute('aria-pressed'),'true');
  await page.locator('[data-pig-summary-filter="male"]').click();
  assert.equal(await page.locator('[data-pig-lot]:visible').count(),0);
  await expectSummary({'':20,male:0,female:20,breeding:4});
  await page.locator('[data-pig-summary-filter=""]').click();
  assert.equal(await page.locator('[data-pig-lot]:visible').count(),2);
  const compactLot = page.locator(`[data-pig-lot="${fixture.breeder}"]`);
  assert((await compactLot.boundingBox()).height < 180,'Un lot sans alerte reste compact sur ordinateur');
  await compactLot.locator('.pig-lot-details summary').click();
  assert(await compactLot.locator('.pig-lot-details dl').isVisible(),'Les détails restent dépliables');
  await compactLot.locator('.pig-lot-details summary').click();


  await page.locator('[data-pig-search]').fill('aucun-lot-ne-correspond-a-ce-texte');
  await expectSummary({'':0,male:0,female:0,breeding:0});

  await page.keyboard.press('Escape');
  await page.locator('[data-pig-show-pending]').click();
  const young=page.locator(`[data-pig-lot="${fixture.young}"]`);
  assert(await young.isVisible());
  await young.locator('[name=field_checked]').check();
  await young.locator('[type=submit]').click();
  await page.locator('[data-action-dialog-confirm]').click();
  await page.waitForFunction(id=>document.querySelector(`[data-pig-lot="${id}"]`)?.dataset.group==='B',fixture.young);
  await page.locator('[data-pig-filter]').selectOption('');
  await page.locator('[data-pig-search]').fill(fixture.facilityName);
  if(await page.locator('#pig-group-detail').isVisible())await page.keyboard.press('Escape');
  await page.locator('[data-workspace-modal-open="pig-register"]').click();
  const form=page.locator('#pig-register form');
  await form.locator('[name=site_id]').selectOption(String(fixture.site));
  await form.locator('[name=facility_id]').selectOption(String(fixture.facility));
  await form.locator('[name=origin]').selectOption('birth');
  assert(await form.locator('[name=mother_batch_id]').isVisible());
  await form.locator('[name=mother_batch_id]').selectOption(String(fixture.breeder));
  await form.locator('[name=sex]').selectOption('male');
  await form.locator('[name=born_on]').fill(fixture.born_on);
  await form.locator('[name=heads]').fill('3');
  assert.match(await form.locator('[data-pig-age-preview]').textContent(),/Groupe initial : A/);
  await form.locator('[type=submit]').click();
  await page.locator('[data-action-dialog-confirm]').click();
  await page.waitForFunction(name=>Array.from(document.querySelectorAll('[data-pig-lot]')).filter(e=>e.dataset.search.includes(name)).length===4,fixture.facilityName);
  await page.locator('[data-pig-consult]').click();
  assert.equal(await page.locator('[data-pig-lot]:visible').count(),4);
  await page.keyboard.press('Escape');
  // A business refusal retains all entered values in the open panel.
  if(await page.locator('#pig-group-detail').isVisible())await page.keyboard.press('Escape');
  await page.locator('[data-workspace-modal-open="pig-register"]').click();
  const retry=page.locator('#pig-register form');
  await retry.locator('[name=site_id]').selectOption(String(fixture.site));await retry.locator('[name=facility_id]').selectOption(String(fixture.facility));
  await retry.locator('[name=sex]').selectOption('female');await retry.locator('[name=born_on]').fill(fixture.born_on);await retry.locator('[name=heads]').fill('300');
  await retry.locator('[type=submit]').click();await page.locator('[data-action-dialog-confirm]').click();
  await retry.locator('[data-feed-error]').waitFor({state:'visible'});
  assert.equal(await retry.locator('[name=heads]').inputValue(),'300');
  await page.keyboard.press('Escape');
  await page.screenshot({path:'/private/tmp/dagril-pigs-desktop.png',fullPage:true});
  for(const width of [390,320]){
   await page.setViewportSize({width,height:900});
   await page.evaluate(()=>window.scrollTo(0,0));
   await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth+1,{},{timeout:3000}).catch(async error=>{console.error(await page.evaluate(()=>({w:innerWidth,doc:document.documentElement.scrollWidth,overflow:[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1).slice(0,10).map(e=>({cls:e.className,x:e.getBoundingClientRect().x,w:e.getBoundingClientRect().width,visibility:getComputedStyle(e).visibility}))})));throw error;});
   await page.screenshot({path:`/private/tmp/dagril-pigs-page-${width}.png`,fullPage:true});
   if(await page.locator('#pig-group-detail').isVisible())await page.keyboard.press('Escape');
  await page.locator('[data-workspace-modal-open="pig-register"]').click();
   assert(await page.locator('#pig-register').isVisible());
   assert(!(await page.locator('#pig-register [data-feed-error]').isVisible()));
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Pas de débordement mobile à '+width+'px');
   await page.screenshot({path:`/private/tmp/dagril-pigs-${width}.png`});
   await page.keyboard.press('Escape');
  }
  // Exercise pagination with 23 DOM-only rows; no extra business records are created.
  await page.setViewportSize({width:1440,height:1000});
  await page.evaluate(() => {
   const old=document.querySelector('[data-pig-directory]'), panel=old.cloneNode(true);
   delete panel.dataset.ready; delete panel.dataset.restoreDialog;
   sessionStorage.removeItem('pig-filters:'+panel.dataset.siteScope);
   history.replaceState(history.state,'',location.pathname);
   const body=panel.querySelector('.pig-lots'), sample=body.querySelector('[data-pig-lot]').cloneNode(true);
   body.replaceChildren();
   for(let i=1;i<=23;i++) {
    const row=sample.cloneNode(true);
    Object.assign(row.dataset,{pigLot:String(i),heads:String(i),group:i<=11?'A':i<=17?'B':'C',sex:'male',breeding:'0',pending:'0',search:'pagination-fixture'});
    row.querySelectorAll('form').forEach(e=>e.remove());
    row.querySelector('details').open=false;
    body.append(row);
   }
   old.replaceWith(panel); window.initPigs(panel);
   document.body.classList.add('workspace-modal-open');
   const dialog=panel.querySelector('#pig-group-detail');dialog.classList.add('is-open');dialog.setAttribute('aria-hidden','false');
  });
  await page.locator('[data-pig-search]').fill('pagination-fixture');
  assert.equal(await page.locator('[data-pig-page-size]').inputValue(),'10');
  assert.equal(await page.locator('[data-pig-lot]:visible').count(),10);
  await expectSummary({'':276,male:276,female:0,breeding:0});
  await page.locator('[data-pig-page="1"]').click();
  assert.equal(await page.locator('[data-pig-lot]:visible').first().getAttribute('data-pig-lot'),'11');
  await expectSummary({'':276,male:276,female:0,breeding:0});
  await page.locator('[data-pig-page="1"]').click();
  assert.equal(await page.locator('[data-pig-lot]:visible').count(),3);
  assert(await page.locator('[data-pig-page="1"]').isDisabled());
  await page.locator('[data-pig-page-size]').selectOption('25');
  assert.equal(await page.locator('[data-pig-lot]:visible').count(),23);
  await page.locator('[data-pig-sort="heads"]').click();
  await page.locator('[data-pig-sort="heads"]').click();
  assert.equal(await page.locator('[data-pig-lot]:visible').first().getAttribute('data-pig-lot'),'23');
  await page.locator('[data-pig-page-size]').selectOption('10');
  for(const [group,count] of [['A',10],['B',6],['C',6]]) {
   await page.locator('[data-pig-filter]').selectOption(group);
   assert.equal(await page.locator('[data-pig-lot]:visible').count(),count);
   assert.equal(await page.locator(`[data-pig-lot]:visible:not([data-group="${group}"])`).count(),0);
  }
  for(const width of [390,320]) {
   await page.setViewportSize({width,height:900});
   assert(!(await page.locator('[data-pig-lot]:visible').evaluateAll(rows=>rows.some(row=>row.scrollWidth>row.clientWidth+1))),'Tableau sans débordement mobile');
  }
  assert.deepEqual(errors,[]);
  console.log('OK : filtres, validation terrain, naissance AJAX, saisie conservée sur refus, mobile 390/320 px, aucune erreur JavaScript.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{console.log(execFileSync('php',[fixtureScript,'cleanup',String(fixture.facility)],{env:process.env,encoding:'utf8'}).trim());});
