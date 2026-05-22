# Audit baseline-280 vs baseline-120226

## 1) Dovadă branch + baseline

### Comenzi rulate

```bash
git branch --show-current
# audit/baseline-280

git log -1 --oneline
# e546ae9 rezultatele rulajului pe server

ls -la audit | head -n 20
# ...
# drwxr-xr-x 2 root root 4096 Feb 13 23:40 280-2026-02-13
# drwxr-xr-x 2 root root 4096 Feb 13 12:33 baseline-120226

find audit -maxdepth 3 -type f | sort
# ...
# audit/280-2026-02-13/baseline.csv
# audit/280-2026-02-13/baseline.json
# ...
# audit/baseline-120226/baseline.csv
# audit/baseline-120226/baseline.json
```

### Confirmare path-uri

- BEFORE: `audit/baseline-120226/baseline.csv` + `audit/baseline-120226/baseline.json`
- AFTER (baseline-280 din acest workspace): `audit/280-2026-02-13/baseline.csv` + `audit/280-2026-02-13/baseline.json`

## 2) Audit strict contract „Debounced data-stream finalizer”

Fișier analizat: `teinvit-core/invitations/wedding/preview/preview.js`.

- Finalizer-ul cu debounce este implementat în `scheduleFinalProductPass()` (timer `setTimeout` cu `FINAL_PASS_DEBOUNCE_MS = 320`, plus confirmare de stabilitate în 2 ferestre consecutive prin `lastStableSignature`/`finalizedSignature`).
- Rulează doar în context product + WAPF prin `isProductWAPFContext()`:
  - necesită `hasWAPF()`
  - interzice `window.TEINVIT_INVITATION_DATA` (deci nu `/i/{token}`)
  - interzice `window.__TEINVIT_PDF_MODE__` (deci nu `/pdf/{token}`)
- Bypass pe `/pdf` este explicit prin `!window.__TEINVIT_PDF_MODE__` în `isProductWAPFContext()`.
- Handshake-ul PDF (`window.__TEINVIT_PDF_READY__ = true`) se setează doar după `window.__TEINVIT_AUTOFIT_DONE__` în `render()`, separat de finalizer-ul WAPF; nu există apel al finalizer-ului în PDF context.

## 3) Comparație BEFORE vs AFTER (baseline)

S-a comparat strict pe selectorii/indicatorii ceruți (`computed`, `geometry`, `lineCount`) între:
- BEFORE: `audit/baseline-120226/baseline.json`
- AFTER: `audit/280-2026-02-13/baseline.json`

### Rezumat pe teme + contexte

| Theme | Context | computed(fontFamily) | computed(fontSize) | computed(lineHeight) | computed(letterSpacing) | geometry diffs | lineCount diffs |
|---|---|---:|---:|---:|---:|---:|---:|
| classic | preview-prod | 0 | 0 | 0 | 0 | 0 | 0 |
| editorial | preview-prod | 0 | 0 | 0 | 0 | 0 | 0 |
| modern | preview-prod | 0 | 0 | 0 | 0 | 0 | 0 |
| romantic | preview-prod | 0 | 0 | 0 | 0 | 0 | 0 |
| classic | i-html | 0 | 9 | 9 | 0 | 59 | 0 |
| editorial | i-html | 0 | 9 | 9 | 0 | 59 | 0 |
| modern | i-html | 0 | 9 | 9 | 0 | 60 | 0 |
| romantic | i-html | 0 | 9 | 9 | 0 | 60 | 0 |
| classic | pdf-html | 0 | 9 | 9 | 0 | 59 | 0 |
| editorial | pdf-html | 0 | 9 | 9 | 0 | 59 | 0 |
| modern | pdf-html | 0 | 9 | 9 | 0 | 60 | 0 |
| romantic | pdf-html | 0 | 9 | 9 | 0 | 60 | 0 |

### Pattern-ul diferențelor

- `preview-prod`: fără diferențe pe toate temele.
- `i-html` + `pdf-html`: diferențe consistente de scalare tipografică și geometrie, fără schimbări de wrapping (`lineCount` constant).
- Valorile de tipografie schimbate recurent:
  - `.teinvit-canvas`: `fontSize 15.87px -> 17.25px`, `lineHeight 21.4245px -> 23.2875px`
  - `.inv-message`: `fontSize 13.9656px -> 15.18px`, `lineHeight 20.9484px -> 22.77px`
  - `.inv-events` / `.events-row.top` / `.events-row.bottom`: `fontSize 12.696px -> 13.8px`, `lineHeight 18.4092px -> 20.01px`
  - `.inv-event`: `fontSize 12.0612px -> 13.11px`, `lineHeight 17.4887px -> 19.0095px`
  - `.inv-names`: majoritar `27.7725px -> 30.1875px` și `34.7156px -> 37.7344px` (cu un caz `36.501px -> 39.675px`, `41.9761px -> 45.6263px`)

## 4) Concluzie

**Contract îndeplinit (pe cod).**

- Finalizer-ul debounce există și este izolat pe product+WAPF.
- PDF este bypass-at explicit pentru finalizer.
- Handshake/autofit PDF sunt pe flux separat și nu sunt influențate de finalizer.
- Baseline arată schimbări doar în `i-html`/`pdf-html` (scalare tipografică + geometrie), fără schimbări de wrap (`lineCount`).

Recomandări minime (fără fixuri):
1. Dacă baseline-280 trebuie să fie numit strict „baseline-280”, uniformizați naming-ul folderului (acum este `280-2026-02-13`).
2. Dacă se dorește zero-diff și pe `i-html`/`pdf-html`, verificați pipeline-ul de scalare (factorul global de font/canvas) care produce offset-ul constant observat.
