(function () {
    'use strict';
    const number = value => value === '' || value === null || value === undefined ? NaN : Number(String(value).replace(',', '.'));
    const kg = value => value.toLocaleString('fr-FR', {minimumFractionDigits: 3, maximumFractionDigits: 3});
    const pct = value => value.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    // Shared weighing input: net weight, or gross minus a tare; optional live gap with the farm weight.
    window.initWeighing = function (root) {
        const boxes = Array.from((root || document).querySelectorAll('[data-weighing]'));
        if (root && root.matches && root.matches('[data-weighing]')) boxes.push(root);
        boxes.forEach(box => {
            if (box.dataset.weighingReady) return;
            box.dataset.weighingReady = '1';
            const form = box.closest('form');
            const field = name => box.querySelector('[name="' + name + '"]');
            const optional = box.hasAttribute('data-weighing-optional');
            function toggle(label, visible, required) {
                label.hidden = !visible;
                const input = label.querySelector('input,textarea');
                input.disabled = !visible;
                input.required = visible && required;
            }
            function sync() {
                const gross = box.querySelector('[name="weighing_mode"][value="gross"]').checked;
                toggle(box.querySelector('[data-weighing-net]'), !gross, !optional);
                // An optional weighing becomes complete once a gross weight is entered.
                const grossRequired = !optional || field('gross_kg').value !== '' || field('tare_kg').value !== '';
                box.querySelectorAll('[data-weighing-gross]').forEach(label => toggle(label, gross, grossRequired && label.querySelector('[name="tare_note"]') === null));
                const net = gross ? number(field('gross_kg').value) - number(field('tare_kg').value) : number(field('net_kg').value);
                const result = box.querySelector('[data-weighing-result]');
                const tare = field('tare_kg');
                tare.setCustomValidity(gross && net <= 0 && !Number.isNaN(net) ? 'La tare doit être inférieure au poids brut.' : '');
                result.hidden = !gross || Number.isNaN(net);
                result.classList.toggle('is-invalid', net <= 0);
                result.textContent = net > 0 ? 'Poids net calculé : ' + kg(Math.round(net * 1000) / 1000) + ' kg' : 'La tare doit être inférieure au poids brut.';
                // Goods counted as well as weighed: missing pieces also need a reason.
                const pieces = field('received_pieces'), piecesNote = box.querySelector('[data-weighing-pieces-note]');
                const sentPieces = pieces ? Number(pieces.dataset.sent) : 0, received = pieces && pieces.value !== '' ? Number(pieces.value) : NaN;
                const missing = pieces && !Number.isNaN(received) ? sentPieces - received : 0;
                if (pieces) {
                    const sentText = sentPieces.toLocaleString('fr-FR');
                    pieces.setCustomValidity(missing < 0 ? 'Plus que l’envoi de la ferme (' + sentText + ') : vérifiez le comptage.' : '');
                    piecesNote.textContent = Number.isNaN(received) ? 'Envoi de la ferme : ' + sentText + '.' : (missing > 0 ? missing.toLocaleString('fr-FR') + ' manquant(s) sur ' + sentText + ' : précisez le motif.' : (missing < 0 ? 'Plus que l’envoi de la ferme (' + sentText + ') : vérifiez le comptage.' : 'Compte conforme à l’envoi.'));
                    piecesNote.classList.toggle('is-warning', missing !== 0);
                }
                const gap = box.querySelector('[data-weighing-gap]');
                if (!gap) return;
                const sent = number(box.dataset.weighingSent);
                const tolerance = box.dataset.weighingTolerance === '' ? null : number(box.dataset.weighingTolerance);
                const value = gap.querySelector('[data-weighing-gap-value]');
                const status = gap.querySelector('[data-weighing-gap-status]');
                const reason = box.querySelector('[data-weighing-reason]');
                if (!(net > 0)) {
                    value.textContent = '—';
                    gap.classList.remove('is-within', 'is-exceeded');
                    status.textContent = tolerance === null ? 'Aucune tolérance paramétrée pour cette espèce : tout écart devra être justifié.' : 'Tolérance : ' + pct(tolerance) + ' %.';
                    toggle(reason, missing > 0, missing > 0);
                    return;
                }
                const loss = Math.round((sent - net) * 1000) / 1000;
                const lossPct = sent > 0 ? loss / sent * 100 : 0;
                const exceeded = tolerance === null ? Math.abs(loss) > 0 : Math.abs(lossPct) > tolerance + 1e-9;
                value.textContent = Math.abs(loss) < 0.0005 ? 'Aucun écart' : (loss > 0 ? 'Perte de ' : 'Excédent de ') + kg(Math.abs(loss)) + ' kg (' + pct(Math.abs(lossPct)) + ' %)';
                gap.classList.toggle('is-exceeded', exceeded);
                gap.classList.toggle('is-within', !exceeded);
                status.textContent = !exceeded ? 'Dans la tolérance' + (tolerance === null ? '.' : ' de ' + pct(tolerance) + ' %.') : (tolerance === null ? 'Aucune tolérance paramétrée' : 'Au-delà de la tolérance de ' + pct(tolerance) + ' %') + ' : précisez le motif. L’écart devra être validé lors du contrôle.';
                toggle(reason, exceeded || missing > 0, true);
            }
            box.addEventListener('input', sync);
            box.addEventListener('change', sync);
            if (form) form.addEventListener('reset', () => queueMicrotask(sync));
            sync();
        });
    };
})();
