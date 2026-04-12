(function () {
    if (window.TEINVIT_PREVIEW_LAYOUT_ENGINE) return;

    function normalizeText(text) {
        return String(text || '').replace(/\s+/g, ' ').trim();
    }

    function formatNamesLayout(text, options) {
        var value = normalizeText(text);
        if (!value) return '';
        var cfg = options || {};
        var lineLimit = Math.max(12, parseInt(cfg.lineLimit || 22, 10));

        var words = value.split(' ').filter(Boolean);
        var tokens = [];
        for (var i = 0; i < words.length; i++) {
            var w = words[i];
            if ((w === '&' || w.toLowerCase() === 'și') && i + 1 < words.length) {
                tokens.push(w + ' ' + words[i + 1]);
                i++;
            } else {
                tokens.push(w);
            }
        }

        var lines = [];
        var current = '';
        tokens.forEach(function (token) {
            var probe = current ? (current + ' ' + token) : token;
            if (probe.length <= lineLimit || !current) {
                current = probe;
                return;
            }
            lines.push(current);
            current = token;
        });
        if (current) lines.push(current);

        return lines.map(function (line, index) {
            return index === 0 ? line : line.replace(/ /g, '\u00A0');
        }).join('\n');
    }

    function hasOverflow(canvas) {
        if (!canvas) return false;
        return canvas.scrollHeight > canvas.clientHeight + 1 || canvas.scrollWidth > canvas.clientWidth + 1;
    }

    function autoFit(canvas, options) {
        if (!canvas) return;
        var cfg = options || {};
        var size = 1;
        var min = typeof cfg.min === 'number' ? cfg.min : 0.58;
        var step = typeof cfg.step === 'number' ? cfg.step : 0.02;
        var maxLoops = typeof cfg.maxLoops === 'number' ? cfg.maxLoops : 60;
        var loops = 0;

        canvas.style.fontSize = '1em';
        while (hasOverflow(canvas) && loops < maxLoops) {
            size -= step;
            if (size < min) break;
            canvas.style.fontSize = size.toFixed(2) + 'em';
            loops++;
        }
    }

    function distributeVerticalSpace(canvas, selectors, options) {
        if (!canvas) return;
        var cfg = options || {};
        var list = (selectors || [])
            .map(function (sel) { return canvas.querySelector(sel); })
            .filter(function (node) { return node && node.style.display !== 'none'; });
        if (list.length < 2) return;

        list.forEach(function (node) {
            node.style.marginTop = '';
            node.style.marginBottom = '';
        });

        var used = list.reduce(function (acc, node) { return acc + node.offsetHeight; }, 0);
        var reserve = typeof cfg.reserve === 'number' ? cfg.reserve : 10;
        var free = Math.max(0, canvas.clientHeight - used - reserve);
        var minGap = typeof cfg.minGap === 'number' ? cfg.minGap : 6;
        var maxGap = typeof cfg.maxGap === 'number' ? cfg.maxGap : 26;
        var gap = Math.max(minGap, Math.min(maxGap, Math.floor(free / (list.length + 1))));

        list.forEach(function (node, i) {
            node.style.marginTop = i === 0 ? '0px' : gap + 'px';
        });
    }

    var timers = {};
    var signatures = {};
    function scheduleFinalPass(canvas, key, signature, runLayout, delay) {
        if (!canvas || !key) return;
        if (timers[key]) clearTimeout(timers[key]);
        timers[key] = setTimeout(function () {
            var prev = signatures[key] || '';
            if (prev === signature && typeof runLayout === 'function') {
                runLayout();
            }
            signatures[key] = signature;
        }, typeof delay === 'number' ? delay : 280);
    }

    window.TEINVIT_PREVIEW_LAYOUT_ENGINE = {
        formatNamesLayout: formatNamesLayout,
        hasOverflow: hasOverflow,
        autoFit: autoFit,
        distributeVerticalSpace: distributeVerticalSpace,
        scheduleFinalPass: scheduleFinalPass
    };
})();
