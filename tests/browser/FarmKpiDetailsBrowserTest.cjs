const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');const assert=require('node:assert/strict');
(async()=>{const b=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});try{
 const p=await b.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'}),errors=[];p.on('pageerror',e=>errors.push(e.message));
 const base=process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8110';
 for(const route of ['slaughters','shipments']){
  await p.goto(base+'/livestock/pigs/'+route);await p.locator('[data-flow-search]').fill('conserver-ce-filtre');await p.waitForFunction(()=>new URL(location.href).searchParams.get('q')==='conserver-ce-filtre');
  const url=p.url(),before=await p.locator('[data-flow-item]:visible').count();
  for(const category of ['submitted','validated','stock','shipment']){
   const trigger=p.locator(`[data-flow-kpi="${category}"]`);await trigger.click();
   const modal=p.locator('#flow-kpi-detail');assert(await modal.isVisible());
   assert.equal(p.url(),url);assert.equal(await p.locator('[data-flow-search]').inputValue(),'conserver-ce-filtre');
   assert.equal(await p.locator('[data-flow-item]:visible').count(),before);
   assert.equal(await modal.locator('[data-kpi-record]:visible:not([data-category="'+category+'"])').count(),0);
   assert((await modal.locator('[data-kpi-record]:visible').count())<=10);
   await modal.locator('[data-kpi-search]').fill('aucun-lot-ne-correspond');assert(await modal.locator('[data-kpi-empty]').isVisible());
   await p.keyboard.press('Escape');assert(!(await modal.isVisible()));assert(await trigger.evaluate(e=>document.activeElement===e));
  }
 }
 // DOM-only sample to exercise independent pagination and sorting.
 await p.evaluate(()=>{const old=document.querySelector('[data-flow-directory]'),panel=old.cloneNode(true);delete panel.dataset.ready;const body=panel.querySelector('#flow-kpi-detail tbody'),sample=body.querySelector('tr').cloneNode(true);body.replaceChildren();for(let i=1;i<=23;i++){const row=sample.cloneNode(true);Object.assign(row.dataset,{category:'validated',reference:'KPI-'+i,date:'2026-10-16',heads:String(i),weight:String(i),search:'kpi-fixture-'+i});body.append(row);}old.replaceWith(panel);window.initPorcineFlow(panel);});
 await p.locator('[data-flow-kpi="validated"]').click();const modal=p.locator('#flow-kpi-detail');
 assert.equal(await modal.locator('[data-kpi-record]:visible').count(),10);
 await modal.locator('[data-kpi-page="1"]').click();assert.equal(await modal.locator('[data-kpi-record]:visible').count(),10);
 await modal.locator('[data-kpi-page="1"]').click();assert.equal(await modal.locator('[data-kpi-record]:visible').count(),3);
 await modal.locator('[data-kpi-size]').selectOption('25');assert.equal(await modal.locator('[data-kpi-record]:visible').count(),23);
 await modal.locator('[data-kpi-sort="heads"]').click();assert.equal(await modal.locator('[data-kpi-record]:visible').first().getAttribute('data-heads'),'1');
 await modal.locator('[data-kpi-sort="heads"]').click();assert.equal(await modal.locator('[data-kpi-record]:visible').first().getAttribute('data-heads'),'23');
 for(const width of [390,320]){await p.setViewportSize({width,height:900});assert(!(await modal.locator('[data-kpi-record]:visible').evaluateAll(rows=>rows.some(r=>r.scrollWidth>r.clientWidth+1))));}
 assert.deepEqual(errors,[]);console.log('OK : quatre modales sur les deux écrans, filtres principaux préservés, recherche, pagination 10 lignes, tri, focus et mobile.');
}finally{await b.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
