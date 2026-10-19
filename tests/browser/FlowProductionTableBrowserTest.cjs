// Requires the authenticated localhost fixture server and the disposable porcine DB.
// Fixture writes are restricted to dagril_porcin_<12 hex>_test; browser requests stay read-only.
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const fs=require('node:fs');

assert(/^dagril_porcin_[a-f0-9]{12}_test$/.test(process.env.DAGRIL_DB_DATABASE||''),'Une base porcine jetable doit être fournie.');
assert(process.env.DAGRIL_FLOW_FIXTURE,'DAGRIL_FLOW_FIXTURE doit désigner la fixture du parcours.');
assert(process.env.DAGRIL_TEST_URL,'DAGRIL_TEST_URL doit désigner le serveur de test authentifié.');
const base=process.env.DAGRIL_TEST_URL.replace(/\/$/,'');
assert(['127.0.0.1','localhost','[::1]'].includes(new URL(base).hostname),'Le serveur navigateur doit être local.');
const fixture=JSON.parse(execFileSync(process.env.DAGRIL_PHP_EXECUTABLE||'php',[path.resolve(__dirname,'../integration/FlowProductionFixture.php')],{encoding:'utf8',env:process.env}));
const normalize=value=>String(value).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
const frenchNumber=value=>Number(String(value).replace(/\s/g,'').replace(',','.').replace(/[^\d.\-]/g,''));

async function assertLayout(page,modal){
 const dimensions=await page.evaluate(id=>{
  const element=id&&document.getElementById(id),bounds=element&&element.getBoundingClientRect();
  return{viewport:innerWidth,document:document.documentElement.scrollWidth,modal:element&&{left:bounds.left,right:bounds.right,scroll:element.scrollWidth,width:element.clientWidth}};
 },modal||null);
 assert(dimensions.document<=dimensions.viewport+1,'La page reste dans la largeur de l’écran : '+JSON.stringify(dimensions));
 if(dimensions.modal){
  assert(dimensions.modal.left>=-1&&dimensions.modal.right<=dimensions.viewport+1,'La modale tient dans l’écran.');
  assert(dimensions.modal.scroll<=dimensions.modal.width+1,'Seul le tableau de détails peut défiler horizontalement.');
 }
}

(async()=>{
 const executablePath=process.env.DAGRIL_CHROME_EXECUTABLE||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
 const browser=await chromium.launch({...(fs.existsSync(executablePath)?{executablePath}:{}),headless:true});
 const errors=[],mutations=[];
 try{
  const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce',serviceWorkers:'block'});
  await context.route('**/*',route=>{
   if(!['GET','HEAD'].includes(route.request().method())){mutations.push(route.request().method()+' '+route.request().url());return route.abort();}
   return route.continue();
  });
  const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));
  assert.equal((await page.goto(base+'/butchery/production')).status(),200);
  await page.waitForFunction(()=>document.querySelector('[data-flow-directory]')?.dataset.ready==='1');
  assert(!/Warning:|Fatal error:/.test(await page.locator('main').innerText()),'Aucune erreur PHP affichée.');
  const table=page.locator('table.production-table');
  assert.equal(await table.count(),1,'Le registre utilise un tableau compact.');
  assert.equal(await table.getAttribute('data-datatable'),'false','Une seule pagination contrôle le registre.');
  assert.equal(await page.locator('[data-flow-page-size]').inputValue(),'10');
  assert.equal(await page.locator('.flow-summary .ui-kpi').count(),4,'Quatre indicateurs concernent les transformations.');
  for(const key of ['pending','validated','input','output']){
   const trigger=page.locator('[data-workspace-modal-open="production-kpi-'+key+'"]');
   assert.equal(await trigger.count(),1);
   assert.equal(frenchNumber(await trigger.locator('.ui-kpi__value').innerText()),Number(fixture.kpis[key]),'L’indicateur '+key+' correspond aux dossiers attendus.');
   assert(await page.locator('#production-kpi-'+key).isHidden(),'Aucune modale ne s’ouvre automatiquement.');
  }

  await page.locator('[data-flow-reset]').click();
  await page.locator('[data-flow-search]').fill(normalize(fixture.prefix));
  const rows=table.locator('tbody [data-flow-item]:visible');
  assert.equal(await rows.count(),10,'Dix transformations sont visibles par défaut.');
  await page.locator('[data-flow-page="1"]').click();assert.equal(await rows.count(),10);
  await page.locator('[data-flow-page="1"]').click();assert.equal(await rows.count(),5);
  assert(await page.locator('[data-flow-page="1"]').isDisabled());
  assert(!/abattage|r[eé]ception/i.test(await page.locator('[data-flow-page-info]').innerText()),'La pagination décrit les transformations.');
  assert(/transformation/i.test(await page.locator('[data-flow-page-info]').innerText()));

  await page.locator('[data-flow-state]').selectOption('results_submitted');
  assert.equal(await rows.count(),10,'Le filtre des transformations à valider revient à la première page.');
  await page.locator('[data-flow-page="1"]').click();assert.equal(await rows.count(),1);
  const before={url:page.url(),search:await page.locator('[data-flow-search]').inputValue(),state:await page.locator('[data-flow-state]').inputValue(),page:await page.locator('[data-flow-page-label]').innerText(),ids:await rows.evaluateAll(elements=>elements.map(element=>element.dataset.orderId))};
  for(const key of ['pending','validated','input','output']){
   const id='production-kpi-'+key,modal=page.locator('#'+id),trigger=page.locator('[data-workspace-modal-open="'+id+'"]');
   await trigger.click();assert(await modal.isVisible());
   assert.equal(await modal.getAttribute('role'),'dialog');assert.equal(await modal.getAttribute('aria-modal'),'true');
   const label=await modal.getAttribute('aria-labelledby');assert((await page.locator('[id="'+label+'"]').innerText()).trim(),'La modale possède un nom accessible.');
   assert.equal(await modal.locator('form,[data-flow-item]').count(),0,'Les détails KPI restent en lecture seule et indépendants du registre.');
   const details=modal.locator('.dagril-datatable');assert.equal(await details.count(),1,'Le KPI possède sa propre datatable.');
   const size=details.locator('select.dagril-table-size'),search=details.locator('.dagril-table-search input[type=search]');
   assert.equal(await size.inputValue(),'10');
   await search.fill(normalize(fixture.prefix));
   assert.equal(await details.locator('tbody tr:visible').count(),10,'La recherche KPI ignore les accents et conserve dix lignes.');
   await details.getByRole('button',{name:'Page suivante',exact:true}).click();
   assert.equal(await details.locator('tbody tr:visible').count(),key==='pending'?1:2,'La pagination KPI est indépendante.');
   await size.selectOption('25');assert.equal(await details.locator('tbody tr:visible').count(),key==='pending'?11:12);
   await details.locator('thead th').first().locator('button').click();
   assert.equal(await details.locator('thead th').first().getAttribute('aria-sort'),'ascending');
   await search.fill('transformation-introuvable-786953');assert(await details.locator('.dagril-table-empty').isVisible());
   await search.fill('');await size.selectOption('10');
   await assertLayout(page,id);
   await page.keyboard.press('Escape');assert(await modal.isHidden());
   assert(await trigger.evaluate(button=>button===document.activeElement),'Échap rend le focus à l’indicateur.');
   assert.equal(page.url(),before.url);assert.equal(await page.locator('[data-flow-search]').inputValue(),before.search);
   assert.equal(await page.locator('[data-flow-state]').inputValue(),before.state);
   assert.equal(await page.locator('[data-flow-page-label]').innerText(),before.page);
   assert.deepEqual(await rows.evaluateAll(elements=>elements.map(element=>element.dataset.orderId)),before.ids,'Consulter les KPI ne change pas les résultats principaux.');
  }

  await page.locator('[data-flow-state]').selectOption('');
  await page.locator('[data-flow-page-size]').selectOption('25');assert.equal(await rows.count(),25);
  await page.locator('[data-flow-page-size]').selectOption('50');assert.equal(await rows.count(),25);
  for(const key of ['input','output','loss']){
   const expected=[...fixture.orders].sort((left,right)=>left[key]-right[key]);
   await page.locator('[data-flow-sort="'+key+'"]').click();
   assert.equal(await rows.first().getAttribute('data-order-id'),String(expected[0].id),'Tri numérique croissant : '+key);
   await page.locator('[data-flow-sort="'+key+'"]').click();
   assert.equal(await rows.first().getAttribute('data-order-id'),String(expected.at(-1).id),'Tri numérique décroissant : '+key);
  }
  const pending=fixture.orders.find(order=>order.status==='results_submitted');
  await page.locator('[data-flow-search]').fill(pending.reference);
  assert.equal(await rows.count(),1,'La recherche du numéro de transformation est précise.');
  const pendingRow=rows.first(),actions=pendingRow.locator('[popovertarget]');
  const detail=page.locator('#production-detail-'+pending.id),menu=page.locator('#production-actions-'+pending.id);
  await actions.click();assert(await menu.isVisible());
  const anchor=await actions.boundingBox(),popup=await menu.boundingBox();assert(Math.min(Math.abs(popup.y-anchor.y-anchor.height),Math.abs(popup.y+popup.height-anchor.y))<=12,'Le menu reste ancré au bouton Actions.');
  assert.equal(await menu.getByRole('button',{name:'Valider',exact:true}).count(),1);
  await menu.getByRole('button',{name:'Détails',exact:true}).click();assert(await detail.isVisible());
  assert.equal(await detail.locator('form[action$="/transformations/'+pending.id+'/validate"]').count(),1);
  assert.equal(await detail.locator('form[action$="/transformations/'+pending.id+'/cancel"]').count(),1);
  for(const name of ['operation_key','_flow_return'])assert.equal(await detail.locator('form[action$="/validate"] [name="'+name+'"]').count(),1);
  assert.equal(await detail.locator('form[action$="/validate"] [name="_flow_return"]').inputValue(),'butchery/production');
  assert((await detail.innerText()).includes('DLC'));
  await page.keyboard.press('Escape');assert(await detail.isHidden());assert(await actions.evaluate(el=>el===document.activeElement));
  const legacy=fixture.orders.find(order=>!order.managed);
  await page.locator('[data-flow-search]').fill(legacy.reference);
  await rows.first().locator('[popovertarget]').click();
  const legacyMenu=page.locator('#production-actions-'+legacy.id),legacyDetail=page.locator('#production-detail-'+legacy.id);
  assert.equal(await legacyMenu.getByRole('button',{name:'Valider',exact:true}).count(),0);
  await legacyMenu.getByRole('button',{name:'Détails',exact:true}).click();
  assert.equal(await legacyDetail.locator('a[href$="/butchery/history/production"]').count(),1);
  assert.equal(await legacyDetail.locator('form[action*="/flow/transformations/"]').count(),0);
  await page.keyboard.press('Escape');
  const cancelled=fixture.orders.find(order=>order.status==='cancelled');
  await page.locator('[data-flow-search]').fill(cancelled.reference);
  assert.equal(await page.locator('#production-actions-'+cancelled.id).getByRole('button',{name:'Valider',exact:true}).count(),0);
  assert.equal(await page.locator('#production-detail-'+cancelled.id).locator('form').count(),0);
  await page.locator('[data-flow-state]').selectOption('validated');assert.equal(await rows.count(),0);
  assert(await page.locator('[data-flow-filter-empty]').isVisible(),'Les filtres sans résultat sont expliqués.');
  await page.locator('[data-flow-reset]').click();
  assert.equal(await page.locator('[data-flow-search]').inputValue(),'');assert.equal(await page.locator('[data-flow-state]').inputValue(),'');
  assert.equal(new URL(page.url()).searchParams.has('q'),false);assert.equal(new URL(page.url()).searchParams.has('status'),false);

  await page.locator('[data-flow-search]').fill(normalize(fixture.prefix));await page.locator('[data-flow-state]').selectOption('validated');
  await page.reload();await page.waitForFunction(()=>document.querySelector('[data-flow-directory]')?.dataset.ready==='1');
  assert.equal(await page.locator('[data-flow-search]').inputValue(),normalize(fixture.prefix));
  assert.equal(await page.locator('[data-flow-state]').inputValue(),'validated','Les filtres sont conservés au rechargement.');
  assert.equal(await page.locator('[data-flow-page-size]').inputValue(),'10','Dix lignes restent le choix par défaut.');
  assert.equal(await rows.count(),10);
  for(const width of [390,320]){
   await page.setViewportSize({width,height:900});await assertLayout(page);
   await page.locator('[data-flow-state]').selectOption('results_submitted');
   const mobileRow=rows.first(),mobileId=await mobileRow.getAttribute('data-order-id');
   const mobileToggle=mobileRow.locator('[popovertarget]'),mobileMenu=page.locator('#production-actions-'+mobileId),mobileDetails=page.locator('#production-detail-'+mobileId);
   await mobileToggle.click();
   const menuBounds=await mobileMenu.boundingBox();assert(menuBounds.x>=0&&menuBounds.x+menuBounds.width<=width+1,'Menu contenu dans l’écran.');
   await mobileMenu.getByRole('button',{name:'Détails',exact:true}).click();await assertLayout(page,'production-detail-'+mobileId);
   assert(await mobileDetails.isVisible());assert(await mobileDetails.locator('button[type=submit]').last().evaluate(el=>el.getBoundingClientRect().height>=44));
   await page.keyboard.press('Escape');assert(await mobileToggle.evaluate(el=>el===document.activeElement));
   assert(await table.evaluate(el=>el.getBoundingClientRect().right<=innerWidth+1));
   assert(!(await rows.evaluateAll(elements=>elements.some(element=>element.scrollWidth>element.clientWidth+1))));
   const id='production-kpi-output',modal=page.locator('#'+id);
   await page.locator('[data-workspace-modal-open="'+id+'"]').click();await assertLayout(page,id);await page.keyboard.press('Escape');
   assert(await modal.isHidden());
  }
  assert.deepEqual(mutations,[],'Aucune requête de modification navigateur n’est tentée.');
  assert.deepEqual(errors,[],'Aucune erreur JavaScript.');
  console.log('OK : transformations en tableau 10/25/50 lignes ; pagination et recherche accentuée ; tris matière/produits/pertes ; quatre KPI métier indépendants en datatables ; détails, actions et anciens dossiers ; filtres persistants ; mobile 390/320 px ; aucune écriture navigateur.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
