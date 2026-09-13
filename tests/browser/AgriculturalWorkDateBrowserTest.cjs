if (!/_(test|testing)$/.test(process.env.DAGRIL_DB_DATABASE || '')) throw new Error('Test database required');
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE || 'playwright');const assert=require('assert');
(async()=>{const browser=await chromium.launch({executablePath:process.env.DAGRIL_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});try{
 const page=await browser.newPage({viewport:{width:390,height:844},timezoneId:'Pacific/Honolulu'});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 // Fixed clock and campaign periods make calendar boundary cases reproducible.
 await page.route('**/agriculture/works',async route=>{const response=await route.fetch();let html=await response.text();html=html.replace(/(<script type="application\/json" data-work-data>)([\s\S]*?)(<\/script>)/,(_,start,json,end)=>{const data=JSON.parse(json),plan=data.plans.find(p=>p.campaign_name==='Test travaux'&&p.campaign_status==='in_progress');data.server_now='2026-09-12T14:30:00+02:00';data.plans=[{...plan,id:90001,campaign_id:90001,campaign_name:'Campagne passée',start_date:'2026-01-01',end_date:'2026-08-31'},{...plan,id:90002,campaign_id:90002,campaign_name:'Campagne courante',start_date:'2026-09-01',end_date:'2026-12-31'},{...plan,id:90003,campaign_id:90003,campaign_name:'Campagne future',start_date:'2026-10-01',end_date:'2026-12-31'}];return start+JSON.stringify(data).replace(/</g,'\\u003c')+end;});await route.fulfill({response,body:html});});
 await page.goto('http://127.0.0.1:8099/agriculture/works');await page.click('[data-work-new]');const date=page.locator('[name="worked_at"]'),message=page.locator('[data-work-date-error]');assert.equal(await date.inputValue(),'2026-09-12T14:30','Application timezone, independent of browser timezone');
 const site=await page.locator('[data-work-site] option').nth(1).getAttribute('value');await page.selectOption('[data-work-site]',site);
 async function select(id){await page.selectOption('[data-work-campaign]',String(id));await page.selectOption('[name="campaign_plot_id"]',String(id));}
 await select(90001);await date.fill('2026-09-01T10:00');assert((await message.innerText()).includes('31/08/2026'));assert(!await date.evaluate(e=>e.checkValidity()));
 await select(90002);await date.fill('2026-09-11T10:00');assert(await date.evaluate(e=>e.checkValidity()));assert(!await message.isVisible());
 await date.fill('2026-08-31T10:00');assert((await message.innerText()).includes('01/09/2026'));
 await date.fill('2026-09-12T15:00');assert((await message.innerText()).includes('future'));assert((await message.innerText()).includes('14:30'));
 await date.fill('');assert((await message.innerText()).includes('incomplètes'));
 await select(90003);await date.fill('2026-10-01T10:00');assert((await message.innerText()).includes('déjà commencée'));
 await select(90002);await date.fill('2026-08-31T10:00');await date.scrollIntoViewIfNeeded();await page.screenshot({path:require('os').tmpdir()+'/work-date-error-mobile.png'});
 await page.selectOption('[data-work-site]','');assert.equal(await date.getAttribute('min'),null,'Old campaign minimum cleared');await page.keyboard.press('Escape');await page.click('[data-work-new]');assert.equal(await date.getAttribute('min'),null);assert(await date.evaluate(e=>e.checkValidity()));assert.deepEqual(errors,[]);console.log('OK: timezone, date entry, past/current/future campaigns, explicit errors, cleared stale constraints, mobile.');
 }finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
