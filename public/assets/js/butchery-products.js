(function () {
    'use strict';
    const normalize = value => String(value || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    // Preview of ButcheryProductService::suggestCode; the server confirms the code and adds a suffix if it is taken.
    const codeOf = name => String(name || '').replace(/œ/g, 'oe').replace(/Œ/g, 'OE').replace(/æ/g, 'ae').replace(/Æ/g, 'AE')
        .normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 50).replace(/-+$/, '') || 'PRODUIT';
    function listen(form, sync) {
        form.addEventListener('input', sync);
        form.addEventListener('change', sync);
        form.addEventListener('reset', () => queueMicrotask(sync));
        sync();
    }
    function initCatalog(page) {
        const search = page.querySelector('[data-product-search]'), material = page.querySelector('[data-product-material-filter]');
        const rows = Array.from(page.querySelectorAll('[data-product-row]')), count = page.querySelector('[data-product-count]'), empty = page.querySelector('[data-product-empty]');
        function apply() {
            const query = normalize(search.value.trim()), wanted = material ? material.value : '';
            let shown = 0;
            rows.forEach(row => {
                const sources = row.dataset.sources ? row.dataset.sources.split(',') : [];
                const match = normalize(row.dataset.search).includes(query) && (!wanted || (wanted === 'none' ? !sources.length : sources.includes(wanted)));
                row.hidden = !match;
                if (match) shown++;
            });
            count.textContent = shown + ' produit(s) sur ' + rows.length;
            empty.hidden = shown > 0;
        }
        search.addEventListener('input', apply);
        if (material) material.addEventListener('change', apply);
        apply();
    }
    // Product drawer: code proposed from the name, and at least one material for a new product.
    function initProductForm(form) {
        const box = form.querySelector('[data-product-form]');
        if (!box || box.dataset.ready) return;
        box.dataset.ready = '1';
        const creating = box.hasAttribute('data-product-new'), sources = Array.from(box.querySelectorAll('[name="source_ids[]"]')), note = box.querySelector('[data-product-sources-note]');
        const code = box.querySelector('[data-product-code]'), badge = box.querySelector('[data-product-code-badge]'), help = box.querySelector('[data-product-code-help]'), name = form.elements.name;
        listen(form, () => {
            const checked = sources.filter(source => source.checked).length;
            if (sources.length) sources[0].setCustomValidity(creating && !checked ? 'Cochez au moins une matière d’origine.' : '');
            note.textContent = checked ? 'Proposé à la transformation de ' + checked + ' matière(s).' : (creating ? 'Cochez au moins une matière : le produit ne sera proposé qu’à leur transformation.' : 'Aucune matière cochée : ce produit ne sera plus proposé à la transformation.');
            note.classList.toggle('is-warning', !checked);
            if (!code) return;
            const custom = code.value.trim() !== '', proposed = name.value.trim() ? codeOf(name.value) : '';
            badge.textContent = custom ? 'Personnalisé' : 'Automatique';
            badge.classList.toggle('is-custom', custom);
            code.placeholder = proposed;
            help.textContent = custom ? 'Lettres, chiffres et tirets. Le code ne pourra plus changer.' : (proposed ? 'Code proposé : ' + proposed + '. Un suffixe est ajouté s’il est déjà pris.' : 'Laissez vide : le code est déduit du nom, par exemple FILET-DE-PORC.');
        });
    }
    // Material drawer: products ticked for this material, with a search when the catalog is long.
    function initMaterialForm(box) {
        if (box.dataset.ready) return;
        box.dataset.ready = '1';
        const checks = Array.from(box.querySelectorAll('[name="product_ids[]"]')), selected = box.querySelector('[data-material-selected]'), search = box.querySelector('[data-material-product-search]');
        listen(box.closest('form'), () => {
            const count = checks.filter(check => check.checked).length;
            selected.textContent = count ? count + ' produit(s) coché(s).' : 'Aucun produit coché : la matière ne pourra plus être transformée.';
            selected.classList.toggle('is-warning', !count);
            if (!search) return;
            const query = normalize(search.value.trim());
            checks.forEach(check => { const choice = check.closest('[data-search]'); choice.hidden = query !== '' && !check.checked && !normalize(choice.dataset.search).includes(query); });
        });
    }
    window.initButcheryProducts = function (root) {
        const scope = root || document;
        const page = scope.matches && scope.matches('[data-butchery-products]') ? scope : scope.querySelector('[data-butchery-products]');
        if (!page) return;
        if (!page.dataset.ready) { page.dataset.ready = '1'; initCatalog(page); }
        page.querySelectorAll('form').forEach(initProductForm);
        page.querySelectorAll('[data-material-products]').forEach(initMaterialForm);
    };
})();
