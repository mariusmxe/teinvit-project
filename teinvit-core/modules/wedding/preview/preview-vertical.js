(function () {
    var PARENT_BOOLEAN_FIELD_IDS = {
        '3ec4ca5': true, // baptism: show parents
        '1f32dd0': true, // baptism: show godparents
        '1eceab7': true, // baptism: show religious event
        'b4fca64': true, // baptism: show party event
        'fc5b530': true  // birthday: show party event
    };

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

    function collectWapfMapFromForm() {
        var form = qs('#teinvit-save-form') || qs('form.cart') || qs('form');
        if (!form) return {};

        var out = {};
        var fd = new FormData(form);

        fd.forEach(function (value, key) {
            var id = parseFieldId(key || '');
            if (!id) return;
            if (Object.prototype.hasOwnProperty.call(PARENT_BOOLEAN_FIELD_IDS, id)) return;

            if (!Object.prototype.hasOwnProperty.call(out, id)) out[id] = [];
            out[id].push(String(value || '').trim());
        });

        Object.keys(PARENT_BOOLEAN_FIELD_IDS).forEach(function (id) {
            var inputs = qsa('[name="wapf[field_' + id + '][]"]', form);
            if (!inputs.length) return;

            var selected = inputs
                .filter(function (input) {
                    return input && input.type === 'checkbox' && input.checked;
                })
                .map(function (input) {
                    return String(input.value || '').trim();
                })
                .filter(function (v) {
                    return v !== '';
                });

            out[id] = selected.join(', ');
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
        if (!url && window.wpApiSettings && window.wpApiSettings.root) {
            url = String(window.wpApiSettings.root).replace(/\/?$/, '/') + 'teinvit/v2/preview/build';
        }
        if (!productId || !url) return;

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id: productId,
                wapf_map: collectWapfMapFromForm()
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
            var obs = new MutationObserver(function (mutations) {
                var repeatableIds = { '2d8d1ce': true, 'd1fe0da': true };
                (mutations || []).forEach(function (mutation) {
                    Array.prototype.slice.call((mutation && mutation.addedNodes) || []).forEach(function (node) {
                        if (!node || node.nodeType !== 1) return;

                        var candidates = [];
                        if (node.matches && node.matches('[name*=\"field_\"]')) {
                            candidates.push(node);
                        }
                        if (node.querySelectorAll) {
                            candidates = candidates.concat(Array.prototype.slice.call(node.querySelectorAll('[name*=\"field_\"]')));
                        }

                        candidates.forEach(function (el) {
                            var id = parseFieldId(el.getAttribute('name') || '');
                            if (!repeatableIds[id]) return;
                            if ((el.value || '').trim() === '') return;
                            el.value = '';
                            el.dispatchEvent(new Event('input', { bubbles: true }));
                            el.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                    });
                });

                normalizeNewCloneInputs();
                buildPreview();
            });
            obs.observe(form, { childList: true, subtree: true });
        }
    });
})();
