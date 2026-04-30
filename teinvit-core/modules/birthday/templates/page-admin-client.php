<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$token = get_query_var( 'teinvit_admin_client_token' );
$token = sanitize_text_field( (string) $token );
$inv = function_exists( 'teinvit_get_invitation_record' ) ? teinvit_get_invitation_record( $token, 'birthday' ) : null;
if ( ! is_array( $inv ) ) {
    echo '<p>Invitația nu a fost găsită.</p>';
    return;
}

$order = wc_get_order( (int) $inv['order_id'] );
if ( ! $order ) {
    echo '<p>Comanda nu a fost găsită.</p>';
    return;
}

$product_id = function_exists( 'teinvit_get_order_primary_product_id' ) ? (int) teinvit_get_order_primary_product_id( $order ) : 0;
$product = $product_id ? wc_get_product( $product_id ) : null;
$order_wapf = function_exists( 'teinvit_extract_order_wapf_field_map' ) ? teinvit_extract_order_wapf_field_map( $order ) : [];
$order_payload = function_exists( 'teinvit_build_invitation_payload_from_wapf_map' )
    ? teinvit_build_invitation_payload_from_wapf_map( 'birthday', $order_wapf, $product_id )
    : [ 'invitation' => [], 'wapf_fields' => $order_wapf ];
$order_invitation = isset( $order_payload['invitation'] ) && is_array( $order_payload['invitation'] ) ? $order_payload['invitation'] : [];
$order_pdf_url = (string) $order->get_meta( '_teinvit_pdf_url' );

$versions = function_exists( 'teinvit_get_versions_for_token_from_storage' ) ? teinvit_get_versions_for_token_from_storage( $token, 'birthday' ) : [];
usort( $versions, static function( $a, $b ) {
    return (int) $a['id'] <=> (int) $b['id'];
} );

$variants = [];
foreach ( $versions as $index => $row ) {
    $snap = json_decode( (string) ( $row['snapshot'] ?? '' ), true );
    $snap_inv = isset( $snap['invitation'] ) && is_array( $snap['invitation'] ) ? $snap['invitation'] : [];
    $snap_wapf = isset( $snap['wapf_fields'] ) && is_array( $snap['wapf_fields'] ) ? $snap['wapf_fields'] : [];

    if ( empty( $snap_inv ) && $index === 0 ) {
        $snap_inv = $order_invitation;
    }
    if ( empty( $snap_wapf ) && $index === 0 ) {
        $snap_wapf = $order_wapf;
    }

    $pdf_url = (string) ( $row['pdf_url'] ?? '' );
    if ( $index === 0 && $pdf_url === '' && $order_pdf_url !== '' ) {
        $pdf_url = esc_url_raw( $order_pdf_url );
    }

    $variants[] = [
        'id' => (int) $row['id'],
        'label' => 'Varianta ' . $index,
        'invitation' => $snap_inv,
        'wapf_fields' => $snap_wapf,
        'created_at' => (string) ( $row['created_at'] ?? '' ),
        'pdf_url' => $pdf_url,
        'pdf_status' => (string) ( $row['pdf_status'] ?? 'none' ),
    ];
}

if ( empty( $variants ) ) {
    $variants[] = [
        'id' => 0,
        'label' => 'Varianta 0',
        'invitation' => $order_invitation,
        'wapf_fields' => $order_wapf,
        'created_at' => '',
        'pdf_url' => $order_pdf_url,
        'pdf_status' => $order_pdf_url !== '' ? 'ready' : 'none',
    ];
}

$active_id = (int) ( $inv['active_version_id'] ?? 0 );
$selected_version_id = isset( $_GET['selected_version_id'] ) ? (int) $_GET['selected_version_id'] : 0;
$current = null;
if ( $selected_version_id > 0 ) {
    foreach ( $variants as $variant ) {
        if ( (int) $variant['id'] === $selected_version_id ) {
            $current = $variant;
            break;
        }
    }
}
if ( ! $current && $active_id > 0 ) {
    foreach ( $variants as $variant ) {
        if ( (int) $variant['id'] === $active_id ) {
            $current = $variant;
            break;
        }
    }
}
if ( ! $current ) {
    $current = $variants[0];
}

$current_invitation = isset( $current['invitation'] ) && is_array( $current['invitation'] ) ? $current['invitation'] : $order_invitation;
$current_wapf = isset( $current['wapf_fields'] ) && is_array( $current['wapf_fields'] ) ? $current['wapf_fields'] : $order_wapf;
$ui_selected_version_id = (int) ( $current['id'] ?? $active_id );
$config = function_exists( 'teinvit_birthday_config_with_defaults' )
    ? teinvit_birthday_config_with_defaults( is_array( $inv['config'] ?? null ) ? $inv['config'] : [] )
    : wp_parse_args( is_array( $inv['config'] ?? null ) ? $inv['config'] : [], function_exists( 'teinvit_default_rsvp_config_for_vertical' ) ? teinvit_default_rsvp_config_for_vertical( 'birthday' ) : [] );

$edits_free_remaining = max( 0, (int) ( $config['edits_free_remaining'] ?? 2 ) );
$edits_paid_remaining = max( 0, (int) ( $config['edits_paid_remaining'] ?? 0 ) );
$edits_remaining = $edits_free_remaining + $edits_paid_remaining;
$capabilities = function_exists( 'teinvit_capabilities_for_token' ) ? teinvit_capabilities_for_token( $token ) : [];
$token_state = isset( $capabilities['state'] ) ? (string) $capabilities['state'] : 'premium_native';
$basic_copy = function_exists( 'teinvit_vertical_basic_copy' ) ? teinvit_vertical_basic_copy( 'birthday' ) : [];
$buy_edits_url = add_query_arg( [ 'teinvit_buy_edits_token' => $token ], home_url( '/' ) );
$buy_premium_upgrade_url = add_query_arg( [ 'teinvit_buy_premium_upgrade_token' => $token ], home_url( '/' ) );
$guest_page_url = home_url( '/invitati/' . rawurlencode( $token ) );
$share_payload = function_exists( 'teinvit_vertical_share_payload' ) ? teinvit_vertical_share_payload( 'birthday', $current_invitation, $guest_page_url ) : [
    'title' => 'Invitație aniversare - Te Invit',
    'text' => 'Te invităm cu drag la petrecerea aniversară',
    'message' => 'Te invităm cu drag la petrecerea aniversară ' . $guest_page_url,
    'url' => $guest_page_url,
];
$share_icon_base = defined( 'TEINVIT_WEDDING_MODULE_URL' ) ? trailingslashit( TEINVIT_WEDDING_MODULE_URL . 'assets/icons/social' ) : '';
$download_pdf_nonce = wp_create_nonce( 'teinvit_download_pdf_' . $token );
$subtitle = function_exists( 'teinvit_join_ro_names' )
    ? teinvit_join_ro_names( isset( $current_invitation['celebrants'] ) && is_array( $current_invitation['celebrants'] ) ? $current_invitation['celebrants'] : [] )
    : '';
if ( $subtitle === '' ) {
    $subtitle = 'Invitație aniversare';
}

$preview_html = function_exists( 'teinvit_render_invitation_html_for_vertical' )
    ? teinvit_render_invitation_html_for_vertical( 'birthday', $current_invitation, $order, 'preview', $product_id )
    : '';
$apf_html = ( $product && function_exists( 'wapf_display_field_groups_for_product' ) ) ? wapf_display_field_groups_for_product( $product ) : '';
$show_deadline = ! empty( $config['show_rsvp_deadline'] );
$deadline_date = (string) ( $config['rsvp_deadline_date'] ?? '' );

$admin_toggle_fields = [
    'show_attending_party' => 'Permite confirmarea participării la petrecere',
    'show_guest_count' => 'Permite completarea numărului de persoane participante',
    'show_kids' => 'Permite confirmarea copiilor însoțitori',
    'show_child_menu' => 'Permite solicitarea meniului pentru copii',
    'show_accommodation' => 'Permite solicitarea de cazare',
    'show_vegetarian' => 'Permite selectarea meniului vegetarian',
    'show_allergies' => 'Permite menționarea alergiilor',
    'show_message' => 'Permite trimiterea unui mesaj către sărbătorit/sărbătoriți',
    'show_special_observations' => 'Permite completarea observațiilor speciale',
];
?>
<style>
.teinvit-admin-page{max-width:1200px;margin:20px auto;padding:16px}.teinvit-admin-title-card{border:1px solid #e5e5e5;padding:16px;border-radius:8px;background:#fff;margin:0 0 16px;text-align:center}.teinvit-admin-title-card h1{margin:0}.teinvit-admin-title-card h1+h1{margin-top:6px}
.teinvit-zone{border:1px solid #e5e5e5;padding:14px;border-radius:8px;background:#fff;margin:16px 0}.teinvit-two-col{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:20px}.teinvit-form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.teinvit-form-row label{display:block}.teinvit-form-row input[type=text]{width:100%}
.teinvit-admin-preview-block{display:block!important;min-height:320px;overflow:visible}.teinvit-admin-preview-block .teinvit-wedding{display:flex!important;justify-content:center!important;min-height:320px;padding:0}.teinvit-admin-page .teinvit-page,.teinvit-admin-page .teinvit-container{display:block!important;max-width:100%;overflow:visible}.teinvit-admin-page .teinvit-preview{display:block!important;visibility:visible!important;opacity:1!important;max-width:760px;margin:0 auto;overflow:hidden}
.teinvit-share-card h3{margin-top:0}.teinvit-share-actions,.teinvit-share-quick{display:flex;gap:8px;flex-wrap:wrap}.teinvit-share-quick{flex-direction:column;max-width:320px;margin-top:8px}.teinvit-share-row{display:flex;align-items:center;gap:10px}.teinvit-share-icon-wrap{width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center}.teinvit-share-icon-wrap img{width:18px;height:18px;display:block}.teinvit-share-social-btn{flex:1;display:inline-flex;align-items:center;justify-content:center;min-height:32px}
.teinvit-rsvp-toggle-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 16px}.teinvit-rsvp-toggle-grid label{display:block}.teinvit-pdf-share-status,.teinvit-share-status{margin-top:8px;font-size:13px;color:#2f3a45}
@media (max-width: 900px){.teinvit-two-col,.teinvit-form-row,.teinvit-rsvp-toggle-grid{grid-template-columns:1fr}.teinvit-admin-page{padding:10px}}
</style>
<div class="teinvit-admin-page teinvit-admin-page-birthday">
  <div class="teinvit-admin-title-card">
    <h1>Administrare invitație aniversare</h1>
    <h1><?php echo esc_html( $subtitle ); ?></h1>
  </div>

  <?php if ( $token_state === 'basic_pure' ) : ?>
  <div class="notice notice-warning" style="padding:10px;">
    <p><strong>Pachet Basic activ.</strong> <?php echo esc_html( preg_replace( '/^Pachet Basic activ\.\s*/', '', (string) ( $basic_copy['notice'] ?? '' ) ) ); ?></p>
    <?php if ( ! empty( $capabilities['can_buy_premium_upgrade'] ) ) : ?>
      <p><a href="<?php echo esc_url( $buy_premium_upgrade_url ); ?>" class="button button-primary">Upgrade la Premium</a></p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="teinvit-zone">
    <h3 style="text-align:center;margin-top:0;">Informații publicate pe pagina invitaților</h3>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-birthday-info-form">
      <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
      <input type="hidden" name="action" value="teinvit_birthday_save_invitation_info">
      <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">

      <label><input type="checkbox" id="date_confirm" name="date_confirm" value="1" <?php checked( $show_deadline ); ?>> Doresc afișarea datei limită pentru confirmări</label>
      <div id="selecteaza-data-wrap" style="margin-top:10px;<?php echo $show_deadline ? '' : 'display:none;'; ?>" class="acf-field acf-field-date-picker" data-name="selecteaza_data" data-type="date_picker">
        <label for="selecteaza_data">Selectează data</label>
        <div class="acf-input">
          <div class="acf-date-picker acf-input-wrap" data-date_format="dd/mm/yy" data-display_format="dd/mm/yy" data-first_day="1">
            <input type="text" id="selecteaza_data" name="selecteaza_data" placeholder="zz/ll/aaaa" value="<?php echo esc_attr( $deadline_date ); ?>" autocomplete="off" class="input">
          </div>
        </div>
      </div>

      <div class="teinvit-form-row" style="margin-top:12px;">
        <label>
          <input type="checkbox" name="show_birthday_party_theme" value="1" <?php checked( ! empty( $config['show_birthday_party_theme'] ) ); ?>>
          Afișează tematica petrecerii
          <input type="text" name="birthday_party_theme_text" value="<?php echo esc_attr( (string) ( $config['birthday_party_theme_text'] ?? '' ) ); ?>" placeholder="Tematica petrecerii">
        </label>
        <label>
          <input type="checkbox" name="show_birthday_dress_code" value="1" <?php checked( ! empty( $config['show_birthday_dress_code'] ) ); ?>>
          Afișează dress code / ținută recomandată
          <input type="text" name="birthday_dress_code_text" value="<?php echo esc_attr( (string) ( $config['birthday_dress_code_text'] ?? '' ) ); ?>" placeholder="Dress code">
        </label>
      </div>

      <?php if ( ! empty( $capabilities['can_save_invitation_info'] ) ) : ?>
        <p><button type="submit" class="button">Salvează informațiile</button></p>
      <?php else : ?>
        <p><em><?php echo esc_html( (string) ( $basic_copy['deadline_locked'] ?? 'Publicarea datei limită pe pagina invitaților este disponibilă după upgrade la Premium.' ) ); ?></em></p>
      <?php endif; ?>
    </form>
  </div>

  <div class="teinvit-zone teinvit-two-col">
    <div>
      <div id="teinvit-vertical-product-preview" class="teinvit-admin-preview-block" data-product-id="<?php echo (int) $product_id; ?>">
        <?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      </div>

      <h3>Alege varianta afișată invitaților:</h3>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-publish-form">
        <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
        <input type="hidden" name="action" value="teinvit_birthday_set_active_version">
        <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
        <?php foreach ( $variants as $variant ) : ?>
          <label style="display:block;margin-bottom:6px;">
            <input type="radio" name="active_version_id" value="<?php echo (int) $variant['id']; ?>" <?php checked( (int) $variant['id'], $ui_selected_version_id ); ?> class="teinvit-variant-radio">
            <?php echo esc_html( $variant['label'] ); ?>
            <?php if ( ! empty( $variant['pdf_url'] ) ) : ?>
              <?php $download_pdf_url = add_query_arg( [ 'action' => 'teinvit_download_variant_pdf', 'token' => $token, 'version_id' => (int) $variant['id'], '_wpnonce' => $download_pdf_nonce ], admin_url( 'admin-post.php' ) ); ?>
              <span style="display:inline-flex;gap:8px;align-items:center;margin-left:8px;">
                <a href="<?php echo esc_url( $download_pdf_url ); ?>" class="button">Descarcă PDF</a>
                <button type="button" class="button teinvit-share-pdf-btn" data-pdf-url="<?php echo esc_attr( $variant['pdf_url'] ); ?>">Distribuie PDF</button>
              </span>
            <?php elseif ( ( $variant['pdf_status'] ?? '' ) === 'processing' ) : ?>
              — <em>PDF în curs...</em>
            <?php elseif ( ( $variant['pdf_status'] ?? '' ) === 'failed' ) : ?>
              — <em>PDF eșuat</em>
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
        <?php if ( ! empty( $capabilities['can_set_active_version'] ) ) : ?>
          <button type="submit" class="button button-primary">Publică</button>
        <?php else : ?>
          <p><em><?php echo esc_html( (string) ( $basic_copy['publish_locked'] ?? 'Publicarea de versiuni este disponibilă după upgrade la Premium.' ) ); ?></em></p>
        <?php endif; ?>
      </form>
      <p id="teinvit-pdf-share-status" class="teinvit-pdf-share-status" aria-live="polite"></p>

      <div class="teinvit-zone teinvit-admin-rsvp-settings">
        <h3 style="text-align:center;margin-top:0;">Setările formularului de confirmare</h3>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-rsvp-config-form">
          <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
          <input type="hidden" name="action" value="teinvit_birthday_save_rsvp_config">
          <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
          <div class="teinvit-rsvp-toggle-grid">
            <?php foreach ( $admin_toggle_fields as $config_key => $label ) : ?>
              <label>
                <input type="checkbox" name="<?php echo esc_attr( $config_key ); ?>" value="1" <?php checked( ! empty( $config[ $config_key ] ) ); ?>>
                <?php echo esc_html( $label ); ?>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if ( ! empty( $capabilities['can_save_rsvp_config'] ) ) : ?>
            <p><button type="submit" class="button">Publică selecțiile</button></p>
          <?php else : ?>
            <p><em><?php echo esc_html( (string) ( $basic_copy['rsvp_locked'] ?? 'Configurările RSVP avansate sunt disponibile după upgrade la Premium.' ) ); ?></em></p>
          <?php endif; ?>
        </form>
      </div>

      <p style="margin-top:8px;">
        <?php if ( ! empty( $capabilities['can_save_rsvp_config'] ) ) : ?>
          <a href="<?php echo esc_url( $guest_page_url ); ?>" target="_blank" rel="noopener">Vezi pagina invitaților</a>
        <?php else : ?>
          <em><?php echo esc_html( (string) ( $basic_copy['guest_page_locked'] ?? 'Pagina personalizată a invitaților tăi este disponibilă după upgrade la Premium.' ) ); ?></em>
        <?php endif; ?>
      </p>

      <?php if ( ! empty( $capabilities['can_share_invitation'] ) ) : ?>
      <div class="teinvit-zone teinvit-share-card" id="teinvit-share-card">
        <h3>Distribuie invitația</h3>
        <div class="teinvit-share-actions">
          <button type="button" class="button button-primary" id="teinvit-share-native">Distribuie</button>
          <button type="button" class="button" id="teinvit-share-copy-main">Copiază link</button>
        </div>
        <div class="teinvit-share-quick">
          <?php if ( $share_icon_base !== '' ) : ?>
          <div class="teinvit-share-row">
            <span class="teinvit-share-icon-wrap"><img src="<?php echo esc_url( $share_icon_base . 'facebook.svg' ); ?>" alt="" aria-hidden="true"></span>
            <a class="button teinvit-share-social-btn" href="<?php echo esc_url( 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $guest_page_url ) ); ?>" target="_blank" rel="noopener">Facebook</a>
          </div>
          <div class="teinvit-share-row">
            <span class="teinvit-share-icon-wrap"><img src="<?php echo esc_url( $share_icon_base . 'whatsapp.svg' ); ?>" alt="" aria-hidden="true"></span>
            <a class="button teinvit-share-social-btn" href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( (string) ( $share_payload['message'] ?? '' ) ) ); ?>" target="_blank" rel="noopener">WhatsApp</a>
          </div>
          <div class="teinvit-share-row">
            <span class="teinvit-share-icon-wrap"><img src="<?php echo esc_url( $share_icon_base . 'instagram.svg' ); ?>" alt="" aria-hidden="true"></span>
            <button type="button" class="button teinvit-share-social-btn" id="teinvit-share-instagram">Instagram</button>
          </div>
          <?php endif; ?>
        </div>
        <p class="teinvit-share-status" id="teinvit-share-status" aria-live="polite"></p>
      </div>
      <?php endif; ?>

      <div class="teinvit-zone teinvit-admin-gifts">
        <h3 style="text-align:center;margin-top:0;">Lista de cadouri</h3>
        <?php if ( ! empty( $capabilities['can_manage_gifts'] ) ) : ?>
          <p><em>Configurarea completă a cadourilor pentru Birthday se activează într-o fază următoare.</em></p>
        <?php else : ?>
          <p><em><?php echo esc_html( (string) ( $basic_copy['gifts_locked'] ?? 'Activarea listei de Cadouri pe pagina invitaților este disponibilă doar pentru pachetul Premium.' ) ); ?></em></p>
        <?php endif; ?>
      </div>
    </div>

    <div class="teinvit-apf-col" data-product-page-preselected-id="<?php echo (int) $product_id; ?>">
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-save-form" class="cart" data-product-page-preselected-id="<?php echo (int) $product_id; ?>">
        <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
        <input type="hidden" name="action" value="teinvit_birthday_save_version_snapshot">
        <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
        <input type="hidden" name="teinvit_parent_checked_json" id="teinvit-parent-checked-json" value="">

        <?php if ( $apf_html !== '' ) : ?>
          <?php echo $apf_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php else : ?>
          <p>Câmpurile APF/WAPF nu sunt disponibile pentru acest produs.</p>
        <?php endif; ?>

        <?php if ( ! empty( $capabilities['can_save_version_snapshot'] ) ) : ?>
          <p id="teinvit-edits-counter"><?php echo (int) $edits_remaining; ?> modificări disponibile<?php if ( $edits_paid_remaining > 0 ) : ?> (<?php echo (int) $edits_paid_remaining; ?> cumpărate)<?php endif; ?></p>
        <?php endif; ?>
        <?php if ( empty( $capabilities['can_save_version_snapshot'] ) ) : ?>
          <p><em><?php echo esc_html( (string) ( $basic_copy['content_locked'] ?? 'Editările de conținut și salvarea versiunilor sunt blocate pe pachetul Basic.' ) ); ?></em></p>
          <?php if ( ! empty( $capabilities['can_buy_premium_upgrade'] ) ) : ?>
            <a href="<?php echo esc_url( $buy_premium_upgrade_url ); ?>" class="button">Upgrade la Premium</a>
          <?php endif; ?>
        <?php elseif ( $edits_remaining > 0 ) : ?>
          <button type="submit" class="button button-primary" id="teinvit-save-btn">Salvează modificările</button>
        <?php else : ?>
          <?php if ( ! empty( $capabilities['can_buy_extra_edits'] ) ) : ?>
            <a href="<?php echo esc_url( $buy_edits_url ); ?>" class="button" target="_blank" rel="noopener">Cumpără modificări suplimentare</a>
          <?php endif; ?>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>
<script>
(function(){
  window.teinvitBirthdayPreviewConfig = Object.assign({}, window.teinvitBirthdayPreviewConfig || {}, {
    previewBuildUrl: <?php echo wp_json_encode( esc_url_raw( rest_url( 'teinvit/v2/preview/build' ) ) ); ?>,
    token: <?php echo wp_json_encode( (string) $token ); ?>,
    productId: <?php echo (int) $product_id; ?>
  });
  window.TEINVIT_INVITATION_DATA = <?php echo wp_json_encode( $current_invitation ); ?>;

  const variants = <?php echo wp_json_encode( $variants ); ?>;
  const initialWapf = <?php echo wp_json_encode( $current_wapf ); ?>;
  const baseUrl = <?php echo wp_json_encode( home_url( '/admin-client/' . rawurlencode( $token ) ) ); ?>;
  const shareUrl = <?php echo wp_json_encode( $guest_page_url ); ?>;
  const shareTitle = <?php echo wp_json_encode( (string) ( $share_payload['title'] ?? 'Invitație aniversare - Te Invit' ) ); ?>;
  const shareText = <?php echo wp_json_encode( (string) ( $share_payload['text'] ?? 'Te invităm cu drag la petrecerea aniversară' ) ); ?>;

  function splitSelected(raw){
    return String(raw || '').split(',').map(function(s){ return s.trim(); }).filter(Boolean);
  }

  function setWapfValues(map){
    const groups = {};
    document.querySelectorAll('#teinvit-save-form [name^="wapf[field_"]').forEach(function(el){
      const m = el.name.match(/^wapf\[field_([^\]]+)\]/);
      if (!m) return;
      const id = m[1].replace(/_(?:clone_)?\d+$/, '');
      if (!groups[id]) groups[id] = [];
      groups[id].push(el);
    });

    Object.keys(groups).forEach(function(id){
      const raw = map && Object.prototype.hasOwnProperty.call(map, id) ? map[id] : '';
      const selected = splitSelected(raw);
      groups[id].forEach(function(el){
        if (el.type === 'hidden' && el.classList.contains('wapf-tf-h')) return;
        if (el.type === 'checkbox') {
          const label = el.closest('label') && el.closest('label').textContent ? el.closest('label').textContent.trim() : '';
          const selectedLower = selected.map(function(v){ return String(v).toLowerCase(); });
          el.checked = selected.includes(el.value) || selected.includes(label) || selectedLower.includes(String(el.value || '').toLowerCase()) || selectedLower.includes(String(label || '').toLowerCase());
        } else if (el.type === 'radio') {
          const label = el.closest('label') && el.closest('label').textContent ? el.closest('label').textContent.trim() : '';
          el.checked = String(el.value) === String(raw) || String(label).trim().toLowerCase() === String(raw).trim().toLowerCase();
        } else if (el.tagName === 'SELECT') {
          const byValue = Array.from(el.options || []).find(function(o){ return String(o.value) === String(raw); });
          const byLabel = Array.from(el.options || []).find(function(o){ return String(o.text).trim() === String(raw).trim(); });
          el.value = byValue ? byValue.value : (byLabel ? byLabel.value : String(raw));
        } else {
          el.value = String(raw || '');
        }
      });
    });

    document.dispatchEvent(new Event('input', { bubbles: true }));
    document.dispatchEvent(new Event('change', { bubbles: true }));
    document.dispatchEvent(new CustomEvent('teinvit:variant-applied'));
  }

  document.addEventListener('DOMContentLoaded', function(){
    setWapfValues(initialWapf || {});

    const deadlineCb = document.getElementById('date_confirm');
    const deadlineWrap = document.getElementById('selecteaza-data-wrap');
    if (deadlineCb && deadlineWrap) {
      deadlineCb.addEventListener('change', function(){ deadlineWrap.style.display = deadlineCb.checked ? '' : 'none'; });
    }
    if (window.acf && typeof window.acf.doAction === 'function') {
      window.acf.doAction('ready');
      window.acf.doAction('append', window.jQuery ? window.jQuery('#teinvit-birthday-info-form') : null);
    }
    if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.datepicker === 'function') {
      window.jQuery('#selecteaza_data').datepicker({ dateFormat: 'dd/mm/yy' });
    }

    document.querySelectorAll('.teinvit-variant-radio').forEach(function(radio){
      radio.addEventListener('change', function(){
        const id = parseInt(radio.value || '0', 10);
        const found = variants.find(function(v){ return parseInt(v.id || '0', 10) === id; });
        if (found && found.wapf_fields) {
          setWapfValues(found.wapf_fields);
        }
        window.location.href = baseUrl + '?selected_version_id=' + encodeURIComponent(id);
      });
    });
  });

  function fallbackCopy(text){
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', 'readonly');
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    ta.setSelectionRange(0, ta.value.length);
    const ok = document.execCommand('copy');
    document.body.removeChild(ta);
    return !!ok;
  }
  async function copyValue(value){
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      try {
        await navigator.clipboard.writeText(value);
        return true;
      } catch (e) {}
    }
    return fallbackCopy(value);
  }
  function setShareStatus(message){
    const box = document.getElementById('teinvit-share-status');
    if (box) box.textContent = String(message || '');
  }
  function setPdfShareStatus(message){
    const box = document.getElementById('teinvit-pdf-share-status');
    if (box) box.textContent = String(message || '');
  }

  const nativeBtn = document.getElementById('teinvit-share-native');
  if (nativeBtn) {
    nativeBtn.addEventListener('click', async function(){
      if (navigator.share && typeof navigator.share === 'function') {
        try {
          await navigator.share({ title: shareTitle, text: shareText, url: shareUrl });
          setShareStatus('Invitația a fost pregătită pentru distribuire.');
          return;
        } catch (e) {
          if (e && e.name === 'AbortError') return;
        }
      }
      setShareStatus('Distribuirea directă nu este disponibilă aici. Folosește butoanele rapide sau copierea linkului.');
    });
  }
  const copyBtn = document.getElementById('teinvit-share-copy-main');
  if (copyBtn) {
    copyBtn.addEventListener('click', async function(){
      setShareStatus((await copyValue(shareUrl)) ? 'Link copiat.' : 'Nu am putut copia automat linkul.');
    });
  }
  const instagramBtn = document.getElementById('teinvit-share-instagram');
  if (instagramBtn) {
    instagramBtn.addEventListener('click', async function(){
      await copyValue(shareUrl);
      setShareStatus('Instagram nu permite distribuire directă web a linkului. Am copiat linkul pentru lipire manuală.');
    });
  }
  document.querySelectorAll('.teinvit-share-pdf-btn').forEach(function(btn){
    btn.addEventListener('click', async function(){
      const pdfUrl = btn.getAttribute('data-pdf-url') || '';
      if (!pdfUrl) {
        setPdfShareStatus('Linkul PDF nu este disponibil.');
        return;
      }
      if (navigator.share && typeof navigator.share === 'function') {
        try {
          await navigator.share({ title: 'PDF invitație', text: 'Poți vedea invitația în format PDF aici:', url: pdfUrl });
          setPdfShareStatus('PDF pregătit pentru distribuire.');
          return;
        } catch (e) {
          if (e && e.name === 'AbortError') return;
        }
      }
      setPdfShareStatus((await copyValue(pdfUrl)) ? 'Linkul PDF a fost copiat.' : 'Nu am putut copia automat linkul PDF.');
    });
  });
})();
</script>
