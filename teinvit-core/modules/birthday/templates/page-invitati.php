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
$birthday_rsvp_mode = function_exists( 'teinvit_birthday_rsvp_mode_from_config' ) ? teinvit_birthday_rsvp_mode_from_config( $config ) : ( ( $config['birthday_rsvp_mode'] ?? 'adult' ) === 'child' ? 'child' : 'adult' );
$is_child_rsvp = $birthday_rsvp_mode === 'child';

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
$child_show_party = ! empty( $config['child_show_attending_party'] );
$child_show_children_count = ! empty( $config['child_show_children_count'] );
$child_show_accompanying_adults = ! empty( $config['child_show_accompanying_adults'] );
$child_show_allergies = ! empty( $config['child_show_allergies'] );
$child_show_vegetarian = ! empty( $config['child_show_vegetarian'] );
$child_show_special_observations = ! empty( $config['child_show_special_observations'] );
$child_show_message = ! empty( $config['child_show_message'] );
$guest_count_question = 'Pentru câte persoane faceți confirmarea (exceptând copii)?';
$rsvp_short_question_defs = [
    'show_attending_party' => 'party',
    'show_guest_count' => 'guest_count',
    'show_kids' => 'kids',
    'show_child_menu' => 'child_menu',
    'show_accommodation' => 'accommodation',
    'show_vegetarian' => 'vegetarian',
    'show_allergies' => 'allergies',
];
$rsvp_enabled_lookup = [
    'show_attending_party' => $show_party,
    'show_guest_count' => $show_guest_count,
    'show_kids' => $show_kids,
    'show_child_menu' => $show_child_menu,
    'show_accommodation' => $show_accommodation,
    'show_vegetarian' => $show_vegetarian,
    'show_allergies' => $show_allergies,
];
$rsvp_order = isset( $config['rsvp_zone2_order'] ) && is_array( $config['rsvp_zone2_order'] ) ? $config['rsvp_zone2_order'] : array_keys( $rsvp_short_question_defs );
$rsvp_order = array_values( array_unique( array_filter( array_map( 'sanitize_key', $rsvp_order ), static function( $key ) use ( $rsvp_short_question_defs ) {
    return isset( $rsvp_short_question_defs[ $key ] );
} ) ) );
$rsvp_order = array_values( array_unique( array_merge( $rsvp_order, array_keys( $rsvp_short_question_defs ) ) ) );
$rsvp_short_questions = [];
foreach ( $rsvp_order as $question_key ) {
    if ( ! empty( $rsvp_enabled_lookup[ $question_key ] ) && isset( $rsvp_short_question_defs[ $question_key ] ) ) {
        $rsvp_short_questions[] = $rsvp_short_question_defs[ $question_key ];
    }
}
$child_question_defs = [
    'child_show_attending_party' => 'child_party',
    'child_show_children_count' => 'child_count',
    'child_show_accompanying_adults' => 'child_accompanying_adults',
    'child_show_vegetarian' => 'child_vegetarian',
    'child_show_allergies' => 'child_allergies',
];
$child_enabled_lookup = [
    'child_show_attending_party' => $child_show_party,
    'child_show_children_count' => $child_show_children_count,
    'child_show_accompanying_adults' => $child_show_accompanying_adults,
    'child_show_vegetarian' => $child_show_vegetarian,
    'child_show_allergies' => $child_show_allergies,
];
$child_rsvp_order = isset( $config['child_rsvp_zone2_order'] ) && is_array( $config['child_rsvp_zone2_order'] ) ? $config['child_rsvp_zone2_order'] : array_keys( $child_question_defs );
$child_rsvp_order = array_values( array_unique( array_filter( array_map( 'sanitize_key', $child_rsvp_order ), static function( $key ) use ( $child_question_defs ) {
    return isset( $child_question_defs[ $key ] );
} ) ) );
$child_rsvp_order = array_values( array_unique( array_merge( $child_rsvp_order, array_keys( $child_question_defs ) ) ) );
$child_short_questions = [];
foreach ( $child_rsvp_order as $question_key ) {
    if ( ! empty( $child_enabled_lookup[ $question_key ] ) && isset( $child_question_defs[ $question_key ] ) ) {
        $child_short_questions[] = $child_question_defs[ $question_key ];
    }
}

$policy_url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
$terms_page = get_page_by_path( 'termeni-si-conditii', OBJECT, 'page' );
$terms_url = $terms_page instanceof WP_Post ? get_permalink( $terms_page ) : '';

$show_gifts_section = ! empty( $config['show_gifts_section'] );
$birthday_gift_title = 'Lista de cadouri';
$birthday_gift_subtitle = 'Alege un cadou pe care dorești să îl rezervi pentru sărbătorit/sărbătoriți.';
if ( function_exists( 'teinvit_vertical_gift_labels' ) ) {
    $gift_labels = teinvit_vertical_gift_labels( 'birthday' );
    if ( ! empty( $gift_labels['title'] ) ) {
        $birthday_gift_title = (string) $gift_labels['title'];
    }
    if ( ! empty( $gift_labels['subtitle'] ) ) {
        $birthday_gift_subtitle = (string) $gift_labels['subtitle'];
    }
}

$gifts = [];
if ( $show_gifts_section ) {
    global $wpdb;
    $gifts_table = function_exists( 'teinvit_birthday_gifts_table_for_token' )
        ? teinvit_birthday_gifts_table_for_token( $token )
        : ( function_exists( 'teinvit_gifts_table_for_token' ) ? teinvit_gifts_table_for_token( $token, 'birthday' ) : '' );

    if ( $gifts_table !== '' ) {
        $gifts = $wpdb->get_results( $wpdb->prepare( "SELECT gift_id,gift_name,gift_link,gift_delivery_address,status FROM {$gifts_table} WHERE token=%s AND include_in_public=1 AND (gift_name<>'' OR gift_link<>'') ORDER BY id ASC", $token ), ARRAY_A );
        $gifts = is_array( $gifts ) ? $gifts : [];
    }
}
?>
<style>
.teinvit-slot-preview{display:block!important;visibility:visible!important;background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:10px;margin:0 auto 16px;max-width:980px;overflow:hidden}.teinvit-slot-preview,.teinvit-slot-preview *{box-sizing:border-box}.teinvit-slot-preview>.teinvit-wedding,.teinvit-slot-preview .teinvit-page,.teinvit-slot-preview .teinvit-container{width:100%;max-width:100%;min-width:0;margin-left:auto;margin-right:auto}.teinvit-slot-preview .teinvit-preview{width:min(100%,559px)!important;max-width:100%!important;aspect-ratio:148/210!important;height:auto!important;min-height:0!important;margin:0 auto!important;overflow:hidden!important}.teinvit-birthday-invitati{max-width:980px;margin:0 auto}.teinvit-birthday-invitati *{box-sizing:border-box}.teinvit-surface-card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:18px}.teinvit-rsvp-card{margin-top:16px;max-width:100%;min-width:0}.teinvit-info-card{margin-top:16px}.teinvit-info-deadline-row{text-align:center;margin-bottom:10px}.teinvit-info-meta-row{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}.teinvit-info-pill{border:1px solid rgba(0,0,0,.1);border-radius:8px;padding:10px;background:#fafafa}.teinvit-info-meta-row .teinvit-info-pill{flex:1 1 240px;max-width:360px;text-align:center}.teinvit-birthday-invitati form,.teinvit-birthday-invitati fieldset{max-width:100%;min-width:0;min-inline-size:0}.teinvit-rsvp-grid,.teinvit-rsvp-question-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 16px}.teinvit-rsvp-field label,.teinvit-rsvp-field input,.teinvit-rsvp-field textarea{display:block;width:100%}.teinvit-rsvp-question{margin-bottom:0}.teinvit-rsvp-choice-group{margin-top:6px}.teinvit-rsvp-choice-group label{display:block;margin-bottom:4px}.teinvit-rsvp-dependent{margin-top:8px;margin-left:16px}.teinvit-rsvp-dependent input{max-width:220px}.teinvit-separator{border:0;height:1px;background:linear-gradient(90deg,transparent,rgba(176,146,97,.7),transparent);margin:20px 0}.teinvit-rsvp-message-wrap textarea{width:100%;min-height:130px;resize:vertical}.teinvit-rsvp-submit-wrap{text-align:center;margin-top:14px}.teinvit-rsvp-submit-wrap button{min-width:170px}.teinvit-inline-error{display:block;color:#a00000;margin-top:4px}.teinvit-field-error{border-color:#a00000!important}.teinvit-rsvp-status{text-align:center;margin-top:10px}.teinvit-rsvp-status.is-ok{color:#176b2c}.teinvit-rsvp-status.is-error{color:#a00000}.teinvit-gifts-intro{text-align:center;margin-bottom:12px}.teinvit-gifts-intro h3{margin:0 0 6px}.teinvit-gifts-intro p{margin:0}.teinvit-gifts-table-wrap{display:block;width:100%;max-width:100%;min-width:0;overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;touch-action:pan-x}.teinvit-gifts-table{width:max-content;min-width:960px;max-width:none;border-collapse:collapse}.teinvit-gifts-table th,.teinvit-gifts-table td{padding:8px;border:1px solid rgba(0,0,0,.14);vertical-align:top}.teinvit-gifts-table th{background:#fafafa;white-space:nowrap}.teinvit-gifts-table th:nth-child(1),.teinvit-gifts-table td:nth-child(1){width:10ch;min-width:10ch;text-align:center}.teinvit-gifts-table th:nth-child(2),.teinvit-gifts-table td:nth-child(2){width:30ch;min-width:30ch}.teinvit-gifts-table th:nth-child(3),.teinvit-gifts-table td:nth-child(3){width:18ch;min-width:18ch;white-space:nowrap}.teinvit-gifts-table th:nth-child(4),.teinvit-gifts-table td:nth-child(4){width:48ch;min-width:48ch}.teinvit-gifts-table th:nth-child(5),.teinvit-gifts-table td:nth-child(5){width:16ch;min-width:16ch;white-space:nowrap}.teinvit-gift-status-reserved{color:#8a4b00;font-weight:600}.teinvit-gift-status-free{color:#176b2c;font-weight:600}
.teinvit-child-observations .teinvit-child-observation-option{display:flex;align-items:flex-start;gap:8px;margin-bottom:8px;line-height:1.35}.teinvit-child-observations .teinvit-child-observation-option input[type=checkbox]{flex:0 0 auto;width:auto;margin:2px 0 0}.teinvit-child-observations .teinvit-rsvp-dependent{margin-left:28px}.teinvit-child-observations .teinvit-rsvp-dependent input,.teinvit-child-observations .teinvit-rsvp-dependent textarea{width:100%;max-width:360px}
@media (max-width: 768px){.teinvit-slot-preview{padding:8px;margin-bottom:12px}.teinvit-rsvp-grid,.teinvit-rsvp-question-grid{grid-template-columns:1fr}.teinvit-surface-card{padding:14px}.teinvit-rsvp-dependent{margin-left:0}.teinvit-rsvp-dependent input{max-width:100%}}
</style>
<div class="teinvit-birthday-invitati">
  <?php if ( $deadline_expired ) : ?>
    <p style="padding:10px;border:1px solid #cc0000;background:#fff3f3;color:#900;">Perioada de confirmare a expirat. Formularul RSVP este dezactivat.</p>
  <?php endif; ?>

  <?php if ( ( $deadline_active && $deadline_raw !== '' ) || ( $show_theme && $theme_text !== '' ) || ( $show_dress && $dress_text !== '' ) ) : ?>
  <div class="teinvit-surface-card teinvit-info-card">
    <?php if ( $deadline_active && $deadline_raw !== '' ) : ?>
      <div class="teinvit-info-deadline-row"><strong>Data maximă pentru confirmări:</strong> <?php echo esc_html( $deadline_raw ); ?></div>
    <?php endif; ?>
    <?php if ( ( $show_dress && $dress_text !== '' ) || ( $show_theme && $theme_text !== '' ) ) : ?>
      <div class="teinvit-info-meta-row">
        <?php if ( $show_dress && $dress_text !== '' ) : ?>
          <div class="teinvit-info-pill"><strong>Dress code:</strong> <?php echo esc_html( $dress_text ); ?></div>
        <?php endif; ?>
        <?php if ( $show_theme && $theme_text !== '' ) : ?>
          <div class="teinvit-info-pill"><strong>Tematica petrecerii:</strong> <?php echo esc_html( $theme_text ); ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="teinvit-surface-card teinvit-rsvp-card">
    <form id="teinvit-birthday-rsvp-form" novalidate>
      <fieldset <?php disabled( $deadline_expired ); ?>>
        <h3 style="text-align:center;margin-top:0;">RSVP</h3>

        <div class="teinvit-rsvp-grid">
          <div class="teinvit-rsvp-field">
            <label for="birthday-rsvp-last-name"><?php echo $is_child_rsvp ? 'Nume părinte/tutore*' : 'Nume*'; ?></label>
            <input id="birthday-rsvp-last-name" name="guest_last_name" required>
          </div>
          <div class="teinvit-rsvp-field">
            <label for="birthday-rsvp-first-name"><?php echo $is_child_rsvp ? 'Prenume părinte/tutore*' : 'Prenume*'; ?></label>
            <input id="birthday-rsvp-first-name" name="guest_first_name" required>
          </div>
          <div class="teinvit-rsvp-field">
            <label for="birthday-rsvp-phone"><?php echo $is_child_rsvp ? 'Telefon părinte/tutore*' : 'Telefon*'; ?></label>
            <input id="birthday-rsvp-phone" name="guest_phone" required>
          </div>
          <div class="teinvit-rsvp-field">
            <label for="birthday-rsvp-email"><?php echo $is_child_rsvp ? 'Email părinte/tutore' : 'Email'; ?></label>
            <input id="birthday-rsvp-email" name="guest_email" type="email">
          </div>
        </div>
        <?php if ( ! $is_child_rsvp && ! $show_guest_count ) : ?>
          <input type="hidden" name="attending_people_count" value="<?php echo $show_party ? '0' : '1'; ?>">
        <?php endif; ?>

        <hr class="teinvit-separator">

        <?php if ( $is_child_rsvp && ! empty( $child_short_questions ) ) : ?>
        <div class="teinvit-rsvp-question-grid">
          <?php foreach ( $child_short_questions as $question_type ) : ?>
            <?php if ( $question_type === 'child_party' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Veți participa la petrecere?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="attending_party" value="1" required> DA</label>
                  <label><input type="radio" name="attending_party" value="0"> NU</label>
                </div>
              </div>
            <?php elseif ( $question_type === 'child_count' ) : ?>
              <div class="teinvit-rsvp-field teinvit-rsvp-question">
                <label for="birthday-rsvp-child-count">Pentru câți copii faceți confirmarea?</label>
                <input id="birthday-rsvp-child-count" name="child_participants_count" type="number" min="0" max="99">
              </div>
            <?php elseif ( $question_type === 'child_accompanying_adults' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Un părinte/adult însoțitor va rămâne la petrecere?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="child_accompanying_adult_stays" value="1"> DA</label>
                  <label><input type="radio" name="child_accompanying_adult_stays" value="0" checked> NU</label>
                </div>
                <div id="birthday-rsvp-child-adults-wrap" class="teinvit-rsvp-dependent" style="display:none;">
                  <label for="birthday-rsvp-child-adults-count">Câți adulți însoțitori vor rămâne?</label>
                  <input id="birthday-rsvp-child-adults-count" name="child_accompanying_adults_count" type="number" min="1" max="99">
                </div>
              </div>
            <?php elseif ( $question_type === 'child_vegetarian' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Doriți meniu vegetarian?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="vegetarian_requested" value="1"> DA</label>
                  <label><input type="radio" name="vegetarian_requested" value="0" checked> NU</label>
                </div>
                <div id="birthday-rsvp-vegetarian-wrap" class="teinvit-rsvp-dependent" style="display:none;">
                  <label for="birthday-rsvp-vegetarian-count">Câte meniuri vegetariene sunt necesare?</label>
                  <input id="birthday-rsvp-vegetarian-count" name="vegetarian_menus_count" type="number" min="1" max="99">
                </div>
              </div>
            <?php elseif ( $question_type === 'child_allergies' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Există alergii sau restricții alimentare?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="has_allergies" value="1"> DA</label>
                  <label><input type="radio" name="has_allergies" value="0" checked> NU</label>
                </div>
                <div id="birthday-rsvp-allergies-wrap" class="teinvit-rsvp-dependent" style="display:none;">
                  <label for="birthday-rsvp-allergy-details">Vă rugăm să menționați alergiile/restricțiile</label>
                  <textarea id="birthday-rsvp-allergy-details" name="allergy_details" rows="3"></textarea>
                </div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?php elseif ( ! empty( $rsvp_short_questions ) ) : ?>
        <div class="teinvit-rsvp-question-grid">
          <?php foreach ( $rsvp_short_questions as $question_type ) : ?>
            <?php if ( $question_type === 'guest_count' ) : ?>
              <div class="teinvit-rsvp-field teinvit-rsvp-question">
                <label for="birthday-rsvp-guest-count"><?php echo esc_html( $guest_count_question ); ?></label>
                <input id="birthday-rsvp-guest-count" name="attending_people_count" type="number" min="0" max="50">
              </div>
            <?php elseif ( $question_type === 'party' ) : ?>
              <div class="teinvit-rsvp-question">
                <label>Veți participa la petrecere?</label>
                <div class="teinvit-rsvp-choice-group">
                  <label><input type="radio" name="attending_party" value="1" required> DA</label>
                  <label><input type="radio" name="attending_party" value="0"> NU</label>
                </div>
              </div>
            <?php elseif ( $question_type === 'kids' ) : ?>
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
            <?php elseif ( $question_type === 'child_menu' ) : ?>
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
            <?php elseif ( $question_type === 'accommodation' ) : ?>
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
            <?php elseif ( $question_type === 'vegetarian' ) : ?>
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
            <?php elseif ( $question_type === 'allergies' ) : ?>
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
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ( $show_gifts_section && ! empty( $gifts ) ) : ?>
        <hr class="teinvit-separator">
        <div class="teinvit-gifts-intro">
          <h3><?php echo esc_html( $birthday_gift_title ); ?></h3>
          <p><?php echo esc_html( $birthday_gift_subtitle ); ?></p>
        </div>
        <div class="teinvit-gifts-table-wrap">
          <table class="teinvit-gifts-table">
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
                  <td><?php if ( ! empty( $gift['gift_link'] ) ) : ?><a href="<?php echo esc_url( $gift['gift_link'] ); ?>" target="_blank" rel="noopener">Vezi produsul</a><?php endif; ?></td>
                  <td><?php echo esc_html( $gift['gift_delivery_address'] ); ?></td>
                  <td class="<?php echo $is_reserved ? 'teinvit-gift-status-reserved' : 'teinvit-gift-status-free'; ?>"><?php echo $is_reserved ? 'Rezervat' : 'Disponibil'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ( ( $is_child_rsvp && ( $child_show_message || $child_show_special_observations ) ) || ( ! $is_child_rsvp && ( $show_message || $show_special_observations ) ) ) : ?>
        <hr class="teinvit-separator">
        <div class="teinvit-rsvp-message-wrap">
          <?php if ( ( $is_child_rsvp && $child_show_message ) || ( ! $is_child_rsvp && $show_message ) ) : ?>
          <div class="teinvit-rsvp-field">
            <label for="birthday-rsvp-message">Doriți să transmiteți un mesaj sărbătoritului/sărbătoriților?</label>
            <textarea id="birthday-rsvp-message" name="message_to_celebrants" rows="5"></textarea>
          </div>
          <?php endif; ?>
          <?php if ( $is_child_rsvp && $child_show_special_observations ) : ?>
          <div class="teinvit-rsvp-field" style="margin-top:12px;">
            <label>Aveți observații speciale pentru organizator?</label>
            <div class="teinvit-rsvp-choice-group teinvit-child-observations">
              <label class="teinvit-child-observation-option"><input type="checkbox" name="child_special_observations_options[]" value="pickup_time" id="birthday-rsvp-child-pickup-check"><span>Copilul trebuie preluat la o anumită oră.</span></label>
              <div id="birthday-rsvp-child-pickup-wrap" class="teinvit-rsvp-dependent" style="display:none;">
                <label for="birthday-rsvp-child-pickup-time">La ce oră trebuie preluat copilul?</label>
                <input id="birthday-rsvp-child-pickup-time" name="child_pickup_time" type="text" placeholder="ex. 18:30">
              </div>
              <label class="teinvit-child-observation-option"><input type="checkbox" name="child_special_observations_options[]" value="shy"><span>Copilul este mai timid.</span></label>
              <label class="teinvit-child-observation-option"><input type="checkbox" name="child_special_observations_options[]" value="restricted_activities" id="birthday-rsvp-child-restricted-check"><span>Copilul nu are voie anumite activități.</span></label>
              <div id="birthday-rsvp-child-restricted-wrap" class="teinvit-rsvp-dependent" style="display:none;">
                <label for="birthday-rsvp-child-restricted-activities">Ce activități trebuie evitate?</label>
                <textarea id="birthday-rsvp-child-restricted-activities" name="child_restricted_activities" rows="3"></textarea>
              </div>
              <label class="teinvit-child-observation-option"><input type="checkbox" name="child_special_observations_options[]" value="accompanied_start_only"><span>Copilul va veni însoțit doar la început.</span></label>
              <label class="teinvit-child-observation-option"><input type="checkbox" name="child_special_observations_options[]" value="other" id="birthday-rsvp-child-observations-other-check"><span>Alte observații.</span></label>
            </div>
            <div id="birthday-rsvp-child-observations-other-wrap" class="teinvit-rsvp-dependent" style="display:none;">
              <label for="birthday-rsvp-child-observations-other">Alte observații</label>
              <textarea id="birthday-rsvp-child-observations-other" name="child_special_observations_other" rows="4"></textarea>
            </div>
          </div>
          <?php elseif ( ! $is_child_rsvp && $show_special_observations ) : ?>
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
  const rsvpMode = <?php echo wp_json_encode( $birthday_rsvp_mode ); ?>;
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
    if (field.type === 'radio') {
      const question = field.closest('.teinvit-rsvp-question');
      if (question) {
        question.querySelectorAll('.teinvit-inline-error').forEach(function(node){
          if (node.getAttribute('data-radio-error') === field.name) node.remove();
        });
      }
      return;
    }
    const next = field.nextElementSibling;
    if (next && next.classList && next.classList.contains('teinvit-inline-error')) next.remove();
  }
  function setFieldError(field, message){
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
        radioError.textContent = message;
        return;
      }
    }
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
  bindConditional('child_accompanying_adult_stays', 'birthday-rsvp-child-adults-wrap');

  function syncChildObservationDependent(checkId, targetId){
    const checkbox = document.getElementById(checkId);
    const target = document.getElementById(targetId);
    if (!checkbox || !target) return;
    const refresh = function(){
      target.style.display = checkbox.checked ? '' : 'none';
      if (!checkbox.checked) {
        target.querySelectorAll('textarea,input,select').forEach(function(el){
          el.value = '';
          clearFieldError(el);
        });
      }
    };
    checkbox.addEventListener('change', refresh);
    refresh();
  }
  function syncChildOtherObservations(){
    syncChildObservationDependent('birthday-rsvp-child-pickup-check', 'birthday-rsvp-child-pickup-wrap');
    syncChildObservationDependent('birthday-rsvp-child-restricted-check', 'birthday-rsvp-child-restricted-wrap');
    syncChildObservationDependent('birthday-rsvp-child-observations-other-check', 'birthday-rsvp-child-observations-other-wrap');
  }
  syncChildOtherObservations();

  function syncPartyAdultsCount(){
    const field = byName('attending_people_count');
    const radios = form.querySelectorAll('[name="attending_party"]');
    if (!field || !radios.length) return;

    const refresh = function(){
      const party = checkedValue('attending_party');
      if (party === '0') {
        field.value = '0';
        if (field.type !== 'hidden') field.setAttribute('readonly', 'readonly');
        clearFieldError(field);
        return;
      }

      if (field.type !== 'hidden') {
        field.removeAttribute('readonly');
        if (party === '1' && field.value === '0') field.value = '';
      } else if (party === '1' && field.value === '0') {
        field.value = '1';
      }
    };

    radios.forEach(function(r){ r.addEventListener('change', refresh); });
    refresh();
  }
  syncPartyAdultsCount();

  function syncChildPartyState(){
    if (rsvpMode !== 'child') return;
    const childCount = byName('child_participants_count');
    const radios = form.querySelectorAll('[name="attending_party"]');
    if (!radios.length) return;

    const refresh = function(){
      const party = checkedValue('attending_party');
      if (party === '0') {
        if (childCount) {
          childCount.value = '0';
          childCount.setAttribute('readonly', 'readonly');
          clearFieldError(childCount);
        }
        ['child_accompanying_adult_stays','vegetarian_requested','has_allergies'].forEach(function(name){
          const no = form.querySelector('[name="' + name + '"][value="0"]');
          if (no) {
            no.checked = true;
            no.dispatchEvent(new Event('change', { bubbles: true }));
          }
        });
        return;
      }

      if (childCount) {
        childCount.removeAttribute('readonly');
        if (party === '1' && childCount.value === '0') childCount.value = '';
      }
    };

    radios.forEach(function(r){ r.addEventListener('change', refresh); });
    refresh();
  }
  syncChildPartyState();

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

    const partyField = form.querySelector('[name="attending_party"]');
    const partyValue = partyField ? checkedValue('attending_party') : '';
    if (partyField && partyValue === '') {
      errors.push('Alegeți dacă veți participa la petrecere.');
      setFieldError(partyField, 'Alegeți DA sau NU.');
    }

    if (rsvpMode === 'child') {
      const childField = byName('child_participants_count');
      const childRaw = childField ? String(childField.value || '').trim() : '';
      let childCount = childField ? intValue('child_participants_count', 0) : (partyValue === '0' ? 0 : 1);
      if (partyField && partyValue === '0') {
        if (childCount !== 0) {
          errors.push('Dacă nu participați la petrecere, numărul de copii trebuie să fie 0.');
          setFieldError(childField, 'Trebuie să fie 0 pentru răspuns NU.');
        } else if (childField) {
          childField.value = '0';
        }
        childCount = 0;
      } else if (childField && (!childRaw || childCount < 1 || childCount > 99)) {
        errors.push('Completați numărul de copii participanți.');
        setFieldError(childField, 'Completați un număr între 1 și 99.');
      }

      const adultStaysField = form.querySelector('[name="child_accompanying_adult_stays"]');
      const adultCountField = byName('child_accompanying_adults_count');
      const adultStays = adultStaysField ? boolValue('child_accompanying_adult_stays') : 0;
      const adultRaw = adultCountField ? String(adultCountField.value || '').trim() : '';
      const adultSubmitted = adultRaw === '' ? 0 : intValue('child_accompanying_adults_count', 0);
      let adultCount = adultStays ? adultSubmitted : 0;
      if (adultStays && (!adultRaw || adultCount < 1 || adultCount > 99)) {
        errors.push('Completați numărul de adulți însoțitori.');
        setFieldError(adultCountField, 'Completați un număr între 1 și 99.');
      }
      if (!adultStays && adultSubmitted !== 0) {
        errors.push('Dacă adultul însoțitor nu rămâne, numărul adulților trebuie să fie 0.');
        setFieldError(adultCountField, 'Trebuie să fie 0 pentru răspuns NU.');
      }
      if (partyField && partyValue === '0' && adultCount > 0) {
        errors.push('Dacă nu participați la petrecere, adulții însoțitori trebuie să fie 0.');
        setFieldError(adultCountField, 'Trebuie să fie 0 pentru răspuns NU.');
      }

      if (boolValue('vegetarian_requested')) {
        const vegCount = intValue('vegetarian_menus_count', 0);
        const knownTotal = Math.max(0, childCount + adultCount);
        const vegMax = knownTotal > 0 ? knownTotal : 99;
        if (vegCount < 1 || vegCount > vegMax) {
          errors.push('Numărul de meniuri vegetariene este invalid.');
          setFieldError(byName('vegetarian_menus_count'), 'Maxim: ' + vegMax);
        }
      }
      if (boolValue('has_allergies') && !String((byName('allergy_details') && byName('allergy_details').value) || '').trim()) {
        errors.push('Completați alergiile/restricțiile.');
        setFieldError(byName('allergy_details'), 'Completați alergiile/restricțiile.');
      }
      const pickupObs = document.getElementById('birthday-rsvp-child-pickup-check');
      const pickupTime = byName('child_pickup_time');
      if (pickupObs && pickupObs.checked && !String((pickupTime && pickupTime.value) || '').trim()) {
        errors.push('Completați ora la care copilul trebuie preluat.');
        setFieldError(pickupTime, 'Completați ora de preluare.');
      }
      const restrictedObs = document.getElementById('birthday-rsvp-child-restricted-check');
      const restrictedText = byName('child_restricted_activities');
      if (restrictedObs && restrictedObs.checked && !String((restrictedText && restrictedText.value) || '').trim()) {
        errors.push('Completați activitățile care trebuie evitate.');
        setFieldError(restrictedText, 'Completați activitățile de evitat.');
      }
      const otherObs = document.getElementById('birthday-rsvp-child-observations-other-check');
      const otherText = byName('child_special_observations_other');
      if (otherObs && otherObs.checked && !String((otherText && otherText.value) || '').trim()) {
        errors.push('Completați câmpul Alte observații.');
        setFieldError(otherText, 'Completați alte observații.');
      }
    } else {
    const peopleField = byName('attending_people_count');
    const peopleRaw = peopleField ? String(peopleField.value || '').trim() : '';
    let peopleCount = 0;
    if (partyField && partyValue === '0') {
      peopleCount = peopleRaw === '' ? 0 : intValue('attending_people_count', 0);
      if (peopleCount !== 0) {
        errors.push('Dacă nu participați la petrecere, numărul de adulți trebuie să fie 0.');
        setFieldError(peopleField, 'Trebuie să fie 0 pentru răspuns NU.');
      } else if (peopleField) {
        peopleField.value = '0';
      }
    } else {
      peopleCount = intValue('attending_people_count', 0);
      if (!peopleRaw || peopleCount < 1 || peopleCount > 50) {
        errors.push('Completați numărul de adulți participanți.');
        setFieldError(peopleField, 'Completați un număr între 1 și 50.');
      }
    }
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

    }

    if (errors.length) {
      const firstInvalid = form.querySelector('.teinvit-field-error');
      if (firstInvalid && typeof firstInvalid.focus === 'function') firstInvalid.focus();
      window.alert(errors.join('\n'));
      return;
    }

    const fd = new FormData(form);
    const payload = {};
    fd.forEach(function(value, key){
      if (key === 'gift_ids[]') {
        payload.gift_ids = payload.gift_ids || [];
        payload.gift_ids.push(value);
        return;
      }
      if (key === 'child_special_observations_options[]') {
        payload.child_special_observations_options = payload.child_special_observations_options || [];
        payload.child_special_observations_options.push(value);
        return;
      }
      payload[key] = value;
    });
    payload.birthday_rsvp_mode = rsvpMode;
    payload.gdpr_accepted = gdpr && gdpr.checked ? 1 : 0;
    payload.marketing_consent = byName('marketing_consent') && byName('marketing_consent').checked ? 1 : 0;
    ['attending_party','bringing_kids','child_menu_requested','needs_accommodation','vegetarian_requested','has_allergies','child_accompanying_adult_stays'].forEach(function(key){
      if (form.querySelector('[name="' + key + '"]')) payload[key] = boolValue(key);
    });
    const selectedGiftInputs = Array.prototype.slice.call(form.querySelectorAll('[name="gift_ids[]"]:checked'));

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
      selectedGiftInputs.forEach(function(input){
        input.checked = false;
        input.disabled = true;
        const row = input.closest('tr');
        const statusCell = row ? row.querySelector('td:last-child') : null;
        if (statusCell) {
          statusCell.textContent = 'Rezervat';
          statusCell.className = 'teinvit-gift-status-reserved';
        }
      });
      form.reset();
      ['bringing_kids','child_menu_requested','needs_accommodation','vegetarian_requested','has_allergies','child_accompanying_adult_stays'].forEach(function(name){
        const no = form.querySelector('[name="' + name + '"][value="0"]');
        if (no) no.checked = true;
      });
      ['bringing_kids','child_menu_requested','needs_accommodation','vegetarian_requested','has_allergies','child_accompanying_adult_stays','attending_party'].forEach(function(name){
        form.querySelectorAll('[name="' + name + '"]').forEach(function(r){ r.dispatchEvent(new Event('change', { bubbles: true })); });
      });
      ['birthday-rsvp-child-pickup-check','birthday-rsvp-child-restricted-check','birthday-rsvp-child-observations-other-check'].forEach(function(id){
        const checkbox = document.getElementById(id);
        if (checkbox) checkbox.dispatchEvent(new Event('change', { bubbles: true }));
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
