const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto((process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8110')+'/livestock/pigs/slaughters');
  assert.equal(await page.locator('table[aria-label="Abattages à la ferme"]').count(),1);
  assert.equal(await page.locator('[data-flow-page-size]').inputValue(),'10');
  // Add display-only rows to exercise pagination without creating abattages.
  await page.evaluate(()=>{
   const old=document.querySelector('[data-flow-directory]'),panel=old.cloneNode(true);
   delete panel.dataset.ready;
   sessionStorage.removeItem('porcine-flow-filters:'+panel.dataset.siteScope+':'+panel.dataset.pageScope);
   history.replaceState(history.state,'',location.pathname);
   const body=panel.querySelector('[data-flow-table-body]');body.replaceChildren();
   for(let i=1;i<=23;i++){
    const row=document.createElement('tr');
    Object.assign(row.dataset,{flowItem:'',flowKey:'test-'+i,kind:'slaughter',state:i<=11?'submitted':'validated',reference:'AB-'+i,date:'2026-10-'+String(i).padStart(2,'0'),heads:String(i),weight:String(i*10),expiry:'2026-11-01',search:'test-abattage-'+i});
    row.innerHTML='<td data-label="Abattage / lot"><strong>AB-'+i+'</strong><small>Ferme de test</small></td><td data-label="Date">16/10/2026</td><td data-label="Porcs">'+i+'</td><td data-label="Carcasses (kg)">'+i*10+'</td><td data-label="DLC">01/11/2026</td><td data-label="État">'+row.dataset.state+'</td><td data-label="Actions"><details><summary>Détails et actions</summary>Traçabilité</details></td>';
    body.append(row);
   }
   old.replaceWith(panel);window.initPorcineFlow(panel);
  });
  const rows=page.locator('[data-flow-item]:visible');
  assert.equal(await rows.count(),10);
  assert.equal(await rows.first().getAttribute('data-heads'),'23');
  await page.locator('[data-flow-page="1"]').click();assert.equal(await rows.count(),10);
  await page.locator('[data-flow-page="1"]').click();assert.equal(await rows.count(),3);
  assert(await page.locator('[data-flow-page="1"]').isDisabled());
  assert(!(await page.locator('[data-flow-filter-update]').isVisible()),'La pagination ne doit pas signaler des résultats masqués par filtre');
  await page.locator('[data-flow-state]').selectOption('submitted');
  assert.equal(await rows.count(),10);
  assert.equal(await rows.first().getAttribute('data-heads'),'11');
  await page.locator('[data-flow-page="1"]').click();assert.equal(await rows.count(),1);
  await page.locator('[data-flow-search]').fill('test-abattage-11');assert.equal(await rows.count(),1);
  await page.locator('[data-flow-reset]').click();
  await page.locator('[data-flow-page-size]').selectOption('25');assert.equal(await rows.count(),23);
  await page.locator('[data-flow-sort="heads"]').click();assert.equal(await rows.first().getAttribute('data-heads'),'1');
  await page.locator('[data-flow-sort="heads"]').click();assert.equal(await rows.first().getAttribute('data-heads'),'23');
  for(const width of [390,320]){await page.setViewportSize({width,height:900});assert(!(await rows.evaluateAll(es=>es.some(e=>e.scrollWidth>e.clientWidth+1))));}
  assert.deepEqual(errors,[]);
  console.log('OK : tableau abattages, pagination 10/25 lignes, filtre état, recherche, tri numérique et mobile.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
