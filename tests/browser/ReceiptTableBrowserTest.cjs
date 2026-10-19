// Read-only checks: display fixtures exercise the register without creating receipts.
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');

(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
  const errors=[],mutations=[];
  await context.route('**/*',route=>{
   if(!['GET','HEAD'].includes(route.request().method())){
    mutations.push(route.request().method()+' '+route.request().url());
    return route.abort();
   }
   return route.continue();
  });
  const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));
  const base=(process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8110').replace(/\/$/,'');
  assert.equal((await page.goto(base+'/butchery/receipts?kind=incoming')).status(),200);
  const table=page.locator('table[aria-label="Réceptions de carcasses"]');
  assert.equal(await page.locator('.flow-summary .ui-kpi').count(),2,'Seulement deux indicateurs liés aux réceptions.');
  assert.equal(await page.locator('#receipt-kpi-raw,#receipt-kpi-finished,#receipt-kpi-counter').count(),0,'Aucun indicateur de stock ou de vente sur cette page.');
  assert.equal(await table.count(),1);
  assert.equal(await page.locator('[data-flow-page-size]').inputValue(),'10');
  assert.equal(await page.locator('[data-flow-kind]').count(),0,'Un seul filtre d’étape évite les choix redondants.');
  assert.equal(await page.locator('[data-flow-state]').inputValue(),'incoming','Les anciens liens de catégorie conservent leur étape.');
  await page.locator('[data-flow-state]').selectOption('pending');await page.reload();
  assert.equal(await page.locator('[data-flow-state]').inputValue(),'pending','L’étape reste mémorisée au rechargement.');
  assert(!/Warning:|Fatal error:/.test(await page.locator('main').innerText()));

  // KPI details are independent of the active register filter and its URL.
  const registerState={
   url:page.url(),
   step:await page.locator('[data-flow-state]').inputValue(),
   search:await page.locator('[data-flow-search]').inputValue(),
   count:await table.locator('tbody [data-flow-item]:visible').count()
  };
  for(const key of ['incoming','pending']){
   const id='receipt-kpi-'+key;
   const trigger=page.locator('[data-workspace-modal-open="'+id+'"]');
   const modal=page.locator('#'+id);
   assert.equal(await trigger.count(),1,'Chaque indicateur possède un bouton de détails.');
   assert(await modal.isHidden(),'Les détails KPI restent fermés à l’arrivée.');
   await trigger.click();
   assert(await modal.isVisible(),'La modale '+key+' s’ouvre.');
   assert.equal(await modal.getAttribute('role'),'dialog');
   assert.equal(await modal.locator('form').count(),0,'Les détails KPI sont en lecture seule.');
   const detailTable=modal.locator('table');assert.equal(await detailTable.count(),1);
   if(await detailTable.getAttribute('data-dagril-table')==='ready'){
    const size=modal.locator('select.dagril-table-size');
    const detailSearch=modal.locator('.dagril-table-search input[type=search]');
    assert.equal(await size.inputValue(),'10','Les détails KPI affichent dix lignes par défaut.');
    const total=await detailTable.locator('tbody tr').count();
    assert.equal(await detailTable.locator('tbody tr:visible').count(),Math.min(total,10));
    await detailSearch.fill('kpi-reception-absente-7823');
    assert(await detailTable.locator('.dagril-table-empty').isVisible(),'Une recherche KPI sans résultat est expliquée.');
    await detailSearch.fill('');
    assert.equal(await detailTable.locator('tbody tr:visible').count(),Math.min(total,10));
   }else{
    assert(await detailTable.locator('tbody td[colspan]').isVisible(),'Un indicateur vide possède un message explicite.');
   }
   await page.keyboard.press('Escape');assert(await modal.isHidden());
   assert(await trigger.evaluate(button=>button===document.activeElement),'Échap rend le focus à l’indicateur.');
   assert.equal(page.url(),registerState.url,'Les détails KPI ne changent pas l’URL.');
   assert.equal(await page.locator('[data-flow-state]').inputValue(),registerState.step,'Le filtre principal reste inchangé.');
   assert.equal(await page.locator('[data-flow-search]').inputValue(),registerState.search,'La recherche principale reste inchangée.');
   assert.equal(await table.locator('tbody [data-flow-item]:visible').count(),registerState.count,'Les détails KPI ne modifient pas les lignes du registre.');
  }

  // When the database fixture has a pending control, retain its existing dialog.
  await page.locator('[data-flow-reset]').click();
  const control=page.locator('[data-receipt-id] [data-workspace-modal-open^="flow-receive-"]').first();
  if(await control.count()){
   await control.evaluate(button=>{const details=button.closest('details');if(details)details.open=true;});
   const id=await control.getAttribute('data-workspace-modal-open');
   // A pending receipt may be on a later page of a populated register.
   await page.locator('[data-flow-page-size]').selectOption('50');
   if(await control.isVisible()){
    await control.click();
    const modal=page.locator('#'+id);
    assert(await modal.isVisible(),'Le contrôle sanitaire existant reste accessible.');
    assert.equal(await modal.locator('[name=visual_conform]').count(),1);
    assert.equal(await modal.locator('[name=documents_conform]').count(),1);
    await page.keyboard.press('Escape');
    assert(await modal.isHidden());
    assert(await control.evaluate(button=>button===document.activeElement),'Le focus revient à l’action du contrôle.');
   }
  }

  await page.evaluate(()=>{
   const old=document.querySelector('[data-flow-directory]'),panel=old.cloneNode(true);
   delete panel.dataset.ready;
   sessionStorage.removeItem('porcine-flow-filters:'+panel.dataset.siteScope+':'+panel.dataset.pageScope);
   history.replaceState(history.state,'',location.pathname);
   panel.querySelector('[data-flow-page-size]').value='10';
   const body=panel.querySelector('[data-flow-table-body]');body.replaceChildren();
   for(let i=1;i<=23;i++){
    const row=document.createElement('tr'),kind=i<=11?'incoming':'pending';
    Object.assign(row.dataset,{flowItem:'',flowKey:'receipt-display-test-'+i,kind,state:kind,reference:'REC-'+i,date:'2026-10-'+String(i).padStart(2,'0'),weight:String(i*10),expiry:'2026-11-01',search:'réception-test-'+i+' Ferme de test lot-'+i});
    row.innerHTML='<td data-label="Référence / lot"><strong>REC-'+i+'</strong><small>Ferme de test · lot-'+i+'</small></td><td data-label="Catégorie">'+(kind==='incoming'?'Envoi attendu':'Contrôle sanitaire')+'</td><td data-label="Poids (kg)" class="num">'+i*10+'</td><td data-label="Date">17/10/2026</td><td data-label="DLC">01/11/2026</td><td data-label="État">'+(kind==='incoming'?'À réceptionner':'Contrôle à effectuer')+'</td><td data-label="Actions"><details class="slaughter-row-actions"><summary>Détails et actions</summary><p>Traçabilité de la réception</p></details></td>';
    body.append(row);
   }
   old.replaceWith(panel);window.initPorcineFlow(panel);
  });
  const rows=table.locator('tbody [data-flow-item]:visible');
  assert.equal(await rows.count(),10,'Dix lignes sont visibles par défaut.');
  assert.equal(await rows.first().getAttribute('data-reference'),'REC-23','Les réceptions les plus récentes apparaissent en premier.');
  await page.locator('[data-flow-page="1"]').click();assert.equal(await rows.count(),10);
  await page.locator('[data-flow-page="1"]').click();assert.equal(await rows.count(),3);
  assert(await page.locator('[data-flow-page="1"]').isDisabled());
  assert(!(await page.locator('[data-flow-filter-update]').isVisible()),'La pagination seule ne signale pas de résultats masqués par filtre.');
  assert(!/abattage/i.test(await page.locator('[data-flow-page-info]').innerText()),'La pagination décrit les réceptions.');

  await page.locator('[data-flow-state]').selectOption('incoming');
  assert.equal(await rows.count(),10);
  assert.equal(await rows.first().getAttribute('data-reference'),'REC-11');
  await page.locator('[data-flow-page="1"]').click();assert.equal(await rows.count(),1);
  await page.locator('[data-flow-search]').fill('reception-test-11');
  assert.equal(await rows.count(),1,'La recherche ignore les accents.');
  await page.locator('[data-flow-reset]').click();
  await page.locator('[data-flow-page-size]').selectOption('25');assert.equal(await rows.count(),23);
  await page.locator('[data-flow-page-size]').selectOption('50');assert.equal(await rows.count(),23);
  await page.locator('[data-flow-sort="weight"]').click();assert.equal(await rows.first().getAttribute('data-weight'),'10');
  await page.locator('[data-flow-sort="weight"]').click();assert.equal(await rows.first().getAttribute('data-weight'),'230');

  await page.locator('[data-flow-state]').selectOption('pending');assert.equal(await rows.count(),12);
  assert(await rows.evaluateAll(items=>items.every(item=>item.dataset.kind==='pending')));
  await page.locator('[data-flow-search]').fill('reception-test-23');assert.equal(await rows.count(),1);
  await page.locator('[data-flow-search]').fill('réception-absente-9898');assert.equal(await rows.count(),0);
  assert(await page.locator('[data-flow-filter-empty]').isVisible());
  await page.locator('[data-flow-reset]').click();assert.equal(await rows.count(),23);

  const details=rows.first().locator('details');await details.locator('summary').click();
  assert(await details.locator('p').isVisible(),'Les détails de la ligne sont dépliables.');
  for(const width of [390,320]){
   await page.setViewportSize({width,height:900});
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'La page tient à '+width+' px.');
   assert(!(await rows.evaluateAll(items=>items.some(item=>item.scrollWidth>item.clientWidth+1))),'Les lignes tiennent à '+width+' px.');
  }
  assert.deepEqual(mutations,[],'Aucune requête de modification n’est tentée.');
  assert.deepEqual(errors,[]);
  console.log('OK : deux modales KPI indépendantes ; réceptions en tableau, 10/25/50 lignes, pagination, filtre d’étape persistant et ancien lien, recherche accentuée, tri numérique, contrôle sanitaire existant et mobile 390/320 ; aucune écriture.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
