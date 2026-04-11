# TeInvit — Faza 2 implementată: runtime resolver + storage per verticală

## Scope
Faza 2 activează infrastructura reală internă pentru verticale și storage, fără a livra încă flow-uri publice complete pentru Baptism/Birthday.

## Implementat

1. Resolver utilizabil real
- `token -> vertical_key`
- `token -> module contract`
- `token -> storage tables family`
- fallback explicit și backward-safe pe `wedding`

2. Storage family map activat
- familii de tabele pe verticale pentru `baptism` și `birthday` sunt create prin `dbDelta`
- instalarea este integrată în activation hooks + schema migration path

3. Snapshot authority integrat
- verticala snapshot persistată la `teinvit_token_generated` este citită prioritar la rezoluția tokenului
- se evită reinterpretarea riscantă a tokenurilor legacy fără snapshot

4. Legacy boundary clar
- rutele Wedding rămân pe comportamentul actual
- pentru tokenuri non-Wedding se aplică blocare explicită (404) în rutele legacy Wedding până la implementarea completă a fazelor următoare

## Deliberat neimplementat în Faza 2
- payload/renderer/preview/PDF pentru Baptism/Birthday
- /admin-client și /invitati complete pentru noile verticale
- binding ACF final
- orice schimbare funcțională Wedding
