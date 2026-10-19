// Read-only overview checks against an authenticated disposable fixture server.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { chromium } = require(process.env.DAGRIL_PLAYWRIGHT_MODULE || 'playwright');

assert(process.env.DAGRIL_TEST_URL, 'DAGRIL_TEST_URL doit désigner le serveur de test authentifié.');
const base = process.env.DAGRIL_TEST_URL.replace(/\/$/, '');
const stages = ['receipts', 'cold-room', 'production', 'counters'];
const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

async function assertLayout(page, modal, context) {
    const bounds = await page.evaluate(id => {
        const dialog = id && document.getElementById(id);
        const rect = dialog && dialog.getBoundingClientRect();
        return {
            viewport: innerWidth,
            document: document.documentElement.scrollWidth,
            dialog: dialog && {
                left: rect.left, right: rect.right,
                width: dialog.clientWidth, scroll: dialog.scrollWidth
            }
        };
    }, modal || null);
    assert(bounds.document <= bounds.viewport + 1, `${context} : pas de débordement de la page (${JSON.stringify(bounds)}).`);
    if (bounds.dialog) {
        assert(bounds.dialog.left >= -1 && bounds.dialog.right <= bounds.viewport + 1, `${context} : la modale tient dans l’écran.`);
        assert(bounds.dialog.scroll <= bounds.dialog.width + 1, `${context} : seul le tableau peut défiler horizontalement.`);
    }
}

async function visitHome(page, suffix) {
    const response = await page.goto(base + suffix);
    assert.equal(response.status(), 200, `${suffix} est accessible.`);
    await page.locator('.butchery-home').waitFor();
    await page.waitForFunction(() => [...document.querySelectorAll('.butchery-stage-card img')]
        .every(image => image.complete && image.naturalWidth > 0));
    assert.equal(await page.locator('.butchery-stage-card').count(), 4, 'Les quatre étapes sont présentes.');
    assert(!/Warning:|Fatal error:/.test(await page.locator('main').innerText()), 'Aucune erreur PHP visible.');
    for (const stage of stages) {
        const modal = page.locator(`#butchery-overview-${stage}`);
        assert.equal(await modal.getAttribute('aria-hidden'), 'true', 'Les détails restent fermés à l’arrivée.');
        assert(await modal.isHidden(), 'La page ne déclenche aucune ouverture automatique.');
    }
}

async function exerciseTable(modal) {
    const table = modal.locator('table').first();
    const initialized = await table.getAttribute('data-dagril-table');
    if (initialized !== 'ready') {
        assert.equal(await table.locator('tbody tr').count(), 1);
        assert(await table.locator('tbody td[colspan]').isVisible(), 'Un tableau vide explique l’absence de données.');
        return;
    }
    const datatable = table.locator('xpath=ancestor::div[contains(concat(" ", normalize-space(@class), " "), " dagril-datatable ")][1]');
    const search = datatable.locator('input[type="search"]');
    const size = datatable.locator('select.dagril-table-size');
    const total = await table.locator('tbody tr').count();
    assert.equal(await size.inputValue(), '10', 'Dix lignes sont affichées par défaut.');
    assert.equal(await table.locator('tbody tr:visible').count(), Math.min(10, total));

    const firstValue = (await table.locator('tbody tr:visible td').first().innerText()).trim();
    await search.fill(firstValue);
    const matching = await table.locator('tbody tr:visible').allInnerTexts();
    assert(matching.length > 0 && matching.every(text => normalize(text).includes(normalize(firstValue))), 'La recherche conserve les éléments correspondants.');
    await search.fill('zzzz-butchery-overview-absent-7840');
    assert(await table.locator('.dagril-table-empty').isVisible(), 'La recherche sans résultat est expliquée.');
    await search.fill('');
    await size.selectOption('25');
    assert.equal(await table.locator('tbody tr:visible').count(), Math.min(25, total), 'Le choix de 25 lignes est appliqué.');
    await table.locator('thead th').first().locator('button').click();
    assert.equal(await table.locator('thead th').first().getAttribute('aria-sort'), 'ascending', 'Le tri est accessible.');
    await size.selectOption('10');
    assert.equal(await table.locator('tbody tr:visible').count(), Math.min(10, total));
}

(async () => {
    const executablePath = process.env.DAGRIL_CHROME_EXECUTABLE || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
    const browser = await chromium.launch({ ...(fs.existsSync(executablePath) ? { executablePath } : {}), headless: true });
    const errors = [], mutations = [];
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce', serviceWorkers: 'block' });
        await context.route('**/*', route => {
            if (!['GET', 'HEAD'].includes(route.request().method())) {
                mutations.push(route.request().method() + ' ' + route.request().url());
                return route.abort();
            }
            return route.continue();
        });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        for (const suffix of ['/butchery', '/butchery/']) await visitHome(page, suffix);

        for (const width of [1440, 390, 320]) {
            await page.setViewportSize({ width, height: width > 1000 ? 1000 : 900 });
            await visitHome(page, '/butchery/');
            await assertLayout(page, null, `${width}px`);
            const initialUrl = page.url();
            for (const stage of stages) {
                const id = `butchery-overview-${stage}`;
                const trigger = page.locator(`.butchery-stage-card [data-workspace-modal-open="${id}"]`);
                const modal = page.locator(`#${id}`);
                await trigger.click();
                assert(await modal.isVisible(), `${stage} : la modale s’ouvre.`);
                assert.equal(await modal.getAttribute('role'), 'dialog');
                assert.equal(await modal.getAttribute('aria-modal'), 'true');
                assert.equal(await modal.getAttribute('aria-hidden'), 'false');
                const label = await modal.getAttribute('aria-labelledby');
                assert((await page.locator(`[id="${label}"]`).innerText()).trim(), 'La modale possède un nom accessible.');
                await page.waitForFunction(dialogId => document.getElementById(dialogId).contains(document.activeElement), id);
                assert.equal(await modal.locator('form').count(), 0, 'Les détails ne contiennent aucune opération de modification.');
                await assertLayout(page, id, `${stage}, ${width}px`);
                await exerciseTable(modal);
                await assertLayout(page, id, `${stage}, ${width}px après filtrage`);
                assert.equal(page.url(), initialUrl, 'Consulter et filtrer les détails ne change pas la page principale.');
                const workflow = modal.locator('footer a');
                assert.equal(new URL(await workflow.getAttribute('href'), page.url()).pathname.replace(/\/$/, ''), `/butchery/${stage}`, 'Le lien conduit au bon parcours métier.');
                await page.keyboard.press('Escape');
                assert(await modal.isHidden(), 'Échap ferme la fenêtre de détails.');
                assert.equal(await modal.getAttribute('aria-hidden'), 'true');
                assert(await trigger.evaluate(element => element === document.activeElement), 'La fermeture rend le focus au bouton d’origine.');
            }
        }

        // Exercise a workflow link instead of relying only on its destination.
        await page.setViewportSize({ width: 1440, height: 1000 });
        await visitHome(page, '/butchery/');
        await page.locator('.butchery-stage-card [data-workspace-modal-open="butchery-overview-receipts"]').click();
        await Promise.all([
            page.waitForURL(url => url.pathname.replace(/\/$/, '') === '/butchery/receipts'),
            page.locator('#butchery-overview-receipts footer a').click()
        ]);
        await page.locator('[data-flow-directory][data-page-scope="butchery-receipts"]').waitFor();
        assert.deepEqual(mutations, [], 'Aucune requête de modification n’a été tentée.');
        assert.deepEqual(errors, [], 'Aucune erreur JavaScript.');
        console.log('OK : accueil Boucherie avec et sans slash ; quatre illustrations et modales ; recherche, tri, 10/25 lignes ; focus/Échap, liens métier et absence de débordement à 1440/390/320 px ; aucune écriture.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
