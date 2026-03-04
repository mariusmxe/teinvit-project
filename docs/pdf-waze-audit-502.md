# Audit PDF Waze links – Invitatie-Nunta-Model-floral-elegant - 502.pdf

## Fișier auditat
- Path confirmat: `/workspace/teinvit-project/docs/Invitatie-Nunta-Model-floral-elegant - 502.pdf`
- Dimensiune: `2300663` bytes
- Header PDF: `%PDF-1.4`
- Marker EOF prezent: `%%EOF`
- Număr pagini: `1`

## Metodă
Audit făcut cu scripturi Python (`pypdf`) direct pe obiectele PDF: catalog (`/Root`), pagini, annotations (`/Annots`) și acțiuni (`/A`, `/AA`).

## Rezultate cheie

### 1) Acțiuni și securitate
- `/OpenAction` la nivel document: `None`.
- `/AA` la nivel document: `None`.
- `/AA` la nivel pagină: absent.
- Total link annotations: `3`.
- Tip acțiune pentru toate link-urile: `/URI`.
- Nu s-au găsit acțiuni `/Launch`, `/JavaScript` sau alte tipuri non-URI.

### 2) Rect-uri clickabile
Link-urile au următoarele rect-uri (`[x1, y1, x2, y2]`):
- `[90.75, 125.459961, 173.25, 138.959961]`
- `[228.75, 96.209961, 311.25, 109.709961]`
- `[168.75, 26.459961, 251.25, 39.959961]`

Textul „Deschide în Waze” există de 3 ori pe pagină. Coordonatele textului (după transformarea matricei de text în coordonate pagină) corespund cu rect-urile de mai sus, deci zonele clickabile sunt aliniate peste text.

### 3) URL-uri extrase
Toate URL-urile externe din PDF sunt Waze și au schemă HTTPS:
1. `https://waze.com/ul/hsxfsbnfee?q=Starea+Civila+Sector+6&navigate=yes&utm_source=teinvit`
2. `https://waze.com/ul/hsxfsbnfee?q=Biserica+Adormirea+Maicii+Domnului&navigate=yes&utm_source=teinvit`
3. `https://waze.com/ul/hsxfsbnfee?q=Restaurant+Calipso&navigate=yes&utm_source=teinvit`

Observații:
- Format folosit: `https://waze.com/ul/...` (universal link).
- Nu există `waze://`.
- Nu există URL fără schemă.
- Nu există alte domenii externe în annotations.

## Concluzie tehnică
- PDF-ul este valid structural și link-urile sunt implementate corect ca `/URI` standard.
- Warning-urile Adobe/Chrome la deschiderea linkurilor externe sunt, în practică, politici de viewer/client și nu pot fi eliminate 100% doar din authoring PDF atunci când destinația este externă.
- Pentru problema mobilă „nu se întâmplă nimic”, cauza principală probabilă este viewer-ul (in-app viewers sau PDF renderers care nu forward-ează universal links), nu formatul linkului sau un rect evident greșit.
