# Faza 2 — fix de închidere: storage dispatch pentru seed

## Problema
`teinvit_seed_invitation_if_missing()` scria în legacy pentru toate verticale deoarece folosea direct `teinvit_db_tables()`.

## Fix aplicat
- seed path-ul rezolvă verticala și selectează family tables corecte:
  - `wedding` -> legacy
  - `baptism` -> `teinvit_baptism_*`
  - `birthday` -> `teinvit_birthday_*`
- verificarea existenței tokenului se face în familia verticalei rezolvate.
- insert-ul de `versions` și `invitations` este coerent în aceeași family.
- fallback backward-safe:
  - dacă resolverul returnează wedding, dar order-ul indică baptism/birthday, seed-ul poate folosi verticala din order pentru a evita scrierea greșită în legacy la comenzi noi.

## Scope limitat
- nu activează Faza 3
- nu schimbă renderer/payload/preview/PDF pentru verticale noi
- nu schimbă flow-urile Wedding
