document.addEventListener('DOMContentLoaded', function () {

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


    function formatDateTimeLine(dateValue, timeValue) {
        var date = (dateValue || '').trim();
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

    /* ==================================================
       THEME LOGIC – PAGINA PRODUS (WAPF)
    ================================================== */

    function applyThemeFromWAPF() {

        var select = qs('[name="wapf[field_6967752ab511b]"]');
        if (!select) return;

        var selectedOption = select.options[select.selectedIndex];
        if (!selectedOption) return;

        var label = selectedOption.text.toLowerCase();
        var slug = 'editorial';

        if (label.indexOf('romantic') !== -1) slug = 'romantic';
        if (label.indexOf('modern') !== -1)   slug = 'modern';
        if (label.indexOf('classic') !== -1)  slug = 'classic';

        var canvas = qs('.teinvit-canvas');
        if (!canvas) return;

        canvas.classList.remove(
            'theme-editorial',
            'theme-romantic',
            'theme-modern',
            'theme-classic'
        );

        canvas.classList.add('theme-' + slug);
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

        var slug = window.TEINVIT_INVITATION_DATA.theme;
        var canvas = qs('.teinvit-canvas');
        if (!canvas) return;

        canvas.classList.remove(
            'theme-editorial',
            'theme-romantic',
            'theme-modern',
            'theme-classic'
        );

        canvas.classList.add('theme-' + slug);
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
            return {
                names: formatNames(
            join(
            valWAPF('6963a95e66425'),
            valWAPF('6963aa37412e4'),
            ' & '
        )
    ),

                message: limitMessage(valWAPF('6963aa782092d')),
                show_parents: checkedWAPF('696445d6a9ce9'),
                parents: {
                    mireasa: join(
                        valWAPF('6964461d67da5'),
                        valWAPF('6964466afe4d1'),
                        ' & '
                    ),
                    mire: join(
                        valWAPF('69644689ee7e1'),
                        valWAPF('696446dfabb7b'),
                        ' & '
                    )
                },
                show_nasi: checkedWAPF('696448f2ae763'),
                nasi: join(
                    valWAPF('69644a3415fb9'),
                    valWAPF('69644a5822ddc'),
                    ' & '
                ),
                events: buildEventsFromWAPF()
            };
        }

                if (window.TEINVIT_INVITATION_DATA) {
                return {
            ...window.TEINVIT_INVITATION_DATA,
            names: formatNames(window.TEINVIT_INVITATION_DATA.names)
            };
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

    /* ==================================================
       RENDER
    ================================================== */

        function render() {

        var data = getInvitationData();
        if (!data) return;

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
            if (cols[0]) cols[0].textContent = data.parents.mireasa;
            if (cols[1]) cols[1].textContent = data.parents.mire;
        } else if (pw) {
            pw.style.display = 'none';
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
            var html =
                '<div class="inv-event">' +
                '<strong>' + e.title + '</strong>' +
                '<div>' + e.loc + '</div>' +
                '<div>' + e.date + '</div>' +
                (e.waze ? '<a href="' + e.waze + '" target="_blank">Deschide în Waze</a>' : '') +
                '</div>';

            (i < 2 ? top : bottom).insertAdjacentHTML('beforeend', html);
        });

        evBox.style.display = data.events.length ? '' : 'none';

        syncBaseFontSize(canvas);
applyAutoFit(canvas);

        // ================================
        // 🔑 PDF HANDSHAKE – RENDER FINAL
        // ================================
        window.TEINVIT_RENDER_READY = true;
        if (
            window.__TEINVIT_PDF_MODE__ &&
            window.__TEINVIT_AUTOFIT_DONE__ &&
            !window.__TEINVIT_PDF_READY__
        ) {
            window.__TEINVIT_PDF_READY__ = true;
        }
    }

/* ==================================================
       AUTO RESIZE GLOBAL
    ================================================== */

    function applyAutoFit(canvas) {

    // Dacă suntem în i/{token} sau pdf/{token},
    // rulăm auto-fit o singură dată
    if (
        (window.__TEINVIT_PDF_MODE__ || window.TEINVIT_INVITATION_DATA) &&
        window.__TEINVIT_AUTOFIT_DONE__
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
        while (canvas.scrollHeight > canvas.clientHeight && safety < 60) {
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
        }, FINAL_PASS_DEBOUNCE_MS);
    }

    function onPreviewDataChange() {
        render();
        scheduleFinalProductPass();
    }

    render();
    scheduleFinalProductPass();
    document.addEventListener('input', onPreviewDataChange);
    document.addEventListener('change', onPreviewDataChange);

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
