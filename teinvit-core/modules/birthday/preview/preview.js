(function () {
    var REPEATABLE_ID = 'd1fe0da';
    var MESSAGE_ID = 'bef895a';
    var MESSAGE_MAX = 255;
    var BUILD_DEBOUNCE_MS = 140;
    var buildTimer = null;
    var buildSeq = 0;
    var lastAppliedSeq = 0;
    var inFlightController = null;
    var pdfReadyCheckTimer = null;
    var pdfReadyCheckAttempts = 0;
    var FINAL_PRODUCT_PASS_DEBOUNCE_MS = 320;
    var finalProductPassTimer = null;
    var lastStableProductSignature = '';
    var finalizedProductSignature = '';
    var previewResizeObserver = null;
    var pdfFontsReady = !(document.fonts && document.fonts.ready);
    var lastPreviewBoxSignature = '';
    var pendingPreviewLayoutCanvas = null;
    var pendingPdfLayoutCanvas = null;

    function qs(sel, root) { return (root || document).querySelector(sel); }
    function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    function getRoot() { return qs('#teinvit-vertical-product-preview') || document; }
    function getCanvas() { return qs('.teinvit-canvas', getRoot()) || qs('.teinvit-canvas'); }
    function isProductPreviewContext() { return !!qs('#teinvit-vertical-product-preview[data-product-id]'); }
    function isDeferredAdminPreviewContext() {
        var cfg = window.teinvitBirthdayPreviewConfig || {};
        return isProductPreviewContext() && !!(cfg.adminClient || cfg.deferInitialBuild);
    }
    function canScheduleWapfBuild() {
        return !(isDeferredAdminPreviewContext() && !window.__TEINVIT_BIRTHDAY_WAPF_READY__);
    }
    function engine() { return window.TEINVIT_PREVIEW_LAYOUT_ENGINE || null; }

    function parseFieldId(name) {
        var raw = String(name || '').trim();
        var m = raw.match(/field_([a-z0-9_\-]+)/i);
        if (!m) return '';
        return String(m[1] || '').replace(/_(?:clone_)?\d+$/, '').trim();
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
        aliases.editorial = 'editorial-luxury';
        aliases.romantic = 'romantic-floral';
        aliases.modern = 'modern-minimal';
        aliases.classic = 'classic-elegant';
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

    var DIVIDER_SVG = {
        'editorial-luxury': '<line stroke="#C9A968" stroke-dasharray="6 5" stroke-linecap="round" stroke-width="1.8" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#C9A968" stroke-dasharray="6 5" stroke-linecap="round" stroke-width="1.8" x1="202" x2="308" y1="35" y2="35"></line><circle cx="160" cy="35" fill="white" opacity="0.55" r="16" stroke="#1F4E5F" stroke-width="1.4"></circle><text fill="#7C7C7C" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">◆</text><text fill="#7C7C7C" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">◆</text><text fill="#202020" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">♡</text>',
        'romantic-floral': '<line stroke="#BFA76F" stroke-linecap="round" stroke-width="2.4" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#BFA76F" stroke-linecap="round" stroke-width="2.4" x1="202" x2="308" y1="35" y2="35"></line><text fill="#6E635E" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#6E635E" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#B86B7A" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">✶</text>',
        'modern-minimal': '<line stroke="#A69A67" stroke-dasharray="10 6" stroke-linecap="round" stroke-width="1.6" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#A69A67" stroke-dasharray="10 6" stroke-linecap="round" stroke-width="1.6" x1="202" x2="308" y1="35" y2="35"></line><text fill="#667063" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#667063" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#202420" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">✦ ✦</text>',
        'classic-elegant': '<line stroke="#B99B5B" stroke-dasharray="2 5" stroke-linecap="round" stroke-width="2.0" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#B99B5B" stroke-dasharray="2 5" stroke-linecap="round" stroke-width="2.0" x1="202" x2="308" y1="35" y2="35"></line><text fill="#6A635C" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#6A635C" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#2E2A27" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">♥</text>',
        'playful-confetti': '<line stroke="#F7A541" stroke-linecap="round" stroke-width="1.8" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#F7A541" stroke-linecap="round" stroke-width="1.8" x1="202" x2="308" y1="35" y2="35"></line><circle cx="160" cy="35" fill="white" opacity="0.55" r="16" stroke="#2D5B8C" stroke-width="1.4"></circle><text fill="#5D7A79" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">◆</text><text fill="#5D7A79" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">◆</text><text fill="#14B8A6" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">✧</text>',
        'candy-pastel': '<line stroke="#F9A8D4" stroke-linecap="round" stroke-width="2.0" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#F9A8D4" stroke-linecap="round" stroke-width="2.0" x1="202" x2="308" y1="35" y2="35"></line><text fill="#6C8A82" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#6C8A82" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#34D399" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">✦♡✦</text>',
        'storybook-dream': '<line stroke="#DDBB7A" stroke-dasharray="12 4" stroke-linecap="round" stroke-width="1.8" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#DDBB7A" stroke-dasharray="12 4" stroke-linecap="round" stroke-width="1.8" x1="202" x2="308" y1="35" y2="35"></line><text fill="#826E90" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#826E90" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#9D4EDD" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">★</text>',
        'balloon-party': '<line stroke="#F6C453" stroke-dasharray="1 6" stroke-linecap="round" stroke-width="2.2" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#F6C453" stroke-dasharray="1 6" stroke-linecap="round" stroke-width="2.2" x1="202" x2="308" y1="35" y2="35"></line><text fill="#6B7593" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#6B7593" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#FF4EC9" font-size="18" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">• ✦ •</text>',
        'golden-celebration': '<line stroke="#E3B23C" stroke-dasharray="6 4" stroke-linecap="round" stroke-width="1.5" x1="12" x2="118" y1="31" y2="31"></line><line stroke="#E3B23C" stroke-dasharray="6 4" stroke-linecap="round" stroke-width="1.5" x1="202" x2="308" y1="31" y2="31"></line><line stroke="#7C6E53" stroke-dasharray="6 4" stroke-linecap="round" stroke-width="1.2" x1="12" x2="118" y1="39" y2="39"></line><line stroke="#7C6E53" stroke-dasharray="6 4" stroke-linecap="round" stroke-width="1.2" x1="202" x2="308" y1="39" y2="39"></line><text fill="#7C6E53" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">✧</text><text fill="#7C6E53" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">✧</text><text fill="#9C6B00" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">⟡</text>',
        'chic-blush': '<line stroke="#D3A46A" stroke-linecap="round" stroke-width="1.8" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#D3A46A" stroke-linecap="round" stroke-width="1.8" x1="202" x2="308" y1="35" y2="35"></line><text fill="#766571" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#766571" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#E879A6" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">♡ ♡</text>',
        'midnight-glam': '<line stroke="#E6B85C" stroke-linecap="round" stroke-width="1.6" x1="12" x2="118" y1="31" y2="31"></line><line stroke="#E6B85C" stroke-linecap="round" stroke-width="1.6" x1="202" x2="308" y1="31" y2="31"></line><line stroke="#675A78" stroke-linecap="round" stroke-width="1.3" x1="12" x2="118" y1="39" y2="39"></line><line stroke="#675A78" stroke-linecap="round" stroke-width="1.3" x1="202" x2="308" y1="39" y2="39"></line><text fill="#675A78" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#675A78" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#E879F9" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">◆ ◆</text>',
        'botanical-grace': '<line stroke="#C2A14A" stroke-dasharray="2 4" stroke-linecap="round" stroke-width="2.0" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#C2A14A" stroke-dasharray="2 4" stroke-linecap="round" stroke-width="2.0" x1="202" x2="308" y1="35" y2="35"></line><text fill="#708172" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#708172" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#506B52" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">✧</text>',
        'royal-blue': '<line stroke="#E5C46A" stroke-linecap="round" stroke-width="2.2" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#E5C46A" stroke-linecap="round" stroke-width="2.2" x1="202" x2="308" y1="35" y2="35"></line><text fill="#66769F" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">◆</text><text fill="#66769F" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">◆</text><text fill="#1E3A8A" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">✦</text>',
        'velvet-noir': '<line stroke="#D7A56A" stroke-dasharray="8 6" stroke-linecap="round" stroke-width="1.8" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#D7A56A" stroke-dasharray="8 6" stroke-linecap="round" stroke-width="1.8" x1="202" x2="308" y1="35" y2="35"></line><text fill="#665864" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">◆</text><text fill="#665864" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">◆</text><text fill="#C24163" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">♥</text>',
        'sunset-fiesta': '<line stroke="#FBBF24" stroke-dasharray="3 4" stroke-linecap="round" stroke-width="2.1" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#FBBF24" stroke-dasharray="3 4" stroke-linecap="round" stroke-width="2.1" x1="202" x2="308" y1="35" y2="35"></line><text fill="#8B6A5C" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#8B6A5C" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#F97316" font-size="18" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">✦ • ✦</text>'
    };

    function renderApprovedDivider(canvas, themeKey) {
        if (!canvas) return null;
        var divider = qs('.inv-divider', canvas) || ensureNode('.inv-divider', '<div class="inv-divider" aria-hidden="true"></div>', canvas);
        var key = normalizeThemeKey(themeKey);
        divider.innerHTML = '<svg aria-hidden="true" focusable="false" viewBox="0 0 320 70" preserveAspectRatio="xMidYMid meet">' + vectorizeDividerSvg(DIVIDER_SVG[key] || DIVIDER_SVG['editorial-luxury']) + '</svg>';
        divider.style.display = '';
        return divider;
    }

    function attrValue(attrs, name, fallback) {
        var re = new RegExp(name + '="([^"]*)"', 'i');
        var match = String(attrs || '').match(re);
        return match ? match[1] : fallback;
    }

    function vectorSymbol(symbol, x, y, fill, size) {
        var s = Math.max(8, parseFloat(size || '14') || 14);
        var cx = parseFloat(x || '0') || 0;
        var cy = (parseFloat(y || '0') || 0) - s * 0.25;
        var color = fill || 'currentColor';
        if (symbol === '•') return '<circle cx="' + cx + '" cy="' + cy + '" r="' + (s * 0.22).toFixed(2) + '" fill="' + color + '"></circle>';
        if (symbol === '◆') return '<polygon points="' + cx + ',' + (cy - s * 0.34).toFixed(2) + ' ' + (cx + s * 0.34).toFixed(2) + ',' + cy + ' ' + cx + ',' + (cy + s * 0.34).toFixed(2) + ' ' + (cx - s * 0.34).toFixed(2) + ',' + cy + '" fill="' + color + '"></polygon>';
        if (symbol === '♥' || symbol === '♡') return '<path d="M ' + cx + ' ' + (cy + s * 0.34).toFixed(2) + ' C ' + (cx - s * 0.62).toFixed(2) + ' ' + (cy - s * 0.08).toFixed(2) + ' ' + (cx - s * 0.34).toFixed(2) + ' ' + (cy - s * 0.54).toFixed(2) + ' ' + cx + ' ' + (cy - s * 0.24).toFixed(2) + ' C ' + (cx + s * 0.34).toFixed(2) + ' ' + (cy - s * 0.54).toFixed(2) + ' ' + (cx + s * 0.62).toFixed(2) + ' ' + (cy - s * 0.08).toFixed(2) + ' ' + cx + ' ' + (cy + s * 0.34).toFixed(2) + ' Z" ' + (symbol === '♡' ? 'fill="none" stroke="' + color + '" stroke-width="' + Math.max(1.4, s * 0.08).toFixed(2) + '"' : 'fill="' + color + '"') + '></path>';
        if (symbol === '★') return '<path d="M ' + cx + ' ' + (cy - s * 0.48).toFixed(2) + ' L ' + (cx + s * 0.14).toFixed(2) + ' ' + (cy - s * 0.12).toFixed(2) + ' L ' + (cx + s * 0.52).toFixed(2) + ' ' + (cy - s * 0.12).toFixed(2) + ' L ' + (cx + s * 0.21).toFixed(2) + ' ' + (cy + s * 0.09).toFixed(2) + ' L ' + (cx + s * 0.32).toFixed(2) + ' ' + (cy + s * 0.45).toFixed(2) + ' L ' + cx + ' ' + (cy + s * 0.23).toFixed(2) + ' L ' + (cx - s * 0.32).toFixed(2) + ' ' + (cy + s * 0.45).toFixed(2) + ' L ' + (cx - s * 0.21).toFixed(2) + ' ' + (cy + s * 0.09).toFixed(2) + ' L ' + (cx - s * 0.52).toFixed(2) + ' ' + (cy - s * 0.12).toFixed(2) + ' L ' + (cx - s * 0.14).toFixed(2) + ' ' + (cy - s * 0.12).toFixed(2) + ' Z" fill="' + color + '"></path>';
        if (symbol === '∞') return '<g fill="none" stroke="' + color + '" stroke-width="' + Math.max(1.5, s * 0.1).toFixed(2) + '"><ellipse cx="' + (cx - s * 0.23).toFixed(2) + '" cy="' + cy + '" rx="' + (s * 0.24).toFixed(2) + '" ry="' + (s * 0.16).toFixed(2) + '"></ellipse><ellipse cx="' + (cx + s * 0.23).toFixed(2) + '" cy="' + cy + '" rx="' + (s * 0.24).toFixed(2) + '" ry="' + (s * 0.16).toFixed(2) + '"></ellipse></g>';
        if (symbol === '✝') return '<g stroke="' + color + '" stroke-width="' + Math.max(1.8, s * 0.12).toFixed(2) + '" stroke-linecap="round"><line x1="' + cx + '" x2="' + cx + '" y1="' + (cy - s * 0.48).toFixed(2) + '" y2="' + (cy + s * 0.48).toFixed(2) + '"></line><line x1="' + (cx - s * 0.27).toFixed(2) + '" x2="' + (cx + s * 0.27).toFixed(2) + '" y1="' + (cy - s * 0.12).toFixed(2) + '" y2="' + (cy - s * 0.12).toFixed(2) + '"></line></g>';
        if (symbol === '❀' || symbol === '❦') return '<g fill="' + color + '"><circle cx="' + cx + '" cy="' + cy + '" r="' + (s * 0.15).toFixed(2) + '"></circle><circle cx="' + cx + '" cy="' + (cy - s * 0.28).toFixed(2) + '" r="' + (s * 0.15).toFixed(2) + '"></circle><circle cx="' + (cx + s * 0.27).toFixed(2) + '" cy="' + cy + '" r="' + (s * 0.15).toFixed(2) + '"></circle><circle cx="' + cx + '" cy="' + (cy + s * 0.28).toFixed(2) + '" r="' + (s * 0.15).toFixed(2) + '"></circle><circle cx="' + (cx - s * 0.27).toFixed(2) + '" cy="' + cy + '" r="' + (s * 0.15).toFixed(2) + '"></circle></g>';
        return '<g fill="' + color + '"><polygon points="' + cx + ',' + (cy - s * 0.48).toFixed(2) + ' ' + (cx + s * 0.12).toFixed(2) + ',' + (cy - s * 0.12).toFixed(2) + ' ' + (cx + s * 0.48).toFixed(2) + ',' + cy + ' ' + (cx + s * 0.12).toFixed(2) + ',' + (cy + s * 0.12).toFixed(2) + ' ' + cx + ',' + (cy + s * 0.48).toFixed(2) + ' ' + (cx - s * 0.12).toFixed(2) + ',' + (cy + s * 0.12).toFixed(2) + ' ' + (cx - s * 0.48).toFixed(2) + ',' + cy + ' ' + (cx - s * 0.12).toFixed(2) + ',' + (cy - s * 0.12).toFixed(2) + '"></polygon></g>';
    }

    function vectorizeDividerSvg(svg) {
        return String(svg || '').replace(/<text\b([^>]*)>([\s\S]*?)<\/text>/gi, function (_, attrs, content) {
            var fill = attrValue(attrs, 'fill', '#000');
            var x = parseFloat(attrValue(attrs, 'x', '0')) || 0;
            var y = parseFloat(attrValue(attrs, 'y', '0')) || 0;
            var size = parseFloat(attrValue(attrs, 'font-size', '14')) || 14;
            var symbols = String(content || '').trim().split(/\s+/).filter(Boolean);
            if (!symbols.length) return '';
            var start = x - ((symbols.length - 1) * size * 0.42);
            return symbols.map(function (symbol, index) {
                return vectorSymbol(symbol, start + index * size * 0.84, y, fill, size);
            }).join('');
        });
    }

    function invitationLayoutSignature(inv) {
        if (!inv || typeof inv !== 'object') return '';
        return JSON.stringify({
            theme: inv.theme || '',
            headline: inv.headline_display || inv.headline || '',
            age: inv.age || {},
            event_name: inv.event_name || {},
            message: inv.message || '',
            events: inv.events || {}
        });
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
        window.__TEINVIT_LAYOUT_SIG__ = invitationLayoutSignature(inv);
        window.__TEINVIT_AUTOFIT_DONE__ = false;
        window.__TEINVIT_LAST_AUTOFIT_SIG__ = '';

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

        var age = qs('.inv-age', canvas) || ensureNode('.inv-age', '<div class="inv-age"></div>', canvas);
        var eventName = qs('.inv-event-name', canvas) || ensureNode('.inv-event-name', '<div class="inv-event-name"></div>', canvas);
        var divider = renderApprovedDivider(canvas, inv.theme || 'editorial-luxury');
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
        if (divider && eventName && divider.parentNode === canvas) {
            canvas.insertBefore(divider, eventName.nextSibling);
        }
        if (divider && msg && divider.parentNode === canvas) {
            canvas.insertBefore(msg, divider.nextSibling);
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
        return canvas;
    }

    function hasOverflow(el) { return engine() ? engine().hasOverflow(el) : (el && (el.scrollHeight > el.clientHeight + 1 || el.scrollWidth > el.clientWidth + 1)); }

    function schedulePdfReadyCheck(canvas) {
        if (!window.__TEINVIT_PDF_MODE__ || !canvas) return;
        pendingPdfLayoutCanvas = canvas;
        if (!pdfFontsReady) return;
        if (pdfReadyCheckTimer) clearTimeout(pdfReadyCheckTimer);
        pdfReadyCheckTimer = setTimeout(function () {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    if (pendingPdfLayoutCanvas !== canvas) return;
                    finalizeBirthdayPdfLayout(canvas);
                    if (hasOverflow(canvas)) return;
                    pendingPdfLayoutCanvas = null;
                    window.TEINVIT_RENDER_READY = true;
                    window.__TEINVIT_PDF_READY__ = true;
                });
            });
        }, 0);
    }

    function schedulePreviewFinalLayout(canvas) {
        if (!canvas || window.__TEINVIT_PDF_MODE__) return;
        pendingPreviewLayoutCanvas = canvas;
        if (!pdfFontsReady) return;
        if (finalTimer) clearTimeout(finalTimer);
        finalTimer = setTimeout(function () {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    if (pendingPreviewLayoutCanvas !== canvas) return;
                    finalizeBirthdayPreviewLayout(canvas);
                    pendingPreviewLayoutCanvas = null;
                    window.TEINVIT_RENDER_READY = true;
                });
            });
        }, 0);
    }

    function parseCssNumber(canvas, variableName, fallback) {
        if (!canvas || !window.getComputedStyle) return fallback;
        var raw = window.getComputedStyle(canvas).getPropertyValue(variableName);
        var value = parseFloat(String(raw || '').replace(',', '.'));
        return isFinite(value) ? value : fallback;
    }

    function countNameLines(canvas) {
        var names = qs('.inv-names', canvas);
        if (!names) return 1;
        var raw = String(names.textContent || '').split('\n').filter(function (line) { return String(line || '').trim() !== ''; }).length;
        return raw > 0 ? raw : 1;
    }

    function resolveBirthdayNameBaseSize(canvas, lines) {
        var nameSize = parseCssNumber(canvas, '--b-size-names', 2.2);
        if (lines >= 4) {
            nameSize = parseCssNumber(canvas, '--b-name-size-4', nameSize);
        } else if (lines >= 3) {
            nameSize = parseCssNumber(canvas, '--b-name-size-3', nameSize);
        }
        return nameSize;
    }

    function getBirthdayNameBand(canvas, lines) {
        var safeLines = Math.max(1, parseInt(lines, 10) || 1);
        var preview = canvas ? canvas.parentElement : null;
        var previewStyle = preview && window.getComputedStyle ? window.getComputedStyle(preview) : null;
        var previewFontPx = previewStyle ? parseFloat(previewStyle.fontSize || '0') : 0;
        var baseFontPx = previewFontPx > 0 ? previewFontPx : 17.25;
        var baseNameSize = resolveBirthdayNameBaseSize(canvas, safeLines);
        var lineBox = baseFontPx * baseNameSize * 1.12;
        var growthStep = Math.max(12, Math.round(lineBox * 0.4));
        var height = 122 + (Math.max(0, safeLines - 1) * growthStep);

        if (safeLines <= 1) return { height: height, min: 1.98, grow: 1.42, step: 0.03 };
        if (safeLines === 2) return { height: height, min: 1.80, grow: 1.28, step: 0.03 };
        if (safeLines === 3) return { height: height, min: 1.60, grow: 1.16, step: 0.02 };
        return { height: height, min: 1.44, grow: 1.08, step: 0.02 };
    }

    function nameOverflows(names, bandHeight) {
        if (!names) return false;
        return names.scrollWidth > names.clientWidth + 1 || names.scrollHeight > bandHeight + 1;
    }

    function applyBirthdayTypography(canvas, options) {
        if (!canvas) return { lines: 1, gap: 26, nameSize: 2.2 };
        var cfg = options || {};
        var lines = countNameLines(canvas);
        var age = qs('.inv-age', canvas);
        var names = qs('.inv-names', canvas);
        var eventName = qs('.inv-event-name', canvas);
        var message = qs('.inv-message', canvas);
        var eventTitle = qs('.inv-events .inv-event strong', canvas);
        var place = qs('.inv-events .inv-event .inv-place', canvas);
        var weekday = qs('.inv-events .inv-event .inv-weekday', canvas);
        var datetime = qs('.inv-events .inv-event .inv-datetime', canvas);
        var waze = qs('.inv-events .inv-event a', canvas);

        var nameSize = resolveBirthdayNameBaseSize(canvas, lines);
        var messageSize = parseCssNumber(canvas, '--b-size-message', 1.0);
        if (lines >= 4) {
            messageSize = parseCssNumber(canvas, '--b-message-size-4', messageSize);
        } else if (lines >= 3) {
            messageSize = parseCssNumber(canvas, '--b-message-size-3', messageSize);
        }

        if (age) age.style.fontSize = parseCssNumber(canvas, '--b-size-age', 1.55) + 'em';
        if (names && !cfg.preserveNameSize) names.style.fontSize = nameSize + 'em';
        if (eventName) eventName.style.fontSize = parseCssNumber(canvas, '--b-size-event', 1.12) + 'em';
        if (message) message.style.fontSize = messageSize + 'em';
        if (eventTitle) eventTitle.style.fontSize = parseCssNumber(canvas, '--b-size-caps', 0.98) + 'em';
        if (place) place.style.fontSize = parseCssNumber(canvas, '--b-size-place', 1.0) + 'em';
        if (weekday) weekday.style.fontSize = parseCssNumber(canvas, '--b-size-weekday', 0.92) + 'em';
        if (datetime) datetime.style.fontSize = parseCssNumber(canvas, '--b-size-date', 0.92) + 'em';
        if (waze) waze.style.fontSize = parseCssNumber(canvas, '--b-size-link', 0.88) + 'em';

        var gap = lines >= 4 ? parseCssNumber(canvas, '--b-gap-tight', parseCssNumber(canvas, '--b-gap-base', 26)) : parseCssNumber(canvas, '--b-gap-base', 26);
        return { lines: lines, gap: gap, nameSize: nameSize };
    }

    function protectNameSection(canvas) {
        var names = qs('.inv-names', canvas);
        if (!names) return getBirthdayNameBand(canvas, 1);
        var lines = countNameLines(canvas);
        var band = getBirthdayNameBand(canvas, lines);
        names.style.minHeight = band.height + 'px';
        names.style.display = 'flex';
        names.style.alignItems = 'center';
        names.style.justifyContent = 'center';
        names.style.width = '100%';
        return band;
    }

    function fitBirthdayNameText(canvas, shrinkOnly) {
        var names = qs('.inv-names', canvas);
        if (!names) return;
        var lines = countNameLines(canvas);
        var band = protectNameSection(canvas);
        var baseSize = resolveBirthdayNameBaseSize(canvas, lines);
        var step = band.step;
        var size = shrinkOnly ? (parseFloat(names.style.fontSize || '0') || baseSize) : baseSize;
        var maxSize = baseSize * band.grow;

        names.style.fontSize = size + 'em';
        if (!shrinkOnly) {
            while (!nameOverflows(names, band.height) && size + step <= maxSize) {
                size = Math.round((size + step) * 100) / 100;
                names.style.fontSize = size + 'em';
            }
            if (nameOverflows(names, band.height)) {
                size = Math.round((size - step) * 100) / 100;
                names.style.fontSize = size + 'em';
            }
        }

        while (nameOverflows(names, band.height) && size > band.min) {
            size = Math.round((size - step) * 100) / 100;
            names.style.fontSize = size + 'em';
        }
    }

    function compactBirthdaySecondary(canvas) {
        var msg = qs('.inv-message', canvas);
        var msgSize = msg ? parseFloat(msg.style.fontSize || '0') : 0;
        while (hasOverflow(canvas) && msg && msgSize > 0.92) {
            msgSize = Math.round((msgSize - 0.02) * 100) / 100;
            msg.style.fontSize = msgSize + 'em';
            distributeVerticalSpace(canvas);
        }

        qsa('.inv-events .inv-event, .inv-event-name, .inv-age', canvas).forEach(function (node) {
            var size = parseFloat(node.style.fontSize || '0') || 1;
            while (hasOverflow(canvas) && size > 0.82) {
                size = Math.round((size - 0.02) * 100) / 100;
                node.style.fontSize = size + 'em';
                distributeVerticalSpace(canvas);
            }
        });
    }

    function finalizeBirthdayPdfLayout(canvas) {
        if (!canvas) return;
        window.__TEINVIT_FINAL_PASS_DONE__ = false;
        canvas.style.fontSize = '1em';
        applyBirthdayTypography(canvas);
        fitBirthdayNameText(canvas, false);
        distributeVerticalSpace(canvas);
        compactBirthdaySecondary(canvas);
        if (hasOverflow(canvas)) {
            if (engine() && typeof engine().autoFit === 'function') {
                engine().autoFit(canvas, { min: 0.72, step: 0.01, maxLoops: 30 });
                applyBirthdayTypography(canvas);
                fitBirthdayNameText(canvas, false);
                distributeVerticalSpace(canvas);
                compactBirthdaySecondary(canvas);
            }
        }
        if (hasOverflow(canvas)) {
            fitBirthdayNameText(canvas, true);
            distributeVerticalSpace(canvas);
        }
        protectNameSection(canvas);
        window.__TEINVIT_AUTOFIT_DONE__ = true;
        window.__TEINVIT_LAST_AUTOFIT_SIG__ = window.__TEINVIT_LAYOUT_SIG__ || '';
        window.__TEINVIT_FINAL_PASS_DONE__ = true;
    }

    function finalizeBirthdayPreviewLayout(canvas) {
        if (!canvas) return;
        window.__TEINVIT_FINAL_PASS_DONE__ = false;
        canvas.style.fontSize = '1em';
        applyBirthdayTypography(canvas);
        fitBirthdayNameText(canvas, false);
        distributeVerticalSpace(canvas);
        compactBirthdaySecondary(canvas);
        if (hasOverflow(canvas) && engine() && typeof engine().autoFit === 'function') {
            engine().autoFit(canvas, { min: 0.72, step: 0.01, maxLoops: 30 });
            applyBirthdayTypography(canvas);
            fitBirthdayNameText(canvas, false);
            distributeVerticalSpace(canvas);
            compactBirthdaySecondary(canvas);
        }
        if (hasOverflow(canvas)) {
            fitBirthdayNameText(canvas, true);
            distributeVerticalSpace(canvas);
        }
        protectNameSection(canvas);
        window.__TEINVIT_AUTOFIT_DONE__ = true;
        window.__TEINVIT_LAST_AUTOFIT_SIG__ = window.__TEINVIT_LAYOUT_SIG__ || '';
        window.__TEINVIT_FINAL_PASS_DONE__ = true;
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
        var tuning = applyBirthdayTypography(canvas);
        fitBirthdayNameText(canvas, false);
        var gap = tuning.gap;
        var minGap = 10;
        while (hasOverflow(canvas) && gap > minGap) {
            gap -= 2;
            distributeVerticalSpace(canvas, gap);
        }
        compactBirthdaySecondary(canvas);
        if (hasOverflow(canvas)) {
            fitBirthdayNameText(canvas, true);
        }

        if (hasOverflow(canvas) && engine() && typeof engine().autoFit === 'function') {
            engine().autoFit(canvas, { min: 0.72, step: 0.01, maxLoops: 30 });
            applyBirthdayTypography(canvas);
            fitBirthdayNameText(canvas, false);
            if (hasOverflow(canvas)) fitBirthdayNameText(canvas, true);
        }
        if (window.__TEINVIT_PDF_MODE__ || window.TEINVIT_INVITATION_DATA) {
            window.__TEINVIT_AUTOFIT_DONE__ = true;
            window.__TEINVIT_LAST_AUTOFIT_SIG__ = currentSig;
        }
    }

    function distributeVerticalSpace(canvas, forcedGap) {
        if (!canvas) return;
        var tuning = applyBirthdayTypography(canvas, { preserveNameSize: true });
        var gap = typeof forcedGap === 'number' ? forcedGap : tuning.gap;
        var age = qs('.inv-age', canvas);
        var names = qs('.inv-names', canvas);
        var eventName = qs('.inv-event-name', canvas);
        var msg = qs('.inv-message', canvas);
        var events = qs('.inv-events', canvas);
        var divider = qs('.inv-divider', canvas);
        var nodes = [age, names, eventName, divider, msg, events].filter(function (node) {
            return node && node.style.display !== 'none';
        });
        if (!nodes.length) return;
        protectNameSection(canvas);

        var usedHeight = nodes.reduce(function (acc, node) { return acc + node.offsetHeight; }, 0);
        var free = Math.max(0, canvas.clientHeight - usedHeight);
        var dynamicGap = Math.max(8, Math.min(36, Math.floor(free / (nodes.length + 1))));
        if (typeof forcedGap === 'number') {
            dynamicGap = forcedGap;
        } else if (tuning.lines >= 4) {
            dynamicGap = Math.min(dynamicGap, gap);
        } else {
            dynamicGap = Math.max(gap, dynamicGap);
        }

        nodes.forEach(function (node, index) {
            node.style.marginTop = dynamicGap + 'px';
            node.style.marginBottom = '0px';
        });
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
        return;
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

    function getProductSnapshotSignature() {
        var mount = qs('#teinvit-vertical-product-preview');
        if (!mount || !isProductPreviewContext() || window.__TEINVIT_PDF_MODE__) return '';
        return JSON.stringify({
            productId: mount.getAttribute('data-product-id') || '',
            wapf: collectWapfMapFromForm()
        });
    }

    function scheduleFinalProductPass() {
        if (!isProductPreviewContext() || window.__TEINVIT_PDF_MODE__) return;
        if (finalProductPassTimer) clearTimeout(finalProductPassTimer);
        finalProductPassTimer = setTimeout(function () {
            var signature = getProductSnapshotSignature();
            if (!signature || signature === finalizedProductSignature) return;
            lastStableProductSignature = signature;
            finalizedProductSignature = signature;
        }, FINAL_PRODUCT_PASS_DEBOUNCE_MS);
    }

    function setupPreviewResizeObserver() {
        var previewRoot = qs('.teinvit-preview');
        if (!previewRoot || window.__TEINVIT_PDF_MODE__ || typeof ResizeObserver === 'undefined' || previewResizeObserver) return;
        previewResizeObserver = new ResizeObserver(function () {
            var width = Math.round((previewRoot.clientWidth || 0) * 100) / 100;
            var height = Math.round((previewRoot.clientHeight || 0) * 100) / 100;
            var signature = width + 'x' + height;
            if (!width || !height || signature === lastPreviewBoxSignature) return;
            lastPreviewBoxSignature = signature;
            window.__TEINVIT_AUTOFIT_DONE__ = false;
            window.__TEINVIT_LAST_AUTOFIT_SIG__ = '';
            schedulePreviewFinalLayout(getCanvas());
        });
        previewResizeObserver.observe(previewRoot);
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

        buildSeq += 1;
        var requestSeq = buildSeq;
        if (inFlightController) {
            inFlightController.abort();
        }
        inFlightController = typeof AbortController !== 'undefined' ? new AbortController() : null;

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId, wapf_map: collectWapfMapFromForm() }),
            signal: inFlightController ? inFlightController.signal : undefined
        }).then(function (r) { return r.json(); }).then(function (json) {
            if (requestSeq < lastAppliedSeq) return;
            if (json && json.ok && json.invitation) {
                lastAppliedSeq = requestSeq;
                window.TEINVIT_INVITATION_DATA = json.invitation;
                var canvas = renderInvitation(json.invitation);
                schedulePreviewFinalLayout(canvas);
                scheduleFinalProductPass();
            }
        }).catch(function (err) {
            if (err && err.name === 'AbortError') return;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        window.__TEINVIT_AUTOFIT_DONE__ = false;
        window.__TEINVIT_LAST_AUTOFIT_SIG__ = '';
        clearPrefilledCloneInputs(document);
        setupMessageCounter();
        var isProductContext = !!qs('#teinvit-vertical-product-preview');
        if (window.TEINVIT_INVITATION_DATA && !isProductContext) {
            var canvas = renderInvitation(window.TEINVIT_INVITATION_DATA);
            if (window.__TEINVIT_PDF_MODE__) schedulePdfReadyCheck(canvas);
            else schedulePreviewFinalLayout(canvas);
        }
        if (isProductContext) {
            if (isDeferredAdminPreviewContext() && window.TEINVIT_INVITATION_DATA) {
                var adminCanvas = renderInvitation(window.TEINVIT_INVITATION_DATA);
                schedulePreviewFinalLayout(adminCanvas);
            } else {
                buildFromApi();
                scheduleFinalProductPass();
            }
        }
        setupPreviewResizeObserver();
    });

    document.addEventListener('input', function (e) {
        var t = e && e.target;
        if (!canScheduleWapfBuild()) return;
        if (t && t.name && t.name.indexOf('wapf[') === 0) {
            scheduleBuildFromApi();
        }
    });
    document.addEventListener('change', function (e) {
        var t = e && e.target;
        if (!canScheduleWapfBuild()) return;
        if (t && t.name && t.name.indexOf('wapf[') === 0 && (!t.type || t.type.toLowerCase() !== 'text') && t.tagName !== 'TEXTAREA') {
            scheduleBuildFromApi();
        }
    });
    document.addEventListener('wapf/date_selected', function () {
        if (!canScheduleWapfBuild()) return;
        setTimeout(function () {
            scheduleBuildFromApi();
        }, 30);
    });
    document.addEventListener('teinvit:birthday-wapf-hydrated', function () {
        if (!isProductPreviewContext()) return;
        window.__TEINVIT_BIRTHDAY_WAPF_READY__ = true;
        window.__TEINVIT_AUTOFIT_DONE__ = false;
        window.__TEINVIT_LAST_AUTOFIT_SIG__ = '';
        setupMessageCounter();
        scheduleBuildFromApi();
        scheduleFinalProductPass();
    });
    document.addEventListener('click', function (e) {
        var t = e && e.target;
        if (!canScheduleWapfBuild()) return;
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
        finalizedProductSignature = '';
        lastStableProductSignature = '';
        lastAppliedSeq = 0;
    });

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () {
            pdfFontsReady = true;
            if (pendingPdfLayoutCanvas) schedulePdfReadyCheck(pendingPdfLayoutCanvas);
            if (pendingPreviewLayoutCanvas) schedulePreviewFinalLayout(pendingPreviewLayoutCanvas);
        }).catch(function () {});
    }

})();
