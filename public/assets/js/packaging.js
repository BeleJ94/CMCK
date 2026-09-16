(function () {
    'use strict';
    window.initPackaging = function (root) {
        const panel = root.querySelector('[data-pack-directory]');
        if (!panel || panel.dataset.ready) return;
        panel.dataset.ready = '1';
        panel.querySelectorAll('[data-pack-tab]').forEach(button => button.addEventListener('click', () => {
            panel.querySelectorAll('[data-pack-space]').forEach(space => space.hidden = space.dataset.packSpace !== button.dataset.packTab);
            panel.querySelectorAll('[data-pack-tab]').forEach(tab => tab.setAttribute('aria-pressed', String(tab === button)));
        }));
        const form = panel.querySelector('[data-pack-editor-form]');
        if (!form) return;
        const batch = form.querySelector('[data-packaging-batch]');
        const format = form.querySelector('[data-bag-format]');
        const count = form.querySelector('[data-bags-count]');
        const summary = form.querySelector('[data-pack-summary]');
        let stocks = JSON.parse(form.querySelector('[data-pack-stocks]').textContent);
        const stockMessage = form.querySelector('[data-pack-stock-message]');
        const submit = form.querySelector('button[type=submit]');
        const number = value => value.toLocaleString('fr-FR', {maximumFractionDigits: 3});
        function update() {
            const lot = batch.selectedOptions[0];
            Array.from(format.options).forEach(option => {
                option.disabled = !!option.value && (!batch.value || option.dataset.target !== lot.dataset.code);
                option.hidden = option.disabled;
            });
            if (format.selectedOptions[0]?.disabled) format.value = '';
            const weight = Number(format.selectedOptions[0]?.dataset.weight || 0);
            const available = Number(lot?.dataset.available || 0);
            const total = weight * Number(count.value || 0);
            form.querySelector('[data-packaging-total]').value = total ? number(total) + ' kg' : '';
            summary.textContent = !batch.value ? 'Sélectionnez un lot pour afficher les formats compatibles.' : !Array.from(format.options).some(o => o.value && !o.disabled) ? 'Aucun format compatible avec ce produit. Ajoutez un format dans les références des sacs vides.' : !weight ? 'Choisissez un format compatible pour calculer le poids.' : total > available ? 'Quantité trop élevée : maximum ' + Math.floor(available / weight) + ' sacs pour ce lot.' : number(total) + ' kg à conditionner · Reste sur le lot : ' + number(available - total) + ' kg. Maximum : ' + Math.floor(available / weight) + ' sacs (selon le stock de farine).';
            const stock = stocks.find(s => String(s.site_id) === lot?.dataset.site && String(s.packaging_item_id) === format.value);
            const workshop = Number(stock?.operational_quantity || 0);
            const maximum = weight ? Math.min(Math.floor(available / weight), workshop) : 0;
            count.max = String(maximum);
            submit.disabled = !weight || !batch.value || maximum < 1 || !count.value || Number(count.value) > maximum;
            stockMessage.textContent = !batch.value || !weight ? 'Sélectionnez un lot et un format pour connaître le stock de sacs.' : (stock?.site_name ? stock.site_name + ' · ' : '') + 'Atelier : ' + number(workshop) + ' sacs utilisables · Magasin : ' + number(Number(stock?.physical_quantity || 0)) + ' sacs, dont ' + number(Number(stock?.reserved_quantity || 0)) + ' réservés.';
            if (weight && batch.value) {
                const missing = Math.max(0, Number(count.value || 0) - workshop);
                if (!workshop || missing) {
                    stockMessage.textContent += missing ? ' Il manque ' + number(missing) + ' sacs à l’atelier.' : ' Aucun sac de ce format n’a été délivré à l’atelier.';
                    stockMessage.textContent += ' Dans Emballages vides → Atelier : créez une demande, faites-la approuver puis délivrer sur le site de ce lot. Si le magasin est vide, faites d’abord réceptionner un achat ou un transfert.';
                }
                summary.textContent += ' Limite avec les sacs disponibles à l’atelier : ' + number(maximum) + ' sacs.';
            }
        }
        form.addEventListener('input', update);
        form.addEventListener('change', update);
        panel.addEventListener('click', event => {
            const trigger = event.target.closest('[data-workspace-modal-open="packagingEditor"]');
            if (trigger) setTimeout(() => {
                batch.value = trigger.dataset.packLot || '';
                form.querySelector('[data-feed-error]').hidden = true;
                batch.dispatchEvent(new Event('change', {bubbles: true}));
            }, 0);
        });
        form.querySelector('[data-pack-refresh]').addEventListener('click', async event => {
            const button = event.currentTarget;
            button.disabled = true;
            const error = form.querySelector('[data-feed-error]');
            try {
                const response = await fetch(location.href, {headers: {'X-Requested-With':'XMLHttpRequest'}, cache:'no-store'});
                const document = new DOMParser().parseFromString(await response.text(), 'text/html');
                const data = document.querySelector('[data-pack-stocks]');
                if (!response.ok || !data) throw new Error('Actualisation impossible. Vérifiez votre connexion ou rechargez la page.');
                stocks = JSON.parse(data.textContent);
                document.querySelectorAll('[data-packaging-batch] option[value]').forEach(option => {
                    const existing = Array.from(batch.options).find(o => o.value === option.value);
                    if (existing) existing.dataset.available = option.dataset.available;
                });
                Array.from(batch.options).filter(o => o.value).forEach(option => {
                    if (!Array.from(document.querySelectorAll('[data-packaging-batch] option')).some(o => o.value === option.value)) option.dataset.available = '0';
                });
                error.hidden = true;
                batch.dispatchEvent(new Event('change', {bubbles:true}));
                stockMessage.textContent += ' Stocks actualisés.';
            } catch (exception) { error.textContent = exception.message; error.hidden = false; }
            finally { button.disabled = false; }
        });
        update();
    };
})();
