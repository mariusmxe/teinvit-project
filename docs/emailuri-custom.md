# Reguli — Custom Emails în TeInvit

## Obiectiv
Sistemul de emailuri custom trebuie să permită:
- creare reală de template-uri noi;
- apariția lor în zona de administrare relevantă;
- trimiterea lor reală la runtime, pe baza triggerelor și eligibilității;
- tracking, suppression și logs coerente.

## Principii
- Template-urile active trebuie tratate ca entități reale, nu ca simple variante UI.
- Trigger-ele trebuie să poată face fan-out către toate template-urile active compatibile.
- Product scoping, consent, suppression, dedupe și rate-limit trebuie aplicate explicit și auditabil.
- Preview-ul și send test trebuie să folosească exact template-ul vizat, nu fallback-uri ambigue.

## Așteptări de la Codex
La orice task pe emailuri custom, verifică explicit:
1. unde este single source of truth pentru template-uri;
2. cum se creează template_id;
3. dacă un template nou apare corect în listă și în zona de settings relevantă;
4. dacă runtime trimite doar template-uri hardcodate sau face fan-out corect;
5. cum se aplică filtrarea pe products / audience / consent / suppression;
6. dacă logs explică de ce un email a fost trimis, queued, skipped sau failed.

## Rezultatul dorit
- create mode real;
- template-uri noi vizibile și administrabile;
- runtime fan-out real;
- logs și tracking coerente;
- fără regresii pentru template-urile MVP deja existente.
