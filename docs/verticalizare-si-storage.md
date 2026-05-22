# Reguli — Verticalizare, resolver și storage

## Context
Wedding rămâne verticala canonică și stabilă.
Baptism și Birthday trebuie dezvoltate pe arhitectură extensibilă, dar fără a destabiliza Wedding.

## Principii
- Resolver-ul trebuie să ducă determinist de la token la verticală, modul și storage family.
- Storage-ul pe verticale trebuie respectat atât la seed, cât și la read/write.
- Nu este acceptată scrierea accidentală în tabele legacy pentru verticale non-Wedding.
- Backward compatibility pentru Wedding este obligatorie.

## Așteptări de la Codex
La orice task care atinge resolver-ul sau DB dispatch-ul, verifică explicit:
1. de unde se rezolvă verticala;
2. ce fallback-uri există;
3. în ce tabele se scrie;
4. din ce tabele se citește;
5. dacă există migrare implicită sau fallback periculos;
6. dacă Wedding rămâne neatins funcțional.

## Ce trebuie evitat
- fallback silențios către tabele legacy pentru non-Wedding;
- citire dintr-o familie de tabele și scriere în alta;
- hardcodări de verticală acolo unde există deja resolver;
- introducerea de comportamente noi fără criterii clare de eligibilitate.
