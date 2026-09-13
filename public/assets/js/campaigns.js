(function () {
    'use strict';
    window.initCampaignDirectory = function (root) {
        var panel = root.matches && root.matches('[data-campaign-directory]') ? root : root.querySelector('[data-campaign-directory]');
        if (!panel || panel.dataset.ready) return;
        panel.dataset.ready = 'true';
        var rows = Array.from(panel.querySelectorAll('[data-campaign-row]'));
        var fields = Array.from(panel.querySelectorAll('[data-campaign-filter]'));
        var drawer = panel.querySelector('[data-campaign-drawer]');
        var key = 'dagril-campaigns-' + panel.dataset.context;
        var page = 1, pageSize = 10, returnFocus = null, saving = false;
        var normalize = function (value) { return String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); };
        var number = function (value) { return value.toLocaleString('fr-FR', { maximumFractionDigits: 2 }); };
        try {
            var saved = JSON.parse(sessionStorage.getItem(key) || '{}');
            fields.forEach(function (field) { field.value = saved[field.dataset.campaignFilter] || ''; });
            page = Math.max(1, Number(saved.page) || 1);
        } catch (error) { /* Storage can be unavailable in private browsing. */ }
        function render() {
            var filters = {};
            fields.forEach(function (field) { filters[field.dataset.campaignFilter] = field.value; });
            var filtered = rows.filter(function (row) {
                return (!filters.search || normalize(row.dataset.search).includes(normalize(filters.search))) &&
                    ['site', 'season', 'status'].every(function (name) { return !filters[name] || row.dataset[name] === filters[name]; });
            });
            var pages = Math.max(1, Math.ceil(filtered.length / pageSize));
            page = Math.min(page, pages);
            rows.forEach(function (row) { row.hidden = true; });
            filtered.slice((page - 1) * pageSize, page * pageSize).forEach(function (row) { row.hidden = false; });
            panel.querySelector('[data-campaign-empty]').hidden = filtered.length > 0;
            var totals = filtered.reduce(function (sum, row) {
                ['area', 'target', 'actual'].forEach(function (name) { sum[name] += Number(row.dataset[name]) || 0; });
                if (['in_progress', 'harvesting'].includes(row.dataset.status)) sum.active++;
                return sum;
            }, { area: 0, target: 0, actual: 0, active: 0 });
            Object.keys(totals).forEach(function (name) { panel.querySelector('[data-campaign-kpi="' + name + '"]').textContent = number(totals[name]); });
            panel.querySelector('[data-campaign-kpi="progress"]').textContent = totals.target > 0 ? number(totals.actual / totals.target * 100) + ' % de l’objectif · tonnes' : 'Tonnes · aucun objectif défini';
            panel.querySelector('[data-campaign-count]').textContent = filtered.length + ' campagne(s) sur ' + rows.length;
            panel.querySelector('[data-campaign-page-label]').textContent = page + ' / ' + pages;
            panel.querySelector('[data-campaign-page="-1"]').disabled = page <= 1;
            panel.querySelector('[data-campaign-page="1"]').disabled = page >= pages;
            try { sessionStorage.setItem(key, JSON.stringify(Object.assign(filters, { page: page }))); } catch (error) {}
        }
        function open(id, trigger) {
            var template = Array.from(panel.querySelectorAll('template[data-campaign-detail]')).find(function (item) { return item.dataset.campaignDetail === id; });
            if (!template) return;
            returnFocus = trigger || panel.querySelector('[data-workspace-modal-open="campaignModal"]');
            drawer.replaceChildren(template.content.cloneNode(true));
            drawer.dataset.id=id;
            drawer.showModal();
        }
        drawer.addEventListener('cancel',function(event){if(saving||drawer.querySelector('.swal2-modal'))event.preventDefault();});
        drawer.addEventListener('close', function () { if (returnFocus && returnFocus.isConnected) returnFocus.focus({ preventScroll: true }); });
        fields.forEach(function (field) { field.addEventListener(field.tagName === 'INPUT' ? 'input' : 'change', function () { page = 1; render(); }); });
        panel.addEventListener('click', function (event) {
            if(saving)return;
            if(event.target.closest('[data-campaign-edit], [data-campaign-edit-back]')){var editing=!!event.target.closest('[data-campaign-edit]');drawer.querySelector('[data-campaign-edit-form]').hidden=!editing;drawer.querySelector('[data-campaign-summary]').hidden=editing;drawer.querySelector('.campaign-drawer-body').scrollTop=0;if(editing)drawer.querySelector('[name="name"]').focus();else drawer.querySelector('[data-campaign-edit]').focus();}
            var trigger = event.target.closest('[data-campaign-open]');
            if (trigger) open(trigger.dataset.campaignOpen, trigger);
            if (event.target.closest('[data-campaign-close], [data-campaign-plan]')) drawer.close();
            if (event.target === drawer) {
                var bounds = drawer.getBoundingClientRect();
                if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) drawer.close();
            }
            if (event.target.closest('[data-campaign-show-harvests]')) {
                var heading = drawer.querySelector('[data-campaign-harvest-heading]');
                heading.focus({ preventScroll: true });
                heading.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            }
            if (event.target.closest('[data-campaign-reset]')) { fields.forEach(function (field) { field.value = ''; }); page = 1; render(); }
            var pagination = event.target.closest('[data-campaign-page]');
            if (pagination) { page += Number(pagination.dataset.campaignPage); render(); }
        });
        drawer.addEventListener('submit',async function(event){
            var form=event.target.closest('[data-campaign-edit-form]');if(!form)return;event.preventDefault();if(saving||!form.reportValidity())return;
            var error=form.querySelector('[data-campaign-edit-error]');error.hidden=true;
            if(form.elements.end_date.value<form.elements.start_date.value){error.textContent='La date de fin doit être égale ou postérieure à la date de début.';error.hidden=false;form.elements.end_date.focus();return;}
            if(!window.Swal){error.textContent='La confirmation est indisponible. Rechargez la page.';error.hidden=false;return;}
            var confirmation=await Swal.fire({target:drawer,title:'Modifier cette campagne ?',text:form.elements.name.value+' · '+form.elements.status.selectedOptions[0].textContent+'. Les changements et leur motif seront enregistrés.',icon:'question',showCancelButton:true,confirmButtonText:'Enregistrer les modifications',cancelButtonText:'Revoir',heightAuto:false});
            if(!confirmation.isConfirmed)return;saving=true;var submit=form.querySelector('[type="submit"]');submit.disabled=true;var saved=false;
            try{
                var response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
                var result=await response.json();if(!response.ok||!result.ok)throw Error(result.message||'La modification a été refusée.');saved=true;
                var refreshed=await fetch(result.refresh_url,{credentials:'same-origin'});if(!refreshed.ok)throw Error('Actualisation impossible.');
                var doc=new DOMParser().parseFromString(await refreshed.text(),'text/html'),next=doc.querySelector('[data-campaign-directory]');if(!next)throw Error('Réponse inattendue.');
                next.dataset.created=drawer.dataset.id;drawer.close();panel.replaceWith(next);window.initCampaignDirectory(next);
                Swal.fire({target:next.querySelector('[data-campaign-drawer]'),toast:true,position:'top-end',icon:'success',title:'Campagne modifiée',timer:3500,showConfirmButton:false,heightAuto:false});
            }catch(e){error.textContent=saved?'La campagne a été enregistrée, mais le détail n’a pas pu être actualisé. Rechargez la page pour consulter les changements.':e.message;error.hidden=false;error.scrollIntoView({block:'nearest'});}
            finally{saving=false;submit.disabled=saved;}
        });
        render();
        if (panel.dataset.created) open(panel.dataset.created);
    };
})();
