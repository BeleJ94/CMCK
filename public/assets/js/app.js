(function () {
    var toggle = document.querySelector('[data-sidebar-toggle]');
    var compactToggle = document.querySelector('[data-sidebar-compact]');
    var menuSearch = document.querySelector('[data-menu-search]');
    var closeTargets = document.querySelectorAll('[data-sidebar-close], .sidebar-nav a');
    var activeDetail = null;

    if (window.localStorage && window.localStorage.getItem('cmckSidebarCompact') === '1') {
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
                window.localStorage.setItem('cmckSidebarCompact', document.body.classList.contains('sidebar-compact') ? '1' : '0');
            }
        });
    }

    if (menuSearch) {
        menuSearch.addEventListener('input', function () {
            var query = menuSearch.value.trim().toLowerCase();

            document.querySelectorAll('[data-menu-section]').forEach(function (section) {
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
            });
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

    document.querySelectorAll('[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
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
        trigger.addEventListener('click', function () {
            var index = Number(trigger.getAttribute('data-notification-index'));
            var notification = (window.cmckNotifications || [])[index];

            if (notification) {
                openDetailModal(notificationDetail(notification));
            }
        });
    });

    document.querySelectorAll('[data-kpi-open]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var index = Number(trigger.getAttribute('data-kpi-index'));
            var kpi = (window.cmckKpis || [])[index];

            if (kpi) {
                openDetailModal(kpiDetail(kpi));
            }
        });
    });

    document.querySelectorAll('[data-chart-open]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            if (event.target.closest('.panel-action') || event.target.closest('.chart-box')) {
                openDetailModal(chartDetail(trigger.getAttribute('data-chart-key')));
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

    function openDetailModal(detail) {
        activeDetail = detail;

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
        }
    }

    function closeNotificationModal() {
        var modal = document.querySelector('[data-notification-modal]');
        document.body.classList.remove('modal-open');
        if (modal) {
            modal.setAttribute('aria-hidden', 'true');
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

        popup.document.write('<!doctype html><html><head><title>Export CMCK</title><style>body{font-family:Arial,sans-serif;color:#162033;padding:32px} .card{border:1px solid #d9e1ea;border-radius:8px;padding:24px} h1{margin:0 0 6px;color:#0b1f35} .meta{color:#667085;margin-bottom:22px} table{width:100%;border-collapse:collapse} th,td{border-top:1px solid #d9e1ea;padding:12px;text-align:left} th{background:#0b1f35;color:#fff} td:first-child{font-weight:700;background:#f6f8fb}</style></head><body>');
        popup.document.write('<div class="card"><h1>CMCK MillTrack</h1><div class="meta">' + escapeHtml(activeDetail.title) + '</div>' + detailTable(activeDetail) + '</div>');
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
        link.download = slug(activeDetail.title || 'export-cmck') + '.xls';
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
                    source: 'Interface CMCK'
                }
            ]
        };
    }

    function chartDetail(key) {
        var chart = chartMeta(key);
        var dataset = (window.cmckDashboard || {})[key] || { labels: [], values: [] };
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
            .replace(/^-+|-+$/g, '') || 'export-cmck';
    }
})();
