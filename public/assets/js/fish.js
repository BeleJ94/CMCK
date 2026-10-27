(function () {
    'use strict';
    const int = value => Number(value || 0).toLocaleString('fr-FR');
    function preselect(modal, field, key) {
        if (!modal || modal.dataset.fishReady) return;
        modal.dataset.fishReady = '1';
        // The workspace modal resets its form before dispatching this event.
        modal.addEventListener('workspace:opened', event => {
            const value = event.detail && event.detail.trigger ? event.detail.trigger.dataset[key] : '';
            const select = modal.querySelector('[name="' + field + '"]');
            if (!select || !value) return;
            select.value = value;
            select.dispatchEvent(new Event('change', {bubbles: true}));
        });
    }
    function initHarvest(form) {
        const box = form.querySelector('[data-fish-harvest-form]');
        if (!box || box.dataset.ready) return;
        box.dataset.ready = '1';
        const lot = form.elements.batch_id, heads = form.elements.heads, final = form.elements.final_harvest;
        const caught = form.elements.caught_at, expiry = form.elements.expiry_date, note = box.querySelector('[data-fish-harvest-note]');
        const shelfLife = Number(box.dataset.shelfLife) || 3;
        function sync() {
            const option = lot.selectedOptions[0];
            if (!option || !option.value) { note.textContent = 'Choisissez le lot pour voir les poissons restants.'; heads.removeAttribute('max'); return; }
            const remaining = Number(option.dataset.remaining), initial = Number(option.dataset.initial), harvested = Number(option.dataset.harvested);
            heads.max = String(remaining);
            caught.min = option.dataset.started + 'T00:00';
            const caughtNow = Number(heads.value) || 0;
            let text = int(remaining) + ' poisson(s) restant(s) sur ' + int(initial) + ' alevins mis.';
            if (caughtNow > remaining) text = 'Le nombre pêché dépasse les ' + int(remaining) + ' poissons restants.';
            else if (final.checked) {
                const dead = remaining - caughtNow;
                const survival = initial > 0 ? (harvested + caughtNow) / initial * 100 : 0;
                text = 'Pêche finale : ' + int(dead) + ' poisson(s) non pêché(s) seront comptés comme morts. Survie du lot : ' + survival.toLocaleString('fr-FR', {maximumFractionDigits: 1}) + ' %.';
            }
            note.textContent = text;
            note.classList.toggle('is-warning', caughtNow > remaining || (final.checked && remaining - caughtNow > 0));
        }
        function syncExpiry() {
            if (expiry.dataset.touched || !/^\d{4}-\d{2}-\d{2}/.test(caught.value)) return;
            const date = new Date(caught.value.slice(0, 10) + 'T00:00:00');
            date.setDate(date.getDate() + shelfLife);
            expiry.value = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
        }
        expiry.addEventListener('input', () => { expiry.dataset.touched = '1'; });
        caught.addEventListener('change', syncExpiry);
        form.addEventListener('input', sync);
        form.addEventListener('change', sync);
        form.addEventListener('reset', () => queueMicrotask(() => { delete expiry.dataset.touched; sync(); }));
        sync();
    }
    function initShipment(form) {
        const box = form.querySelector('[data-fish-shipment-form]');
        if (!box || box.dataset.ready) return;
        box.dataset.ready = '1';
        const sync = () => {
            const option = form.elements.weight_stock_id.selectedOptions[0];
            const quantity = form.elements.quantity_kg;
            if (option && option.dataset.max) { quantity.max = option.dataset.max; quantity.placeholder = 'Jusqu’à ' + Number(option.dataset.max).toLocaleString('fr-FR') + ' kg'; }
            else { quantity.removeAttribute('max'); quantity.placeholder = ''; }
        };
        form.addEventListener('change', sync);
        form.addEventListener('reset', () => queueMicrotask(sync));
        sync();
    }
    window.initFish = function (root) {
        const scope = root || document;
        const page = scope.matches && scope.matches('[data-fish-page]') ? scope : scope.querySelector('[data-fish-page]');
        if (!page) return;
        preselect(page.querySelector('#fish-stock'), 'parcel_id', 'fishParcel');
        preselect(page.querySelector('#fish-harvest'), 'batch_id', 'fishLot');
        preselect(page.querySelector('#fish-shipment'), 'weight_stock_id', 'fishStock');
        page.querySelectorAll('form').forEach(form => { initHarvest(form); initShipment(form); });
    };
})();
