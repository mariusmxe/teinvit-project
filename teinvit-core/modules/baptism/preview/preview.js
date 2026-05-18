(function () {
    var REPEATABLE_ID = '2d8d1ce';
    var MESSAGE_ID = '4c3baec';
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

    var DIVIDER_SVG = {
        'little-princess': '<line stroke="#C8A96A" stroke-dasharray="6 5" stroke-linecap="round" stroke-width="1.8" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#C8A96A" stroke-dasharray="6 5" stroke-linecap="round" stroke-width="1.8" x1="202" x2="308" y1="35" y2="35"></line><circle cx="160" cy="35" fill="white" opacity="0.55" r="16" stroke="#2B5A78" stroke-width="1.4"></circle><text fill="#6A5E5A" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">✧</text><text fill="#6A5E5A" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">✧</text><text fill="#C77D8C" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">∞</text>',
        'blush-angel': '<line stroke="#DCC38A" stroke-linecap="round" stroke-width="2.0" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#DCC38A" stroke-linecap="round" stroke-width="2.0" x1="202" x2="308" y1="35" y2="35"></line><text fill="#8C7E80" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">✦</text><text fill="#8C7E80" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">✦</text><text fill="#E3A8B9" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">✧</text>',
        'rosy-grace': '<line stroke="#C7A86A" stroke-linecap="round" stroke-width="1.8" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#C7A86A" stroke-linecap="round" stroke-width="1.8" x1="202" x2="308" y1="35" y2="35"></line><circle cx="160" cy="35" fill="white" opacity="0.55" r="16" stroke="#3F607A" stroke-width="1.4"></circle><text fill="#726271" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">✧</text><text fill="#726271" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">✧</text><text fill="#A96384" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">♡</text>',
        'sweet-peony': '<line stroke="#D2B179" stroke-linecap="round" stroke-width="1.5" x1="12" x2="118" y1="31" y2="31"></line><line stroke="#D2B179" stroke-linecap="round" stroke-width="1.5" x1="202" x2="308" y1="31" y2="31"></line><line stroke="#7D6B6D" stroke-linecap="round" stroke-width="1.2" x1="12" x2="118" y1="39" y2="39"></line><line stroke="#7D6B6D" stroke-linecap="round" stroke-width="1.2" x1="202" x2="308" y1="39" y2="39"></line><text fill="#7D6B6D" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">✧</text><text fill="#7D6B6D" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">✧</text><text fill="#C77E98" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">∞</text>',
        'pink-cherub': '<line stroke="#FACC15" stroke-linecap="round" stroke-width="1.4" x1="12" x2="118" y1="31" y2="31"></line><line stroke="#FACC15" stroke-linecap="round" stroke-width="1.4" x1="202" x2="308" y1="31" y2="31"></line><line stroke="#8B6B75" stroke-linecap="round" stroke-width="1.2" x1="12" x2="118" y1="39" y2="39"></line><line stroke="#8B6B75" stroke-linecap="round" stroke-width="1.2" x1="202" x2="308" y1="39" y2="39"></line><circle cx="160" cy="35" fill="white" opacity="0.55" r="16" stroke="#5B7FA3" stroke-width="1.4"></circle><text fill="#8B6B75" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#8B6B75" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#FB7185" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">❦</text>',
        'little-prince': '<line stroke="#D4AF37" stroke-dasharray="8 4" stroke-linecap="round" stroke-width="1.6" x1="12" x2="118" y1="31" y2="31"></line><line stroke="#D4AF37" stroke-dasharray="8 4" stroke-linecap="round" stroke-width="1.6" x1="202" x2="308" y1="31" y2="31"></line><line stroke="#6978A1" stroke-dasharray="8 4" stroke-linecap="round" stroke-width="1.3" x1="12" x2="118" y1="39" y2="39"></line><line stroke="#6978A1" stroke-dasharray="8 4" stroke-linecap="round" stroke-width="1.3" x1="202" x2="308" y1="39" y2="39"></line><text fill="#6978A1" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#6978A1" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#1E3A8A" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">✧ ✧</text>',
        'blue-angel': '<line stroke="#D6B46A" stroke-linecap="round" stroke-width="1.9" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#D6B46A" stroke-linecap="round" stroke-width="1.9" x1="202" x2="308" y1="35" y2="35"></line><text fill="#6A90A5" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#6A90A5" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#5DA9E9" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">∞ ✧</text>',
        'gentle-sailor': '<line stroke="#E0BE74" stroke-linecap="round" stroke-width="1.5" x1="12" x2="118" y1="31" y2="31"></line><line stroke="#E0BE74" stroke-linecap="round" stroke-width="1.5" x1="202" x2="308" y1="31" y2="31"></line><line stroke="#68839B" stroke-linecap="round" stroke-width="1.2" x1="12" x2="118" y1="39" y2="39"></line><line stroke="#68839B" stroke-linecap="round" stroke-width="1.2" x1="202" x2="308" y1="39" y2="39"></line><text fill="#68839B" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#68839B" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#22577A" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">∞ ∞</text>',
        'sky-blessing': '<line stroke="#D4B072" stroke-dasharray="6 4" stroke-linecap="round" stroke-width="1.4" x1="12" x2="118" y1="31" y2="31"></line><line stroke="#D4B072" stroke-dasharray="6 4" stroke-linecap="round" stroke-width="1.4" x1="202" x2="308" y1="31" y2="31"></line><line stroke="#7F96A7" stroke-dasharray="6 4" stroke-linecap="round" stroke-width="1.2" x1="12" x2="118" y1="39" y2="39"></line><line stroke="#7F96A7" stroke-dasharray="6 4" stroke-linecap="round" stroke-width="1.2" x1="202" x2="308" y1="39" y2="39"></line><text fill="#7F96A7" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">✧</text><text fill="#7F96A7" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">✧</text><text fill="#6DAEDB" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">♡</text>',
        'royal-baptism': '<line stroke="#E6C57A" stroke-linecap="round" stroke-width="1.5" x1="12" x2="118" y1="31" y2="31"></line><line stroke="#E6C57A" stroke-linecap="round" stroke-width="1.5" x1="202" x2="308" y1="31" y2="31"></line><line stroke="#85745F" stroke-linecap="round" stroke-width="1.2" x1="12" x2="118" y1="39" y2="39"></line><line stroke="#85745F" stroke-linecap="round" stroke-width="1.2" x1="202" x2="308" y1="39" y2="39"></line><circle cx="160" cy="35" fill="white" opacity="0.55" r="16" stroke="#496A81" stroke-width="1.4"></circle><text fill="#85745F" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">✧</text><text fill="#85745F" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">✧</text><text fill="#7B5E3B" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">✝</text>',
        'twin-harmony': '<line stroke="#C8A96A" stroke-dasharray="3 4" stroke-linecap="round" stroke-width="2.0" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#C8A96A" stroke-dasharray="3 4" stroke-linecap="round" stroke-width="2.0" x1="202" x2="308" y1="35" y2="35"></line><text fill="#6F877A" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">♡</text><text fill="#6F877A" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">♡</text><text fill="#5C7A6A" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">∞</text>',
        'triple-blessing': '<line stroke="#D8B06D" stroke-linecap="round" stroke-width="1.8" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#D8B06D" stroke-linecap="round" stroke-width="1.8" x1="202" x2="308" y1="35" y2="35"></line><circle cx="160" cy="35" fill="white" opacity="0.55" r="16" stroke="#607B90" stroke-width="1.4"></circle><text fill="#8B7481" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">✧</text><text fill="#8B7481" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">✧</text><text fill="#A58096" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">❀</text>',
        'heavenly-stars': '<line stroke="#F0D57B" stroke-linecap="round" stroke-width="2.2" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#F0D57B" stroke-linecap="round" stroke-width="2.2" x1="202" x2="308" y1="35" y2="35"></line><text fill="#8F96C4" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#8F96C4" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#8DA0F0" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">✦</text>',
        'little-miracles': '<line stroke="#D8B46A" stroke-dasharray="8 6" stroke-linecap="round" stroke-width="1.7" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#D8B46A" stroke-dasharray="8 6" stroke-linecap="round" stroke-width="1.7" x1="202" x2="308" y1="35" y2="35"></line><text fill="#7E948D" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">•</text><text fill="#7E948D" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">•</text><text fill="#6FB1A0" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">✝ ♡</text>',
        'angelic-trio': '<line stroke="#DEC175" stroke-linecap="round" stroke-width="1.9" x1="12" x2="118" y1="35" y2="35"></line><line stroke="#DEC175" stroke-linecap="round" stroke-width="1.9" x1="202" x2="308" y1="35" y2="35"></line><circle cx="160" cy="35" fill="white" opacity="0.55" r="16" stroke="#657F97" stroke-width="1.4"></circle><text fill="#8E81A4" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="126" y="38">♡</text><text fill="#8E81A4" font-size="12" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="194" y="38">♡</text><text fill="#9A84C7" font-size="22" style="font-family: Noto Sans Symbols 2, Noto Sans Symbols, Georgia, Times New Roman, serif;" text-anchor="middle" x="160" y="42">✧</text>'
    };

    function renderApprovedDivider(canvas, themeKey) {
        if (!canvas) return null;
        var divider = qs('.inv-divider', canvas) || ensureBefore('.inv-divider', '<div class="inv-divider" aria-hidden="true"></div>', qs('.inv-parents-wrapper', canvas), canvas);
        var key = normalizeThemeKey(themeKey);
        divider.innerHTML = '<svg aria-hidden="true" focusable="false" viewBox="0 0 320 70" preserveAspectRatio="xMidYMid meet">' + vectorizeDividerSvg(DIVIDER_SVG[key] || DIVIDER_SVG['little-princess']) + '</svg>';
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

        var eventsWrap = qs('.inv-events', canvas) || ensureBefore('.inv-events', '<div class="inv-events"><div class="events-row top"></div><div class="events-row bottom"></div></div>', null, canvas);
        var msg = qs('.inv-message', canvas) || ensureBefore('.inv-message', '<div class="inv-message"></div>', eventsWrap, canvas);
        var divider = renderApprovedDivider(canvas, inv.theme || 'little-princess');
        var parents = qs('.inv-parents-wrapper', canvas) || ensureBefore('.inv-parents-wrapper', '<div class="inv-parents-wrapper"><div class="section-title">ÎMPREUNĂ CU PĂRINȚII</div><div class="inv-parents inv-parents-grid"><div class="inv-parent-col inv-parent-mireasa"></div><div class="inv-parent-sep" aria-hidden="true">&</div><div class="inv-parent-col inv-parent-mire"></div></div></div>', msg, canvas);
        var nasi = qs('.inv-nasi', canvas) || ensureBefore('.inv-nasi', '<div class="inv-nasi"><div class="section-title">ȘI CU NAȘII</div><div class="nasi-row inv-parents-grid"><div class="inv-parent-col nasi-godmother"></div><div class="inv-parent-sep" aria-hidden="true">&</div><div class="inv-parent-col nasi-godfather"></div></div></div>', msg, canvas);
        if (names && divider && divider.parentNode === canvas) {
            canvas.insertBefore(divider, names.nextSibling);
        }

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
                    finalizeBaptismPdfLayout(canvas);
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
                    finalizeBaptismPreviewLayout(canvas);
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

    function getBaptismNameBand(canvas, lines) {
        var safeLines = Math.max(1, parseInt(lines, 10) || 1);
        var preview = canvas ? canvas.parentElement : null;
        var previewStyle = preview && window.getComputedStyle ? window.getComputedStyle(preview) : null;
        var previewFontPx = previewStyle ? parseFloat(previewStyle.fontSize || '0') : 0;
        var baseFontPx = previewFontPx > 0 ? previewFontPx : 17.25;
        var baseNameSize = parseCssNumber(canvas, '--ba-size-names', 2.14);
        var lineBox = baseFontPx * baseNameSize * 1.14;
        var growthStep = Math.max(12, Math.round(lineBox * 0.4));
        var height = 116 + (Math.max(0, safeLines - 1) * growthStep);

        if (safeLines <= 1) return { height: height, min: 1.92, grow: 1.42, step: 0.03 };
        if (safeLines === 2) return { height: height, min: 1.74, grow: 1.28, step: 0.03 };
        if (safeLines === 3) return { height: height, min: 1.54, grow: 1.16, step: 0.02 };
        return { height: height, min: 1.38, grow: 1.08, step: 0.02 };
    }

    function nameOverflows(names, bandHeight) {
        if (!names) return false;
        return names.scrollWidth > names.clientWidth + 1 || names.scrollHeight > bandHeight + 1;
    }

    function protectNameSection(canvas) {
        var names = qs('.inv-names', canvas);
        if (!names) return getBaptismNameBand(canvas, 1);
        var lines = countNameLines(canvas);
        var band = getBaptismNameBand(canvas, lines);
        names.style.minHeight = band.height + 'px';
        names.style.display = 'flex';
        names.style.alignItems = 'center';
        names.style.justifyContent = 'center';
        names.style.width = '100%';
        return band;
    }

    function fitBaptismNameText(canvas, shrinkOnly) {
        var names = qs('.inv-names', canvas);
        if (!names) return;
        var lines = countNameLines(canvas);
        var band = protectNameSection(canvas);
        var baseSize = parseCssNumber(canvas, '--ba-size-names', 2.14);
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

    function compactBaptismSecondary(canvas) {
        var msg = qs('.inv-message', canvas);
        var msgSize = msg ? parseCssNumber(canvas, '--ba-size-message', 1.06) : 0;
        while (hasOverflow(canvas) && msg && msgSize > 0.88) {
            msgSize = Math.round((msgSize - 0.02) * 100) / 100;
            msg.style.fontSize = msgSize + 'em';
            distributeVerticalSpace(canvas);
        }
        qsa('.inv-parents-wrapper, .inv-nasi, .inv-events .inv-event', canvas).forEach(function (node) {
            var size = parseFloat(node.style.fontSize || '1') || 1;
            while (hasOverflow(canvas) && size > 0.82) {
                size = Math.round((size - 0.02) * 100) / 100;
                node.style.fontSize = size + 'em';
                distributeVerticalSpace(canvas);
            }
        });
    }

    function finalizeBaptismPdfLayout(canvas) {
        if (!canvas) return;
        window.__TEINVIT_FINAL_PASS_DONE__ = false;
        canvas.style.fontSize = '1em';
        fitBaptismNameText(canvas, false);
        distributeVerticalSpace(canvas);
        compactBaptismSecondary(canvas);
        if (hasOverflow(canvas)) {
            if (engine() && typeof engine().autoFit === 'function') {
                engine().autoFit(canvas, { min: 0.58, step: 0.02, maxLoops: 60 });
                fitBaptismNameText(canvas, false);
                distributeVerticalSpace(canvas);
                compactBaptismSecondary(canvas);
            }
        }
        if (hasOverflow(canvas)) {
            fitBaptismNameText(canvas, true);
            distributeVerticalSpace(canvas);
        }
        protectNameSection(canvas);
        window.__TEINVIT_AUTOFIT_DONE__ = true;
        window.__TEINVIT_LAST_AUTOFIT_SIG__ = window.__TEINVIT_LAYOUT_SIG__ || '';
        window.__TEINVIT_FINAL_PASS_DONE__ = true;
    }

    function finalizeBaptismPreviewLayout(canvas) {
        if (!canvas) return;
        window.__TEINVIT_FINAL_PASS_DONE__ = false;
        canvas.style.fontSize = '1em';
        fitBaptismNameText(canvas, false);
        distributeVerticalSpace(canvas);
        compactBaptismSecondary(canvas);
        if (hasOverflow(canvas) && engine() && typeof engine().autoFit === 'function') {
            engine().autoFit(canvas, { min: 0.58, step: 0.02, maxLoops: 60 });
            fitBaptismNameText(canvas, false);
            distributeVerticalSpace(canvas);
            compactBaptismSecondary(canvas);
        }
        if (hasOverflow(canvas)) {
            fitBaptismNameText(canvas, true);
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
        fitBaptismNameText(canvas, false);
        distributeVerticalSpace(canvas);
        compactBaptismSecondary(canvas);
        if (hasOverflow(canvas)) {
            fitBaptismNameText(canvas, true);
        }
        if (engine() && typeof engine().autoFit === 'function') {
            if (hasOverflow(canvas)) engine().autoFit(canvas, { min: 0.58, step: 0.02, maxLoops: 60 });
            fitBaptismNameText(canvas, false);
            if (hasOverflow(canvas)) fitBaptismNameText(canvas, true);
        }
        if (window.__TEINVIT_PDF_MODE__ || window.TEINVIT_INVITATION_DATA) {
            window.__TEINVIT_AUTOFIT_DONE__ = true;
            window.__TEINVIT_LAST_AUTOFIT_SIG__ = currentSig;
        }
    }

    function distributeVerticalSpace(canvas) {
        if (!canvas) return;
        protectNameSection(canvas);
        if (engine() && typeof engine().distributeVerticalSpace === 'function') {
            engine().distributeVerticalSpace(canvas, ['.inv-names', '.inv-divider', '.inv-parents-wrapper', '.inv-nasi', '.inv-message', '.inv-events'], { reserve: 8, minGap: 4, maxGap: 26 });
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
        var url = window.teinvitBaptismPreviewConfig && window.teinvitBaptismPreviewConfig.previewBuildUrl;
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
            buildFromApi();
            scheduleFinalProductPass();
        }
        setupPreviewResizeObserver();
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
