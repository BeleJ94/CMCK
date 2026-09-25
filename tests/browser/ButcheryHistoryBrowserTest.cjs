const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('assert'),fs=require('fs'),os=require('os'),path=require('path');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.DAGRIL_CHROME||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:900},reducedMotion:'reduce'}),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  for(const section of ['receipts','slaughters']){
   assert.equal((await page.goto('http://127.0.0.1:8099/butchery/'+section)).status(),200);
   assert(await page.locator('.butchery-history').isVisible());
   // Real query/view output produced inside a rolled-back test transaction.
   const fixture=fs.readFileSync(path.join(os.tmpdir(),'dagril-history-'+section+'.html'),'utf8');
   await page.evaluate(html=>{const workspace=document.querySelector('[data-butchery-workspace]');workspace.innerHTML=html;delete workspace.dataset.ready;window.DagrilExperience.hydrate(document);},fixture);
   const table=page.locator('.butchery-history'),search=table.locator('input[type=search]');
   assert.equal(await table.locator('tbody tr:visible').count(),section==='receipts'?10:3);
   if(section==='receipts'){
    await table.locator('[aria-label="Page 2"]').click();assert.equal(await table.locator('tbody tr:visible').count(),2);
    await table.locator('select.dagril-table-size').selectOption('25');assert.equal(await table.locator('tbody tr:visible').count(),12);
    await search.fill('Refusée');assert.equal(await table.locator('tbody tr:visible').count(),3);
    await search.fill('introuvable-xyz');assert((await table.locator('tbody').textContent()).includes('Aucun'));
    await search.fill('');
   }
   const status=table.locator('[data-conversion-filter=status]');
   await status.selectOption(section==='receipts'?'accepted':'validated');
   assert.equal(await table.locator('tbody tr:visible').count(),section==='receipts'?3:1);
   if(section==='receipts'){
    await table.locator('[data-conversion-filter=type]').selectOption('internal_btr');
    assert((await table.locator('tbody tr:visible').textContent()).includes('Aucun'));
    await table.locator('[data-conversion-filter=type]').selectOption('external_bra');
    await table.locator('[data-conversion-filter=from]').fill('2026-09-02');
    await table.locator('[data-conversion-filter=to]').fill('2026-09-02');
    await table.locator('[data-conversion-filter=to]').dispatchEvent('change');
    assert.equal(await table.locator('tbody tr:visible').count(),1);
    await table.locator('[data-conversion-filter=from]').fill('2026-09-03');
    await table.locator('[data-conversion-filter=from]').dispatchEvent('change');
    assert(await table.locator('.conversion-filter-error').isVisible());
   }
   await table.locator('.conversion-filter-reset').click();
   assert.equal(await status.inputValue(),'');
   assert(await table.locator('.conversion-filter-error').isHidden());
   await table.locator('th').first().locator('button').click();assert.equal(await table.locator('th').first().getAttribute('aria-sort'),'ascending');
   const trigger=table.locator('tr:visible [data-workspace-modal-open]').first();await trigger.click();
   const modal=page.locator('.butchery-editor.is-open');assert(await modal.isVisible());assert(await modal.locator('dl').isVisible());
   assert.equal(await modal.locator('form').count(),0);
   for(const width of [1440,390,320]){
    await page.setViewportSize({width,height:800});
    await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
    const footer=await modal.locator('footer').boundingBox();assert(footer.y+footer.height<=801&&footer.x>=0&&footer.x+footer.width<=width+1);
   }
   await page.keyboard.press('Escape');assert(await modal.isHidden());
   assert(await trigger.evaluate(el=>el===document.activeElement));
   for(const width of [390,320]){
    await page.setViewportSize({width,height:800});await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
   }
   await page.setViewportSize({width:1440,height:900});
   await page.screenshot({path:path.join(os.tmpdir(),'dagril-history-'+section+'.png')});
  }
  assert.deepEqual(errors,[]);console.log('OK : tableaux alimentés, recherche, pagination, tri, détails en lecture, clavier et mobile 320/390.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
