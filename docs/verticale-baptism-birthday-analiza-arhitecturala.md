# TeInvit — Analiză arhitecturală finală pentru verticale noi (Botez + Zi de naștere)

Data: 2026-04-08
Scope: analiză repo + APF/ACF furnizate (fără implementare cod)

## Verdict executiv

Direcția validată: **Wedding rămâne canonical/stabil; Botez + Birthday se dezvoltă pe module noi, cu storage separat pe verticală, infrastructură shared minimă și resolver global minim token→verticală→modul→storage map**.

Această direcție este compatibilă cu starea actuală a repo-ului și minimizează riscul de regresie pe contractele Wedding.

## Constatări cheie (stare actuală repo)

1. Sistemul este doar parțial modularizat; runtime-ul real încă încarcă doar Wedding.
2. Routing public (`/i/{token}`, `/pdf/{token}`) este hard-legat de renderer-ul Wedding.
3. Seed/payload canonical este wedding-centric (WAPF IDs Wedding, semantică miri/civil/religios/petrecere).
4. Storage-ul curent e comun (familii unice `teinvit_*`), nu separat pe verticală.
5. RSVP/Gifts/Reports/Merge Tags/Email context sunt wedding-centric semantic.
6. Există deja fundație bună pentru catalog produse per verticală în Woo (Custom Products), reutilizabilă.

## APF/ACF — concluzii pentru noile verticale

- APF Botez și Birthday descriu bine datele de invitație pe produs (entități + condiționalități + evenimente).
- ACF exportat conține structuri pentru Admin-client/RSVP Botez + Birthday, dar:
  - toate grupurile sunt legate la `post_type = post` (nu la tokenized invitation context),
  - multe câmpuri au `allow_in_bindings = 0`,
  - deci exportul este structural, nu binding runtime gata de folosit în rutele tokenizate.

Concluzie: ACF poate fi folosit ca sursă declarativă, dar necesită strat explicit de binding token+verticală.

## Model semantic final recomandat

### Botez
- Entitate principală: copil/copii (1..3)
- Entități opționale: părinți (0..2), nași (0..2)
- Evenimente: ceremonie religioasă (0..1), petrecere (0..1)
- Fără cununie civilă
- Mesaj RSVP: către familie

### Birthday
- Entitate principală: sărbătorit/sărbătoriți (1..4)
- Fără părinți în invitație
- Evenimente: petrecere (0..1)
- Mesaj RSVP: către sărbătorit/sărbătoriți

## Gifts — verdict ferm

Se recomandă păstrarea logicii mature existente de gifts (slots, locking, reserve, addon dependencies, reporting) și **replicarea ei pe verticale**, nu mutarea logicii în repeater ACF de cadouri.

Motiv: logica actuală include constrângeri tranzacționale și lifecycle (publicare/lock/rezervare/rollback/raportare) care depășesc rolul unui simplu model de câmpuri ACF.

## Arhitectură finală țintă

1. **Shared infrastructure minimă**
   - securitate, token issuance, hook-uri Woo, email engine, integrații, utilitare.
2. **Global registry/resolver minim**
   - `token -> vertical_key -> module contract + storage namespace`.
3. **Per-vertical modules**
   - `modules/wedding`, `modules/baptism`, `modules/birthday`.
4. **Per-vertical storage families (aceeași DB WP)**
   - tabele separate pe verticală pentru invitations/versions/rsvp/gifts (+ eventual view-uri unificate read-only pentru rapoarte comune).

## De ce această variantă de storage

- Mai bună decât `set comun + vertical_key`:
  - reduce coupling semantic, indexuri și migrații mai curate per verticală,
  - scade risc de regresie pe Wedding.
- Mai bună decât DB-uri separate:
  - simplifică operațional (backup, deploy, access control, Woo hooks),
  - păstrează coeziunea în aceeași instalație WP/Woo.

## Roadmap în faze (paralel Botez + Birthday)

1. Faza 1 — contracte shared
2. Faza 2 — resolver + storage per verticală
3. Faza 3 — bootstrap module Baptism + Birthday
4. Faza 4 — payload + renderer + preview + PDF
5. Faza 5 — /admin-client + /invitati + RSVP + reports + gifts
6. Faza 6 — regression safety Wedding
7. Faza 7 — readiness verticale viitoare

