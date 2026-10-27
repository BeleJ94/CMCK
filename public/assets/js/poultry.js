(function () {
    'use strict';
    const int = value => Number(value || 0).toLocaleString('fr-FR');
    const kg = (value, digits) => value.toLocaleString('fr-FR', {maximumFractionDigits: digits === undefined ? 3 : digits});
    const weightOf = form => {
        const gross = form.querySelector('[name="weighing_mode"][value="gross"]');
        const value = name => Number(String(form.elements[name] && form.elements[name].value || '').replace(',', '.'));
        return gross && gross.checked ? value('gross_kg') - value('tare_kg') : value('net_kg');
    };
    const once = (form, selector) => {
        const box = form.querySelector(selector);
        if (!box || box.dataset.ready) return null;
        box.dataset.ready = '1';
        return box;
    };
    const listen = (form, sync) => {
        form.addEventListener('input', sync);
        form.addEventListener('change', sync);
        form.addEventListener('reset', () => queueMicrotask(sync));
        sync();
    };
    // Poultry houses of the chosen farm only, with their free places.
    function filterHouses(select, site) {
        Array.from(select.options).forEach(option => { if (option.value) { option.disabled = option.dataset.site !== site; option.hidden = option.disabled; } });
        if (select.selectedOptions[0] && select.selectedOptions[0].disabled) select.value = '';
        const option = select.selectedOptions[0];
        return option && option.value && option.dataset.free !== '' ? Number(option.dataset.free) : null;
    }
    function capacity(free, count, input) {
        if (free === null) { input.setCustomValidity(''); return ''; }
        input.setCustomValidity(count > free ? 'La capacité du poulailler serait dépassée.' : '');
        return count > free ? 'Capacité dépassée : ' + int(free) + ' place(s) libre(s) dans ce poulailler.' : int(free) + ' place(s) libre(s) dans ce poulailler.';
    }
    function initReceive(form) {
        const box = once(form, '[data-poultry-receive-form]');
        if (!box) return;
        const heads = form.elements.heads, note = box.querySelector('[data-poultry-receive-note]');
        listen(form, () => {
            const count = Number(heads.value) || 0, weight = weightOf(form);
            const free = filterHouses(form.elements.facility_id, form.elements.site_id.value);
            const parts = [capacity(free, count, heads)];
            if (count > 0 && weight > 0) parts.push('Poids vif moyen : ' + kg(weight / count) + ' kg par oiseau.');
            note.textContent = parts.filter(Boolean).join(' ') || 'Choisissez le poulailler pour voir les places disponibles.';
            note.classList.toggle('is-warning', heads.validationMessage !== '');
        });
    }
    function initHatch(form) {
        const box = once(form, '[data-poultry-hatch-form]');
        if (!box) return;
        const mother = form.elements.mother_batch_id, heads = form.elements.heads, at = form.elements.hatched_at, note = box.querySelector('[data-poultry-hatch-note]');
        listen(form, () => {
            const option = mother.selectedOptions[0];
            if (!option || !option.value) { note.textContent = 'Choisissez les pondeuses puis le poulailler des poussins, sur la même ferme.'; return; }
            at.min = option.dataset.started + 'T00:00';
            const free = filterHouses(form.elements.facility_id, option.dataset.site);
            note.textContent = capacity(free, Number(heads.value) || 0, heads) || 'Choisissez le poulailler des poussins.';
            note.classList.toggle('is-warning', heads.validationMessage !== '');
        });
    }
    // Daily record: eggs only for layers; mortality within the birds still available.
    function initRecord(form) {
        const box = once(form, '[data-poultry-record-form]');
        if (!box) return;
        const lot = form.elements.batch_id, eggs = form.elements.eggs_count, dead = form.elements.mortality_heads, day = form.elements.recorded_on;
        const eggsField = box.querySelector('[data-poultry-eggs]'), note = box.querySelector('[data-poultry-record-note]');
        listen(form, () => {
            const option = lot.selectedOptions[0];
            if (!option || !option.value) { eggsField.hidden = false; note.textContent = 'Choisissez le lot.'; return; }
            const layer = option.dataset.type === 'layer', available = Number(option.dataset.available);
            eggsField.hidden = !layer; eggs.disabled = !layer; if (!layer) eggs.value = '0';
            dead.max = String(available); day.min = option.dataset.started;
            const count = Number(dead.value) || 0;
            const today = layer ? int(option.dataset.eggsToday) + ' œuf(s) et ' + int(option.dataset.deadToday) + ' mort(s) déjà saisis aujourd’hui.' : int(option.dataset.deadToday) + ' mort(s) déjà saisi(s) aujourd’hui.';
            note.textContent = count > available ? 'La mortalité dépasse les ' + int(available) + ' oiseaux disponibles.' : int(available) + ' oiseau(x) disponible(s). ' + today;
            note.classList.toggle('is-warning', count > available);
        });
    }
    function initSlaughter(form) {
        const box = once(form, '[data-poultry-slaughter-form]');
        if (!box) return;
        const lot = form.elements.batch_id, heads = form.elements.heads, dead = form.elements.dead_heads, at = form.elements.slaughtered_at, expiry = form.elements.expiry_date;
        const note = box.querySelector('[data-poultry-slaughter-note]'), shelfNote = box.querySelector('[data-poultry-shelf]');
        const shelfLife = () => { const option = lot.selectedOptions[0]; return Number(option && option.dataset.type === 'layer' ? box.dataset.shelfLayer : box.dataset.shelfBroiler) || 4; };
        function syncExpiry() {
            if (expiry.dataset.touched || !/^\d{4}-\d{2}-\d{2}/.test(at.value)) return;
            const date = new Date(at.value.slice(0, 10) + 'T00:00:00');
            date.setDate(date.getDate() + shelfLife());
            expiry.value = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
        }
        expiry.addEventListener('input', () => { expiry.dataset.touched = '1'; });
        at.addEventListener('change', syncExpiry);
        lot.addEventListener('change', syncExpiry);
        form.addEventListener('reset', () => queueMicrotask(() => { delete expiry.dataset.touched; }));
        listen(form, () => {
            const option = lot.selectedOptions[0];
            if (!option || !option.value) { note.textContent = 'Choisissez le lot pour voir les oiseaux disponibles.'; heads.removeAttribute('max'); return; }
            const available = Number(option.dataset.available), count = Number(heads.value) || 0, deaths = Number(dead.value) || 0, weight = weightOf(form);
            heads.max = String(Math.max(1, available - deaths));
            at.min = option.dataset.started + 'T00:00';
            shelfNote.textContent = 'Proposée à ' + shelfLife() + ' jours : ' + (option.dataset.type === 'layer' ? 'carcasse de poule.' : 'carcasse de poulet.');
            let text = int(available) + ' oiseau(x) disponible(s) dans ce lot.';
            if (count + deaths > available) text = 'Carcasses et morts dépassent les ' + int(available) + ' oiseaux disponibles.';
            else if (count > 0 && weight > 0) text += ' Soit ' + kg(weight / count) + ' kg par carcasse.';
            note.textContent = text;
            note.classList.toggle('is-warning', count + deaths > available);
        });
    }
    function initLive(form) {
        const box = once(form, '[data-poultry-live-form]');
        if (!box) return;
        const lot = form.elements.batch_id, heads = form.elements.heads, note = box.querySelector('[data-poultry-live-note]');
        listen(form, () => {
            const option = lot.selectedOptions[0];
            if (!option || !option.value) { note.textContent = 'Choisissez le lot pour voir les poulets disponibles.'; heads.removeAttribute('max'); return; }
            const available = Number(option.dataset.available), count = Number(heads.value) || 0, weight = weightOf(form);
            heads.max = String(available);
            let text = int(available) + ' poulet(s) disponible(s).';
            if (count > available) text = 'Le nombre envoyé dépasse les ' + int(available) + ' poulets disponibles.';
            else if (count > 0 && weight > 0) text += ' Poids vif moyen : ' + kg(weight / count) + ' kg par poulet.';
            note.textContent = text;
            note.classList.toggle('is-warning', count > available);
        });
    }
    // Carcass shipment: kilos proposed from the pieces, the last pieces take the remaining kilos.
    function initShipment(form) {
        const box = once(form, '[data-poultry-shipment-form]');
        if (!box) return;
        const stock = form.elements.weight_stock_id, pieces = form.elements.quantity_pieces, quantity = form.elements.quantity_kg, hint = box.querySelector('[data-poultry-shipment-hint]');
        let proposed = '';
        listen(form, event => {
            const option = stock.selectedOptions[0];
            if (!option || !option.value) { pieces.removeAttribute('max'); quantity.removeAttribute('max'); hint.textContent = 'Proposé selon le poids moyen des carcasses.'; return; }
            const totalKg = Number(option.dataset.kg), totalPieces = Number(option.dataset.pieces), count = Number(pieces.value) || 0;
            pieces.max = String(totalPieces); quantity.max = String(totalKg);
            if (count > 0 && count <= totalPieces && (!event || event.target !== quantity)) {
                const share = count >= totalPieces ? totalKg : Math.floor(Math.round(totalKg * 1000) * count / totalPieces) / 1000;
                if (quantity.value === '' || quantity.value === proposed) { quantity.value = share.toFixed(3); proposed = quantity.value; }
            }
            hint.textContent = int(totalPieces) + ' carcasse(s) et ' + kg(totalKg) + ' kg disponibles' + (count === totalPieces ? ' : tout le poids restant part avec elles.' : '.');
        });
    }
    // Slaughter of live birds at the butchery: carcass weight per bird and yield on the live weight taken.
    function initButchery(form) {
        const box = once(form, '[data-poultry-butchery-form]');
        if (!box) return;
        const lot = form.elements.live_lot_id, heads = form.elements.heads, dead = form.elements.dead_heads, at = form.elements.slaughtered_at, note = box.querySelector('[data-poultry-butchery-note]');
        listen(form, () => {
            const option = lot.selectedOptions[0];
            if (!option || !option.value) { note.textContent = 'Choisissez le lot pour voir les poulets disponibles.'; return; }
            const available = Number(option.dataset.available), liveKg = Number(option.dataset.kg), count = Number(heads.value) || 0, deaths = Number(dead.value) || 0, weight = weightOf(form);
            heads.max = String(Math.max(1, available - deaths));
            at.min = option.dataset.received + 'T00:00';
            let text = int(available) + ' poulet(s) disponible(s) hors comptoir.';
            if (count + deaths > available) text = 'Poulets abattus et morts dépassent les ' + int(available) + ' disponibles.';
            else if (count > 0 && weight > 0) {
                const used = count + deaths >= available ? liveKg : Math.floor(Math.round(liveKg * 1000) * (count + deaths) / available) / 1000;
                text += ' Soit ' + kg(weight / count) + ' kg par carcasse' + (used > 0 ? ', rendement estimé ' + kg(weight / used * 100, 1) + ' %.' : '.');
            }
            note.textContent = text;
            note.classList.toggle('is-warning', count + deaths > available);
        });
    }
    window.initPoultry = function (root) {
        const scope = root || document;
        scope.querySelectorAll('[data-poultry-butchery-form]').forEach(box => initButchery(box.closest('form')));
        const page = scope.matches && scope.matches('[data-poultry-page]') ? scope : scope.querySelector('[data-poultry-page]');
        if (!page) return;
        page.querySelectorAll('form').forEach(form => { initReceive(form); initHatch(form); initRecord(form); initSlaughter(form); initLive(form); initShipment(form); });
    };
})();
