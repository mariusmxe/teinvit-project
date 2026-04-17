(function () {
    var REPEATABLE_ID = '2d8d1ce';
    var MESSAGE_ID = '4c3baec';
    var MESSAGE_MAX = 255;
    var BUILD_DEBOUNCE_MS = 140;
    var buildTimer = null;
    var pdfReadyCheckTimer = null;
    var pdfReadyCheckAttempts = 0;

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
            'little-princess': true,
            'blush-angel': true,
            'rosy-grace': true,
            'sweet-peony': true,
            'pink-cherub': true,
            'little-prince': true,
            'blue-angel': true,
            'gentle-sailor': true,
            'sky-blessing': true,
            'royal-baptism': true,
            'twin-harmony': true,
            'triple-blessing': true,
            'heavenly-stars': true,
            'little-miracles': true,
            'angelic-trio': true
        };
    }

    function parseUnitsFromHeadline(headline) {
        var raw = String(headline || '').replace(/\s+/g, ' ').trim();
        if (!raw) return [];
        return raw
            .split(/\s*(?:,|&|și)\s*/i)
            .map(function (part) { return String(part || '').trim(); })
            .filter(Boolean);
    }

    function normalizeNameUnits(units, fallbackHeadline) {
        if (Array.isArray(units)) {
            var cleaned = units.map(function (u) {
                return String(u || '').replace(/\s+/g, ' ').trim();
            }).filter(Boolean);
            if (cleaned.length) return cleaned;
        }
        var parsed = parseUnitsFromHeadline(fallbackHeadline);
        if (parsed.length) return parsed;
        var fallback = String(fallbackHeadline || '').replace(/\s+/g, ' ').trim();
        return fallback ? [fallback] : [];
    }

    function toNoWrap(text) {
        return String(text || '').replace(/\s+/g, ' ').trim().replace(/ /g, '\u00A0');
    }

    function resolveNameLineLimit(names) {
        return names.length >= 3 ? 30 : 22;
    }

    function splitNameByWords(name, lineLimit) {
        var words = String(name || '').replace(/\s+/g, ' ').trim().split(' ').filter(Boolean);
        if (!words.length) return [];
        var out = [];
        var current = '';
        words.forEach(function (word) {
            var probe = current ? (current + ' ' + word) : word;
            if (!current || probe.length <= lineLimit) {
                current = probe;
                return;
            }
            out.push(current);
            current = word;
        });
        if (current) out.push(current);
        return out;
    }

    function formatNamesWeddingRule(units, maxCharsPerLine, fallbackHeadline) {
        var names = normalizeNameUnits(units, fallbackHeadline);
        if (!names.length) return '';
        var limit = Math.max(12, resolveNameLineLimit(names));
        var tokens = [];

        names.forEach(function (name, idx) {
            var chunks = splitNameByWords(name, limit);
            if (!chunks.length) return;
            var connector = '';
            if (idx > 0) {
                connector = (idx === names.length - 1) ? 'și ' : '& ';
            }
            chunks.forEach(function (chunk, cidx) {
                var prefix = cidx === 0 ? connector : '';
                tokens.push({
                    measure: prefix + chunk
                });
            });
        });

        var lines = [];
        var current = '';

        tokens.forEach(function (token) {
            var probe = current ? (current + ' ' + token.measure) : token.measure;
            if (!current || probe.length <= limit) {
                current = probe;
                return;
            }
            lines.push(current);
            current = token.measure;
        });
        if (current) lines.push(current);

        return lines.map(function (line) { return toNoWrap(line); }).join('\n');
    }

    function normalizeThemeKey(themeKey) {
        var raw = String(themeKey || '').toLowerCase().trim();
        if (!raw) return 'little-princess';
        var catalog = themeCatalog();
        if (catalog[raw]) return raw;

        var aliases = {
            '58a6u': 'little-princess',
            'trs1l': 'blush-angel',
            'pu7cd': 'rosy-grace',
            'h1ww0': 'sweet-peony',
            '0jzln': 'pink-cherub',
            '0487g': 'little-prince',
            'aq8z0': 'blue-angel',
            'yqsze': 'gentle-sailor',
            '1irb5': 'sky-blessing',
            '5o9gi': 'royal-baptism',
            'w1a2c': 'twin-harmony',
            '5o9dm': 'triple-blessing',
            't0bes': 'heavenly-stars',
            'mjibs': 'little-miracles',
            'gks08': 'angelic-trio',
            'little princess': 'little-princess',
            'blush angel': 'blush-angel',
            'rosy grace': 'rosy-grace',
            'sweet peony': 'sweet-peony',
            'pink cherub': 'pink-cherub',
            'little prince': 'little-prince',
            'blue angel': 'blue-angel',
            'gentle sailor': 'gentle-sailor',
            'sky blessing': 'sky-blessing',
            'royal baptism': 'royal-baptism',
            'twin harmony': 'twin-harmony',
            'triple blessing': 'triple-blessing',
            'heavenly stars': 'heavenly-stars',
            'little miracles': 'little-miracles',
            'angelic trio': 'angelic-trio'
        };
        aliases.editorial = 'little-princess';
        aliases.romantic = 'blush-angel';
        aliases.modern = 'little-prince';
        aliases.classic = 'royal-baptism';
        if (aliases[raw]) return aliases[raw];

        raw = raw.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        return catalog[raw] ? raw : 'little-princess';
    }

    function applyTheme(canvas, themeKey) {
        if (!canvas) return;
        [
            'theme-baptism-little-princess', 'theme-baptism-blush-angel', 'theme-baptism-rosy-grace', 'theme-baptism-sweet-peony',
            'theme-baptism-pink-cherub', 'theme-baptism-little-prince', 'theme-baptism-blue-angel', 'theme-baptism-gentle-sailor',
            'theme-baptism-sky-blessing', 'theme-baptism-royal-baptism', 'theme-baptism-twin-harmony', 'theme-baptism-triple-blessing',
            'theme-baptism-heavenly-stars', 'theme-baptism-little-miracles', 'theme-baptism-angelic-trio'
        ].forEach(function (c) { canvas.classList.remove(c); });

        var key = normalizeThemeKey(themeKey);
        var vertical = 'theme-baptism-' + key;

        canvas.classList.add(vertical);
    }

    function invitationLayoutSignature(inv) {
        if (!inv || typeof inv !== 'object') return '';
        return JSON.stringify({
            theme: inv.theme || '',
            headline: inv.headline_display || inv.headline || '',
            parents: inv.parents || {},
            godparents: inv.godparents || {},
            message: inv.message || '',
            events: inv.events || {}
        });
    }

    function ensureBefore(selector, html, anchor, root) {
        var node = qs(selector, root);
        if (node) return node;
        var wrap = document.createElement('div');
        wrap.innerHTML = html;
        node = wrap.firstChild;
        if (anchor && anchor.parentNode === root) root.insertBefore(node, anchor);
        else root.appendChild(node);
        return node;
    }

    function renderInvitation(inv) {
        if (!inv) return;
        var canvas = getCanvas();
        if (!canvas) return;

        applyTheme(canvas, inv.theme || 'little-princess');
        window.__TEINVIT_LAYOUT_SIG__ = invitationLayoutSignature(inv);

        if (window.__TEINVIT_PDF_MODE__) {
            window.TEINVIT_RENDER_READY = false;
            window.__TEINVIT_PDF_READY__ = false;
        }

        var names = qs('.inv-names', canvas);
        if (names) {
            var formattedHeadline = String(inv.headline_display || '').trim();
            if (!formattedHeadline) {
                formattedHeadline = formatNamesWeddingRule(inv.name_units || [], inv.name_line_limit || 22, inv.headline || '');
            }
            names.textContent = formattedHeadline;
            names.style.whiteSpace = 'pre-line';
        }

        var eventsWrap = qs('.inv-events', canvas) || ensureBefore('.inv-events', '<div class="inv-events"><div class="events-row top"></div><div class="events-row bottom"></div></div>', null, canvas);
        var msg = qs('.inv-message', canvas) || ensureBefore('.inv-message', '<div class="inv-message"></div>', eventsWrap, canvas);
        var parents = qs('.inv-parents-wrapper', canvas) || ensureBefore('.inv-parents-wrapper', '<div class="inv-parents-wrapper"><div class="section-title">ÎMPREUNĂ CU PĂRINȚII</div><div class="inv-parents inv-parents-grid"><div class="inv-parent-col inv-parent-mireasa"></div><div class="inv-parent-sep" aria-hidden="true">&</div><div class="inv-parent-col inv-parent-mire"></div></div></div>', msg, canvas);
        var nasi = qs('.inv-nasi', canvas) || ensureBefore('.inv-nasi', '<div class="inv-nasi"><div class="section-title">ȘI CU NAȘII</div><div class="nasi-row inv-parents-grid"><div class="inv-parent-col nasi-godmother"></div><div class="inv-parent-sep" aria-hidden="true">&</div><div class="inv-parent-col nasi-godfather"></div></div></div>', msg, canvas);

        var enabledParents = !!(inv.parents && inv.parents.enabled);
        parents.style.display = enabledParents ? '' : 'none';
        var mother = qs('.inv-parent-mireasa', parents);
        var father = qs('.inv-parent-mire', parents);
        var amp = qs('.inv-parent-sep', parents);
        var parentTitle = qs('.section-title', parents);
        var onlyOneParent = !!(Boolean(inv.parents && inv.parents.mother) !== Boolean(inv.parents && inv.parents.father));
        if (parentTitle) parentTitle.textContent = (inv.parents && inv.parents.title) || 'ÎMPREUNĂ CU PĂRINȚII';
        if (mother) mother.textContent = (inv.parents && inv.parents.mother) || '';
        if (father) father.textContent = (inv.parents && inv.parents.father) || '';
        if (amp) amp.style.visibility = ((inv.parents && inv.parents.mother) && (inv.parents && inv.parents.father)) ? 'visible' : 'hidden';
        var parentGrid = qs('.inv-parents-grid', parents);
        if (parentGrid) parentGrid.classList.toggle('is-single', onlyOneParent);

        var enabledNasi = !!(inv.godparents && inv.godparents.enabled);
        nasi.style.display = enabledNasi ? '' : 'none';
        var row = qs('.nasi-row', nasi);
        var godTitle = qs('.section-title', nasi);
        if (row) {
            var godmother = (inv.godparents && inv.godparents.godmother) || '';
            var godfather = (inv.godparents && inv.godparents.godfather) || '';
            var godSep = qs('.inv-parent-sep', row);
            var godLeft = qs('.nasi-godmother', row);
            var godRight = qs('.nasi-godfather', row);
            var onlyOneGodparent = !!(Boolean(godmother) !== Boolean(godfather));
            if (godTitle) godTitle.textContent = (inv.godparents && inv.godparents.title) || 'ȘI CU NAȘII';
            if (godLeft) godLeft.textContent = godmother;
            if (godRight) godRight.textContent = godfather;
            if (godSep) godSep.style.visibility = (godmother && godfather) ? 'visible' : 'hidden';
            row.classList.toggle('is-single', onlyOneGodparent);
        }

        msg.textContent = inv.message || '';

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
            var html = '<strong>' + (e.title || '') + '</strong><div class="inv-place">' + (e.loc || '') + '</div><div class="inv-datetime">' + (e.date || '') + '</div>';
            if (e.waze) html += '<a href="' + e.waze + '" target="_blank" rel="noopener">Deschide în Waze</a>';
            node.innerHTML = html;
            top.appendChild(node);
        });

        applyAutoFit(canvas);
        distributeVerticalSpace(canvas);
        scheduleFinalPass(canvas);

        if (window.__TEINVIT_PDF_MODE__) {
            schedulePdfReadyCheck(canvas);
        } else {
            window.TEINVIT_RENDER_READY = true;
        }
    }

    function hasOverflow(el) { return engine() ? engine().hasOverflow(el) : (el && (el.scrollHeight > el.clientHeight + 1 || el.scrollWidth > el.clientWidth + 1)); }

    function schedulePdfReadyCheck(canvas) {
        if (!window.__TEINVIT_PDF_MODE__ || !canvas) return;
        if (pdfReadyCheckTimer) clearTimeout(pdfReadyCheckTimer);
        pdfReadyCheckTimer = setTimeout(function () {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    if (hasOverflow(canvas) && pdfReadyCheckAttempts < 4) {
                        pdfReadyCheckAttempts++;
                        window.__TEINVIT_AUTOFIT_DONE__ = false;
                        applyAutoFit(canvas);
                        schedulePdfReadyCheck(canvas);
                        return;
                    }
                    window.TEINVIT_RENDER_READY = true;
                    window.__TEINVIT_PDF_READY__ = true;
                });
            });
        }, 40);
    }

    function applyAutoFit(canvas) {
        if (!canvas) return;
        var currentSig = window.__TEINVIT_LAYOUT_SIG__ || '';
        var lastSig = window.__TEINVIT_LAST_AUTOFIT_SIG__ || '';
        if (
            (window.__TEINVIT_PDF_MODE__ || window.TEINVIT_INVITATION_DATA) &&
            window.__TEINVIT_AUTOFIT_DONE__ &&
            currentSig !== '' &&
            currentSig === lastSig
        ) {
            return;
        }
        if (engine() && typeof engine().autoFit === 'function') {
            engine().autoFit(canvas, { min: 0.58, step: 0.02, maxLoops: 60 });
        }
        if (window.__TEINVIT_PDF_MODE__ || window.TEINVIT_INVITATION_DATA) {
            window.__TEINVIT_AUTOFIT_DONE__ = true;
            window.__TEINVIT_LAST_AUTOFIT_SIG__ = currentSig;
        }
    }

    function distributeVerticalSpace(canvas) {
        if (!canvas) return;
        if (engine() && typeof engine().distributeVerticalSpace === 'function') {
            engine().distributeVerticalSpace(canvas, ['.inv-names', '.inv-parents-wrapper', '.inv-nasi', '.inv-message', '.inv-events'], { reserve: 10, minGap: 6, maxGap: 26 });
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
        var sig = layoutSignature(canvas);
        if (engine() && typeof engine().scheduleFinalPass === 'function') {
            engine().scheduleFinalPass(canvas, 'baptism', sig, function () {
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
                if (val.length > MESSAGE_MAX) {
                    textarea.value = val.substring(0, MESSAGE_MAX);
                }
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
        window.__TEINVIT_AUTOFIT_DONE__ = false;
        window.__TEINVIT_LAST_AUTOFIT_SIG__ = '';
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

    document.addEventListener('teinvit:variant-applied', function () {
        window.__TEINVIT_AUTOFIT_DONE__ = false;
        window.__TEINVIT_LAST_AUTOFIT_SIG__ = '';
        pdfReadyCheckAttempts = 0;
    });

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () {
            window.__TEINVIT_AUTOFIT_DONE__ = false;
            window.__TEINVIT_LAST_AUTOFIT_SIG__ = '';
            var data = window.TEINVIT_INVITATION_DATA;
            if (data) renderInvitation(data);
        }).catch(function () {});
    }
})();
