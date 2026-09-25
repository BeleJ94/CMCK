const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright'),assert=require('assert');
(async()=>{const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});try{
const page=await browser.newPage({viewport:{width:1440,height:900},reducedMotion:'reduce'}),errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.goto('http://127.0.0.1:8099/livestock/conversions');
const status=page.locator('[data-conversion-filter=status]'),type=page.locator('[data-conversion-filter=type]'),from=page.locator('[data-conversion-filter=from]'),to=page.locator('[data-conversion-filter=to]');
await status.selectOption('validated');assert.equal(await page.locator('tbody tr:visible').filter({hasText:'TST-CONVERSION-UX'}).count(),0);
await status.selectOption('submitted');await type.selectOption('fish_catch');assert.equal(await page.locator('tbody tr:visible').filter({hasText:'TST-CONVERSION-UX'}).count(),0);
await type.selectOption('slaughter');assert.equal(await page.locator('tbody tr:visible').filter({hasText:'TST-CONVERSION-UX'}).count(),1);
await from.fill('2099-01-01');await to.fill('2000-01-01');await to.blur();assert(await page.locator('.conversion-filter-error').isVisible());
await page.getByRole('button',{name:'Réinitialiser'}).click();assert.equal(await status.inputValue(),'');assert.equal(await from.inputValue(),'');assert(!(await page.locator('.conversion-filter-error').isVisible()));
const search=page.getByRole('searchbox',{name:'Rechercher une conversion'});await search.fill('TST-CONVERSION-UX');assert.equal(await page.locator('tbody tr:visible').count(),1);
for(const width of [1440,390,320]){await page.setViewportSize({width,height:800});const bar=await page.locator('.conversion-filterbar').boundingBox();assert(bar.x>=0&&bar.x+bar.width<=width+1);}
await page.setViewportSize({width:1440,height:900});
const row=page.locator('tbody tr').filter({hasText:'TST-CONVERSION-UX'});assert.equal(await row.count(),1);
await row.getByRole('button',{name:'Consulter'}).click();
let modal=page.locator('.livestock-editor.is-open');assert(await modal.getByText('Aucun mouvement de stock',{exact:false}).isVisible());
assert(await modal.getByRole('button',{name:'Valider et créer le stock'}).isVisible());
for(const width of [1440,390,320]){await page.setViewportSize({width,height:800});const f=await modal.locator('footer').boundingBox();assert(f.y+f.height<=801);assert(f.x>=0);assert(f.x+f.width<=width+1);}
await modal.locator('.modal-close').click();await page.getByRole('button',{name:'Nouvelle conversion'}).click();
modal=page.locator('#livestock-conversions');await modal.locator('[name=gross_weight_kg]').fill('120');await modal.locator('[name=tare_weight_kg]').fill('20');assert.equal(await modal.locator('[data-conversion-net]').textContent(),'100 kg');
await modal.locator('[name=tare_weight_kg]').fill('125');assert.equal(await modal.locator('[data-conversion-net]').textContent(),'—');assert(await modal.locator('[name=tare_weight_kg]').evaluate(e=>!e.validity.valid));
await page.screenshot({path:'/private/tmp/livestock-conversion-mobile.png'});assert.deepEqual(errors,[]);
console.log('OK : détail latéral, validation contextualisée, calcul net, tare invalide et actions visibles à 1440/390/320 px.');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
