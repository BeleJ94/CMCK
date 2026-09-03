(function () {
    'use strict';

    var kpis = window.dagrilAnalytics || [];
    var modal = document.querySelector('[data-analytics-modal]');
    var modalCanvas = document.querySelector('[data-analytics-chart]');
    var activeIndex = null;
    var returnFocus = null;
    var resizeTimer = null;

    function palette(warning) {
        return warning ? { main: '#d97706', soft: 'rgba(217,119,6,.15)' } : { main: '#0786d1', soft: 'rgba(7,134,209,.15)' };
    }

    function setupCanvas(canvas) {
        var rect = canvas.getBoundingClientRect();
        var ratio = Math.min(window.devicePixelRatio || 1, 2);
        canvas.width = Math.max(1, Math.round(rect.width * ratio));
        canvas.height = Math.max(1, Math.round(rect.height * ratio));
        var context = canvas.getContext('2d');
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        return { context: context, width: rect.width, height: rect.height };
    }

    function valuesFor(kpi) {
        var values = kpi && kpi.chart && Array.isArray(kpi.chart.values) ? kpi.chart.values : [];
        return values.map(function (value) { return Number(value) || 0; });
    }

    function drawMini(canvas, kpi) {
        var box = setupCanvas(canvas);
        var ctx = box.context;
        var values = valuesFor(kpi);
        var colors = palette(kpi.status === 'warning');
        ctx.clearRect(0, 0, box.width, box.height);
        if (!values.length) values = [0, 0, 0, 0, 0, 0];
        var max = Math.max.apply(Math, values.concat([1]));
        var gap = 5;
        var barWidth = Math.max(3, (box.width - gap * (values.length - 1)) / values.length);
        values.forEach(function (value, index) {
            var height = Math.max(2, (value / max) * (box.height - 8));
            var x = index * (barWidth + gap);
            var y = box.height - height;
            ctx.fillStyle = index === values.length - 1 ? colors.main : colors.soft;
            ctx.beginPath();
            if (ctx.roundRect) ctx.roundRect(x, y, barWidth, height, 3);
            else ctx.rect(x, y, barWidth, height);
            ctx.fill();
        });
    }

    function drawLarge(kpi) {
        var box = setupCanvas(modalCanvas);
        var ctx = box.context;
        var values = valuesFor(kpi);
        var labels = kpi.chart && kpi.chart.labels ? kpi.chart.labels : [];
        var colors = palette(kpi.status === 'warning');
        var padding = { top: 24, right: 22, bottom: 44, left: 58 };
        var plotWidth = Math.max(1, box.width - padding.left - padding.right);
        var plotHeight = Math.max(1, box.height - padding.top - padding.bottom);
        var max = Math.max.apply(Math, values.concat([1]));
        var gridMax = max * 1.12;
        ctx.clearRect(0, 0, box.width, box.height);
        modalCanvas._dagrilPoints = [];
        ctx.font = '11px Inter, system-ui, sans-serif';
        ctx.textBaseline = 'middle';

        for (var line = 0; line <= 4; line++) {
            var y = padding.top + plotHeight * (line / 4);
            ctx.strokeStyle = '#e7edf2';
            ctx.lineWidth = 1;
            ctx.beginPath();ctx.moveTo(padding.left, y);ctx.lineTo(box.width - padding.right, y);ctx.stroke();
            ctx.fillStyle = '#718096';ctx.textAlign = 'right';
            ctx.fillText(formatNumber(gridMax * (1 - line / 4)), padding.left - 10, y);
        }

        if (!values.length) return;
        var type = kpi.chart.type || 'line';
        if (type === 'bar') {
            var step = plotWidth / values.length;
            var width = Math.min(42, step * .58);
            values.forEach(function (value, index) {
                var height = (value / gridMax) * plotHeight;
                var x = padding.left + step * index + (step - width) / 2;
                var y = padding.top + plotHeight - height;
                ctx.fillStyle = index === values.length - 1 ? colors.main : colors.soft;
                ctx.beginPath();if (ctx.roundRect) ctx.roundRect(x, y, width, height, 5);else ctx.rect(x, y, width, height);ctx.fill();
                modalCanvas._dagrilPoints.push({ x: x + width / 2, y: y, value: value, label: labels[index] || '—', reference: (kpi.chart.references || [])[index] || '' });
                drawLabel(ctx, labels[index], x + width / 2, box.height - 20);
            });
        } else {
            var lineStep = values.length > 1 ? plotWidth / (values.length - 1) : plotWidth;
            var points = values.map(function (value, index) { return { x: padding.left + (values.length > 1 ? lineStep * index : plotWidth / 2), y: padding.top + plotHeight - (value / gridMax) * plotHeight }; });
            var gradient = ctx.createLinearGradient(0, padding.top, 0, padding.top + plotHeight);
            gradient.addColorStop(0, colors.soft);gradient.addColorStop(1, 'rgba(255,255,255,0)');
            ctx.beginPath();ctx.moveTo(points[0].x, padding.top + plotHeight);points.forEach(function (point) { ctx.lineTo(point.x, point.y); });ctx.lineTo(points[points.length - 1].x, padding.top + plotHeight);ctx.closePath();ctx.fillStyle = gradient;ctx.fill();
            ctx.beginPath();points.forEach(function (point, index) { if (!index) ctx.moveTo(point.x, point.y);else ctx.lineTo(point.x, point.y); });ctx.strokeStyle = colors.main;ctx.lineWidth = 3;ctx.lineJoin = 'round';ctx.stroke();
            points.forEach(function (point, index) { ctx.beginPath();ctx.arc(point.x, point.y, 4, 0, Math.PI * 2);ctx.fillStyle = '#fff';ctx.fill();ctx.strokeStyle = colors.main;ctx.lineWidth = 2;ctx.stroke();drawLabel(ctx, labels[index], point.x, box.height - 20); });
            modalCanvas._dagrilPoints = points.map(function (point, index) { return { x: point.x, y: point.y, value: values[index], label: labels[index] || '—', reference: (kpi.chart.references || [])[index] || '' }; });
        }
    }

    function drawLabel(ctx, label, x, y) {
        var value = String(label || '—');
        if (value.length > 10) value = value.slice(0, 9) + '…';
        ctx.fillStyle = '#718096';ctx.textAlign = 'center';ctx.fillText(value, x, y);
    }

    function formatNumber(value) {
        return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1, notation: Math.abs(value) >= 10000 ? 'compact' : 'standard' }).format(value);
    }

    function open(index, trigger) {
        var kpi = kpis[index];
        if (!kpi || !modal) return;
        activeIndex = index;returnFocus = trigger;
        modal.querySelector('[data-analytics-title]').textContent = kpi.label;
        modal.querySelector('[data-analytics-subtitle]').textContent = kpi.count + ' source(s) validée(s) · ' + (kpi.chart.type === 'bar' ? 'Comparaison' : 'Évolution');
        modal.querySelector('[data-analytics-value]').textContent = formatNumber(Number(kpi.value) || 0) + ' ' + kpi.unit;
        modal.querySelector('[data-analytics-detail]').href = (window.dagrilAnalyticsBase || '') + '/' + kpi.detail_path + '?' + (window.dagrilAnalyticsQuery || '');
        modal.querySelector('[data-analytics-empty]').hidden = valuesFor(kpi).length > 0;
        document.body.classList.add('analytics-modal-open');
        modal.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(function () { drawLarge(kpi);modal.querySelector('.modal-close').focus(); });
    }

    function close() {
        if (!modal) return;
        document.body.classList.remove('analytics-modal-open');
        modal.setAttribute('aria-hidden', 'true');
        activeIndex = null;
        if (returnFocus && document.contains(returnFocus)) returnFocus.focus({ preventScroll: true });
        returnFocus = null;
    }

    document.querySelectorAll('[data-analytics-mini]').forEach(function (canvas) {
        drawMini(canvas, kpis[Number(canvas.getAttribute('data-analytics-mini'))] || {});
    });
    document.querySelectorAll('[data-analytics-open]').forEach(function (trigger) {
        trigger.addEventListener('click', function () { open(Number(trigger.getAttribute('data-analytics-open')), trigger); });
    });
    document.querySelectorAll('[data-analytics-close]').forEach(function (trigger) { trigger.addEventListener('click', close); });
    if (modalCanvas) {
        modalCanvas.addEventListener('mousemove', function (event) {
            var points = modalCanvas._dagrilPoints || [];
            var tooltip = modal.querySelector('[data-analytics-tooltip]');
            if (!points.length || !tooltip) return;
            var nearest = points.reduce(function (best, point) { return Math.abs(point.x - event.offsetX) < Math.abs(best.x - event.offsetX) ? point : best; }, points[0]);
            if (Math.abs(nearest.x - event.offsetX) > 46) { tooltip.classList.remove('is-visible');return; }
            tooltip.innerHTML = '<strong>' + nearest.value.toLocaleString('fr-FR') + '</strong><span>' + escapeText(nearest.reference || nearest.label) + '</span>';
            tooltip.style.left = Math.min(modalCanvas.clientWidth - 120, Math.max(58, nearest.x)) + 'px';
            tooltip.style.top = Math.max(12, nearest.y - 18) + 'px';
            tooltip.classList.add('is-visible');
        });
        modalCanvas.addEventListener('mouseleave', function () { var tooltip = modal.querySelector('[data-analytics-tooltip]');if (tooltip) tooltip.classList.remove('is-visible'); });
    }
    function escapeText(value) { var node=document.createElement('span');node.textContent=String(value);return node.innerHTML; }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && activeIndex !== null) close();
        if (event.key === 'Tab' && activeIndex !== null) {
            var focusable = modal.querySelectorAll('button:not([disabled]),a[href]');
            if (!focusable.length) return;
            var first = focusable[0];var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault();last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault();first.focus(); }
        }
    });
    window.addEventListener('resize', function () {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(function () {
            document.querySelectorAll('[data-analytics-mini]').forEach(function (canvas) { drawMini(canvas, kpis[Number(canvas.getAttribute('data-analytics-mini'))] || {}); });
            if (activeIndex !== null) drawLarge(kpis[activeIndex]);
        }, 120);
    });
})();
