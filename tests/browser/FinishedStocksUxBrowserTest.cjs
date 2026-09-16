const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright'),assert=require('assert');
(async()=>{const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});try{
const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'}),errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.goto('http://127.0.0.1:8099/finished-stocks');
for(const tab of ['products','formats','entries','outputs']){await page.click('[data-finished-tab="'+tab+'"]');assert.equal(await page.locator('[data-finished-space]:visible').count(),1);assert(await page.locator('[data-finished-space="'+tab+'"]').isVisible());assert.equal(await page.locator('[data-finished-space="'+tab+'"] .dagril-table-toolbar').count(),['products','formats'].includes(tab)?0:1);}
await page.click('[data-finished-tab="formats"]');
const total=await page.locator('[data-format-card]').count();assert(total>0);
for(const key of ['product','weight','state']){
 const select=page.locator('[data-format-filter="'+key+'"]');
 const options=await select.locator('option').evaluateAll(options=>options.map(o=>o.value).filter(Boolean));
 for(const value of options){await select.selectOption(value);const values=await page.locator('[data-format-card]:visible').evaluateAll((cards,key)=>cards.map(c=>c.dataset[key]),key);assert(values.every(v=>v===value));}
 await page.click('[data-format-reset]');assert.equal(await page.locator('[data-format-card]:visible').count(),total);
}
await page.screenshot({path:'/private/tmp/finished-formats-desktop.png'});
for(const width of [390,320]){await page.setViewportSize({width,height:800});await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth);await page.screenshot({path:'/private/tmp/finished-formats-'+width+'.png'});}
await page.setViewportSize({width:1440,height:1000});
await page.click('[data-finished-tab="products"]');assert(await page.locator('.finished-stock-card').count()>0);assert.equal(await page.locator('.finished-stock-card').count(),await page.locator('.finished-palette').count());await page.screenshot({path:'/private/tmp/finished-stocks-desktop.png'});
for(const width of [1440,390,320]){await page.setViewportSize({width,height:800});await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth);await page.screenshot({path:'/private/tmp/finished-cards-'+width+'.png'});await page.locator('[data-finished-space="products"] [data-workspace-modal-open]').first().click();const modal=page.locator('.finished-detail.is-open');assert(await modal.isVisible());const bounds=await modal.boundingBox();assert(Math.abs(bounds.y)<1);const footer=await modal.locator('footer').boundingBox();assert(footer.y+footer.height<=801);await modal.locator('.feed-form-body').evaluate(e=>e.scrollTop=e.scrollHeight);await page.screenshot({path:'/private/tmp/finished-stocks-'+width+'.png'});await modal.locator('.modal-close').click();}
assert.deepEqual(errors,[]);console.log('OK : quatre onglets, tableaux avec recherche/pagination, détails latéraux, mobile 390/320 et aucun débordement des boutons.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
