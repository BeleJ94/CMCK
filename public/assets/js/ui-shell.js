(function () {
    'use strict';
    function closeAttention(restoreFocus) {
        const shell=document.querySelector('[data-ui-attention]');
        if(!shell)return;
        const toggle=shell.querySelector('[data-ui-attention-toggle]');
        const panel=shell.querySelector('[data-ui-attention-panel]');
        const wasOpen=!panel.hidden;
        panel.hidden=true;toggle.setAttribute('aria-expanded','false');
        if(wasOpen&&restoreFocus)toggle.focus();
    }
    window.initUIShell=function(root) {
        root.querySelectorAll('[data-ui-attention-panel]').forEach(panel=>{panel.hidden=true;});
        root.querySelectorAll('[data-ui-attention-toggle]').forEach(toggle=>toggle.setAttribute('aria-expanded','false'));
    };
    document.addEventListener('click',function(event){
        const toggle=event.target.closest('[data-ui-attention-toggle]');
        if(toggle){
            const shell=toggle.closest('[data-ui-attention]');const panel=shell.querySelector('[data-ui-attention-panel]');
            const open=panel.hidden;closeAttention(false);panel.hidden=!open;toggle.setAttribute('aria-expanded',String(open));
            return;
        }
        if(event.target.closest('[data-ui-attention-close]')){closeAttention(true);return;}
        if(!event.target.closest('[data-ui-attention-panel]')||event.target.closest('[data-workspace-modal-open]'))closeAttention(false);
    });
    document.addEventListener('keydown',function(event){
        const shell=document.querySelector('[data-ui-attention]');const panel=shell?.querySelector('[data-ui-attention-panel]');
        if(!panel||panel.hidden)return;
        if(event.key==='Escape'){event.preventDefault();closeAttention(true);return;}
        if(event.key==='ArrowDown'||event.key==='ArrowUp'){
            if(!shell.contains(event.target))return;
            const links=Array.from(panel.querySelectorAll('a,button'));if(!links.length)return;
            event.preventDefault();const index=links.indexOf(document.activeElement);const next=event.key==='ArrowDown'?index+1:index<0?links.length-1:index-1;
            links[(next+links.length)%links.length].focus();
        }
    });
    document.addEventListener('focusin',function(event){
        if(!event.target.closest('[data-ui-attention]'))closeAttention(false);
    });
    document.addEventListener('change',function(event){
        const select=event.target.closest('[data-site-context-select]');if(!select)return;
        const form=select.form;if(!form)return;
        if(form.elements._return_to)form.elements._return_to.value=location.pathname+location.search+location.hash;
        if(form.requestSubmit)form.requestSubmit();else HTMLFormElement.prototype.submit.call(form);
    });
    function clearFieldError(field) {
        const error=field.parentElement?.querySelector('[data-ui-field-error]');
        if(error){
            const ids=(field.getAttribute('aria-describedby')||'').split(/\s+/).filter(id=>id&&id!==error.id);
            if(ids.length)field.setAttribute('aria-describedby',ids.join(' '));else field.removeAttribute('aria-describedby');
            error.remove();field.removeAttribute('aria-invalid');
        }
    }
    let errorId=0;
    document.addEventListener('invalid',function(event){
        const field=event.target;
        if(!field.matches('input,select,textarea')||!field.closest('.app-shell')||field.type==='hidden')return;
        clearFieldError(field);field.setAttribute('aria-invalid','true');
        const error=document.createElement('span');error.className='ui-field-error';error.dataset.uiFieldError='';error.id='ui-field-error-'+(++errorId);
        const validity=field.validity;
        error.textContent=validity.valueMissing?'Renseignez ce champ.':validity.rangeOverflow?'La valeur dépasse le maximum autorisé.':validity.rangeUnderflow?'La valeur est inférieure au minimum autorisé.':field.validationMessage||'Vérifiez cette valeur.';
        const label=field.closest('label');if(label)label.appendChild(error);else field.after(error);
        field.setAttribute('aria-describedby',((field.getAttribute('aria-describedby')||'')+' '+error.id).trim());
    },true);
    ['input','change'].forEach(type=>document.addEventListener(type,function(event){
        const field=event.target;if(field.matches('input,select,textarea')&&field.validity.valid)clearFieldError(field);
    }));
    document.addEventListener('reset',function(event){
        queueMicrotask(()=>event.target.querySelectorAll('[aria-invalid="true"]').forEach(clearFieldError));
    });
})();
