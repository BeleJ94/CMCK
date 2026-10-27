(function () {
    'use strict';
    // Row menus are native popovers placed under their toggle, reachable with the arrow keys.
    function initMenus(scope) {
        scope.querySelectorAll('[data-row-menu]').forEach(menu => {
            if (menu.dataset.ready) return;
            menu.dataset.ready = '1';
            const toggle = document.getElementById(menu.dataset.toggleId);
            if (!toggle) return;
            const place = () => {
                const rect = toggle.getBoundingClientRect(), width = menu.offsetWidth || 220, height = menu.offsetHeight || 120;
                if (rect.bottom < 0 || rect.top > innerHeight) { menu.hidePopover(); return; }
                menu.style.left = Math.max(8, Math.min(innerWidth - width - 8, rect.right - width)) + 'px';
                menu.style.top = (rect.bottom + height + 8 <= innerHeight ? rect.bottom + 6 : Math.max(8, rect.top - height - 6)) + 'px';
            };
            menu.rowPlace = place;
            menu.addEventListener('toggle', event => {
                const open = event.newState === 'open';
                toggle.setAttribute('aria-expanded', String(open));
                toggle.classList.toggle('is-open', open);
                if (open) { place(); const first = menu.querySelector('[role="menuitem"]'); if (first) first.focus({preventScroll: true}); }
            });
            menu.addEventListener('click', event => {
                const item = event.target.closest('[role="menuitem"]');
                if (!item) return;
                if (item.hasAttribute('data-workspace-modal-open')) item.dataset.workspaceReturnFocus = toggle.id;
                menu.hidePopover();
            });
            menu.addEventListener('keydown', event => {
                if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                const items = Array.from(menu.querySelectorAll('[role="menuitem"]'));
                let index = items.indexOf(document.activeElement);
                index = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                items[index].focus();
            });
        });
    }
    // A fixed popover follows its toggle while the page scrolls, and closes once the toggle leaves the screen.
    const follow = () => document.querySelectorAll('[data-row-menu]').forEach(menu => { if (menu.rowPlace && menu.matches(':popover-open')) menu.rowPlace(); });
    window.addEventListener('scroll', follow, {passive: true, capture: true});
    window.addEventListener('resize', follow, {passive: true});
    // One preview modal serves every transfer note: the trigger tells which BTR to load.
    function initBtrPreview(scope) {
        // Only the shared modal holds the frame; other elements may carry data-btr-preview.
        const modal = Array.from(scope.querySelectorAll('[data-btr-preview]')).find(element => element.querySelector('[data-btr-frame]'));
        if (!modal || modal.dataset.ready) return;
        modal.dataset.ready = '1';
        const frame = modal.querySelector('[data-btr-frame]'), loading = modal.querySelector('[data-btr-loading]');
        frame.addEventListener('load', () => {
            if (!frame.getAttribute('src')) return;
            loading.hidden = true;
            frame.classList.add('is-ready');
            // Escape also closes the preview when the focus is inside the document.
            try { frame.contentDocument.addEventListener('keydown', event => { if (event.key === 'Escape') { event.preventDefault(); modal.querySelector('[data-workspace-modal-close]').click(); } }); } catch (_) {}
        });
        modal.addEventListener('workspace:opened', event => {
            const trigger = event.detail && event.detail.trigger;
            const url = trigger ? trigger.dataset.btrUrl : '';
            if (!url) return;
            modal.querySelector('[data-btr-title]').textContent = trigger.dataset.btrTitle || 'Bon de transfert';
            modal.querySelector('[data-btr-tab]').href = url;
            modal.querySelector('[data-btr-pdf]').href = url + (url.includes('?') ? '&' : '?') + 'export=pdf';
            if (frame.getAttribute('src') !== url) { loading.hidden = false; frame.classList.remove('is-ready'); frame.src = url; }
        });
        modal.querySelector('[data-btr-print]').addEventListener('click', () => {
            if (!frame.contentWindow || !frame.classList.contains('is-ready')) return;
            frame.contentWindow.focus();
            frame.contentWindow.print();
        });
    }
    // A trigger can open a drawer with one field already chosen: data-preselect-field and data-preselect-value.
    function initPreselect(scope) {
        scope.querySelectorAll('.workspace-entity-modal').forEach(modal => {
            if (modal.dataset.preselectReady) return;
            modal.dataset.preselectReady = '1';
            modal.addEventListener('workspace:opened', event => {
                const trigger = event.detail && event.detail.trigger;
                const name = trigger ? trigger.dataset.preselectField : '';
                const field = name ? modal.querySelector('[name="' + name + '"]') : null;
                if (!field) return;
                field.value = trigger.dataset.preselectValue || '';
                field.dispatchEvent(new Event('change', {bubbles: true}));
            });
        });
    }
    window.initRowActions = function (root) {
        const scope = root || document;
        initMenus(scope);
        initBtrPreview(scope);
        initPreselect(scope);
    };
})();
