// Client exits hub rendered by tests/DistributionViewTest.php (run it first): filters of the history, details, delivery drawer,
// exit without an order with its confirmation and a refused save, stock drawer and phones.
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright'),assert=require('assert'),fs=require('fs'),os=require('os');
const base=process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8099';
(async()=>{const b=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});try{
 const p=await b.newPage({viewport:{width:1440,height:900},reducedMotion:'reduce'}),errors=[];p.on('pageerror',e=>errors.push(e.message));
 assert.equal((await p.goto(base+'/distributions')).status(),200);assert.equal(await p.locator('.distribution-hub').count(),1);
 await p.evaluate(html=>{document.querySelector('.content-area').innerHTML=html;window.DagrilExperience.hydrate(document.querySelector('.content-area'));},fs.readFileSync(os.tmpdir()+'/dagril-distributions.html','utf8'));
 // One history for delivery notes and exit notes.
 const rows=p.locator('#distributionsTable tbody tr:visible');assert.equal(await rows.count(),8);
 await p.locator('[data-conversion-filter=type]').selectOption('delivery');assert.equal(await rows.count(),2);
 await p.locator('[data-conversion-filter=status]').selectOption('validated');assert.equal(await rows.count(),1);
 await p.getByRole('button',{name:'Réinitialiser'}).click();await p.locator('[data-conversion-filter=type]').selectOption('direct');assert.equal(await rows.count(),6);
 await p.locator('.fish-section input[type=search]').fill('BS-TEST-3');assert.equal(await rows.count(),1);
 await p.getByRole('button',{name:'Consulter BS-TEST-3'}).click();assert((await p.locator('#distribution-detail-3').textContent()).includes('<script>unsafe</script>'));await p.keyboard.press('Escape');
 // Delivery drawer of the ready order: the office first, the mill as the other choice.
 await p.locator('[data-delivery-order="7"]').getByRole('button',{name:'Livrer'}).click();const deliver=p.locator('#deliver-7 form');
 await p.waitForFunction(()=>document.querySelector('#deliver-7 [data-line-input]').value==='15');
 assert.match(await deliver.locator('[data-delivery-summary]').innerText(),/15 sac\(s\) · 750 kg · 525,00 USD · 1\s470\s000 CDF/);
 await deliver.locator('label:has([name=source][value="source-1"])').click();assert.equal(await deliver.locator('[data-line-input]').inputValue(),'10');await p.keyboard.press('Escape');
 // Exit without an order: lot maximum, live weight, confirmation, refusal kept in the form.
 await p.locator('.ui-page-header').getByRole('button',{name:'Sortie sans commande'}).click();const modal=p.locator('#distribution-create'),f=modal.locator('form');
 await f.locator('[name=finished_stock_id]').selectOption('1');await f.locator('[name=quantity_bags]').fill('31');assert.equal(await f.locator('[name=quantity_bags]').evaluate(e=>e.checkValidity()),false);
 await f.locator('[name=quantity_bags]').fill('2');await f.locator('[name=recipient_name]').fill('Client');assert((await f.locator('[data-distribution-summary]').textContent()).includes('Sortie : 50 kg'));
 await f.evaluate((el,url)=>el.action=url,base+'/distributions');
 await p.route('**/distributions',r=>r.request().method()==='POST'?r.fulfill({status:422,contentType:'application/json',body:JSON.stringify({success:false,message:'Erreur simulée'})}):r.continue());
 await f.getByRole('button',{name:'Vérifier et sortir'}).click();const dialog=p.locator('[data-exit-confirm-dialog]');await dialog.waitFor({state:'visible'});
 assert.match(await dialog.locator('.op-confirm__lead').innerText(),/^Client · depuis Minoterie/);await dialog.locator('[data-exit-confirm-ok]').click();
 await f.locator('[data-distribution-error]').waitFor({state:'visible'});assert.equal(await f.locator('[name=quantity_bags]').inputValue(),'2');
 await f.locator('[name=customer_id]').selectOption('4');assert(await f.locator('[data-exit-recipient]').isHidden());
 for(const width of [390,320]){await p.setViewportSize({width,height:800});await p.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));const box=await modal.locator('footer').boundingBox();assert(box.x>=0&&box.x+box.width<=width+1&&box.y+box.height<=801);}
 await p.keyboard.press('Escape');assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 await p.locator('[data-workspace-modal-open=distribution-kpi-stock]').click();assert.equal(await p.locator('#distribution-kpi-stock tbody tr:visible').count(),1);
 assert.deepEqual(errors,[]);console.log('OK : historique BL et BS filtré, détails, livraison, sortie sans commande, refus, stock et mobile.');
}finally{await b.close();}})().catch(e=>{console.error(e);process.exit(1)});
