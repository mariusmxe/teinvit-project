# Reguli — /admin-client/{token} și /invitati/{token}

## /admin-client/{token}
Această pagină este zona în care clientul își poate administra invitația în limitele produsului cumpărat.

### Reguli de bază
- Randarea se face în shell WordPress real.
- Preview-ul trebuie să folosească renderer-ul și payload-ul canonical.
- Câmpurile editabile trebuie să reflecte exact datele reale din comandă sau din snapshot-ul activ/selectat.
- Salvarea creează versiune nouă când fluxul cere versionare.
- Publicarea stabilește varianta activă pentru pagina invitaților.

### Ce nu este acceptat
- prefill parțial sau inconsistent;
- checkbox-uri parent nealiniate cu starea copilului;
- preview rupt după Save;
- logică separată care produce diferențe față de `/i` sau `/pdf`.

## /invitati/{token}
Această pagină este sursa publică pentru invitați.

### Reguli de bază
- Afișează exclusiv varianta publicată/activă.
- Câmpurile afișate trebuie să respecte setările și publicarea făcute de client.
- RSVP, lista de cadouri și alte elemente publice trebuie să fie aliniate cu capabilitățile produsului și cu datele active.

### Propagare obligatorie
Fluxul corect este:
- clientul editează în admin-client;
- salvarea creează snapshot/versiune nouă;
- publicarea setează varianta activă;
- `/invitati/{token}` citește doar varianta activă.

## Verificări obligatorii pentru Codex
La orice task pe aceste pagini, verifică explicit:
1. sursa datelor afișate;
2. traseul Save;
3. traseul Publish;
4. sursa folosită de `/invitati/{token}`;
5. dacă există gating pe produs sau capabilități;
6. dacă există risc de regresie în Wedding.
