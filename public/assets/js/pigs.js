(function () {
    'use strict';
    let previousDirectory = null;
    const capabilities = {accepts_births:'les naissances', allows_breeding:'la reproduction', allows_slaughter:'l’abattage'};
    // Same calendar-month rule as PigGroupService::entryDate(): clamp to the last day of the month.
    function entryDate(year, month, day, months) {
        const last = new Date(year, month - 1 + months + 1, 0).getDate();
        return new Date(year, month - 1 + months, Math.min(day, last));
    }
    function groupFor(iso, groups, today) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(iso || '')) return null;
        const [year, month, day] = iso.split('-').map(Number);
        let found = null;
        groups.forEach(group => {
            if (group.status === 'inactive' || today < entryDate(year, month, day, Number(group.min))) return;
            if (!found || Number(group.min) > Number(found.min)) found = group;
        });
        return found;
    }
    function activeGroups(groups) {
        const active = groups.filter(group => group.status !== 'inactive').sort((a, b) => Number(a.min) - Number(b.min) || a.code.localeCompare(b.code));
        return active.map((group, index) => ({...group, max: active[index + 1] ? Number(active[index + 1].min) : null}));
    }
    function ageLabel(group) {
        const min = Number(group.min);
        if (group.max === null) return min === 0 ? 'Tous les âges' : 'Dès ' + min + ' mois';
        return min === 0 ? 'Moins de ' + group.max + ' mois' : 'De ' + min + ' à moins de ' + group.max + ' mois';
    }
    // Mirrors PigGroupService::checks(); the server remains the authority.
    function issues(groups) {
        const active = activeGroups(groups);
        const found = [];
        const first = active.find(group => Number(group.min) === 0);
        if (!first) found.push('Un groupe actif doit commencer à 0 mois (dès la naissance).');
        const ages = new Map();
        active.forEach(group => ages.set(Number(group.min), (ages.get(Number(group.min)) || []).concat(group.code)));
        ages.forEach((codes, age) => { if (codes.length > 1) found.push('Les groupes ' + codes.join(' et ') + ' commencent tous à ' + age + ' mois.'); });
        if (first && !Number(first.accepts_births)) found.push('Le groupe ' + first.code + ', qui commence à 0 mois, doit accueillir les naissances.');
        ['allows_breeding', 'allows_slaughter'].forEach(key => { if (!active.some(group => Number(group[key]))) found.push('Au moins un groupe actif doit permettre ' + capabilities[key] + '.'); });
        return found;
    }
    function impact(lots, before, after, today) {
        const result = {lots:0, heads:0};
        lots.forEach(([born, , heads]) => {
            const previous = groupFor(born, before, today), next = groupFor(born, after, today);
            if ((previous && previous.code) !== (next && next.code)) { result.lots++; result.heads += Number(heads) || 0; }
        });
        return result;
    }
    function impactText(result) {
        return result.lots ? result.lots + ' lot(s) (' + result.heads + ' porcs) changeront de groupe attendu et devront être vérifiés sur le terrain.' : 'Aucun lot de votre périmètre ne change de groupe.';
    }
    function initReferential(root) {
        const page = root.matches?.('[data-pig-referential]') ? root : root.querySelector('[data-pig-referential]');
        if (!page || page.dataset.ready) return;
        page.dataset.ready = '1';
        let groups = [], lots = [];
        try { groups = JSON.parse(page.dataset.groups || '[]'); lots = JSON.parse(page.dataset.lots || '[]'); } catch (_) { return; }
        const today = new Date(page.dataset.today + 'T00:00:00');
        const byId = id => groups.find(group => String(group.id) === String(id));
        const replaced = (id, patch) => groups.map(group => String(group.id) === String(id) ? {...group, ...patch} : group);
        page.querySelectorAll('[data-pig-group-form]').forEach(form => {
            const current = byId(form.dataset.groupId);
            function candidate() {
                const values = {code:(form.elements.code.value || '').trim().toUpperCase() || (form.elements.code.dataset.suggestedCode || ''), name:form.elements.name.value.trim(), min:form.elements.min_age_months.value === '' ? NaN : Number(form.elements.min_age_months.value)};
                Object.keys(capabilities).forEach(key => { values[key] = form.elements[key].checked ? 1 : 0; });
                return current ? replaced(current.id, values) : groups.concat([{...values, id:'new', status:'active'}]);
            }
            function render() {
                if (!current && form.elements.code.value !== form.elements.code.value.toUpperCase()) form.elements.code.value = form.elements.code.value.toUpperCase();
                const suggested = form.elements.code.dataset.suggestedCode || '';
                if (!current && suggested) {
                    const typed = form.elements.code.value.trim();
                    const custom = typed !== '' && typed !== suggested;
                    const badge = form.querySelector('[data-pig-code-badge]');
                    badge.textContent = custom ? 'Personnalisé' : 'Automatique';
                    badge.classList.toggle('is-custom', custom);
                    form.querySelector('[data-pig-code-reset]').hidden = !custom;
                    form.querySelector('[data-pig-code-help]').textContent = typed === '' ? 'Laissé vide, le code ' + suggested + ' sera attribué automatiquement.' : custom ? 'Code personnalisé : court et unique (lettres, chiffres ou tirets).' : 'Proposé à la suite des codes existants. Vous pouvez le modifier.';
                }
                const next = candidate();
                const edited = next.find(group => String(group.id) === String(current ? current.id : 'new'));
                const valid = Number.isInteger(edited.min) && edited.min >= 0 && edited.min <= Number(form.elements.min_age_months.max);
                const range = form.querySelector('[data-pig-group-range]');
                const timeline = form.querySelector('[data-pig-preview-timeline]');
                const list = valid ? activeGroups(next) : [];
                const placed = list.find(group => String(group.id) === String(edited.id));
                range.textContent = form.elements.min_age_months.value === '' ? 'Âge à partir duquel les porcs rejoignent ce groupe.' : !valid ? 'Saisissez un nombre entier de mois, de 0 à ' + form.elements.min_age_months.max + '.' : edited.status === 'inactive' ? 'Groupe désactivé : il ne classe aucun lot.' : 'Ce groupe couvrira : ' + ageLabel(placed).toLocaleLowerCase('fr') + '.';
                timeline.replaceChildren(...list.map(group => {
                    const item = document.createElement('li');
                    item.className = 'pig-timeline__step' + (String(group.id) === String(edited.id) ? ' is-edited' : '');
                    item.style.setProperty('--pig-span', Math.max(1, (group.max === null ? Number(list[list.length - 1].min) + 3 : group.max) - Number(group.min)));
                    const bar = document.createElement('span'); bar.className = 'pig-timeline__bar';
                    const code = document.createElement('span'); code.className = 'pig-timeline__code'; code.textContent = group.code || '?'; bar.append(code);
                    const name = document.createElement('strong'); name.textContent = group.name || 'Sans libellé';
                    const age = document.createElement('small'); age.textContent = ageLabel(group);
                    item.append(bar, name, age);
                    return item;
                }));
                const problems = valid ? issues(next) : [];
                if (!current && edited.code && groups.some(group => group.code === edited.code)) problems.unshift('Le code ' + edited.code + ' existe déjà' + (groups.find(group => group.code === edited.code).status === 'inactive' ? ' parmi les groupes désactivés : réactivez-le plutôt.' : '.'));
                form.querySelector('[data-pig-preview-issues]').replaceChildren(...problems.map(text => { const item = document.createElement('li'); item.textContent = text; return item; }));
                const result = valid ? impact(lots, groups, next, today) : {lots:0, heads:0};
                const impactNode = form.querySelector('[data-pig-preview-impact]');
                impactNode.textContent = valid ? impactText(result) : '';
                impactNode.classList.toggle('is-warning', result.lots > 0);
                form.dataset.confirm = (current ? 'Enregistrer les modifications du groupe ' + current.code : 'Ajouter le groupe ' + (edited.code || '')) + ' ? ' + (valid ? impactText(result) : '');
            }
            form.querySelector('[data-pig-code-reset]')?.addEventListener('click', () => {
                form.elements.code.value = form.elements.code.dataset.suggestedCode;
                render();
                form.elements.code.focus();
            });
            form.addEventListener('input', render);
            form.addEventListener('change', render);
            form.addEventListener('reset', () => queueMicrotask(render));
            render();
        });
        page.querySelectorAll('[data-pig-group-status],[data-pig-group-delete]').forEach(form => {
            const group = byId(form.dataset.groupId);
            if (!group) return;
            const removing = form.matches('[data-pig-group-delete]');
            const next = removing ? groups.filter(item => item !== group) : replaced(group.id, {status:group.status === 'active' ? 'inactive' : 'active'});
            const problems = removing && group.status === 'inactive' ? [] : issues(next);
            const button = form.querySelector('button[type="submit"]');
            const hint = form.closest('footer')?.querySelector('[data-pig-status-hint]');
            if (problems.length) {
                button.disabled = true;
                button.title = problems[0];
                if (hint && !hint.textContent) hint.textContent = (removing ? 'Suppression' : 'Désactivation') + ' impossible : ' + problems[0].charAt(0).toLocaleLowerCase('fr') + problems[0].slice(1);
                return;
            }
            const result = impact(lots, groups, next, today);
            form.dataset.confirm = form.dataset.confirm + ' ' + impactText(result);
            if (hint && !removing && group.status === 'active' && result.lots && !hint.textContent) hint.textContent = 'Si vous le désactivez, ' + result.lots + ' lot(s) seront à reclasser.';
        });
    }
    window.initPigs = function (root) {
        initReferential(root);
        const panel = root.matches?.('[data-pig-directory]:not([data-pig-referential])') ? root : root.querySelector('[data-pig-directory]:not([data-pig-referential])');
        if (!panel || panel.dataset.ready) return;
        panel.dataset.ready = '1';
        const search = panel.querySelector('[data-pig-search]');
        const filter = panel.querySelector('[data-pig-filter]');
        if (!search || !filter) return;
        const cards = Array.from(panel.querySelectorAll('[data-pig-lot]'));
        let pigGroups = [];
        try { pigGroups = JSON.parse(panel.dataset.pigGroups || '[]'); } catch (_) {}
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
            if(title)title.textContent = filter.selectedOptions[0]?.hasAttribute('data-group-option') ? 'Groupe ' + filter.value + ' · Détails des lots' : filter.value === 'pending' ? 'Passages de groupe à vérifier' : filter.value === 'unknown' ? 'Lots à renseigner' : 'Détails des lots';
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
                        const today = new Date(birth.max + 'T00:00:00');
                        const group = groupFor(birth.value, pigGroups, today);
                        const isBirth = origin && origin.value === 'birth';
                        preview.textContent = new Date(birth.value + 'T00:00:00') > today ? 'La naissance ne peut pas être dans le futur.' : !group ? 'Aucun groupe actif ne couvre cet âge.' : 'Groupe initial : ' + group.code + ' · ' + group.name + '. ' + (isBirth && !Number(group.births) ? 'Ce groupe n’accueille pas les naissances : vérifiez la date.' : 'Vérifiez ce classement sur le terrain.');
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
