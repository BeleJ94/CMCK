const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('assert');
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try {
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'}),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:8099/packaging');
  await page.click('[data-pack-tab="history"]');
  assert(await page.locator('[data-pack-space="history"]').isVisible());
  assert(!(await page.locator('[data-pack-space="available"]').isVisible()));
  await page.click('[data-pack-tab="available"]');
  const row=page.locator('[data-pack-lot]').first();
  if(await row.count()) {
   const id=await row.getAttribute('data-pack-lot');await row.click();
   await page.waitForFunction(id=>document.querySelector('[data-packaging-batch]').value===id,id);
   const format=page.locator('#packagingEditor [data-bag-format]');
   const value=await format.evaluate(s=>Array.from(s.options).find(o=>o.value&&!o.disabled)?.value);
   if(value){await format.selectOption(value);await page.locator('#packagingEditor [data-bags-count]').fill('1');assert((await page.locator('[data-pack-summary]').textContent()).includes('kg'));
    assert((await page.locator('[data-pack-stock-message]').textContent()).includes('Atelier :'));
    await page.locator('[data-pack-refresh]').click();
    await page.waitForFunction(()=>document.querySelector('[data-pack-stock-message]').textContent.includes('Stocks actualisés.'));
    assert.equal(await page.locator('#packagingEditor [data-bags-count]').inputValue(),'1');}
   await page.locator('#packagingEditor .modal-close').click();
  }
  await page.screenshot({path:'/private/tmp/packaging-desktop.png'});
  for(const width of [1440,390,320]){
   await page.setViewportSize({width,height:900});
   await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth);
   await page.locator('.dashboard-hero [data-workspace-modal-open]').click();
   assert(await page.locator('#packagingEditor footer .btn-primary').isVisible());
   const bounds=await page.locator('#packagingEditor footer').boundingBox();
   assert(bounds.y>=0 && bounds.y+bounds.height<=901, 'Footer must fit entirely inside viewport');
   const modal=await page.locator('#packagingEditor').boundingBox();assert(Math.abs(modal.y)<1,'Drawer must start at viewport top');
   await page.locator('#packagingEditor .feed-form-body').evaluate(e=>e.scrollTop=e.scrollHeight);
   assert(await page.locator('[data-pack-summary]').isVisible());
   await page.screenshot({path:'/private/tmp/packaging-'+width+'.png'});
   await page.locator('#packagingEditor .modal-close').click();
  }
  const results=await page.evaluate(async()=>{
   const f=document.querySelector('#packagingEditor form');const results=[];
   for(const invalid of [false,true]){const data=new FormData(f);data.set('production_batch_id','');data.set('packaged_at','not-a-date');if(invalid)data.set('_token','invalid');const r=await fetch(f.action,{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest'}});results.push({status:r.status,body:await r.json()});}return results;
  });
  assert.equal(results[0].status,422);assert(results[0].body.message.includes('date'));assert.equal(results[1].status,419);assert.deepEqual(errors,[]);
  console.log('OK : onglets, présélection du lot, calcul, modal mobile 390/320, erreurs AJAX 422/419 et aucune erreur JavaScript.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
