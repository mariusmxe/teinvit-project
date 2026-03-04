# Plan implementare: link intermediar first-party pentru Waze (`/go/waze`)

## Context confirmat
- În PDF link-urile sunt deja implementate ca annotations cu acțiune standard `/URI` (nu `/Launch`, nu `/JavaScript`).
- Rect-urile clickabile sunt corecte/aliniate pe textul „Deschide în Waze”.
- Warning-urile desktop (Adobe/Chrome extension) sunt în principal politici viewer-side pentru link extern.
- Pe Android (Honor Document service) există cazuri în care link-ul direct din PDF către `waze.com/ul` nu produce nicio acțiune vizibilă.

## Obiectiv (fără schimbare pipeline WP→Node)
1. În sursa HTML `/pdf/{token}` și implicit în PDF-ul generat, înlocuim linkul direct Waze cu link first-party:
   - `https://teinvit.com/go/waze?...`
2. Endpoint-ul WordPress `/go/waze` construiește destinația Waze și face redirect controlat.
3. Menținem fluxul existent WP→Node; schimbăm doar:
   - template/render link în HTML
   - endpoint nou în WP pentru redirect.

## Structură propusă endpoint `/go/waze`

### 1) Contract URL de intrare (first-party)
Variantă recomandată (minimală și robustă):
- `https://teinvit.com/go/waze?token={public_token}&loc={civil|church|party}`

Parametri:
- `token` = identificator eveniment/invitație (același folosit în ecosistemul curent)
- `loc` = cheie locație (enum controlat), pentru a evita coordonate/text sensibile direct în query

Variantă extinsă (dacă e nevoie de analytics sau fallback explicit):
- `https://teinvit.com/go/waze?token={public_token}&loc={civil|church|party}&src=pdf`

### 2) Rezolvare date în backend WP
`/go/waze`:
1. Validează parametrii (`token`, `loc`, eventual `src`).
2. Rezolvă evenimentul pe baza `token`.
3. Extrage destinația pentru `loc` din datele deja existente în WP (adresă + opțional lat/lng).
4. Construiește URL final Waze universal link.

### 3) Construire URL final Waze
Prioritate recomandată:
1. Dacă avem coordonate valide:
   - `https://waze.com/ul?ll={lat},{lng}&navigate=yes&utm_source=teinvit&utm_medium=pdf&utm_campaign=go-waze`
2. Dacă nu avem coordonate, fallback pe query text:
   - `https://waze.com/ul?q={encoded_address_or_name}&navigate=yes&utm_source=teinvit&utm_medium=pdf&utm_campaign=go-waze`

Observații:
- Menținem exclusiv schemă `https`.
- Folosim `waze.com/ul` (universal link), nu `waze://` direct în PDF.
- Parametrii UTM rămân opționali, dar utili pentru măsurare.

### 4) Comportament mobile (deschidere app + fallback)
`/go/waze` răspunde diferit în funcție de context:
- Caz standard (inclusiv desktop și majoritatea mobile viewers):
  - HTTP 302/303 către `https://waze.com/ul?...`
- Caz problematic Android in-app viewer (ex. Honor Document service):
  - Dacă UA semnalează webview/viewer cu comportament restrictiv, endpoint-ul poate servi o pagină intermediară first-party foarte simplă cu:
    - CTA „Deschide în Waze” (către universal link)
    - fallback „Deschide în browser”
    - fallback „Copiază adresa”
  - Opțional, pentru Android, se poate adăuga buton secundar cu `intent://` în pagină (nu în PDF), păstrând universal link drept opțiunea principală.

## Impact asupra PDF (confirmare explicită)
- În PDF, linkul rămâne tot annotation cu acțiune `/URI`.
- Se schimbă doar valoarea URI din `https://waze.com/ul?...` în `https://teinvit.com/go/waze?...`.
- Deci compatibilitatea PDF rămâne aceeași la nivel de standard; controlul logicii se mută first-party pe server.

## Fișiere implicate (la nivel de plan)

### WordPress (endpoint redirect)
- Fișier(e) de routing/init unde se definesc endpoint-uri custom (`/go/waze`).
- Fișier(e) de logică pentru:
  - validare parametri
  - rezolvare eveniment/locație din token
  - construire URL Waze
  - redirect + fallback page (dacă e activată ramura mobile-webview)

### HTML/PDF rendering
- Template-ul/sursa care produce HTML-ul pentru `/pdf/{token}` (acolo unde astăzi se inserează linkurile `waze.com/ul`).
- Funcția/helper-ul de generare URL pentru CTA „Deschide în Waze”.

### Config / observabilitate (opțional)
- loc pentru feature flag (ex: activează pagina intermediară doar pentru anumite UA)
- log minim pentru debug (`token`, `loc`, `ua family`, rezultat redirect)

## Plan de testare cerut (matrice explicită)

### A) Adobe Acrobat Pro 2025.001.21223 (desktop)
Scop:
- Verificăm că click pe link din PDF deschide `teinvit.com/go/waze?...` și apoi redirectează corect la Waze URL.
Pași:
1. Deschidere PDF local.
2. Click pe fiecare CTA „Deschide în Waze”.
3. Confirmare că URL final conține `waze.com/ul` + `navigate=yes`.
4. Confirmare că warning-ul viewer (dacă apare) este cel standard de link extern și nu blocare silențioasă.
Criterii de acceptare:
- Toate linkurile funcționează consistent; nu apar acțiuni non-URI.

### B) Chrome Acrobat extension 26.2.1.2
Scop:
- Verificăm comportamentul prin plugin-ul Acrobat în browser și redirect-ul first-party.
Pași:
1. Deschidere PDF în Chrome cu extension activ.
2. Click pe fiecare link Waze.
3. Confirmare lanț: `teinvit.com/go/waze` -> `waze.com/ul?...`.
4. Verificare că nu există dead click.
Criterii de acceptare:
- Redirectul e consecvent; eventualele confirmări sunt viewer-side, dar fluxul continuă.

### C) Android 10 (MagicOS), Honor Document service 10.0.0.641, Waze instalat
Scop:
- Validăm fixul principal: evitarea situației „nu se întâmplă nimic”.
Pași:
1. Deschidere PDF exact în Honor Document service.
2. Tap pe fiecare link „Deschide în Waze”.
3. Observare rezultat:
   - deschidere directă Waze
   - deschidere intermediar first-party + CTA, apoi Waze
   - fallback browser cu destinația corectă
4. Repetare cu Waze dezinstalat (fallback web/store) dacă posibil.
Criterii de acceptare:
- Nu există „dead tap”; utilizatorul ajunge fie în app, fie într-un fallback clar care duce mai departe.

## Riscuri și limitări
- Warning-urile de securitate pentru linkuri externe nu pot fi garantat eliminate 100% în toate viewer-ele desktop.
- Unele PDF viewers mobile pot limita deep-linking din context embedded; de aceea pagina intermediară first-party e utilă ca plasă de siguranță.
- Detectarea strictă după User-Agent poate fi fragilă; recomandat fallback generic simplu și clar.

## Criterii de done (funcționale)
1. Toate linkurile Waze din HTML/PDF folosesc `https://teinvit.com/go/waze?...`.
2. `/go/waze` construiește determinist URL Waze (coordonate când există, altfel query text) și face redirect valid.
3. În PDF rămân actions `/URI` standard.
4. În mediul Android țintă nu mai există tap fără rezultat (direct sau via fallback intermediar).
