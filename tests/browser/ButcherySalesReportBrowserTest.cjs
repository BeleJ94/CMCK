const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.DAGRIL_PLAYWRIGHT_MODULE||'playwright');
const fx=JSON.parse(fs.readFileSync(process.env.DAGRIL_REPORT_FIXTURE,'utf8')),base=process.env.DAGRIL_TEST_URL;
assert(/^dagril_porcin_[a-f0-9]{12}_test$/.test(fx.database));assert.equal(fx.database,process.env.DAGRIL_DB_DATABASE);assert(['127.0.0.1','localhost'].includes(new URL(base).hostname));
(async()=>{
 const browser=await chromium.launch({executablePath:'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless:true});
 try{
  const context=await browser.newContext({viewport:{width:1440,height:1050},reducedMotion:'reduce'}),page=await context.newPage(),errors=[],mutations=[];page.on('pageerror',e=>errors.push(e.message));
  await context.route('**/*',route=>{if(!['GET','HEAD'].includes(route.request().method())){mutations.push(route.request().method());return route.abort();}return route.continue();});
  const url=base+'/butchery/pos/report?'+new URLSearchParams(fx.query);assert.equal((await page.goto(url)).status(),200);
  await page.locator('.pos-sales-report .dagril-datatable').first().waitFor();assert(!/Warning:|Fatal error:/.test(await page.locator('main').innerText()));
  assert.equal(await page.locator('.pos-report-summary > section').count(),2);assert.match(await page.locator('.pos-report-summary').innerText(),/1\s?051,05 USD/);assert.match(await page.locator('.pos-report-summary').innerText(),/10\s?000,00 CDF/);
  const table=page.locator('table[aria-label="Tickets du rapport de ventes"]'),grid=page.locator('.dagril-datatable').filter({has:table}),rows=table.locator('tbody tr:visible');
  assert.equal(await table.locator('tbody tr').count(),108);assert.equal(await rows.count(),10);assert(await grid.locator('.dagril-table-search').isHidden(),'Tous les filtres utilisés par les exports sont dans le formulaire principal.');
  const form=page.getByRole('form',{name:'Filtres du rapport de ventes'});
  await form.locator('[name=currency]').selectOption('USD');await form.locator('[name=status]').selectOption('paid');
  await form.getByRole('button',{name:'Appliquer les filtres',exact:true}).click();await page.locator('body.is-loading').waitFor({state:'hidden'});
  assert.equal(await table.locator('tbody tr').count(),105);assert.equal(await page.locator('.pos-report-summary > section').count(),1);
  await grid.getByRole('button',{name:'Page suivante',exact:true}).click();assert.equal(await rows.count(),10);
  const xlsxUrl=await page.getByRole('link',{name:'Exporter Excel',exact:true}).getAttribute('href'),pdfUrl=await page.getByRole('link',{name:'Exporter PDF',exact:true}).getAttribute('href');
  for(const target of [xlsxUrl,pdfUrl]){const parsed=new URL(target);assert.equal(parsed.searchParams.get('currency'),'USD');assert.equal(parsed.searchParams.get('status'),'paid');assert.equal(parsed.searchParams.get('q'),fx.prefix);assert.equal(parsed.searchParams.has('page'),false);}
  const xlsx=await context.request.get(xlsxUrl);assert.equal(xlsx.status(),200);assert.match(xlsx.headers()['content-type'],/spreadsheetml/);const bytes=await xlsx.body();assert.equal(bytes.subarray(0,2).toString(),'PK');
  const file=path.join(fs.mkdtempSync(path.join(os.tmpdir(),'dagril-report-http-')),'report.xlsx');
  try{fs.writeFileSync(file,bytes);const meta=JSON.parse(execFileSync('php',['-r','$z=new ZipArchive();$z->open($argv[1]);$d=new DOMDocument();$d->loadXML($z->getFromName("xl/worksheets/sheet2.xml"));$x=new DOMXPath($d);$x->registerNamespace("m","http://schemas.openxmlformats.org/spreadsheetml/2006/main");echo json_encode(["dataRows"=>$x->query("//m:sheetData/m:row")->length-6,"formulas"=>$x->query("//m:f")->length]);',file],{encoding:'utf8'}));assert.equal(meta.dataRows,105,'L’export HTTP inclut les 105 tickets, pas uniquement la page visible.');assert.equal(meta.formulas,0);}finally{fs.rmSync(path.dirname(file),{recursive:true,force:true});}
  const pdf=await context.request.get(pdfUrl);assert.equal(pdf.status(),200);assert.match(pdf.headers()['content-type'],/application\/pdf/);assert.equal((await pdf.body()).subarray(0,4).toString(),'%PDF');
  await page.screenshot({path:'/private/tmp/dagril-sales-report-desktop.png',fullPage:true});
  for(const width of [390,320]){await page.setViewportSize({width,height:900});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Rapport sans débordement horizontal à '+width);await page.screenshot({path:'/private/tmp/dagril-sales-report-'+width+'.png',fullPage:true});}
  for(const suffix of ['&currency=EUR','&from=2026-02-30','&q%5B%5D=invalide','&export=incorrect']){const invalid=await context.request.get(url+suffix);assert.equal(invalid.status(),400);assert(!/Fatal error:|Stack trace:/.test(await invalid.text()));}
  await page.goto(base+'/butchery/pos/report?'+new URLSearchParams({...fx.query,q:'rapport-vide-78463'}));assert(await page.getByText('Aucune vente pour ces filtres.',{exact:true}).isVisible());
  const emptyPdf=await context.request.get(await page.getByRole('link',{name:'Exporter PDF',exact:true}).getAttribute('href'));assert.equal(emptyPdf.status(),200);assert.equal((await emptyPdf.body()).subarray(0,4).toString(),'%PDF');
  await page.goto(base+'/butchery/counters');assert.equal(await page.getByRole('link',{name:'Rapport de ventes',exact:true}).count(),1);await page.getByRole('link',{name:'Rapport de ventes',exact:true}).click();await page.locator('.pos-sales-report').waitFor();
  assert.deepEqual(mutations,[]);assert.deepEqual(errors,[]);console.log('OK : filtres du rapport, synthèses USD/CDF, tableau10lignes, exports HTTP XLSX4feuilles et PDF toutespages, filtres invalides, rapport vide, navigation caisse et mobile390/320 ; aucune écriture.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
