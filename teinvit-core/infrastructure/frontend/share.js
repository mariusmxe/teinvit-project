(function () {
  'use strict';

  function parsePayload(card) {
    try {
      return JSON.parse(card.getAttribute('data-share-payload') || '{}') || {};
    } catch (e) {
      return {};
    }
  }

  function buildMessage(payload) {
    if (payload.message) {
      return String(payload.message);
    }
    return [payload.text || '', payload.url || ''].filter(Boolean).join('\n');
  }

  function setStatus(card, message) {
    var status = card.querySelector('[data-teinvit-share-status]');
    if (status) {
      status.textContent = String(message || '');
    }
  }

  function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = String(text || '');
    ta.setAttribute('readonly', 'readonly');
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    ta.setSelectionRange(0, ta.value.length);
    var ok = document.execCommand('copy');
    document.body.removeChild(ta);
    return !!ok;
  }

  async function copyText(text) {
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      try {
        await navigator.clipboard.writeText(String(text || ''));
        return true;
      } catch (e) {}
    }
    return fallbackCopy(text);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-teinvit-share-card]').forEach(function (card) {
      var payload = parsePayload(card);
      var message = buildMessage(payload);
      var whatsapp = card.querySelector('[data-teinvit-share-action="whatsapp"]');
      if (whatsapp) {
        whatsapp.setAttribute('href', 'https://wa.me/?text=' + encodeURIComponent(message));
      }

      card.addEventListener('click', async function (event) {
        var target = event.target.closest('[data-teinvit-share-action]');
        if (!target || !card.contains(target)) {
          return;
        }

        var action = target.getAttribute('data-teinvit-share-action');
        if (action === 'native') {
          if (navigator.share && typeof navigator.share === 'function') {
            try {
              await navigator.share({
                title: payload.title || document.title || '',
                text: payload.text || '',
                url: payload.url || ''
              });
              setStatus(card, 'Linkul este pregătit pentru distribuire.');
              return;
            } catch (e) {
              if (e && e.name === 'AbortError') {
                return;
              }
            }
          }
          var copiedFallback = await copyText(payload.url || message);
          setStatus(card, copiedFallback ? 'Distribuirea nu este disponibilă în acest browser. Linkul a fost copiat.' : 'Distribuirea nu este disponibilă în acest browser.');
          return;
        }

        if (action === 'copy') {
          var copiedLink = await copyText(payload.url || '');
          setStatus(card, copiedLink ? 'Linkul a fost copiat.' : 'Nu am putut copia automat linkul.');
          return;
        }

        if (action === 'instagram') {
          var copiedMessage = await copyText(message || payload.url || '');
          setStatus(card, copiedMessage ? 'Mesajul a fost copiat. Îl poți lipi în Instagram.' : 'Nu am putut copia automat mesajul.');
        }
      });
    });
  });
})();
