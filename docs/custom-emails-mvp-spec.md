# TeInvit – WooCommerce → Custom Emails (MVP) – Specificație implementabilă (fără cod)

## 0) Obiectiv și constrângeri MVP

Acest MVP introduce un modul de email tranzacțional + marketing în ecosistemul TeInvit, cu următoarele decizii obligatorii:

1. Trimiterea emailurilor se face prin sistemul WooCommerce Emails (clase `WC_Email`), astfel încât emailurile să fie vizibile în `WooCommerce → Settings → Emails`.
2. Tracking implementat din start: **open** + **click**.
3. Campaniile către invitați (Guests) se trimit **doar** dacă:
   - există email valid, și
   - la ultima confirmare RSVP au `marketing_consent=1`.
4. Securitate: fără dependențe externe obligatorii; queue locală (WP-Cron / Action Scheduler).
5. Scope MVP: primele 3 emailuri definite mai jos.

---

## 1) MVP – primele 3 emailuri (obligatorii)

### A) Trigger: Token generated → Email către client (miri)

- **Eveniment declanșator:** `do_action('teinvit_token_generated', $order_id, $token)`.
- **Audience:** Customer (owner comanda).
- **Recipient resolution (ordine):**
  1. `billing_email` din comandă,
  2. fallback la email user asociat comenzii.
- **Conținut minim:**
  - CTA principal: „Administrare invitație” → `/admin-client/{token}`
  - CTA secundar (opțional): „Pagina invitaților” → `/invitati/{token}`
  - text scurt capabilități: editare, publicare, RSVP, cadouri, raport.
- **Tip:** tranzacțional (nu necesită consimțământ marketing).

### B) Trigger: RSVP received → Email către client „Confirmare nouă primită”

- **Eveniment declanșator:** după persistența RSVP în DB.
- **Audience:** Customer.
- **Conținut minim:**
  - Rezumat RSVP: nume, telefon, nr adulți, copii, civil/religios/petrecere, cazare, vegetarian + nr meniuri, alergii, mesaj.
  - Link către raport invitați din `/admin-client/{token}` (ideal anchor către zona raport).
- **Regulă anti-spam / dedupe (default propus):**
  - Se trimite email la fiecare submit RSVP **care schimbă datele relevante** față de ultima înregistrare pentru aceeași pereche `(token, phone_normalized)`.
  - Dacă submit-ul este duplicat (aceleași valori semnificative în fereastră 10 minute), nu se trimite.
- **Justificare:**
  - păstrăm notificare aproape real-time pentru modificări reale,
  - evităm avalanșă de emailuri din refresh/retrimiteri accidentale.

### C) Trigger: Invitati marketing consent #1 → Email invitaților la 24h

- **Eveniment declanșator:** RSVP salvat.
- **Delay propus (MVP):** `24h` după submit.
- **Audience:** Guests eligibili per RSVP.
- **Eligibilitate strictă:**
  - `email` existent + valid,
  - `marketing_consent=1` la **ultima** confirmare pentru acel invitat,
  - nu este în suppression list,
  - respectă rate limit.
- **Conținut minim:**
  - inspirație / „cum funcționează invitațiile digitale”
  - CTA către pagină modele / magazin
  - „de ce primești email” (ai bifat acordul în formular)
  - link obligatoriu de unsubscribe.
- **Tip:** marketing (consimțământ obligatoriu).

---

## 2) UI exact în WP Admin

## 2.1 Intrare meniu

- Meniu principal: `WooCommerce → Custom Emails`.
- Submeniuri MVP:
  1. `All Emails`
  2. `New Email`
  3. `Logs`
  4. `Unsubscribes / Suppression` (**inclus în MVP**, necesar conformitate marketing)

## 2.2 All Emails (listă)

Coloane:
- Name (intern)
- Trigger
- Audience
- Status (`Draft` / `Active`)
- Delay
- Last modified
- Sent (30d)
- Open rate (30d)
- Click rate (30d)
- Actions: Edit / Duplicate / Disable

Filtre:
- Status
- Trigger
- Audience

## 2.3 New Email (create/edit) – câmpuri exacte

### A) Basic
- `Internal Name` (text, obligatoriu)
- `Status` (Draft / Active)
- `Subject` (text, obligatoriu)
- `Preheader` (text, opțional)
- `Heading` (text, obligatoriu)
- `Email Type` (MVP: HTML only)

### B) Trigger
- `Trigger` (dropdown):
  - Token generated
  - RSVP received
  - Guest consent #1 (24h)
- `Delay`:
  - numeric + unități (minutes/hours/days)
  - default pentru Guest consent #1 = 24h
- `Schedule Mode`:
  - Immediate (0 delay)
  - Delayed

### C) Audience
- `Audience Type`:
  - Customer
  - Guests
- `Rules`:
  - `Require valid email` (default ON, locked ON pentru MVP)
  - `Require marketing consent` (default:
    - OFF pentru Customer transactional,
    - ON + locked pentru Guests marketing)

### D) Content Builder (blocuri minime)
- Logo (optional)
- Banner (image + alt)
- Title (H1/H2)
- Text (rich text simplu)
- Button (label + URL)
- Divider
- Footer (include legal text/unsubscribe placeholders)

### E) Merge Tags (MVP)

#### Globale / customer
- `{site_name}`
- `{order_number}`
- `{order_id}`
- `{token}`
- `{admin_client_url}`
- `{invitati_url}`
- `{report_url}`
- `{customer_first_name}`
- `{customer_last_name}`

#### RSVP/guest context
- `{guest_name}`
- `{guest_phone}`
- `{guest_email}`
- `{rsvp_adults}`
- `{rsvp_children}`
- `{rsvp_attending_civil}`
- `{rsvp_attending_religious}`
- `{rsvp_attending_party}`
- `{rsvp_accommodation}`
- `{rsvp_vegetarian}`
- `{rsvp_vegetarian_menus}`
- `{rsvp_allergies}`
- `{rsvp_message}`

#### Marketing/legal
- `{unsubscribe_url}` (obligatoriu pentru marketing)
- `{why_received_text}`

### F) Rules / Safety
- `Rate limit per template+recipient`: default `max 2 / 7 zile`
- `Dedupe window`: default `10 minute`
- `Deduplication key`:
  - Customer emails: `(template_id, token, recipient_email, semantic_hash)`
  - Guest emails: `(template_id, recipient_email, campaign_bucket)`

### G) Preview + Test
- Preview desktop/mobile
- Select context sample (order/token/rsvp)
- `Send test email` (către adresă introdusă manual)
- Afișare merge tags nerecunoscute (warning)

---

## 3) Trigger hooks și integrare cu arhitectura TeInvit

### 3.1 Token generated (A)

- Hook existent: `do_action('teinvit_token_generated', $order_id, $token)`.
- Listener email engine:
  - construiește payload context,
  - rezolvă recipient customer,
  - creează job send (imediat),
  - loghează în `teinvit_email_sends`.

### 3.2 RSVP received (B)

- În MVP se standardizează un hook explicit după write DB:
  - `do_action('teinvit_rsvp_saved', $token, $rsvp_id, $payload)`
- Listener email engine:
  - normalizează telefon/email,
  - calculează `semantic_hash` RSVP,
  - aplică dedupe/reguli,
  - trimite notificare customer dacă e eligibil.

### 3.3 Guest consent #1 (C, +24h)

- Sursă trigger: același moment `teinvit_rsvp_saved`.
- Scheduling local:
  - recomandat `Action Scheduler` (dacă WooCommerce disponibil, robust pentru retry/queue),
  - fallback `WP-Cron` dacă Action Scheduler indisponibil.
- Job delayed (24h):
  - recitește „ultima stare RSVP” pentru invitat înainte de trimitere,
  - verifică din nou `email valid + marketing_consent=1 + !suppressed + rate-limit`.

### 3.4 Identificări cheie

- `token` client/comandă:
  - din trigger (`teinvit_token_generated`) sau din RSVP payload, apoi mapat la comandă.
- email client:
  - `billing_email` din order, fallback user email.
- listă invitați eligibili:
  - din RSVP rows per token,
  - se ia **ultima confirmare per identity key** (`phone_normalized` sau fallback `email_normalized`),
  - filtru final: email valid + consent=1 + nesuprimat.

---

## 4) Tracking Open + Click (structură exactă)

## 4.1 Open tracking

- Pixel endpoint (query var):
  - `/?teinvit_open={send_id}&sig={signature}`
- Mecanism:
  - inserare pixel 1x1 în HTML email,
  - la request valid: log event `open` (dedupe per 15 minute pe același send),
  - răspuns imagine transparentă.

## 4.2 Click tracking

- Redirect endpoint:
  - `/?teinvit_click={send_id}&u={encoded_url}&sig={signature}`
- Mecanism:
  - toate linkurile trackable se rescriu prin endpoint,
  - validare semnătură,
  - log event `click`,
  - redirect `302` către URL destinație.

## 4.3 send_id și semnătură

- `send_id`: UUIDv4 generat la momentul creării send record.
- `sig`: HMAC (secret server) peste parametrii critici (`send_id`, `u`, timestamp opțional) pentru a preveni event spoofing.

## 4.4 Ce stocăm (GDPR-aware)

- în loguri events:
  - `event_type` (open/click),
  - timestamp,
  - `ip_hash` (SHA-256 + salt rotativ),
  - `ua_hash` (SHA-256),
  - `url` (doar pentru click, fără parametri sensibili dacă e posibil).
- nu stocăm IP raw în MVP implicit.

## 4.5 Logs UI (MVP)

- listă sends:
  - queued/sent/failed,
  - opens count,
  - clicks count,
  - first_open_at / last_open_at,
  - first_click_at / last_click_at.
- drill-down pe send:
  - timeline evenimente + metadata hash.

---

## 5) Tabele / stocare (exact)

## 5.1 `wp_teinvit_email_sends`

Scop: log de trimitere + stare operațională.

Câmpuri MVP:
- `id` BIGINT UNSIGNED PK AI
- `send_id` CHAR(36) NOT NULL UNIQUE
- `template_id` BIGINT UNSIGNED NOT NULL
- `trigger_key` VARCHAR(64) NOT NULL
- `audience_type` VARCHAR(32) NOT NULL (`customer`/`guest`)
- `token` VARCHAR(191) NULL
- `order_id` BIGINT UNSIGNED NULL
- `rsvp_id` BIGINT UNSIGNED NULL
- `recipient_email` VARCHAR(191) NOT NULL
- `recipient_hash` CHAR(64) NOT NULL
- `subject_rendered` VARCHAR(255) NOT NULL
- `body_rendered_hash` CHAR(64) NOT NULL
- `status` VARCHAR(16) NOT NULL (`queued`/`sent`/`failed`/`suppressed`/`skipped`)
- `error_code` VARCHAR(64) NULL
- `error_message` TEXT NULL
- `scheduled_at` DATETIME NULL
- `sent_at` DATETIME NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Indexuri minimale:
- UNIQUE (`send_id`)
- INDEX (`template_id`, `status`)
- INDEX (`recipient_email`)
- INDEX (`token`)
- INDEX (`order_id`)
- INDEX (`scheduled_at`, `status`)

## 5.2 `wp_teinvit_email_events`

Scop: tracking opens/clicks.

Câmpuri MVP:
- `id` BIGINT UNSIGNED PK AI
- `send_id` CHAR(36) NOT NULL
- `event_type` VARCHAR(16) NOT NULL (`open`/`click`)
- `event_at` DATETIME NOT NULL
- `ip_hash` CHAR(64) NULL
- `ua_hash` CHAR(64) NULL
- `url` TEXT NULL
- `meta_json` LONGTEXT NULL

Indexuri minimale:
- INDEX (`send_id`, `event_type`)
- INDEX (`event_at`)

## 5.3 `wp_teinvit_email_suppression` (MVP recomandat obligatoriu)

Scop: unsubscribe/suppression global marketing.

Câmpuri:
- `id` BIGINT UNSIGNED PK AI
- `email` VARCHAR(191) NOT NULL
- `email_hash` CHAR(64) NOT NULL
- `scope` VARCHAR(32) NOT NULL (`marketing`)
- `reason` VARCHAR(64) NOT NULL (`unsubscribe_link`, `admin_manual`, `bounce_future`)
- `source_send_id` CHAR(36) NULL
- `created_at` DATETIME NOT NULL

Indexuri:
- UNIQUE (`email`, `scope`)
- INDEX (`email_hash`)

---

## 6) GDPR / marketing compliance

1. Regula strictă: emailuri marketing către invitați doar cu `marketing_consent=1` la ultima confirmare.
2. Unsubscribe obligatoriu în emailurile marketing:
   - link semnat `{unsubscribe_url}`,
   - la click: adăugare în `suppression`, confirmare pe ecran.
3. „De ce primești acest email” inclus în footer marketing.
4. Retenție date (MVP):
   - `email_events`: 12 luni implicit (opțiune 24 luni în setări),
   - `email_sends`: 24 luni pentru audit operațional,
   - `suppression`: păstrat până la revocare manuală (interes legitim de conformitate).
5. Date minimizate:
   - hash pentru IP/UA,
   - fără stocare date inutile.

---

## 7) Flux operațional de trimitere (MVP)

1. Trigger intern TeInvit/WC.
2. Resolve template activ pentru trigger + audience.
3. Build context + merge tags.
4. Safety gate:
   - valid email,
   - consent (dacă marketing),
   - suppression check,
   - dedupe + rate-limit.
5. Insert `queued` în `email_sends`.
6. Send prin WC_Email.
7. Update `sent` / `failed`.
8. Tracking events populate `email_events`.

---

## 8) Acceptance criteria (test plan obligatoriu)

## 8.1 Email A – Token generated către customer

- **Cum declanșez:** comandă eligibilă → completed → token generat.
- **Așteptat:**
  - se creează `send` cu `trigger_key=token_generated`, `audience=customer`, status `sent`.
  - email conține `{admin_client_url}` valid; optional `{invitati_url}`.
- **Tracking:**
  - open pixel produce event `open` pentru `send_id`.
  - click pe CTA produce event `click` + redirect corect.

## 8.2 Email B – RSVP received către customer

- **Cum declanșez:** submit RSVP valid.
- **Așteptat:**
  - email cu rezumat RSVP complet.
  - link raport din admin-client inclus.
- **Dedupe tests:**
  - retransmitere identică în <10 min ⇒ nu trimite al doilea email (`skipped`).
  - modificare reală (ex. nr adulți) ⇒ trimite email nou.
- **Tracking:** open + click logate.

## 8.3 Email C – Guest consent #1 marketing (24h)

- **Cum declanșez:** RSVP cu email + consent=1; rulează scheduler după 24h.
- **Așteptat:**
  - trimite doar dacă invitat eligibil la momentul execuției jobului.
  - include CTA magazin + unsubscribe + why_received.

### Teste eligibilitate obligatorii

1. Invitat fără email → **nu primește** (`skipped_no_email`).
2. Invitat cu email + `marketing_consent=0` → **nu primește** (`skipped_no_consent`).
3. Invitat cu email + `marketing_consent=1` → **primește** (`sent`).
4. Invitat suppressat (unsubscribe) → **nu primește** (`suppressed`).

### Tracking + unsubscribe

- Open event logat la deschidere.
- Click event logat + redirect corect către URL final.
- Click unsubscribe:
  - adaugă email în suppression,
  - următoarele campanii marketing către acel email sunt blocate.

---

## 9) Ce NU intră în MVP (explicit)

- Editor drag-and-drop avansat.
- Segmentări complexe multi-condiție (dincolo de consent/email/rate-limit).
- Bounce/complaint webhooks externe.
- A/B testing.
- Analytics BI avansat (doar logs operaționale).

---

## 10) Definition of Done (MVP)

MVP este „Done” când:
1. Cele 3 triggere trimit corect prin WooCommerce Emails.
2. `Custom Emails` UI există cu submeniurile definite.
3. Tracking open/click funcționează end-to-end și este vizibil în Logs.
4. Marketing guests respectă strict `email valid + consent=1 + !suppressed`.
5. Unsubscribe funcționează și blochează trimiteri viitoare marketing.
6. Acceptance tests din secțiunea 8 trec complet.
