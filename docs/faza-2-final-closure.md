# Faza 2 — închidere finală (fix versions residual write)

## Problema rămasă
După dispatch fix pe seed, `invitations` era izolat corect pe familii non-Wedding, dar apăreau încă inserări reziduale în `legacy versions` pentru tokenuri non-Wedding.

## Cauză
Existau încă write/read path-uri care foloseau implicit tabele legacy `versions` în fluxul Wedding custom-pages/database helpers.

## Fix minim aplicat
- Dispatch pentru snapshot read (`get_versions_for_token`, `get_active_snapshot`) pe family rezolvată din token.
- În `ensure_active_snapshot_payload`, inserarea fallback de `versions` folosește family tables rezolvate din token.
- La seed fallback din order pentru non-Wedding se persistă meta snapshot vertical, astfel încât resolverul să nu mai cadă pe legacy în același request.
- Migrarea legacy->modular este blocată pentru tokenuri non-Wedding.

## Scope
- fără Faza 3
- fără schimbări renderer/payload/preview/PDF
- fără schimbări funcționale Wedding
