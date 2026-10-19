const assert=require('node:assert/strict'),fs=require('node:fs');
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
assert(/^dagril_porcin_[a-f0-9]{12}_test$/.test(process.env.DAGRIL_DB_DATABASE||''),'Base jetable obligatoire.');
const fx=JSON.parse(fs.readFileSync(process.env.DAGRIL_FLOW_FIXTURE,'utf8'));assert.equal(fx.database,process.env.DAGRIL_DB_DATABASE);
const base=process.env.DAGRIL_TEST_URL;assert(['localhost','127.0.0.1'].includes(new URL(base).hostname));
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'}),page=await context.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/butchery/counters');await page.waitForFunction(()=>document.querySelector('[data-butchery-pos]')?.dataset.posReady==='1');
  assert(!/Warning:|Fatal error:/.test(await page.locator('main').innerText()));
  await page.locator('[data-pos-counter]').selectOption(String(fx.counter));
  const add=async(item,weight)=>{await page.locator('[data-pos-item="'+item+'"]').click();const dialog=page.locator('[data-pos-weight]');await dialog.locator('[name=quantity]').fill(weight);await dialog.getByRole('button',{name:'Ajouter au panier',exact:true}).click();};
  await add(fx.item1,'1.250');await add(fx.item2,'0.500');assert.equal(await page.locator('[data-pos-lines] .pos-basket-line').count(),2);
  assert.match(await page.locator('[data-pos-total]').innerText(),/15,00 USD/);assert(await page.locator('[data-pos-currency]').isDisabled());
  const form=page.locator('[data-pos-payment]');await form.locator('[name=received_amount]').fill('10');assert(await page.locator('[data-pos-checkout]').isDisabled());
  await form.locator('[name=received_amount]').fill('20');await form.locator('[name=customer_name]').fill('Client <script>window.posInjected=1</script>');assert.match(await page.locator('[data-pos-change]').innerText(),/5,00 USD/);
  const token=await form.locator('[name=_token]').inputValue();let lostTicket=null,attempts=0;
  await page.route('**/butchery/pos/checkout',async route=>{attempts++;if(attempts===1){const result=await route.fetch();lostTicket=await result.json();assert(lostTicket.ok,lostTicket.message);return route.abort('failed');}return route.continue();});
  await page.locator('[data-pos-checkout]').click();await page.waitForFunction(()=>document.querySelector('[data-pos-error]')?.textContent.includes('Résultat non confirmé'));
  assert(await page.locator('[data-pos-clear]').isDisabled(),'Un paiement ambigu conserve son panier verrouillé.');
  await page.reload();await page.waitForFunction(()=>document.querySelector('[data-butchery-pos]')?.dataset.posReady==='1');assert.match(await page.locator('[data-pos-error]').innerText(),/reste à confirmer/);assert.equal(await form.locator('[name=received_amount]').inputValue(),'20.00');assert.equal(await form.locator('[name=customer_name]').inputValue(),'Client <script>window.posInjected=1</script>');
  await page.locator('[data-pos-checkout]').click();await page.locator('#pos-receipt.is-open').waitFor();await page.waitForFunction(()=>!document.querySelector('[data-pos-print]').disabled);
  assert.equal(attempts,2);assert.equal(new URL(await page.locator('[data-pos-open]').getAttribute('href')).pathname,'/butchery/pos/tickets/'+lostTicket.ticket_id,'Le retry retrouve le ticket existant.');
  const frame=page.frameLocator('[data-pos-receipt-frame]');assert.equal(await frame.locator('tbody tr').count(),2);assert.match(await frame.locator('.total').innerText(),/15,00 USD/);
  assert.equal(await frame.locator('script').count(),0,'Le client est échappé dans le reçu.');assert.equal(await frame.locator('body').evaluate(el=>el.ownerDocument.defaultView.posInjected),undefined);
  const receiptUrl=await page.locator('[data-pos-open]').getAttribute('href');
  for(const paper of ['80','a4']){await page.locator('[data-pos-paper]').selectOption(paper);await page.waitForFunction(()=>!document.querySelector('[data-pos-print]').disabled);const pdf=await context.request.get(receiptUrl.replace(/\?.*$/,'')+'?paper='+paper+'&export=pdf');assert.equal(pdf.status(),200);assert.match(pdf.headers()['content-type'],/application\/pdf/);assert.equal((await pdf.body()).subarray(0,4).toString(),'%PDF');}
  await page.locator('[data-pos-receipt-frame]').evaluate(el=>{el.contentWindow.print=()=>{el.dataset.printed='1';};});await page.locator('[data-pos-print]').click();assert.equal(await page.locator('[data-pos-receipt-frame]').getAttribute('data-printed'),'1');
  await page.locator('#pos-receipt').getByRole('button',{name:'Nouvelle vente',exact:true}).click();assert.equal(await page.locator('[data-pos-lines] .pos-basket-line').count(),0);
  // Receipt reprints are read-only and retain the originally paid totals.
  await page.getByRole('link',{name:'Reçus et ventes',exact:true}).click();await page.locator('table[aria-label="Tickets de caisse"]').waitFor();
  const ticketRow=page.locator('table[aria-label="Tickets de caisse"] tbody tr').filter({has:page.locator('a[href*="/tickets/'+lostTicket.ticket_id+'"]')});assert.equal(await ticketRow.count(),1);
  await ticketRow.getByRole('button',{name:'Annuler',exact:true}).click();const cancel=page.locator('#pos-cancel-'+lostTicket.ticket_id+' form');await cancel.locator('[name=reason]').fill('Erreur de saisie avant livraison - test');await cancel.locator('[name=not_delivered]').check();
  const cancelled=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().endsWith('/tickets/'+lostTicket.ticket_id+'/cancel'));await cancel.locator('button[type=submit]').click();await page.locator('[data-action-dialog-confirm]').click();assert((await(await cancelled).json()).ok);await page.locator('body.is-loading').waitFor({state:'hidden'});
  const receipt=await context.request.get(receiptUrl);assert.match(await receipt.text(),/TICKET ANNULÉ/);
  for(const width of [390,320]){
   await page.setViewportSize({width,height:900});await page.goto(base+'/butchery/counters');await page.locator('[data-pos-counter]').selectOption(String(fx.counter));await add(fx.item1,'0.250');
   assert(await page.locator('[data-pos-show-basket]').isVisible());await page.locator('[data-pos-show-basket]').click();
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Aucun débordement horizontal à '+width);
   await page.screenshot({path:'/private/tmp/dagril-pos-'+width+'.png',fullPage:true});await page.locator('[data-pos-clear]').click();
  }
  assert.deepEqual(errors,[]);console.log('OK : panier multiarticles, total/monnaie, réponse perdue et retry après rechargement sans double vente, reçus échappés/PDF 80mm et A4/impression, historique, annulation et mobile390/320.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
