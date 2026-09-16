(function () {
    'use strict';
    window.initFinishedStocks = function (root) {
        const panel = root.matches?.('[data-finished-directory]') ? root : root.querySelector('[data-finished-directory]');
        if (!panel || panel.dataset.ready) return;
        panel.dataset.ready = '1';
        const filters = Array.from(panel.querySelectorAll('[data-format-filter]'));
        const cards = Array.from(panel.querySelectorAll('[data-format-card]'));
        function filterFormats() {
            let visible = 0;
            cards.forEach(card => {
                card.hidden = !filters.every(filter => !filter.value || card.dataset[filter.dataset.formatFilter] === filter.value);
                if (!card.hidden) visible++;
            });
            panel.querySelector('[data-format-count]').textContent = visible + ' format' + (visible > 1 ? 's' : '') + ' affiché' + (visible > 1 ? 's' : '') + ' sur ' + cards.length;
            panel.querySelector('[data-format-empty]').hidden = visible > 0;
        }
        filters.forEach(filter => filter.addEventListener('change', filterFormats));
        panel.querySelector('[data-format-reset]').addEventListener('click', () => { filters.forEach(filter => filter.value = ''); filterFormats(); });
        filterFormats();
        panel.querySelectorAll('[data-finished-tab]').forEach(button => button.addEventListener('click', () => {
            panel.querySelectorAll('[data-finished-space]').forEach(space => { space.hidden = space.dataset.finishedSpace !== button.dataset.finishedTab; });
            panel.querySelectorAll('[data-finished-tab]').forEach(tab => tab.setAttribute('aria-pressed', String(tab === button)));
        }));
    };
})();
