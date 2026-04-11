(function () {
    function qs(sel, root) { return (root || document).querySelector(sel); }
    function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    function getRoot() {
        return qs('#teinvit-vertical-product-preview') || document;
    }

    function getCanvas() {
        return qs('.teinvit-canvas', getRoot()) || qs('.teinvit-canvas');
    }

    function parseFieldId(name) {
        var raw = String(name || '').trim();
        var m = raw.match(/field_([a-z0-9_\-]+)/i);
        if (!m) return '';
        return String(m[1] || '').replace(/_\d+$/, '').replace(/^clone_/, '').trim();
    }

    function collectWapfMapFromForm() {
        var form = qs('#teinvit-save-form') || qs('form.cart') || qs('form');
        if (!form) return {};
        var out = {};
        var fd = new FormData(form);
        fd.forEach(function (value, key) {
            var id = parseFieldId(key);
            if (!id) return;
            if (!Object.prototype.hasOwnProperty.call(out, id)) out[id] = [];
            out[id].push(String(value || '').trim());
        });
        Object.keys(out).forEach(function (id) {
            var uniq = [];
            out[id].forEach(function (v) { if (v && uniq.indexOf(v) === -1) uniq.push(v); });
            out[id] = uniq.join(', ');
        });
        return out;
    }

    function applyTheme(canvas, themeKey) {
        if (!canvas) return;
        [
            'theme-editorial-luxury', 'theme-romantic-floral', 'theme-modern-minimal', 'theme-classic-elegant',
            'theme-baptism-editorial', 'theme-baptism-romantic', 'theme-baptism-modern', 'theme-baptism-classic'
        ].forEach(function (c) { canvas.classList.remove(c); });

        var t = String(themeKey || 'editorial').toLowerCase();
        var shared = 'theme-editorial-luxury';
        if (t === 'romantic') shared = 'theme-romantic-floral';
        else if (t === 'modern') shared = 'theme-modern-minimal';
        else if (t === 'classic') shared = 'theme-classic-elegant';

        var vertical = 'theme-baptism-editorial';
        if (t === 'romantic') vertical = 'theme-baptism-romantic';
        else if (t === 'modern') vertical = 'theme-baptism-modern';
        else if (t === 'classic') vertical = 'theme-baptism-classic';

        canvas.classList.add(shared);
        canvas.classList.add(vertical);
    }

    function ensureNode(sel, html, root) {
        var node = qs(sel, root);
        if (node) return node;
        var wrap = document.createElement('div');
        wrap.innerHTML = html;
        node = wrap.firstChild;
        root.appendChild(node);
        return node;
    }

    function renderInvitation(inv) {
        if (!inv) return;
        var canvas = getCanvas();
        if (!canvas) return;

        applyTheme(canvas, inv.theme || 'editorial');

        var names = qs('.inv-names', canvas);
        if (names) names.textContent = inv.headline || '';

        var msg = qs('.inv-message', canvas) || ensureNode('.inv-message', '<div class="inv-message"></div>', canvas);
        msg.textContent = inv.message || '';

        var parents = qs('.inv-parents-wrapper', canvas);
        if (!parents && inv.parents) {
            parents = ensureNode('.inv-parents-wrapper', '<div class="inv-parents-wrapper"><div class="section-title">Împreună cu părinții</div><div class="inv-parents inv-parents-grid"><div class="inv-parent-col inv-parent-mireasa"></div><div class="inv-parent-col inv-parent-mire"></div></div></div>', canvas);
        }
        if (parents) {
            var enabledParents = !!(inv.parents && inv.parents.enabled);
            parents.style.display = enabledParents ? '' : 'none';
            var mother = qs('.inv-parent-mireasa', parents);
            var father = qs('.inv-parent-mire', parents);
            if (mother) mother.textContent = (inv.parents && inv.parents.mother) || '';
            if (father) {
                var mt = (inv.parents && inv.parents.mother) || '';
                var ft = (inv.parents && inv.parents.father) || '';
                father.textContent = mt && ft ? ('& ' + ft) : ft;
            }
        }

        var nasi = qs('.inv-nasi', canvas);
        if (!nasi && inv.godparents) {
            nasi = ensureNode('.inv-nasi', '<div class="inv-nasi"><div class="section-title">Și cu nașii</div><div class="nasi-row"></div></div>', canvas);
        }
        if (nasi) {
            var enabledNasi = !!(inv.godparents && inv.godparents.enabled);
            nasi.style.display = enabledNasi ? '' : 'none';
            var row = qs('.nasi-row', nasi);
            if (row) {
                row.textContent = [
                    inv.godparents && inv.godparents.godmother || '',
                    inv.godparents && inv.godparents.godfather || ''
                ].filter(Boolean).join(' & ');
            }
        }

        var eventsWrap = qs('.inv-events', canvas) || ensureNode('.inv-events', '<div class="inv-events"><div class="events-row top"></div><div class="events-row bottom"></div></div>', canvas);
        var top = qs('.events-row.top', eventsWrap);
        if (top) top.innerHTML = '';

        var events = [];
        if (inv.events && inv.events.religious && inv.events.religious.enabled) events.push(inv.events.religious);
        if (inv.events && inv.events.party && inv.events.party.enabled) events.push(inv.events.party);

        eventsWrap.style.display = events.length ? '' : 'none';
        events.forEach(function (e) {
            if (!top) return;
            var node = document.createElement('div');
            node.className = 'inv-event';
            var html = '<strong>' + (e.title || '') + '</strong><div>' + (e.loc || '') + '</div><div>' + (e.date || '') + '</div>';
            if (e.waze) html += '<a href="' + e.waze + '" target="_blank" rel="noopener">Waze</a>';
            node.innerHTML = html;
            top.appendChild(node);
        });

        applyAutoFit(canvas);
        scheduleFinalPass(canvas);

        if (window.__TEINVIT_PDF_MODE__) {
            window.__TEINVIT_PDF_READY__ = true;
        }
    }

    function hasOverflow(el) {
        if (!el) return false;
        return el.scrollHeight > el.clientHeight + 1 || el.scrollWidth > el.clientWidth + 1;
    }

    function applyAutoFit(canvas) {
        if (!canvas) return;
        var size = 1;
        var min = 0.58;
        var tries = 0;
        canvas.style.fontSize = '1em';
        while (hasOverflow(canvas) && tries < 60) {
            size -= 0.02;
            if (size < min) break;
            canvas.style.fontSize = size.toFixed(2) + 'em';
            var msg = qs('.inv-message', canvas);
            if (msg) msg.style.marginTop = '0.7em';
            var events = qs('.inv-events', canvas);
            if (events) events.style.marginTop = '0.7em';
            tries++;
        }
    }

    var finalTimer = null;
    var lastSig = '';
    function layoutSignature(canvas) {
        return JSON.stringify({
            fs: canvas && canvas.style ? canvas.style.fontSize : '',
            text: (qs('.inv-names', canvas) || {}).textContent || '',
            msg: (qs('.inv-message', canvas) || {}).textContent || '',
            events: qsa('.inv-event', canvas).map(function (e) { return e.textContent || ''; })
        });
    }

    function scheduleFinalPass(canvas) {
        if (!canvas) return;
        if (finalTimer) clearTimeout(finalTimer);
        finalTimer = setTimeout(function () {
            var sig = layoutSignature(canvas);
            if (sig === lastSig) {
                canvas.style.fontSize = '1em';
                requestAnimationFrame(function () {
                    if (hasOverflow(canvas)) applyAutoFit(canvas);
                });
            }
            lastSig = sig;
        }, 260);
    }

    function buildFromApi() {
        var mount = qs('#teinvit-vertical-product-preview');
        if (!mount) return;
        var productId = parseInt(mount.getAttribute('data-product-id') || '0', 10);
        var url = window.teinvitBaptismPreviewConfig && window.teinvitBaptismPreviewConfig.previewBuildUrl;
        if (!productId || !url) return;

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId, wapf_map: collectWapfMapFromForm() })
        }).then(function (r) { return r.json(); }).then(function (json) {
            if (json && json.ok && json.invitation) {
                window.TEINVIT_INVITATION_DATA = json.invitation;
                renderInvitation(json.invitation);
            }
        }).catch(function () {});
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (window.TEINVIT_INVITATION_DATA) {
            renderInvitation(window.TEINVIT_INVITATION_DATA);
        }
        if (qs('#teinvit-vertical-product-preview')) {
            buildFromApi();
        }
    });

    document.addEventListener('input', function (e) {
        var t = e && e.target;
        if (t && t.name && t.name.indexOf('wapf[') === 0) buildFromApi();
    });
    document.addEventListener('change', function (e) {
        var t = e && e.target;
        if (t && t.name && t.name.indexOf('wapf[') === 0) buildFromApi();
    });
})();
