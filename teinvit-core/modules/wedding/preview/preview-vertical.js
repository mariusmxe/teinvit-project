(function () {
    function qs(sel, root) { return (root || document).querySelector(sel); }
    function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function parseFieldId(name) {
        var raw = String(name || '').trim();
        var m = raw.match(/field_([a-z0-9]+)/i);
        if (!m) return '';
        var id = String(m[1] || '').trim();
        if (!id) return '';
        return id.replace(/_\d+$/, '');
    }

    function collectWapfMap() {
        var out = {};
        qsa('[name^="wapf[field_"]').forEach(function (el) {
            var name = el.getAttribute('name') || '';
            var id = parseFieldId(name);
            if (!id) return;

            var values = [];
            if (el.tagName === 'SELECT' && el.multiple) {
                Array.prototype.slice.call(el.options || []).forEach(function (opt) {
                    if (opt && opt.selected) values.push((opt.value || '').trim());
                });
            } else if (el.type === 'checkbox' || el.type === 'radio') {
                if (el.checked) values.push((el.value || '').trim());
            } else {
                values.push((el.value || '').trim());
            }

            values = values.filter(Boolean);
            if (!values.length) return;

            if (!out[id]) out[id] = [];
            out[id] = out[id].concat(values);
        });

        Object.keys(out).forEach(function (id) {
            var uniq = [];
            out[id].forEach(function (v) { if (uniq.indexOf(v) === -1) uniq.push(v); });
            out[id] = uniq.join(', ');
        });

        return out;
    }

    function applyTheme(canvas, themeKey) {
        if (!canvas) return;
        ['theme-editorial-luxury', 'theme-romantic-floral', 'theme-modern-minimal', 'theme-classic-elegant', 'theme-baptism-editorial', 'theme-baptism-romantic', 'theme-baptism-modern', 'theme-baptism-classic', 'theme-birthday-editorial', 'theme-birthday-romantic', 'theme-birthday-modern', 'theme-birthday-classic'].forEach(function (c) {
            canvas.classList.remove(c);
        });
        var t = String(themeKey || '').toLowerCase();
        var vertical = (window.teinvitVerticalPreviewConfig && String(window.teinvitVerticalPreviewConfig.vertical || '').toLowerCase()) || '';
        var cls = 'theme-editorial-luxury';
        if (t === 'romantic') cls = 'theme-romantic-floral';
        else if (t === 'modern') cls = 'theme-modern-minimal';
        else if (t === 'classic') cls = 'theme-classic-elegant';
        canvas.classList.add(cls);

        var vCls = '';
        if (vertical === 'baptism') {
            vCls = 'theme-baptism-editorial';
            if (t === 'romantic') vCls = 'theme-baptism-romantic';
            else if (t === 'modern') vCls = 'theme-baptism-modern';
            else if (t === 'classic') vCls = 'theme-baptism-classic';
        } else if (vertical === 'birthday') {
            vCls = 'theme-birthday-editorial';
            if (t === 'romantic') vCls = 'theme-birthday-romantic';
            else if (t === 'modern') vCls = 'theme-birthday-modern';
            else if (t === 'classic') vCls = 'theme-birthday-classic';
        }

        if (vCls) {
            canvas.classList.add(vCls);
        }
    }

    function buildPreview() {
        var mount = qs('#teinvit-vertical-product-preview');
        if (!mount) return;

        var productId = parseInt(mount.getAttribute('data-product-id') || '0', 10);
        var url = (window.teinvitVerticalPreviewConfig && window.teinvitVerticalPreviewConfig.previewBuildUrl) || '';
        if (!productId || !url) return;

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id: productId,
                wapf_map: collectWapfMap()
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (!json || !json.ok || !json.invitation) return;

            var canvas = qs('.teinvit-canvas', mount);
            if (!canvas) return;
            applyTheme(canvas, json.invitation.theme || 'editorial');

            var names = qs('.inv-names', canvas);
            if (names) names.textContent = json.invitation.headline || '';

            var msg = qs('.inv-message', canvas);
            if (msg) msg.textContent = json.invitation.message || '';

            var parents = qs('.inv-parents-wrapper', canvas);
            if (parents && json.invitation.parents) {
                parents.style.display = json.invitation.parents.enabled ? '' : 'none';
                var mother = qs('.inv-parent-mireasa', parents);
                var father = qs('.inv-parent-mire', parents);
                if (mother) mother.textContent = json.invitation.parents.mother || '';
                if (father) father.textContent = json.invitation.parents.father || '';
            }

            var nasi = qs('.inv-nasi', canvas);
            if (nasi && json.invitation.godparents) {
                nasi.style.display = json.invitation.godparents.enabled ? '' : 'none';
                var row = qs('.nasi-row', nasi);
                if (row) row.textContent = [json.invitation.godparents.godmother || '', json.invitation.godparents.godfather || ''].filter(Boolean).join(' & ');
            }

            var eventsWrap = qs('.inv-events', canvas);
            if (eventsWrap) {
                var top = qs('.events-row.top', eventsWrap);
                var bottom = qs('.events-row.bottom', eventsWrap);
                if (top) top.innerHTML = '';
                if (bottom) bottom.innerHTML = '';

                var events = [];
                if (json.invitation.events && json.invitation.events.religious && json.invitation.events.religious.enabled) events.push(json.invitation.events.religious);
                if (json.invitation.events && json.invitation.events.party && json.invitation.events.party.enabled) events.push(json.invitation.events.party);

                eventsWrap.style.display = events.length ? '' : 'none';
                events.forEach(function (event) {
                    if (!top) return;
                    var div = document.createElement('div');
                    div.className = 'inv-event';
                    var html = '<strong>' + (event.title || '') + '</strong><div>' + (event.loc || '') + '</div><div>' + (event.date || '') + '</div>';
                    if (event.waze) html += '<a href="' + event.waze + '" target="_blank" rel="noopener">Waze</a>';
                    div.innerHTML = html;
                    top.appendChild(div);
                });
            }
        })
        .catch(function () {});
    }

    function normalizeNewCloneInputs() {
        var repeatableIds = { '2d8d1ce': true, 'd1fe0da': true };
        qsa('[name*=\"field_\"]').forEach(function (el) {
            var id = parseFieldId(el.getAttribute('name') || '');
            if (!repeatableIds[id]) return;
            if (el.__teinvitCloneNormalized) return;

            var siblings = qsa('[name=\"' + (el.getAttribute('name') || '').replace(/\"/g, '\\\"') + '\"]');
            if (siblings.length > 1 && (el.value || '').trim() !== '') {
                el.value = '';
            }
            el.__teinvitCloneNormalized = true;
        });
    }

    function setupMessageCounter() {
        var maxChars = (window.teinvitVerticalPreviewConfig && parseInt(window.teinvitVerticalPreviewConfig.maxChars, 10)) || 250;
        qsa('textarea[name="wapf[field_4c3baec]"], textarea[name="wapf[field_bef895a]"]').forEach(function (textarea) {
            if (!textarea) return;

            textarea.setAttribute('maxlength', String(maxChars));

            var counter = document.createElement('div');
            counter.className = 'teinvit-message-counter';
            counter.style.fontSize = '12px';
            counter.style.color = '#6b7280';
            counter.style.marginTop = '6px';
            textarea.insertAdjacentElement('afterend', counter);

            var update = function () {
                if ((textarea.value || '').length > maxChars) {
                    textarea.value = textarea.value.substring(0, maxChars);
                }
                var len = (textarea.value || '').length;
                counter.textContent = len + ' / ' + maxChars + ' caractere';
                buildPreview();
            };

            textarea.addEventListener('input', update);
            update();
        });
    }

    document.addEventListener('change', function (e) {
        var t = e.target;
        if (t && t.name && t.name.indexOf('wapf[') === 0) {
            setTimeout(buildPreview, 50);
        }
    });

    document.addEventListener('input', function (e) {
        var t = e.target;
        if (t && t.name && t.name.indexOf('wapf[') === 0 && t.tagName !== 'TEXTAREA') {
            setTimeout(buildPreview, 50);
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        if (!qs('#teinvit-vertical-product-preview')) return;
        normalizeNewCloneInputs();
        setupMessageCounter();
        buildPreview();

        var form = qs('form.cart') || document.body;
        if (window.MutationObserver && form) {
            var obs = new MutationObserver(function () {
                normalizeNewCloneInputs();
                buildPreview();
            });
            obs.observe(form, { childList: true, subtree: true });
        }
    });
})();
