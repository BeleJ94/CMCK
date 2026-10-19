(function () {
    'use strict';
    let previousDirectory = null;
    window.initPigs = function (root) {
        const panel = root.matches?.('[data-pig-directory]') ? root : root.querySelector('[data-pig-directory]');
        if (!panel || panel.dataset.ready) return;
        panel.dataset.ready = '1';
        const search = panel.querySelector('[data-pig-search]');
        const filter = panel.querySelector('[data-pig-filter]');
        if (!search || !filter) return;
        const cards = Array.from(panel.querySelectorAll('[data-pig-lot]'));
        const key = 'pig-filters:' + panel.dataset.siteScope;
        const snapshot = new Map(cards.map(card => [card.dataset.pigLot, card.textContent.replace(/\s+/g, ' ').trim()]));
        const changed = new Set(previousDirectory?.scope === key ? cards.filter(card => previousDirectory.rows.get(card.dataset.pigLot) !== snapshot.get(card.dataset.pigLot)).map(card => card.dataset.pigLot) : []);
        previousDirectory = {scope:key, rows:snapshot};
        const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('fr');
        const url = new URL(window.location.href);
        let metric = '';
        let pageNumber = 1;
        let sortKey = 'lot';
        let sortDirection = 1;
        const pageSize = panel.querySelector('[data-pig-page-size]');
        const validMetrics = ['', 'male', 'female', 'breeding'];
        try {
            const saved = JSON.parse(sessionStorage.getItem(key) || '{}');
            search.value = saved.search || '';
            filter.value = saved.filter || '';
            metric = validMetrics.includes(saved.metric) ? saved.metric : '';
        } catch (_) {}
        if (url.searchParams.has('filter') || url.searchParams.has('q') || url.searchParams.has('metric')) {
            filter.value = url.searchParams.get('filter') || '';
            search.value = url.searchParams.get('q') || '';
            metric = validMetrics.includes(url.searchParams.get('metric')) ? url.searchParams.get('metric') : '';
        }
        function render(updateUrl = false) {
            let count = 0;
            const matched = [];
            const totals = {total:0, male:0, female:0, breeding:0};
            const query = normalize(search.value.trim());
            cards.forEach(card => {
                const value = filter.value;
                const matches = !value || card.dataset.group === value || card.dataset.sex === value || (value === 'breeding' && card.dataset.breeding === '1') || (value === 'pending' && card.dataset.pending === '1');
                const inScope = matches && normalize(card.dataset.search).includes(query);
                card.hidden = !inScope || !( !metric || card.dataset.sex === metric || (metric === 'breeding' && card.dataset.breeding === '1'));
                if (!card.hidden) { count++; matched.push(card); }
                if (inScope) {
                    const heads = Number(card.dataset.heads) || 0;
                    totals.total += heads;
                    if (card.dataset.sex === 'male' || card.dataset.sex === 'female') totals[card.dataset.sex] += heads;
                    if (card.dataset.breeding === '1') totals.breeding += heads;
                }
            });
            const compare = new Intl.Collator('fr', {numeric:true, sensitivity:'base'});
            const sortValue = card => sortKey === 'lot' ? card.dataset.pigLot : card.dataset[sortKey];
            matched.sort((a,b) => compare.compare(sortValue(a),sortValue(b)) * sortDirection);
            const size = Number(pageSize.value);
            const pages = Math.max(1,Math.ceil(count / size));
            pageNumber = Math.min(pageNumber,pages);
            const body = panel.querySelector('.pig-lots');
            matched.forEach((card,index) => {
                body.appendChild(card);
                card.hidden = index < (pageNumber-1)*size || index >= pageNumber*size;
            });
            panel.querySelector('[data-pig-page-info]').textContent = count ? ((pageNumber-1)*size+1)+'–'+Math.min(pageNumber*size,count)+' sur '+count+' lot(s)' : '0 lot';
            panel.querySelector('[data-pig-page-label]').textContent = 'Page '+pageNumber+' / '+pages;
            panel.querySelector('[data-pig-page="-1"]').disabled = pageNumber === 1;
            panel.querySelector('[data-pig-page="1"]').disabled = pageNumber === pages;
            panel.querySelectorAll('[data-pig-sort]').forEach(button => button.closest('th').setAttribute('aria-sort',button.dataset.pigSort === sortKey ? (sortDirection === 1 ? 'ascending' : 'descending') : 'none'));
            panel.querySelectorAll('[data-pig-summary-filter]').forEach(button => {
                button.querySelector('.ui-kpi__value').textContent = totals[button.dataset.pigSummaryFilter || 'total'];
            });
            panel.querySelectorAll('[data-pig-export]').forEach(link => {
                const destination = new URL(link.href);
                destination.search = new URLSearchParams({export:link.dataset.pigExport,filter:filter.value,metric,q:search.value.trim(),sort:sortKey,direction:sortDirection===1?'asc':'desc'}).toString();
                link.href = destination.href;
            });
            panel.dataset.activeFilter = filter.value;
            const title = panel.querySelector('[data-pig-group-title]');
            if(title)title.textContent = ['A','B','C'].includes(filter.value) ? 'Groupe ' + filter.value + ' · Détails des lots' : filter.value === 'pending' ? 'Passages de groupe à vérifier' : filter.value === 'unknown' ? 'Lots à renseigner' : 'Détails des lots';
            panel.querySelector('[data-pig-count]').textContent = count + ' lot(s) sur ' + cards.length;
            panel.querySelector('[data-pig-empty]').hidden = count > 0 || cards.length === 0;
            panel.querySelector('[data-pig-filter-update]').hidden = !cards.some(card => !matched.includes(card) && changed.has(card.dataset.pigLot));
            const active = Number(Boolean(query)) + Number(Boolean(filter.value)) + Number(Boolean(metric));
            panel.querySelector('[data-pig-reset]').hidden = active === 0;
            panel.querySelector('[data-pig-filter-count]').textContent = active ? active + ' filtre(s) actif(s)' : 'Tous les lots du périmètre';
            panel.querySelectorAll('[data-pig-summary-filter]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.pigSummaryFilter === metric)));
            panel.querySelectorAll('[data-pig-group-button]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.pigGroupButton === filter.value)));
            try { sessionStorage.setItem(key, JSON.stringify({search:search.value,filter:filter.value,metric})); } catch (_) {}
            if (updateUrl) {
                const next = new URL(window.location.href);
                for (const [name, value] of [['filter',filter.value],['q',search.value.trim()],['metric',metric]]) {
                    if (value) next.searchParams.set(name,value); else next.searchParams.delete(name);
                }
                window.history.replaceState(window.history.state, '', next);
            }
        }
        search.addEventListener('input', () => { pageNumber = 1; render(true); });
        filter.addEventListener('change', () => { pageNumber = 1; metric = ''; render(true); });
        function selectFilter(value) { pageNumber = 1; metric = ''; filter.value = value; search.value = ''; render(true); }
        panel.querySelectorAll('[data-pig-group-button]').forEach(button => button.addEventListener('click', () => selectFilter(button.dataset.pigGroupButton)));
        panel.querySelectorAll('[data-pig-summary-filter]').forEach(button => button.addEventListener('click', () => { pageNumber = 1; metric = button.dataset.pigSummaryFilter; render(true); }));
        pageSize.addEventListener('change', () => { pageNumber = 1; render(); });
        panel.querySelectorAll('[data-pig-page]').forEach(button => button.addEventListener('click', () => { pageNumber += Number(button.dataset.pigPage); render(); }));
        panel.querySelectorAll('[data-pig-sort]').forEach(button => button.addEventListener('click', () => {
            sortDirection = sortKey === button.dataset.pigSort ? -sortDirection : 1;
            sortKey = button.dataset.pigSort; pageNumber = 1; render();
        }));
        function resetFilters() { selectFilter(''); search.focus(); }
        panel.querySelector('[data-pig-reset]').addEventListener('click', resetFilters);
        panel.querySelector('[data-pig-reveal-results]').addEventListener('click', resetFilters);
        panel.querySelector('[data-pig-show-pending]')?.addEventListener('click', () => { selectFilter('pending'); filter.focus(); });
        panel.querySelector('[data-pig-show-unknown]')?.addEventListener('click', () => { selectFilter('unknown'); filter.focus(); });
        render(true);
        if(panel.dataset.restoreDialog === '1')window.setTimeout(() => { if(panel.isConnected)panel.querySelector('[data-pig-consult]')?.click(); },0);
        panel.querySelectorAll('[data-pig-form]').forEach(form => {
            function sync() {
                const site = form.elements.site_id;
                const birth = form.elements.born_on;
                const origin = form.elements.origin;
                if (site) {
                    ['facility_id','mother_batch_id'].forEach(name => {
                        const field = form.elements[name];
                        Array.from(field.options).forEach(option => { if (option.value) { option.disabled = option.dataset.site !== site.value; option.hidden = option.disabled; } });
                        if (field.selectedOptions[0]?.disabled) field.value = '';
                    });
                }
                if (origin) {
                    const isBirth = origin.value === 'birth';
                    form.querySelector('[data-pig-mother]').hidden = !isBirth;
                    form.elements.mother_batch_id.disabled = !isBirth;
                    form.elements.mother_batch_id.required = isBirth;
                }
                const preview = form.querySelector('[data-pig-age-preview]');
                if (preview && birth) {
                    preview.textContent = 'Le groupe est calculé selon la date de naissance.';
                    if (/^\d{4}-\d{2}-\d{2}$/.test(birth.value)) {
                        const [year,month,day] = birth.value.split('-').map(Number);
                        const today = new Date(birth.max + 'T00:00:00');
                        function threshold(months) { const last = new Date(year,month-1+months+1,0).getDate(); return new Date(year,month-1+months,Math.min(day,last)); }
                        const born = new Date(year,month-1,day);
                        preview.textContent = born > today ? 'La naissance ne peut pas être dans le futur.' : 'Groupe initial : ' + (today < threshold(2) ? 'A' : today < threshold(3) ? 'B' : 'C') + '. Vérifiez ce classement sur le terrain.';
                    }
                }
            }
            form.addEventListener('reset', () => queueMicrotask(() => {
                const error = form.querySelector('[data-feed-error]');
                if (error) error.hidden = true;
                sync();
            }));
            form.addEventListener('input',sync);
            form.addEventListener('change',sync);
            sync();
        });
    };
})();
