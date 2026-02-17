# Testare migrare pagini custom (/admin-client/{token}, /invitati/{token})

1. **Acces /admin-client/{token} valid/invalid**
   - Valid: autentificare cu user-ul comenzii, deschidere `/admin-client/{token}` => se încarcă formularul.
   - Invalid: token inexistent => 404.

2. **Salvare modificări**
   - Submit formular „Salvează informații” și „Salvează modificări” => datele se persistă în `wp_teinvit_invitations.config`.

3. **Setare versiune activă**
   - Creează snapshot nou („Salvează modificări”), apoi setează din dropdown și confirmă `active_version_id`.

4. **Formular RSVP valid/invalid (telefon)**
   - Valid: `07xxxxxxxx` => 200, RSVP salvat.
   - Invalid: `07123` => 400 `phone_invalid`.

5. **Câmpuri copii, cazare, alergii**
   - Activează toggle-urile în config și verifică afișarea câmpurilor condiționale pe `/invitati/{token}`.

6. **Rezervare cadouri + 409 Conflict**
   - Două sesiuni rezervă același gift simultan: prima 200, a doua 409 `gift_conflict`.

7. **Preview background corect**
   - Verifică faptul că imaginea vine din `modules/wedding/assets/backgrounds/{model_key}.png|jpg`, nu din featured image.

8. **GDPR obligatoriu**
   - Submit fără checkbox GDPR => 400 `gdpr_required`.

9. **Afișare deadline**
   - Activează `show_rsvp_deadline` + text și verifică mesajul pe pagina invitați.

10. **/pdf/{token} neafectat**
   - Acces `/pdf/{token}` și compară output-ul cu comportamentul anterior.
