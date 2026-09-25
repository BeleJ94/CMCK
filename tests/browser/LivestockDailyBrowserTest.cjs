const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright'),assert=require('assert');
(async()=>{const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});try{
const page=await browser.newPage({viewport:{width:1440,height:900},reducedMotion:'reduce'}),errors=[];page.on('pageerror',e=>errors.push(e.message));
const r=await page.goto('http://127.0.0.1:8099/livestock/daily');assert.equal(r.status(),200);
const lots=await page.locator('[data-daily-lots]').evaluate(e=>JSON.parse(e.textContent));
const cattle=lots.find(b=>b.category==='livestock'&&b.batch_number.startsWith('TST-CARDS-UI-')),bird=lots.find(b=>b.category==='poultry'&&b.batch_number.startsWith('TST-CARDS-UI-'));assert(cattle&&bird);
assert(!(await page.locator('[data-daily-actions]').isVisible()));
await page.locator('[data-daily-site]').selectOption(String(cattle.site_id));await page.locator('[data-daily-lot]').selectOption(String(cattle.id));
assert(await page.locator('[data-daily-action=weighings]').isVisible());assert(!(await page.locator('[data-daily-action=poultry]').isVisible()));
await page.locator('[data-daily-action=weighings]').click();const modal=page.locator('#livestock-weighings');
await page.waitForFunction(id=>document.querySelector('#livestock-weighings [name=batch_id]').value===id,String(cattle.id));
await modal.locator('[name=sample_heads]').fill('2');await modal.locator('[name=total_weight_kg]').fill('80');
assert((await modal.locator('[data-livestock-context]').textContent()).includes('40 kg / animal'));
await page.evaluate(()=>window.dailyNavigationMarker=true);
await modal.locator('[type=submit]').click();await page.locator('[data-action-dialog-confirm]').click();
await page.waitForFunction(()=>!document.querySelector('#livestock-weighings.is-open')&&document.querySelector('[data-daily-history]')?.textContent.includes('40,000'));
assert(await page.evaluate(()=>window.dailyNavigationMarker));assert.equal(await page.locator('[data-daily-lot]').inputValue(),String(cattle.id));
const row=page.locator('[data-daily-table] tbody tr:visible').filter({hasText:'40,000'});assert(await row.count()>0);
await row.first().getByRole('button',{name:'Consulter'}).click();assert(await page.locator('.is-open').getByText('2 animaux pesés',{exact:false}).isVisible());await page.locator('.is-open .modal-close').click();
await page.locator('[data-daily-lot]').selectOption(String(bird.id));assert(await page.locator('[data-daily-action=poultry]').isVisible());assert.equal(await page.locator('[data-daily-table] tbody tr:visible').filter({hasText:'40,000'}).count(),0);
await page.locator('[data-daily-action=poultry]').click();const poultry=page.locator('#livestock-poultry');await page.waitForFunction(id=>document.querySelector('#livestock-poultry [name=batch_id]').value===id,String(bird.id));await poultry.locator('[name=mortality_heads]').fill('2');assert((await poultry.locator('[data-livestock-context]').textContent()).includes('18 volailles'));
for(const width of [390,320]){await page.setViewportSize({width,height:800});const footer=await poultry.locator('footer').boundingBox();assert(footer.y+footer.height<=801);}
await poultry.locator('.modal-close').click();assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
assert.deepEqual(errors,[]);console.log('OK : sélection par lot, actions par espèce, pesée AJAX sans navigation, historique filtré, détail et mobile.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
