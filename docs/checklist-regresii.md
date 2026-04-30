# Checklist de regresii — TeInvit

Acest checklist trebuie parcurs pentru orice task sensibil din proiect.

## 1. Preview / PDF
- Preview-ul încă afișează toate datele corect?
- `/i/{token}` rămâne coerent cu preview-ul?
- `/pdf/{token}` rămâne coerent cu preview-ul?
- PDF-ul final păstrează aceeași logică de randare?
- Tema, fonturile, spacing-ul și wrapping-ul rămân stabile?

## 2. Admin-client / Invitati
- Save funcționează fără pierdere de date?
- Publish setează corect varianta activă?
- `/invitati/{token}` citește exclusiv varianta activă?
- Gating-ul pe produs și capabilități rămâne corect?

## 3. WAPF / APF
- Câmpurile se prepopulează corect?
- Conditionals rămân funcționale?
- Parent/child logic rămâne coerentă după Save?

## 4. Produse și addon-uri
- Basic / Premium / addon-urile rămân corect clasificate?
- Eligibilitatea și capabilitățile se comportă conform produsului cumpărat?
- Cadourile și sloturile se calculează corect?

## 5. Verticalizare / DB
- Resolver-ul returnează verticala corectă?
- Scrierea și citirea merg în familia de tabele corectă?
- Wedding rămâne backward-safe?

## 6. Emailuri și automatizări
- Trigger-ele corecte pornesc?
- Se aplică filtrarea pe product_ids / consent / suppression?
- Logs explică corect statusul fiecărui email?

## 7. Observabilitate
- Există logs utile pentru debugging?
- Erorile importante sunt detectabile?
- Există semnale clare pentru skipped / failed / invalid state?
