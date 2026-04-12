(function () {
    var REPEATABLE_ID = 'd1fe0da';
    var MESSAGE_ID = 'bef895a';
    var MESSAGE_MAX = 255;
    var BUILD_DEBOUNCE_MS = 140;
    var buildTimer = null;

    function qs(sel, root) { return (root || document).querySelector(sel); }
    function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    function getRoot() { return qs('#teinvit-vertical-product-preview') || document; }
    function getCanvas() { return qs('.teinvit-canvas', getRoot()) || qs('.teinvit-canvas'); }
    function isProductPreviewContext() { return !!qs('#teinvit-vertical-product-preview[data-product-id]'); }

    function parseFieldId(name) {
        var raw = String(name || '').trim();
        var m = raw.match(/field_([a-z0-9_\-]+)/i);
        if (!m) return '';
        return String(m[1] || '').replace(/_\d+$/, '').trim();
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
            out[id].forEach(function (v) {
                var val = String(v || '').trim();
                if (!val) return;
                if (uniq.indexOf(val) === -1) uniq.push(val);
            });
            out[id] = uniq.join(', ');
        });
        return out;
    }

    function applyTheme(canvas, themeKey) {
        if (!canvas) return;
        [
            'theme-editorial-luxury', 'theme-romantic-floral', 'theme-modern-minimal', 'theme-classic-elegant',
            'theme-birthday-editorial', 'theme-birthday-romantic', 'theme-birthday-modern', 'theme-birthday-classic'
        ].forEach(function (c) { canvas.classList.remove(c); });

        var t = String(themeKey || 'editorial').toLowerCase();
        var shared = 'theme-editorial-luxury';
        if (t === 'romantic') shared = 'theme-romantic-floral';
        else if (t === 'modern') shared = 'theme-modern-minimal';
        else if (t === 'classic') shared = 'theme-classic-elegant';

        var vertical = 'theme-birthday-editorial';
        if (t === 'romantic') vertical = 'theme-birthday-romantic';
        else if (t === 'modern') vertical = 'theme-birthday-modern';
        else if (t === 'classic') vertical = 'theme-birthday-classic';

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

        var eventsWrap = qs('.inv-events', canvas) || ensureNode('.inv-events', '<div class="inv-events"><div class="events-row top"></div><div class="events-row bottom"></div></div>', canvas);
        var top = qs('.events-row.top', eventsWrap);
        if (top) top.innerHTML = '';

        var party = inv.events && inv.events.party ? inv.events.party : null;
        var enabled = !!(party && party.enabled);
        eventsWrap.style.display = enabled ? '' : 'none';

        if (enabled && top) {
            var node = document.createElement('div');
            node.className = 'inv-event';
            var html = '<strong>' + (party.title || 'Petrecere') + '</strong><div>' + (party.loc || '') + '</div><div>' + (party.date || '') + '</div>';
            if (party.waze) html += '<a href="' + party.waze + '" target="_blank" rel="noopener">Deschide în Waze</a>';
            node.innerHTML = html;
            top.appendChild(node);
        }

        if (isProductPreviewContext()) {
            applyAutoFit(canvas);
            distributeVerticalSpace(canvas);
            scheduleFinalPass(canvas);
        }

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
        var min = 0.60;
        var tries = 0;
        canvas.style.fontSize = '1em';
        while (hasOverflow(canvas) && tries < 50) {
            size -= 0.02;
            if (size < min) break;
            canvas.style.fontSize = size.toFixed(2) + 'em';
            tries++;
        }
    }

    function distributeVerticalSpace(canvas) {
        if (!canvas) return;
        var blocks = ['.inv-names', '.inv-message', '.inv-events']
            .map(function (s) { return qs(s, canvas); })
            .filter(function (n) { return n && n.style.display !== 'none'; });
        if (blocks.length < 2) return;

        blocks.forEach(function (n) { n.style.marginTop = ''; n.style.marginBottom = ''; });
        var used = blocks.reduce(function (acc, n) { return acc + n.offsetHeight; }, 0);
        var free = Math.max(0, canvas.clientHeight - used - 10);
        var gap = Math.max(8, Math.min(30, Math.floor(free / (blocks.length + 1))));
        blocks.forEach(function (n, i) { n.style.marginTop = i === 0 ? '0px' : gap + 'px'; });
    }

    var finalTimer = null;
    var lastSig = '';
    function layoutSignature(canvas) {
        return JSON.stringify({
            fs: canvas && canvas.style ? canvas.style.fontSize : '',
            text: (qs('.inv-names', canvas) || {}).textContent || '',
            msg: (qs('.inv-message', canvas) || {}).textContent || '',
            event: (qs('.inv-event', canvas) || {}).textContent || ''
        });
    }

    function scheduleFinalPass(canvas) {
        if (!canvas) return;
        if (finalTimer) clearTimeout(finalTimer);
        finalTimer = setTimeout(function () {
            var sig = layoutSignature(canvas);
            if (sig === lastSig) {
                canvas.style.fontSize = '1em';
                distributeVerticalSpace(canvas);
                requestAnimationFrame(function () {
                    if (hasOverflow(canvas)) applyAutoFit(canvas);
                });
            }
            lastSig = sig;
        }, 280);
    }

    function clearPrefilledCloneInputs(scope) {
        qsa('[name^="wapf[field_' + REPEATABLE_ID + '_"]', scope || document).forEach(function (el) {
            var name = String(el.getAttribute('name') || '');
            var m = name.match(new RegExp('^wapf\\[field_' + REPEATABLE_ID + '_(?:clone_)?(\\d+)\\]$'));
            if (!m) return;
            var idx = parseInt(m[1] || '0', 10);
            if (!(idx > 1)) return;
            if (el.getAttribute('data-teinvit-clone-init') === '1') return;
            el.setAttribute('data-teinvit-clone-init', '1');
            if (String(el.value || '').trim() !== '') {
                el.value = '';
            }
        });
    }

    function setupMessageCounter() {
        qsa('textarea[name="wapf[field_' + MESSAGE_ID + ']"], textarea[name="wapf[field_' + MESSAGE_ID + '][]"]').forEach(function (textarea) {
            textarea.setAttribute('maxlength', String(MESSAGE_MAX));
            var container = textarea.parentNode || textarea;
            var counter = qs('.teinvit-message-counter[data-for="' + MESSAGE_ID + '"]', container);
            if (!counter) {
                counter = document.createElement('div');
                counter.className = 'teinvit-message-counter';
                counter.setAttribute('data-for', MESSAGE_ID);
                counter.style.fontSize = '12px';
                counter.style.color = '#6b7280';
                counter.style.marginTop = '6px';
                counter.style.textAlign = 'right';
                textarea.insertAdjacentElement('afterend', counter);
            }

            function update() {
                var val = String(textarea.value || '');
                if (val.length > MESSAGE_MAX) textarea.value = val.substring(0, MESSAGE_MAX);
                counter.textContent = String((textarea.value || '').length) + ' / ' + MESSAGE_MAX + ' caractere';
            }

            if (textarea.getAttribute('data-teinvit-counter-init') !== '1') {
                textarea.addEventListener('input', update);
                textarea.setAttribute('data-teinvit-counter-init', '1');
            }
            update();
        });
    }

    function scheduleBuildFromApi() {
        if (buildTimer) clearTimeout(buildTimer);
        buildTimer = setTimeout(function () {
            buildFromApi();
        }, BUILD_DEBOUNCE_MS);
    }

    function isRepeatControl(node) {
        if (!node || node.nodeType !== 1) return false;
        var control = node.closest('button, a, [role="button"], .wapf-repeatable-add, .wapf-repeatable-remove, .wapf-clone-add, .wapf-clone-remove');
        if (!control) return false;
        var text = ((control.className || '') + ' ' + (control.getAttribute('data-action') || '') + ' ' + (control.textContent || '')).toLowerCase();
        return text.indexOf('wapf') !== -1 || text.indexOf('repeat') !== -1 || text.indexOf('clone') !== -1 || text.indexOf('șterge') !== -1;
    }

    function buildFromApi() {
        var mount = qs('#teinvit-vertical-product-preview');
        if (!mount) return;
        var productId = parseInt(mount.getAttribute('data-product-id') || '0', 10);
        var url = window.teinvitBirthdayPreviewConfig && window.teinvitBirthdayPreviewConfig.previewBuildUrl;
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
        clearPrefilledCloneInputs(document);
        setupMessageCounter();
        if (window.TEINVIT_INVITATION_DATA) {
            renderInvitation(window.TEINVIT_INVITATION_DATA);
        }
        if (qs('#teinvit-vertical-product-preview')) {
            buildFromApi();
        }
    });

    document.addEventListener('input', function (e) {
        var t = e && e.target;
        if (t && t.name && t.name.indexOf('wapf[') === 0) {
            scheduleBuildFromApi();
        }
    });
    document.addEventListener('change', function (e) {
        var t = e && e.target;
        if (t && t.name && t.name.indexOf('wapf[') === 0 && (!t.type || t.type.toLowerCase() !== 'text') && t.tagName !== 'TEXTAREA') {
            scheduleBuildFromApi();
        }
    });
    document.addEventListener('click', function (e) {
        var t = e && e.target;
        if (!isRepeatControl(t)) return;
        setTimeout(function () {
            clearPrefilledCloneInputs(document);
            setupMessageCounter();
            scheduleBuildFromApi();
        }, 40);
    });
})();
