# Reguli — Paritate preview / PDF

## Obiectiv
Preview-ul și PDF-ul final trebuie să fie identice funcțional și cât mai apropiate vizual posibil.

Orice diferență de:
- text;
- ordonare;
- font;
- spacing;
- wrapping;
- poziționare;
- stare de theme;
- randare a câmpurilor dinamice
este considerată problemă reală.

## Reguli canonice
- Preview-ul este JS-first.
- Payload-ul canonic trebuie să fie sursa de adevăr pentru randare.
- `/i/{token}` și `/pdf/{token}` nu trebuie să devină fluxuri divergente.
- PDF-ul trebuie să aștepte semnalele de readiness definite de sistem înainte de randare.

## Cerințe pentru analiză și implementare
La orice task care atinge preview-ul sau PDF-ul, trebuie verificat:
1. de unde vine payload-ul;
2. unde este aplicată tema;
3. unde se face autofit;
4. când este semnalat readiness-ul;
5. dacă există logică separată doar pentru un context;
6. dacă modificarea poate produce regresii în Wedding.

## Reguli de siguranță
- Nu modifica mecanisme stabile de readiness fără justificare clară.
- Nu dubla logica de temă, mapping sau compoziție text dacă există deja una canonică.
- Nu introduce transformări de date diferite între preview și PDF.
