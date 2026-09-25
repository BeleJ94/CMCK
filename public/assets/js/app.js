(function () {
    var toggle = document.querySelector('[data-sidebar-toggle]');
    var compactToggle = document.querySelector('[data-sidebar-compact]');
    var menuSearch = document.querySelector('[data-menu-search]');
    var closeTargets = document.querySelectorAll('[data-sidebar-close], .sidebar-nav a');
    var activeDetail = null;
    var detailReturnFocus = null;
    var menuSearchSnapshot = null;

    if (window.localStorage && window.localStorage.getItem('dagrilSidebarCompact') === '1') {
        document.body.classList.add('sidebar-compact');
    }

    function syncCompactToggle() {
        if (compactToggle) {
            compactToggle.setAttribute('aria-pressed', document.body.classList.contains('sidebar-compact') ? 'true' : 'false');
        }
    }

    syncCompactToggle();

    if (toggle) {
        toggle.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-open');
        });
    }

    if (compactToggle) {
        compactToggle.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-compact');
            syncCompactToggle();

            if (window.localStorage) {
                window.localStorage.setItem('dagrilSidebarCompact', document.body.classList.contains('sidebar-compact') ? '1' : '0');
            }
        });
    }

    if (menuSearch) {
        menuSearch.addEventListener('input', function () {
            var query = menuSearch.value.trim().toLowerCase();
            var sections = Array.prototype.slice.call(document.querySelectorAll('[data-menu-section]'));

            if (query && menuSearchSnapshot === null) {
                menuSearchSnapshot = sections.map(function (section) {
                    return section.querySelector('[data-section-toggle]').getAttribute('aria-expanded');
                });
            }

            sections.forEach(function (section, sectionIndex) {
                var visibleCount = 0;

                section.querySelectorAll('[data-menu-item]').forEach(function (item) {
                    var text = item.getAttribute('data-menu-text') || '';
                    var isVisible = !query || text.indexOf(query) !== -1;
                    item.hidden = !isVisible;

                    if (isVisible) {
                        visibleCount++;
                    }
                });

                section.hidden = visibleCount === 0;
                var sectionToggle = section.querySelector('[data-section-toggle]');
                if (query && visibleCount > 0) {
                    sectionToggle.setAttribute('aria-expanded', 'true');
                } else if (!query && menuSearchSnapshot !== null) {
                    sectionToggle.setAttribute('aria-expanded', menuSearchSnapshot[sectionIndex] || 'false');
                }
            });

            if (!query) menuSearchSnapshot = null;
        });
    }

    closeTargets.forEach(function (target) {
        target.addEventListener('click', function () {
            document.body.classList.remove('sidebar-open');
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.body.classList.remove('sidebar-open');
            closeNotificationModal();
        }
    });

    document.querySelectorAll('[data-validate]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                form.classList.add('was-validated');
                var firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });
    });

    document.querySelectorAll('[data-uppercase]').forEach(function (input) {
        input.addEventListener('input', function () {
            input.value = input.value.toUpperCase();
        });
    });

    document.querySelectorAll('[data-weighing-exit]').forEach(function (form) {
        var grossInput = form.querySelector('[data-poids-brut]');
        var tareInput = form.querySelector('[data-poids-tare]');
        var netInput = form.querySelector('[data-poids-net]');

        function updateNet() {
            var gross = Number(grossInput ? grossInput.value : 0);
            var tare = Number(tareInput ? tareInput.value : 0);
            var net = Math.max(gross - tare, 0);

            if (netInput) {
                netInput.value = net.toLocaleString('fr-FR', { maximumFractionDigits: 3 });
            }
        }

        if (tareInput) {
            tareInput.addEventListener('input', updateNet);
            updateNet();
        }
    });

    document.querySelectorAll('[data-production-form]').forEach(function (form) {
        var batchSelect = form.querySelector('[data-production-batch]');
        var treatedInput = form.querySelector('[data-treated-quantity]');
        var machineInput = form.querySelector('[data-machine-name]');
        var goodInput = form.querySelector('[data-good-quantity]');
        var wasteInput = form.querySelector('[data-waste-quantity]');
        var yieldInput = form.querySelector('[data-yield-rate]');

        function updateBatchInfo() {
            var option = batchSelect ? batchSelect.options[batchSelect.selectedIndex] : null;
            var treated = option ? Number(option.getAttribute('data-quantity') || 0) : 0;
            var machine = option ? option.getAttribute('data-machine') || '' : '';

            if (treatedInput) {
                treatedInput.value = treated ? treated.toLocaleString('fr-FR', { maximumFractionDigits: 3 }) + ' kg' : '';
            }

            if (machineInput) {
                machineInput.value = machine;
            }

            updateYield();
        }

        function updateYield() {
            var option = batchSelect ? batchSelect.options[batchSelect.selectedIndex] : null;
            var treated = option ? Number(option.getAttribute('data-quantity') || 0) : 0;
            var good = Number(goodInput ? goodInput.value : 0);
            var waste = Math.max(treated - good, 0);
            var rate = treated > 0 ? (good / treated) * 100 : 0;

            if (wasteInput) {
                wasteInput.value = treated > 0 ? waste.toFixed(3) : '';
            }

            if (yieldInput) {
                yieldInput.value = rate.toLocaleString('fr-FR', { maximumFractionDigits: 2 }) + ' %';
            }

            if (goodInput) {
                if (treated > 0 && good > treated) {
                    goodInput.setCustomValidity('Le bon produit ne doit pas depasser la quantite traitee.');
                } else {
                    goodInput.setCustomValidity('');
                }
            }
        }

        if (batchSelect) {
            batchSelect.addEventListener('change', updateBatchInfo);
        }

        [goodInput, wasteInput].forEach(function (input) {
            if (input) {
                input.addEventListener('input', updateYield);
            }
        });

        updateBatchInfo();
    });

    document.querySelectorAll('[data-waste-form]').forEach(function (form) {
        var available = Number(form.getAttribute('data-available-stock') || 0);
        var inputQuantity = form.querySelector('[data-waste-input]');
        var outputQuantity = form.querySelector('[data-waste-output]');
        var yieldRate = form.querySelector('[data-waste-yield]');

        function updateWasteYield() {
            var input = Number(inputQuantity ? inputQuantity.value : 0);
            var output = Number(outputQuantity ? outputQuantity.value : 0);
            var rate = input > 0 ? (output / input) * 100 : 0;

            if (yieldRate) {
                yieldRate.value = rate.toLocaleString('fr-FR', { maximumFractionDigits: 2 }) + ' %';
            }

            if (inputQuantity) {
                if (input > available) {
                    inputQuantity.setCustomValidity('La quantite traitee depasse le stock dechets disponible.');
                } else {
                    inputQuantity.setCustomValidity('');
                }
            }

            if (outputQuantity) {
                if (input > 0 && output > input) {
                    outputQuantity.setCustomValidity('L aliment betail produit ne peut pas depasser la quantite traitee.');
                } else {
                    outputQuantity.setCustomValidity('');
                }
            }
        }

        [inputQuantity, outputQuantity].forEach(function (input) {
            if (input) {
                input.addEventListener('input', updateWasteYield);
            }
        });

        updateWasteYield();
    });

    document.querySelectorAll('[data-packaging-form]').forEach(function (form) {
        var batchSelect = form.querySelector('[data-packaging-batch]');
        var formatSelect = form.querySelector('[data-bag-format]');
        var bagsInput = form.querySelector('[data-bags-count]');
        var productInput = form.querySelector('[data-packaging-product]');
        var availableInput = form.querySelector('[data-packaging-available]');
        var totalInput = form.querySelector('[data-packaging-total]');

        function updatePackaging() {
            var batchOption = batchSelect ? batchSelect.options[batchSelect.selectedIndex] : null;
            var formatOption = formatSelect ? formatSelect.options[formatSelect.selectedIndex] : null;
            var available = batchOption ? Number(batchOption.getAttribute('data-available') || 0) : 0;
            var product = batchOption ? batchOption.getAttribute('data-product') || '' : '';
            var formatWeight = formatOption ? Number(formatOption.getAttribute('data-weight') || 0) : 0;
            var bags = Number(bagsInput ? bagsInput.value : 0);
            var total = formatWeight * bags;

            if (productInput) {
                productInput.value = product;
            }

            if (availableInput) {
                availableInput.value = available ? available.toLocaleString('fr-FR', { maximumFractionDigits: 3 }) + ' kg' : '';
            }

            if (totalInput) {
                totalInput.value = total ? total.toLocaleString('fr-FR', { maximumFractionDigits: 3 }) + ' kg' : '';
            }

            if (bagsInput) {
                if (available > 0 && total > available) {
                    bagsInput.setCustomValidity('Le poids total depasse la quantite disponible.');
                } else {
                    bagsInput.setCustomValidity('');
                }
            }
        }

        [batchSelect, formatSelect, bagsInput].forEach(function (input) {
            if (input) {
                input.addEventListener('input', updatePackaging);
                input.addEventListener('change', updatePackaging);
            }
        });

        updatePackaging();
    });

    document.querySelectorAll('[data-distribution-form]').forEach(function (form) {
        var stockSelect = form.querySelector('[data-distribution-stock]');
        var productInput = form.querySelector('[data-distribution-product]');
        var formatInput = form.querySelector('[data-distribution-format]');
        var availableInput = form.querySelector('[data-distribution-available]');
        var bagsInput = form.querySelector('[data-distribution-bags]');
        var totalInput = form.querySelector('[data-distribution-total]');

        function updateDistribution() {
            var option = stockSelect ? stockSelect.options[stockSelect.selectedIndex] : null;
            var product = option ? option.getAttribute('data-product') || '' : '';
            var format = option ? option.getAttribute('data-format') || '' : '';
            var availableBags = option ? Number(option.getAttribute('data-bags') || 0) : 0;
            var availableKg = option ? Number(option.getAttribute('data-kg') || 0) : 0;
            var weight = option ? Number(option.getAttribute('data-weight') || 0) : 0;
            var bags = Number(bagsInput ? bagsInput.value : 0);
            var total = bags * weight;

            if (productInput) {
                productInput.value = product;
            }
            if (formatInput) {
                formatInput.value = format;
            }
            if (availableInput) {
                availableInput.value = availableBags ? availableBags.toLocaleString('fr-FR') + ' sacs / ' + availableKg.toLocaleString('fr-FR', { maximumFractionDigits: 3 }) + ' kg' : '';
            }
            if (totalInput) {
                totalInput.value = total ? total.toLocaleString('fr-FR', { maximumFractionDigits: 3 }) + ' kg' : '';
            }
            if (bagsInput) {
                if (availableBags > 0 && bags > availableBags) {
                    bagsInput.setCustomValidity('Le nombre de sacs depasse le stock disponible.');
                } else {
                    bagsInput.setCustomValidity('');
                }
            }
        }

        [stockSelect, bagsInput].forEach(function (input) {
            if (input) {
                input.addEventListener('input', updateDistribution);
                input.addEventListener('change', updateDistribution);
            }
        });

        updateDistribution();
    });

    document.querySelectorAll('[data-notification-open]').forEach(function (trigger) {
        trigger.dataset.dagrilDetailReady = 'true';
        trigger.addEventListener('click', function () {
            var index = Number(trigger.getAttribute('data-notification-index'));
            var notification = (window.dagrilNotifications || [])[index];

            if (notification) {
                openDetailModal(notificationDetail(notification), trigger);
            }
        });
    });

    document.querySelectorAll('[data-kpi-open]').forEach(function (trigger) {
        trigger.dataset.dagrilDetailReady = 'true';
        trigger.addEventListener('click', function () {
            var index = Number(trigger.getAttribute('data-kpi-index'));
            var kpi = (window.dagrilKpis || [])[index];

            if (kpi) {
                openDetailModal(kpiDetail(kpi), trigger);
            }
        });
    });

    document.querySelectorAll('[data-chart-open]').forEach(function (trigger) {
        trigger.dataset.dagrilDetailReady = 'true';
        trigger.addEventListener('click', function (event) {
            if (event.target.closest('.panel-action') || event.target.closest('.chart-box')) {
                openDetailModal(chartDetail(trigger.getAttribute('data-chart-key')), trigger);
            }
        });
    });

    document.querySelectorAll('[data-modal-close], [data-modal-backdrop]').forEach(function (trigger) {
        trigger.addEventListener('click', closeNotificationModal);
    });

    var pdfButton = document.querySelector('[data-export-pdf]');
    var excelButton = document.querySelector('[data-export-excel]');

    if (pdfButton) {
        pdfButton.addEventListener('click', exportDetailPdf);
    }

    if (excelButton) {
        excelButton.addEventListener('click', exportDetailExcel);
    }

    function openDetailModal(detail, trigger) {
        activeDetail = detail;
        detailReturnFocus = trigger || document.activeElement;

        setText('[data-modal-title]', detail.title || 'Detail');
        setText('[data-modal-message]', detail.summary || '');
        setText('[data-modal-date]', detail.meta || '');
        setText('[data-modal-level]', detail.level || '');
        setText('[data-modal-severity]', detail.kicker || 'Analyse');
        setText('[data-modal-summary-label]', detail.summaryLabel || 'Resume');
        setText('[data-modal-date-label]', detail.metaLabel || 'Periode');
        setText('[data-modal-level-label]', detail.levelLabel || 'Type');
        setTable(detail);

        var modal = document.querySelector('[data-notification-modal]');
        var icon = document.querySelector('[data-modal-icon]');

        if (icon) {
            icon.className = 'modal-icon severity-' + (detail.severity || 'info');
            icon.innerHTML = '<i class="bi ' + (detail.icon || 'bi-info-circle') + '"></i>';
        }

        document.body.classList.add('modal-open');
        if (modal) {
            modal.setAttribute('aria-hidden', 'false');
            window.setTimeout(function () {
                var close = modal.querySelector('[data-modal-close]');
                if (close) {
                    close.focus({ preventScroll: true });
                }
            }, 0);
        }
    }

    function closeNotificationModal() {
        var modal = document.querySelector('[data-notification-modal]');
        var wasOpen = document.body.classList.contains('modal-open');
        document.body.classList.remove('modal-open');
        if (modal) {
            modal.setAttribute('aria-hidden', 'true');
        }
        if (wasOpen && detailReturnFocus && document.contains(detailReturnFocus)) {
            detailReturnFocus.focus({ preventScroll: true });
        }
        if (wasOpen) {
            detailReturnFocus = null;
        }
    }

    function exportDetailPdf() {
        if (!activeDetail) {
            return;
        }

        var popup = window.open('', '_blank');
        if (!popup) {
            window.print();
            return;
        }

        popup.document.write('<!doctype html><html><head><title>Export DAGRIL</title><style>body{font-family:Arial,sans-serif;color:#162033;padding:32px} .card{border:1px solid #d9e1ea;border-radius:8px;padding:24px} h1{margin:0 0 6px;color:#0b1f35} .meta{color:#667085;margin-bottom:22px} table{width:100%;border-collapse:collapse} th,td{border-top:1px solid #d9e1ea;padding:12px;text-align:left} th{background:#0b1f35;color:#fff} td:first-child{font-weight:700;background:#f6f8fb}</style></head><body>');
        popup.document.write('<div class="card"><h1>DAGRIL ERP</h1><div class="meta">' + escapeHtml(activeDetail.title) + '</div>' + detailTable(activeDetail) + '</div>');
        popup.document.write('</body></html>');
        popup.document.close();
        popup.focus();
        popup.print();
    }

    function exportDetailExcel() {
        if (!activeDetail) {
            return;
        }

        var html = '<html><head><meta charset="utf-8"></head><body>' + detailTable(activeDetail) + '</body></html>';
        var blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = slug(activeDetail.title || 'export-dagril') + '.xls';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    }

    function notificationDetail(notification) {
        return {
            title: notification.title || 'Notification',
            kicker: 'Alerte ' + severityLabel(notification.severity),
            summary: notification.message || '',
            meta: formatDate(notification.created_at),
            level: severityLabel(notification.severity),
            severity: notification.severity || 'info',
            icon: severityIcon(notification.severity),
            summaryLabel: 'Message',
            metaLabel: 'Date',
            levelLabel: 'Niveau',
            rows: [
                { item: 'Titre', value: notification.title || '' },
                { item: 'Message', value: notification.message || '' },
                { item: 'Niveau', value: severityLabel(notification.severity) },
                { item: 'Date', value: formatDate(notification.created_at) }
            ]
        };
    }

    function kpiDetail(kpi) {
        var status = kpi.tone === 'red' ? 'A surveiller' : (kpi.tone === 'orange' ? 'Sous observation' : 'Conforme');
        var unit = detectUnit(kpi.value);

        return {
            title: kpi.label || 'KPI',
            kicker: 'Indicateur Direction',
            summary: 'Valeur actuelle : ' + (kpi.value || '0'),
            meta: new Date().toLocaleDateString('fr-FR'),
            level: 'KPI operationnel',
            severity: kpi.tone === 'red' ? 'danger' : (kpi.tone === 'orange' ? 'warning' : 'success'),
            icon: kpi.icon || 'bi-speedometer2',
            summaryLabel: 'Valeur',
            metaLabel: 'Date analyse',
            levelLabel: 'Categorie',
            columns: [
                { key: 'indicator', label: 'Indicateur' },
                { key: 'value', label: 'Valeur' },
                { key: 'unit', label: 'Unite' },
                { key: 'status', label: 'Statut' },
                { key: 'source', label: 'Source' }
            ],
            rows: [
                {
                    indicator: kpi.label || '',
                    value: kpi.value || '0',
                    unit: unit,
                    status: status,
                    source: 'Dashboard Direction'
                },
                {
                    indicator: 'Horodatage analyse',
                    value: new Date().toLocaleString('fr-FR'),
                    unit: 'Date',
                    status: 'Actualise',
                    source: 'Interface DAGRIL'
                }
            ]
        };
    }

    function chartDetail(key) {
        var chart = chartMeta(key);
        var dataset = (window.dagrilDashboard || {})[key] || { labels: [], values: [] };
        var rows = [];

        (dataset.labels || []).forEach(function (label, index) {
            var value = Number((dataset.values || [])[index] || 0);
            rows.push({
                rank: index + 1,
                label: label,
                value: formatNumber(value),
                unit: chart.unit,
                status: chartStatus(value, chart.unit)
            });
        });

        if (!rows.length) {
            rows.push({ rank: 1, label: 'Aucune donnee', value: '0', unit: chart.unit, status: 'Aucune activite' });
        }

        return {
            title: chart.title,
            kicker: 'Analyse graphique',
            summary: chart.summary,
            meta: chart.period,
            level: chart.unitLabel,
            severity: 'info',
            icon: chart.icon,
            summaryLabel: 'Lecture',
            metaLabel: 'Periode',
            levelLabel: 'Unite',
            columns: [
                { key: 'rank', label: 'N' },
                { key: 'label', label: chart.rowLabel },
                { key: 'value', label: 'Valeur' },
                { key: 'unit', label: 'Unite' },
                { key: 'status', label: 'Lecture' }
            ],
            rows: rows
        };
    }

    function chartMeta(key) {
        var meta = {
            productionSevenDays: {
                title: 'Production sur 7 jours',
                summary: 'Evolution des sorties totales de production validees.',
                period: '7 derniers jours',
                unit: 'kg',
                unitLabel: 'Kilogrammes',
                icon: 'bi-bar-chart-line',
                rowLabel: 'Jour'
            },
            yieldByMachine: {
                title: 'Rendement par machine',
                summary: 'Comparaison du rendement moyen par machine.',
                period: '7 derniers jours',
                unit: '%',
                unitLabel: 'Pourcentage',
                icon: 'bi-speedometer2',
                rowLabel: 'Machine'
            },
            receptionBySupplier: {
                title: 'Reception par fournisseur',
                summary: 'Repartition du mais brut recu par fournisseur.',
                period: '7 derniers jours',
                unit: 'kg',
                unitLabel: 'Kilogrammes',
                icon: 'bi-building-check',
                rowLabel: 'Fournisseur'
            }
        };

        return meta[key] || meta.productionSevenDays;
    }

    function detailTable(detail) {
        var rows = detail.rows || [];
        var columns = detail.columns || [
            { key: 'item', label: 'Element' },
            { key: 'value', label: 'Valeur' }
        ];
        var html = '<table><thead><tr>';
        columns.forEach(function (column) {
            html += '<th>' + escapeHtml(column.label) + '</th>';
        });
        html += '</tr></thead><tbody>';
        rows.forEach(function (row) {
            html += '<tr>';
            columns.forEach(function (column) {
                html += '<td>' + escapeHtml(row[column.key] || '') + '</td>';
            });
            html += '</tr>';
        });
        return html + '</tbody></table>';
    }

    function setTable(detail) {
        var container = document.querySelector('[data-modal-table-wrap]');
        if (container) {
            container.innerHTML = detailTable(detail);
        }
    }

    function setText(selector, value) {
        var element = document.querySelector(selector);
        if (element) {
            element.textContent = value;
        }
    }

    function severityLabel(severity) {
        var labels = { danger: 'Critique', warning: 'Attention', info: 'Information', success: 'Succes' };
        return labels[severity] || 'Information';
    }

    function severityIcon(severity) {
        if (severity === 'danger') {
            return 'bi-x-octagon';
        }
        if (severity === 'warning') {
            return 'bi-exclamation-triangle';
        }
        if (severity === 'success') {
            return 'bi-check2-circle';
        }
        return 'bi-info-circle';
    }

    function formatDate(value) {
        if (!value) {
            return 'Non renseignee';
        }

        var date = new Date(String(value).replace(' ', 'T'));
        if (isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleString('fr-FR');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('fr-FR', { maximumFractionDigits: 1 });
    }

    function detectUnit(value) {
        value = String(value || '');
        if (value.indexOf('%') !== -1) {
            return '%';
        }
        if (value.toLowerCase().indexOf('kg') !== -1) {
            return 'kg';
        }
        return 'Unite';
    }

    function chartStatus(value, unit) {
        if (Number(value) <= 0) {
            return 'Aucune activite';
        }
        if (unit === '%' && Number(value) >= 75) {
            return 'Bon rendement';
        }
        if (unit === '%' && Number(value) < 75) {
            return 'A ameliorer';
        }
        return 'Activite validee';
    }

    function slug(value) {
        return String(value)
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'export-dagril';
    }

    window.DagrilDetailUi = {
        open: openDetailModal,
        notification: notificationDetail,
        kpi: kpiDetail,
        chart: chartDetail
    };
})();

/* DAGRIL Experience Layer --------------------------------------------------
 * Progressive enhancement for ERP tables, confirmations and AJAX submits.
 * Business rules remain entirely server-side; native forms remain the fallback.
 */
(function () {
    'use strict';

    var pendingForm = null;
    var pendingSubmitter = null;
    var dialogReturnFocus = null;
    var commandReturnFocus = null;
    var userModalReturnFocus = null;
    var siteModalReturnFocus = null;
    var workspaceModalReturnFocus = null;
    var requestInFlight = false;
    var toastTimer = null;

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    }

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function setShellInert(inert) {
        var shell = qs('.app-shell');
        if (!shell) return;
        if (inert) {
            shell.setAttribute('inert', '');
            shell.setAttribute('aria-hidden', 'true');
        } else {
            shell.removeAttribute('inert');
            shell.removeAttribute('aria-hidden');
        }
    }

    function syncSidebarToggle(returnFocus) {
        var open = document.body.classList.contains('sidebar-open');
        var toggle = qs('[data-sidebar-toggle]');
        if (!toggle) return;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
        if (returnFocus && !open && document.contains(toggle)) toggle.focus();
    }

    function syncActiveMenuGroup() {
        var activeLink = qs('.sidebar [data-menu-item].active, .sidebar [data-menu-item][aria-current="page"]');
        var activeSection = activeLink ? activeLink.closest('[data-menu-section]') : null;
        qsa('.sidebar [data-menu-section]').forEach(function (section, index) {
            var isCurrent = activeSection ? section === activeSection : index === 0;
            section.classList.toggle('is-current', isCurrent);
            var sectionToggle = qs('[data-section-toggle]', section);
            if (sectionToggle) sectionToggle.setAttribute('aria-expanded', isCurrent ? 'true' : 'false');
        });
    }

    function normalize(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    function escapeText(value) {
        return document.createTextNode(String(value == null ? '' : value));
    }

    function icon(name) {
        var node = document.createElement('i');
        node.className = 'bi ' + name;
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    function hydrate(root) {
        root = root || document;
        if(window.initMachineFeeds)window.initMachineFeeds(root);
        if(window.initMachines)window.initMachines(root);
        if(window.initProduction)window.initProduction(root);
        if(window.initWaste)window.initWaste(root);
        if(window.initPelletization)window.initPelletization(root);
        if(window.initAudit)window.initAudit(root);
        if(window.initEmptyPackaging)window.initEmptyPackaging(root);
        if(window.initPackaging)window.initPackaging(root);
        if(window.initFinishedStocks)window.initFinishedStocks(root);
        if(window.initLivestock)window.initLivestock(root);
        if(window.initButchery)window.initButchery(root);
        if(window.initTransfers)window.initTransfers(root);
        if(window.initDistributions)window.initDistributions(root);
        if(window.initFuelLogistics)window.initFuelLogistics(root);
        if (window.initWorkDirectory) window.initWorkDirectory(root);
        if (window.initCampaignDirectory) window.initCampaignDirectory(root);
        if (window.initPlotDirectory) window.initPlotDirectory(root);
        if (window.initDepotDirectory) window.initDepotDirectory(root);
        enhanceAccessibility(root);
        refreshBusinessForms(root);
        enhanceDetailTriggers(root);
        enhanceTables(root);
        enhanceSiteTabs(root);
        enhanceBusinessTabs(root);
    }

    function enhanceBusinessTabs(root) {
        var agriculturePage=qs('[data-agriculture-section]',root);var agricultureSection=agriculturePage?agriculturePage.getAttribute('data-agriculture-section'):'overview';
        if(agriculturePage&&agricultureSection!=='overview'){var panelMap={campaigns:'agriCampaigns',plots:'agriPlanning',planning:'agriPlanning',inputs:'agriExtraDirectory',works:'agriExtraDirectory',harvests:'agriHarvests',stocks:'agriExtraDirectory',transports:'agriTransports',workers:'agriExtraDirectory',equipment:'agriExtraDirectory'};['agriCampaigns','agriPlanning','agriHarvests','agriTransports','agriExtraDirectory'].forEach(function(id){var panel=document.getElementById(id);if(panel)panel.hidden=id!==panelMap[agricultureSection];});return;}
        var list = qs('[data-business-tabs]', root);
        if (!list || list.dataset.ready === 'true') return;
        list.dataset.ready = 'true';
        var tabs = qsa('[data-business-tab]', list);
        var panels = qsa('[data-business-tab-panel]', root);
        function select(key, focus, updateHash) {
            tabs.forEach(function (tab) { var active=tab.dataset.businessTab===key;tab.setAttribute('aria-selected',active?'true':'false');tab.tabIndex=active?0:-1;tab.classList.toggle('is-active',active);if(active&&focus)tab.focus({preventScroll:true}); });
            panels.forEach(function (panel) { panel.hidden=panel.dataset.businessTabPanel!==key; });
            if(updateHash&&window.history&&window.history.replaceState)window.history.replaceState(null,'','#agri-'+key);
        }
        tabs.forEach(function(tab,index){tab.id='agriTab-'+tab.dataset.businessTab;var panel=document.getElementById(tab.getAttribute('aria-controls'));if(panel)panel.setAttribute('aria-labelledby',tab.id);tab.addEventListener('click',function(){select(tab.dataset.businessTab,false,true);});tab.addEventListener('keydown',function(event){if(['ArrowLeft','ArrowRight','Home','End'].indexOf(event.key)===-1)return;event.preventDefault();var target=event.key==='Home'?0:(event.key==='End'?tabs.length-1:(index+(event.key==='ArrowRight'?1:-1)+tabs.length)%tabs.length);select(tabs[target].dataset.businessTab,true,true);});});
        var requested=String(window.location.hash||'').replace('#agri-','');select(tabs.some(function(tab){return tab.dataset.businessTab===requested;})?requested:(tabs[0]?tabs[0].dataset.businessTab:''),false,false);
    }

    function enhanceSiteTabs(root) {
        var list = qs('[data-site-tabs]', root);
        if (!list || list.dataset.ready === 'true') return;
        list.dataset.ready = 'true';
        var mapping = { sites: 'siteDirectory', locations: 'siteLocations', 'cost-centers': 'siteCostCenters', units: 'siteUnits', assignments: 'siteAssignments' };
        var sitePanel = qs('.sites-table-panel', root);
        if (sitePanel) sitePanel.id = 'siteDirectory';
        var tabs = qsa('[data-site-tab]', list);
        Object.keys(mapping).forEach(function (key) {
            var panel = document.getElementById(mapping[key]);
            if (panel) { panel.setAttribute('role', 'tabpanel');panel.setAttribute('data-site-tab-panel', key);panel.setAttribute('aria-labelledby', 'siteTab-' + key); }
            var tab = qs('[data-site-tab="' + key + '"]', list);
            if (tab) tab.id = 'siteTab-' + key;
        });
        function select(key, focus, updateHash) {
            if (!mapping[key]) key = 'sites';
            tabs.forEach(function (tab) {
                var active = tab.dataset.siteTab === key;
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                tab.tabIndex = active ? 0 : -1;
                tab.classList.toggle('is-active', active);
                if (active && focus) tab.focus({ preventScroll: true });
            });
            Object.keys(mapping).forEach(function (panelKey) {
                var panel = document.getElementById(mapping[panelKey]);
                if (panel) panel.hidden = panelKey !== key;
            });
            if (updateHash && window.history && window.history.replaceState) window.history.replaceState(null, '', '#tab-' + key);
        }
        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () { select(tab.dataset.siteTab, false, true); });
            tab.addEventListener('keydown', function (event) {
                if (['ArrowLeft','ArrowRight','Home','End'].indexOf(event.key) === -1) return;
                event.preventDefault();
                var target = event.key === 'Home' ? 0 : (event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length);
                select(tabs[target].dataset.siteTab, true, true);
            });
        });
        var requested = String(window.location.hash || '').replace('#tab-', '');
        select(mapping[requested] ? requested : 'sites', false, false);
    }

    function openUserModal(trigger, account) {
        var modal = qs('[data-user-modal]');
        var form = modal ? qs('[data-user-form]', modal) : null;
        if (!modal || !form) return;
        userModalReturnFocus = trigger || null;
        form.reset();
        qsa('input[name="site_ids[]"]', form).forEach(function (input) { input.checked = false; });
        var editing = Boolean(account && account.id);
        form.action = editing ? modal.getAttribute('data-update-pattern').replace('__id__', String(account.id)) : modal.getAttribute('data-create-action');
        qs('[data-user-modal-title]', modal).textContent = editing ? 'Modifier l’utilisateur' : 'Nouvel utilisateur';
        qs('[data-user-modal-subtitle]', modal).textContent = editing ? 'Mettez à jour le compte et son périmètre de sites.' : 'Le compte sera créé sans permission métier.';
        qs('[data-user-submit-label]', modal).textContent = editing ? 'Enregistrer les modifications' : 'Créer l’utilisateur';
        qs('[data-user-password-label]', modal).textContent = editing ? 'Nouveau mot de passe' : 'Mot de passe temporaire *';
        qs('[data-user-password-help]', modal).textContent = editing ? 'Laissez vide pour conserver le mot de passe actuel.' : 'Au moins 8 caractères. Aucun rôle métier ne sera attribué automatiquement.';
        var password = qs('[data-user-password]', modal);
        password.required = !editing;
        if (editing) {
            ['name','email','phone','status'].forEach(function (name) { var field=form.elements[name];if(field) field.value=account[name] || ''; });
            (account.site_ids || []).forEach(function (siteId) { var input=qs('input[name="site_ids[]"][value="'+String(siteId).replace(/"/g,'')+'"]',form);if(input)input.checked=true; });
        }
        document.body.classList.add('user-modal-open');
        modal.setAttribute('aria-hidden','false');
        var first = form.elements.name;
        if (first) first.focus({ preventScroll:true });
    }

    function closeUserModal(returnFocus) {
        var modal = qs('[data-user-modal]');
        document.body.classList.remove('user-modal-open');
        if (modal) modal.setAttribute('aria-hidden','true');
        if (returnFocus && userModalReturnFocus && document.contains(userModalReturnFocus)) userModalReturnFocus.focus({ preventScroll:true });
        userModalReturnFocus = null;
    }

    function openSiteModal(id, trigger) {
        var modal = document.getElementById(id);
        if (!modal || !modal.classList.contains('site-entity-modal')) return;
        closeSiteModal(false);
        siteModalReturnFocus = trigger || document.activeElement;
        var form = qs('form', modal);
        if (form && !trigger.hasAttribute('data-site-edit') && !trigger.hasAttribute('data-site-assignment')) form.reset();
        modal.classList.add('is-open');
        var backdrop = qs('[data-site-modal-backdrop]');
        if (backdrop) backdrop.classList.add('is-open');
        document.body.classList.add('site-modal-open');
        modal.setAttribute('aria-hidden', 'false');
        var first = qs('input:not([type="hidden"]), select, textarea, button', modal);
        if (first) window.setTimeout(function () { first.focus({ preventScroll: true }); }, 0);
    }

    function closeSiteModal(restoreFocus) {
        var open = qs('.site-entity-modal.is-open');
        if (open) { open.classList.remove('is-open');open.setAttribute('aria-hidden', 'true'); }
        var backdrop = qs('[data-site-modal-backdrop]');
        if (backdrop) backdrop.classList.remove('is-open');
        document.body.classList.remove('site-modal-open');
        if (restoreFocus && siteModalReturnFocus && document.contains(siteModalReturnFocus)) siteModalReturnFocus.focus({ preventScroll: true });
        if (restoreFocus) siteModalReturnFocus = null;
    }

    function openWorkspaceModal(id, trigger) {
        closeActionMenus();
        closeWorkspaceModal(false);
        var modal=document.getElementById(id);if(!modal||!modal.classList.contains('workspace-entity-modal'))return;
        workspaceModalReturnFocus=trigger||document.activeElement;var form=qs('form',modal);if(form){form.reset();var page=qs('[data-agriculture-section]');var returnField=form.elements._return_to;if(page&&returnField&&page.dataset.agricultureSection!=='overview')returnField.value='agriculture/'+page.dataset.agricultureSection;}
        if(id==='harvestModal')prepareHarvestEditor(modal,trigger);
        updateHarvestPlanSummary(qs('[data-harvest-plan]',modal));
        var depotStock=trigger&&trigger.getAttribute('data-depot-stock');if(depotStock){var stockField=qs('[data-farm-transport-stock]',modal);if(stockField)stockField.value=depotStock;}
        enhanceFarmTransportRoutes(modal);
        var campaignSite=qs('[data-campaign-site]',modal);if(campaignSite)syncCampaignSuggestedCode(campaignSite);
        var plotSite=qs('[data-plot-site]',modal);if(plotSite)syncPlotSuggestedCode(plotSite);
        var farmCodeSite=qs('[data-farm-code-site]',modal);if(farmCodeSite)syncFarmSuggestedCode(farmCodeSite);
        var planSite=qs('[data-plan-site]',modal);if(planSite){var requestedSite=trigger?trigger.getAttribute('data-plan-site-id'):'';if(requestedSite)planSite.value=requestedSite;syncPlanSite(planSite);var requestedPlot=trigger?trigger.getAttribute('data-plan-plot'):'';var plotSelect=qs('[data-plan-plot-select]',modal);if(requestedPlot&&plotSelect)plotSelect.value=requestedPlot;updatePlotContext(plotSelect);}
        var requestedCampaign=trigger?trigger.getAttribute('data-plan-campaign-id'):null;var campaignSelect=qs('[data-plan-campaign]',modal);if(requestedCampaign&&campaignSelect)campaignSelect.value=requestedCampaign;
        modal.classList.add('is-open');var backdrop=qs('.workspace-modal-backdrop');if(backdrop)backdrop.classList.add('is-open');document.body.classList.add('workspace-modal-open');modal.setAttribute('aria-hidden','false');
        var first=qs('input:not([type="hidden"]),select,textarea,button',modal);if(first)window.setTimeout(function(){first.focus({preventScroll:true});},0);
    }

    document.addEventListener('input',function(event){if(event.target.closest('#harvestModal'))updateHarvestNet(event.target.form);});

    function prepareHarvestEditor(modal,trigger){
        var form=qs('form',modal),raw=trigger&&trigger.getAttribute('data-harvest-edit'),data=raw?JSON.parse(raw):null;
        if(!form.dataset.createAction){form.dataset.createAction=form.action;form.dataset.createConfirm=form.dataset.confirm;}
        form.action=data?trigger.getAttribute('data-harvest-update-url'):form.dataset.createAction;
        qs('#harvestModalTitle',modal).textContent=data?'Modifier la récolte':'Soumettre une récolte';
        qs('footer button[type="submit"],footer button:not([type])',modal).textContent=data?'Enregistrer les modifications':'Soumettre la récolte';
        var select=qs('[data-harvest-plan]',modal),fixed=qs('[data-harvest-fixed-plan]',modal),reason=qs('[name="reason"]',form),notice=qs('[data-harvest-edit-notice]',modal);
        select.disabled=!!data;fixed.disabled=!data;reason.disabled=!data;reason.required=!!data;qs('[data-harvest-reason]',modal).hidden=!data;notice.hidden=!data;
        if(data){select.value=data.campaign_plot_id;fixed.value=data.campaign_plot_id;Object.keys(data).forEach(function(name){if(name==='campaign_plot_id'||!form.elements[name])return;var field=form.elements[name];field.value=data[name]===null?'':(field.type==='datetime-local'?String(data[name]).replace(' ','T'):String(data[name]));});notice.textContent=data.harvest_number+(data.status==='validated'?' · Correction administrative : le stock restant sera ajusté selon le nouveau poids net.':' · Correction de la récolte avant validation.')+' La campagne et la parcelle restent inchangées.';}
        form.dataset.confirmTitle=data?'Confirmer la modification de récolte':'Soumettre une récolte';form.dataset.confirm=data?'Vérifiez les poids et le motif. Une récolte validée conserve sa validation et son stock sera ajusté.':form.dataset.createConfirm;
        var summaryNote=qs('.harvest-plan-summary-note',modal);if(summaryNote)summaryNote.textContent=data&&data.status==='validated'?'Récoltes validées avant correction · la valeur précédente de cette récolte est incluse.':'Données de la campagne sélectionnée · hors récolte en cours de saisie.';
        updateHarvestNet(form);
    }
    function updateHarvestNet(form){if(!form||!qs('[data-harvest-net]',form))return;var net=(Number(form.elements.gross_weight_kg.value)||0)-(Number(form.elements.tare_weight_kg.value)||0)-(Number(form.elements.drying_loss_kg.value)||0);qs('[data-harvest-net]',form).textContent=net.toLocaleString('fr-FR',{minimumFractionDigits:3,maximumFractionDigits:3})+' kg';}

    function updateHarvestPlanSummary(select) {
        if(!select)return;
        var summary=qs('.harvest-plan-summary',select.form),option=select.options[select.selectedIndex];
        if(!summary)return;
        var data=null;try{data=option&&option.dataset.harvestSummary?JSON.parse(option.dataset.harvestSummary):null;}catch(error){}
        summary.hidden=!data;
        qsa('[data-harvest-info]',summary).forEach(function(field){field.textContent=data?(data[field.dataset.harvestInfo]||'—'):'';});
    }

    function syncCampaignSuggestedCode(select) {
        if(!select)return;var form=select.closest('form');var input=form?qs('[data-campaign-code]',form):null;var option=select.options[select.selectedIndex];
        if(input&&option&&option.getAttribute('data-suggested-code'))input.value=option.getAttribute('data-suggested-code');
    }

    function syncPlotSuggestedCode(select) {
        if(!select)return;var form=select.closest('form');var input=form?qs('[data-plot-code]',form):null;var option=select.options[select.selectedIndex];
        if(input&&option&&option.getAttribute('data-suggested-code'))input.value=option.getAttribute('data-suggested-code');
    }

    function syncFarmSuggestedCode(select){if(!select)return;var form=select.closest('form');var target=form&&select.dataset.codeTarget?form.elements[select.dataset.codeTarget]:null;var option=select.options[select.selectedIndex];if(target&&option&&option.dataset.suggestedCode)target.value=option.dataset.suggestedCode;}

    function syncPlanSite(select) {
        if(!select)return;var form=select.closest('form');var site=select.value;
        ['[data-plan-campaign]','[data-plan-plot-select]'].forEach(function(selector){var field=form?qs(selector,form):null;if(!field)return;var first='';Array.prototype.forEach.call(field.options,function(option){var allowed=option.getAttribute('data-site-id')===site;option.hidden=!allowed;option.disabled=!allowed;if(allowed&&!first)first=option.value;});if(!field.value||field.options[field.selectedIndex].disabled)field.value=first;});
        updatePlotContext(form?qs('[data-plan-plot-select]',form):null);
    }

    function updatePlotContext(select) {
        if(!select)return;var form=select.closest('form');var card=form?qs('[data-plot-context]',form):null;var option=select.selectedIndex>=0?select.options[select.selectedIndex]:null;if(!card)return;
        function put(selector,value){var node=qs(selector,card);if(node)node.textContent=value;}
        if(!option||option.disabled||!option.value){put('[data-plot-context-title]','Aucune parcelle disponible');put('[data-plot-context-site]','Créez une parcelle dans cette ferme avant de planifier.');['[data-plot-total]','[data-plot-exploited]','[data-plot-remaining]','[data-plot-count]'].forEach(function(selector){put(selector,'—');});return;}
        var hectares=function(value){return Number(value||0).toLocaleString('fr-FR',{minimumFractionDigits:3,maximumFractionDigits:3})+' ha';};
        put('[data-plot-context-title]',(option.dataset.code||'')+' — '+(option.dataset.name||''));put('[data-plot-context-site]',option.dataset.site||'');put('[data-plot-total]',hectares(option.dataset.totalArea));put('[data-plot-exploited]',hectares(option.dataset.exploitedArea));put('[data-plot-remaining]',hectares(option.dataset.remainingArea));put('[data-plot-count]',option.dataset.planningCount||'0');
    }

    function closeWorkspaceModal(restoreFocus) {
        var modal=qs('.workspace-entity-modal.is-open');if(modal){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');}
        var backdrop=qs('.workspace-modal-backdrop');if(backdrop)backdrop.classList.remove('is-open');document.body.classList.remove('workspace-modal-open');
        if(restoreFocus&&workspaceModalReturnFocus&&document.contains(workspaceModalReturnFocus))workspaceModalReturnFocus.focus({preventScroll:true});if(restoreFocus)workspaceModalReturnFocus=null;
    }

    function closeActionMenus() {
        qsa('[data-action-menu]').forEach(function(menu){menu.hidden=true;});
        qsa('[data-action-menu-toggle]').forEach(function(toggle){toggle.setAttribute('aria-expanded','false');});
    }

    function toggleActionMenu(toggle) {
        var menu=document.getElementById(toggle.getAttribute('data-action-menu-toggle'));if(!menu)return;
        var willOpen=menu.hidden;closeActionMenus();menu.hidden=!willOpen;toggle.setAttribute('aria-expanded',willOpen?'true':'false');
        if(willOpen){var first=qs('button',menu);if(first)first.focus({preventScroll:true});}
    }

    function openSiteEditor(trigger, account) {
        var modal = qs('[data-site-editor]');
        var form = modal ? qs('[data-site-form]', modal) : null;
        if (!modal || !form) return;
        form.reset();
        var editing = Boolean(account && account.id);
        form.action = editing ? modal.dataset.updatePattern.replace('__id__', String(account.id)) : modal.dataset.createAction;
        ['site_type_id','code','name','description','status'].forEach(function (name) { if (editing && form.elements[name]) form.elements[name].value = account[name] == null ? '' : account[name]; });
        var title = qs('h2', modal);if (title) title.textContent = editing ? 'Modifier le site' : 'Nouveau site';
        var label = qs('[data-site-submit-label]', modal);if (label) label.textContent = editing ? 'Enregistrer les modifications' : 'Créer le site';
        openSiteModal('siteEditor', trigger);
    }

    function openSiteAssignment(trigger, account) {
        var modal = qs('[data-assignment-editor]');
        var form = modal ? qs('[data-assignment-form]', modal) : null;
        if (!modal || !form || !account) return;
        form.reset();
        form.action = modal.dataset.actionPattern.replace('__id__', String(account.id));
        qsa('input[name="site_ids[]"]', form).forEach(function (input) { input.checked = (account.site_ids || []).indexOf(Number(input.value)) !== -1; });
        if (form.elements.default_site_id) form.elements.default_site_id.value = account.default_site_id || '';
        var name = qs('[data-assignment-name]', modal);if (name) name.textContent = account.name || 'Utilisateur';
        var role = qs('[data-assignment-role]', modal);if (role) role.textContent = account.role_name || 'Sans rôle historique';
        openSiteModal('assignmentEditor', trigger);
    }

    function enhanceAccessibility(root) {
        qsa('table thead th', root).forEach(function (heading) {
            if (!heading.hasAttribute('scope')) {
                heading.setAttribute('scope', 'col');
            }
        });

        qsa('.icon-button, .panel-action', root).forEach(function (button) {
            if (!button.hasAttribute('aria-label')) {
                var label = button.getAttribute('title') || normalize(button.textContent).replace(/^./, function (letter) { return letter.toUpperCase(); });
                if (label) {
                    button.setAttribute('aria-label', label);
                }
            }
        });

        qsa('form', root).forEach(function (form) {
            if (!form.hasAttribute('novalidate')) {
                form.setAttribute('data-progressive-form', 'true');
            }
        });

        qsa('table', root).forEach(function (table, index) {
            if (table.hasAttribute('aria-label') || table.hasAttribute('aria-labelledby') || table.querySelector('caption')) {
                return;
            }
            var panel = table.closest('.table-panel, .form-panel, section');
            var title = panel ? panel.querySelector('.panel-heading h2, .panel-heading h3, h2, h3') : null;
            table.setAttribute('aria-label', title ? title.textContent.trim() : 'Tableau de données ' + (index + 1));
        });
    }

    function numericValue(field) {
        return Number(String(field && field.value || 0).replace(/\s/g, '').replace(',', '.')) || 0;
    }

    function selectedOption(field) {
        return field && field.selectedIndex >= 0 ? field.options[field.selectedIndex] : null;
    }

    function enhanceFarmTransportRoutes(root) {
        qsa('[data-farm-transport-stock]', root).forEach(function (select) {
            var form = select.form;
            var route = form && qs('[data-farm-transport-route]', form);
            if (!route) return;
            function syncRoute() {
                var option = select.options[select.selectedIndex];
                var origin = option ? option.getAttribute('data-route-origin') : '';
                var destination = route.getAttribute('data-route-destination');
                route.value = origin && destination ? origin + ' → ' + destination : '';
            }
            if (!select.dataset.routeReady) {
                select.dataset.routeReady = 'true';
                select.addEventListener('change', syncRoute);
                form.addEventListener('reset', function () { window.setTimeout(syncRoute, 0); });
            }
            syncRoute();
        });
    }

    function enhanceWeighingEntry(root) {
        qsa('[data-weighing-entry]', root).forEach(function (form) {
            var select = qs('[data-entry-transport]', form);
            if (!select) return;
            var workspace = form.closest('.weighing-entry-workspace');
            var dialog = workspace && qs('.entry-bt-dialog', workspace);
            var display = qs('[data-entry-display]', form);
            var trigger = qs('[data-entry-picker-open]', form);
            var error = qs('[data-entry-picker-error]', form);
            var choices = dialog ? qsa('[data-entry-choice]', dialog) : [];
            function syncContext() {
                var option = select.options[select.selectedIndex];
                qsa('[data-entry-context]', form).forEach(function (field) {
                    field.textContent = option && option.value ? option.getAttribute('data-entry-' + field.dataset.entryContext) || '—' : '—';
                });
                if (display) display.value = option && option.value ? option.textContent.trim() : '';
                choices.forEach(function (choice) {
                    var selected = choice.dataset.entryChoice === select.value;
                    choice.setAttribute('aria-pressed', String(selected));
                    qs('.entry-bt-selected', choice).hidden = !selected;
                });
                if (select.value && error) { error.hidden = true; display.removeAttribute('aria-invalid'); }
            }
            if (!select.dataset.entryReady) {
                select.dataset.entryReady = 'true';
                select.addEventListener('change', syncContext);
                form.addEventListener('reset', function () { window.setTimeout(syncContext, 0); });
                if (dialog && typeof dialog.showModal === 'function') {
                    qs('[data-entry-select-fallback]', form).hidden = true;
                    select.required = false;
                    qs('[data-entry-picker]', form).hidden = false;
                    var search = qs('[data-entry-picker-search]', dialog);
                    function normalize(value) { return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim(); }
                    function filter() {
                        var terms = normalize(search.value).split(/\s+/).filter(Boolean);
                        var count = 0;
                        choices.forEach(function (choice) {
                            var text = normalize(choice.textContent);
                            choice.hidden = !terms.every(function (term) { return text.indexOf(term) !== -1; });
                            if (!choice.hidden) count++;
                        });
                        qs('[data-entry-picker-count]', dialog).textContent = count + ' BT disponible(s)';
                        qs('[data-entry-picker-empty]', dialog).hidden = count !== 0;
                    }
                    function open() { search.value = ''; filter(); syncContext(); if (!dialog.open) dialog.showModal(); search.focus(); }
                    trigger.addEventListener('click', open);
                    display.addEventListener('click', open);
                    search.addEventListener('input', filter);
                    choices.forEach(function (choice) {
                        choice.addEventListener('click', function () {
                            select.value = choice.dataset.entryChoice;
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                            dialog.close();
                        });
                    });
                    qsa('[data-entry-picker-close]', dialog).forEach(function (button) { button.addEventListener('click', function () { dialog.close(); }); });
                    dialog.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); dialog.close(); }
                    });
                    dialog.addEventListener('close', function () { if (trigger.isConnected) trigger.focus(); });
                    form.addEventListener('submit', function (event) {
                        if (!select.value) {
                            event.preventDefault(); event.stopImmediatePropagation();
                            error.hidden = false; display.setAttribute('aria-invalid', 'true'); open();
                        }
                    }, true);
                }
            }
            syncContext();
        });
    }

    function refreshBusinessForms(root) {
        enhanceWeighingEntry(root);
        enhanceFarmTransportRoutes(root);
        qsa('[data-weighing-exit], [data-production-form], [data-waste-form], [data-packaging-form], [data-distribution-form]', root)
            .forEach(updateBusinessForm);
    }

    function updateBusinessForm(form) {
        if (!form) return;

        if (form.matches('[data-weighing-exit]')) {
            var exitWorkspace = form.closest('.weighing-exit-workspace');
            var decision = qs('[name="decision"]', form);
            if (exitWorkspace && decision) {
                var rejected = decision.value === 'reject';
                qsa('[data-exit-validate-label]', exitWorkspace).forEach(function (button) { button.textContent = rejected ? 'Confirmer le refus' : 'Valider et créditer le silo'; });
                var effect = qs('[data-exit-effect]', exitWorkspace);
                if (effect) effect.textContent = rejected ? 'Le refus ne crédite pas le silo.' : 'La validation d’une livraison acceptée ajoute le poids net au silo choisi.';
            }

            var gross = numericValue(form.querySelector('[data-poids-brut]'));
            var tare = numericValue(form.querySelector('[data-poids-tare]'));
            var net = form.querySelector('[data-poids-net]');
            if (net) net.value = form.querySelector('[data-poids-tare]').value === '' ? 'À calculer' : Math.max(gross - tare, 0).toLocaleString('fr-FR', { maximumFractionDigits: 3 });
        }

        if (form.matches('[data-production-form]')) {
            var batch = selectedOption(form.querySelector('[data-production-batch]'));
            var treated = batch ? Number(batch.getAttribute('data-quantity') || 0) : 0;
            var machine = batch ? batch.getAttribute('data-machine') || '' : '';
            var goodInput = form.querySelector('[data-good-quantity]');
            var good = numericValue(goodInput);
            var treatedField = form.querySelector('[data-treated-quantity]');
            var machineField = form.querySelector('[data-machine-name]');
            var yieldField = form.querySelector('[data-yield-rate]');
            var wasteField = form.querySelector('[data-waste-quantity]');
            var wasteLines = qsa('[data-waste-line]', form);
            var recoveredWaste = wasteLines.reduce(function (sum, field) { return sum + numericValue(field); }, 0);

            if (treatedField) treatedField.value = treated ? treated.toLocaleString('fr-FR', { maximumFractionDigits: 3 }) + ' kg' : '';
            if (machineField) machineField.value = machine;
            if (yieldField) yieldField.value = (treated > 0 ? (good / treated) * 100 : 0).toLocaleString('fr-FR', { maximumFractionDigits: 2 }) + ' %';
            if (wasteField && !wasteLines.length) {
                wasteField.value = treated > 0 ? Math.max(treated - good, 0).toFixed(3) : '';
                recoveredWaste = numericValue(wasteField);
            }
            var variance = form.querySelector('[data-variance]');
            if (variance) {
                var gap = treated - good - recoveredWaste;
                var gapRate = treated > 0 ? (gap / treated) * 100 : 0;
                variance.value = gap.toFixed(3) + ' kg / ' + gapRate.toFixed(3) + ' %';
            }
            if (goodInput) goodInput.setCustomValidity(treated > 0 && good > treated ? 'La farine produite ne doit pas dépasser la quantité chargée.' : '');
        }

        if (form.matches('[data-waste-form]')) {
            var available = Number(form.getAttribute('data-available-stock') || 0);
            var wasteInput = form.querySelector('[data-waste-input]');
            var wasteOutput = form.querySelector('[data-waste-output]');
            var inputQuantity = numericValue(wasteInput);
            var outputQuantity = numericValue(wasteOutput);
            var wasteYield = form.querySelector('[data-waste-yield]');
            if (wasteYield) wasteYield.value = (inputQuantity > 0 ? (outputQuantity / inputQuantity) * 100 : 0).toLocaleString('fr-FR', { maximumFractionDigits: 2 }) + ' %';
            if (wasteInput) wasteInput.setCustomValidity(inputQuantity > available ? 'La quantité traitée dépasse le stock de déchets disponible.' : '');
            if (wasteOutput) wasteOutput.setCustomValidity(inputQuantity > 0 && outputQuantity > inputQuantity ? 'La quantité produite ne peut pas dépasser la quantité traitée.' : '');
        }

        if (form.matches('[data-packaging-form]')) {
            var packagingBatch = selectedOption(form.querySelector('[data-packaging-batch]'));
            var bagFormat = selectedOption(form.querySelector('[data-bag-format]'));
            var availableQuantity = packagingBatch ? Number(packagingBatch.getAttribute('data-available') || 0) : 0;
            var bagWeight = bagFormat ? Number(bagFormat.getAttribute('data-weight') || 0) : 0;
            var bagCount = numericValue(form.querySelector('[data-bags-count]'));
            var packagedTotal = bagWeight * bagCount;
            var productField = form.querySelector('[data-packaging-product]');
            var availableField = form.querySelector('[data-packaging-available]');
            var totalField = form.querySelector('[data-packaging-total]');
            var bagsField = form.querySelector('[data-bags-count]');
            if (productField) productField.value = packagingBatch ? packagingBatch.getAttribute('data-product') || '' : '';
            if (availableField) availableField.value = availableQuantity ? availableQuantity.toLocaleString('fr-FR', { maximumFractionDigits: 3 }) + ' kg' : '';
            if (totalField) totalField.value = packagedTotal ? packagedTotal.toLocaleString('fr-FR', { maximumFractionDigits: 3 }) + ' kg' : '';
            if (bagsField) bagsField.setCustomValidity(availableQuantity > 0 && packagedTotal > availableQuantity ? 'Le poids total dépasse la quantité disponible.' : '');
        }

        if (form.matches('[data-distribution-form]')) {
            var stock = selectedOption(form.querySelector('[data-distribution-stock]'));
            var availableBags = stock ? Number(stock.getAttribute('data-bags') || 0) : 0;
            var availableKg = stock ? Number(stock.getAttribute('data-kg') || 0) : 0;
            var unitWeight = stock ? Number(stock.getAttribute('data-weight') || 0) : 0;
            var distributionBags = form.querySelector('[data-distribution-bags]');
            var requestedBags = numericValue(distributionBags);
            var distributionProduct = form.querySelector('[data-distribution-product]');
            var distributionFormat = form.querySelector('[data-distribution-format]');
            var distributionAvailable = form.querySelector('[data-distribution-available]');
            var distributionTotal = form.querySelector('[data-distribution-total]');
            if (distributionProduct) distributionProduct.value = stock ? stock.getAttribute('data-product') || '' : '';
            if (distributionFormat) distributionFormat.value = stock ? stock.getAttribute('data-format') || '' : '';
            if (distributionAvailable) distributionAvailable.value = availableBags ? availableBags.toLocaleString('fr-FR') + ' sacs / ' + availableKg.toLocaleString('fr-FR', { maximumFractionDigits: 3 }) + ' kg' : '';
            if (distributionTotal) distributionTotal.value = requestedBags && unitWeight ? (requestedBags * unitWeight).toLocaleString('fr-FR', { maximumFractionDigits: 3 }) + ' kg' : '';
            if (distributionBags) distributionBags.setCustomValidity(availableBags > 0 && requestedBags > availableBags ? 'Le nombre de sacs dépasse le stock disponible.' : '');
        }
    }

    function enhanceDetailTriggers(root) {
        if (!window.DagrilDetailUi) return;
        qsa('[data-notification-open]:not([data-dagril-detail-ready])', root).forEach(function (trigger) {
            trigger.dataset.dagrilDetailReady = 'true';
            trigger.addEventListener('click', function () {
                var item = (window.dagrilNotifications || [])[Number(trigger.getAttribute('data-notification-index'))];
                if (item) window.DagrilDetailUi.open(window.DagrilDetailUi.notification(item));
            });
        });
        qsa('[data-kpi-open]:not([data-dagril-detail-ready])', root).forEach(function (trigger) {
            trigger.dataset.dagrilDetailReady = 'true';
            trigger.addEventListener('click', function () {
                var item = (window.dagrilKpis || [])[Number(trigger.getAttribute('data-kpi-index'))];
                if (item) window.DagrilDetailUi.open(window.DagrilDetailUi.kpi(item));
            });
        });
        qsa('[data-chart-open]:not([data-dagril-detail-ready])', root).forEach(function (trigger) {
            trigger.dataset.dagrilDetailReady = 'true';
            trigger.addEventListener('click', function (event) {
                if (event.target.closest('.panel-action') || event.target.closest('.chart-box')) {
                    window.DagrilDetailUi.open(window.DagrilDetailUi.chart(trigger.getAttribute('data-chart-key')));
                }
            });
        });
    }

    function enhanceTables(root) {
        qsa('.content-area table', root).forEach(function (table, index) {
            if (table.dataset.dagrilTable === 'ready' || table.getAttribute('data-datatable') === 'false') {
                return;
            }
            if (table.classList.contains('dataTable') || table.closest('.dataTables_wrapper') || table.closest('.modal-table-wrap')) {
                return;
            }

            var body = table.tBodies && table.tBodies[0];
            var rows = body ? Array.prototype.slice.call(body.rows) : [];
            var headings = table.tHead ? Array.prototype.slice.call(table.tHead.querySelectorAll('th')) : [];
            var placeholderOnly = rows.length === 1 && rows[0].cells.length === 1 && rows[0].cells[0].colSpan > 1;
            if (!body || !headings.length || placeholderOnly) {
                table.classList.add('enterprise-table');
                return;
            }

            table.dataset.dagrilTable = 'ready';
            table.classList.add('enterprise-table');
            createDataTable(table, body, rows, headings, index);
        });
    }

    function createDataTable(table, body, sourceRows, headings, index) {
        var existingScroll = table.parentNode && table.parentNode.classList.contains('table-responsive') ? table.parentNode : null;
        var container = document.createElement('div');
        container.className = 'dagril-datatable';
        container.dataset.tableIndex = String(index);
        var scroll = existingScroll || document.createElement('div');
        scroll.classList.add('table-responsive');

        if (existingScroll) {
            existingScroll.parentNode.insertBefore(container, existingScroll);
            container.appendChild(existingScroll);
        } else {
            table.parentNode.insertBefore(container, table);
            scroll.appendChild(table);
            container.appendChild(scroll);
        }

        var toolbar = document.createElement('div');
        toolbar.className = 'dagril-table-toolbar';
        toolbar.setAttribute('role', 'search');

        var search = document.createElement('label');
        search.className = 'dagril-table-search';
        search.appendChild(icon('bi-search'));
        var searchInput = document.createElement('input');
        searchInput.type = 'search';
        searchInput.placeholder = table.getAttribute('data-search-placeholder') || 'Rechercher dans le tableau';
        searchInput.setAttribute('aria-label', searchInput.placeholder);
        search.appendChild(searchInput);

        var meta = document.createElement('span');
        meta.className = 'dagril-table-meta';
        meta.setAttribute('aria-live', 'polite');

        var sizeLabel = document.createElement('label');
        sizeLabel.className = 'sr-only';
        sizeLabel.textContent = 'Lignes par page';
        var size = document.createElement('select');
        size.className = 'dagril-table-size';
        size.setAttribute('aria-label', 'Lignes par page');
        [10, 25, 50, 100].forEach(function (value) {
            var option = document.createElement('option');
            option.value = String(value);
            option.textContent = value + ' lignes';
            size.appendChild(option);
        });

        var density = document.createElement('button');
        density.type = 'button';
        density.className = 'dagril-density-button';
        density.title = 'Changer la densite';
        density.setAttribute('aria-label', 'Activer la vue compacte');
        density.appendChild(icon('bi-list'));

        toolbar.appendChild(search);
        toolbar.appendChild(meta);
        toolbar.appendChild(sizeLabel);
        toolbar.appendChild(size);
        toolbar.appendChild(density);
        container.insertBefore(toolbar, scroll);

        var pagination = document.createElement('div');
        pagination.className = 'dagril-table-pagination';
        var pageInfo = document.createElement('span');
        pageInfo.className = 'dagril-page-info';
        var pageActions = document.createElement('div');
        pageActions.className = 'dagril-page-actions';
        pagination.appendChild(pageInfo);
        pagination.appendChild(pageActions);
        container.appendChild(pagination);

        var state = {
            rows: sourceRows,
            query: '',
            page: 1,
            pageSize: 10,
            sortIndex: null,
            sortDirection: 1,
            emptyRow: null
        };

        headings.forEach(function (heading, headingIndex) {
            var headingText = heading.textContent.trim();
            heading.setAttribute('scope', 'col');
            if (!headingText || /^(action|actions)$/i.test(headingText) || heading.getAttribute('data-sortable') === 'false') {
                return;
            }
            var sortButton = document.createElement('button');
            sortButton.type = 'button';
            sortButton.className = 'dagril-sort-button';
            sortButton.appendChild(escapeText(headingText));
            sortButton.appendChild(icon('bi-arrow-down-up'));
            sortButton.setAttribute('aria-label', 'Trier par ' + headingText);
            heading.textContent = '';
            heading.appendChild(sortButton);
            heading.setAttribute('aria-sort', 'none');
            sortButton.addEventListener('click', function () {
                if (state.sortIndex === headingIndex) {
                    state.sortDirection *= -1;
                } else {
                    state.sortIndex = headingIndex;
                    state.sortDirection = 1;
                }
                headings.forEach(function (other) { other.setAttribute('aria-sort', 'none'); });
                heading.setAttribute('aria-sort', state.sortDirection === 1 ? 'ascending' : 'descending');
                state.page = 1;
                renderTable(state, body, meta, pageInfo, pageActions, headings.length);
            });
        });

        searchInput.addEventListener('input', function () {
            state.query = normalize(searchInput.value);
            state.page = 1;
            renderTable(state, body, meta, pageInfo, pageActions, headings.length);
        });
        size.addEventListener('change', function () {
            state.pageSize = Number(size.value) || 10;
            state.page = 1;
            renderTable(state, body, meta, pageInfo, pageActions, headings.length);
        });
        density.addEventListener('click', function () {
            var compact = container.classList.toggle('dagril-table-compact');
            density.setAttribute('aria-label', compact ? 'Activer la vue confortable' : 'Activer la vue compacte');
            density.innerHTML = '';
            density.appendChild(icon(compact ? 'bi-list-ul' : 'bi-list'));
        });
        pageActions.addEventListener('click', function (event) {
            var button = event.target.closest('[data-page]');
            if (!button || button.disabled) {
                return;
            }
            state.page = Number(button.dataset.page) || 1;
            renderTable(state, body, meta, pageInfo, pageActions, headings.length);
        });

        if (table.hasAttribute('data-daily-table')) {
            state.rowFilter = function (row) { return row.dataset.batch === table.dataset.selectedBatch; };
            table.addEventListener('daily-filter', function () {
                state.page = 1;
                state.query = '';
                searchInput.value = '';
                renderTable(state, body, meta, pageInfo, pageActions, headings.length);
            });
        }
        if (table.hasAttribute('data-conversion-filters') || table.hasAttribute('data-butchery-history')) {
            var historyKind = table.getAttribute('data-butchery-history');
            toolbar.classList.add('conversion-filterbar');
            searchInput.placeholder = historyKind ? 'Référence, origine…' : 'Référence, lot ou site…';
            searchInput.setAttribute('aria-label', historyKind ? 'Rechercher dans l’historique' : 'Rechercher une conversion');
            var filterFields = {};
            function addFilter(name, title, options) {
                var label = document.createElement('label');
                label.className = 'conversion-filter';
                var caption = document.createElement('span');
                caption.textContent = title;
                var field = document.createElement(options ? 'select' : 'input');
                field.dataset.conversionFilter = name;
                if (options) options.forEach(function (item) { field.add(new Option(item[1], item[0])); });
                else field.type = 'date';
                label.appendChild(caption);
                label.appendChild(field);
                toolbar.appendChild(label);
                filterFields[name] = field;
                field.addEventListener('change', applyFilters);
            }
            if (historyKind) {
                var historyStatuses = historyKind === 'receipts'
                    ? [['','Tous les statuts'],['draft','Brouillon'],['sanitary_pending','À contrôler'],['accepted','Acceptée'],['rejected','Refusée'],['cancelled','Annulée']]
                    : [['','Tous les statuts'],['submitted','À valider'],['validated','Validé'],['cancelled','Annulé']];
                if(historyKind==='production')historyStatuses=[['','Tous les statuts'],['in_progress','En cours'],['results_submitted','À valider'],['validated','Validé'],['cancelled','Annulé']];
                if(historyKind==='stocks')historyStatuses=[['','Tous les statuts'],['available','Disponible'],['reserved','Réservé'],['expired','DLC dépassée'],['depleted','Épuisé'],['quarantine','Quarantaine'],['rejected','Refusé'],['cancelled','Annulé'],['in_transit','En transit']];
                if(historyKind==='sales')historyStatuses=[['','Tous les statuts'],['validated','Validée'],['cancelled','Annulée']];
                if(historyKind==='transfers')historyStatuses=[['','Tous les statuts'],['draft','Brouillon'],['approved','Approuvé'],['in_transit','En transit'],['received','Reçu'],['cancelled','Annulé']];
                if(historyKind==='inter-site') {
                    historyStatuses=[['','Tous les statuts']];
                    try { historyStatuses=historyStatuses.concat(JSON.parse(table.getAttribute('data-status-options') || '[]')); } catch (e) {}
                    addFilter('type','Type',[['','Tous les types'],['inter_site','Inter-site'],['internal','Interne']]);
                }
                if(historyKind==='distributions')historyStatuses=[['','Tous les statuts'],['validated','Validée'],['cancelled','Annulée'],['draft','Brouillon']];
                if(historyKind==='fuel-orders')historyStatuses=[['','Tous les statuts'],['draft','Brouillon'],['submitted','À valider'],['approved','Approuvé'],['partially_received','Réception partielle'],['received','Reçu'],['cancelled','Annulé']];
                if(historyKind==='fuel-missions')historyStatuses=[['','Tous les statuts'],['submitted','À valider'],['approved','Approuvé'],['in_progress','En cours'],['completed','Terminé'],['justification_pending','À justifier'],['settled','Soldé'],['cancelled','Annulé']];
                if(historyKind==='recipes')historyStatuses=[['','Tous les statuts'],['active','Active'],['retired','Ancienne version'],['inactive','Fiche inactive']];
                addFilter('status', 'Statut', historyStatuses);
                if(historyKind==='stocks'){addFilter('type','Type',[['','Tous les types'],['raw','Matières premières'],['finished','Produits et coproduits']]);addFilter('dlc','Échéance',[['','Toutes les DLC'],['soon','Sous 3 jours'],['expired','Dépassée'],['valid','Plus de 3 jours']]);}
                if (historyKind === 'receipts') addFilter('type', 'Origine', [['','Toutes les origines'],['internal_btr','Élevage'],['external_bra','Fournisseur']]);
            } else {
                addFilter('status', 'Statut', [['','Tous les statuts'],['submitted','À valider'],['validated','Validé']]);
                addFilter('type', 'Opération', [['','Toutes les opérations'],['slaughter','Abattage'],['fish_catch','Pêche'],['formal_weighing','Pesée de conversion']]);
            }
            addFilter('from', historyKind==='stocks'?'DLC du':'Du', null);
            addFilter('to', historyKind==='stocks'?'DLC au':'Au', null);
            var reset = document.createElement('button');
            reset.type = 'button';
            reset.className = 'btn-secondary conversion-filter-reset';
            reset.appendChild(icon('bi-arrow-counterclockwise'));
            reset.appendChild(escapeText('Réinitialiser'));
            toolbar.appendChild(reset);
            var periodError = document.createElement('p');
            periodError.className = 'conversion-filter-error';
            periodError.setAttribute('role', 'status');
            periodError.hidden = true;
            toolbar.appendChild(periodError);
            // Display preferences belong immediately above the table.
            var displayBar = document.createElement('div');
            displayBar.className = 'conversion-displaybar';
            displayBar.appendChild(meta);
            sizeLabel.className = 'conversion-page-size';
            sizeLabel.textContent = 'Lignes à afficher';
            sizeLabel.appendChild(size);
            displayBar.appendChild(sizeLabel);
            container.insertBefore(displayBar, scroll);
            // The compact layout is fixed here, so no extra density control is needed.
            density.remove();
            container.classList.add('dagril-table-compact');
            function applyFilters() {
                var status = filterFields.status.value, type = filterFields.type ? filterFields.type.value : '';
                var from = filterFields.from.value, to = filterFields.to.value;
                var invalid = !!(from && to && from > to);
                periodError.hidden = !invalid;
                periodError.textContent = invalid ? 'La date de fin doit être égale ou postérieure à la date de début.' : '';
                filterFields.to.setAttribute('aria-invalid', String(invalid));
                state.rowFilter = function (row) {
                    return !invalid && (!filterFields.dlc || !filterFields.dlc.value || row.dataset.dlc === filterFields.dlc.value) && (!status || row.dataset.status === status) &&
                        (!type || row.dataset.type === type) &&
                        (!from || row.dataset.date >= from) && (!to || row.dataset.date <= to);
                };
                state.page = 1;
                renderTable(state, body, meta, pageInfo, pageActions, headings.length);
            }
            reset.addEventListener('click', function () {
                Object.keys(filterFields).forEach(function (key) { filterFields[key].value = ''; });
                searchInput.value = '';
                state.query = '';
                applyFilters();
                searchInput.focus();
            });
        }
        renderTable(state, body, meta, pageInfo, pageActions, headings.length);
    }

    function renderTable(state, body, meta, pageInfo, pageActions, columnCount) {
        if (state.emptyRow && state.emptyRow.parentNode) {
            state.emptyRow.parentNode.removeChild(state.emptyRow);
        }
        state.emptyRow = null;

        var filtered = state.rows.filter(function (row) {
            return (!state.rowFilter || state.rowFilter(row)) && (!state.query || normalize(row.textContent).indexOf(state.query) !== -1);
        });

        if (state.sortIndex !== null) {
            filtered.sort(function (left, right) {
                var a = cellValue(left.cells[state.sortIndex]);
                var b = cellValue(right.cells[state.sortIndex]);
                if (typeof a === 'number' && typeof b === 'number') {
                    return (a - b) * state.sortDirection;
                }
                return String(a).localeCompare(String(b), 'fr', { numeric: true, sensitivity: 'base' }) * state.sortDirection;
            });
        }

        var totalPages = Math.max(1, Math.ceil(filtered.length / state.pageSize));
        state.page = Math.min(state.page, totalPages);
        var first = (state.page - 1) * state.pageSize;
        var last = Math.min(first + state.pageSize, filtered.length);
        var visibleRows = filtered.slice(first, last);

        state.rows.forEach(function (row) { row.hidden = true; });
        filtered.forEach(function (row) { body.appendChild(row); });
        visibleRows.forEach(function (row) { row.hidden = false; });

        if (!filtered.length) {
            state.emptyRow = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyCell.colSpan = Math.max(1, columnCount);
            emptyCell.className = 'dagril-table-empty';
            emptyCell.appendChild(icon('bi-search'));
            emptyCell.appendChild(escapeText(body.closest('table').hasAttribute('data-daily-table') && !state.query ? ' Aucun enregistrement pour ce lot.' : ' Aucun resultat ne correspond a votre recherche.'));
            state.emptyRow.appendChild(emptyCell);
            body.appendChild(state.emptyRow);
        }

        meta.textContent = filtered.length + ' resultat' + (filtered.length > 1 ? 's' : '');
        pageInfo.textContent = filtered.length ? 'Affichage ' + (first + 1) + '–' + last + ' sur ' + filtered.length : 'Aucun resultat';
        renderPagination(pageActions, state.page, totalPages);
    }

    function renderPagination(container, current, total) {
        container.innerHTML = '';
        container.appendChild(pageButton('bi-chevron-left', Math.max(1, current - 1), current === 1, 'Page precedente'));
        var start = Math.max(1, current - 1);
        var end = Math.min(total, start + 2);
        start = Math.max(1, end - 2);
        for (var page = start; page <= end; page++) {
            var button = pageButton(String(page), page, false, 'Page ' + page, true);
            if (page === current) {
                button.classList.add('is-current');
                button.setAttribute('aria-current', 'page');
            }
            container.appendChild(button);
        }
        container.appendChild(pageButton('bi-chevron-right', Math.min(total, current + 1), current === total, 'Page suivante'));
    }

    function pageButton(content, page, disabled, label, plainText) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'dagril-page-button';
        button.dataset.page = String(page);
        button.disabled = disabled;
        button.setAttribute('aria-label', label);
        if (plainText) {
            button.textContent = content;
        } else {
            button.appendChild(icon(content));
        }
        return button;
    }

    function cellValue(cell) {
        if (!cell) {
            return '';
        }
        var explicit = cell.getAttribute('data-order');
        var raw = (explicit !== null ? explicit : cell.textContent).trim();
        var dateValue = Date.parse(raw.replace(/(\d{2})\/(\d{2})\/(\d{4})/, '$3-$2-$1'));
        if (/\d{4}-\d{2}-\d{2}|\d{2}\/\d{2}\/\d{4}/.test(raw) && !isNaN(dateValue)) {
            return dateValue;
        }
        var numeric = raw.replace(/\s/g, '').replace(',', '.').match(/^-?\d+(?:\.\d+)?/);
        if (numeric) {
            return Number(numeric[0]);
        }
        return normalize(raw);
    }

    function shouldAjax(form) {
        if (!form || String(form.method || '').toLowerCase() !== 'post') {
            return false;
        }
        if (!form.closest('.app-shell') || form.dataset.ajax === 'false' || form.hasAttribute('data-native-submit')) {
            return false;
        }
        return typeof window.fetch === 'function' && typeof window.FormData === 'function';
    }

    function openActionDialog(form, submitter) {
        closeCommandPalette();
        pendingForm = form;
        pendingSubmitter = submitter;
        dialogReturnFocus = submitter || document.activeElement;
        var dialog = qs('[data-action-dialog]');
        var title = operationName(form, submitter);
        qs('[data-action-dialog-title]').textContent = title;
        qs('[data-action-dialog-description]').textContent = (submitter && submitter.getAttribute('data-confirm')) || form.getAttribute('data-confirm') || 'Vérifiez le résumé avant de lancer cette opération.';
        renderOperationSummary(form);
        setShellInert(true);
        document.body.classList.add('action-dialog-open');
        dialog.setAttribute('aria-hidden', 'false');
        var confirm = qs('[data-action-dialog-confirm]');
        confirm.disabled = false;
        confirm.removeAttribute('aria-busy');
        confirm.innerHTML = '<i class="bi bi-check2"></i><span>Confirmer et exécuter</span>';
        window.setTimeout(function () { confirm.focus(); }, 0);
    }

    function closeActionDialog(restoreFocus) {
        if (requestInFlight) {
            return;
        }
        var dialog = qs('[data-action-dialog]');
        document.body.classList.remove('action-dialog-open');
        if (dialog) {
            dialog.setAttribute('aria-hidden', 'true');
        }
        setShellInert(false);
        pendingForm = null;
        pendingSubmitter = null;
        if (restoreFocus && dialogReturnFocus && document.contains(dialogReturnFocus)) {
            dialogReturnFocus.focus();
        }
    }

    function operationName(form, submitter) {
        var text = submitter ? submitter.textContent.trim() : '';
        if (!text && submitter) {
            text = submitter.getAttribute('aria-label') || submitter.getAttribute('title') || submitter.value || '';
        }
        if (!text) {
            var button = form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
            text = button ? (button.textContent || button.getAttribute('aria-label') || button.getAttribute('title') || button.value || '').trim() : '';
        }
        return text || form.getAttribute('data-confirm-title') || 'Confirmer l’opération';
    }

    function renderOperationSummary(form) {
        var summary = qs('[data-operation-summary]');
        summary.innerHTML = '';
        var entries = [];
        var elements = Array.prototype.slice.call(form.elements || []);
        elements.forEach(function (field) {
            var name = field.name || '';
            var type = String(field.type || '').toLowerCase();
            if (!name || /^(_token|idempotency_key|password)$/i.test(name) || ['submit', 'button', 'reset', 'hidden'].indexOf(type) !== -1 || field.disabled) {
                return;
            }
            if ((type === 'checkbox' || type === 'radio') && !field.checked) {
                return;
            }
            var value = fieldValue(field);
            if (!value) {
                return;
            }
            entries.push({ label: fieldLabel(field), value: value });
        });

        if (entries.length < 2) {
            var row = form.closest('tr');
            if (row) {
                var table = row.closest('table');
                var labels = table && table.tHead ? Array.prototype.map.call(table.tHead.querySelectorAll('th'), function (th) { return th.textContent.trim(); }) : [];
                Array.prototype.slice.call(row.cells, 0, 4).forEach(function (cell, index) {
                    var value = cell.textContent.trim().replace(/\s+/g, ' ');
                    if (value && !/action/i.test(labels[index] || '')) {
                        entries.push({ label: labels[index] || 'Contexte', value: value.substring(0, 100) });
                    }
                });
            }
            if (!row) {
                var context = form.closest('article, .alert-row, .list-row, .metric-card');
                var contextText = context ? context.textContent.trim().replace(/\s+/g, ' ') : '';
                if (contextText) {
                    entries.push({ label: 'Contexte', value: contextText.substring(0, 140) });
                }
            }
        }

        entries = entries.slice(0, 8);
        entries.unshift({ label: 'Operation', value: operationName(form, pendingSubmitter) });
        entries.forEach(function (entry) {
            var item = document.createElement('div');
            var term = document.createElement('dt');
            var value = document.createElement('dd');
            term.appendChild(escapeText(entry.label));
            value.appendChild(escapeText(entry.value));
            item.appendChild(term);
            item.appendChild(value);
            summary.appendChild(item);
        });
    }

    function fieldValue(field) {
        var type = String(field.type || '').toLowerCase();
        if (type === 'file') {
            return field.files && field.files.length ? Array.prototype.map.call(field.files, function (file) { return file.name; }).join(', ') : '';
        }
        if (field.tagName === 'SELECT') {
            return Array.prototype.filter.call(field.options, function (option) { return option.selected; }).map(function (option) { return option.textContent.trim(); }).join(', ');
        }
        if (type === 'checkbox' || type === 'radio') {
            return field.value || 'Oui';
        }
        return String(field.value || '').trim();
    }

    function fieldLabel(field) {
        var label = null;
        if (field.id) {
            label = document.querySelector('label[for="' + cssEscape(field.id) + '"]');
        }
        label = label || field.closest('label');
        var text = label ? label.textContent.trim().replace(/\s+/g, ' ') : '';
        if (field.tagName === 'SELECT' && label) {
            var span = label.querySelector('span');
            text = span ? span.textContent.trim() : text;
        }
        return text || field.getAttribute('placeholder') || humanize(field.name);
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }
        return String(value).replace(/([ #;?%&,.+*~\':"!^$[\]()=>|/@])/g, '\\$1');
    }

    function humanize(value) {
        value = String(value || '').replace(/\[.*?\]/g, '').replace(/[_-]+/g, ' ');
        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    function executePendingForm() {
        if (!pendingForm || requestInFlight) {
            return;
        }
        submitAjax(pendingForm, pendingSubmitter);
    }

    function submitAjax(form, submitter) {
        requestInFlight = true;
        form.dataset.ajaxBusy = 'true';
        form.classList.add('is-submitting');
        form.setAttribute('aria-busy', 'true');
        document.body.classList.add('is-loading');

        var confirm = qs('[data-action-dialog-confirm]');
        confirm.disabled = true;
        confirm.setAttribute('aria-busy', 'true');
        confirm.innerHTML = '<span class="button-spinner" aria-hidden="true"></span><span>Traitement en cours…</span>';

        var data = new FormData(form);
        if (submitter && submitter.name) {
            data.append(submitter.name, submitter.value || '1');
        }

        var submissionUrl = submitter && submitter.getAttribute('formaction') || form.action;
        fetch(submissionUrl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            redirect: 'follow',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html, application/json;q=0.9'
            }
        }).then(function (response) {
            var contentType = response.headers.get('content-type') || '';
            if (contentType.indexOf('application/json') !== -1) {
                return response.json().then(function (payload) { return handleJsonResponse(response, payload); });
            }
            return response.text().then(function (html) { return handleHtmlResponse(response, html); });
        }).catch(function () {
            showToast('État à vérifier', 'La réponse du serveur n’a pas pu être confirmée. Aucune relance automatique ne sera effectuée.', 'error');
        }).then(function () {
            finishRequest(form);
        });
    }

    function handleJsonResponse(response, payload) {
        if (response.status === 401 || response.status === 419) {
            showToast('Session à renouveler', 'Votre session ou votre jeton de sécurité a expiré. Rechargez la page avant de continuer.', 'error');
            return;
        }
        if (!response.ok || !payload || payload.ok === false) {
            if(pendingForm&&pendingForm.matches('[data-feed-form],[data-machine-form],[data-prod-form],[data-waste-sale-form],[data-waste-process-form],[data-pellet-form],[data-audit-review],[data-empty-form],[data-pack-editor-form],[data-livestock-form],[data-butchery-form]')){var feedError=qs('[data-feed-error]',pendingForm);feedError.textContent=payload&&payload.message?payload.message:'L’alimentation n’a pas été enregistrée.';feedError.hidden=false;feedError.scrollIntoView({block:'nearest'});}
            showToast('Opération refusée', payload && payload.message ? payload.message : 'Le serveur a refusé cette opération.', 'error');
            return;
        }
        if (payload.redirect_url || payload.refresh_url) {
            return fetchAndSwap(payload.refresh_url || payload.redirect_url, payload.message || '');
        }
        showToast('Opération terminée', payload.message || 'Les données ont été actualisées.', 'success');
    }

    function handleHtmlResponse(response, html) {
        var parsed = new DOMParser().parseFromString(html, 'text/html');
        if (parsed.querySelector('.auth-shell')) {
            window.location.assign(response.url);
            return;
        }
        var incoming = parsed.querySelector('.content-area');
        var current = qs('.content-area');
        if (!response.ok || !incoming || !current) {
            var plainMessage = parsed.body ? parsed.body.textContent.trim() : '';
            showToast('Opération non exécutée', plainMessage.substring(0, 220) || 'Réponse inattendue du serveur.', 'error');
            return;
        }

        var responseMarker = incoming.querySelector('[data-response-flash]');
        var responseKind = responseMarker ? responseMarker.getAttribute('data-response-flash') : '';
        var responseMessage = responseMarker ? responseMarker.getAttribute('data-response-message') || '' : '';

        var incomingAgriculture=parsed.querySelector('[data-agriculture-section]');var currentAgriculture=qs('[data-agriculture-section]');
        if(incomingAgriculture&&currentAgriculture&&incomingAgriculture.dataset.agricultureSection===currentAgriculture.dataset.agricultureSection&&incomingAgriculture.dataset.agricultureSection!=='overview'){
            var section=incomingAgriculture.dataset.agricultureSection;var directoryIds={campaigns:'agriCampaigns',plots:'agriPlanning',planning:'agriPlanning',inputs:'agriExtraDirectory',works:'agriExtraDirectory',harvests:'agriHarvests',stocks:'agriExtraDirectory',transports:'agriTransports',workers:'agriExtraDirectory',equipment:'agriExtraDirectory'};var id=directoryIds[section];var incomingDirectory=id?incomingAgriculture.querySelector('#'+id):null;var currentDirectory=id?currentAgriculture.querySelector('#'+id):null;
            if(response.ok&&incomingDirectory&&currentDirectory){
                if(section==='campaigns'||section==='plots'){
                    (section==='plots'?['#plotModal','#planModal']:['#campaignModal','#planModal']).forEach(function(selector){var next=incomingAgriculture.querySelector(selector);var previous=currentAgriculture.querySelector(selector);if(next&&previous){previous.replaceWith(next);hydrate(next);}});
                }
                if(section==='harvests'){var nextHarvest=incomingAgriculture.querySelector('#harvestModal'),previousHarvest=currentAgriculture.querySelector('#harvestModal');if(nextHarvest&&previousHarvest){previousHarvest.replaceWith(nextHarvest);hydrate(nextHarvest);}}
                if(section==='stocks'){var nextTransport=incomingAgriculture.querySelector('#transportModal'),previousTransport=currentAgriculture.querySelector('#transportModal');if(nextTransport&&previousTransport){previousTransport.replaceWith(nextTransport);hydrate(nextTransport);}}
                if(section==='transports'){
                    ['.transport-summary','#transportModal'].forEach(function(selector){
                        var next=incomingAgriculture.querySelector(selector);var previous=currentAgriculture.querySelector(selector);
                        if(next&&previous){previous.replaceWith(next);hydrate(next);}
                    });
                }
                currentDirectory.replaceWith(incomingDirectory);incomingDirectory.hidden=false;hydrate(incomingDirectory);syncShell(parsed,response.url);if(responseKind==='error'){showToast('Opération refusée',responseMessage||'Le serveur a refusé cette opération.','error');}else{showToast('Opération réussie',responseMessage||'Le tableau a été actualisé sans recharger la page.','success');}return;}
        }

        var nextProduction=incoming.querySelector('[data-production-directory]'),oldProduction=current.querySelector('[data-production-directory]');
        if(response.ok&&nextProduction&&oldProduction){var productionSaved=pendingForm&&pendingForm.matches('[data-prod-form]');var listScroll=productionSaved?Number(pendingForm.dataset.returnScroll||0):window.scrollY;oldProduction.replaceWith(nextProduction);hydrate(nextProduction);syncShell(parsed,response.url);if(productionSaved&&window.showProductionNext)window.showProductionNext(nextProduction);window.scrollTo({top:listScroll,behavior:'instant'});return;}

        current.innerHTML = incoming.innerHTML;
        current.removeAttribute('aria-busy');
        document.title = parsed.title || document.title;
        syncShell(parsed, response.url);
        hydrate(current);
        window.scrollTo({ top: 0, behavior: 'smooth' });

        var error = current.querySelector('.alert-danger, .alert.danger, .alert-error, [data-form-errors]');
        var success = current.querySelector('.app-alert-success, .alert-success, .alert.success');
        if (responseKind === 'error') {
            showToast('Opération refusée', responseMessage || (error ? error.textContent.trim() : 'Le serveur a refusé cette opération.'), 'error');
        } else if (responseKind === 'success') {
            showToast('Opération terminée', responseMessage || (success ? success.textContent.trim() : 'Les données ont été actualisées.'), 'success');
            if (success && responseMessage && normalize(success.textContent) === normalize(responseMessage)) success.remove();
        } else if (error) {
            showToast('Opération refusée', error.textContent.trim(), 'error');
        } else {
            showToast('Opération terminée', success ? success.textContent.trim() : 'Les données ont été actualisées sans recharger la page.', 'success');
        }
        current.focus({ preventScroll: true });
    }

    function fetchAndSwap(url, successMessage) {
        return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                return response.text().then(function (html) {
                    handleHtmlResponse(response, html);
                    if (response.ok && successMessage) showToast('Opération terminée', successMessage, 'success');
                });
            });
    }

    function syncShell(parsed, responseUrl) {
        var incomingTitle = parsed.querySelector('.page-title');
        var currentTitle = qs('.page-title');
        if (incomingTitle && currentTitle) {
            currentTitle.innerHTML = incomingTitle.innerHTML;
        }
        var activeIncoming = parsed.querySelector('.sidebar a.active');
        qsa('.sidebar a.active').forEach(function (link) {
            link.classList.remove('active');
            link.removeAttribute('aria-current');
        });
        if (activeIncoming) {
            var href = activeIncoming.getAttribute('href');
            var activeCurrent = href ? qs('.sidebar a[href="' + cssEscape(href) + '"]') : null;
            if (activeCurrent) {
                activeCurrent.classList.add('active');
                activeCurrent.setAttribute('aria-current', 'page');
            }
        }
        var incomingCurrentSection = activeIncoming ? activeIncoming.closest('[data-menu-section]') : null;
        var incomingSections = qsa('[data-menu-section]', parsed);
        var incomingGroupIndex = incomingCurrentSection ? incomingSections.indexOf(incomingCurrentSection) : -1;
        qsa('.sidebar [data-menu-section]').forEach(function (section, index) {
            var isCurrent = index === incomingGroupIndex;
            section.classList.toggle('is-current', isCurrent);
            var toggle = qs('[data-section-toggle]', section);
            if (toggle) toggle.setAttribute('aria-expanded', isCurrent ? 'true' : 'false');
        });
        qsa('.sidebar [data-command-link]').forEach(function (currentLink) {
            var incomingLink = qsa('.sidebar [data-command-link]', parsed).filter(function (candidate) {
                return candidate.getAttribute('href') === currentLink.getAttribute('href');
            })[0];
            if (!incomingLink) return;
            var incomingBadge = incomingLink.querySelector('.nav-badge');
            var currentBadge = currentLink.querySelector('.nav-badge');
            if (incomingBadge && currentBadge) currentBadge.textContent = incomingBadge.textContent;
            else if (!incomingBadge && currentBadge) currentBadge.remove();
            else if (incomingBadge && !currentBadge) currentLink.appendChild(incomingBadge.cloneNode(true));
        });
        if (responseUrl && responseUrl !== window.location.href) {
            try {
                var target = new URL(responseUrl, window.location.href);
                if (target.origin === window.location.origin) {
                    window.history.pushState({ dagrilAjax: true }, '', target.href);
                }
            } catch (ignore) {}
        }
    }

    function finishRequest(form) {
        requestInFlight = false;
        document.body.classList.remove('is-loading', 'action-dialog-open', 'user-modal-open', 'site-modal-open', 'workspace-modal-open');
        qsa('.site-entity-modal.is-open').forEach(function (modal) { modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true'); });
        var siteBackdrop = qs('[data-site-modal-backdrop]');if (siteBackdrop) siteBackdrop.classList.remove('is-open');
        var dialog = qs('[data-action-dialog]');
        if (dialog) {
            dialog.setAttribute('aria-hidden', 'true');
        }
        setShellInert(false);
        if (form && document.contains(form)) {
            delete form.dataset.ajaxBusy;
            form.classList.remove('is-submitting');
            form.removeAttribute('aria-busy');
        }
        if(form&&document.contains(form)&&form.matches('[data-feed-form],[data-machine-form],[data-prod-form],[data-waste-sale-form],[data-waste-process-form],[data-pellet-form],[data-audit-review],[data-empty-form],[data-pack-editor-form],[data-livestock-form],[data-butchery-form]')&&form.closest('.is-open'))document.body.classList.add('workspace-modal-open');
        pendingForm = null;
        pendingSubmitter = null;
        if (dialogReturnFocus && document.contains(dialogReturnFocus)) {
            dialogReturnFocus.focus({ preventScroll: true });
        } else {
            var content = qs('.content-area');
            if (content) content.focus({ preventScroll: true });
        }
        dialogReturnFocus = null;
    }

    function showToast(title, message, tone) {
        var region = qs('[data-toast-region]');
        if (!region) {
            return;
        }
        region.innerHTML = '';
        var toast = document.createElement('div');
        toast.className = 'dagril-toast is-' + (tone || 'info');
        toast.setAttribute('role', tone === 'error' ? 'alert' : 'status');
        var toastIcon = document.createElement('span');
        toastIcon.className = 'dagril-toast-icon';
        toastIcon.appendChild(icon(tone === 'error' ? 'bi-exclamation-triangle' : 'bi-check2'));
        var copy = document.createElement('div');
        var strong = document.createElement('strong');
        var detail = document.createElement('span');
        strong.appendChild(escapeText(title));
        detail.appendChild(escapeText(message));
        copy.appendChild(strong);
        copy.appendChild(detail);
        var close = document.createElement('button');
        close.type = 'button';
        close.setAttribute('aria-label', 'Fermer la notification');
        close.appendChild(icon('bi-x'));
        close.addEventListener('click', function () { dismissToast(toast); });
        toast.appendChild(toastIcon);
        toast.appendChild(copy);
        toast.appendChild(close);
        region.appendChild(toast);
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () { dismissToast(toast); }, tone === 'error' ? 9000 : 5200);
    }

    function dismissToast(toast) {
        if (!toast || !toast.parentNode) {
            return;
        }
        toast.classList.add('is-leaving');
        window.setTimeout(function () {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 200);
    }

    function openCommandPalette() {
        var palette = qs('[data-command-palette]');
        var input = qs('[data-command-input]');
        if (!palette || !input || document.body.classList.contains('action-dialog-open')) {
            return;
        }
        commandReturnFocus = document.activeElement;
        buildCommandResults('');
        input.value = '';
        setShellInert(true);
        document.body.classList.add('command-palette-open');
        palette.setAttribute('aria-hidden', 'false');
        window.setTimeout(function () { input.focus(); }, 0);
    }

    function closeCommandPalette() {
        var palette = qs('[data-command-palette]');
        var wasOpen = document.body.classList.contains('command-palette-open');
        document.body.classList.remove('command-palette-open');
        if (palette) {
            palette.setAttribute('aria-hidden', 'true');
        }
        if (!document.body.classList.contains('action-dialog-open')) setShellInert(false);
        if (wasOpen && commandReturnFocus && document.contains(commandReturnFocus)) {
            commandReturnFocus.focus();
        }
        if (wasOpen) commandReturnFocus = null;
    }

    function buildCommandResults(query) {
        var results = qs('[data-command-results]');
        var empty = qs('[data-command-empty]');
        if (!results) {
            return;
        }
        results.innerHTML = '';
        var matches = qsa('[data-command-link]').filter(function (link) {
            return !query || normalize((link.dataset.commandGroup || '') + ' ' + (link.dataset.commandLabel || '')).indexOf(normalize(query)) !== -1;
        }).slice(0, 14);
        matches.forEach(function (source) {
            var link = document.createElement('a');
            link.className = 'command-result';
            link.href = source.href;
            link.appendChild(icon(source.dataset.commandIcon || 'bi-circle'));
            var copy = document.createElement('span');
            var title = document.createElement('strong');
            var group = document.createElement('small');
            title.appendChild(escapeText(source.dataset.commandLabel || source.textContent.trim()));
            group.appendChild(escapeText(source.dataset.commandGroup || 'Module'));
            copy.appendChild(title);
            copy.appendChild(group);
            link.appendChild(copy);
            link.appendChild(icon('bi-arrow-right-short'));
            results.appendChild(link);
        });
        if (empty) {
            empty.hidden = matches.length > 0;
        }
    }

    function trapFocus(event, container) {
        if (event.key !== 'Tab' || !container) {
            return;
        }
        var focusable = qsa('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])', container)
            .filter(function (element) { return element.offsetParent !== null; });
        if (!focusable.length) {
            return;
        }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (!container.contains(document.activeElement)) {
            event.preventDefault();
            (event.shiftKey ? last : first).focus();
            return;
        }
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!shouldAjax(form) || form.dataset.ajaxBusy === 'true') {
            if (form && form.dataset.ajaxBusy === 'true') {
                event.preventDefault();
            }
            return;
        }
        if (!form.checkValidity()) {
            event.preventDefault();
            form.classList.add('was-validated');
            if (typeof form.reportValidity === 'function') {
                form.reportValidity();
            }
            return;
        }
        event.preventDefault();
        openActionDialog(form, event.submitter || null);
    });

    document.addEventListener('click', function (event) {
        var ticketPreviewOpen = event.target.closest('[data-ticket-preview-open]');
        var ticketPreview = qs('[data-ticket-preview]');
        if (ticketPreviewOpen && ticketPreview) {
            var ticketFrame = qs('iframe', ticketPreview);
            if (!ticketFrame.hasAttribute('src')) ticketFrame.src = ticketFrame.dataset.src;
            ticketPreview.showModal();
            return;
        }
        if (ticketPreview && ticketPreview.open) {
            var bounds = ticketPreview.getBoundingClientRect();
            var outside = event.target === ticketPreview && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom);
            if (event.target.closest('[data-ticket-preview-close]') || outside) {
                ticketPreview.close();
                return;
            }
        }
        var sidebarTrigger = event.target.closest('[data-sidebar-toggle]');
        var sidebarClose = event.target.closest('[data-sidebar-close], .sidebar-nav a');
        if (sidebarTrigger || sidebarClose) {
            syncSidebarToggle(Boolean(sidebarTrigger));
        }
        var sectionToggle = event.target.closest('[data-section-toggle]');
        if (sectionToggle) {
            var willOpen = sectionToggle.getAttribute('aria-expanded') !== 'true';
            if (willOpen) {
                document.querySelectorAll('[data-section-toggle]').forEach(function (toggle) {
                    if (toggle !== sectionToggle) toggle.setAttribute('aria-expanded', 'false');
                });
            }
            sectionToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            return;
        }
        var userCreate = event.target.closest('[data-user-create]');
        if (userCreate) { openUserModal(userCreate, null);return; }
        var userEdit = event.target.closest('[data-user-edit]');
        if (userEdit) {
            try { openUserModal(userEdit, JSON.parse(userEdit.getAttribute('data-user') || '{}')); }
            catch (ignore) { showToast('Données indisponibles','Impossible d’ouvrir cet utilisateur.','error'); }
            return;
        }
        if (event.target.closest('[data-user-modal-close]')) { closeUserModal(true);return; }
        var siteOpen = event.target.closest('[data-site-modal-open]');
        if (siteOpen) { if (siteOpen.getAttribute('data-site-modal-open') === 'siteEditor') openSiteEditor(siteOpen, null);else openSiteModal(siteOpen.getAttribute('data-site-modal-open'), siteOpen);return; }
        var siteEdit = event.target.closest('[data-site-edit]');
        if (siteEdit) { try { openSiteEditor(siteEdit, JSON.parse(siteEdit.getAttribute('data-site') || '{}')); } catch (ignore) { showToast('Données indisponibles','Impossible d’ouvrir ce site.','error'); }return; }
        var siteAssignment = event.target.closest('[data-site-assignment]');
        if (siteAssignment) { try { openSiteAssignment(siteAssignment, JSON.parse(siteAssignment.getAttribute('data-assignment') || '{}')); } catch (ignore) { showToast('Données indisponibles','Impossible d’ouvrir cette affectation.','error'); }return; }
        if (event.target.closest('[data-site-modal-close], [data-site-modal-backdrop]')) { closeSiteModal(true);return; }
        var workspaceOpen=event.target.closest('[data-workspace-modal-open]');if(workspaceOpen){openWorkspaceModal(workspaceOpen.getAttribute('data-workspace-modal-open'),workspaceOpen);return;}
        if(event.target.closest('[data-workspace-modal-close]')){closeWorkspaceModal(true);return;}
        var actionMenuToggle=event.target.closest('[data-action-menu-toggle]');if(actionMenuToggle){toggleActionMenu(actionMenuToggle);return;}
        if(!event.target.closest('[data-action-menu]'))closeActionMenus();
        if (event.target.closest('[data-action-dialog-confirm]')) {
            executePendingForm();
            return;
        }
        if (event.target.closest('[data-action-dialog-close]')) {
            closeActionDialog(true);
            return;
        }
        if (event.target.closest('[data-command-open]')) {
            openCommandPalette();
            return;
        }
        if (event.target.closest('[data-command-close]')) {
            closeCommandPalette();
            return;
        }
        if (event.target.closest('.command-result')) {
            closeCommandPalette();
        }
    });

    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-uppercase]')) {
            event.target.value = event.target.value.toUpperCase();
        }
        var businessForm = event.target.closest('[data-weighing-exit], [data-production-form], [data-waste-form], [data-packaging-form], [data-distribution-form]');
        if (businessForm) updateBusinessForm(businessForm);
        if (event.target.matches('[data-command-input]')) {
            buildCommandResults(event.target.value);
        }
    });

    document.addEventListener('change', function (event) {
        if(event.target.matches('[data-campaign-site]'))syncCampaignSuggestedCode(event.target);
        if(event.target.matches('[data-plot-site]'))syncPlotSuggestedCode(event.target);
        if(event.target.matches('[data-farm-code-site]'))syncFarmSuggestedCode(event.target);
        if(event.target.matches('[data-plan-site]'))syncPlanSite(event.target);
        if(event.target.closest('#harvestModal'))updateHarvestNet(event.target.form);
        if(event.target.matches('[data-harvest-plan]'))updateHarvestPlanSummary(event.target);
        if(event.target.matches('[data-plan-plot-select]'))updatePlotContext(event.target);
        var businessForm = event.target.closest('[data-weighing-exit], [data-production-form], [data-waste-form], [data-packaging-form], [data-distribution-form]');
        if (businessForm) updateBusinessForm(businessForm);
    });

    document.addEventListener('keydown', function (event) {
        if ((event.metaKey || event.ctrlKey) && String(event.key).toLowerCase() === 'k') {
            event.preventDefault();
            openCommandPalette();
            return;
        }
        if (event.key === 'Escape') {
            closeActionMenus();
            syncSidebarToggle(false);
            if (document.body.classList.contains('action-dialog-open')) {
                closeActionDialog(true);
            } else if (document.body.classList.contains('command-palette-open')) {
                closeCommandPalette();
            } else if (document.body.classList.contains('user-modal-open')) {
                closeUserModal(true);
            } else if (document.body.classList.contains('site-modal-open')) {
                closeSiteModal(true);
            } else if (document.body.classList.contains('workspace-modal-open')) {
                closeWorkspaceModal(true);
            } else if (document.body.classList.contains('modal-open')) {
                closeNotificationModal();
            }
        }
        if (document.body.classList.contains('action-dialog-open')) {
            trapFocus(event, qs('[data-action-dialog]'));
        } else if (document.body.classList.contains('command-palette-open')) {
            trapFocus(event, qs('[data-command-palette]'));
        } else if (document.body.classList.contains('user-modal-open')) {
            trapFocus(event, qs('[data-user-modal]'));
        } else if (document.body.classList.contains('site-modal-open')) {
            trapFocus(event, qs('.site-entity-modal.is-open'));
        } else if (document.body.classList.contains('workspace-modal-open')) {
            trapFocus(event, qs('.workspace-entity-modal.is-open'));
        } else if (document.body.classList.contains('modal-open')) {
            trapFocus(event, qs('[data-notification-modal]'));
        }
    });

    window.addEventListener('popstate', function () {
        window.location.reload();
    });

    window.DagrilExperience = {
        hydrate: hydrate,
        toast: showToast
    };

    ready(function () {
        syncSidebarToggle(false);
        syncActiveMenuGroup();
        hydrate(document);
    });
})();
