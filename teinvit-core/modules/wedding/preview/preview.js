document.addEventListener('DOMContentLoaded', function () {

    var PARENT_BOOLEAN_FIELD_IDS = {
        '696445d6a9ce9': true,
        '696448f2ae763': true,
        '69644d9e814ef': true,
        '69645088f4b73': true,
        '696451a951467': true
    };

    function galleryConfig() {
        return window.TEINVIT_GALLERY_CONFIG || {};
    }

    function galleryCaptureRoot() {
        return qs('[data-teinvit-gallery-capture="1"]') || qs('.teinvit-preview');
    }

    function galleryIsVisibleNode(node) {
        if (!node || node.nodeType !== 1 || !window.getComputedStyle) return false;
        var style = window.getComputedStyle(node);
        return style.display !== 'none' && style.visibility !== 'hidden';
    }

    function galleryVisibleNodes(canvas) {
        return [canvas].concat(qsa('.inv-names, .inv-message, .inv-parents-wrapper, .inv-nasi, .inv-events, .inv-event', canvas))
            .filter(galleryIsVisibleNode);
    }

    function galleryOverflowCount(canvas) {
        return galleryVisibleNodes(canvas).filter(function (node) {
            return (node.clientHeight > 0 && node.scrollHeight > node.clientHeight + 1) ||
                (node.clientWidth > 0 && node.scrollWidth > node.clientWidth + 1);
        }).length;
    }

    function galleryClassName(node) {
        if (!node) return '';
        if (typeof node.className === 'string') return node.className;
        return node.getAttribute ? (node.getAttribute('class') || '') : '';
    }

    function gallerySimpleSelector(node) {
        if (!node || !node.tagName) return '';
        var className = galleryClassName(node);
        var classes = className.split(/\s+/).filter(function (cls) {
            return /^[A-Za-z0-9_-]+$/.test(cls);
        });
        return classes.length ? '.' + classes.join('.') : node.tagName.toLowerCase();
    }

    function gallerySiblingIndex(node) {
        if (!node || !node.parentElement || !node.tagName) return 0;
        var tagName = node.tagName;
        var siblings = Array.prototype.filter.call(node.parentElement.children, function (child) {
            return child.tagName === tagName;
        });
        return siblings.length > 1 ? siblings.indexOf(node) + 1 : 0;
    }

    function galleryDiagnosticSelector(node, canvas) {
        if (!node) return '';
        if (node === canvas) return '.teinvit-canvas';
        var parts = [];
        var current = node;
        while (current && current !== canvas && current.nodeType === 1) {
            var part = gallerySimpleSelector(current);
            var index = gallerySiblingIndex(current);
            if (index > 0) {
                part += ':nth-of-type(' + index + ')';
            }
            parts.unshift(part);
            current = current.parentElement;
        }
        return parts.length ? '.teinvit-canvas > ' + parts.join(' > ') : gallerySimpleSelector(node);
    }

    function galleryTextSample(node) {
        var text = String((node && (node.innerText || node.textContent)) || '')
            .replace(/\s+/g, ' ')
            .trim();
        return text.length > 140 ? text.slice(0, 137) + '...' : text;
    }

    function galleryDiagnosticNodes(canvas) {
        var selector = [
            '.inv-names',
            '.inv-divider',
            '.inv-message',
            '.inv-parents-wrapper',
            '.inv-parents',
            '.inv-parent-col',
            '.inv-nasi',
            '.nasi-row',
            '.inv-events',
            '.events-row',
            '.inv-event',
            '.inv-event strong',
            '.inv-event > div',
            '.inv-event > a',
            '.section-title'
        ].join(', ');
        return [canvas].concat(qsa(selector, canvas)).filter(galleryIsVisibleNode);
    }

    function galleryOverflowElements(canvas) {
        return galleryDiagnosticNodes(canvas).map(function (node) {
            var deltaHeight = node.scrollHeight - node.clientHeight;
            var deltaWidth = node.scrollWidth - node.clientWidth;
            var vertical = node.clientHeight > 0 && deltaHeight > 1;
            var horizontal = node.clientWidth > 0 && deltaWidth > 1;
            if (!vertical && !horizontal) return null;
            return {
                selector: galleryDiagnosticSelector(node, canvas),
                className: galleryClassName(node),
                axis: vertical && horizontal ? 'both' : (horizontal ? 'horizontal' : 'vertical'),
                scrollHeight: node.scrollHeight,
                clientHeight: node.clientHeight,
                scrollWidth: node.scrollWidth,
                clientWidth: node.clientWidth,
                deltaHeight: deltaHeight,
                deltaWidth: deltaWidth,
                textSample: galleryTextSample(node)
            };
        }).filter(Boolean);
    }

    function galleryBox(node) {
        if (!node || !node.getBoundingClientRect) return { width: 0, height: 0, top: 0, left: 0 };
        var rect = node.getBoundingClientRect();
        return {
            width: Math.round(rect.width),
            height: Math.round(rect.height),
            top: Math.round(rect.top * 100) / 100,
            left: Math.round(rect.left * 100) / 100
        };
    }

    function galleryNormalizeUrl(url) {
        return String(url || '').replace(/#.*$/, '');
    }

    function galleryWaitImages(root) {
        var images = qsa('img', root);
        if (!images.length) return Promise.resolve(false);
        return Promise.all(images.map(function (img) {
            var loaded = (img.complete && img.naturalWidth > 0)
                ? Promise.resolve(true)
                : new Promise(function (resolve) {
                    img.addEventListener('load', function () { resolve(true); }, { once: true });
                    img.addEventListener('error', function () { resolve(false); }, { once: true });
                });
            return loaded.then(function (ok) {
                if (!ok || !img.decode) return ok;
                return img.decode().then(function () { return true; }).catch(function () { return false; });
            });
        })).then(function (values) {
            return values.every(Boolean);
        });
    }

    function galleryWaitDomStable(root, waitMs) {
        return new Promise(function (resolve) {
            var mutated = false;
            var observer = null;
            if (window.MutationObserver && root) {
                observer = new MutationObserver(function () { mutated = true; });
                observer.observe(root, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['class', 'style'] });
            }
            setTimeout(function () {
                if (observer) observer.disconnect();
                resolve(!mutated);
            }, waitMs || 200);
        });
    }

    function galleryWaitLayoutStable(root, canvas) {
        return new Promise(function (resolve) {
            requestAnimationFrame(function () {
                var a = JSON.stringify({ root: galleryBox(root), canvas: galleryBox(canvas) });
                requestAnimationFrame(function () {
                    var b = JSON.stringify({ root: galleryBox(root), canvas: galleryBox(canvas) });
                    requestAnimationFrame(function () {
                        var c = JSON.stringify({ root: galleryBox(root), canvas: galleryBox(canvas) });
                        resolve(a === b && b === c);
                    });
                });
            });
        });
    }

    function galleryBackgroundState(root) {
        var cfg = galleryConfig();
        var img = qs('.teinvit-bg', root);
        if (!img) {
            return { loaded: false, decoded: false, url: '', match: false, natural: { width: 0, height: 0 } };
        }
        var actual = img.currentSrc || img.src || '';
        var expected = String(cfg.background_url || '');
        var loaded = !!(img.complete && img.naturalWidth > 0 && img.naturalHeight > 0);
        return {
            loaded: loaded,
            decoded: loaded,
            url: actual,
            match: expected !== '' && galleryNormalizeUrl(actual) === galleryNormalizeUrl(expected),
            natural: { width: img.naturalWidth || 0, height: img.naturalHeight || 0 }
        };
    }

    function galleryThemeClass(canvas) {
        if (!canvas) return '';
        var cfg = galleryConfig();
        var expected = String(cfg.theme_css_class || '');
        if (expected && canvas.classList && canvas.classList.contains(expected)) return expected;
        var classes = Array.prototype.slice.call(canvas.classList || []);
        return classes.filter(function (cls) { return cls.indexOf('theme-') === 0; })[0] || '';
    }

    function setGalleryState(canvas, extra) {
        var root = galleryCaptureRoot();
        var cfg = galleryConfig();
        var box = galleryBox(root);
        var bg = galleryBackgroundState(root);
        var actualTheme = galleryThemeClass(canvas);
        var expectedTheme = cfg.theme_css_class || '';
        var overflowCount = galleryOverflowCount(canvas);
        var state = {
            fonts_ready: !(document.fonts && document.fonts.status) || document.fonts.status === 'loaded',
            background_loaded: bg.loaded,
            images_decoded: !!(extra && extra.images_decoded),
            theme_applied: expectedTheme !== '' && actualTheme === expectedTheme,
            autofit_done: window.__TEINVIT_AUTOFIT_DONE__ === true,
            final_pass_done: window.__TEINVIT_GALLERY_FINAL_PASS_DONE__ === true && overflowCount === 0,
            layout_stable: !!(extra && extra.layout_stable),
            dom_stable: !!(extra && extra.dom_stable),
            overflow_count: overflowCount,
            capture_box: { width: box.width, height: box.height },
            expected_theme_key: cfg.theme_key_internal || '',
            actual_theme_class: actualTheme,
            background_url: bg.url,
            background_match: bg.match,
            background_natural: bg.natural
        };
        if (window.__TEINVIT_GALLERY_MODE__ && overflowCount > 0) {
            state.overflow_elements = galleryOverflowElements(canvas);
        }
        window.__TEINVIT_GALLERY_STATE__ = state;
        return state;
    }

    function maybeFinalizeGalleryReady(canvas) {
        if (!window.__TEINVIT_GALLERY_MODE__ || !canvas) return;
        var root = galleryCaptureRoot();
        if (!root) return;
        window.__TEINVIT_GALLERY_READY__ = false;
        var fontsReady = document.fonts && document.fonts.ready ? document.fonts.ready.catch(function () {}) : Promise.resolve();
        fontsReady
            .then(function () { return galleryWaitImages(root); })
            .then(function (imagesDecoded) {
                return galleryWaitLayoutStable(root, canvas).then(function (layoutStable) {
                    return { imagesDecoded: imagesDecoded, layoutStable: layoutStable };
                });
            })
            .then(function (ready) {
                return galleryWaitDomStable(root, 200).then(function (domStable) {
                    var state = setGalleryState(canvas, { images_decoded: ready.imagesDecoded, layout_stable: ready.layoutStable, dom_stable: domStable });
                    if (
                        state.fonts_ready &&
                        state.background_loaded &&
                        state.images_decoded &&
                        state.theme_applied &&
                        state.autofit_done &&
                        state.final_pass_done &&
                        state.layout_stable &&
                        state.dom_stable &&
                        state.overflow_count === 0 &&
                        state.capture_box.width === 559 &&
                        state.capture_box.height === 794 &&
                        state.background_match
                    ) {
                        window.__TEINVIT_GALLERY_READY__ = true;
                    }
                });
            });
    }

    function finalizeWeddingGalleryLayout(canvas) {
        return new Promise(function (resolve) {
            galleryReadyCheckTimer = setTimeout(function runAttempt() {
                window.__TEINVIT_AUTOFIT_DONE__ = false;
                applyAutoFit(canvas);
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        var overflow = galleryOverflowCount(canvas);
                        if (overflow > 0 && galleryReadyCheckAttempts < 5) {
                            galleryReadyCheckAttempts++;
                            galleryReadyCheckTimer = setTimeout(runAttempt, 40);
                            return;
                        }
                        window.__TEINVIT_AUTOFIT_DONE__ = true;
                        window.__TEINVIT_GALLERY_FINAL_PASS_DONE__ = overflow === 0;
                        resolve(overflow);
                    });
                });
            }, 0);
        });
    }

    function scheduleGalleryReadyCheck(canvas) {
        if (!window.__TEINVIT_GALLERY_MODE__ || !canvas) return;
        if (galleryReadyCheckTimer) {
            clearTimeout(galleryReadyCheckTimer);
        }
        galleryReadyCheckAttempts = 0;
        window.__TEINVIT_GALLERY_READY__ = false;
        window.__TEINVIT_GALLERY_FINAL_PASS_DONE__ = false;

        var fontsReady = document.fonts && document.fonts.ready ? document.fonts.ready.catch(function () {}) : Promise.resolve();
        fontsReady.then(function () {
            finalizeWeddingGalleryLayout(canvas).then(function () {
                maybeFinalizeGalleryReady(canvas);
            });
        });
    }

    /* ==================================================
       HELPERS
    ================================================== */

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call(
            (root || document).querySelectorAll(sel)
        );
    }

    function join(a, b, sep) {
        if (a && b) return a + sep + b;
        return a || b || '';
    }

    function escapeHtmlAttr(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function normalizeWazeDeepLink(rawValue, locationText) {
        var raw = (rawValue || '').toString().trim();
        var location = (locationText || '').toString().trim();

        function buildWazeUrl(params) {
            var url = new URL('https://waze.com/ul');
            Object.keys(params || {}).forEach(function (key) {
                var val = (params[key] || '').toString().trim();
                if (val) url.searchParams.set(key, val);
            });

            if (!url.searchParams.get('ll') && !url.searchParams.get('q') && location) {
                url.searchParams.set('q', location);
            }
            if ((url.searchParams.get('ll') || url.searchParams.get('q')) && !url.searchParams.get('navigate')) {
                url.searchParams.set('navigate', 'yes');
            }
            if (!url.searchParams.get('utm_source')) {
                url.searchParams.set('utm_source', 'teinvit');
            }

            return url.toString();
        }

        if (raw) {
            if (/^waze:\/\//i.test(raw)) {
                var custom = raw.replace(/^waze:\/\//i, '');
                var customQuery = '';
                var qIndex = custom.indexOf('?');
                if (qIndex >= 0) {
                    customQuery = custom.slice(qIndex + 1);
                } else if (custom.indexOf('&') >= 0) {
                    customQuery = custom;
                }

                var customParams = new URLSearchParams(customQuery);
                return buildWazeUrl({
                    ll: customParams.get('ll') || '',
                    q: customParams.get('q') || '',
                    navigate: customParams.get('navigate') || customParams.get('n') || 'yes'
                });
            }

            var candidate = raw;
            if (!/^https?:\/\//i.test(candidate)) {
                candidate = 'https://' + candidate.replace(/^\/+/, '');
            }

            try {
                var parsed = new URL(candidate);
                if (/waze\.com$/i.test(parsed.hostname) || /\.waze\.com$/i.test(parsed.hostname)) {
                    var params = new URLSearchParams(parsed.search || '');
                    var pathname = parsed.pathname || '';

                    if (!/\/ul(\/|$)/i.test(pathname)) {
                        pathname = '/ul';
                    }

                    var normalized = new URL('https://waze.com' + pathname);
                    params.forEach(function (val, key) {
                        normalized.searchParams.set(key, val);
                    });

                    if (!normalized.searchParams.get('ll') && !normalized.searchParams.get('q') && location) {
                        normalized.searchParams.set('q', location);
                    }
                    if ((normalized.searchParams.get('ll') || normalized.searchParams.get('q')) && !normalized.searchParams.get('navigate')) {
                        normalized.searchParams.set('navigate', 'yes');
                    }
                    if (!normalized.searchParams.get('utm_source')) {
                        normalized.searchParams.set('utm_source', 'teinvit');
                    }

                    return normalized.toString();
                }
            } catch (e) {
                // fallback to location-based deep link below
            }
        }

        if (location) {
            return buildWazeUrl({ q: location, navigate: 'yes' });
        }

        return '';
    }


    function normalizeDisplayDate(dateValue) {
        var raw = (dateValue || '').trim();
        if (!raw) return '';

        var mdy = raw.match(/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])-(\d{4})$/);
        if (mdy) {
            return mdy[2] + '-' + mdy[1] + '-' + mdy[3];
        }

        var ymd = raw.match(/^(\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/);
        if (ymd) {
            return ymd[3] + '-' + ymd[2] + '-' + ymd[1];
        }

        var dmy = raw.match(/^(0[1-9]|[12]\d|3[01])-(0[1-9]|1[0-2])-(\d{4})$/);
        if (dmy) {
            return raw;
        }

        return raw;
    }

    function formatDateTimeLine(dateValue, timeValue) {
        var date = normalizeDisplayDate(dateValue);
        var time = (timeValue || '').trim();

        if (!date) return '';
        if (/^([01]\d|2[0-3]):[0-5]\d$/.test(time)) {
            return date + ' ora ' + time;
        }

        return date;
    }

    /* ==================================================
       B1 – FORMAT NUME MIRI
       max 22 caractere / rând
    ================================================== */

function formatNames(text) {
    if (!text) return '';

    text = text.trim();

    function noWrap(str) {
        return str.replace(/ /g, '\u00A0');
    }

    // split FINAL, atomic, nu incremental
    function splitFinal(str, limit) {
        if (str.length <= limit) {
            return { line1: str, line2: null };
        }

        var words = str.split(' ');
        var line1 = '';
        var i = 0;

        for (; i < words.length; i++) {
            var test = line1 ? line1 + ' ' + words[i] : words[i];
            if (test.length <= limit) {
                line1 = test;
            } else {
                break;
            }
        }

        return {
            line1: line1,
            line2: words.slice(i).join(' ')
        };
    }

    var parts = text.split(' & ');

    /* ===============================
       CAZ 1: DOAR MIREASA
       STABIL, FĂRĂ STĂRI INTERMEDIARE
    =============================== */
    if (parts.length === 1) {
        var brideOnly = parts[0].trim();
        var split = splitFinal(brideOnly, 22);

        if (!split.line2) {
            return split.line1;
        }

        return split.line1 + '\n' + noWrap(split.line2);
    }

    /* ===============================
       CAZ 2: MIREASA + MIRE
       MAX 4 RÂNDURI
    =============================== */
    var bride = parts[0].trim();
    var groom = parts[1].trim();

    var splitBride = splitFinal(bride, 22);

    var lines = [];
    lines.push(splitBride.line1);

    if (splitBride.line2) {
        lines.push(noWrap(splitBride.line2));
    }

    lines.push('&');
    lines.push(groom);

    return lines.join('\n');
}



    /* ==================================================
       B2 – LIMITARE MESAJ (255 caractere)
    ================================================== */

    function limitMessage(text) {
        if (!text) return '';
        return text.substring(0, 255);
    }

    function resolveCanonicalThemeClass(themeValue) {
        var raw = (themeValue || '').toString().trim().toLowerCase();
        if (!raw) return 'theme-editorial-luxury';

        if (
            raw.indexOf('theme-editorial-luxury') !== -1 ||
            raw.indexOf('editorial luxury') !== -1 ||
            raw.indexOf('editorial') !== -1
        ) {
            return 'theme-editorial-luxury';
        }

        if (
            raw.indexOf('theme-romantic-floral') !== -1 ||
            raw.indexOf('romantic floral') !== -1 ||
            raw.indexOf('romantic') !== -1
        ) {
            return 'theme-romantic-floral';
        }

        if (
            raw.indexOf('theme-modern-minimal') !== -1 ||
            raw.indexOf('modern minimal') !== -1 ||
            raw.indexOf('modern') !== -1
        ) {
            return 'theme-modern-minimal';
        }

        if (
            raw.indexOf('theme-classic-elegant') !== -1 ||
            raw.indexOf('classic elegant') !== -1 ||
            raw.indexOf('classic') !== -1
        ) {
            return 'theme-classic-elegant';
        }

        return 'theme-editorial-luxury';
    }

    function applyCanonicalThemeClass(canvas, themeValue) {
        if (!canvas) return;

        var current = Array.prototype.slice.call(canvas.classList);
        current.forEach(function (cls) {
            if (cls.indexOf('theme-') === 0) {
                canvas.classList.remove(cls);
            }
        });

        var canonicalClass = resolveCanonicalThemeClass(themeValue);
        canvas.classList.add(canonicalClass);
    }

    /* ==================================================
       THEME LOGIC – PAGINA PRODUS (WAPF)
    ================================================== */

    function applyThemeFromWAPF() {

        var select = qs('[name="wapf[field_6967752ab511b]"]');
        if (!select) return;

        var selectedOption = select.options[select.selectedIndex];
        if (!selectedOption) return;

        var canvas = qs('.teinvit-canvas');
        if (!canvas) return;

        var label = selectedOption.text || '';
        var value = select.value || '';

        applyCanonicalThemeClass(canvas, label || value);
    }

    /* ==================================================
       THEME LOGIC – PAGINA /i/{token}
    ================================================== */

    function applyThemeFromInvitationData() {

        if (
            !window.TEINVIT_INVITATION_DATA ||
            !window.TEINVIT_INVITATION_DATA.theme
        ) {
            return;
        }

        var canvas = qs('.teinvit-canvas');
        if (!canvas) return;

        applyCanonicalThemeClass(canvas, window.TEINVIT_INVITATION_DATA.theme);
    }

    function getLineHeightPx(el) {
        if (!el) return 0;
        var cs = window.getComputedStyle(el);
        var lh = parseFloat(cs.lineHeight);
        if (!isNaN(lh)) return lh;

        var fs = parseFloat(cs.fontSize);
        if (isNaN(fs)) return 0;
        return fs * 1.2;
    }

    function isMultiLine(el) {
        if (!el || !el.textContent) return false;

        var lineHeight = getLineHeightPx(el);
        if (!lineHeight) return false;

        var h = el.getBoundingClientRect().height;
        return h > (lineHeight * 1.3);
    }

    function splitParentPair(raw) {
        var value = (raw || '').replace(/\s+/g, ' ').trim();
        if (!value) return null;

        var idx = value.indexOf(' & ');
        var sepLen = 3;

        if (idx < 0) {
            idx = value.indexOf('&');
            sepLen = 1;
        }

        if (idx < 0) return null;

        var mother = value.slice(0, idx).trim();
        var father = value.slice(idx + sepLen).trim();
        if (!mother || !father) return null;

        return {
            mother: mother,
            father: father
        };
    }

    function splitParentBalanced(raw) {
        var value = (raw || '').replace(/\s+/g, ' ').trim();
        if (!value) return null;

        var words = value.split(' ').filter(Boolean);
        if (words.length < 2) return null;

        var mid = Math.max(1, Math.floor(words.length / 2));
        var left = words.slice(0, mid).join(' ').trim();
        var right = words.slice(mid).join(' ').trim();
        if (!left || !right) return null;

        return {
            mother: left,
            father: right
        };
    }

    function restoreParentRaw(el) {
        if (!el) return;
        var raw = el.getAttribute('data-raw') || '';
        el.textContent = raw;
        el.removeAttribute('data-parent-split');
    }

    function applyForcedParentSplit(el, pair) {
        if (!el || !pair) return;

        el.textContent = '';

        var mother = document.createElement('span');
        mother.className = 'parent-mother';
        mother.textContent = pair.mother + ' ';

        var amp = document.createElement('span');
        amp.className = 'parent-amp';
        amp.textContent = '&';

        var br = document.createElement('br');

        var father = document.createElement('span');
        father.className = 'parent-father';
        father.textContent = pair.father;

        el.appendChild(mother);
        el.appendChild(amp);
        el.appendChild(br);
        el.appendChild(father);
        el.setAttribute('data-parent-split', '1');
    }

    function normalizeParentsWrap(wrapper) {
        if (!wrapper) return;

        var left = qs('.inv-parent-col.inv-parent-mireasa', wrapper);
        var right = qs('.inv-parent-col.inv-parent-mire', wrapper);
        var grid = qs('.inv-parents-grid', wrapper) || qs('.inv-parents', wrapper);

        if (!left || !right || !grid) return;

        var leftRaw = (left.getAttribute('data-raw') || left.textContent || '').replace(/\s+/g, ' ').trim();
        var rightRaw = (right.getAttribute('data-raw') || right.textContent || '').replace(/\s+/g, ' ').trim();

        left.setAttribute('data-raw', leftRaw);
        right.setAttribute('data-raw', rightRaw);

        restoreParentRaw(left);
        restoreParentRaw(right);

        var leftPair = splitParentPair(leftRaw) || splitParentBalanced(leftRaw);
        var rightPair = splitParentPair(rightRaw) || splitParentBalanced(rightRaw);

        var needsSplit = !!(leftPair || rightPair);
        if (!needsSplit) {
            needsSplit = isMultiLine(left) || isMultiLine(right);
        }

        if (!needsSplit) {
            grid.classList.remove('parents-force-split');
            return;
        }

        if (leftPair) {
            applyForcedParentSplit(left, leftPair);
        }

        if (rightPair) {
            applyForcedParentSplit(right, rightPair);
        }

        grid.classList.add('parents-force-split');
    }

    /* ==================================================
       CANONICAL PREVIEW PAYLOAD (server-side builder)
    ================================================== */

    var canonicalPreviewData = null;
    var canonicalPreviewTimer = null;
    var canonicalPreviewInFlight = false;
    var canonicalPreviewQueued = false;
    var canonicalPreviewSeq = 0;
    var canonicalPreviewLastAppliedSeq = 0;
    var pdfReadyCheckTimer = null;
    var pdfReadyCheckAttempts = 0;
    var galleryReadyCheckTimer = null;
    var galleryReadyCheckAttempts = 0;

    function invitationLayoutSignature(data) {
        if (!data || typeof data !== 'object') return '';
        return JSON.stringify({
            theme: data.theme || '',
            names: data.names || '',
            message: data.message || '',
            show_parents: !!data.show_parents,
            parents: data.parents || {},
            show_nasi: !!data.show_nasi,
            nasi: data.nasi || '',
            events: Array.isArray(data.events) ? data.events : []
        });
    }

    function normalizeInvitationForRender(data) {
        if (!data || typeof data !== 'object') return null;

        var normalized = {
            theme: data.theme || 'editorial',
            names: formatNames(data.names || ''),
            message: data.message || '',
            show_parents: !!data.show_parents,
            parents: data.parents || {},
            show_nasi: !!data.show_nasi,
            nasi: data.nasi || '',
            events: Array.isArray(data.events) ? data.events : []
        };

        return normalized;
    }

    function collectWapfMapFromForm() {
        var form = qs('#teinvit-save-form') || qs('form.cart') || qs('form');
        if (!form) return {};

        var fd = new FormData(form);
        var out = {};

        fd.forEach(function(value, key){
            var m = key.match(/^wapf\[field_([^\]]+)\]/);
            if(!m) return;
            var id = String(m[1] || '').trim();
            if(!id) return;

            if (Object.prototype.hasOwnProperty.call(PARENT_BOOLEAN_FIELD_IDS, id)) {
                return;
            }

            if (!Object.prototype.hasOwnProperty.call(out, id)) {
                out[id] = [];
            }
            out[id].push(String(value || '').trim());
        });

        Object.keys(PARENT_BOOLEAN_FIELD_IDS).forEach(function(id){
            var inputs = qsa('[name="wapf[field_' + id + '][]"]', form);
            if (!inputs.length) return;

            var selected = inputs
                .filter(function(input){
                    return input && input.type === 'checkbox' && input.checked;
                })
                .map(function(input){
                    return String(input.value || '').trim();
                })
                .filter(function(v){
                    return v !== '';
                });

            out[id] = selected.join(', ');
        });

        Object.keys(out).forEach(function(id){
            if (Array.isArray(out[id])) {
                out[id] = out[id].filter(function(v){ return v !== ''; }).join(', ');
            } else {
                out[id] = String(out[id] || '').trim();
            }
        });

        return out;
    }

    function requestCanonicalPreviewBuild() {
        if (canonicalPreviewInFlight) {
            canonicalPreviewQueued = true;
            return;
        }
        if (!hasWAPF()) return;
        if (!window.teinvitPreviewConfig || !window.teinvitPreviewConfig.previewBuildUrl) return;

        var map = collectWapfMapFromForm();
        if (!Object.keys(map).length) return;

        var productInput = qs('[name="add-to-cart"]') || qs('input[name="product_id"]') || qs('[name="variation_id"]');
        var productId = productInput ? parseInt(productInput.value || '0', 10) : 0;
        if (!(productId > 0) && window.teinvitPreviewConfig && window.teinvitPreviewConfig.productId) {
            productId = parseInt(window.teinvitPreviewConfig.productId || '0', 10);
        }

        var requestSeq = ++canonicalPreviewSeq;
        canonicalPreviewInFlight = true;
        fetch(window.teinvitPreviewConfig.previewBuildUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id: productId > 0 ? productId : 0,
                token: window.teinvitPreviewConfig && window.teinvitPreviewConfig.token ? window.teinvitPreviewConfig.token : '',
                wapf_map: map
            })
        }).then(function(resp){
            return resp.json();
        }).then(function(data){
            if (requestSeq < canonicalPreviewSeq) {
                return;
            }
            if (data && data.ok && data.invitation) {
                canonicalPreviewData = data.invitation;
                window.TEINVIT_INVITATION_DATA = data.invitation;
                window.__TEINVIT_AUTOFIT_DONE__ = false;
                canonicalPreviewLastAppliedSeq = requestSeq;
                render();
            }
        }).catch(function(){
            // no-op
        }).finally(function(){
            canonicalPreviewInFlight = false;
            if (canonicalPreviewQueued) {
                canonicalPreviewQueued = false;
                scheduleCanonicalPreviewBuild();
            }
        });
    }

    function scheduleCanonicalPreviewBuild() {
        if (canonicalPreviewTimer) {
            clearTimeout(canonicalPreviewTimer);
        }
        canonicalPreviewTimer = setTimeout(requestCanonicalPreviewBuild, 180);
    }

    /* ==================================================
       DATA SOURCES
    ================================================== */

    function hasWAPF() {
        return qsa('[name^="wapf[field_"]').length > 0;
    }

    function valWAPF(id) {
        var el =
            qs('[name="wapf[field_' + id + ']"]') ||
            qs('[name="wapf[field_' + id + '][]"]');

        return el ? el.value : '';
    }

    function checkedWAPF(id) {
        return qsa('[name="wapf[field_' + id + '][]"]:checked').length > 0;
    }

    function buildEventsFromWAPF() {
        var events = [];

        if (checkedWAPF('69644d9e814ef')) {
            events.push({
                title: 'Cununie civilă',
                loc: valWAPF('69644f2b40023'),
                date: formatDateTimeLine(
                    valWAPF('69644f85d865e'),
                    valWAPF('8dec5e7')
                ),
                waze: valWAPF('69644fd5c832b')
            });
        }

        if (checkedWAPF('69645088f4b73')) {
            events.push({
                title: 'Ceremonie religioasă',
                loc: valWAPF('696450ee17f9e'),
                date: formatDateTimeLine(
                    valWAPF('696450ffe7db4'),
                    valWAPF('32f74cc')
                ),
                waze: valWAPF('69645104b39f4')
            });
        }

        if (checkedWAPF('696451a951467')) {
            events.push({
                title: 'Petrecerea',
                loc: valWAPF('696451d204a8a'),
                date: formatDateTimeLine(
                    valWAPF('696452023cdcd'),
                    valWAPF('a4a0fca')
                ),
                waze: valWAPF('696452478586d')
            });
        }

        return events;
    }

    function getInvitationData() {

            if (hasWAPF()) {
            if (!canonicalPreviewData) {
                if (window.teinvitPreviewConfig && window.teinvitPreviewConfig.previewBuildUrl) {
                    scheduleCanonicalPreviewBuild();
                    if (window.TEINVIT_INVITATION_DATA) {
                        return normalizeInvitationForRender(window.TEINVIT_INVITATION_DATA);
                    }
                    return null;
                }

                if (window.TEINVIT_INVITATION_DATA) {
                    return normalizeInvitationForRender(window.TEINVIT_INVITATION_DATA);
                }

                return null;
            }
            return normalizeInvitationForRender(canonicalPreviewData);
        }

                if (window.TEINVIT_INVITATION_DATA) {
                return normalizeInvitationForRender(window.TEINVIT_INVITATION_DATA);
        }

                return null;

    }
/* ==================================================
   SYNC BASE FONT – PREVIEW ⇄ PDF
   Asigură bază tipografică identică
================================================== */
function syncBaseFontSize(canvas) {

    // doar pentru /pdf/{token}
    if (!window.__TEINVIT_PDF_MODE__) return;

 
    // 🔑 IMPORTANT:
    // NU modificăm nimic în PDF
}

    function schedulePdfReadyCheck(canvas) {
        if (!window.__TEINVIT_PDF_MODE__ || !canvas) return;

        if (pdfReadyCheckTimer) {
            clearTimeout(pdfReadyCheckTimer);
        }

        pdfReadyCheckTimer = setTimeout(function () {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    var overflow = hasCanvasOverflow(canvas);
                    if (overflow && pdfReadyCheckAttempts < 4) {
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

    /* ==================================================
       RENDER
    ================================================== */

        function render() {

        var data = getInvitationData();
        if (!data) return;

        window.__TEINVIT_LAYOUT_SIG__ = invitationLayoutSignature(data);

        if (data.message) data.message = limitMessage(data.message);

        var canvas = qs('.teinvit-canvas');
        if (!canvas) return;

        applyThemeFromWAPF();
        applyThemeFromInvitationData();

        var namesEl = qs('.inv-names', canvas);
        if (namesEl) {
        namesEl.textContent = data.names;
        namesEl.style.whiteSpace = 'pre-line';
        namesEl.style.display = data.names ? '' : 'none';
        }



        var pw = qs('.inv-parents-wrapper', canvas);
        if (pw && data.show_parents) {
            pw.style.display = '';
            var cols = qsa('.inv-parents div', pw);
            if (cols[0]) {
                cols[0].textContent = data.parents.mireasa;
                cols[0].setAttribute('data-raw', (data.parents.mireasa || '').replace(/\s+/g, ' ').trim());
                cols[0].removeAttribute('data-parent-split');
            }
            if (cols[1]) {
                cols[1].textContent = data.parents.mire;
                cols[1].setAttribute('data-raw', (data.parents.mire || '').replace(/\s+/g, ' ').trim());
                cols[1].removeAttribute('data-parent-split');
            }

            normalizeParentsWrap(pw);
        } else if (pw) {
            pw.style.display = 'none';
            var grid = qs('.inv-parents-grid', pw) || qs('.inv-parents', pw);
            if (grid) grid.classList.remove('parents-force-split');
        }

        var nasiBox = qs('.inv-nasi', canvas);
        if (nasiBox && data.show_nasi) {
            nasiBox.style.display = '';
            qs('.nasi-row', nasiBox).textContent = data.nasi;
        } else if (nasiBox) {
            nasiBox.style.display = 'none';
        }

        var msgEl = qs('.inv-message', canvas);
        if (msgEl) {
            msgEl.textContent = data.message;
            msgEl.style.display = data.message ? '' : 'none';
        }

        var evBox = qs('.inv-events', canvas);
        if (!evBox) return;

        var top = qs('.events-row.top', evBox);
        var bottom = qs('.events-row.bottom', evBox);

        top.innerHTML = '';
        bottom.innerHTML = '';

        data.events.forEach(function (e, i) {
            var wazeLink = normalizeWazeDeepLink(e.waze, e.loc);
            var html =
                '<div class="inv-event">' +
                '<strong>' + e.title + '</strong>' +
                '<div>' + e.loc + '</div>' +
                '<div>' + e.date + '</div>' +
                (wazeLink ? '<a href="' + escapeHtmlAttr(wazeLink) + '">Deschide în Waze</a>' : '') +
                '</div>';

            (i < 2 ? top : bottom).insertAdjacentHTML('beforeend', html);
        });

        evBox.style.display = data.events.length ? '' : 'none';

        syncBaseFontSize(canvas);
applyAutoFit(canvas);

        // ================================
        // 🔑 PDF HANDSHAKE – RENDER FINAL
        // ================================
        if (window.__TEINVIT_PDF_MODE__) {
            schedulePdfReadyCheck(canvas);
        } else if (window.__TEINVIT_GALLERY_MODE__) {
            scheduleGalleryReadyCheck(canvas);
        } else {
            window.TEINVIT_RENDER_READY = true;
        }
    }

/* ==================================================
       AUTO RESIZE GLOBAL
    ================================================== */

    function hasCanvasOverflow(canvas) {
        if (!canvas) return false;
        return (
            canvas.scrollHeight > canvas.clientHeight + 1 ||
            canvas.scrollWidth > canvas.clientWidth + 1
        );
    }

    function applyAutoFit(canvas) {

    // Dacă suntem în i/{token} sau pdf/{token},
    // rulăm auto-fit o singură dată
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

        var baseFont = 1;
        var minFont = 0.52;
        var safety = 0;
        var names   = qs('.inv-names', canvas);
        var parents = qs('.inv-parents-wrapper', canvas);
        var nasi    = qs('.inv-nasi', canvas);
        var message = qs('.inv-message', canvas);
        var events  = qs('.inv-events', canvas);
        var rows    = qsa('.events-row', canvas);
        canvas.style.fontSize = baseFont + 'em';
        while (hasCanvasOverflow(canvas) && safety < 60) {
            baseFont -= 0.02;
            if (baseFont < minFont) break;

            canvas.style.fontSize = baseFont + 'em';

            if (names) names.style.marginBottom = '1.15em';
            if (parents) parents.style.marginTop = '0.7em';
            if (nasi) nasi.style.marginTop = '0.7em';
            if (message) message.style.marginTop = '0.7em';
            if (events) events.style.marginTop = '0.7em';

            rows.forEach(function (row, i) {
                if (i > 0) row.style.marginTop = '0.6em';
            });

            safety++;
        }

    // Marcăm auto-fit ca fiind finalizat
    if (window.__TEINVIT_PDF_MODE__ || window.TEINVIT_INVITATION_DATA) {
        window.__TEINVIT_AUTOFIT_DONE__ = true;
        window.__TEINVIT_LAST_AUTOFIT_SIG__ = currentSig;
    }


    }

    /* ==================================================
       PRODUCT PREVIEW – FINAL PASS (WAPF STABLE STATE)
    ================================================== */

    var FINAL_PASS_DEBOUNCE_MS = 320;
    var finalPassTimer = null;
    var lastStableSignature = '';
    var finalizedSignature = '';

    function isProductWAPFContext() {
        return hasWAPF() && !window.TEINVIT_INVITATION_DATA && !window.__TEINVIT_PDF_MODE__;
    }

    function hasRequiredWAPFFields() {
        return !!(
            qs('[name="wapf[field_6967752ab511b]"]') &&
            qs('[name="wapf[field_6963a95e66425]"]') &&
            qs('[name="wapf[field_6963aa37412e4]"]') &&
            qs('[name="wapf[field_6963aa782092d]"]')
        );
    }

    function getProductSnapshotSignature() {
        if (!isProductWAPFContext() || !hasRequiredWAPFFields()) {
            return '';
        }

        var data = getInvitationData();
        if (!data) return '';

        var themeSelect = qs('[name="wapf[field_6967752ab511b]"]');
        var selectedLabel = '';
        if (themeSelect && themeSelect.options && themeSelect.selectedIndex >= 0) {
            selectedLabel = (themeSelect.options[themeSelect.selectedIndex].text || '').trim().toLowerCase();
        }

        return JSON.stringify({
            themeLabel: selectedLabel,
            names: data.names || '',
            message: data.message || '',
            show_parents: !!data.show_parents,
            show_nasi: !!data.show_nasi,
            parents: data.parents || {},
            nasi: data.nasi || '',
            events: data.events || []
        });
    }

    function scheduleFinalProductPass() {
        if (!isProductWAPFContext()) return;

        if (finalPassTimer) {
            clearTimeout(finalPassTimer);
        }

        finalPassTimer = setTimeout(function () {
            var signature = getProductSnapshotSignature();
            if (!signature || signature === finalizedSignature) {
                return;
            }

            // confirmăm stabilitatea pe 2 ferestre consecutive de debounce
            if (signature !== lastStableSignature) {
                lastStableSignature = signature;
                scheduleFinalProductPass();
                return;
            }

            finalizedSignature = signature;
            render();

            // Final normalize: doar în context product+WAPF,
            // încercăm 1em; dacă apare overflow real, revenim la auto-fit.
            var canvas = qs('.teinvit-canvas');
            if (!canvas || !isProductWAPFContext()) {
                return;
            }

            canvas.style.fontSize = '1em';
            window.requestAnimationFrame(function () {
                if (!isProductWAPFContext()) return;
                if (hasCanvasOverflow(canvas)) {
                    applyAutoFit(canvas);
                }
            });
        }, FINAL_PASS_DEBOUNCE_MS);
    }

    function onPreviewDataChange() {
        if (hasWAPF()) {
            scheduleCanonicalPreviewBuild();
        }
        render();
        scheduleFinalProductPass();
    }

    document.addEventListener('teinvit:variant-applied', function(){
        canonicalPreviewData = null;
        canonicalPreviewSeq++;
        finalizedSignature = '';
        lastStableSignature = '';
        canonicalPreviewLastAppliedSeq = 0;
        window.__TEINVIT_AUTOFIT_DONE__ = false;
        window.__TEINVIT_LAST_AUTOFIT_SIG__ = '';
        pdfReadyCheckAttempts = 0;
        scheduleCanonicalPreviewBuild();
    });

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function(){
            window.__TEINVIT_AUTOFIT_DONE__ = false;
            window.__TEINVIT_LAST_AUTOFIT_SIG__ = '';
            render();
        }).catch(function(){});
    }

    if (hasWAPF()) {
        scheduleCanonicalPreviewBuild();
    }
    render();
    scheduleFinalProductPass();
    document.addEventListener('input', onPreviewDataChange);
    document.addEventListener('change', onPreviewDataChange);

    var previewRoot = qs('.teinvit-preview');
    if (previewRoot && typeof ResizeObserver !== 'undefined') {
        var previewResizeObserver = new ResizeObserver(function () {
            window.__TEINVIT_AUTOFIT_DONE__ = false;
            render();
            scheduleFinalProductPass();
        });
        previewResizeObserver.observe(previewRoot);
    }

});

/* ==================================================
   SAFETY NET – MESSAGE COUNTER INIT
   (WAPF loads async)
================================================== */
(function forceMessageCounterInit() {

    var MESSAGE_FIELD_ID = '6963aa782092d';
    var MAX_CHARS = 255;

    function findTextarea() {
        return document.querySelector(
            '[name="wapf[field_' + MESSAGE_FIELD_ID + ']"]'
        );
    }

    function createCounter(textarea) {
        if (textarea._teinvitCounterAttached) return;
        textarea._teinvitCounterAttached = true;

        var counter = document.createElement('div');
        counter.className = 'teinvit-message-counter';
        counter.style.marginTop = '6px';
        counter.style.fontSize = '0.75rem';
        counter.style.textAlign = 'right';
        counter.style.fontFamily = 'inherit';

        textarea.parentNode.appendChild(counter);

        function updateCounter() {
            var len = textarea.value.length;

            if (len > MAX_CHARS) {
                textarea.value = textarea.value.substring(0, MAX_CHARS);
                len = MAX_CHARS;
            }

            counter.textContent = len + ' / ' + MAX_CHARS + ' caractere';
        }

        textarea.addEventListener('input', updateCounter);
        updateCounter();
    }

    var tries = 0;
    var interval = setInterval(function () {
        var textarea = findTextarea();
        if (textarea) {
            createCounter(textarea);
            clearInterval(interval);
        }
        if (++tries > 20) {
            clearInterval(interval);
        }
    }, 300);

})();


/* ==================================================
   VALIDARE ORĂ – ADD TO CART (WAPF)
================================================== */
(function initHourValidationOnAddToCart() {

    var HOUR_FIELD_IDS = ['8dec5e7', '32f74cc', 'a4a0fca'];
    var HOUR_REGEX = /^([01]\d|2[0-3]):[0-5]\d$/;

    function getField(id) {
        return document.querySelector('[name="wapf[field_' + id + ']"]');
    }

    function clearValidationState(input) {
        if (!input) return;
        input.removeAttribute('aria-invalid');
        input.style.outline = '';
    }

    function markInvalid(input) {
        if (!input) return;
        input.setAttribute('aria-invalid', 'true');
        input.style.outline = '2px solid #d63638';
    }

    function validateHours() {
        for (var i = 0; i < HOUR_FIELD_IDS.length; i++) {
            var input = getField(HOUR_FIELD_IDS[i]);
            if (!input) continue;

            var value = (input.value || '').trim();
            clearValidationState(input);

            if (!value) continue;
            if (!HOUR_REGEX.test(value)) {
                markInvalid(input);
                input.focus();
                return false;
            }
        }

        return true;
    }

    function showError() {
        window.alert('Format oră invalid. Folosește formatul HH:MM (ex: 09:30, 14:05, 23:59).');
    }

    function bindValidation() {
        var form = document.querySelector('form.cart');
        if (!form) return false;
        if (form._teinvitHourValidationAttached) return true;

        form.addEventListener('submit', function (e) {
            if (!validateHours()) {
                e.preventDefault();
                e.stopPropagation();
                showError();
            }
        });

        form._teinvitHourValidationAttached = true;
        return true;
    }

    if (bindValidation()) return;

    var tries = 0;
    var interval = setInterval(function () {
        if (bindValidation()) {
            clearInterval(interval);
            return;
        }
        if (++tries > 20) {
            clearInterval(interval);
        }
    }, 300);

})();
