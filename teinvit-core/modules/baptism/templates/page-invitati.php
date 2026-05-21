<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$token = get_query_var( 'teinvit_invitati_token' );
$token = sanitize_text_field( (string) $token );
$inv = function_exists( 'teinvit_get_invitation_record' ) ? teinvit_get_invitation_record( $token, 'baptism' ) : null;
if ( ! is_array( $inv ) ) {
    echo '<p>Invitația nu a fost găsită.</p>';
    return;
}

$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $inv['order_id'] ) : null;
$product_id = ( $order && function_exists( 'teinvit_get_order_primary_product_id' ) ) ? (int) teinvit_get_order_primary_product_id( $order ) : 0;
$config = function_exists( 'teinvit_baptism_config_with_defaults' )
    ? teinvit_baptism_config_with_defaults( is_array( $inv['config'] ?? null ) ? $inv['config'] : [] )
    : wp_parse_args( is_array( $inv['config'] ?? null ) ? $inv['config'] : [], function_exists( 'teinvit_default_rsvp_config_for_vertical' ) ? teinvit_default_rsvp_config_for_vertical( 'baptism' ) : [] );

$context_invitation = isset( $context['invitation'] ) && is_array( $context['invitation'] ) ? $context['invitation'] : [];
$context_preview_html = isset( $context['preview_html'] ) ? (string) $context['preview_html'] : '';
$active = function_exists( 'teinvit_get_active_snapshot_for_token_from_storage' )
    ? teinvit_get_active_snapshot_for_token_from_storage( $token, 'baptism' )
    : null;
$payload = ! empty( $active['snapshot'] ) ? json_decode( (string) $active['snapshot'], true ) : [];
$invitation = isset( $payload['invitation'] ) && is_array( $payload['invitation'] ) ? $payload['invitation'] : [];
$use_context_preview = ( $context_preview_html !== '' && ! empty( $context_invitation ) );
if ( empty( $invitation ) && ! empty( $context_invitation ) ) {
    $invitation = $context_invitation;
}
$preview_html = '';
if ( $use_context_preview ) {
    if ( empty( $invitation ) ) {
        $invitation = $context_invitation;
    }
    $preview_html = $context_preview_html;
}
if ( ! empty( $invitation ) && function_exists( 'teinvit_render_invitation_html_for_vertical' ) ) {
    if ( $preview_html === '' ) {
        $preview_html = teinvit_render_invitation_html_for_vertical( 'baptism', $invitation, $order, 'preview', $product_id );
    }
    $preview_html = preg_replace( '/<script>\s*window\.TEINVIT_INVITATION_DATA\s*=.*?<\/script>/s', '', (string) $preview_html );
    $preview_html = preg_replace( '/window\.TEINVIT_INVITATION_DATA\s*=\s*.*?;\s*/s', '', (string) $preview_html );
}

$deadline_active = ! empty( $config['show_rsvp_deadline'] );
$deadline_raw = (string) ( $config['rsvp_deadline_date'] ?? '' );
$deadline_ts = 0;
if ( $deadline_raw !== '' && preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $deadline_raw, $m ) ) {
    $deadline_ts = strtotime( $m[3] . '-' . $m[2] . '-' . $m[1] . ' 23:59:59' );
}
$deadline_expired = $deadline_active && $deadline_ts > 0 && time() > $deadline_ts;

$show_religious = ! empty( $config['show_attending_religious'] );
$show_party = ! empty( $config['show_attending_party'] );
$show_adults = ! empty( $config['show_attending_people_count'] );
$show_kids = ! empty( $config['show_kids'] );
$show_child_menu = ! empty( $config['show_child_menu'] );
$show_child_seat = ! empty( $config['show_child_seat'] );
$show_accommodation = ! empty( $config['show_accommodation'] );
$show_transport = ! empty( $config['show_transport'] );
$show_vegetarian = ! empty( $config['show_vegetarian'] );
$show_allergies = ! empty( $config['show_allergies'] );
$show_message = ! empty( $config['show_message'] );

$events = isset( $invitation['events'] ) && is_array( $invitation['events'] ) ? $invitation['events'] : [];
$religious_event = isset( $events['religious'] ) && is_array( $events['religious'] ) ? $events['religious'] : [];
$party_event = isset( $events['party'] ) && is_array( $events['party'] ) ? $events['party'] : [];

$policy_url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
$terms_page = get_page_by_path( 'termeni-si-conditii', OBJECT, 'page' );
$terms_url = $terms_page instanceof WP_Post ? get_permalink( $terms_page ) : '';

$show_gifts_section = ! empty( $config['show_gifts_section'] );
$gifts = [];
if ( $show_gifts_section ) {
    global $wpdb;
    $gifts_table = function_exists( 'teinvit_baptism_gifts_table_for_token' )
        ? teinvit_baptism_gifts_table_for_token( $token )
        : ( function_exists( 'teinvit_gifts_table_for_token' ) ? teinvit_gifts_table_for_token( $token, 'baptism' ) : '' );

    if ( $gifts_table !== '' ) {
        $gifts = $wpdb->get_results( $wpdb->prepare( "SELECT gift_id,gift_name,gift_link,gift_delivery_address,status FROM {$gifts_table} WHERE token=%s AND include_in_public=1 AND (gift_name<>'' OR gift_link<>'') ORDER BY id ASC", $token ), ARRAY_A );
        $gifts = is_array( $gifts ) ? $gifts : [];
    }
}
?>
<style>
.teinvit-slot-preview{display:block!important;visibility:visible!important;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:10px;margin:0 auto 16px;max-width:980px;overflow:hidden}.teinvit-slot-preview,.teinvit-slot-preview *{box-sizing:border-box}.teinvit-slot-preview>.teinvit-wedding,.teinvit-slot-preview .teinvit-page,.teinvit-slot-preview .teinvit-container{width:100%;max-width:100%;min-width:0;margin-left:auto;margin-right:auto}.teinvit-slot-preview .teinvit-preview{width:min(100%,559px)!important;max-width:100%!important;aspect-ratio:148/210!important;height:auto!important;min-height:0!important;margin:0 auto!important;overflow:hidden!important}
.teinvit-baptism-invitati{max-width:980px;margin:0 auto}.teinvit-baptism-invitati *{box-sizing:border-box}.teinvit-surface-card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:18px}.teinvit-rsvp-card,.teinvit-info-card{margin-top:16px}.teinvit-rsvp-grid,.teinvit-rsvp-question-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 16px}.teinvit-rsvp-field label,.teinvit-rsvp-field input,.teinvit-rsvp-field textarea{display:block;width:100%}.teinvit-rsvp-question{margin-bottom:0}.teinvit-rsvp-choice-group{margin-top:6px}.teinvit-rsvp-choice-group label{display:block;margin-bottom:4px}.teinvit-rsvp-dependent{margin-top:8px;margin-left:16px}.teinvit-rsvp-dependent input{max-width:220px}.teinvit-rsvp-message-wrap textarea{width:100%;min-height:130px;resize:vertical}.teinvit-rsvp-submit-wrap{text-align:center;margin-top:14px}.teinvit-rsvp-submit-wrap button{min-width:170px}.teinvit-separator{border:0;height:1px;background:linear-gradient(90deg,transparent,rgba(176,146,97,.7),transparent);margin:20px 0}.teinvit-inline-error{display:block;color:#a00000;margin-top:4px}.teinvit-field-error{border-color:#a00000!important}.teinvit-rsvp-status{text-align:center;margin-top:10px}.teinvit-rsvp-status.is-ok{color:#176b2c}.teinvit-rsvp-status.is-error{color:#a00000}.teinvit-info-meta-row{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}.teinvit-info-pill{border:1px solid rgba(0,0,0,.1);border-radius:8px;padding:10px;background:#fafafa;flex:1 1 240px;max-width:420px;text-align:center}.teinvit-baptism-invitati form,.teinvit-baptism-invitati fieldset{max-width:100%;min-width:0;min-inline-size:0}.teinvit-gifts-intro{text-align:center;margin-bottom:12px}.teinvit-gifts-intro h3{margin:0 0 6px}.teinvit-gifts-table-wrap{display:block;width:100%;max-width:100%;min-width:0;overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;touch-action:pan-x}.teinvit-gifts-table{width:max-content;min-width:960px;max-width:none;border-collapse:collapse}.teinvit-gifts-table th,.teinvit-gifts-table td{padding:8px;border:1px solid rgba(0,0,0,.14);vertical-align:top}.teinvit-gifts-table th{background:#fafafa;white-space:nowrap}.teinvit-gifts-table th:nth-child(1),.teinvit-gifts-table td:nth-child(1){width:10ch;min-width:10ch;text-align:center}.teinvit-gifts-table th:nth-child(2),.teinvit-gifts-table td:nth-child(2){width:30ch;min-width:30ch}.teinvit-gifts-table th:nth-child(3),.teinvit-gifts-table td:nth-child(3){width:18ch;min-width:18ch;white-space:nowrap}.teinvit-gifts-table th:nth-child(4),.teinvit-gifts-table td:nth-child(4){width:48ch;min-width:48ch}.teinvit-gifts-table th:nth-child(5),.teinvit-gifts-table td:nth-child(5){width:16ch;min-width:16ch;white-space:nowrap}.teinvit-gift-status-reserved{color:#8a4b00;font-weight:600}.teinvit-gift-status-free{color:#176b2c;font-weight:600}
.teinvit-baptism-invitati .teinvit-rsvp-card{max-width:100%;min-width:0}
@media (max-width:768px){.teinvit-slot-preview{padding:8px;margin-bottom:12px}.teinvit-rsvp-grid,.teinvit-rsvp-question-grid{grid-template-columns:1fr}.teinvit-surface-card{padding:14px}.teinvit-rsvp-dependent{margin-left:0}.teinvit-rsvp-dependent input{max-width:100%}}
</style>
<?php if ( ! empty( $preview_html ) ) : ?>
  <script>window.TEINVIT_INVITATION_DATA = <?php echo wp_json_encode( $invitation ); ?>;</script>
  <div class="teinvit-slot teinvit-slot-preview" data-teinvit-slot="preview">
    <?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  </div>
<?php endif; ?>

<div class="teinvit-baptism-invitati">
  <?php if ( $deadline_expired ) : ?>
    <p style="padding:10px;border:1px solid #cc0000;background:#fff3f3;color:#900;">Perioada de confirmare a expirat. Formularul RSVP este dezactivat.</p>
  <?php endif; ?>

  <?php if ( ( $deadline_active && $deadline_raw !== '' ) || ! empty( $config['show_baptism_religious_info'] ) || ! empty( $config['show_baptism_party_info'] ) ) : ?>
  <div class="teinvit-surface-card teinvit-info-card">
    <?php if ( $deadline_active && $deadline_raw !== '' ) : ?>
      <div style="text-align:center;margin-bottom:10px;"><strong>Data maximă pentru confirmări:</strong> <?php echo esc_html( $deadline_raw ); ?></div>
    <?php endif; ?>
    <div class="teinvit-info-meta-row">
      <?php if ( ! empty( $config['show_baptism_religious_info'] ) && ! empty( $religious_event['enabled'] ) ) : ?>
        <div class="teinvit-info-pill">
          <strong>Slujba de botez</strong><br>
          <?php echo esc_html( trim( (string) ( $religious_event['loc'] ?? '' ) ) ); ?>
          <?php if ( ! empty( $religious_event['date'] ) ) : ?><br><?php echo esc_html( (string) $religious_event['date'] ); ?><?php endif; ?>
          <?php if ( ! empty( $religious_event['waze'] ) ) : ?><br><a href="<?php echo esc_url( $religious_event['waze'] ); ?>" target="_blank" rel="noopener">Deschide Waze</a><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ( ! empty( $config['show_baptism_party_info'] ) && ! empty( $party_event['enabled'] ) ) : ?>
        <div class="teinvit-info-pill">
          <strong>Petrecerea de botez</strong><br>
          <?php echo esc_html( trim( (string) ( $party_event['loc'] ?? '' ) ) ); ?>
          <?php if ( ! empty( $party_event['date'] ) ) : ?><br><?php echo esc_html( (string) $party_event['date'] ); ?><?php endif; ?>
          <?php if ( ! empty( $party_event['waze'] ) ) : ?><br><a href="<?php echo esc_url( $party_event['waze'] ); ?>" target="_blank" rel="noopener">Deschide Waze</a><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="teinvit-surface-card teinvit-rsvp-card">
    <form id="teinvit-baptism-rsvp-form" novalidate>
      <fieldset <?php disabled( $deadline_expired ); ?>>
        <h3 style="text-align:center;margin-top:0;">RSVP</h3>
        <div class="teinvit-rsvp-grid">
          <div class="teinvit-rsvp-field">
            <label for="baptism-rsvp-last-name">Nume invitat*</label>
            <input id="baptism-rsvp-last-name" name="guest_last_name" required>
          </div>
          <div class="teinvit-rsvp-field">
            <label for="baptism-rsvp-first-name">Prenume invitat*</label>
            <input id="baptism-rsvp-first-name" name="guest_first_name" required>
          </div>
          <div class="teinvit-rsvp-field">
            <label for="baptism-rsvp-phone">Telefon*</label>
            <input id="baptism-rsvp-phone" name="guest_phone" required>
          </div>
          <div class="teinvit-rsvp-field">
            <label for="baptism-rsvp-email">Email</label>
            <input id="baptism-rsvp-email" name="guest_email" type="email">
          </div>
        </div>

        <hr class="teinvit-separator">
        <div class="teinvit-rsvp-question-grid">
          <?php if ( $show_religious ) : ?>
          <div class="teinvit-rsvp-question">
            <label>Participați la Slujba de botez?</label>
            <div class="teinvit-rsvp-choice-group">
              <label><input type="radio" name="attending_religious" value="1" required> DA</label>
              <label><input type="radio" name="attending_religious" value="0"> NU</label>
            </div>
          </div>
          <?php endif; ?>
          <?php if ( $show_party ) : ?>
          <div class="teinvit-rsvp-question">
            <label>Participați la petrecerea de botez?</label>
            <div class="teinvit-rsvp-choice-group">
              <label><input type="radio" name="attending_party" value="1" required> DA</label>
              <label><input type="radio" name="attending_party" value="0"> NU</label>
            </div>
          </div>
          <?php endif; ?>
          <?php if ( $show_adults ) : ?>
          <div class="teinvit-rsvp-field teinvit-rsvp-question">
            <label for="baptism-rsvp-adults">Pentru câte persoane confirmați participarea?</label>
            <input id="baptism-rsvp-adults" name="attending_people_count" type="number" min="0" max="999">
          </div>
          <?php endif; ?>
          <?php if ( $show_kids ) : ?>
          <div class="teinvit-rsvp-question">
            <label>Veniți însoțiți de copii?</label>
            <div class="teinvit-rsvp-choice-group">
              <label><input type="radio" name="bringing_kids" value="1" required> DA</label>
              <label><input type="radio" name="bringing_kids" value="0"> NU</label>
            </div>
            <div id="baptism-rsvp-kids-wrap" class="teinvit-rsvp-dependent" style="display:none;">
              <label for="baptism-rsvp-kids-count">Câți copii?</label>
              <input id="baptism-rsvp-kids-count" name="kids_count" type="number" min="1" max="999">
            </div>
          </div>
          <?php endif; ?>
          <?php if ( $show_child_menu ) : ?>
          <div class="teinvit-rsvp-question">
            <label>Aveți nevoie de meniu copil?</label>
            <div class="teinvit-rsvp-choice-group">
              <label><input type="radio" name="child_menu_requested" value="1"> DA</label>
              <label><input type="radio" name="child_menu_requested" value="0" checked> NU</label>
            </div>
            <div id="baptism-rsvp-child-menu-wrap" class="teinvit-rsvp-dependent" style="display:none;">
              <label for="baptism-rsvp-child-menu-count">Câte meniuri copil sunt necesare?</label>
              <input id="baptism-rsvp-child-menu-count" name="child_menu_count" type="number" min="1" max="999">
            </div>
          </div>
          <?php endif; ?>
          <?php if ( $show_child_seat ) : ?>
          <div class="teinvit-rsvp-question">
            <label>Aveți nevoie de scaun copil?</label>
            <div class="teinvit-rsvp-choice-group">
              <label><input type="radio" name="child_seat_requested" value="1"> DA</label>
              <label><input type="radio" name="child_seat_requested" value="0" checked> NU</label>
            </div>
            <div id="baptism-rsvp-child-seat-wrap" class="teinvit-rsvp-dependent" style="display:none;">
              <label for="baptism-rsvp-child-seat-count">Câte scaune copil sunt necesare?</label>
              <input id="baptism-rsvp-child-seat-count" name="child_seat_count" type="number" min="1" max="999">
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
            <div id="baptism-rsvp-accommodation-wrap" class="teinvit-rsvp-dependent" style="display:none;">
              <label for="baptism-rsvp-accommodation-count">Pentru câte persoane solicitați cazare?</label>
              <input id="baptism-rsvp-accommodation-count" name="accommodation_people_count" type="number" min="1" max="999">
            </div>
          </div>
          <?php endif; ?>
          <?php if ( $show_transport ) : ?>
          <div class="teinvit-rsvp-question">
            <label>Aveți nevoie de transport între biserică și restaurant?</label>
            <div class="teinvit-rsvp-choice-group">
              <label><input type="radio" name="transport_requested" value="1"> DA</label>
              <label><input type="radio" name="transport_requested" value="0" checked> NU</label>
            </div>
            <div id="baptism-rsvp-transport-wrap" class="teinvit-rsvp-dependent" style="display:none;">
              <label for="baptism-rsvp-transport-count">Pentru câte persoane solicitați transport?</label>
              <input id="baptism-rsvp-transport-count" name="transport_people_count" type="number" min="1" max="999">
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
            <div id="baptism-rsvp-vegetarian-wrap" class="teinvit-rsvp-dependent" style="display:none;">
              <label for="baptism-rsvp-vegetarian-count">Câte meniuri vegetariene sunt necesare?</label>
              <input id="baptism-rsvp-vegetarian-count" name="vegetarian_menus_count" type="number" min="1" max="999">
            </div>
          </div>
          <?php endif; ?>
          <?php if ( $show_allergies ) : ?>
          <div class="teinvit-rsvp-question">
            <label>Există alergii sau restricții alimentare?</label>
            <div class="teinvit-rsvp-choice-group">
              <label><input type="radio" name="has_allergies" value="1"> DA</label>
              <label><input type="radio" name="has_allergies" value="0" checked> NU</label>
            </div>
            <div id="baptism-rsvp-allergies-wrap" class="teinvit-rsvp-dependent" style="display:none;">
              <label for="baptism-rsvp-allergy-details">Vă rugăm să menționați alergiile/restricțiile</label>
              <textarea id="baptism-rsvp-allergy-details" name="allergy_details" rows="3"></textarea>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <?php if ( $show_gifts_section && ! empty( $gifts ) ) : ?>
        <hr class="teinvit-separator">
        <div class="teinvit-gifts-intro">
          <h3>Lista de cadouri</h3>
          <p>Alege un cadou pe care dorești să îl rezervi din lista de mai jos.</p>
        </div>
        <div class="teinvit-gifts-table-wrap">
          <table class="teinvit-gifts-table">
            <thead><tr><th>Selectează</th><th>Denumire produs</th><th>Link produs</th><th>Adresă de livrare</th><th>Status produs</th></tr></thead>
            <tbody>
              <?php foreach ( $gifts as $gift ) : ?>
                <?php $is_reserved = ( (string) ( $gift['status'] ?? '' ) === 'reserved' ); ?>
                <tr>
                  <td><?php if ( ! $is_reserved ) : ?><input type="checkbox" name="gift_ids[]" value="<?php echo esc_attr( $gift['gift_id'] ); ?>"><?php endif; ?></td>
                  <td><?php echo esc_html( $gift['gift_name'] ); ?></td>
                  <td><?php if ( ! empty( $gift['gift_link'] ) ) : ?><a href="<?php echo esc_url( $gift['gift_link'] ); ?>" target="_blank" rel="noopener">Vezi produsul</a><?php endif; ?></td>
                  <td><?php echo esc_html( $gift['gift_delivery_address'] ); ?></td>
                  <td class="<?php echo $is_reserved ? 'teinvit-gift-status-reserved' : 'teinvit-gift-status-free'; ?>"><?php echo $is_reserved ? 'Rezervat' : 'Disponibil'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ( $show_message ) : ?>
        <hr class="teinvit-separator">
        <div class="teinvit-rsvp-message-wrap">
          <div class="teinvit-rsvp-field">
            <label for="baptism-rsvp-message">Doriți să transmiteți un mesaj pentru familie/copil?</label>
            <textarea id="baptism-rsvp-message" name="message_to_family" rows="5"></textarea>
          </div>
        </div>
        <?php endif; ?>

        <hr class="teinvit-separator">
        <div class="teinvit-rsvp-field">
          <label>
            <input type="checkbox" name="gdpr_accepted" value="1" required>
            Sunt de acord cu prelucrarea datelor mele în scopul gestionării invitației și confirmării prezenței la eveniment și declar că am citit și accept
            <?php if ( $policy_url ) : ?><a href="<?php echo esc_url( $policy_url ); ?>" target="_blank" rel="noopener">Politica de prelucrare a datelor</a><?php else : ?><span>Politica de prelucrare a datelor</span><?php endif; ?>
            și
            <?php if ( $terms_url ) : ?><a href="<?php echo esc_url( $terms_url ); ?>" target="_blank" rel="noopener">Termenii și condițiile</a>.<?php else : ?><span>Termenii și condițiile</span>.<?php endif; ?>
          </label>
          <label style="display:block;margin-top:8px;">
            <input type="checkbox" name="marketing_consent" value="1">
            Accept comunicări comerciale (marketing).
            <?php if ( $policy_url ) : ?><a href="<?php echo esc_url( $policy_url ); ?>" target="_blank" rel="noopener">Politica de confidențialitate</a><?php endif; ?>
          </label>
        </div>

        <div class="teinvit-rsvp-submit-wrap">
          <button type="submit">Trimite formularul</button>
          <div id="teinvit-baptism-rsvp-msg" class="teinvit-rsvp-status" aria-live="polite"></div>
        </div>
      </fieldset>
    </form>
  </div>
</div>
<script>
(function(){
  const endpoint = <?php echo wp_json_encode( esc_url_raw( rest_url( 'teinvit/v2/invitati/' . rawurlencode( $token ) . '/rsvp' ) ) ); ?>;
  const cfg = <?php echo wp_json_encode( [
    'show_religious' => $show_religious,
    'show_party' => $show_party,
    'show_adults' => $show_adults,
    'show_kids' => $show_kids,
    'show_child_menu' => $show_child_menu,
    'show_child_seat' => $show_child_seat,
    'show_accommodation' => $show_accommodation,
    'show_transport' => $show_transport,
    'show_vegetarian' => $show_vegetarian,
    'show_allergies' => $show_allergies
  ] ); ?>;
  const form = document.getElementById('teinvit-baptism-rsvp-form');
  const msg = document.getElementById('teinvit-baptism-rsvp-msg');
  if (!form) return;

  function byName(name){ return form.querySelector('[name="' + name + '"]'); }
  function checkedValue(name){ const node = form.querySelector('[name="' + name + '"]:checked'); return node ? node.value : ''; }
  function boolValue(name){ return checkedValue(name) === '1' ? 1 : 0; }
  function intValue(name, fallback){ const field = byName(name); const raw = String((field && field.value) || '').trim(); if (!raw) return fallback || 0; const n = parseInt(raw, 10); return Number.isFinite(n) ? n : (fallback || 0); }
  function setError(field, text){
    if (!field) return;
    field.classList.add('teinvit-field-error');
    if (field.type === 'radio') {
      const question = field.closest('.teinvit-rsvp-question');
      const group = field.closest('.teinvit-rsvp-choice-group');
      if (question && group) {
        let radioError = null;
        question.querySelectorAll('.teinvit-inline-error').forEach(function(node){
          if (!radioError && node.getAttribute('data-radio-error') === field.name) radioError = node;
        });
        if (!radioError) {
          radioError = document.createElement('small');
          radioError.className = 'teinvit-inline-error';
          radioError.setAttribute('data-radio-error', field.name || '');
          group.insertAdjacentElement('beforebegin', radioError);
        }
        radioError.textContent = text;
        return;
      }
    }
    let next = field.nextElementSibling;
    if (!next || !next.classList || !next.classList.contains('teinvit-inline-error')) {
      next = document.createElement('small');
      next.className = 'teinvit-inline-error';
      field.insertAdjacentElement('afterend', next);
    }
    next.textContent = text;
  }
  function clearErrors(){ form.querySelectorAll('.teinvit-field-error').forEach(el => el.classList.remove('teinvit-field-error')); form.querySelectorAll('.teinvit-inline-error').forEach(el => el.remove()); if (msg) { msg.textContent = ''; msg.className = 'teinvit-rsvp-status'; } }
  function bindConditional(name, targetId){
    const target = document.getElementById(targetId);
    const radios = form.querySelectorAll('[name="' + name + '"]');
    if (!target || !radios.length) return;
    const refresh = function(){
      const yes = checkedValue(name) === '1';
      target.style.display = yes ? '' : 'none';
      if (!yes) target.querySelectorAll('input,textarea,select').forEach(el => { el.value = ''; el.classList.remove('teinvit-field-error'); });
    };
    radios.forEach(r => r.addEventListener('change', refresh));
    refresh();
  }
  bindConditional('bringing_kids', 'baptism-rsvp-kids-wrap');
  bindConditional('child_menu_requested', 'baptism-rsvp-child-menu-wrap');
  bindConditional('child_seat_requested', 'baptism-rsvp-child-seat-wrap');
  bindConditional('needs_accommodation', 'baptism-rsvp-accommodation-wrap');
  bindConditional('transport_requested', 'baptism-rsvp-transport-wrap');
  bindConditional('vegetarian_requested', 'baptism-rsvp-vegetarian-wrap');
  bindConditional('has_allergies', 'baptism-rsvp-allergies-wrap');

  function refreshAdultsForAttendance(){
    const adults = byName('attending_people_count');
    if (!adults) return;
    const anyQuestion = cfg.show_religious || cfg.show_party;
    const religious = cfg.show_religious ? checkedValue('attending_religious') : '';
    const party = cfg.show_party ? checkedValue('attending_party') : '';
    const anyAnsweredNo = anyQuestion && (!cfg.show_religious || religious === '0') && (!cfg.show_party || party === '0');
    if (anyAnsweredNo) {
      adults.value = '0';
      adults.setAttribute('readonly', 'readonly');
      return;
    }
    adults.removeAttribute('readonly');
    if (adults.value === '0') adults.value = '';
  }
  form.querySelectorAll('[name="attending_religious"],[name="attending_party"]').forEach(r => r.addEventListener('change', refreshAdultsForAttendance));
  refreshAdultsForAttendance();

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

    if (!lastName || !lastName.value.trim()) { errors.push('Nume obligatoriu.'); setError(lastName, 'Nume obligatoriu.'); }
    if (!firstName || !firstName.value.trim()) { errors.push('Prenume obligatoriu.'); setError(firstName, 'Prenume obligatoriu.'); }
    const phoneValue = phone ? String(phone.value || '').trim() : '';
    if (!/^(?:07\d{8}|\+407\d{8}|\+[1-9]\d{7,14})$/.test(phoneValue)) { errors.push('Telefon invalid.'); setError(phone, 'Telefon invalid.'); }
    const emailValue = email ? String(email.value || '').trim() : '';
    if (emailValue !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) { errors.push('Email invalid.'); setError(email, 'Email invalid.'); }
    if (!gdpr || !gdpr.checked) { errors.push('GDPR obligatoriu.'); setError(gdpr, 'GDPR obligatoriu.'); }
    if (cfg.show_religious && checkedValue('attending_religious') === '') { errors.push('Alegeți participarea la Slujba de botez.'); setError(form.querySelector('[name="attending_religious"]'), 'Alegeți DA sau NU.'); }
    if (cfg.show_party && checkedValue('attending_party') === '') { errors.push('Alegeți participarea la petrecere.'); setError(form.querySelector('[name="attending_party"]'), 'Alegeți DA sau NU.'); }

    const anyQuestion = cfg.show_religious || cfg.show_party;
    const anyAttending = anyQuestion ? (boolValue('attending_religious') || boolValue('attending_party')) : true;
    const adultsField = byName('attending_people_count');
    const adultsRaw = adultsField ? String(adultsField.value || '').trim() : '';
    let adults = cfg.show_adults ? intValue('attending_people_count', 0) : (anyAttending ? 1 : 0);
    if (cfg.show_adults && anyAttending && (!adultsRaw || adults < 1 || adults > 999)) { errors.push('Completați numărul de adulți.'); setError(adultsField, 'Completați un număr valid.'); }
    if (cfg.show_adults && !anyAttending && adults !== 0) { errors.push('Adulții trebuie să fie 0 când nu participați.'); setError(adultsField, 'Trebuie să fie 0.'); }

    let kids = 0;
    if (cfg.show_kids) {
      if (checkedValue('bringing_kids') === '') { errors.push('Alegeți dacă veniți cu copii.'); setError(form.querySelector('[name="bringing_kids"]'), 'Alegeți DA sau NU.'); }
      kids = boolValue('bringing_kids') ? intValue('kids_count', 0) : 0;
      if (boolValue('bringing_kids') && (kids < 1 || kids > 999)) { errors.push('Completați numărul de copii.'); setError(byName('kids_count'), 'Completați un număr valid.'); }
    }
    const total = adults + kids;
    function validateDependent(flagName, countName, max, label){
      if (!boolValue(flagName)) return;
      const count = intValue(countName, 0);
      if (count < 1 || count > max) {
        errors.push(label + ' invalid.');
        setError(byName(countName), max > 0 ? 'Maxim: ' + max : 'Completați un număr valid.');
      }
    }
    if (cfg.show_child_menu) validateDependent('child_menu_requested', 'child_menu_count', cfg.show_kids ? kids : 999, 'Meniu copil');
    if (cfg.show_child_seat) validateDependent('child_seat_requested', 'child_seat_count', cfg.show_kids ? kids : 999, 'Scaun copil');
    if (cfg.show_accommodation) validateDependent('needs_accommodation', 'accommodation_people_count', total, 'Cazare');
    if (cfg.show_transport) validateDependent('transport_requested', 'transport_people_count', total, 'Transport');
    if (cfg.show_vegetarian) validateDependent('vegetarian_requested', 'vegetarian_menus_count', total, 'Meniu vegetarian');
    if (cfg.show_allergies && boolValue('has_allergies') && !String((byName('allergy_details') && byName('allergy_details').value) || '').trim()) {
      errors.push('Completați alergiile/restricțiile.');
      setError(byName('allergy_details'), 'Completați alergiile/restricțiile.');
    }
    if (errors.length) {
      if (msg) { msg.textContent = errors[0]; msg.className = 'teinvit-rsvp-status is-error'; }
      return;
    }

    const fd = new FormData(form);
    const payload = {};
    fd.forEach(function(value, key){
      const cleanKey = key.endsWith('[]') ? key.slice(0, -2) : key;
      if (Object.prototype.hasOwnProperty.call(payload, cleanKey)) {
        if (!Array.isArray(payload[cleanKey])) payload[cleanKey] = [payload[cleanKey]];
        payload[cleanKey].push(value);
      } else {
        payload[cleanKey] = value;
      }
    });
    try {
      const response = await fetch(endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const json = await response.json().catch(function(){ return {}; });
      if (!response.ok || !json.ok) {
        throw new Error((json && json.message) || 'Nu s-a putut trimite formularul.');
      }
      if (msg) { msg.textContent = 'Mulțumim! Confirmarea a fost trimisă.'; msg.className = 'teinvit-rsvp-status is-ok'; }
      form.reset();
      form.querySelectorAll('.teinvit-rsvp-dependent').forEach(el => el.style.display = 'none');
      refreshAdultsForAttendance();
    } catch (err) {
      if (msg) { msg.textContent = err && err.message ? err.message : 'Nu s-a putut trimite formularul.'; msg.className = 'teinvit-rsvp-status is-error'; }
    }
  });
})();
</script>
