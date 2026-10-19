// Local browser requests are read-only. Fixtures require a disposable porcine DB.
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path');
assert(/^dagril_porcin_[a-f0-9]{12}_test$/.test(process.env.DAGRIL_DB_DATABASE||''),'Une base porcine jetable est requise.');
assert(process.env.DAGRIL_FLOW_FIXTURE,'La fixture du parcours porcin est requise.');
assert(process.env.DAGRIL_TEST_URL,'Un serveur local authentifié est requis.');
const base=process.env.DAGRIL_TEST_URL.replace(/\/$/,'');
assert(['127.0.0.1','localhost','[::1]'].includes(new URL(base).hostname),'Les tests restent sur localhost.');
const fixture=JSON.parse(execFileSync(process.env.DAGRIL_PHP_EXECUTABLE||'php',[path.resolve(__dirname,'../integration/ColdRoomFixture.php')],{env:process.env,encoding:'utf8'}));
const normalize=value=>String(value).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
const frenchNumber=value=>Number(String(value).replace(/\s/g,'').replace(',','.').replace(/[^\d.\-]/g,''));
const kg=value=>Number(value).toLocaleString('fr-FR',{minimumFractionDigits:3,maximumFractionDigits:3});
async function assertLayout(page,modal){
 const result=await page.evaluate(id=>{const el=id&&document.getElementById(id),bounds=el?.getBoundingClientRect();return{viewport:innerWidth,document:document.documentElement.scrollWidth,modal:el&&{left:bounds.left,right:bounds.right,scroll:el.scrollWidth,width:el.clientWidth}};},modal||null);
 assert(result.document<=result.viewport+1,'Pas de débordement horizontal de la page : '+JSON.stringify(result));
 if(result.modal){assert(result.modal.left>=-1&&result.modal.right<=result.viewport+1,'La modale tient dans l’écran.');assert(result.modal.scroll<=result.modal.width+1,'Le contenu de la modale ne déborde pas.');}
}
async function assertClosedFocus(page,modal,trigger){
 // Delayed focus callbacks must not move focus back into an already closed modal.
 await page.waitForTimeout(100);
 assert(await modal.isHidden(),'La modale est fermée.');
 assert(await trigger.evaluate(el=>el===document.activeElement),'La fermeture rend durablement le focus au bouton d’origine.');
 assert(await page.evaluate(()=>!document.activeElement.closest('.workspace-entity-modal[aria-hidden="true"]')),'Le focus ne reste pas dans une modale fermée.');
}
(async()=>{
 const chrome=process.env.DAGRIL_CHROME_EXECUTABLE||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
 const browser=await chromium.launch({...(fs.existsSync(chrome)?{executablePath:chrome}:{}),headless:true});
 const errors=[],mutations=[];
 try{
  const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce',serviceWorkers:'block'});
  await context.route('**/*',route=>{if(!['GET','HEAD'].includes(route.request().method())){mutations.push(route.request().method()+' '+route.request().url());return route.abort();}return route.continue();});
  const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));
  assert.equal((await page.goto(base+'/butchery/cold-room')).status(),200);
  await page.waitForFunction(()=>document.querySelector('[data-flow-directory]')?.dataset.ready==='1');
  assert(!/Warning:|Fatal error:/.test(await page.locator('main').innerText()),'Aucune erreur PHP affichée.');
  const articleModal=page.locator('#cold-room-article-detail'),table=articleModal.locator('table.cold-room-table'),rows=table.locator('tbody [data-flow-item]:visible');
  const cards=page.locator('[data-cold-card]'),visibleCards=page.locator('[data-cold-card]:visible'),mainSearch=page.locator('[data-cold-search]'),mainKind=page.locator('[data-cold-kind]');
  async function closeArticle(trigger){await page.keyboard.press('Escape');await assertClosedFocus(page,articleModal,trigger);}
  async function openArticle(key){
   const trigger=page.locator('[data-cold-card][data-article-key="'+key+'"] [data-cold-article="'+key+'"]');
   await trigger.click();assert(await articleModal.isVisible(),'Le détail de l’article s’ouvre à la demande.');return trigger;
  }
  assert.equal(await table.count(),1,'Un seul registre de lots, placé dans la modale article.');
  assert(await articleModal.isHidden(),'Le registre ne s’ouvre pas automatiquement.');
  assert.equal(await table.getAttribute('data-datatable'),'false','La pagination du registre reste unique.');
  assert.equal(await page.locator('[data-flow-page-size]').inputValue(),'10');
  assert.equal(await page.locator('.flow-summary .ui-kpi').count(),4,'Quatre indicateurs concernent la chambre froide.');
  for(const key of ['raw','finished','available','expired']){
   const trigger=page.locator('[data-workspace-modal-open="cold-room-kpi-'+key+'"]');
   assert.equal(frenchNumber(await trigger.locator('.ui-kpi__value').innerText()),fixture.kpis[key],'Calcul indépendant du KPI '+key);
   assert(await page.locator('#cold-room-kpi-'+key).isHidden(),'Les KPI ne s’ouvrent pas automatiquement.');
  }
  for(const key of fixture.excluded)assert.equal(await table.locator('[data-flow-key="'+key+'"]').count(),0,'Les lots sans poids au froid sont exclus.');
  for(const record of fixture.records){
   const row=table.locator('[data-flow-key="'+record.key+'"]');assert.equal(await row.count(),1);
   for(const key of ['physical','reserved','available'])assert.equal(Number(await row.getAttribute('data-'+key)),record[key],record.key+' : '+key);
   assert.equal(await row.getAttribute('data-kind'),record.kind);assert.equal(await row.getAttribute('data-date'),record.date);
   assert.equal(await row.getAttribute('data-state'),record.state);
  }
  const articleGroups=await table.locator('tbody [data-flow-item]').evaluateAll(elements=>{
   const grouped={};for(const row of elements){const key=row.dataset.article;const group=grouped[key]||(grouped[key]={kind:row.dataset.kind,physical:0,reserved:0,available:0,keys:[]});for(const value of ['physical','reserved','available'])group[value]+=Number(row.dataset[value]);group.keys.push(row.dataset.flowKey);}return grouped;
  });
  assert.equal(await cards.count(),Object.keys(articleGroups).length,'Chaque article possède une seule carte, quel que soit son nombre de lots.');
  for(const[key,group]of Object.entries(articleGroups)){
   const card=page.locator('[data-cold-card][data-article-key="'+key+'"]');assert.equal(await card.count(),1);assert.equal(await card.getAttribute('data-kind'),group.kind);
   for(const value of ['physical','reserved','available']){
    const stat=card.locator('[data-cold-'+value+']');assert.equal(await stat.count(),1);
    const attribute=await stat.getAttribute('data-cold-'+value),actual=attribute&&Number.isFinite(Number(attribute))?Number(attribute):frenchNumber(await stat.innerText());
    assert.equal(Math.round(actual*1000),Math.round(group[value]*1000),'Total absolu de la carte '+key+' : '+value);
   }
   assert.equal(await card.locator('img').count(),1,'Illustration réutilisée pour chaque article.');
  }
  const fixtureArticles={};for(const record of fixture.records){const key=await table.locator('[data-flow-key="'+record.key+'"]').getAttribute('data-article');(fixtureArticles[record.kind]||=new Set()).add(key);}
  assert.equal(fixtureArticles.raw.size,1,'Les treize lots de matière partagent une carte.');assert.equal(fixtureArticles.finished.size,1,'Les douze lots transformés partagent une carte.');
  const rawArticle=[...fixtureArticles.raw][0],finishedArticle=[...fixtureArticles.finished][0];assert.notEqual(rawArticle,finishedArticle,'Les deux familles conservent leurs cartes distinctes.');
  const rawName=await page.locator('[data-cold-card][data-article-key="'+rawArticle+'"]').getAttribute('data-article-name');assert(rawName,'Chaque carte expose le nom de l’article.');
  await mainSearch.fill(normalize(rawName));assert(await page.locator('[data-cold-card][data-article-key="'+rawArticle+'"]:visible').count(),'La recherche reconnaît le nom de l’article.');assert(await articleModal.isHidden());
  await page.locator('[data-cold-reset]').click();await mainSearch.fill(normalize(fixture.prefix));assert.equal(await visibleCards.count(),2,'La recherche par lots retrouve leurs deux articles.');
  const rawTrigger=await openArticle(rawArticle);
  await articleModal.locator('[data-flow-search]').fill(normalize(fixture.prefix));
  assert.equal(await rows.count(),10);await articleModal.locator('[data-flow-page="1"]').click();assert.equal(await rows.count(),3);assert(await articleModal.locator('[data-flow-page="1"]').isDisabled());
  assert(/lot/i.test(await articleModal.locator('[data-flow-page-info]').innerText()),'La pagination parle des lots de stock.');
  assert(!/abattage|transformation|réception/i.test(await articleModal.locator('[data-flow-page-info]').innerText()));
  await articleModal.locator('[data-flow-page-size]').selectOption('25');assert.equal(await rows.count(),13);
  await articleModal.locator('[data-flow-page-size]').selectOption('50');assert.equal(await rows.count(),13);
  assert(await rows.evaluateAll(elements=>elements.every(row=>row.dataset.kind==='raw')),'Le registre se limite à l’article choisi.');
  const partial=fixture.records.find(row=>row.kind==='finished'&&row.number.endsWith('-01'));
  assert.equal(partial.total,50);assert.equal(partial.counter,20);assert.equal(partial.physical,30);assert.equal(partial.available,25);
  const today=fixture.records.find(row=>row.kind==='raw'&&row.date===fixture.today);assert(today.available>0,'La DLC du jour reste utilisable.');
  for(const key of ['physical','reserved','available']){
   await articleModal.locator('[data-flow-sort="'+key+'"]').click();
   let values=await rows.evaluateAll((elements,key)=>elements.map(element=>Number(element.dataset[key])),key);
   assert.deepEqual(values,[...values].sort((a,b)=>a-b),'Tri numérique croissant '+key);
   await articleModal.locator('[data-flow-sort="'+key+'"]').click();
   values=await rows.evaluateAll((elements,key)=>elements.map(element=>Number(element.dataset[key])),key);
   assert.deepEqual(values,[...values].sort((a,b)=>b-a),'Tri numérique décroissant '+key);
  }
  await articleModal.locator('[data-flow-search]').fill('lot-introuvable-785322');assert.equal(await rows.count(),0);assert(await articleModal.locator('[data-flow-filter-empty]').isVisible());
  await articleModal.locator('[data-flow-reset]').click();assert.equal(await articleModal.locator('[data-flow-search]').inputValue(),'');
  await closeArticle(rawTrigger);
  await mainKind.selectOption('finished');assert.equal(await visibleCards.count(),1,'Le filtre de catégorie s’applique aux cartes.');
  let finishedTrigger=await openArticle(finishedArticle);await articleModal.locator('[data-flow-search]').fill(normalize(fixture.prefix));
  await articleModal.locator('[data-flow-page-size]').selectOption('25');assert.equal(await rows.count(),12);
  await articleModal.locator('[data-flow-state]').selectOption('reserved');
  assert.equal(await rows.count(),fixture.records.filter(row=>row.kind==='finished'&&row.state==='reserved').length,'Type et état se combinent.');
  await closeArticle(finishedTrigger);
  const snapshot={url:page.url(),search:await mainSearch.inputValue(),kind:await mainKind.inputValue(),keys:await visibleCards.evaluateAll(elements=>elements.map(el=>el.dataset.articleKey))};
  for(const key of ['raw','finished','available','expired']){
   const id='cold-room-kpi-'+key,modal=page.locator('#'+id),trigger=page.locator('[data-workspace-modal-open="'+id+'"]');
   await trigger.click();assert(await modal.isVisible());assert.equal(await modal.getAttribute('role'),'dialog');assert.equal(await modal.getAttribute('aria-modal'),'true');
   const labelledBy=await modal.getAttribute('aria-labelledby');assert((await page.locator('[id="'+labelledBy+'"]').innerText()).trim(),'La modale a un titre accessible.');
   assert.equal(await modal.locator('form,[data-flow-item]').count(),0,'Un KPI reste en lecture seule et indépendant du registre.');
   const grid=modal.locator('.dagril-datatable');assert.equal(await grid.count(),1);
   const search=grid.locator('.dagril-table-search input[type=search]'),size=grid.locator('select.dagril-table-size');assert.equal(await size.inputValue(),'10');
   await search.fill(normalize(fixture.prefix));
   const expected=fixture.records.filter(row=>key==='available'?row.available>0:key==='expired'?row.date<fixture.today:row.kind===key).length;
   assert.equal(await grid.locator('tbody tr:visible').count(),Math.min(10,expected),'Les détails correspondent au KPI '+key);
   if(expected>10){await grid.getByRole('button',{name:'Page suivante',exact:true}).click();assert.equal(await grid.locator('tbody tr:visible').count(),Math.min(10,expected-10));}
   await size.selectOption('25');assert.equal(await grid.locator('tbody tr:visible').count(),expected);
   await search.fill('lot-introuvable-785322');assert(await grid.locator('.dagril-table-empty').isVisible());
   await assertLayout(page,id);await page.keyboard.press('Escape');assert(await modal.isHidden());assert(await trigger.evaluate(el=>el===document.activeElement));
   assert.equal(page.url(),snapshot.url);assert.equal(await mainSearch.inputValue(),snapshot.search);assert.equal(await mainKind.inputValue(),snapshot.kind);assert.deepEqual(await visibleCards.evaluateAll(elements=>elements.map(el=>el.dataset.articleKey)),snapshot.keys,'La lecture d’un KPI ne modifie pas les articles affichés.');assert(await articleModal.isHidden());
  }
  await page.locator('[data-cold-reset]').click();
  for(const record of [fixture.records[0],partial,...fixture.records.filter(row=>['expired','quarantine','cancelled'].includes(row.state)).slice(0,4)]){
   await mainSearch.fill(record.number);assert.equal(await visibleCards.count(),1,'Une recherche par numéro retrouve la carte de l’article.');
   const key=await table.locator('[data-flow-key="'+record.key+'"]').getAttribute('data-article'),articleTrigger=await openArticle(key);
   assert.equal(await articleModal.locator('[data-flow-search]').inputValue(),'','La recherche principale ne masque pas les autres lots de l’article.');
   await articleModal.locator('[data-flow-search]').fill(record.number);
   const row=rows.first();assert.equal(await rows.count(),1);
   const toggle=row.locator('[popovertarget]'),menu=page.locator('#cold-room-actions-'+record.key),detail=page.locator('#cold-room-detail-'+record.key);
   await toggle.click();assert(await menu.isVisible());
   assert.equal(await menu.locator('[data-cold-supply]').count(),record.kind==='finished'&&record.available>0?1:0,'Approvisionnement uniquement pour un produit utilisable disponible.');
   await menu.getByRole('button',{name:'Détails',exact:true}).click();assert(await detail.isVisible());
   assert((await detail.innerText()).includes(record.number));assert(normalize(await detail.innerText()).includes(normalize(kg(record.physical))),'Le détail indique le poids réellement au froid.');
   await assertLayout(page,'cold-room-detail-'+record.key);await page.keyboard.press('Escape');await assertClosedFocus(page,detail,toggle);
   await toggle.click();await menu.getByRole('button',{name:'Détails',exact:true}).click();
   await detail.getByRole('button',{name:'Fermer',exact:true}).click();await assertClosedFocus(page,detail,toggle);
   assert(await articleModal.isVisible(),'Fermer un détail retrouve le registre de l’article.');await closeArticle(articleTrigger);
  }
  for(const[index,record]of[partial,fixture.records.find(row=>row.kind==='finished'&&row.number.endsWith('-02'))].entries()){
   await mainSearch.fill(record.number);const articleTrigger=await openArticle(finishedArticle);await articleModal.locator('[data-flow-search]').fill(record.number);const toggle=rows.first().locator('[popovertarget]');await toggle.click();
   await page.locator('#cold-room-actions-'+record.key+' [data-cold-supply]').click();
   const supply=page.locator('#flow-supply');assert(await supply.isVisible());
   assert.equal(await supply.locator('[name=finished_lot_id]').inputValue(),String(record.id),'Le lot choisi est présélectionné.');
   assert.equal(await supply.locator('[name=quantity_kg]').inputValue(),'','La quantité précédente n’est jamais réutilisée.');
   await supply.locator('[name=quantity_kg]').fill('1');
   if(index===0)await page.keyboard.press('Escape');else await supply.getByRole('button',{name:'Fermer',exact:true}).click();
   await assertClosedFocus(page,supply,toggle);
   assert(await articleModal.isVisible(),'Fermer l’approvisionnement retrouve le registre de l’article.');await closeArticle(articleTrigger);
   const globalSupply=page.locator('.cold-room-toolbar-actions [data-workspace-modal-open="flow-supply"]');
   await globalSupply.click();assert(await supply.isVisible());
   assert.equal(await supply.locator('[name=finished_lot_id]').inputValue(),'','Le bouton général ne réutilise pas le lot présélectionné dans une ligne.');
   assert.equal(await supply.locator('[name=quantity_kg]').inputValue(),'','Le bouton général ne réutilise pas la quantité de la ligne.');
   if(index===0)await supply.getByRole('button',{name:'Fermer',exact:true}).click();else await page.keyboard.press('Escape');
   await assertClosedFocus(page,supply,globalSupply);
  }
  await mainSearch.fill('lot-introuvable-785322');assert.equal(await visibleCards.count(),0);
  assert(await page.locator('[data-cold-empty]').isVisible(),'Une recherche sans article affiche son explication.');
  await page.locator('[data-cold-reset]').click();assert.equal(await mainSearch.inputValue(),'');
  await mainSearch.fill(normalize(fixture.prefix));await mainKind.selectOption('finished');
  await page.reload();await page.waitForFunction(()=>document.querySelector('[data-flow-directory]')?.dataset.ready==='1');
  assert.equal(await mainSearch.inputValue(),normalize(fixture.prefix));assert.equal(await mainKind.inputValue(),'finished');assert.equal(await page.locator('[data-flow-page-size]').inputValue(),'10');assert(await articleModal.isHidden(),'Les filtres persistés ne déclenchent aucune ouverture de modale.');assert.equal(await visibleCards.count(),1);
  for(const width of [390,320]){
   await page.setViewportSize({width,height:900});await assertLayout(page);
   finishedTrigger=await openArticle(finishedArticle);await articleModal.locator('[data-flow-search]').fill(partial.number);await assertLayout(page,'cold-room-article-detail');
   const row=rows.first(),key=await row.getAttribute('data-flow-key'),toggle=row.locator('[popovertarget]');
   assert(await toggle.evaluate(el=>el.getBoundingClientRect().height>=44),'La cible Actions est utilisable au toucher.');
   await toggle.click();const menu=page.locator('#cold-room-actions-'+key),bounds=await menu.boundingBox();assert(bounds.x>=0&&bounds.x+bounds.width<=width+1);
   await page.keyboard.press('Escape');assert(await menu.isHidden(),'Échap ferme le menu Actions.');assert(await articleModal.isVisible(),'Échap dans le menu conserve les lots de l’article.');assert(await toggle.evaluate(el=>el===document.activeElement));await toggle.click();
   await menu.getByRole('button',{name:'Détails',exact:true}).click();await assertLayout(page,'cold-room-detail-'+key);await page.keyboard.press('Escape');await assertClosedFocus(page,page.locator('#cold-room-detail-'+key),toggle);
   assert(await table.evaluate(el=>el.getBoundingClientRect().right<=innerWidth+1));assert(!(await rows.evaluateAll(elements=>elements.some(el=>el.scrollWidth>el.clientWidth+1))));
   await closeArticle(finishedTrigger);
   await page.locator('[data-workspace-modal-open=cold-room-kpi-available]').click();await assertLayout(page,'cold-room-kpi-available');await page.keyboard.press('Escape');
  }
  assert.deepEqual(mutations,[],'Aucune requête de modification navigateur.');assert.deepEqual(errors,[],'Aucune erreur JavaScript.');
  console.log('OK : cartes par article/catégorie et lots regroupés ; totaux physique/réservé/disponible ; comptoir exclu du froid ; périmés et quarantaine bloqués ; modale avec tableau 10/25/50 et tri numérique ; filtres articles persistés et filtres de lots indépendants ; KPI indépendants ; détails et approvisionnement avec retour à l’article ; mobile 390/320 ; aucune écriture navigateur.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
