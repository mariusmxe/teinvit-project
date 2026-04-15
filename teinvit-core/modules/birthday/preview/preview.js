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
    function engine() { return window.TEINVIT_PREVIEW_LAYOUT_ENGINE || null; }

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
            var val = String(value || '').trim();
            var isRepeatable = id === REPEATABLE_ID || /field_[a-z0-9]+_(?:clone_)?\d+/i.test(String(key || ''));
            if (isRepeatable) {
                if (!Object.prototype.hasOwnProperty.call(out, id) || !Array.isArray(out[id])) out[id] = [];
                out[id].push(val);
            } else {
                out[id] = val;
            }
        });
        Object.keys(out).forEach(function (id) {
            if (Array.isArray(out[id])) {
                var uniq = [];
                out[id].forEach(function (v) {
                    var val = String(v || '').trim();
                    if (!val) return;
                    if (uniq.indexOf(val) === -1) uniq.push(val);
                });
                out[id] = uniq.join(', ');
            } else {
                out[id] = String(out[id] || '').trim();
            }
        });
        return out;
    }

    function themeCatalog() {
        return {
            'editorial-luxury': true,
            'romantic-floral': true,
            'modern-minimal': true,
            'classic-elegant': true,
            'playful-confetti': true,
            'candy-pastel': true,
            'storybook-dream': true,
            'balloon-party': true,
            'golden-celebration': true,
            'chic-blush': true,
            'midnight-glam': true,
            'botanical-grace': true,
            'royal-blue': true,
            'velvet-noir': true,
            'sunset-fiesta': true
        };
    }

    function normalizeThemeKey(themeKey) {
        var raw = String(themeKey || '').toLowerCase().trim();
        if (!raw) return 'editorial-luxury';
        var catalog = themeCatalog();
        if (catalog[raw]) return raw;

        var aliases = {
            '58a6u': 'editorial-luxury',
            'trs1l': 'romantic-floral',
            'pu7cd': 'modern-minimal',
            'h1ww0': 'classic-elegant',
            '761q4': 'playful-confetti',
            'diqh7': 'candy-pastel',
            'm76bw': 'storybook-dream',
            'v79ej': 'balloon-party',
            'i5ldk': 'golden-celebration',
            'ftv53': 'chic-blush',
            '5pedl': 'midnight-glam',
            '8f3yw': 'botanical-grace',
            'b1lnh': 'royal-blue',
            '8781i': 'velvet-noir',
            'mwftw': 'sunset-fiesta',
            'editorial luxury': 'editorial-luxury',
            'romantic floral': 'romantic-floral',
            'modern minimal': 'modern-minimal',
            'classic elegant': 'classic-elegant',
            'playful confetti': 'playful-confetti',
            'candy pastel': 'candy-pastel',
            'storybook dream': 'storybook-dream',
            'balloon party': 'balloon-party',
            'golden celebration': 'golden-celebration',
            'chic blush': 'chic-blush',
            'midnight glam': 'midnight-glam',
            'botanical grace': 'botanical-grace',
            'royal blue': 'royal-blue',
            'velvet noir': 'velvet-noir',
            'sunset fiesta': 'sunset-fiesta'
        };
        if (aliases[raw]) return aliases[raw];

        raw = raw.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        return catalog[raw] ? raw : 'editorial-luxury';
    }

    function applyTheme(canvas, themeKey) {
        if (!canvas) return;
        [
            'theme-birthday-editorial-luxury', 'theme-birthday-romantic-floral', 'theme-birthday-modern-minimal', 'theme-birthday-classic-elegant',
            'theme-birthday-playful-confetti', 'theme-birthday-candy-pastel', 'theme-birthday-storybook-dream', 'theme-birthday-balloon-party',
            'theme-birthday-golden-celebration', 'theme-birthday-chic-blush', 'theme-birthday-midnight-glam', 'theme-birthday-botanical-grace',
            'theme-birthday-royal-blue', 'theme-birthday-velvet-noir', 'theme-birthday-sunset-fiesta'
        ].forEach(function (c) { canvas.classList.remove(c); });

        var key = normalizeThemeKey(themeKey);
        var vertical = 'theme-birthday-' + key;

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

        applyTheme(canvas, inv.theme || 'editorial-luxury');

        var names = qs('.inv-names', canvas);
        if (names) {
            var formattedHeadline = inv.headline || '';
            if (engine() && typeof engine().formatNamesLayout === 'function') {
                formattedHeadline = engine().formatNamesLayout(formattedHeadline, { lineLimit: 22, units: inv.name_units || [] });
            }
            names.textContent = formattedHeadline;
            names.style.whiteSpace = 'pre-line';
        }

        var age = qs('.inv-age', canvas) || ensureNode('.inv-age', '<div class="inv-age"></div>', canvas);
        var eventName = qs('.inv-event-name', canvas) || ensureNode('.inv-event-name', '<div class="inv-event-name"></div>', canvas);
        var msg = qs('.inv-message', canvas) || ensureNode('.inv-message', '<div class="inv-message"></div>', canvas);
        var hasAge = !!(inv.age && inv.age.enabled && (inv.age.line || inv.age.value));
        var hasEventName = !!(inv.event_name && inv.event_name.enabled && (inv.event_name.line || inv.event_name.value));
        age.style.display = hasAge ? '' : 'none';
        eventName.style.display = hasEventName ? '' : 'none';
        age.textContent = hasAge ? (inv.age.line || '') : '';
        eventName.textContent = hasEventName ? (inv.event_name.line || '') : '';
        msg.textContent = inv.message || '';

        if (names && age && age.parentNode === canvas) {
            canvas.insertBefore(age, names);
        }
        if (names && eventName && eventName.parentNode === canvas) {
            var afterNames = names.nextSibling;
            if (afterNames) canvas.insertBefore(eventName, afterNames);
            else canvas.appendChild(eventName);
        }
        if (eventName && msg && eventName.parentNode === canvas) {
            canvas.insertBefore(msg, eventName.nextSibling);
        }

        var eventsWrap = qs('.inv-events', canvas) || ensureNode('.inv-events', '<div class="inv-events"><div class="events-row top"></div><div class="events-row bottom"></div></div>', canvas);
        var top = qs('.events-row.top', eventsWrap);
        if (top) top.innerHTML = '';

        var party = inv.events && inv.events.party ? inv.events.party : null;
        var enabled = !!(party && party.enabled);
        eventsWrap.style.display = enabled ? '' : 'none';

        if (enabled && top) {
            var node = document.createElement('div');
            node.className = 'inv-event';
            var html = '<strong>' + (party.title || 'PETRECERE') + '</strong><div class="inv-place">' + (party.loc || '') + '</div>';
            if (party.weekday) html += '<div class="inv-weekday">' + party.weekday + '</div>';
            html += '<div class="inv-datetime">' + (party.date || '') + '</div>';
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

    function hasOverflow(el) { return engine() ? engine().hasOverflow(el) : (el && (el.scrollHeight > el.clientHeight + 1 || el.scrollWidth > el.clientWidth + 1)); }

    function applyAutoFit(canvas) {
        if (!canvas) return;
        if (engine() && typeof engine().autoFit === 'function') {
            engine().autoFit(canvas, { min: 0.60, step: 0.02, maxLoops: 50 });
            return;
        }
    }

    function distributeVerticalSpace(canvas) {
        if (!canvas) return;
        if (engine() && typeof engine().distributeVerticalSpace === 'function') {
            engine().distributeVerticalSpace(canvas, ['.inv-age', '.inv-names', '.inv-event-name', '.inv-message', '.inv-events'], { reserve: 10, minGap: 7, maxGap: 24 });
        }
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
        var sig = layoutSignature(canvas);
        if (engine() && typeof engine().scheduleFinalPass === 'function') {
            engine().scheduleFinalPass(canvas, 'birthday', sig, function () {
                canvas.style.fontSize = '1em';
                distributeVerticalSpace(canvas);
                requestAnimationFrame(function () {
                    if (hasOverflow(canvas)) applyAutoFit(canvas);
                });
            }, 280);
            return;
        }
        if (finalTimer) clearTimeout(finalTimer);
        finalTimer = setTimeout(function () {
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
    document.addEventListener('wapf/date_selected', function () {
        setTimeout(function () {
            scheduleBuildFromApi();
        }, 30);
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
