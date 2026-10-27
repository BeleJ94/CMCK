(function () {
    'use strict';
    const int = value => Number(value || 0).toLocaleString('fr-FR');
    const kg = value => value.toLocaleString('fr-FR', {maximumFractionDigits: 1});
    const weightOf = form => {
        const gross = form.querySelector('[name="weighing_mode"][value="gross"]');
        const value = name => Number(String(form.elements[name] && form.elements[name].value || '').replace(',', '.'));
        return gross && gross.checked ? value('gross_kg') - value('tare_kg') : value('net_kg');
    };
    // Reception: buildings of the chosen farm only, with their free places and the average live weight.
    function initReceive(form) {
        const box = form.querySelector('[data-cattle-receive-form]');
        if (!box || box.dataset.ready) return;
        box.dataset.ready = '1';
        const site = form.elements.site_id, facility = form.elements.facility_id, heads = form.elements.heads, note = box.querySelector('[data-cattle-receive-note]');
        function sync() {
            Array.from(facility.options).forEach(option => { if (option.value) { option.disabled = option.dataset.site !== site.value; option.hidden = option.disabled; } });
            if (facility.selectedOptions[0] && facility.selectedOptions[0].disabled) facility.value = '';
            const option = facility.selectedOptions[0], count = Number(heads.value) || 0, weight = weightOf(form);
            const free = option && option.value && option.dataset.free !== '' ? Number(option.dataset.free) : null;
            const parts = [];
            if (free !== null) parts.push(count > free ? 'Capacité dépassée : ' + int(free) + ' place(s) libre(s) dans ce bâtiment.' : int(free) + ' place(s) libre(s) dans ce bâtiment.');
            if (count > 0 && weight > 0) parts.push('Poids vif moyen : ' + kg(weight / count) + ' kg par tête.');
            note.textContent = parts.join(' ') || 'Choisissez le bâtiment pour voir les places disponibles.';
            note.classList.toggle('is-warning', free !== null && count > free);
            heads.setCustomValidity(free !== null && count > free ? 'La capacité du bâtiment serait dépassée.' : '');
        }
        form.addEventListener('input', sync);
        form.addEventListener('change', sync);
        form.addEventListener('reset', () => queueMicrotask(sync));
        sync();
    }
    // Slaughter: available heads of the lot, carcass weight per head and suggested shelf life.
    function initSlaughter(form) {
        const box = form.querySelector('[data-cattle-slaughter-form]');
        if (!box || box.dataset.ready) return;
        box.dataset.ready = '1';
        const lot = form.elements.batch_id, heads = form.elements.heads, at = form.elements.slaughtered_at, expiry = form.elements.expiry_date, note = box.querySelector('[data-cattle-slaughter-note]');
        const shelfLife = Number(box.dataset.shelfLife) || 5;
        function sync() {
            const option = lot.selectedOptions[0];
            if (!option || !option.value) { note.textContent = 'Choisissez le lot pour voir les bovins disponibles.'; heads.removeAttribute('max'); return; }
            const available = Number(option.dataset.available), count = Number(heads.value) || 0, weight = weightOf(form), average = Number(option.dataset.average) || 0;
            heads.max = String(available);
            at.min = option.dataset.started + 'T00:00';
            let text = int(available) + ' bovin(s) disponible(s) dans ce lot.';
            if (count > available) text = 'Le nombre abattu dépasse les ' + int(available) + ' bovins disponibles.';
            else if (count > 0 && weight > 0) {
                text += ' Soit ' + kg(weight / count) + ' kg par carcasse';
                text += average > 0 ? ', rendement carcasse estimé ' + kg(weight / (average * count) * 100) + ' %.' : '.';
            }
            note.textContent = text;
            note.classList.toggle('is-warning', count > available);
        }
        function syncExpiry() {
            if (expiry.dataset.touched || !/^\d{4}-\d{2}-\d{2}/.test(at.value)) return;
            const date = new Date(at.value.slice(0, 10) + 'T00:00:00');
            date.setDate(date.getDate() + shelfLife);
            expiry.value = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
        }
        expiry.addEventListener('input', () => { expiry.dataset.touched = '1'; });
        at.addEventListener('change', syncExpiry);
        form.addEventListener('input', sync);
        form.addEventListener('change', sync);
        form.addEventListener('reset', () => queueMicrotask(() => { delete expiry.dataset.touched; sync(); }));
        sync();
    }
    function initShipment(form) {
        const box = form.querySelector('[data-cattle-shipment-form]');
        if (!box || box.dataset.ready) return;
        box.dataset.ready = '1';
        const sync = () => {
            const option = form.elements.weight_stock_id.selectedOptions[0], quantity = form.elements.quantity_kg;
            if (option && option.dataset.max) { quantity.max = option.dataset.max; quantity.placeholder = 'Jusqu’à ' + Number(option.dataset.max).toLocaleString('fr-FR') + ' kg'; }
            else { quantity.removeAttribute('max'); quantity.placeholder = ''; }
        };
        form.addEventListener('change', sync);
        form.addEventListener('reset', () => queueMicrotask(sync));
        sync();
    }
    window.initCattle = function (root) {
        const scope = root || document;
        const page = scope.matches && scope.matches('[data-cattle-page]') ? scope : scope.querySelector('[data-cattle-page]');
        if (!page) return;
        page.querySelectorAll('form').forEach(form => { initReceive(form); initSlaughter(form); initShipment(form); });
    };
})();
