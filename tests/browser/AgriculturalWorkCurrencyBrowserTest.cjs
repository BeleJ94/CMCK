if (!/_(test|testing)$/.test(process.env.DAGRIL_DB_DATABASE || '')) throw new Error('Test database required');
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE || 'playwright');
const assert=require('assert');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.DAGRIL_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try {
  const page=await browser.newPage({viewport:{width:390,height:844}}), errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:8099/agriculture/works');
  const data=await page.locator('[data-work-data]').textContent();
  const plan=JSON.parse(data).plans.find(p=>p.campaign_name==='Test travaux'&&p.campaign_status==='in_progress');
  await page.click('[data-work-new]');
  const rate=page.locator('[name="exchange_rate"]'),currency=page.locator('[name="currency"]');
  assert.equal(await currency.inputValue(),'USD');assert.equal(await rate.inputValue(),'1');assert(await rate.evaluate(e=>e.readOnly));
  await currency.selectOption('CDF');assert.equal(await rate.inputValue(),'');assert(!await rate.evaluate(e=>e.checkValidity()));
  await rate.fill('2800');await currency.selectOption('USD');assert.equal(await rate.inputValue(),'1');
  await currency.selectOption('CDF');assert.equal(await rate.inputValue(),'');await rate.fill('2800');
  await page.selectOption('[data-work-site]',String(plan.site_id));await page.selectOption('[data-work-campaign]',String(plan.campaign_id));await page.selectOption('[name="campaign_plot_id"]',String(plan.id));
  const marker='Currency-UI-'+Date.now();await page.fill('[name="responsible_name"]',marker);await page.fill('[name="worked_at"]','2026-09-11T09:30');await page.fill('[name="other_cost"]','28000');await page.fill('[name="other_cost_reason"]','Test taux');
  assert((await page.locator('[data-work-conversion]').innerText()).includes('10,00 USD'));
  await rate.scrollIntoViewIfNeeded();await page.screenshot({path:require('os').tmpdir()+'/work-currency-mobile.png'});
  let navigations=0;page.on('framenavigated',f=>{if(f===page.mainFrame())navigations++;});
  await page.click('[data-work-submit]');await page.click('.swal2-confirm');await page.waitForFunction(()=>!document.querySelector('[data-work-editor]').open);
  await page.fill('[data-work-filter="search"]',marker);await page.click('[data-work-open]');
  assert((await page.locator('[data-work-detail]').innerText()).includes('10,00 USD'));
  await page.click('[data-work-edit]');assert.equal(await rate.inputValue(),'2800.000000');await rate.fill('1400');await page.fill('[name="reason"]','Correction taux test');
  await page.click('[data-work-submit]');await page.click('.swal2-confirm');await page.waitForFunction(()=>!document.querySelector('[data-work-editor]').open);
  await page.click('[data-work-open]');assert((await page.locator('[data-work-detail]').innerText()).includes('20,00 USD'));assert.equal(navigations,0);assert.deepEqual(errors,[]);
  console.log('OK: USD default, fixed rate, CDF required, currency switch, conversion, AJAX save and rate correction, mobile.');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
