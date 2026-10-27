// Age-group referential: live preview, AJAX create/deactivate/delete, guarded actions and mobile layout.
// Run against the disposable server described in travaux-agricoles-validation.md (base dagril_porcin_*_test with migration 041).
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict');
const base=process.env.DAGRIL_TEST_URL||'http://127.0.0.1:8107';
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/livestock/pigs/groups');
  assert.equal(await page.locator('.pig-timeline:not(.pig-timeline--mini) .pig-timeline__step').count(),3,'Frise des trois groupes historiques');
  assert.match(await page.locator('.pig-ref-checks-wrap summary').textContent(),/cohérent/,'Contrôles repliés quand tout est cohérent');
  assert(await page.locator('[data-pig-ref-group="C"] [data-pig-group-status] button').isDisabled(),'Le seul groupe de reproduction ne peut pas être désactivé');
  assert.match(await page.locator('[data-pig-ref-group="C"] [data-pig-status-hint]').textContent(),/reproduction/);

  await page.locator('[data-workspace-modal-open="pig-group-new"]').first().click();
  const form=page.locator('#pig-group-new form');
  assert.equal(await form.locator('[name=code]').inputValue(),'D','Code D proposé automatiquement');
  assert.equal(await form.locator('[data-pig-code-badge]').textContent(),'Automatique');
  await form.locator('[name=code]').fill('rf');
  assert.equal(await form.locator('[name=code]').inputValue(),'RF','Code modifiable, mis en majuscules');
  assert.equal(await form.locator('[data-pig-code-badge]').textContent(),'Personnalisé');
  await form.locator('[data-pig-code-reset]').click();
  assert.equal(await form.locator('[name=code]').inputValue(),'D','Retour au code proposé');
  assert(await form.locator('[data-pig-code-reset]').isHidden());
  await form.locator('[name=code]').fill('');
  assert.match(await form.locator('[data-pig-code-help]').textContent(),/D sera attribué automatiquement/,'Champ vidé : attribution automatique annoncée');
  await form.locator('[name=name]').fill('Porcs de réforme');
  await form.locator('[name=min_age_months]').fill('3');
  assert.match((await form.locator('[data-pig-preview-issues] li').allTextContents()).join(' '),/commencent tous à 3 mois/,'Chevauchement signalé avant l’envoi');
  await form.locator('[name=min_age_months]').fill('6');
  assert.equal(await form.locator('[data-pig-preview-issues] li').count(),0);
  assert.match(await form.locator('[data-pig-group-range]').textContent(),/dès 6 mois/);
  assert.equal(await form.locator('[data-pig-preview-timeline] .pig-timeline__step').count(),4,'Aperçu de la frise avec le nouveau groupe');
  await form.locator('.pig-ref-toggle:has([name=allows_slaughter])').click();
  assert(await form.locator('[name=allows_slaughter]').isChecked(),'La carte entière active la capacité');
  await form.locator('.pig-ref-illustration:has([value=adult-pig])').click();
  await form.locator('footer button[type=submit]').click();
  assert.match(await page.locator('[data-action-dialog-description]').textContent(),/Ajouter le groupe D/,'Confirmation avec l’impact');
  await page.locator('[data-action-dialog-confirm]').click();
  await page.locator('[data-pig-ref-group="D"]').waitFor();
  assert.equal(await page.locator('.pig-timeline:not(.pig-timeline--mini) .pig-timeline__step').count(),4,'Frise actualisée après création');
  assert.match(await page.locator('[data-pig-ref-group="C"] .pig-ref-card__age').textContent(),/De 3 à moins de 6 mois/,'Tranche de C recalculée');

  await page.locator('[data-pig-ref-group="D"] [data-pig-group-status] button').click();
  await page.locator('[data-action-dialog-confirm]').click();
  await page.locator('.pig-ref-inactive [data-pig-ref-group="D"]').waitFor({state:'attached'});
  await page.locator('.pig-ref-inactive > summary').click();
  await page.locator('.pig-ref-inactive [data-pig-ref-group="D"] [data-pig-group-delete] button').click();
  await page.locator('[data-action-dialog-confirm]').click();
  await page.locator('[data-pig-ref-group="D"]').waitFor({state:'detached'});
  assert.equal(await page.locator('.pig-ref-history li').first().locator('.ui-status').textContent(),'Suppression','Historique des modifications à jour');

  await page.goto(base+'/livestock/pigs');
  assert.equal(await page.locator('[data-group-option]').count(),3,'Filtres des effectifs issus du référentiel');
  assert(await page.locator('a[href$="livestock/pigs/groups"]').first().isVisible(),'Accès aux règles depuis les effectifs');
  assert.deepEqual(errors,[]);

  for(const width of [390,320]){
   const mobile=await browser.newPage({viewport:{width,height:844},reducedMotion:'reduce'});
   await mobile.goto(base+'/livestock/pigs/groups');
   assert(await mobile.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Pas de débordement mobile à '+width+'px');
   await mobile.locator('[data-workspace-modal-open="pig-group-new"]').first().click();
   assert(await mobile.locator('#pig-group-new footer button[type=submit]').isVisible(),'Bouton d’enregistrement visible à '+width+'px');
   await mobile.close();
  }
  console.log('Référentiel des groupes porcins : parcours navigateur validé.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
