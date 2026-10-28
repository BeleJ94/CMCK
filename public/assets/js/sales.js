(function () {
    'use strict';
    const number = (value, digits) => value.toLocaleString('fr-FR', {minimumFractionDigits: digits, maximumFractionDigits: digits});
    const parse = value => Number(String(value || '').replace(',', '.'));
    function listen(form, sync) {
        form.addEventListener('input', sync);
        form.addEventListener('change', sync);
        form.addEventListener('reset', () => queueMicrotask(sync));
        sync();
    }
    // Client record: legal identifiers, trade name and contact person only for companies.
    function initCustomerForm(box) {
        if (box.dataset.ready) return;
        box.dataset.ready = '1';
        const form = box.closest('form'), label = box.querySelector('[data-customer-name-label]');
        listen(form, () => {
            const company = (form.querySelector('[name="customer_type"]:checked') || {}).value !== 'individual';
            box.querySelectorAll('[data-company-only]').forEach(field => {
                field.hidden = !company;
                field.querySelectorAll('input,select,textarea').forEach(input => { input.disabled = !company; });
            });
            label.textContent = company ? 'Raison sociale *' : 'Nom et prénom *';
        });
    }
    // Rate of the day: variation against the rate in force, with a confirmation beyond the alert threshold.
    function initRateForm(form) {
        if (form.dataset.ready) return;
        form.dataset.ready = '1';
        const current = parse(form.dataset.currentRate), alert = parse(form.dataset.alert), note = form.querySelector('[data-rate-variation]');
        const confirm = form.querySelector('[data-rate-confirm]'), check = confirm.querySelector('input');
        listen(form, () => {
            const rate = parse(form.elements.rate.value);
            if (!rate || !current) { note.textContent = current ? '' : 'Premier taux : il s’applique dès son enregistrement.'; confirm.hidden = true; check.required = false; check.checked = false; note.classList.remove('is-warning'); return; }
            const variation = (rate / current - 1) * 100, large = Math.abs(variation) > alert;
            note.textContent = 'Variation : ' + (variation > 0 ? '+' : '') + number(variation, 1) + ' % par rapport au taux en vigueur (' + number(current, 2) + ' CDF).' + (large ? ' Variation importante : vérifiez le taux.' : '');
            note.classList.toggle('is-warning', large);
            confirm.hidden = !large; check.required = large; if (!large) check.checked = false;
        });
    }
    // Price drawer: CDF equivalent at the rate of the day, while typing.
    function initPriceForm(box) {
        if (box.dataset.ready) return;
        box.dataset.ready = '1';
        const rate = parse(box.dataset.rate);
        listen(box.closest('form'), () => {
            box.querySelectorAll('[data-price-input]').forEach(input => {
                const note = input.closest('label').querySelector('[data-price-cdf]'), price = parse(input.value);
                note.textContent = !price ? 'Laissé vide : tarif inchangé.' : (rate ? '≈ ' + number(price * rate, 0) + ' CDF au taux du jour' : 'Équivalent CDF disponible après la saisie du taux.');
            });
        });
    }
    // Order lines: amount of each line, total in USD (and CDF for clients billed in CDF), stock free at the production site.
    function initOrderForm(box) {
        if (box.dataset.ready) return;
        box.dataset.ready = '1';
        const form = box.closest('form'), body = box.querySelector('[data-order-lines]'), template = box.querySelector('[data-order-line-template]');
        const add = box.querySelector('[data-order-line-add]'), total = box.querySelector('[data-order-total]'), rate = parse(box.dataset.rate);
        let next = Number(add.dataset.nextIndex || 1);
        const sync = () => {
            const rows = Array.from(body.querySelectorAll('[data-order-line]')), chosen = rows.map(row => row.querySelector('select').value);
            let bags = 0, amount = 0;
            rows.forEach((row, index) => {
                const select = row.querySelector('select'), option = select.selectedOptions[0], quantity = Math.floor(parse(row.querySelector('input').value));
                const hint = row.querySelector('[data-order-line-hint]'), cell = row.querySelector('[data-order-line-amount]');
                // The same product and format cannot be ordered on two lines.
                Array.from(select.options).forEach(item => { if (item.value) item.hidden = chosen.some((value, other) => other !== index && value === item.value); });
                row.querySelector('[data-order-line-remove]').disabled = rows.length === 1;
                if (!option || !option.value) { hint.textContent = ''; cell.textContent = '—'; return; }
                const price = parse(option.dataset.price), free = Number(option.dataset.free);
                cell.textContent = quantity > 0 ? number(quantity * price, 2) + ' USD' : '—';
                hint.textContent = free > 0 ? number(free, 0) + ' sac(s) libre(s) à ' + option.dataset.source + (quantity > free ? ' : ' + number(quantity - free, 0) + ' à produire.' : '.') : 'Aucun sac libre à ' + option.dataset.source + ' : tout sera à produire.';
                hint.classList.toggle('is-warning', quantity > free);
                if (quantity > 0) { bags += quantity; amount += quantity * price; }
            });
            const client = form.elements.customer_id.selectedOptions[0], cdf = client && client.dataset.currency === 'CDF';
            total.textContent = bags ? 'Total : ' + number(bags, 0) + ' sac(s) · ' + number(amount, 2) + ' USD' + (cdf ? (rate ? ' · ≈ ' + number(amount * rate, 0) + ' CDF au taux du jour' : ' · taux du jour à saisir pour ce client facturé en CDF') : '') : 'Ajoutez les produits commandés.';
        };
        add.addEventListener('click', () => { body.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(next++))); sync(); });
        box.addEventListener('click', event => { const remove = event.target.closest('[data-order-line-remove]'); if (remove && body.querySelectorAll('[data-order-line]').length > 1) { remove.closest('[data-order-line]').remove(); sync(); } });
        // A reset keeps one empty line, as on the first opening.
        form.addEventListener('reset', () => queueMicrotask(() => { Array.from(body.querySelectorAll('[data-order-line]')).slice(1).forEach(row => { if (!row.querySelector('select option[selected]')) row.remove(); }); sync(); }));
        form.addEventListener('input', sync);
        form.addEventListener('change', sync);
        sync();
    }
    window.initSales = function (root) {
        const scope = root || document;
        scope.querySelectorAll('[data-order-form]').forEach(initOrderForm);
        scope.querySelectorAll('[data-customer-form]').forEach(initCustomerForm);
        scope.querySelectorAll('[data-sales-rate-form]').forEach(initRateForm);
        scope.querySelectorAll('[data-sales-price-form]').forEach(initPriceForm);
    };
})();
