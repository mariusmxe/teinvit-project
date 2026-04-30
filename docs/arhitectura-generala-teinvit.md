# Arhitectură generală — TeInvit

## Obiectiv
TeInvit este o platformă pentru invitații digitale construită pe WordPress + WooCommerce, unde datele colectate de la client trebuie să circule coerent până la:
- preview-ul invitației;
- pagina publică pentru invitați;
- pagina de administrare a clientului;
- PDF-ul final;
- fluxurile conexe: RSVP, cadouri, emailuri, rapoarte.

## Principii canonice
- WordPress este orchestratorul principal pentru business logic.
- Node/Puppeteer are rol dedicat de randare PDF.
- Wedding este verticala canonică și stabilă.
- Orice extindere pentru Baptism și Birthday trebuie să respecte regulile de stabilitate ale Wedding.
- `preview.js` și payload-ul canonic trebuie folosite coerent în toate mediile relevante.
- Nu se acceptă dublarea logicii dacă există deja un flux canonic corect.

## Rute importante
### Rute considerate stabile
- `/i/{token}` — preview/public preview stabil
- `/pdf/{token}` — HTML pentru randare PDF

### Rute gestionate în shell WordPress
- `/admin-client/{token}`
- `/invitati/{token}`

## Principiu operațional
Single source of truth este obligatoriu.

Ce completează clientul la checkout, ce se salvează în comanda WooCommerce și ce se publică ulterior trebuie să poată fi urmărit coerent, fără reconstrucții manuale fragile.

## Ce trebuie evitat
- logică business mutată în Node;
- hardcodări ascunse care rup eligibilitatea sau propagarea;
- fallback-uri ambigue către tabele/versiuni legacy;
- soluții paralele pentru aceleași date;
- refactorizări creative în zone stabile, fără cerință clară.
