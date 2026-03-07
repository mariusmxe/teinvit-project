<?php
$token = get_query_var( 'teinvit_invitati_token' );
$inv = teinvit_get_invitation( $token );
$active = teinvit_get_active_snapshot( $token );
$snapshot = $active ? json_decode( (string) $active['snapshot'], true ) : [];
$invitation_data = (array) ( $snapshot['invitation'] ?? [] );
$config = wp_parse_args( (array) ( $inv['config'] ?? [] ), teinvit_default_rsvp_config() );

$deadline_active = ! empty( $config['show_rsvp_deadline'] );
$deadline_raw = (string) ( $config['rsvp_deadline_date'] ?? '' );
$deadline_ts = 0;
if ( $deadline_raw !== '' && preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $deadline_raw, $m ) ) {
    $deadline_ts = strtotime( $m[3] . '-' . $m[2] . '-' . $m[1] . ' 23:59:59' );
}
$deadline_expired = $deadline_active && $deadline_ts > 0 && time() > $deadline_ts;

$bg = teinvit_model_background_url( $inv['model_key'] ?? 'invn01' );

$events = isset( $invitation_data['events'] ) && is_array( $invitation_data['events'] ) ? $invitation_data['events'] : [];
$event_flags = [
    'civil' => false,
    'religious' => false,
    'party' => false,
];
foreach ( $events as $event ) {
    if ( ! is_array( $event ) ) {
        continue;
    }
    $title = strtolower( trim( (string) ( $event['title'] ?? '' ) ) );
    if ( strpos( $title, 'civil' ) !== false ) {
        $event_flags['civil'] = true;
    }
    if ( strpos( $title, 'religio' ) !== false ) {
        $event_flags['religious'] = true;
    }
    if ( strpos( $title, 'petrec' ) !== false ) {
        $event_flags['party'] = true;
    }
}

$show_civil = ! empty( $event_flags['civil'] ) && ! empty( $config['show_attending_civil'] );
$show_religious = ! empty( $event_flags['religious'] ) && ! empty( $config['show_attending_religious'] );
$show_party = ! empty( $event_flags['party'] ) && ! empty( $config['show_attending_party'] );
$show_kids = ! empty( $config['show_kids'] );
$show_accommodation = ! empty( $config['show_accommodation'] );
$show_vegetarian = ! empty( $config['show_vegetarian'] );
$show_allergies = ! empty( $config['show_allergies'] );
$show_message = isset( $config['show_message'] ) ? ! empty( $config['show_message'] ) : true;


$zone2_question_defs = [
    'show_attending_civil' => [ 'type' => 'civil' ],
    'show_attending_religious' => [ 'type' => 'religious' ],
    'show_attending_party' => [ 'type' => 'party' ],
    'show_kids' => [ 'type' => 'kids' ],
    'show_accommodation' => [ 'type' => 'accommodation' ],
    'show_vegetarian' => [ 'type' => 'vegetarian' ],
    'show_allergies' => [ 'type' => 'allergies' ],
];
$zone2_order = isset( $config['rsvp_zone2_order'] ) && is_array( $config['rsvp_zone2_order'] ) ? $config['rsvp_zone2_order'] : array_keys( $zone2_question_defs );
$zone2_enabled = [];
foreach ( $zone2_order as $config_key ) {
    if ( ! isset( $zone2_question_defs[ $config_key ] ) || empty( $config[ $config_key ] ) ) {
        continue;
    }

    if ( $config_key === 'show_attending_civil' && ! $show_civil ) {
        continue;
    }
    if ( $config_key === 'show_attending_religious' && ! $show_religious ) {
        continue;
    }
    if ( $config_key === 'show_attending_party' && ! $show_party ) {
        continue;
    }

    $zone2_enabled[] = $zone2_question_defs[ $config_key ]['type'];
}

$policy_url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
$terms_page = get_page_by_path( 'termeni-si-conditii', OBJECT, 'page' );
$terms_url = $terms_page instanceof WP_Post ? get_permalink( $terms_page ) : '';

global $wpdb;
$t = teinvit_db_tables();
$gifts = $wpdb->get_results( $wpdb->prepare( "SELECT gift_id,gift_name,gift_link,gift_delivery_address,status FROM {$t['gifts']} WHERE token=%s ORDER BY id ASC", $token ), ARRAY_A );
$in_cpt_template = ! empty( $GLOBALS['TEINVIT_IN_CPT_TEMPLATE'] );
?>
<style>
  .teinvit-rsvp-zone { display: block; margin-bottom: 16px; }
  .teinvit-rsvp-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 16px; }
  .teinvit-rsvp-grid .teinvit-rsvp-field label,
  .teinvit-rsvp-grid .teinvit-rsvp-field input,
  .teinvit-rsvp-grid .teinvit-rsvp-field textarea { display: block; width: 100%; }
  .teinvit-rsvp-attendees { margin-top: 10px; }
  .teinvit-rsvp-rinline { display: flex; align-items: center; justify-content: flex-start; gap: 10px; flex-wrap: wrap; }
  .teinvit-rsvp-rinline label { margin: 0; }
  .teinvit-rsvp-rinline input { width: 80px; max-width: 100%; }
  .teinvit-rsvp-question { margin-bottom: 14px; }
  .teinvit-rsvp-zone2-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 20px; align-items: start; }
  .teinvit-rsvp-zone2-col .teinvit-rsvp-question { margin-bottom: 12px; }
  .teinvit-rsvp-choice-group { margin-top: 6px; }
  .teinvit-rsvp-choice-group label { display: block; margin-bottom: 4px; }
  .teinvit-rsvp-dependent { margin-top: 8px; margin-left: 16px; }
  .teinvit-rsvp-message-wrap { text-align: center; }
  .teinvit-rsvp-message-wrap textarea { width: min(50%, 640px); min-height: 180px; resize: none; margin: 0 auto; }
  .teinvit-rsvp-gdpr-wrap { margin-top: 12px; text-align: left; display: inline-block; max-width: min(50%, 640px); width: 100%; }
  .teinvit-rsvp-submit-wrap { margin-top: 14px; text-align: center; }
  @media (max-width: 900px) {
    .teinvit-rsvp-grid,
    .teinvit-rsvp-zone2-grid { grid-template-columns: 1fr; }
    .teinvit-rsvp-message-wrap textarea,
    .teinvit-rsvp-gdpr-wrap { width: 100%; max-width: 100%; }
  }
</style>
<div class="teinvit-invitati-page" style="max-width:980px;margin:0 auto;">
  <?php if ( ! $in_cpt_template ) : ?>
  <div class="preview" style="position:relative;">
    <img src="<?php echo esc_url( $bg ); ?>" alt="background" style="width:100%;height:auto;display:block;">
    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:28px;"><?php echo esc_html( $invitation_data['names'] ?? '' ); ?></div>
  </div>
  <?php endif; ?>

  <?php if ( $deadline_expired ) : ?>
    <p style="padding:10px;border:1px solid #cc0000;background:#fff3f3;color:#900;">Perioada de confirmare a expirat. Formularul RSVP este dezactivat.</p>
  <?php endif; ?>

  <form id="teinvit-rsvp-form">
    <fieldset <?php disabled( $deadline_expired ); ?>>
      <?php if ( $deadline_active && $deadline_raw ) : ?>
        <h3 class="has-text-align-center"><?php echo esc_html( 'Data maximă pentru confirmări: ' . $deadline_raw ); ?></h3>
      <?php else : ?>
        <h3 class="has-text-align-center">RSVP</h3>
      <?php endif; ?>

      <div class="teinvit-rsvp-zone teinvit-rsvp-grid">
        <div class="teinvit-rsvp-field">
          <label for="rsvp-nume">Nume*</label>
          <input id="rsvp-nume" name="numele_tau" required>
        </div>

        <div class="teinvit-rsvp-field">
          <label for="rsvp-prenume">Prenume*</label>
          <input id="rsvp-prenume" name="prenumele_tau" required>
        </div>

        <div class="teinvit-rsvp-field">
          <label for="rsvp-telefon">Telefon*</label>
          <input id="rsvp-telefon" name="numar_de_telefon" required>
        </div>

        <div class="teinvit-rsvp-field">
          <label for="rsvp-email">Email*</label>
          <input id="rsvp-email" name="email" type="email" required>
        </div>

      </div>

      <div class="teinvit-rsvp-zone teinvit-rsvp-attendees">
        <div class="teinvit-rsvp-rinline">
          <label for="rsvp-persoane">Pentru câte persoane faceți confirmarea (exceptând copii).</label>
          <input id="rsvp-persoane" name="pentru_cate_persoane_confirmati_prezenta" type="number" min="1" value="1">
        </div>
      </div>

      <hr>

      <div class="teinvit-rsvp-zone teinvit-rsvp-zone2-grid">
        <div class="teinvit-rsvp-zone2-col">
          <?php foreach ( $zone2_enabled as $index => $question_type ) : ?>
            <?php if ( $index % 2 !== 0 ) { continue; } ?>
            <?php if ( $question_type === 'civil' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Veți participa la cununia civilă?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="veti_participa_la_cununia_civila" value="DA" required> DA</label>
                  <label><input type="radio" name="veti_participa_la_cununia_civila" value="NU"> NU</label>
                </div>
              </div>
            <?php elseif ( $question_type === 'religious' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Veți participa la ceremonia religioasă?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="veti_participa_la_ceremonia_religioasa" value="DA" required> DA</label>
                  <label><input type="radio" name="veti_participa_la_ceremonia_religioasa" value="NU"> NU</label>
                </div>
              </div>
            <?php elseif ( $question_type === 'party' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Veți participa la petrecere?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="veti_participa_la_petrecere" value="DA" required> DA</label>
                  <label><input type="radio" name="veti_participa_la_petrecere" value="NU"> NU</label>
                </div>
              </div>
            <?php elseif ( $question_type === 'kids' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Veți veni însoțiți de copii?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="veti_veni_insotiti_de_copii" value="DA"> DA</label>
                  <label><input type="radio" name="veti_veni_insotiti_de_copii" value="NU" checked> NU</label>
                </div>
                <div id="rsvp-kids-wrap" class="teinvit-rsvp-dependent" style="display:none;">
                  <label for="rsvp-kids-count">Câți copii vă vor însoți</label>
                  <input id="rsvp-kids-count" name="cati_copii_va_vor_insoti" type="number" min="0" value="0">
                </div>
              </div>
            <?php elseif ( $question_type === 'accommodation' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Aveți nevoie de cazare?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="aveti_nevoie_de_cazare" value="DA"> DA</label>
                  <label><input type="radio" name="aveti_nevoie_de_cazare" value="NU" checked> NU</label>
                </div>
                <div id="rsvp-accommodation-wrap" class="teinvit-rsvp-dependent" style="display:none;">
                  <label for="rsvp-accommodation-count">Pentru câte persoane solicitați cazare</label>
                  <input id="rsvp-accommodation-count" name="pentru_cate_persoane_solicitati_cazare" type="number" min="0" value="0">
                </div>
              </div>
            <?php elseif ( $question_type === 'vegetarian' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Doriți meniu vegetarian?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="doriti_meniu_vegetarian" value="DA"> DA</label>
                  <label><input type="radio" name="doriti_meniu_vegetarian" value="NU" checked> NU</label>
                </div>
              </div>
            <?php elseif ( $question_type === 'allergies' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Aveți alergii alimentare?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="aveti_alergii_alimentare" value="DA"> DA</label>
                  <label><input type="radio" name="aveti_alergii_alimentare" value="NU" checked> NU</label>
                </div>
                <div id="rsvp-allergies-wrap" class="teinvit-rsvp-dependent" style="display:none;">
                  <label for="rsvp-allergies-detail">Vă rugăm să specificați alergiile</label>
                  <input id="rsvp-allergies-detail" name="va_rugam_sa_specificati_alergiile">
                </div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>

        <div class="teinvit-rsvp-zone2-col">
          <?php foreach ( $zone2_enabled as $index => $question_type ) : ?>
            <?php if ( $index % 2 === 0 ) { continue; } ?>
            <?php if ( $question_type === 'civil' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Veți participa la cununia civilă?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="veti_participa_la_cununia_civila" value="DA" required> DA</label>
                  <label><input type="radio" name="veti_participa_la_cununia_civila" value="NU"> NU</label>
                </div>
              </div>
            <?php elseif ( $question_type === 'religious' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Veți participa la ceremonia religioasă?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="veti_participa_la_ceremonia_religioasa" value="DA" required> DA</label>
                  <label><input type="radio" name="veti_participa_la_ceremonia_religioasa" value="NU"> NU</label>
                </div>
              </div>
            <?php elseif ( $question_type === 'party' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Veți participa la petrecere?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="veti_participa_la_petrecere" value="DA" required> DA</label>
                  <label><input type="radio" name="veti_participa_la_petrecere" value="NU"> NU</label>
                </div>
              </div>
            <?php elseif ( $question_type === 'kids' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Veți veni însoțiți de copii?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="veti_veni_insotiti_de_copii" value="DA"> DA</label>
                  <label><input type="radio" name="veti_veni_insotiti_de_copii" value="NU" checked> NU</label>
                </div>
                <div id="rsvp-kids-wrap" class="teinvit-rsvp-dependent" style="display:none;">
                  <label for="rsvp-kids-count">Câți copii vă vor însoți</label>
                  <input id="rsvp-kids-count" name="cati_copii_va_vor_insoti" type="number" min="0" value="0">
                </div>
              </div>
            <?php elseif ( $question_type === 'accommodation' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Aveți nevoie de cazare?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="aveti_nevoie_de_cazare" value="DA"> DA</label>
                  <label><input type="radio" name="aveti_nevoie_de_cazare" value="NU" checked> NU</label>
                </div>
                <div id="rsvp-accommodation-wrap" class="teinvit-rsvp-dependent" style="display:none;">
                  <label for="rsvp-accommodation-count">Pentru câte persoane solicitați cazare</label>
                  <input id="rsvp-accommodation-count" name="pentru_cate_persoane_solicitati_cazare" type="number" min="0" value="0">
                </div>
              </div>
            <?php elseif ( $question_type === 'vegetarian' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Doriți meniu vegetarian?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="doriti_meniu_vegetarian" value="DA"> DA</label>
                  <label><input type="radio" name="doriti_meniu_vegetarian" value="NU" checked> NU</label>
                </div>
              </div>
            <?php elseif ( $question_type === 'allergies' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Aveți alergii alimentare?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="aveti_alergii_alimentare" value="DA"> DA</label>
                  <label><input type="radio" name="aveti_alergii_alimentare" value="NU" checked> NU</label>
                </div>
                <div id="rsvp-allergies-wrap" class="teinvit-rsvp-dependent" style="display:none;">
                  <label for="rsvp-allergies-detail">Vă rugăm să specificați alergiile</label>
                  <input id="rsvp-allergies-detail" name="va_rugam_sa_specificati_alergiile">
                </div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>

      <table>
        <tbody>
        <?php foreach ( $gifts as $gift ) : ?>
          <tr>
            <td><input type="checkbox" name="gift_ids[]" value="<?php echo esc_attr( $gift['gift_id'] ); ?>" <?php disabled( 'reserved' === $gift['status'] ); ?>></td>
            <td><?php echo esc_html( $gift['gift_name'] ); ?></td>
            <td><?php if ( $gift['gift_link'] ) : ?><a href="<?php echo esc_url( $gift['gift_link'] ); ?>" target="_blank">link</a><?php endif; ?></td>
            <td><?php echo esc_html( $gift['gift_delivery_address'] ); ?></td>
            <td><?php echo esc_html( $gift['status'] ); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <hr>

      <div class="teinvit-rsvp-zone teinvit-rsvp-message-wrap">
        <?php if ( $show_message ) : ?>
          <h3>Dacă doriți, lăsați un mesaj pentru miri</h3>
          <textarea name="daca_doriti_lasati_un_mesaj_pentru_miri" rows="8"></textarea>
        <?php endif; ?>

        <div class="teinvit-rsvp-gdpr-wrap">
          <label>
            <input type="checkbox" name="gdpr_accept" required>
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
              <span>Termenii și condițiile</span>. <!-- TODO: configure URL pentru termenii și condițiile -->
            <?php endif; ?>
          </label>
        </div>

        <div class="teinvit-rsvp-submit-wrap">
          <button type="submit">Trimite formularul</button>
        </div>
      </div>
    </fieldset>
  </form>
  <div id="teinvit-rsvp-msg"></div>
</div>
<script>
(function(){
  function bindConditional(radioName, targetId){
    const radios = document.querySelectorAll('input[name="' + radioName + '"]');
    const target = document.getElementById(targetId);
    if(!radios.length || !target) return;

    const refresh = ()=>{
      const selected = Array.from(radios).find(r => r.checked);
      target.style.display = selected && selected.value === 'DA' ? '' : 'none';
    };

    radios.forEach(r=>r.addEventListener('change', refresh));
    refresh();
  }

  function setFieldError(field, message) {
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

  function clearFieldErrors(form) {
    form.querySelectorAll('.teinvit-field-error').forEach(el => el.classList.remove('teinvit-field-error'));
    form.querySelectorAll('.teinvit-inline-error').forEach(el => el.remove());
  }

  bindConditional('veti_veni_insotiti_de_copii', 'rsvp-kids-wrap');
  bindConditional('aveti_nevoie_de_cazare', 'rsvp-accommodation-wrap');
  bindConditional('aveti_alergii_alimentare', 'rsvp-allergies-wrap');

  document.getElementById('teinvit-rsvp-form').addEventListener('submit', async function(e){
    e.preventDefault();

    const form = e.target;
    if (form.querySelector('fieldset[disabled]')) return;

    clearFieldErrors(form);

    const errors = [];
    const nume = form.querySelector('[name="numele_tau"]');
    const prenume = form.querySelector('[name="prenumele_tau"]');
    const telefon = form.querySelector('[name="numar_de_telefon"]');
    const email = form.querySelector('[name="email"]');
    const gdpr = form.querySelector('[name="gdpr_accept"]');

    if (!nume || !nume.value.trim()) {
      errors.push('Nume este obligatoriu.');
      setFieldError(nume, 'Nume obligatoriu.');
    }
    if (!prenume || !prenume.value.trim()) {
      errors.push('Prenume este obligatoriu.');
      setFieldError(prenume, 'Prenume obligatoriu.');
    }

    const phoneValue = telefon ? String(telefon.value || '').trim() : '';
    const phoneOk = /^(?:07\d{8}|\+407\d{8})$/.test(phoneValue);
    if (!phoneOk) {
      errors.push('Telefon invalid. Folosiți formatul 07xxxxxxxx sau +407xxxxxxxx.');
      setFieldError(telefon, 'Telefon invalid.');
    }

    const emailValue = email ? String(email.value || '').trim() : '';
    const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue);
    if (!emailOk) {
      errors.push('Email invalid.');
      setFieldError(email, 'Email invalid.');
    }

    if (!gdpr || !gdpr.checked) {
      errors.push('Trebuie să acceptați GDPR pentru trimitere.');
      setFieldError(gdpr, 'GDPR obligatoriu.');
    }

    if (errors.length) {
      window.alert(errors.join('\n'));
      return;
    }

    const fd = new FormData(form);
    const payload = {};

    for (const [k, v] of fd.entries()) {
      if (k === 'gift_ids[]') {
        payload.gift_ids = (payload.gift_ids || []);
        payload.gift_ids.push(v);
        continue;
      }

      payload[k] = v;
    }

    payload.guest_last_name = payload.numele_tau || '';
    payload.guest_first_name = payload.prenumele_tau || '';
    const normalizedPhone = String(payload.numar_de_telefon || '').replace(/\s+/g, '');
    payload.guest_phone = normalizedPhone.startsWith('+40') ? ('0' + normalizedPhone.slice(3)) : normalizedPhone;
    payload.guest_email = (payload.email || '').trim();
    payload.attending_people_count = payload.pentru_cate_persoane_confirmati_prezenta || 1;

    payload.attending_civil = payload.veti_participa_la_cununia_civila === 'DA' ? 1 : 0;
    payload.attending_religious = payload.veti_participa_la_ceremonia_religioasa === 'DA' ? 1 : 0;
    payload.attending_party = payload.veti_participa_la_petrecere === 'DA' ? 1 : 0;

    payload.bringing_kids = payload.veti_veni_insotiti_de_copii === 'DA' ? 1 : 0;
    payload.kids_count = payload.cati_copii_va_vor_insoti || 0;

    payload.needs_accommodation = payload.aveti_nevoie_de_cazare === 'DA' ? 1 : 0;
    payload.accommodation_people_count = payload.pentru_cate_persoane_solicitati_cazare || 0;

    payload.vegetarian_requested = payload.doriti_meniu_vegetarian === 'DA' ? 1 : 0;
    payload.has_allergies = payload.aveti_alergii_alimentare === 'DA' ? 1 : 0;
    payload.allergy_details = payload.va_rugam_sa_specificati_alergiile || '';
    payload.message_to_couple = payload.daca_doriti_lasati_un_mesaj_pentru_miri || '';
    payload.gdpr_accepted = payload.gdpr_accept ? 1 : 0;

    const res = await fetch('<?php echo esc_url_raw( rest_url( 'teinvit/v2/invitati/' . rawurlencode( $token ) . '/rsvp' ) ); ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const data = await res.json();
    const msg = document.getElementById('teinvit-rsvp-msg');
    if (!res.ok) {
      msg.textContent = data.message || 'Eroare';
      return;
    }

    msg.textContent = 'Mulțumim! RSVP salvat.';
    form.querySelectorAll('input,textarea,button').forEach(el => el.disabled = true);
  });
})();
</script>
