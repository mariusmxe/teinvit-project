(function () {
function j(x) { return document.querySelector(x); }
function qsa(x, root) { return Array.prototype.slice.call((root || document).querySelectorAll(x)); }
function el(tag, attrs) {
    var n = document.createElement(tag);
    if (attrs) {
        Object.keys(attrs).forEach(function (k) {
            if (k === 'text') n.textContent = attrs[k];
            else if (k === 'value') n.value = attrs[k];
            else n.setAttribute(k, attrs[k]);
        });
    }
    return n;
}

async function api(url, method, data, nonce) {
    const r = await fetch(url, {
        method: method || 'GET',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce || '' },
        body: data ? JSON.stringify(data) : undefined
    });

    const payload = await r.json();
    if (!r.ok) {
        var err = new Error(payload.message || 'error');
        err.data = payload.data || {};
        throw err;
    }
    return payload;
}

function normalizeBool(v) {
    if (typeof v === 'boolean') return v;
    if (v === null || v === undefined) return false;
    var s = String(v).trim().toLowerCase();
    return s === '1' || s === 'true' || s === 'yes' || s === 'da' || s === 'on';
}

if (window.TEINVIT_CLIENT_ADMIN) {
    const C = window.TEINVIT_CLIENT_ADMIN;
    C.gifts = (C.gifts || []).map(function (g) { return Object.assign({}, g); });

    function getWapfFieldValue(fieldId) {
        var textInput = document.querySelector('[name="wapf[field_' + fieldId + ']"]');
        if (textInput) {
            if (textInput.type === 'checkbox') {
                return textInput.checked ? (textInput.value || '1') : '';
            }
            return textInput.value || '';
        }

        var checks = qsa('[name="wapf[field_' + fieldId + '][]"]:checked');
        if (checks.length) {
            return checks.map(function (c) { return c.value || '1'; }).join(',');
        }
        return '';
    }

    function evaluateRule(rule) {
        var currentRaw = getWapfFieldValue(rule.field);
        var currentBool = normalizeBool(currentRaw);
        var expected = (rule.value || '').toString();
        var expectedBool = normalizeBool(expected);
        var op = (rule.operator || '==').toString();

        if (op === '!=' || op === 'not' || op === 'is_not') {
            if (expected === '') return !currentBool;
            return currentRaw !== expected;
        }

        if (expected === '') return currentBool;
        if (expectedBool) return currentBool;
        return currentRaw === expected;
    }

    function applyWapfConditions() {
        qsa('.teinvit-wapf-field[data-wapf-conditions]').forEach(function (node) {
            var raw = node.getAttribute('data-wapf-conditions') || '[]';
            var rules = [];
            try { rules = JSON.parse(raw); } catch (e) { rules = []; }
            if (!Array.isArray(rules) || !rules.length) {
                node.style.display = '';
                return;
            }

            var visible = rules.every(evaluateRule);
            node.style.display = visible ? '' : 'none';
        });
    }

    const versions = (C.versions || []).map(v => parseInt(v.version, 10));
    const sel = j('#teinvit-active-version');
    versions.forEach(function (v) {
        const o = el('option', { value: v, text: 'v' + v });
        if (v === parseInt(C.settings.active_version, 10)) o.selected = true;
        sel.appendChild(o);
    });

    function refreshCounter() {
        const rem = parseInt(C.remaining, 10) || 0;
        const c = j('#teinvit-edits-counter');
        const save = j('#teinvit-save-version');
        const buy = j('#teinvit-buy-edits');
        if (rem === 2) c.textContent = '2 modificări gratuite';
        else if (rem === 1) c.textContent = '1 modificare gratuită';
        else c.textContent = '0 modificări';
        save.style.display = rem > 0 ? 'inline-block' : 'none';
        buy.style.display = rem === 0 ? 'inline-block' : 'none';
    }

    refreshCounter();
    applyWapfConditions();
    document.addEventListener('input', applyWapfConditions);
    document.addEventListener('change', applyWapfConditions);

    j('#teinvit-set-active').addEventListener('click', async function () {
        await api(C.rest + '/client-admin/' + C.token + '/set-active-version', 'POST', { version: parseInt(sel.value, 10) }, C.nonce);
        alert('Setat');
    });

    j('#teinvit-save-version').addEventListener('click', async function () {
        const fields = {};
        qsa('[name^="wapf[field_"]').forEach(function (i) {
            var k = i.name.replace('wapf[field_', '').replace('][]', '').replace(']', '');
            if (i.type === 'checkbox') {
                if (i.checked) fields[k] = i.value || '1';
            } else if (i.type === 'radio') {
                if (i.checked) fields[k] = i.value || '';
            } else {
                fields[k] = i.value;
            }
        });

        const inv = window.TEINVIT_INVITATION_DATA || {};
        const res = await api(C.rest + '/client-admin/' + C.token + '/save-version', 'POST', { invitation: inv, wapf_fields: fields }, C.nonce);
        C.remaining = Math.max(0, (parseInt(C.remaining, 10) || 0) - 1);
        refreshCounter();
        const o = el('option', { value: res.version, text: 'v' + res.version });
        sel.appendChild(o);
        sel.value = res.version;
        alert('Versiune salvată');
    });

    const flagDefs = [
        ['civil', 'Confirmare prezenta Cununie civilă'],
        ['religious', 'Confirmare prezenta Ceremonie religioasă'],
        ['party', 'Confirmare prezenta Petrecere'],
        ['kids', 'Număr copii'],
        ['lodging', 'Solicitare cazare'],
        ['vegetarian', 'Meniu vegetarian'],
        ['allergies', 'Alergeni'],
        ['gifts_enabled', 'Doresc afișarea listei de cadouri']
    ];

    const flagsBox = j('#teinvit-rsvp-flags');
    flagDefs.forEach(function (fd) {
        const l = el('label');
        const i = el('input', { type: 'checkbox', 'data-flag': fd[0] });
        if (C.settings.rsvp_flags && C.settings.rsvp_flags[fd[0]]) i.checked = true;
        l.appendChild(i);
        l.appendChild(document.createTextNode(' ' + fd[1]));
        flagsBox.appendChild(l);
    });

    j('#teinvit-save-flags').addEventListener('click', async function () {
        const flags = {};
        flagsBox.querySelectorAll('[data-flag]').forEach(function (i) { flags[i.dataset.flag] = i.checked; });
        await api(C.rest + '/client-admin/' + C.token + '/flags', 'POST', { flags: flags }, C.nonce);
        j('#teinvit-gifts-section').style.display = flags.gifts_enabled ? 'block' : 'none';
        alert('Salvat');
    });

    function giftRow(g) {
        const row = el('div', { class: 'gift-row' });
        row.dataset.id = g.id ? String(g.id) : '';

        const t = el('input', { type: 'text', 'data-k': 'title', placeholder: 'Denumire produs', value: g.title || '' });
        const u = el('input', { type: 'text', 'data-k': 'url', placeholder: 'Link produs', value: g.url || '' });
        const d = el('input', { type: 'text', 'data-k': 'delivery_address', placeholder: 'Adresă livrare', value: g.delivery_address || '' });

        row.appendChild(t);
        row.appendChild(u);
        row.appendChild(d);
        return row;
    }

    function renderGifts() {
        const list = j('#teinvit-gifts-list');
        list.innerHTML = '';
        const gifts = C.gifts || [];

        gifts.forEach(function (g) { list.appendChild(giftRow(g)); });

        j('#teinvit-gifts-counter').textContent = 'Ai folosit ' + gifts.length + ' din ' + (parseInt(C.capacity, 10) || 0) + ' cadouri';
        j('#teinvit-add-gift').style.display = gifts.length < (parseInt(C.capacity, 10) || 0) ? 'inline-block' : 'none';
        j('#teinvit-buy-gifts').style.display = gifts.length >= (parseInt(C.capacity, 10) || 0) ? 'inline-block' : 'none';
    }

    renderGifts();
    j('#teinvit-gifts-section').style.display = (C.settings.rsvp_flags && C.settings.rsvp_flags.gifts_enabled) ? 'block' : 'none';

    j('#teinvit-add-gift').addEventListener('click', function () {
        C.gifts = C.gifts || [];
        if (C.gifts.length >= (parseInt(C.capacity, 10) || 0)) return;
        C.gifts.push({ id: null, title: '', url: '', delivery_address: '', position: C.gifts.length + 1 });
        renderGifts();
    });

    j('#teinvit-save-gifts').addEventListener('click', async function () {
        const gifts = [];
        qsa('#teinvit-gifts-list .gift-row').forEach(function (r, idx) {
            gifts.push({
                id: r.dataset.id ? parseInt(r.dataset.id, 10) : null,
                title: r.querySelector('[data-k="title"]').value,
                url: r.querySelector('[data-k="url"]').value,
                delivery_address: r.querySelector('[data-k="delivery_address"]').value,
                position: idx + 1
            });
        });

        try {
            const res = await api(C.rest + '/client-admin/' + C.token + '/gifts', 'POST', { gifts: gifts }, C.nonce);
            C.gifts = res.gifts || gifts;
            renderGifts();
            alert('Cadouri salvate');
        } catch (e) {
            alert(e.message || 'Eroare la salvare');
        }
    });

    api(C.rest + '/client-admin/' + C.token + '/rsvp', 'GET', null, C.nonce).then(function (rows) {
        const box = j('#teinvit-rsvp-report');
        if (!rows.length) {
            box.textContent = 'Nu există RSVP-uri.';
            return;
        }
        const t = el('table');
        const h = el('tr');
        ['Nume invitat', 'Prenume invitat', 'Telefon invitat', 'Câte persoane confirmă'].forEach(function (c) { h.appendChild(el('th', { text: c })); });
        t.appendChild(h);
        rows.forEach(function (r) {
            const tr = el('tr');
            [r.guest_last_name, r.guest_first_name, r.phone, r.attendees_count].forEach(function (v) { tr.appendChild(el('td', { text: String(v || '') })); });
            t.appendChild(tr);
        });
        box.appendChild(t);
    });
}

if (window.TEINVIT_GUEST) {
    const G = window.TEINVIT_GUEST;
    const root = j('#teinvit-guest-rsvp');
    if (!root) return;

    const card = el('div', { class: 'teinvit-guest-rsvp-card' });
    const title = el('h3', { text: 'Confirmare prezență' });
    card.appendChild(title);

    const f = el('form', { class: 'teinvit-guest-rsvp-form' });

    function addField(label, name, type) {
        const wrap = el('label');
        wrap.appendChild(document.createTextNode(label));
        const i = el('input', { name: name, type: type || 'text' });
        wrap.appendChild(i);
        f.appendChild(wrap);
        return i;
    }

    const ln = addField('Nume invitat', 'guest_last_name', 'text');
    const fn = addField('Prenume invitat', 'guest_first_name', 'text');
    const ph = addField('Telefon invitat', 'phone', 'text');
    const at = addField('Câte persoane confirmă', 'attendees_count', 'number');
    at.value = '1';

    function addYesNo(label, key) {
        const wrap = el('label');
        wrap.appendChild(document.createTextNode(label));
        const s = el('select', { name: key });
        s.appendChild(el('option', { value: '', text: '-' }));
        s.appendChild(el('option', { value: 'da', text: 'DA' }));
        s.appendChild(el('option', { value: 'nu', text: 'NU' }));
        wrap.appendChild(s);
        f.appendChild(wrap);
        return s;
    }

    const fields = {};
    if (G.flags.civil) fields.civil = addYesNo('Prezență civilă', 'civil');
    if (G.flags.religious) fields.religious = addYesNo('Prezență religioasă', 'religious');
    if (G.flags.party) fields.party = addYesNo('Prezență petrecere', 'party');

    if (G.flags.kids) {
        fields.kids = addYesNo('Copii', 'kids');
        const kidsCount = addField('Nr copii', 'kids_count', 'number');
        kidsCount.style.display = 'none';
        fields.kids_count = kidsCount;
        fields.kids.addEventListener('change', function () { kidsCount.style.display = fields.kids.value === 'da' ? '' : 'none'; });
    }

    if (G.flags.lodging) {
        fields.lodging = addYesNo('Cazare', 'lodging');
        const lodgeCount = addField('Câte persoane', 'lodging_count', 'number');
        lodgeCount.style.display = 'none';
        fields.lodging_count = lodgeCount;
        fields.lodging.addEventListener('change', function () { lodgeCount.style.display = fields.lodging.value === 'da' ? '' : 'none'; });
    }

    if (G.flags.vegetarian) fields.vegetarian = addYesNo('Vegetarian', 'vegetarian');

    if (G.flags.allergies) {
        fields.allergies = addYesNo('Alergeni', 'allergies');
        const allergiesText = addField('Completează alergiile', 'allergies_text', 'text');
        allergiesText.style.display = 'none';
        fields.allergies_text = allergiesText;
        fields.allergies.addEventListener('change', function () { allergiesText.style.display = fields.allergies.value === 'da' ? '' : 'none'; });
    }

    const submit = el('button', { type: 'submit', text: 'Trimite RSVP' });
    f.appendChild(submit);
    card.appendChild(f);

    let giftChecks = [];
    if (G.flags.gifts_enabled) {
        const giftsTitle = el('h3', { text: 'Lista cadouri' });
        const gbox = el('div', { class: 'teinvit-guest-gifts' });
        (G.gifts || []).forEach(function (g) {
            const c = el('label');
            const i = el('input', { type: 'checkbox', value: g.id });
            if ((G.bookedGiftIds || []).map(Number).includes(Number(g.id))) i.disabled = true;
            c.appendChild(i);
            c.appendChild(document.createTextNode(' ' + g.title));
            gbox.appendChild(c);
            giftChecks.push(i);
        });
        card.appendChild(giftsTitle);
        card.appendChild(gbox);
    }

    root.appendChild(card);

    f.addEventListener('submit', async function (e) {
        e.preventDefault();

        var phone = (ph.value || '').replace(/\D+/g, '');
        if (!/^07\d{8}$/.test(phone)) {
            alert('Telefon invalid. Format corect: 0721330411');
            return;
        }

        const payloadFields = {};
        Object.keys(fields).forEach(function (k) {
            payloadFields[k] = fields[k].value;
        });

        const p = {
            guest_last_name: ln.value,
            guest_first_name: fn.value,
            phone: phone,
            attendees_count: parseInt(at.value, 10) || 1,
            fields: payloadFields,
            gift_ids: giftChecks.filter(i => i.checked && !i.disabled).map(i => parseInt(i.value, 10))
        };

        try {
            await api(G.rest + '/invite/' + G.token + '/rsvp', 'POST', p, '');
            alert('Mulțumim!');
            location.reload();
        } catch (err) {
            alert(err.message || 'Eroare la trimitere RSVP');
            location.reload();
        }
    });
}
})();
