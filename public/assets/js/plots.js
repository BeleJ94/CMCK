(function(){
    'use strict';
    window.initPlotDirectory=function(root){
        var panel=root.matches&&root.matches('[data-plot-directory]')?root:root.querySelector('[data-plot-directory]');
        if(!panel||panel.dataset.ready)return;panel.dataset.ready='true';
        var cards=Array.from(panel.querySelectorAll('[data-plot-card]')),fields=Array.from(panel.querySelectorAll('[data-plot-filter]')),drawer=panel.querySelector('[data-plot-drawer]'),page=1,size=9,focus=null,key='dagril-plots-'+panel.dataset.context;
        function q(s){return panel.querySelector(s);}
        function norm(s){return String(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();}
        try{var saved=JSON.parse(sessionStorage.getItem(key)||'{}');fields.forEach(f=>f.value=saved[f.dataset.plotFilter]||'');page=Math.max(1,Number(saved.page)||1);}catch(e){}
        function render(){var f={};fields.forEach(el=>f[el.dataset.plotFilter]=el.value);var filtered=cards.filter(c=>(!f.search||norm(c.dataset.search).includes(norm(f.search)))&&['site','status','current'].every(k=>!f[k]||c.dataset[k]===f[k]));var pages=Math.max(1,Math.ceil(filtered.length/size));page=Math.min(page,pages);cards.forEach(c=>c.hidden=true);filtered.slice((page-1)*size,page*size).forEach(c=>c.hidden=false);
            q('[data-plot-empty]').hidden=filtered.length>0;q('[data-plot-count]').textContent=filtered.length+' parcelle(s) sur '+cards.length;q('[data-plot-page-label]').textContent=page+' / '+pages;q('[data-plot-page="-1"]').disabled=page===1;q('[data-plot-page="1"]').disabled=page===pages;try{sessionStorage.setItem(key,JSON.stringify(Object.assign(f,{page:page})));}catch(e){}
        }
        fields.forEach(el=>el.addEventListener(el.tagName==='INPUT'?'input':'change',()=>{page=1;render();}));
        panel.addEventListener('click',function(event){var t=event.target.closest('button');if(!t)return;if(t.matches('[data-plot-open]')){var template=Array.from(panel.querySelectorAll('[data-plot-detail]')).find(el=>el.dataset.plotDetail===t.dataset.plotOpen);if(!template)return;focus=t;drawer.replaceChildren(template.content.cloneNode(true));drawer.showModal();}if(t.matches('[data-plot-close],[data-plot-plan]'))drawer.close();if(t.matches('[data-plot-reset]')){fields.forEach(f=>f.value='');page=1;render();}if(t.matches('[data-plot-page]')){page+=Number(t.dataset.plotPage);render();}});
        drawer.addEventListener('close',()=>{if(focus&&focus.isConnected)focus.focus({preventScroll:true});});render();
    };
})();
