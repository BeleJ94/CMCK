// Read-only UI checks. Use an authenticated disposable fixture server; this test
// never creates fixtures and rejects every request that could submit a mutation.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.DAGRIL_PLAYWRIGHT_MODULE || 'playwright');

assert(process.env.DAGRIL_TEST_URL, 'Set DAGRIL_TEST_URL to the disposable fixture server.');
const base = process.env.DAGRIL_TEST_URL.replace(/\/$/, '');
const profile = process.env.DAGRIL_UI_PROFILE || 'admin';
assert(['admin', 'livestock'].includes(profile), 'DAGRIL_UI_PROFILE must be admin or livestock.');
const screenshots = process.env.DAGRIL_UI_SCREENSHOT_DIR || '/private/tmp/dagril-green-ui';
const farmPaths = ['/livestock/pigs', '/livestock/pigs/slaughters', '/livestock/pigs/shipments'];
const butcheryPaths = ['/butchery/receipts', '/butchery/cold-room', '/butchery/production', '/butchery/counters'];
const screenPaths = [...farmPaths, ...(profile === 'admin' ? butcheryPaths : []), '/livestock'];
const searchMiss = 'zzzz-green-ui-absent-9e7a';

function isGreen(value) {
    const rgb = value.match(/[\d.]+/g)?.map(Number);
    return rgb?.length >= 3 && rgb[1] > rgb[0] && rgb[1] > rgb[2];
}

async function assertNoOverflow(page, context) {
    const layout = await page.evaluate(() => ({
        width: innerWidth,
        document: document.documentElement.scrollWidth,
        offenders: [...document.querySelectorAll('body *')].filter(element => {
            const style = getComputedStyle(element);
            return style.display !== 'none' && style.visibility !== 'hidden'
                && !element.closest('.sidebar,.workspace-entity-modal:not(.is-open),[aria-hidden="true"]')
                && element.getBoundingClientRect().right > innerWidth + 1
                && !element.closest('.flow-table-scroll,.table-responsive,.table-scroll');
        }).slice(0, 8).map(element => ({ tag: element.tagName, class: String(element.className) }))
    }));
    assert(layout.document <= layout.width + 1, `${context}: document overflows ${JSON.stringify(layout)}`);
}

async function visit(page, route) {
    const response = await page.goto(base + route);
    assert.equal(response.status(), 200, route + ' must be accessible to ' + profile);
    await page.locator('#mainContent').waitFor({ state: 'visible' });
    await page.waitForFunction(() => {
        const directory = document.querySelector('[data-pig-directory],[data-flow-directory]');
        return !directory || directory.dataset.ready === '1';
    });
    assert.equal(await page.locator('main').count(), 1, route + ': one main landmark');
    assert.equal(await page.locator('h1').count(), 1, route + ': one page title');
    assert(!/Warning:|Fatal error:|Uncaught (?:Error|Exception)/.test(await page.locator('body').innerText()), route + ': no PHP errors');
    await assertNoOverflow(page, route);
}

async function assertTheme(page) {
    const theme = await page.evaluate(() => {
        const style = getComputedStyle(document.documentElement);
        const primary = [...document.querySelectorAll('#mainContent .btn-primary')].find(element => element.offsetParent !== null);
        const active = document.querySelector('.flow-navigation [aria-current="page"]');
        const sidebarStyle = getComputedStyle(document.querySelector('.sidebar'));
        return {
            accent: style.getPropertyValue('--accent').trim().toLowerCase(),
            hover: style.getPropertyValue('--accent-hover').trim().toLowerCase(),
            soft: style.getPropertyValue('--accent-soft').trim().toLowerCase(),
            warning: style.getPropertyValue('--st-warning').trim().toLowerCase(),
            danger: style.getPropertyValue('--st-danger').trim().toLowerCase(),
            primary: primary && getComputedStyle(primary).backgroundColor,
            active: active && getComputedStyle(active).backgroundColor,
            sidebar: sidebarStyle.backgroundImage.match(/rgba?\([^)]+\)/)?.[0] || sidebarStyle.backgroundColor
        };
    });
    assert.equal(theme.accent, '#18864b', 'DAGRIL green brand token');
    assert.equal(theme.hover, '#116b3a', 'DAGRIL green hover token');
    assert.equal(theme.soft, '#eaf7ef', 'DAGRIL pale green token');
    assert.notEqual(theme.warning, theme.accent, 'Warnings retain a distinct semantic color');
    assert.notEqual(theme.danger, theme.accent, 'Errors retain a distinct semantic color');
    if (theme.primary) assert(isGreen(theme.primary), 'Primary action is green: ' + theme.primary);
    if (theme.active) assert(isGreen(theme.active), 'Current process step is green: ' + theme.active);
    assert(isGreen(theme.sidebar), 'Shared navigation uses green: ' + theme.sidebar);
}

async function assertHeader(page) {
    const siteControls = page.locator('[data-site-context-select]');
    assert(await siteControls.count() > 0, 'Shared site context selector is available');
    assert.deepEqual(await siteControls.evaluateAll(elements => elements.filter(element => element.hasAttribute('onchange')).map(element => element.id || element.name)), [], 'Site context changes use the shared script');
    const toggle = page.locator('[data-ui-attention-toggle]');
    const panel = page.locator('[data-ui-attention-panel]');
    assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
    assert(!await panel.isVisible(), 'Attention panel starts closed');
    await toggle.click();
    assert.equal(await toggle.getAttribute('aria-expanded'), 'true');
    assert(await panel.isVisible(), 'Attention panel opens from the header');
    assert.equal(await toggle.getAttribute('aria-controls'), await panel.getAttribute('id'));
    const items = await panel.locator('.ui-attention-list a').evaluateAll(links => links.map(link => ({
        href: link.href,
        count: Number.parseInt(link.querySelector('small').textContent, 10)
    })));
    assert.equal(Number(await page.locator('[data-ui-attention-count]').textContent()), items.reduce((sum, item) => sum + item.count, 0), 'Header counter sums its actionable items');
    const allLinks = await panel.locator('a').evaluateAll(links => links.map(link => link.href));
    for (const href of allLinks) {
        const destination = new URL(href);
        assert.equal(destination.origin, new URL(base).origin, 'Attention links remain within DAGRIL');
        if (profile === 'livestock') assert(farmPaths.includes(destination.pathname), 'Livestock attention links stay within the authorized module');
    }
    const pending = items.find(item => new URL(item.href).pathname.endsWith('/livestock/pigs') && new URL(item.href).searchParams.get('filter') === 'pending');
    assert(pending, 'Fixture exposes pending pig groups as an actionable attention item');
    const badge = page.locator('.sidebar a[href$="/livestock/pigs"] .nav-badge');
    assert.equal(Number(await badge.textContent()), pending.count, 'Sidebar and attention panel share the same pending-group count');
    if (profile === 'livestock') {
        assert.equal(items.length, 1, 'Restricted livestock profile sees exactly its pending-group item');
        assert.equal(allLinks.length, 1, 'Restricted livestock profile has no unrelated alert links');
    }
    await toggle.focus();
    await page.keyboard.press('ArrowDown');
    assert(await panel.evaluate(element => document.activeElement === element.querySelector('a,button')), 'ArrowDown enters the attention panel');
    await page.keyboard.press('ArrowUp');
    assert(await panel.evaluate(element => document.activeElement === [...element.querySelectorAll('a,button')].at(-1)), 'ArrowUp wraps to the final attention action');
    await page.keyboard.press('Escape');
    assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
    assert(!await panel.isVisible(), 'Escape closes the attention panel');
    assert(await toggle.evaluate(element => element === document.activeElement), 'Escape returns focus to the attention button');
    await page.keyboard.press('Enter');
    assert(await panel.isVisible(), 'Keyboard activates the attention button');
    await panel.locator('[data-ui-attention-close]').click();
    assert(!await panel.isVisible(), 'Close action dismisses the attention panel');
    assert(await toggle.evaluate(element => element === document.activeElement), 'Close action returns focus to the attention button');
    await assertNoOverflow(page, 'Shared header');
}

async function testAttentionNavigation(page) {
    await visit(page, '/livestock/pigs');
    await page.locator('[data-ui-attention-toggle]').click();
    const link = page.locator('[data-ui-attention-panel] .ui-attention-list a[href*="filter=pending"]');
    assert.equal(await link.count(), 1, 'One pending-group attention link is available');
    await link.click();
    await page.waitForURL(current => current.pathname.endsWith('/livestock/pigs') && current.searchParams.get('filter') === 'pending');
    await page.waitForFunction(() => document.querySelector('[data-pig-directory]')?.dataset.ready === '1');
    assert(!await page.locator('#pig-group-detail').isVisible(), 'A URL filter alone does not open the dialog');
    await page.locator('[data-pig-consult]').click();
    assert.equal(await page.locator('[data-pig-filter]').inputValue(), 'pending', 'Attention link opens the requested pending filter');
    assert(await page.locator('[data-pig-lot]:visible').count() > 0, 'Pending-group drill-down displays the seeded records');
    assert.equal(await page.locator('[data-pig-lot]:visible:not([data-pending="1"])').count(), 0, 'Attention drill-down contains only pending groups');
    assert.equal(await page.locator('[data-ui-attention-toggle]').getAttribute('aria-expanded'), 'false', 'Navigation dismisses the attention panel');
    await page.locator('[data-pig-reset]').click();
}

async function assertFieldLabels(page) {
    const unlabeled = await page.locator('#mainContent input:not([type="hidden"]),#mainContent select,#mainContent textarea').evaluateAll(elements => elements.filter(element => {
        if (!element.offsetParent || element.disabled) return false;
        const labelledBy = (element.getAttribute('aria-labelledby') || '').split(/\s+/)
            .map(id => document.getElementById(id)?.textContent || '').join('').trim();
        return !element.getAttribute('aria-label')?.trim() && !labelledBy
            && ![...(element.labels || [])].some(label => label.textContent.trim());
    }).map(element => element.name || element.outerHTML));
    assert.deepEqual(unlabeled, [], 'Visible form fields have labels');
}

async function assertMobileTargets(page, width) {
    if (width > 1000) return;
    const controls = await page.locator('.pig-directory button,.pig-directory input,.pig-directory select,.pig-directory textarea,.flow-directory button,.flow-directory input,.flow-directory select,.flow-directory textarea').evaluateAll(elements => elements.filter(element => {
        const style = getComputedStyle(element);
        return !element.disabled && element.type !== 'hidden' && !['checkbox', 'radio'].includes(element.type)
            && element.offsetParent !== null && style.visibility !== 'hidden'
            && !element.closest('[aria-hidden="true"]');
    }).map(element => ({
        name: element.getAttribute('aria-label') || element.name || element.textContent.trim().slice(0, 60),
        height: element.getBoundingClientRect().height,
        font: /^(INPUT|SELECT|TEXTAREA)$/.test(element.tagName) ? parseFloat(getComputedStyle(element).fontSize) : null
    })));
    assert.deepEqual(controls.filter(control => control.height < 43.5), [], 'Mobile controls offer 44px touch targets');
    assert.deepEqual(controls.filter(control => control.font !== null && control.font < 16), [], 'Mobile fields use 16px text to avoid iOS zoom');
}

async function testPigFilters(page) {
    await visit(page, '/livestock/pigs');
    if(!await page.locator('#pig-group-detail').isVisible())await page.locator('[data-pig-consult]').click();
    const reset = page.locator('[data-pig-reset]');
    if(await reset.isVisible())await reset.click();
    const total = await page.locator('[data-pig-lot]:visible').count();
    assert.equal(await page.locator('[data-pig-count]').getAttribute('aria-live'), 'polite');
    await page.locator('[data-pig-search]').fill(searchMiss);
    assert.equal(await page.locator('[data-pig-lot]:visible').count(), 0, 'Search can show zero matching lots');
    assert(await page.locator('[data-pig-empty]').isVisible(), 'No-results state is visible');
    await reset.click();
    assert.equal(await page.locator('[data-pig-search]').inputValue(), '');
    assert.equal(await page.locator('[data-pig-filter]').inputValue(), '');
    assert.equal(await page.locator('[data-pig-lot]:visible').count(), total, 'Reset restores the full directory');
    for (const value of ['male', 'female', 'breeding']) {
        const kpi = page.locator(`[data-pig-summary-filter="${value}"]`);
        await kpi.focus();
        await page.keyboard.press('Enter');
        assert.equal(await page.locator('[data-pig-filter]').inputValue(), '', 'KPI preserves the directory scope');
        assert.equal(await kpi.getAttribute('aria-pressed'), 'true');
        assert.equal(new URL(page.url()).searchParams.get('metric'), value, 'KPI selection is shareable');
        await page.reload();
        await page.waitForFunction(() => document.querySelector('[data-pig-directory]')?.dataset.ready === '1');
        assert.equal(await page.locator(`[data-pig-summary-filter="${value}"]`).getAttribute('aria-pressed'), 'true', 'Reload restores the KPI selection');
        assert(!await page.locator('#pig-group-detail').isVisible());
        await page.locator('[data-pig-consult]').click();
        const mismatch = await page.locator('[data-pig-lot]:visible').evaluateAll((cards, selected) => cards.filter(card => selected === 'breeding' ? card.dataset.breeding !== '1' : card.dataset.sex !== selected).length, value);
        assert.equal(mismatch, 0, 'KPI results match the selected sex or purpose');
    }
    for (const group of ['A', 'B', 'C']) {
        if(await page.locator('#pig-group-detail').isVisible())await page.keyboard.press('Escape');
        await page.locator(`[data-pig-group-button="${group}"]`).click();
        assert.equal(await page.locator('[data-pig-filter]').inputValue(), group);
        assert.equal(await page.locator(`[data-pig-group-button="${group}"]`).getAttribute('aria-pressed'), 'true');
        assert.equal(await page.locator(`[data-pig-lot]:visible:not([data-group="${group}"])`).count(), 0);
    }
    await reset.click();
}

async function testFlowFilters(page, route) {
    await visit(page, route);
    const reset = page.locator('[data-flow-reset]');
    const search = page.locator('[data-flow-search]');
    const state = page.locator('[data-flow-state]');
    const kind = page.locator('[data-flow-kind]');
    await reset.click();
    const total = await page.locator('[data-flow-item]:visible').count();
    assert.equal(await page.locator('[data-flow-count]').getAttribute('aria-live'), 'polite');
    await search.fill(searchMiss);
    assert.equal(await page.locator('[data-flow-item]:visible').count(), 0, route + ': search hides unrelated records');
    await reset.click();
    assert.equal(await search.inputValue(), '');
    assert.equal(await page.locator('[data-flow-item]:visible').count(), total, route + ': reset restores records');
    for (const [field, key, attribute] of [[state, 'status', 'data-state'], [kind, 'kind', 'data-kind']]) {
        if (!await field.count()) continue;
        const value = await field.locator('option').evaluateAll(options => options.find(option => option.value)?.value);
        if (!value) continue;
        await field.selectOption(value);
        assert.equal(new URL(page.url()).searchParams.get(key), value, route + ': filter updates URL');
        const mismatch = await page.locator('[data-flow-item]:visible').evaluateAll((items, selected) => items.filter(item => !(item.getAttribute(selected.attribute) || '').split(/\s+/).includes(selected.value)).length, { attribute, value });
        assert.equal(mismatch, 0, route + ': visible records match ' + key);
        await page.reload();
        await page.waitForFunction(() => document.querySelector('[data-flow-directory]')?.dataset.ready === '1');
        assert.equal(await field.inputValue(), value, route + ': URL restores filter');
        await reset.click();
        assert.equal(await field.inputValue(), '');
    }
    // Every KPI drills into a real screen; same-page drill-downs keep their filter.
    const targets = await page.locator('.ui-kpi-grid a.ui-kpi').evaluateAll(links => links.map(link => link.href));
    for (const target of [...new Set(targets)]) {
        const url = new URL(target);
        assert.equal(url.origin, new URL(base).origin, 'KPI stays within DAGRIL');
        await visit(page, route);
        const index = await page.locator('.ui-kpi-grid a.ui-kpi').evaluateAll((links, href) => links.findIndex(link => link.href === href), target);
        assert(index >= 0, 'KPI source link is available');
        await page.locator('.ui-kpi-grid a.ui-kpi').nth(index).click();
        await page.waitForURL(current => current.pathname === url.pathname
            && [...url.searchParams].every(([key, value]) => current.searchParams.get(key) === value));
        await page.waitForFunction(() => document.querySelector('[data-flow-directory]')?.dataset.ready === '1');
        // Full-page loading also validates a shared link independently of AJAX.
        const response = await page.goto(target);
        assert.equal(response.status(), 200, 'KPI destination is available: ' + target);
        await page.waitForFunction(() => document.querySelector('[data-flow-directory]')?.dataset.ready === '1');
        for (const [key, selector] of [['kind', '[data-flow-kind]'], ['status', '[data-flow-state]']]) {
            if (url.searchParams.has(key)) assert.equal(await page.locator(selector).inputValue(), url.searchParams.get(key), 'KPI destination applies ' + key);
        }
    }
}

async function testDrawers(page, route, width) {
    const ids = await page.locator('#mainContent [data-workspace-modal-open]:visible').evaluateAll(buttons => [...new Set(buttons.map(button => button.dataset.workspaceModalOpen))]);
    for (const id of ids) {
        const opener = page.locator(`#mainContent [data-workspace-modal-open="${id}"]:visible`).first();
        await opener.click();
        const modal = page.locator(`[id="${id}"]`);
        assert(await modal.isVisible(), `${route}: ${id} opens`);
        assert.equal(await modal.getAttribute('role'), 'dialog');
        assert.equal(await modal.getAttribute('aria-modal'), 'true');
        assert.equal(await modal.getAttribute('aria-hidden'), 'false');
        const labelId = await modal.getAttribute('aria-labelledby');
        assert((await page.locator(`[id="${labelId}"]`).textContent()).trim(), 'Dialog has an accessible name');
        await page.waitForFunction(dialogId => document.getElementById(dialogId).contains(document.activeElement), id);
        await assertFieldLabels(page);
        await assertMobileTargets(page, width);
        const focusable = 'button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
        await modal.evaluate((element, selector) => [...element.querySelectorAll(selector)].filter(field => field.offsetParent !== null).at(-1).focus(), focusable);
        await page.keyboard.press('Tab');
        assert(await modal.evaluate((element, selector) => document.activeElement === [...element.querySelectorAll(selector)].find(field => field.offsetParent !== null), focusable), 'Tab wraps inside the dialog');
        await page.keyboard.press('Shift+Tab');
        assert(await modal.evaluate((element, selector) => document.activeElement === [...element.querySelectorAll(selector)].filter(field => field.offsetParent !== null).at(-1), focusable), 'Shift+Tab wraps inside the dialog');
        await assertNoOverflow(page, `${route}, ${id}, ${width}px`);
        const footer = id === 'pig-group-detail' ? null : await modal.locator('footer').boundingBox();
        if (footer) assert(footer.y >= 0 && footer.y + footer.height <= page.viewportSize().height + 1, `${id}: save/cancel actions stay inside the viewport`);
        await page.keyboard.press('Escape');
        assert.equal(await modal.getAttribute('aria-hidden'), 'true');
        assert(!await modal.isVisible(), 'Escape closes the dialog');
        assert(await opener.evaluate(element => element === document.activeElement), 'Closing restores focus to the opener');
    }
}

(async () => {
    fs.mkdirSync(screenshots, { recursive: true });
    const executablePath = process.env.DAGRIL_CHROME_EXECUTABLE || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
    const browser = await chromium.launch({ ...(fs.existsSync(executablePath) ? { executablePath } : {}), headless: true });
    const errors = [];
    const mutations = [];
    try {
        const context = await browser.newContext({
            viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce', serviceWorkers: 'block',
            ...(process.env.DAGRIL_BROWSER_STORAGE_STATE ? { storageState: process.env.DAGRIL_BROWSER_STORAGE_STATE } : {})
        });
        await context.route('**/*', route => {
            if (!['GET', 'HEAD'].includes(route.request().method())) {
                mutations.push(route.request().method() + ' ' + route.request().url());
                return route.abort();
            }
            return route.continue();
        });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await testPigFilters(page);
        await testAttentionNavigation(page);
        for (const route of screenPaths.filter(route => route !== '/livestock/pigs' && route !== '/livestock')) await testFlowFilters(page, route);
        for (const width of [1440, 390, 320]) {
            await page.setViewportSize({ width, height: width > 1000 ? 1000 : 900 });
            for (const route of screenPaths) {
                await visit(page, route);
                await assertTheme(page);
                await assertHeader(page);
                await assertFieldLabels(page);
                await assertMobileTargets(page, width);
                await testDrawers(page, route, width);
                if (route === '/livestock/pigs' || route === '/butchery/counters' || route === '/livestock') {
                    await page.evaluate(async () => {
                        document.activeElement?.blur();
                        window.scrollTo(0, 0);
                        await new Promise(resolve => requestAnimationFrame(resolve));
                    });
                    await page.screenshot({ path: path.join(screenshots, `${profile}-${route.split('/').filter(Boolean).join('-')}-${width}.png`), fullPage: true });
                }
            }
            if (width < 1000) {
                const menu = page.locator('[data-sidebar-toggle]');
                await menu.click();
                assert.equal(await menu.getAttribute('aria-expanded'), 'true', 'Mobile menu reports its open state');
                await page.keyboard.press('Escape');
                assert.equal(await menu.getAttribute('aria-expanded'), 'false', 'Escape closes the mobile menu');
            }
        }
        if (profile === 'livestock') {
            assert.equal(await page.locator('.sidebar a[href*="/butchery"],[data-command-link][href*="/butchery"]').count(), 0, 'Livestock role sees only authorized modules');
            for (const route of butcheryPaths) {
                const response = await page.goto(base + route);
                assert.equal(response.status(), 403, 'Restricted role cannot access ' + route);
            }
        }
        assert.deepEqual(mutations, [], 'No mutation requests are made');
        assert.deepEqual(errors, [], 'No JavaScript errors');
        console.log(`OK: green theme, URL filters/reset, KPI and attention drill-down, shared badges and keyboard safety, dialog labels/focus/escape; ${screenPaths.length} screens at 1440/390/320 px; profile ${profile}; no data writes.`);
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
