# TeInvit — Faza 1 implementată: foundation contracts (vertical architecture)

## Obiectiv
Faza 1 introduce doar fundația contractuală pentru arhitectura pe verticale, fără schimbări funcționale în Wedding.

## Ce include Faza 1

1. Contracte shared minime pentru verticale:
- listă verticale suportate (`wedding`, `baptism`, `birthday`)
- normalizare cheie verticală
- default/fallback explicit `wedding`

2. Contract storage family map (pregătit pentru Faza 2):
- wedding -> tabele legacy existente
- baptism -> namespace țintă `{$wpdb->prefix}teinvit_baptism_*`
- birthday -> namespace țintă `{$wpdb->prefix}teinvit_birthday_*`

3. Contract registry module:
- wedding marcat `legacy-safe`
- baptism/birthday marcate `scaffold`

4. Resolver contractual minim:
- token -> verticală
- ordine de rezoluție: invitations.module_key -> order meta snapshot -> order meta fallback -> detecție din order items -> default wedding

5. Snapshot authority foundation la momentul token generated:
- hook pe `teinvit_token_generated`
- persistă verticala în order meta:
  - `_teinvit_vertical_key_snapshot`
  - `_teinvit_vertical_key`

## Ce NU include (intenționat)
- fără flow funcțional nou pentru Baptism/Birthday
- fără routing nou
- fără renderer nou
- fără tabele noi create efectiv
- fără schimbare comportament Wedding

## Motivare backward compatibility
- Wedding rămâne branch implicit/default
- codul runtime existent pentru Wedding nu a fost redirecționat în această fază
- noile contracte sunt foundation-only pentru Faza 2
