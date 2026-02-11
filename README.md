# teinvit-project

TeInvit – Plugin WordPress (**teinvit-core**) + serviciu Node.js Puppeteer pentru generare PDF (**teinvit-pdf**).

Acest repository reprezintă baza oficială de cod pentru TeInvit și oglindește structura din producție.

---

## Componente

### 1) Plugin WordPress (teinvit-core)
Path: `teinvit-core/`  
Entrypoint: `teinvit-core/teinvit-core.php`

Conține logica de randare a invitației și endpoint-urile WordPress utilizate pentru:
- preview produs
- pagină invitați `/i/{token}`
- sursa HTML pentru PDF `/pdf/{token}`

Fișierele de randare (template/CSS/JS) se află în:
`teinvit-core/invitations/wedding/preview/`

---

### 2) Serviciu Node.js PDF (teinvit-pdf)
Path: `teinvit-pdf/`  
Entrypoint: `teinvit-pdf/server.js`

Generează PDF-ul final utilizând Puppeteer prin încărcarea endpoint-ului WordPress:
- `/pdf/{token}`

---

## Flux de randare (JS-first)

Fluxul complet de afișare al invitației este:

- Preview produs (`/preview`)
- Pagina invitați (`/i/{token}`)
- Sursa HTML pentru PDF (`/pdf/{token}`)
- PDF final generat prin Puppeteer (pe baza `/pdf/{token}`)

Randarea este de tip JS-first, iar HTML-ul final stabilizat este cel utilizat pentru generarea PDF-ului.

---

## Reguli ale repository-ului

- Structura folderelor trebuie să rămână identică cu producția (production-mirrored).
- Nu se aplatizează fișierele și nu se redenumesc path-urile (pentru a evita ruperea include-urilor sau a asset-urilor).
- Orice task sau modificare trebuie să specifice clar componenta vizată:
  - `[teinvit-core]` pentru modificări în pluginul WordPress
  - `[teinvit-pdf]` pentru modificări în serviciul Node/Puppeteer
