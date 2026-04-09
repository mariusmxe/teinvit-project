# Faza 2 — investigație order 885 / token 885-3fa7077c4e369fc41cc0

## Verdict
Implementarea actuală este **parțială/incompletă față de definiția strictă Faza 2 pentru write path non-Wedding**.

- Storage families Baptism/Birthday sunt create corect.
- Resolver contract există.
- Dar write path-ul real de seed continuă să scrie în legacy (`teinvit_db_tables()`), nu în family per verticală.

## Cauza principală
`teinvit_seed_invitation_if_missing()` scrie hard pe tabelele legacy returnate de `teinvit_db_tables()`, indiferent de verticală.

`module_key` este setat din resolver, dar **doar ca metadată în rândul legacy**, nu ca dispatch de storage family.

## Implicație pentru cazul 885
Dacă seed-ul s-a executat, rândul apare în `wp8k_teinvit_invitations`.
Acest comportament explică rezultatul observat (legacy populated, family baptism empty).

## Ce trebuie corectat pentru închiderea Fazei 2
1. Introducerea unui storage dispatch real pentru operațiile de seed/snapshot (invitations + versions) pe baza verticalei rezolvate.
2. Menținerea Wedding pe storage legacy explicit.
3. Folosirea family tables pentru non-Wedding în fluxurile noi interne, fără a expune UX incomplet.
