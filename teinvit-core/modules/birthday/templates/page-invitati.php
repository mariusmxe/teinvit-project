<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$token = get_query_var( 'teinvit_invitati_token' );
$token = sanitize_text_field( (string) $token );
$inv = function_exists( 'teinvit_get_invitation_record' ) ? teinvit_get_invitation_record( $token, 'birthday' ) : null;
if ( ! is_array( $inv ) ) {
    echo '<p>Invitația nu a fost găsită.</p>';
    return;
}

$config = function_exists( 'teinvit_birthday_config_with_defaults' )
    ? teinvit_birthday_config_with_defaults( is_array( $inv['config'] ?? null ) ? $inv['config'] : [] )
    : wp_parse_args( is_array( $inv['config'] ?? null ) ? $inv['config'] : [], function_exists( 'teinvit_default_rsvp_config_for_vertical' ) ? teinvit_default_rsvp_config_for_vertical( 'birthday' ) : [] );

$deadline_active = ! empty( $config['show_rsvp_deadline'] );
$deadline_raw = (string) ( $config['rsvp_deadline_date'] ?? '' );
$deadline_ts = 0;
if ( $deadline_raw !== '' && preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $deadline_raw, $m ) ) {
    $deadline_ts = strtotime( $m[3] . '-' . $m[2] . '-' . $m[1] . ' 23:59:59' );
}
$deadline_expired = $deadline_active && $deadline_ts > 0 && time() > $deadline_ts;

$show_theme = ! empty( $config['show_birthday_party_theme'] ) || ! empty( $config['birthday_show_party_theme'] );
$theme_text = trim( (string) ( $config['birthday_party_theme_text'] ?? '' ) );
$show_dress = ! empty( $config['show_birthday_dress_code'] ) || ! empty( $config['birthday_show_dress_code'] );
$dress_text = trim( (string) ( $config['birthday_dress_code_text'] ?? '' ) );
$show_party = ! empty( $config['show_attending_party'] );
$show_guest_count = isset( $config['show_guest_count'] ) ? ! empty( $config['show_guest_count'] ) : ! empty( $config['show_attending_people_count'] );
$show_kids = ! empty( $config['show_kids'] );
$show_child_menu = ! empty( $config['show_child_menu'] );
$show_accommodation = ! empty( $config['show_accommodation'] );
$show_vegetarian = ! empty( $config['show_vegetarian'] );
$show_allergies = ! empty( $config['show_allergies'] );
$show_message = isset( $config['show_message'] ) ? ! empty( $config['show_message'] ) : true;
$show_special_observations = ! empty( $config['show_special_observations'] );

$policy_url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
$terms_page = get_page_by_path( 'termeni-si-conditii', OBJECT, 'page' );
$terms_url = $terms_page instanceof WP_Post ? get_permalink( $terms_page ) : '';
?>
<style>
.teinvit-birthday-invitati{max-width:980px;margin:0 auto}.teinvit-surface-card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:18px}.teinvit-rsvp-card{margin-top:16px}.teinvit-info-strip{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:14px}.teinvit-info-pill{border:1px solid rgba(0,0,0,.1);border-radius:8px;padding:10px;background:#fafafa}.teinvit-rsvp-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 16px}.teinvit-rsvp-field label,.teinvit-rsvp-field input,.teinvit-rsvp-field textarea{display:block;width:100%}.teinvit-rsvp-question{margin-bottom:14px}.teinvit-rsvp-choice-group{margin-top:6px}.teinvit-rsvp-choice-group label{display:block;margin-bottom:4px}.teinvit-rsvp-dependent{margin-top:8px;margin-left:16px}.teinvit-rsvp-dependent input{max-width:220px}.teinvit-separator{border:0;height:1px;background:linear-gradient(90deg,transparent,rgba(176,146,97,.7),transparent);margin:20px 0}.teinvit-rsvp-message-wrap textarea{width:100%;min-height:130px;resize:vertical}.teinvit-rsvp-submit-wrap{text-align:center;margin-top:14px}.teinvit-rsvp-submit-wrap button{min-width:170px}.teinvit-inline-error{display:block;color:#a00000;margin-top:4px}.teinvit-field-error{border-color:#a00000!important}.teinvit-rsvp-status{text-align:center;margin-top:10px}.teinvit-rsvp-status.is-ok{color:#176b2c}.teinvit-rsvp-status.is-error{color:#a00000}
@media (max-width: 768px){.teinvit-info-strip,.teinvit-rsvp-grid{grid-template-columns:1fr}.teinvit-surface-card{padding:14px}.teinvit-rsvp-dependent{margin-left:0}.teinvit-rsvp-dependent input{max-width:100%}}
</style>
<div class="teinvit-birthday-invitati">
  <?php if ( $deadline_expired ) : ?>
    <p style="padding:10px;border:1px solid #cc0000;background:#fff3f3;color:#900;">Perioada de confirmare a expirat. Formularul RSVP este dezactivat.</p>
  <?php endif; ?>

  <?php if ( ( $deadline_active && $deadline_raw !== '' ) || ( $show_theme && $theme_text !== '' ) || ( $show_dress && $dress_text !== '' ) ) : ?>
  <div class="teinvit-surface-card" style="margin-top:16px;">
    <div class="teinvit-info-strip">
      <?php if ( $deadline_active && $deadline_raw !== '' ) : ?>
        <div class="teinvit-info-pill"><strong>Data maximă pentru confirmări:</strong> <?php echo esc_html( $deadline_raw ); ?></div>
      <?php endif; ?>
      <?php if ( $show_theme && $theme_text !== '' ) : ?>
        <div class="teinvit-info-pill"><strong>Tematica petrecerii:</strong> <?php echo esc_html( $theme_text ); ?></div>
      <?php endif; ?>
      <?php if ( $show_dress && $dress_text !== '' ) : ?>
        <div class="teinvit-info-pill"><strong>Dress code:</strong> <?php echo esc_html( $dress_text ); ?></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="teinvit-surface-card teinvit-rsvp-card">
    <form id="teinvit-birthday-rsvp-form" novalidate>
      <fieldset <?php disabled( $deadline_expired ); ?>>
        <h3 style="text-align:center;margin-top:0;">RSVP</h3>

        <div class="teinvit-rsvp-grid">
          <div class="teinvit-rsvp-field">
            <label for="birthday-rsvp-last-name">Nume*</label>
            <input id="birthday-rsvp-last-name" name="guest_last_name" required>
          </div>
          <div class="teinvit-rsvp-field">
            <label for="birthday-rsvp-first-name">Prenume*</label>
            <input id="birthday-rsvp-first-name" name="guest_first_name" required>
          </div>
          <div class="teinvit-rsvp-field">
            <label for="birthday-rsvp-phone">Telefon*</label>
            <input id="birthday-rsvp-phone" name="guest_phone" required>
          </div>
          <div class="teinvit-rsvp-field">
            <label for="birthday-rsvp-email">Email</label>
            <input id="birthday-rsvp-email" name="guest_email" type="email">
          </div>
          <?php if ( $show_guest_count ) : ?>
          <div class="teinvit-rsvp-field">
            <label for="birthday-rsvp-guest-count">Pentru câte persoane confirmați participarea?</label>
            <input id="birthday-rsvp-guest-count" name="attending_people_count" type="number" min="1" max="50" value="1">
          </div>
          <?php else : ?>
            <input type="hidden" name="attending_people_count" value="1">
          <?php endif; ?>
        </div>

        <hr class="teinvit-separator">

        <?php if ( $show_party ) : ?>
        <div class="teinvit-rsvp-question">
          <label>Veți participa la petrecere?</label>
          <div class="teinvit-rsvp-choice-group">
            <label><input type="radio" name="attending_party" value="1" required> DA</label>
            <label><input type="radio" name="attending_party" value="0"> NU</label>
          </div>
        </div>
        <?php endif; ?>

        <?php if ( $show_kids ) : ?>
        <div class="teinvit-rsvp-question">
          <label>Veți veni însoțiți de copii?</label>
          <div class="teinvit-rsvp-choice-group">
            <label><input type="radio" name="bringing_kids" value="1"> DA</label>
            <label><input type="radio" name="bringing_kids" value="0" checked> NU</label>
          </div>
          <div id="birthday-rsvp-kids-wrap" class="teinvit-rsvp-dependent" style="display:none;">
            <label for="birthday-rsvp-kids-count">Număr copii</label>
            <input id="birthday-rsvp-kids-count" name="kids_count" type="number" min="1" max="50">
          </div>
        </div>
        <?php endif; ?>

        <?php if ( $show_child_menu ) : ?>
        <div class="teinvit-rsvp-question">
          <label>Aveți nevoie de meniu pentru copil/copii?</label>
          <div class="teinvit-rsvp-choice-group">
            <label><input type="radio" name="child_menu_requested" value="1"> DA</label>
            <label><input type="radio" name="child_menu_requested" value="0" checked> NU</label>
          </div>
          <div id="birthday-rsvp-child-menu-wrap" class="teinvit-rsvp-dependent" style="display:none;">
            <label for="birthday-rsvp-child-menu-count">Număr meniuri copii</label>
            <input id="birthday-rsvp-child-menu-count" name="child_menu_count" type="number" min="1" max="50">
          </div>
        </div>
        <?php endif; ?>

        <?php if ( $show_accommodation ) : ?>
        <div class="teinvit-rsvp-question">
          <label>Aveți nevoie de cazare?</label>
          <div class="teinvit-rsvp-choice-group">
            <label><input type="radio" name="needs_accommodation" value="1"> DA</label>
            <label><input type="radio" name="needs_accommodation" value="0" checked> NU</label>
          </div>
          <div id="birthday-rsvp-accommodation-wrap" class="teinvit-rsvp-dependent" style="display:none;">
            <label for="birthday-rsvp-accommodation-count">Număr persoane pentru cazare</label>
            <input id="birthday-rsvp-accommodation-count" name="accommodation_people_count" type="number" min="1" max="50">
          </div>
        </div>
        <?php endif; ?>

        <?php if ( $show_vegetarian ) : ?>
        <div class="teinvit-rsvp-question">
          <label>Doriți meniu vegetarian?</label>
          <div class="teinvit-rsvp-choice-group">
            <label><input type="radio" name="vegetarian_requested" value="1"> DA</label>
            <label><input type="radio" name="vegetarian_requested" value="0" checked> NU</label>
          </div>
          <div id="birthday-rsvp-vegetarian-wrap" class="teinvit-rsvp-dependent" style="display:none;">
            <label for="birthday-rsvp-vegetarian-count">Număr meniuri vegetariene</label>
            <input id="birthday-rsvp-vegetarian-count" name="vegetarian_menus_count" type="number" min="1" max="50">
          </div>
        </div>
        <?php endif; ?>

        <?php if ( $show_allergies ) : ?>
        <div class="teinvit-rsvp-question">
          <label>Aveți alergii alimentare?</label>
          <div class="teinvit-rsvp-choice-group">
            <label><input type="radio" name="has_allergies" value="1"> DA</label>
            <label><input type="radio" name="has_allergies" value="0" checked> NU</label>
          </div>
          <div id="birthday-rsvp-allergies-wrap" class="teinvit-rsvp-dependent" style="display:none;">
            <label for="birthday-rsvp-allergy-details">Detalii alergii</label>
            <textarea id="birthday-rsvp-allergy-details" name="allergy_details" rows="3"></textarea>
          </div>
        </div>
        <?php endif; ?>

        <?php if ( $show_message || $show_special_observations ) : ?>
        <hr class="teinvit-separator">
        <div class="teinvit-rsvp-message-wrap">
          <?php if ( $show_message ) : ?>
          <div class="teinvit-rsvp-field">
            <label for="birthday-rsvp-message">Doriți să transmiteți un mesaj sărbătoritului/sărbătoriților?</label>
            <textarea id="birthday-rsvp-message" name="message_to_celebrants" rows="5"></textarea>
          </div>
          <?php endif; ?>
          <?php if ( $show_special_observations ) : ?>
          <div class="teinvit-rsvp-field" style="margin-top:12px;">
            <label for="birthday-rsvp-observations">Aveți observații speciale pentru organizator?</label>
            <textarea id="birthday-rsvp-observations" name="special_observations" rows="4"></textarea>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <hr class="teinvit-separator">
        <div class="teinvit-rsvp-field">
          <label>
            <input type="checkbox" name="gdpr_accepted" value="1" required>
            Sunt de acord cu prelucrarea datelor mele în scopul gestionării invitației și confirmării prezenței la eveniment și declar că am citit și accept
            <?php if ( $policy_url ) : ?>
              <a href="<?php echo esc_url( $policy_url ); ?>" target="_blank" rel="noopener">Politica de prelucrare a datelor</a>
            <?php else : ?>
              <span>Politica de prelucrare a datelor</span>
            <?php endif; ?>
            și
            <?php if ( $terms_url ) : ?>
              <a href="<?php echo esc_url( $terms_url ); ?>" target="_blank" rel="noopener">Termenii și condițiile</a>.
            <?php else : ?>
              <span>Termenii și condițiile</span>.
            <?php endif; ?>
          </label>
          <label style="display:block;margin-top:8px;">
            <input type="checkbox" name="marketing_consent" value="1">
            Accept comunicări comerciale (marketing).
            <?php if ( $policy_url ) : ?>
              <a href="<?php echo esc_url( $policy_url ); ?>" target="_blank" rel="noopener">Politica de confidențialitate</a>
            <?php endif; ?>
          </label>
        </div>

        <div class="teinvit-rsvp-submit-wrap">
          <button type="submit">Trimite formularul</button>
          <div id="teinvit-birthday-rsvp-msg" class="teinvit-rsvp-status" aria-live="polite"></div>
        </div>
      </fieldset>
    </form>
  </div>
</div>
<script>
(function(){
  const endpoint = <?php echo wp_json_encode( esc_url_raw( rest_url( 'teinvit/v2/invitati/' . rawurlencode( $token ) . '/rsvp' ) ) ); ?>;
  const form = document.getElementById('teinvit-birthday-rsvp-form');
  const msg = document.getElementById('teinvit-birthday-rsvp-msg');
  if (!form) return;

  function byName(name){ return form.querySelector('[name="' + name + '"]'); }
  function checkedValue(name){
    const node = form.querySelector('[name="' + name + '"]:checked');
    return node ? node.value : '';
  }
  function boolValue(name){ return checkedValue(name) === '1' ? 1 : 0; }
  function intValue(name, fallback){
    const raw = String((byName(name) && byName(name).value) || '').trim();
    if (!raw) return fallback || 0;
    const n = parseInt(raw, 10);
    return Number.isFinite(n) ? n : (fallback || 0);
  }
  function clearFieldError(field){
    if (!field) return;
    field.classList.remove('teinvit-field-error');
    const next = field.nextElementSibling;
    if (next && next.classList && next.classList.contains('teinvit-inline-error')) next.remove();
  }
  function setFieldError(field, message){
    if (!field) return;
    field.classList.add('teinvit-field-error');
    let next = field.nextElementSibling;
    if (!next || !next.classList || !next.classList.contains('teinvit-inline-error')) {
      next = document.createElement('small');
      next.className = 'teinvit-inline-error';
      field.insertAdjacentElement('afterend', next);
    }
    next.textContent = message;
  }
  function clearErrors(){
    form.querySelectorAll('.teinvit-field-error').forEach(function(el){ el.classList.remove('teinvit-field-error'); });
    form.querySelectorAll('.teinvit-inline-error').forEach(function(el){ el.remove(); });
    if (msg) {
      msg.textContent = '';
      msg.className = 'teinvit-rsvp-status';
    }
  }
  function bindConditional(name, targetId){
    const target = document.getElementById(targetId);
    const radios = form.querySelectorAll('[name="' + name + '"]');
    if (!target || !radios.length) return;
    const refresh = function(){
      const yes = checkedValue(name) === '1';
      target.style.display = yes ? '' : 'none';
      if (!yes) {
        target.querySelectorAll('input,textarea,select').forEach(function(el){
          el.value = '';
          clearFieldError(el);
        });
      }
    };
    radios.forEach(function(r){ r.addEventListener('change', refresh); });
    refresh();
  }
  bindConditional('bringing_kids', 'birthday-rsvp-kids-wrap');
  bindConditional('child_menu_requested', 'birthday-rsvp-child-menu-wrap');
  bindConditional('needs_accommodation', 'birthday-rsvp-accommodation-wrap');
  bindConditional('vegetarian_requested', 'birthday-rsvp-vegetarian-wrap');
  bindConditional('has_allergies', 'birthday-rsvp-allergies-wrap');

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    if (form.querySelector('fieldset[disabled]')) return;
    clearErrors();

    const errors = [];
    const lastName = byName('guest_last_name');
    const firstName = byName('guest_first_name');
    const phone = byName('guest_phone');
    const email = byName('guest_email');
    const gdpr = byName('gdpr_accepted');

    if (!lastName || !lastName.value.trim()) {
      errors.push('Nume este obligatoriu.');
      setFieldError(lastName, 'Nume obligatoriu.');
    }
    if (!firstName || !firstName.value.trim()) {
      errors.push('Prenume este obligatoriu.');
      setFieldError(firstName, 'Prenume obligatoriu.');
    }
    const phoneValue = phone ? String(phone.value || '').trim() : '';
    if (!/^(?:07\d{8}|\+407\d{8}|\+[1-9]\d{7,14})$/.test(phoneValue)) {
      errors.push('Telefon invalid. Folosiți 07xxxxxxxx, +407xxxxxxxx sau format internațional (+...).');
      setFieldError(phone, 'Telefon invalid.');
    }
    const emailValue = email ? String(email.value || '').trim() : '';
    if (emailValue !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
      errors.push('Email invalid.');
      setFieldError(email, 'Email invalid.');
    }
    if (!gdpr || !gdpr.checked) {
      errors.push('Trebuie să acceptați GDPR pentru trimitere.');
      setFieldError(gdpr, 'GDPR obligatoriu.');
    }

    const peopleCount = Math.max(1, intValue('attending_people_count', 1));
    const kidsCount = boolValue('bringing_kids') ? intValue('kids_count', 0) : 0;
    if (boolValue('bringing_kids') && kidsCount < 1) {
      errors.push('Completați numărul de copii.');
      setFieldError(byName('kids_count'), 'Completați numărul de copii.');
    }
    const maxTotal = Math.max(1, peopleCount + Math.max(0, kidsCount));
    if (boolValue('child_menu_requested')) {
      const childMenuCount = intValue('child_menu_count', 0);
      const maxChildMenu = kidsCount > 0 ? kidsCount : maxTotal;
      if (childMenuCount < 1 || childMenuCount > maxChildMenu) {
        errors.push('Numărul de meniuri copii este invalid.');
        setFieldError(byName('child_menu_count'), 'Maxim: ' + maxChildMenu);
      }
    }
    if (boolValue('needs_accommodation') && intValue('accommodation_people_count', 0) < 1) {
      errors.push('Completați numărul de persoane pentru cazare.');
      setFieldError(byName('accommodation_people_count'), 'Completați numărul de persoane.');
    }
    if (boolValue('vegetarian_requested')) {
      const vegCount = intValue('vegetarian_menus_count', 0);
      if (vegCount < 1 || vegCount > maxTotal) {
        errors.push('Numărul de meniuri vegetariene este invalid.');
        setFieldError(byName('vegetarian_menus_count'), 'Maxim: ' + maxTotal);
      }
    }
    if (boolValue('has_allergies') && !String((byName('allergy_details') && byName('allergy_details').value) || '').trim()) {
      errors.push('Completați alergiile.');
      setFieldError(byName('allergy_details'), 'Completați alergiile.');
    }

    if (errors.length) {
      const firstInvalid = form.querySelector('.teinvit-field-error');
      if (firstInvalid && typeof firstInvalid.focus === 'function') firstInvalid.focus();
      window.alert(errors.join('\n'));
      return;
    }

    const fd = new FormData(form);
    const payload = {};
    fd.forEach(function(value, key){ payload[key] = value; });
    payload.gdpr_accepted = gdpr && gdpr.checked ? 1 : 0;
    payload.marketing_consent = byName('marketing_consent') && byName('marketing_consent').checked ? 1 : 0;
    ['attending_party','bringing_kids','child_menu_requested','needs_accommodation','vegetarian_requested','has_allergies'].forEach(function(key){
      if (form.querySelector('[name="' + key + '"]')) payload[key] = boolValue(key);
    });

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const json = await response.json();
      if (!response.ok || !json || json.ok !== true) {
        throw new Error((json && json.message) || 'Nu s-a putut salva confirmarea.');
      }
      if (msg) {
        msg.textContent = 'Confirmarea a fost trimisă. Mulțumim!';
        msg.className = 'teinvit-rsvp-status is-ok';
      }
      form.reset();
      ['bringing_kids','child_menu_requested','needs_accommodation','vegetarian_requested','has_allergies'].forEach(function(name){
        const no = form.querySelector('[name="' + name + '"][value="0"]');
        if (no) no.checked = true;
      });
      ['bringing_kids','child_menu_requested','needs_accommodation','vegetarian_requested','has_allergies'].forEach(function(name){
        form.querySelectorAll('[name="' + name + '"]').forEach(function(r){ r.dispatchEvent(new Event('change', { bubbles: true })); });
      });
    } catch (err) {
      if (msg) {
        msg.textContent = err && err.message ? err.message : 'Nu s-a putut salva confirmarea.';
        msg.className = 'teinvit-rsvp-status is-error';
      }
    }
  });
})();
</script>
