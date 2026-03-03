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
$show_gifts_section = ! empty( $config['show_gifts_section'] );


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
$gifts = $wpdb->get_results( $wpdb->prepare( "SELECT gift_id,gift_name,gift_link,gift_delivery_address,status FROM {$t['gifts']} WHERE token=%s AND include_in_public=1 AND (gift_name<>'' OR gift_link<>'') ORDER BY id ASC", $token ), ARRAY_A );
$in_cpt_template = ! empty( $GLOBALS['TEINVIT_IN_CPT_TEMPLATE'] );
?>
<style>
  .teinvit-surface-card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:18px}.teinvit-preview-card{padding:10px;margin-bottom:16px}.teinvit-rsvp-card{margin-top:16px}.teinvit-rsvp-zone { display: block; margin-bottom: 16px; }
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
  #teinvit-rsvp-msg { margin-top: 10px; text-align: center; }
  .teinvit-separator{border:0;height:1px;background:linear-gradient(90deg,transparent,rgba(176,146,97,.7),transparent);margin:20px 0;position:relative}
  .teinvit-separator::after{content:"❦";position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);background:#fff;color:#b09261;padding:0 10px;font-size:14px;line-height:1}
  .teinvit-section-separator{position:relative;height:28px;margin:20px auto 18px;max-width:420px;text-align:center}
  .teinvit-section-separator::before{content:"";position:absolute;left:0;right:0;top:50%;height:1px;background:linear-gradient(90deg,transparent,rgba(176,146,97,.75),transparent)}
  .teinvit-section-separator span{position:relative;display:inline-block;padding:0 12px;background:#fff;color:#b09261;line-height:28px}
  @media (max-width: 900px) {
    .teinvit-rsvp-grid,
    .teinvit-rsvp-zone2-grid { grid-template-columns: 1fr; }
    .teinvit-rsvp-message-wrap textarea,
    .teinvit-rsvp-gdpr-wrap { width: 100%; max-width: 100%; }
  }
</style>
<div class="teinvit-invitati-page" style="max-width:980px;margin:0 auto;">
  <?php if ( ! $in_cpt_template ) : ?>
  <div class="teinvit-surface-card teinvit-preview-card">
  <div class="preview" style="position:relative;">
    <img src="<?php echo esc_url( $bg ); ?>" alt="background" style="width:100%;height:auto;display:block;">
    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:28px;"><?php echo esc_html( $invitation_data['names'] ?? '' ); ?></div>
  </div>
  </div>
  <?php endif; ?>

  <?php if ( $deadline_expired ) : ?>
    <p style="padding:10px;border:1px solid #cc0000;background:#fff3f3;color:#900;">Perioada de confirmare a expirat. Formularul RSVP este dezactivat.</p>
  <?php endif; ?>

  <div class="teinvit-surface-card teinvit-rsvp-card">
  <form id="teinvit-rsvp-form" novalidate>
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
          <label for="rsvp-email">Email</label>
          <input id="rsvp-email" name="email" type="email">
        </div>

      </div>

      <div class="teinvit-rsvp-zone teinvit-rsvp-attendees">
        <div class="teinvit-rsvp-rinline">
          <label for="rsvp-persoane">Pentru câte persoane faceți confirmarea (exceptând copii).</label>
          <input id="rsvp-persoane" name="pentru_cate_persoane_confirmati_prezenta" type="number" min="1" value="1">
        </div>
      </div>

      <hr class="teinvit-separator">

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
                  <input id="rsvp-kids-count" name="cati_copii_va_vor_insoti" type="number" min="1">
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
                  <input id="rsvp-accommodation-count" name="pentru_cate_persoane_solicitati_cazare" type="number" min="1">
                </div>
              </div>
            <?php elseif ( $question_type === 'vegetarian' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Doriți meniu vegetarian?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="doriti_meniu_vegetarian" value="DA"> DA</label>
                  <label><input type="radio" name="doriti_meniu_vegetarian" value="NU" checked> NU</label>
                </div>
                <div id="rsvp-vegetarian-wrap" class="teinvit-rsvp-dependent" style="display:none;">
                  <label for="rsvp-vegetarian-count">Câte meniuri?</label>
                  <input id="rsvp-vegetarian-count" name="cate_meniuri_vegetariene" type="number" min="1">
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
                  <input id="rsvp-kids-count" name="cati_copii_va_vor_insoti" type="number" min="1">
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
                  <input id="rsvp-accommodation-count" name="pentru_cate_persoane_solicitati_cazare" type="number" min="1">
                </div>
              </div>
            <?php elseif ( $question_type === 'vegetarian' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Doriți meniu vegetarian?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="doriti_meniu_vegetarian" value="DA"> DA</label>
                  <label><input type="radio" name="doriti_meniu_vegetarian" value="NU" checked> NU</label>
                </div>
                <div id="rsvp-vegetarian-wrap" class="teinvit-rsvp-dependent" style="display:none;">
                  <label for="rsvp-vegetarian-count">Câte meniuri?</label>
                  <input id="rsvp-vegetarian-count" name="cate_meniuri_vegetariene" type="number" min="1">
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

      <?php if ( $show_gifts_section && ! empty( $gifts ) ) : ?>
        <div class="teinvit-section-separator" aria-hidden="true"><span>❦</span></div>
        <h3>Lista de cadouri disponibile</h3>
        <p>Poți alege un cadou pentru miri din lista lor de dorințe. Îl poți trimite prin curier la Adresa de livrare completată în dreptul cadoului, sau îl poți înmâna personal.</p>
        <table>
          <thead>
            <tr>
              <th>Selectează</th>
              <th>Denumire produs</th>
              <th>Link produs</th>
              <th>Adresă de livrare</th>
              <th>Status produs</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ( $gifts as $gift ) : ?>
            <?php $is_reserved = ( (string) ( $gift['status'] ?? '' ) === 'reserved' ); ?>
            <tr>
              <td><?php if ( ! $is_reserved ) : ?><input type="checkbox" name="gift_ids[]" value="<?php echo esc_attr( $gift['gift_id'] ); ?>"><?php endif; ?></td>
              <td><?php echo esc_html( $gift['gift_name'] ); ?></td>
              <td><?php if ( $gift['gift_link'] ) : ?><a href="<?php echo esc_url( $gift['gift_link'] ); ?>" target="_blank" rel="noopener">Vezi produsul</a><?php endif; ?></td>
              <td><?php echo esc_html( $gift['gift_delivery_address'] ); ?></td>
              <td><?php echo $is_reserved ? 'Rezervat' : 'Disponibil'; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <hr class="teinvit-separator">
      <?php endif; ?>

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
          <div id="teinvit-rsvp-msg"></div>
        </div>
      </div>
    </fieldset>
  </form>
  </div>
</div>
<script>
(function(){
  function bindConditional(radioName, targetId){
    const radios = document.querySelectorAll('input[name="' + radioName + '"]');
    const target = document.getElementById(targetId);
    if(!radios.length || !target) return;

    const refresh = ()=>{
      const selected = Array.from(radios).find(r => r.checked);
      const isDa = selected && selected.value === 'DA';
      target.style.display = isDa ? '' : 'none';

      const dependent = target.querySelector('input,textarea,select');
      if (!dependent) return;

      if (!isDa) {
        dependent.value = '';
        clearFieldError(dependent);
      }
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


  function clearFieldError(field) {
    if (!field) return;
    field.classList.remove('teinvit-field-error');
    const next = field.nextElementSibling;
    if (next && next.classList && next.classList.contains('teinvit-inline-error')) {
      next.remove();
    }
  }

  function clearFieldErrors(form) {
    form.querySelectorAll('.teinvit-field-error').forEach(el => el.classList.remove('teinvit-field-error'));
    form.querySelectorAll('.teinvit-inline-error').forEach(el => el.remove());
  }

  bindConditional('veti_veni_insotiti_de_copii', 'rsvp-kids-wrap');
  bindConditional('aveti_nevoie_de_cazare', 'rsvp-accommodation-wrap');
  bindConditional('aveti_alergii_alimentare', 'rsvp-allergies-wrap');
  bindConditional('doriti_meniu_vegetarian', 'rsvp-vegetarian-wrap');

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
    const phoneOk = /^(?:07\d{8}|\+407\d{8}|\+[1-9]\d{7,14})$/.test(phoneValue);
    if (!phoneOk) {
      errors.push('Telefon invalid. Folosiți 07xxxxxxxx, +407xxxxxxxx sau format internațional (+...).');
      setFieldError(telefon, 'Telefon invalid.');
    }

    const emailValue = email ? String(email.value || '').trim() : '';
    if (emailValue !== '') {
      const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue);
      if (!emailOk) {
        errors.push('Email invalid.');
        setFieldError(email, 'Email invalid.');
      }
    }

    const adultsCount = Math.max(1, parseInt(form.querySelector('[name="pentru_cate_persoane_confirmati_prezenta"]')?.value || '1', 10) || 1);
    const kidsAnswer = form.querySelector('input[name="veti_veni_insotiti_de_copii"]:checked')?.value || 'NU';
    const kidsCount = kidsAnswer === 'DA' ? Math.max(0, parseInt(form.querySelector('[name="cati_copii_va_vor_insoti"]')?.value || '0', 10) || 0) : 0;
    const maxVegetarian = adultsCount + kidsCount;
    const vegAnswer = form.querySelector('input[name="doriti_meniu_vegetarian"]:checked')?.value || 'NU';
    const vegInput = form.querySelector('[name="cate_meniuri_vegetariene"]');
    if (vegInput) {
      vegInput.max = String(maxVegetarian);
    }
    if (vegAnswer === 'DA') {
      const vegCount = Math.max(0, parseInt(vegInput?.value || '0', 10) || 0);
      if (vegCount < 1 || vegCount > maxVegetarian) {
        errors.push('Câte meniuri? trebuie să fie între 1 și ' + maxVegetarian + '.');
        setFieldError(vegInput, 'Valoare invalidă. Max: ' + maxVegetarian);
      }
    }


    const kidsInput = form.querySelector('[name="cati_copii_va_vor_insoti"]');
    if (kidsAnswer === 'DA') {
      const kidsRaw = String(kidsInput?.value || '').trim();
      const kidsParsed = parseInt(kidsRaw || '0', 10);
      if (kidsRaw === '' || !Number.isFinite(kidsParsed) || kidsParsed < 1) {
        errors.push('Completati numarul de copii');
        setFieldError(kidsInput, 'Completati numarul de copii');
      }
    }

    const accommodationAnswer = form.querySelector('input[name="aveti_nevoie_de_cazare"]:checked')?.value || 'NU';
    const accommodationInput = form.querySelector('[name="pentru_cate_persoane_solicitati_cazare"]');
    if (accommodationAnswer === 'DA') {
      const accRaw = String(accommodationInput?.value || '').trim();
      const accParsed = parseInt(accRaw || '0', 10);
      if (accRaw === '' || !Number.isFinite(accParsed) || accParsed < 1) {
        errors.push('Completati numarul de persoane care au nevoie de cazare');
        setFieldError(accommodationInput, 'Completati numarul de persoane care au nevoie de cazare');
      }
    }

    if (vegAnswer === 'DA') {
      const vegRaw = String(vegInput?.value || '').trim();
      if (vegRaw === '') {
        errors.push('Completati numarul de meniuri vegetariene');
        setFieldError(vegInput, 'Completati numarul de meniuri vegetariene');
      }
    }

    const allergyAnswer = form.querySelector('input[name="aveti_alergii_alimentare"]:checked')?.value || 'NU';
    const allergyInput = form.querySelector('[name="va_rugam_sa_specificati_alergiile"]');
    if (allergyAnswer === 'DA' && String(allergyInput?.value || '').trim() === '') {
      errors.push('Completati alergiile');
      setFieldError(allergyInput, 'Completati alergiile');
    }

    if (!gdpr || !gdpr.checked) {
      errors.push('Trebuie să acceptați GDPR pentru trimitere.');
      setFieldError(gdpr, 'GDPR obligatoriu.');
    }

    if (errors.length) {
      const firstInvalid = form.querySelector('.teinvit-field-error');
      if (firstInvalid && typeof firstInvalid.focus === 'function') {
        firstInvalid.focus();
      }
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
    payload.guest_phone = normalizedPhone.startsWith('+407') ? ('0' + normalizedPhone.slice(3)) : normalizedPhone;
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
    payload.vegetarian_menus_count = payload.vegetarian_requested ? (parseInt(payload.cate_meniuri_vegetariene || '0', 10) || 0) : 0;
    payload.has_allergies = payload.aveti_alergii_alimentare === 'DA' ? 1 : 0;
    payload.allergy_details = payload.va_rugam_sa_specificati_alergiile || '';
    payload.message_to_couple = payload.daca_doriti_lasati_un_mesaj_pentru_miri || '';
    payload.gdpr_accepted = payload.gdpr_accept ? 1 : 0;
    payload.marketing_consent = payload.marketing_consent ? 1 : 0;

    const res = await fetch('<?php echo esc_url_raw( rest_url( 'teinvit/v2/invitati/' . rawurlencode( $token ) . '/rsvp' ) ); ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const data = await res.json();
    const msg = document.getElementById('teinvit-rsvp-msg');
    if (!res.ok) {
      msg.textContent = data.message || 'Eroare';
      const fieldMap = {
        guest_email: '[name="email"]',
        kids_count: '[name="cati_copii_va_vor_insoti"]',
        accommodation_people_count: '[name="pentru_cate_persoane_solicitati_cazare"]',
        vegetarian_menus_count: '[name="cate_meniuri_vegetariene"]',
        allergy_details: '[name="va_rugam_sa_specificati_alergiile"]'
      };
      const selector = fieldMap[data?.data?.field || ''];
      if (selector) {
        const field = form.querySelector(selector);
        if (field) {
          setFieldError(field, data.message || 'Valoare invalidă.');
          if (typeof field.focus === 'function') field.focus();
        }
      }
      return;
    }

    msg.textContent = 'Mulțumim! Informațiile au fost salvate.';
    form.querySelectorAll('input,textarea,button').forEach(el => el.disabled = true);
  });
})();
</script>
