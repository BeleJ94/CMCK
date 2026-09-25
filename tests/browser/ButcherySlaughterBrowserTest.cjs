const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('assert'),fs=require('fs'),os=require('os'),path=require('path');
(async()=>{const browser=await chromium.launch({executablePath:process.env.DAGRIL_CHROME||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});try{
const page=await browser.newPage({viewport:{width:1440,height:900},reducedMotion:'reduce'}),errors=[];page.on('pageerror',e=>errors.push(e.message));
assert.equal((await page.goto('http://127.0.0.1:8099/butchery/slaughters')).status(),200);
await page.evaluate(html=>{document.querySelector('[data-butchery-workspace]').outerHTML=html;window.DagrilExperience.hydrate(document);},fs.readFileSync(path.join(os.tmpdir(),'dagril-slaughter-workspace.html'),'utf8'));
for(let index=0;index<2;index++){
 const trigger=page.locator('.butchery-kpi').nth(index);await trigger.click();const modal=page.locator('#butchery-kpi-'+index);assert(await modal.isVisible());assert.equal(await modal.locator('[data-dagril-table=ready]').count(),1);
 const search=modal.locator('input[type=search]');await search.fill('introuvable-xyz');assert((await modal.locator('tbody tr:visible').textContent()).includes('Aucun'));await search.fill('');
 for(const width of [390,320]){await page.setViewportSize({width,height:800});await page.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));const f=await modal.locator('footer').boundingBox();assert(f.y+f.height<=801&&f.x>=0&&f.x+f.width<=width+1);}
 await page.keyboard.press('Escape');assert(await modal.isHidden());assert(await trigger.evaluate(el=>el===document.activeElement));await page.setViewportSize({width:1440,height:900});
}
await page.getByRole('button',{name:'Enregistrer un abattage',exact:true}).first().click();const modal=page.locator('.butchery-editor.is-open'),select=modal.locator('[name=live_lot_id]');assert(await modal.locator('[data-live-summary]').isHidden());
const value=await select.locator('option').evaluateAll(os=>os.find(o=>o.value)?.value);assert(value);await select.selectOption(value);assert(await modal.locator('[data-live-summary]').isVisible());assert.equal(await modal.locator('[data-live-field=heads]').textContent(),'10');assert.equal(await modal.locator('[name=input_heads]').getAttribute('max'),'10');
await modal.locator('[name=input_heads]').fill('11');assert(await modal.locator('[name=input_heads]').evaluate(el=>el.validity.rangeOverflow));
await select.selectOption('');assert(await modal.locator('[data-live-summary]').isHidden());assert.equal(await modal.locator('[name=input_heads]').getAttribute('max'),null);await select.selectOption(value);
for(const width of [390,320]){await page.setViewportSize({width,height:800});assert(await modal.locator('[data-live-summary]').evaluate(el=>el.scrollWidth<=el.clientWidth+1));}
await modal.locator('.modal-close').click();await page.getByRole('button',{name:'Enregistrer un abattage',exact:true}).first().click();assert(await page.locator('.butchery-editor.is-open [data-live-summary]').isHidden());
assert.deepEqual(errors,[]);console.log('OK : KPI et DataTables, clavier, détails du lot, maximum de têtes, reset et mobile 320/390.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
