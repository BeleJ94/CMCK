const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');const assert=require('node:assert/strict');const fs=require('node:fs');
(async()=>{const b=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});try{
 const p=await b.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'}),errors=[];p.on('pageerror',e=>errors.push(e.message));
 await p.goto((process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8110')+'/livestock/pigs/shipments');
 await p.locator('[data-flow-page-size]').selectOption('50');
 const rows=p.locator('[data-shipment-id]');assert(await rows.count()>0,'Envois de test requis');
 const modal=p.locator('#flow-shipment-btr');
 for(let i=0;i<Math.min(2,await rows.count());i++){
  const row=rows.nth(i),trigger=row.locator('[data-btr-preview]');
  const reference=await row.getAttribute('data-reference'),url=p.url();
  await trigger.click();await modal.locator('[data-btr-print]').waitFor({state:'visible'});
  await p.waitForFunction(()=>!document.querySelector('[data-btr-print]').disabled);
  assert(await modal.isVisible());assert.equal(p.url(),url);
  const frame=p.frameLocator('[data-btr-frame]');
  assert((await frame.locator('.shipment-btr-document').innerText()).includes(reference));
  if((await row.getAttribute('data-state'))==='cancelled')assert.match(await frame.locator('.btr-status').innerText(),/ANNULÉ/);
  const iframe=await p.locator('[data-btr-frame]').elementHandle(),doc=await iframe.contentFrame();
  await doc.evaluate(()=>{window.print=()=>{window.__btrPrinted=true;};});
  await modal.locator('[data-btr-print]').click();assert(await doc.evaluate(()=>window.__btrPrinted===true),'Le bouton imprime seulement le document');
  const response=await p.request.get(await modal.locator('[data-btr-download]').getAttribute('href'));
  assert.equal(response.status(),200);assert.match(response.headers()['content-type'],/application\/pdf/);
  const pdf=await response.body();assert.equal(pdf.subarray(0,5).toString(),'%PDF-');
  if(i===0)fs.writeFileSync('/private/tmp/dagril-shipment-btr-test.pdf',pdf);
  await p.keyboard.press('Escape');assert(!(await modal.isVisible()));assert(await trigger.evaluate(e=>document.activeElement===e));
 }
 for(const width of [390,320]){
  await p.setViewportSize({width,height:900});
  const trigger=rows.first().locator('[data-btr-preview]');await trigger.click();
  await p.waitForFunction(()=>!document.querySelector('[data-btr-print]').disabled);
  assert(await modal.evaluate(e=>e.scrollWidth<=e.clientWidth+1),'Fenêtre sans débordement horizontal');
  const doc=await (await p.locator('[data-btr-frame]').elementHandle()).contentFrame();
  assert(await doc.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'BTR lisible sans défilement horizontal');
  await doc.locator('.btr-heading h1').click();await doc.locator('body').press('Escape');
  assert(!(await modal.isVisible()),'Échap ferme aussi depuis l’aperçu');
 }
 assert.deepEqual(errors,[]);console.log('OK : BTR par envoi, statut réel, aperçu sans navigation, impression du document, PDF, focus/Échap et mobile.');
}finally{await b.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
