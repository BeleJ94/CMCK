const { chromium } = require(process.env.DAGRIL_PLAYWRIGHT_MODULE || 'playwright');
const assert = require('assert');
(async () => {
    const browser = await chromium.launch({ executablePath: process.env.DAGRIL_CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
    try {
        const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        assert.equal((await page.goto('http://127.0.0.1:8099/butchery')).status(), 200);
        const site = await page.locator('select[name=site_id] option').evaluateAll(options => options.find(option => /BOUCH|boucherie/i.test(option.textContent))?.value);
        assert(site, 'Site BOUCH disponible');
        await page.evaluate(async siteId => {
            const token = document.querySelector('input[name=_token]').value;
            await fetch('/context/site', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: token, site_id: siteId }) });
        }, site);
        await page.goto('http://127.0.0.1:8099/butchery');
        assert.equal(await page.locator('.sidebar a[aria-current=page][href$="/butchery/receipts"]').count(), 1);
        for (const section of ['receipts', 'slaughters', 'production', 'stocks', 'outgoing', 'recipes']) {
            await Promise.all([page.waitForURL('**/butchery/' + section), page.locator('.sidebar a[href$="/butchery/' + section + '"]').click()]);
            assert.equal(await page.locator('.butchery-nav').count(), 0);
            assert.equal(await page.locator('[data-butchery-panel]:visible').evaluateAll(panels => panels.every(panel => panel.dataset.butcheryPanel === location.pathname.split('/').pop())), true);
            assert.equal(await page.locator('.sidebar a[aria-current=page][href$="/butchery/' + section + '"]').count(), 1);
            await page.reload();
            assert.equal(await page.locator('.butchery-nav').count(), 0);
            const triggers = page.locator('[data-butchery-panel]:visible [data-workspace-modal-open]');
            for (let i = 0; i < await triggers.count(); i++) {
                await triggers.nth(i).click();
                const modal = page.locator('.butchery-editor.is-open');
                assert(await modal.isVisible());
                assert.equal(await modal.locator('[name=_section]').inputValue(), section);
                assert.equal(await modal.locator('input:not([type=hidden]):not(label input),select:not(label select)').count(), 0);
                for (const width of [1440, 390, 320]) {
                    await page.setViewportSize({ width, height: 800 });
                    const footer = await modal.locator('footer').boundingBox();
                    assert(footer.y + footer.height <= 801, 'Actions visibles');
                    assert(footer.x >= 0 && footer.x + footer.width <= width + 1, 'Actions contenues sur mobile');
                }
                await modal.locator('.modal-close').click();
                await page.setViewportSize({ width: 1440, height: 900 });
            }
            for (const width of [390, 320]) {
                await page.setViewportSize({ width, height: 800 });
                await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
                const overflow = await page.evaluate(() => ({width: innerWidth, scroll: document.documentElement.scrollWidth}));
                if (overflow.scroll > overflow.width + 1) await page.screenshot({path:'/private/tmp/butchery-overflow.png',fullPage:true});
                assert(overflow.scroll <= overflow.width + 1, 'Aucun débordement de page : ' + section + ' ' + JSON.stringify(overflow));
            }
            await page.setViewportSize({ width: 1440, height: 900 });
        }
        const csrf = await page.evaluate(async () => {
            const form = document.querySelector('[data-butchery-form]'), data = new FormData(form);
            data.set('_token', 'invalid');
            const response = await fetch(form.action, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            return { status: response.status, data: await response.json() };
        });
        assert.equal(csrf.status, 419);
        assert.equal(csrf.data.ok, false);
        await page.goto('http://127.0.0.1:8099/butchery/receipts');
        await page.screenshot({ path: '/private/tmp/butchery-overview.png' });
        await page.setViewportSize({ width: 390, height: 844 });
        await page.reload();
        await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
        await page.screenshot({ path: '/private/tmp/butchery-mobile.png' });
        assert.equal((await page.goto('http://127.0.0.1:8099/butchery/unknown')).status(), 404);
        assert.deepEqual(errors, []);
        console.log('OK : six sous-menus, liens directs, rechargement, panneaux, mobile 320/390, actions visibles, CSRF JSON et route inconnue.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
