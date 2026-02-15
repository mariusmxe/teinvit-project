# TeInvit – Theme Refresh Impact Analysis (EL-1 / RF-3 / MM-1 / CE-2)

## Scope
Analysis-only document for replacing legacy text themes with:
- Editorial Luxury (EL-1)
- Romantic Floral (RF-3)
- Modern Minimal (MM-1)
- Classic Elegant (CE-2)

Constraints preserved:
- JS-first rendering flow and existing `applyAutoFit()` behavior
- 1:1 parity across product preview, `/i/{token}`, `/pdf/{token}`, and Puppeteer PDF
- No changes to WP→Node PDF pipeline and no changes to `window.__TEINVIT_PDF_READY__` handshake

## A) Exact files and touch points

1. `teinvit-core/invitations/wedding/preview/template.php`
   - Insert `<div class="inv-divider" aria-hidden="true"></div>` immediately after `.inv-names` in BOTH render branches (preview and pdf context) to keep markup parity.

2. `teinvit-core/invitations/wedding/preview/themes.css`
   - Replace legacy theme typography tokens with the new 4-theme contract.
   - Keep global layout/flow predictable; add divider styles per theme (hairline + ornament-dot variant).
   - Keep section title sizing equal to section body sizing; differentiate by weight + letter spacing only.

3. `teinvit-core/invitations/wedding/preview/renderer.php`
   - Update Google Fonts URL in `print_assets()` to load only the required families/weights for EL-1, RF-3, MM-1, CE-2.
   - Keep preconnect links and same enqueue path for preview/pdf contexts.

4. `teinvit-core/invitations/wedding/preview/preview.css`
   - Normally no required change (layout shell already mirrors `pdf.css`).
   - Touch only if tiny neutral spacing normalization is needed globally.

5. `teinvit-core/invitations/wedding/preview/pdf.css`
   - Normally no required change (layout shell already mirrors `preview.css`).
   - Touch only if exact same neutral spacing normalization is required as in `preview.css`.

6. `teinvit-core/invitations/wedding/preview/preview.js`
   - Prefer zero changes.
   - Optional minimal change only for divider visibility fallback (show divider only when names + any following section exist). If CSS-only visibility rules are sufficient, keep JS untouched.

## B) Risks and mitigations

### Autofit / overflow risks
- Largest risk theme: RF-3 (`Parisienne` names at `2.26em`) because script-like glyph metrics can increase line box height and wrapping pressure.
- Divider can add vertical pressure if it consumes real height/margins.

Mitigations:
- Keep divider in-flow but with `height: 0` + border/pseudo and very small symmetric margins.
- Put all sizes in `em` and keep a strict token contract per selector.
- Keep controlled line-heights for names/message/events.
- Keep section title font-size equal to section body (`0.92em`) to avoid hidden vertical inflation.

### 1:1 preview vs /i vs /pdf risks
- Divergence happens if markup differs between preview and pdf branches.
- Divergence happens if theme styles leak into layout styles in `preview.css` or `pdf.css` instead of shared `themes.css`.

Mitigations:
- Add divider in both template branches at identical DOM position.
- Keep typography/theme tokens centralized in `themes.css` only.
- Keep `preview.css` and `pdf.css` for shell/layout parity only.

### Font loading risks (FOUT/FOIT affecting measurements)
- If fonts arrive late, first autofit pass may measure fallback font and lock a suboptimal scale in `/i` or `/pdf` contexts (one-pass guard).

Mitigations (no pipeline change):
- Continue using Google Fonts + `display=swap` and preconnect.
- Reduce family/weight list to only required fonts to minimize fetch/parse time.
- Keep initial render deterministic; avoid JS font-loading orchestration unless a proven mismatch appears.

## C) Minimal implementation plan (no heavy refactor)

1. Add `.inv-divider` element in `template.php` after `.inv-names` in both context branches.
2. Update Google Fonts link in `renderer.php` to required families only:
   - Playfair Display 600
   - Source Serif 4 400
   - Raleway 600
   - Parisienne 400
   - Crimson Text 400/600
   - DM Sans 600
   - Inter 400/600
3. Rewrite theme token blocks in `themes.css` for EL-1 / RF-3 / MM-1 / CE-2:
   - names, message, section, date, link sizes
   - colors (names/primary/secondary/accent/link)
   - CAPS rules (same size, different weight + spacing)
4. Add divider styles in `themes.css`:
   - Hairline variant for EL-1 / MM-1 / CE-2 (width + opacity + accent color)
   - Ornament-dot for RF-3 using pseudo-elements (`::before` line + `::after` center dot)
5. Keep `preview.js` unchanged unless divider visibility cannot be solved safely via CSS selectors.
6. Validate parity with visual and overflow checks in product preview, `/i/{token}`, `/pdf/{token}` and one generated PDF sample/theme.
