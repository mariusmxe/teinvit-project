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
$birthday_rsvp_mode = function_exists( 'teinvit_birthday_rsvp_mode_from_config' ) ? teinvit_birthday_rsvp_mode_from_config( $config ) : ( ( $config['birthday_rsvp_mode'] ?? 'adult' ) === 'child' ? 'child' : 'adult' );

$edits_free_remaining = max( 0, (int) ( $config['edits_free_remaining'] ?? 2 ) );
$edits_paid_remaining = max( 0, (int) ( $config['edits_paid_remaining'] ?? 0 ) );
$edits_remaining = $edits_free_remaining + $edits_paid_remaining;
$capabilities = function_exists( 'teinvit_capabilities_for_token' ) ? teinvit_capabilities_for_token( $token ) : [];
$can_save_invitation_info = ! empty( $capabilities['can_save_invitation_info'] );
$token_state = isset( $capabilities['state'] ) ? (string) $capabilities['state'] : 'premium_native';
$show_gifts_section = ! empty( $config['show_gifts_section'] );
$gifts_summary = function_exists( 'teinvit_birthday_build_gifts_summary_for_token' ) ? teinvit_birthday_build_gifts_summary_for_token( $token, $config ) : [ 'total_slots' => 20, 'used_slots' => 0, 'available_slots' => 20 ];
$gifts_max_slots = max( 0, (int) ( $gifts_summary['total_slots'] ?? 20 ) );
$gift_rows_export = [];
$gifts_table = function_exists( 'teinvit_birthday_gifts_table_for_token' ) ? teinvit_birthday_gifts_table_for_token( $token ) : '';
$rsvp_table = function_exists( 'teinvit_birthday_rsvp_table_for_token' ) ? teinvit_birthday_rsvp_table_for_token( $token ) : '';
if ( $gifts_table !== '' && $rsvp_table !== '' ) {
    global $wpdb;
    $gift_rows = $wpdb->get_results( $wpdb->prepare( "SELECT g.*, rs.guest_first_name, rs.guest_last_name, rs.guest_phone FROM {$gifts_table} g LEFT JOIN {$rsvp_table} rs ON rs.id = g.reserved_by_rsvp_id WHERE g.token = %s ORDER BY g.id ASC", $token ), ARRAY_A );
    $gift_rows = is_array( $gift_rows ) ? $gift_rows : [];
    foreach ( $gift_rows as $row ) {
        $phone = trim( (string) ( $row['guest_phone'] ?? '' ) );
        if ( strpos( $phone, '+407' ) === 0 ) {
            $phone = '0' . substr( $phone, 3 );
        }

        $reserved_by = 'Disponibil';
        if ( (string) ( $row['status'] ?? '' ) === 'reserved' ) {
            $reserved_by = trim( (string) ( $row['guest_first_name'] ?? '' ) . ' ' . (string) ( $row['guest_last_name'] ?? '' ) );
            if ( $phone !== '' ) {
                $reserved_by = trim( $reserved_by . ' ' . $phone );
            }
            if ( $reserved_by === '' ) {
                $reserved_by = 'Rezervat';
            }
        }

        $gift_rows_export[] = [
            'gift_id' => (string) ( $row['gift_id'] ?? '' ),
            'gift_name' => (string) ( $row['gift_name'] ?? '' ),
            'gift_link' => (string) ( $row['gift_link'] ?? '' ),
            'gift_delivery_address' => (string) ( $row['gift_delivery_address'] ?? '' ),
            'include_in_public' => ! empty( $row['include_in_public'] ) ? 1 : 0,
            'published_locked' => ! empty( $row['published_locked'] ) ? 1 : 0,
            'status' => (string) ( $row['status'] ?? 'free' ),
            'reserved_by' => $reserved_by,
        ];
    }
}
$gifts_remaining = max( 0, (int) ( $gifts_summary['available_slots'] ?? $gifts_max_slots ) );
$buy_gifts_url = add_query_arg( [ 'teinvit_buy_gifts_token' => $token ], home_url( '/' ) );
$catalog = function_exists( 'teinvit_get_catalog_for_token' ) ? teinvit_get_catalog_for_token( $token ) : [];
$gifts_slots_per_purchase = function_exists( 'teinvit_catalog_first_extra_gifts_slots' ) ? (int) teinvit_catalog_first_extra_gifts_slots( $catalog, 10 ) : 10;
$buy_gifts_cta_label = 'Cumpără pachet +' . max( 1, $gifts_slots_per_purchase );
$report_sets = function_exists( 'teinvit_birthday_build_rsvp_report_sets' ) ? teinvit_birthday_build_rsvp_report_sets( $token ) : [ 'history' => [], 'unique' => [], 'multiple_phones_count' => 0, 'unique_phones_count' => 0, 'submissions_count' => 0 ];
$report_unique = is_array( $report_sets['unique'] ?? null ) ? $report_sets['unique'] : [];
$report_history = is_array( $report_sets['history'] ?? null ) ? $report_sets['history'] : [];
$report_kpis = function_exists( 'teinvit_birthday_build_rsvp_report_kpis' ) ? teinvit_birthday_build_rsvp_report_kpis( $report_sets, $config ) : [];
$report_include_mode = function_exists( 'teinvit_birthday_report_sets_include_mode' ) ? teinvit_birthday_report_sets_include_mode( $report_sets ) : false;
$report_headers = function_exists( 'teinvit_birthday_report_headers' ) ? teinvit_birthday_report_headers( $report_include_mode ) : [];
$report_export_url = wp_nonce_url( admin_url( 'admin-post.php?action=teinvit_birthday_export_guest_report&token=' . rawurlencode( $token ) ), 'teinvit_admin_' . $token );
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
$admin_child_toggle_fields = [
    'child_show_attending_party' => 'Permite confirmarea participării la petrecere',
    'child_show_children_count' => 'Permite completarea numărului de copii participanți',
    'child_show_accompanying_adults' => 'Permite completarea numărului de adulți însoțitori care rămân la petrecere',
    'child_show_allergies' => 'Permite menționarea alergiilor sau restricțiilor alimentare',
    'child_show_vegetarian' => 'Permite selectarea meniului vegetarian',
    'child_show_special_observations' => 'Permite completarea observațiilor speciale pentru organizator',
    'child_show_message' => 'Permite trimiterea unui mesaj către sărbătorit/sărbătoriți',
];
?>
<style>
.teinvit-admin-page{max-width:1200px;margin:20px auto;padding:16px}.teinvit-admin-page-birthday,.teinvit-admin-page-birthday *{box-sizing:border-box}.teinvit-admin-title-card{border:1px solid #e5e5e5;padding:16px;border-radius:8px;background:#fff;margin:0 0 16px;text-align:center}.teinvit-admin-title-card h1{margin:0}.teinvit-admin-title-card h1+h1{margin-top:6px}
.teinvit-zone{border:1px solid #e5e5e5;padding:14px;border-radius:8px;background:#fff;margin:16px 0}.teinvit-two-col{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:20px;align-items:start}.teinvit-form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.teinvit-form-row label{display:block}.teinvit-form-row input[type=text]{width:100%}
.teinvit-info-form{display:flex;flex-direction:column;align-items:center;gap:12px}.teinvit-info-deadline-toggle{text-align:center}.teinvit-info-date-wrap{width:min(260px,100%);text-align:center}.teinvit-info-date-wrap .acf-input,.teinvit-info-date-wrap .acf-input-wrap{width:100%}.teinvit-info-date-wrap input[type=text]{max-width:220px;text-align:center}.teinvit-info-free-text-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;width:100%;max-width:820px}.teinvit-info-text-card{display:flex;flex-direction:column;gap:8px}.teinvit-info-text-card input[type=text]{width:100%}.teinvit-info-actions{text-align:center;margin:4px 0 0}.teinvit-apf-col{min-width:0}.teinvit-apf-col .wapf-wrapper,.teinvit-apf-col .wapf,.teinvit-apf-col form.cart{max-width:100%}.teinvit-admin-page-birthday .teinvit-apf-col #teinvit-save-form{display:block;width:100%;max-width:100%;margin:0}.teinvit-admin-page-birthday .teinvit-admin-gifts{display:block;width:100%;max-width:100%;clear:both}.teinvit-admin-gifts h3{text-align:center;margin-top:0}
.teinvit-admin-preview-block{display:block!important;min-height:320px;overflow:visible}.teinvit-admin-preview-block .teinvit-wedding{display:flex!important;justify-content:center!important;min-height:320px;padding:0}.teinvit-admin-page .teinvit-page,.teinvit-admin-page .teinvit-container{display:block!important;max-width:100%;overflow:visible}.teinvit-admin-page .teinvit-preview{display:block!important;visibility:visible!important;opacity:1!important;max-width:760px;margin:0 auto;overflow:hidden}
.teinvit-share-card h3{margin-top:0}.teinvit-share-help{margin:0 0 10px}.teinvit-share-actions{display:flex;gap:8px;flex-wrap:wrap}.teinvit-share-quick{display:flex;flex-direction:column;gap:8px;margin-top:8px;max-width:320px}.teinvit-share-row{display:flex;align-items:center;gap:10px}.teinvit-share-icon-wrap{width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center;flex:0 0 26px}.teinvit-share-icon-wrap img{width:18px;height:18px;display:block}.teinvit-share-social-btn{flex:1;display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:4px 10px;line-height:1.2;text-align:center}
.teinvit-admin-page-birthday .teinvit-variant-pdf-actions{display:inline-flex;gap:8px;align-items:center;margin-left:8px;vertical-align:middle;flex-wrap:wrap;max-width:100%}.teinvit-admin-page-birthday .teinvit-variant-pdf-actions .button{line-height:1.2;min-height:28px;padding:3px 10px}
.teinvit-rsvp-mode-choice{display:flex;gap:14px;flex-wrap:wrap;justify-content:center;margin:0 0 12px}.teinvit-rsvp-mode-choice label{display:inline-flex;align-items:center;gap:6px}.teinvit-rsvp-mode-panel[hidden]{display:none!important}.teinvit-rsvp-toggle-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 16px}.teinvit-rsvp-toggle-grid label{display:block}.teinvit-pdf-share-status,.teinvit-share-status{margin-top:8px;font-size:13px;color:#2f3a45}
.teinvit-gifts-table-wrap,.teinvit-report-table-wrap{width:100%;overflow-x:auto;background:#fff}.teinvit-gifts-table,.teinvit-report-table{width:max-content;min-width:100%;border-collapse:collapse}.teinvit-gifts-table th,.teinvit-gifts-table td,.teinvit-report-table th,.teinvit-report-table td{border:1px solid #ddd;padding:8px;vertical-align:top}.teinvit-gifts-table input[type=text],.teinvit-gifts-table input[type=url],.teinvit-gifts-table textarea{width:100%}.teinvit-gifts-table textarea{min-height:56px;resize:vertical}.teinvit-gifts-actions,.teinvit-report-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:10px}.teinvit-report-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.teinvit-report-card{border:1px solid #ddd;padding:10px;border-radius:8px;background:#fafafa}.teinvit-report-row-multi{background:#fff2f2}.teinvit-report-table td:nth-last-child(1),.teinvit-report-table td:nth-last-child(2){width:42ch;min-width:42ch;white-space:normal;word-break:break-word}
@media (max-width: 1024px){.teinvit-admin-page-birthday .teinvit-two-col{grid-template-columns:minmax(0,1fr)!important}}
@media (max-width: 768px){.teinvit-admin-page-birthday{padding:10px;max-width:100%;overflow-x:hidden}.teinvit-admin-page-birthday .teinvit-two-col{display:grid!important;grid-template-columns:minmax(0,1fr)!important}.teinvit-admin-page-birthday .teinvit-two-col>div,.teinvit-admin-page-birthday .teinvit-zone,.teinvit-admin-page-birthday .teinvit-apf-col,.teinvit-admin-page-birthday #teinvit-save-form,.teinvit-admin-page-birthday .teinvit-admin-preview-block,.teinvit-admin-page-birthday .teinvit-share-card,.teinvit-admin-page-birthday .teinvit-admin-rsvp-settings{width:100%!important;max-width:100%!important;min-width:0}.teinvit-admin-page-birthday .teinvit-admin-preview-block{display:block!important;order:1;overflow:hidden}.teinvit-admin-page-birthday .teinvit-admin-preview-block .teinvit-wedding,.teinvit-admin-page-birthday .teinvit-admin-preview-block .teinvit-page,.teinvit-admin-page-birthday .teinvit-admin-preview-block .teinvit-container,.teinvit-admin-page-birthday .teinvit-admin-preview-block .teinvit-preview{width:100%!important;max-width:100%!important;min-width:0}.teinvit-admin-page-birthday .teinvit-admin-preview-block .teinvit-wedding{display:block!important}.teinvit-admin-page-birthday .teinvit-admin-preview-block .teinvit-preview{aspect-ratio:148/210!important;height:auto!important;min-height:0!important;overflow:hidden}.teinvit-admin-page-birthday .teinvit-apf-col .wapf-wrapper,.teinvit-admin-page-birthday .teinvit-apf-col .wapf,.teinvit-admin-page-birthday .teinvit-apf-col form.cart,.teinvit-admin-page-birthday .teinvit-apf-col .wapf-field,.teinvit-admin-page-birthday .teinvit-apf-col .wapf-field-container,.teinvit-admin-page-birthday .teinvit-apf-col .wapf-repeatable,.teinvit-admin-page-birthday .teinvit-apf-col .wapf-field-row,.teinvit-admin-page-birthday .teinvit-apf-col .wapf-input,.teinvit-admin-page-birthday .teinvit-apf-col .wapf-input-wrap{width:100%;max-width:100%;min-width:0}.teinvit-admin-page-birthday .teinvit-apf-col input:not([type=checkbox]):not([type=radio]),.teinvit-admin-page-birthday .teinvit-apf-col select,.teinvit-admin-page-birthday .teinvit-apf-col textarea{width:100%;max-width:100%;min-width:0}.teinvit-admin-page-birthday .teinvit-variant-pdf-actions{display:flex;margin:6px 0 0 0}.teinvit-admin-page-birthday .teinvit-share-actions,.teinvit-admin-page-birthday .teinvit-share-row,.teinvit-admin-page-birthday .teinvit-gifts-actions,.teinvit-admin-page-birthday .teinvit-report-toolbar{max-width:100%;min-width:0}.teinvit-admin-page-birthday .teinvit-share-quick{width:100%;max-width:100%}.teinvit-admin-page-birthday .teinvit-report-grid{grid-template-columns:1fr}}
@media (max-width: 640px){.teinvit-two-col,.teinvit-form-row,.teinvit-rsvp-toggle-grid,.teinvit-info-free-text-grid{grid-template-columns:1fr!important}.teinvit-admin-page{padding:10px}}
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

  <div class="teinvit-zone teinvit-birthday-info-settings">
    <h3 style="text-align:center;margin-top:0;">Informații publicate pe pagina invitaților</h3>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-birthday-info-form" class="teinvit-info-form">
      <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
      <input type="hidden" name="action" value="teinvit_birthday_save_invitation_info">
      <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">

      <label class="teinvit-info-deadline-toggle"><input type="checkbox" id="date_confirm" name="date_confirm" value="1" <?php checked( $show_deadline ); ?> <?php disabled( ! $can_save_invitation_info ); ?>> Doresc afișarea datei limită pentru confirmări</label>
      <div id="selecteaza-data-wrap" style="<?php echo $show_deadline ? '' : 'display:none;'; ?>" class="acf-field acf-field-date-picker teinvit-info-date-wrap" data-name="selecteaza_data" data-type="date_picker">
        <label for="selecteaza_data">Selectează data</label>
        <div class="acf-input">
          <div class="acf-date-picker acf-input-wrap" data-date_format="dd/mm/yy" data-display_format="dd/mm/yy" data-first_day="1">
            <input type="text" id="selecteaza_data" name="selecteaza_data" placeholder="zz/ll/aaaa" value="<?php echo esc_attr( $deadline_date ); ?>" autocomplete="off" class="input" <?php disabled( ! $can_save_invitation_info ); ?>>
          </div>
        </div>
      </div>

      <div class="teinvit-info-free-text-grid">
        <label class="teinvit-info-text-card">
          <span><input type="checkbox" name="show_birthday_party_theme" value="1" <?php checked( ! empty( $config['show_birthday_party_theme'] ) ); ?> <?php disabled( ! $can_save_invitation_info ); ?>> Afișează tematica petrecerii</span>
          <input type="text" name="birthday_party_theme_text" value="<?php echo esc_attr( (string) ( $config['birthday_party_theme_text'] ?? '' ) ); ?>" placeholder="Tematica petrecerii" aria-label="Tematica petrecerii" <?php disabled( ! $can_save_invitation_info ); ?>>
        </label>
        <label class="teinvit-info-text-card">
          <span><input type="checkbox" name="show_birthday_dress_code" value="1" <?php checked( ! empty( $config['show_birthday_dress_code'] ) ); ?> <?php disabled( ! $can_save_invitation_info ); ?>> Afișează dress code / ținută recomandată</span>
          <input type="text" name="birthday_dress_code_text" value="<?php echo esc_attr( (string) ( $config['birthday_dress_code_text'] ?? '' ) ); ?>" placeholder="Dress code" aria-label="Dress code" <?php disabled( ! $can_save_invitation_info ); ?>>
        </label>
      </div>

      <?php if ( $can_save_invitation_info ) : ?>
        <p class="teinvit-info-actions"><button type="submit" class="button">Salvează informațiile</button></p>
      <?php else : ?>
        <p class="teinvit-info-actions"><em><?php echo esc_html( (string) ( $basic_copy['deadline_locked'] ?? 'Informațiile pot fi publicate pe pagina invitaților doar după upgrade la Premium.' ) ); ?></em></p>
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
              <span class="teinvit-variant-pdf-actions">
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
          <div class="teinvit-rsvp-mode-choice" role="radiogroup" aria-label="Tip formular RSVP Birthday">
            <label><input type="radio" name="birthday_rsvp_mode" value="adult" <?php checked( $birthday_rsvp_mode, 'adult' ); ?>> Zi de naștere Adult/Adulți</label>
            <label><input type="radio" name="birthday_rsvp_mode" value="child" <?php checked( $birthday_rsvp_mode, 'child' ); ?>> Zi de naștere Copil/Copii</label>
          </div>
          <div class="teinvit-rsvp-mode-panel" data-rsvp-mode-panel="adult" <?php echo $birthday_rsvp_mode === 'adult' ? '' : 'hidden'; ?>>
            <div class="teinvit-rsvp-toggle-grid">
              <?php foreach ( $admin_toggle_fields as $config_key => $label ) : ?>
                <label>
                  <input type="checkbox" name="<?php echo esc_attr( $config_key ); ?>" value="1" <?php checked( ! empty( $config[ $config_key ] ) ); ?>>
                  <?php echo esc_html( $label ); ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="teinvit-rsvp-mode-panel" data-rsvp-mode-panel="child" <?php echo $birthday_rsvp_mode === 'child' ? '' : 'hidden'; ?>>
            <div class="teinvit-rsvp-toggle-grid">
              <?php foreach ( $admin_child_toggle_fields as $config_key => $label ) : ?>
                <label>
                  <input type="checkbox" name="<?php echo esc_attr( $config_key ); ?>" value="1" <?php checked( ! empty( $config[ $config_key ] ) ); ?>>
                  <?php echo esc_html( $label ); ?>
                </label>
              <?php endforeach; ?>
            </div>
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
        <p class="teinvit-share-help">Trimite rapid invitația către familie și prieteni. Pe telefon poți folosi butonul „Distribuie”, iar în rest ai opțiuni rapide mai jos.</p>
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
    </div>

    <div class="teinvit-apf-col" data-product-page-preselected-id="<?php echo (int) $product_id; ?>">
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-save-form" class="cart">
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
  <div class="teinvit-zone teinvit-admin-gifts">
    <h3>Lista de cadouri</h3>
    <?php if ( ! empty( $capabilities['can_manage_gifts'] ) ) : ?>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-birthday-gifts-form">
        <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
        <input type="hidden" name="action" value="teinvit_birthday_save_gifts">
        <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
        <label><input type="checkbox" name="show_gifts_section" value="1" id="teinvit-birthday-show-gifts-section" <?php checked( $show_gifts_section ); ?>> Activează secțiunea Cadouri pe pagina invitaților</label>
        <div id="teinvit-birthday-gifts-editor" style="margin-top:10px;<?php echo $show_gifts_section ? '' : 'display:none;'; ?>">
          <div class="teinvit-gifts-table-wrap">
            <table class="teinvit-gifts-table" id="teinvit-birthday-gifts-table">
              <thead><tr><th>Selectează</th><th>Denumire produs</th><th>Link produs</th><th>Adresă de livrare</th><th>Rezervat de</th></tr></thead>
              <tbody id="teinvit-birthday-gifts-body"></tbody>
            </table>
          </div>
          <div class="teinvit-gifts-actions">
            <button type="button" class="button" id="teinvit-birthday-add-gift">Adaugă cadou</button>
            <span id="teinvit-birthday-gifts-counter">Mai poți adăuga <?php echo (int) $gifts_remaining; ?> cadouri în listă</span>
            <?php if ( ! empty( $capabilities['can_buy_extra_gifts'] ) ) : ?>
              <a href="<?php echo esc_url( $buy_gifts_url ); ?>" class="button" id="teinvit-birthday-buy-gifts" style="<?php echo $gifts_remaining > 0 ? 'display:none;' : ''; ?>" target="_blank" rel="noopener"><?php echo esc_html( $buy_gifts_cta_label ); ?></a>
            <?php endif; ?>
          </div>
          <p style="margin-top:10px;"><button type="submit" class="button button-primary" id="teinvit-birthday-save-gifts">Salvează lista</button></p>
        </div>
      </form>
    <?php else : ?>
      <p><em><?php echo esc_html( (string) ( $basic_copy['gifts_locked'] ?? 'Activarea listei de Cadouri pe pagina invitaților este disponibilă doar pentru pachetul Premium.' ) ); ?></em></p>
    <?php endif; ?>
  </div>
  <div class="teinvit-zone teinvit-admin-report">
    <h3 style="text-align:center;margin-top:0;">Raport invitați</h3>
    <div class="teinvit-report-grid">
      <?php foreach ( $report_kpis as $metric => $value ) : ?>
        <div class="teinvit-report-card"><strong><?php echo esc_html( (string) $metric ); ?>:</strong> <?php echo esc_html( (string) $value ); ?></div>
      <?php endforeach; ?>
    </div>
    <div class="teinvit-report-toolbar">
      <label><input type="radio" name="teinvit-birthday-report-view" value="unique" checked> Unic</label>
      <label><input type="radio" name="teinvit-birthday-report-view" value="history"> Istoric</label>
      <label><input type="checkbox" id="teinvit-birthday-filter-multi"> Doar confirmări multiple</label>
      <label><input type="checkbox" id="teinvit-birthday-filter-party"> Doar Petrecere = DA</label>
      <label><input type="checkbox" id="teinvit-birthday-filter-cazare"> Doar Cazare = DA</label>
      <label><input type="checkbox" id="teinvit-birthday-filter-message"> Doar cu Mesaj completat</label>
      <label><input type="checkbox" id="teinvit-birthday-filter-observations"> Doar cu Observații speciale</label>
      <a href="<?php echo esc_url( $report_export_url ); ?>" class="button">Descarcă raportul (XLSX)</a>
    </div>
    <div class="teinvit-report-table-wrap">
      <table class="teinvit-report-table" id="teinvit-birthday-report-table">
        <thead><tr><?php foreach ( $report_headers as $header ) : ?><th><?php echo esc_html( (string) $header ); ?></th><?php endforeach; ?></tr></thead>
        <tbody></tbody>
      </table>
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
  const initialInvitation = <?php echo wp_json_encode( $current_invitation ); ?>;
  const baseUrl = <?php echo wp_json_encode( home_url( '/admin-client/' . rawurlencode( $token ) ) ); ?>;
  const shareUrl = <?php echo wp_json_encode( $guest_page_url ); ?>;
  const shareTitle = <?php echo wp_json_encode( (string) ( $share_payload['title'] ?? 'Invitație aniversare - Te Invit' ) ); ?>;
  const shareText = <?php echo wp_json_encode( (string) ( $share_payload['text'] ?? 'Te invităm cu drag la petrecerea aniversară' ) ); ?>;
  const editsRemaining = <?php echo (int) $edits_remaining; ?>;
  const saveForm = document.getElementById('teinvit-save-form');
  const parentBooleanIds = ['fc5b530','2cac251','1aa14a1'];
  const repeatableFieldIds = ['d1fe0da'];
  const parentChildFallbacks = {
    fc5b530: { value: '59yiz', children: ['0c45e7b','1d485ae','baee2f0','a2be7ee'] },
    '2cac251': { value: '1', children: ['4e73bc1'] },
    '1aa14a1': { value: '1', children: ['cb7c1fd'] }
  };
  let isHydratingWapf = true;
  let saveSubmitGuardInstalled = false;
  const giftsInitial = <?php echo wp_json_encode( $gift_rows_export ); ?>;
  const giftsMaxSlots = <?php echo (int) $gifts_max_slots; ?>;
  const reportUnique = <?php echo wp_json_encode( $report_unique ); ?>;
  const reportHistory = <?php echo wp_json_encode( $report_history ); ?>;
  const reportIncludeMode = <?php echo $report_include_mode ? 'true' : 'false'; ?>;

  window.teinvitBirthdayPreviewConfig.adminClient = true;
  window.teinvitBirthdayPreviewConfig.deferInitialBuild = true;
  window.__TEINVIT_BIRTHDAY_WAPF_READY__ = false;

  function qsa(selector, root){
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function escapeHtml(value){
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function yn(value){
    return Number(value) === 1 ? 'DA' : 'NU';
  }

  function refreshRsvpModePanels(){
    const selected = document.querySelector('input[name="birthday_rsvp_mode"]:checked')?.value || 'adult';
    qsa('[data-rsvp-mode-panel]').forEach(function(panel){
      panel.hidden = panel.getAttribute('data-rsvp-mode-panel') !== selected;
    });
  }

  qsa('input[name="birthday_rsvp_mode"]').forEach(function(radio){
    radio.addEventListener('change', refreshRsvpModePanels);
  });
  refreshRsvpModePanels();

  const giftsBody = document.getElementById('teinvit-birthday-gifts-body');
  const giftsEditor = document.getElementById('teinvit-birthday-gifts-editor');
  const showGiftsCheckbox = document.getElementById('teinvit-birthday-show-gifts-section');
  const addGiftBtn = document.getElementById('teinvit-birthday-add-gift');
  const giftsCounter = document.getElementById('teinvit-birthday-gifts-counter');
  const buyGiftsBtn = document.getElementById('teinvit-birthday-buy-gifts');
  const giftsForm = document.getElementById('teinvit-birthday-gifts-form');

  function birthdayGiftRowsCount(){
    if (!giftsBody) return 0;
    let used = 0;
    qsa('tr[data-gift-row="1"]', giftsBody).forEach(function(row){
      const name = (row.querySelector('input[data-field="gift_name"]')?.value || '').trim();
      const link = (row.querySelector('input[data-field="gift_link"]')?.value || '').trim();
      if (name || link) used++;
    });
    return used;
  }

  function refreshBirthdayGiftsCounter(){
    const remaining = Math.max(0, giftsMaxSlots - birthdayGiftRowsCount());
    if (giftsCounter) giftsCounter.textContent = 'Mai poți adăuga ' + remaining + ' cadouri în listă';
    if (addGiftBtn) addGiftBtn.disabled = remaining === 0;
    if (buyGiftsBtn) buyGiftsBtn.style.display = remaining === 0 ? 'inline-block' : 'none';
  }

  function createBirthdayGiftRow(item, index){
    if (!giftsBody) return;
    const tr = document.createElement('tr');
    tr.setAttribute('data-gift-row', '1');
    const locked = Number(item.published_locked || 0) === 1;
    const includeChecked = Number(item.include_in_public || 0) === 1;
    const hasName = String(item.gift_name || '').trim() !== '';
    const hasLink = String(item.gift_link || '').trim() !== '';
    const hasAddress = String(item.gift_delivery_address || '').trim() !== '';

    tr.innerHTML = `
      <td>
        <input type="hidden" name="gifts[${index}][gift_id]" value="${escapeHtml(item.gift_id || '')}">
        <input type="checkbox" name="gifts[${index}][include_in_public]" value="1" ${includeChecked ? 'checked' : ''}>
      </td>
      <td><input type="text" data-field="gift_name" name="gifts[${index}][gift_name]" value="${escapeHtml(item.gift_name || '')}" ${(locked && hasName) ? 'readonly' : ''}></td>
      <td><input type="url" data-field="gift_link" name="gifts[${index}][gift_link]" value="${escapeHtml(item.gift_link || '')}" ${(locked && hasLink) ? 'readonly' : ''}></td>
      <td><textarea name="gifts[${index}][gift_delivery_address]" ${(locked && hasAddress) ? 'readonly' : ''}>${escapeHtml(item.gift_delivery_address || '')}</textarea></td>
      <td>${escapeHtml(item.reserved_by || 'Disponibil')}</td>
    `;

    qsa('input[data-field="gift_name"], input[data-field="gift_link"]', tr).forEach(function(el){
      el.addEventListener('input', refreshBirthdayGiftsCounter);
    });
    giftsBody.appendChild(tr);
  }

  function renderBirthdayGifts(){
    if (!giftsBody) return;
    giftsBody.innerHTML = '';
    giftsInitial.forEach(function(item, index){
      createBirthdayGiftRow(item, index);
    });
    if (!giftsInitial.length) {
      createBirthdayGiftRow({ gift_id: '', gift_name: '', gift_link: '', gift_delivery_address: '', include_in_public: 1, published_locked: 0, reserved_by: 'Disponibil' }, 0);
    }
    refreshBirthdayGiftsCounter();
  }

  if (showGiftsCheckbox && giftsEditor) {
    showGiftsCheckbox.addEventListener('change', function(){
      const enabled = !!showGiftsCheckbox.checked;
      giftsEditor.style.display = enabled ? '' : 'none';
      if (!enabled && giftsForm) {
        giftsForm.submit();
      }
    });
  }

  if (addGiftBtn) {
    addGiftBtn.addEventListener('click', function(){
      const idx = giftsBody ? qsa('tr[data-gift-row="1"]', giftsBody).length : 0;
      createBirthdayGiftRow({ gift_id: '', gift_name: '', gift_link: '', gift_delivery_address: '', include_in_public: 1, published_locked: 0, reserved_by: 'Disponibil' }, idx);
      refreshBirthdayGiftsCounter();
    });
  }

  if (giftsForm) {
    giftsForm.addEventListener('submit', function(e){
      const shouldConfirm = !showGiftsCheckbox || !!showGiftsCheckbox.checked;
      if (!shouldConfirm) return;
      if (!window.confirm('După salvarea listei cadourile completate nu mai pot fi editate. Ești sigur că vrei să o salvezi?')) {
        e.preventDefault();
      }
    });
  }
  renderBirthdayGifts();

  const reportTableBody = document.querySelector('#teinvit-birthday-report-table tbody');
  function mapBirthdayReportRow(r){
    const mode = String(r.birthday_rsvp_mode || 'adult') === 'child' ? 'child' : 'adult';
    const adults = mode === 'child' ? Math.max(0, Number(r.child_accompanying_adults_count || 0)) : Math.max(0, Number(r.attending_people_count || 0));
    const kids = mode === 'child' ? Math.max(0, Number(r.child_participants_count || 0)) : (Number(r.bringing_kids) === 1 ? Math.max(0, Number(r.kids_count || 0)) : 0);
    const childMenuRequested = Number(r.child_menu_requested || 0) === 1;
    const partyActive = Number(r.party_question_active_at_submit || 0) === 1;
    const message = String(r.message_to_celebrants || '');
    const observations = String(r.special_observations || '');
    const cells = [
      r.multi_badge || '',
      r.guest_last_name || '',
      r.guest_first_name || '',
      r.guest_phone || '',
      r.guest_email || '',
      r.created_at_display || '',
      partyActive ? yn(r.attending_party) : 'N/A',
      String(adults + kids),
      String(adults),
      mode === 'child' ? (kids > 0 ? 'DA' : 'NU') : yn(r.bringing_kids),
      (mode === 'child' || Number(r.bringing_kids) === 1) ? String(kids) : '-',
      childMenuRequested ? 'DA' : 'NU',
      childMenuRequested ? String(Math.max(0, Number(r.child_menu_count || 0))) : '-',
      yn(r.needs_accommodation),
      Number(r.needs_accommodation) === 1 ? String(r.accommodation_people_count || '-') : '-',
      yn(r.vegetarian_requested),
      Number(r.vegetarian_requested) === 1 ? String(r.vegetarian_menus_count || '-') : '-',
      yn(r.has_allergies),
      Number(r.has_allergies) === 1 ? (r.allergy_details || '-') : '-',
      message.trim() !== '' ? message : '-',
      observations.trim() !== '' ? observations : '-'
    ];
    if (reportIncludeMode) {
      cells.splice(1, 0, mode === 'child' ? 'Copil/Copii' : 'Adult/Adulti');
      cells.splice(13, 0, mode === 'child' ? yn(r.child_accompanying_adult_stays) : '-', mode === 'child' ? String(adults) : '-');
    }
    return {
      is_multi: Number(r.is_multi || 0) === 1,
      party_da: partyActive && Number(r.attending_party || 0) === 1,
      cazare_da: Number(r.needs_accommodation || 0) === 1,
      has_message: message.trim() !== '',
      has_observations: observations.trim() !== '',
      cells: cells
    };
  }

  function renderBirthdayReport(){
    if (!reportTableBody) return;
    const view = document.querySelector('input[name="teinvit-birthday-report-view"]:checked')?.value || 'unique';
    const rows = (view === 'history' ? reportHistory : reportUnique)
      .map(mapBirthdayReportRow)
      .filter(function(r){ return !document.getElementById('teinvit-birthday-filter-multi')?.checked || r.is_multi; })
      .filter(function(r){ return !document.getElementById('teinvit-birthday-filter-party')?.checked || r.party_da; })
      .filter(function(r){ return !document.getElementById('teinvit-birthday-filter-cazare')?.checked || r.cazare_da; })
      .filter(function(r){ return !document.getElementById('teinvit-birthday-filter-message')?.checked || r.has_message; })
      .filter(function(r){ return !document.getElementById('teinvit-birthday-filter-observations')?.checked || r.has_observations; });
    reportTableBody.innerHTML = '';
    rows.forEach(function(r){
      const tr = document.createElement('tr');
      if (r.is_multi) tr.classList.add('teinvit-report-row-multi');
      tr.innerHTML = r.cells.map(function(cell){ return '<td>' + escapeHtml(cell) + '</td>'; }).join('');
      reportTableBody.appendChild(tr);
    });
  }

  qsa('input[name="teinvit-birthday-report-view"], #teinvit-birthday-filter-multi, #teinvit-birthday-filter-party, #teinvit-birthday-filter-cazare, #teinvit-birthday-filter-message, #teinvit-birthday-filter-observations').forEach(function(el){
    el.addEventListener('change', renderBirthdayReport);
  });
  renderBirthdayReport();

  function markCloneButtonsAsNonSubmit(){
    if (!saveForm) return;
    qsa('.wapf-add-clone, .wapf-del-clone', saveForm).forEach(function(btn){
      if (btn && btn.tagName === 'BUTTON') {
        btn.setAttribute('type', 'button');
      }
    });
  }

  function normalizeFieldId(id){
    return String(id || '')
      .replace(/^field_/, '')
      .replace(/^wapf\[field_/, '')
      .replace(/\]$/, '')
      .replace(/_(?:clone_)?\d+$/, '')
      .trim();
  }

  function rawFieldIdFromName(name){
    const match = String(name || '').match(/^wapf\[field_([^\]]+)\]/);
    return match ? String(match[1] || '').trim() : '';
  }

  function fieldIdFromName(name){
    return normalizeFieldId(rawFieldIdFromName(name));
  }

  function isSkippableInput(el){
    return !el || (el.type === 'hidden' && el.classList.contains('wapf-tf-h')) || /_qty\]$/.test(String(el.name || ''));
  }

  function lower(value){
    return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
  }

  function splitSelected(raw){
    if (Array.isArray(raw)) {
      return raw.map(function(value){ return String(value || '').trim(); }).filter(Boolean);
    }
    return String(raw || '')
      .split(/[\n,]+/)
      .map(function(value){ return value.trim(); })
      .filter(Boolean);
  }

  function rawValueForField(map, id){
    if (!map || typeof map !== 'object') return '';
    const matches = [];
    Object.keys(map).forEach(function(key){
      const normalized = normalizeFieldId(key);
      if (normalized !== id) return;
      const value = map[key];
      if (Array.isArray(value)) {
        value.forEach(function(part){ if (String(part || '').trim() !== '') matches.push(part); });
      } else if (String(value || '').trim() !== '') {
        matches.push(value);
      }
    });
    return matches.length > 1 ? matches : (matches[0] || '');
  }

  function isTruthyRaw(raw){
    const selected = splitSelected(raw);
    if (!selected.length) return false;
    return selected.some(function(value){
      const normalized = lower(value);
      return normalized !== '' && normalized !== '0' && normalized !== 'false' && normalized !== 'nu' && normalized !== 'no';
    });
  }

  function hasMeaningfulRaw(raw){
    return splitSelected(raw).some(function(value){ return isTruthyRaw(value); });
  }

  function mapWithInferredParents(map){
    const out = Object.assign({}, map || {});
    Object.keys(parentChildFallbacks).forEach(function(parentId){
      if (isTruthyRaw(rawValueForField(out, parentId))) return;
      const fallback = parentChildFallbacks[parentId] || {};
      const hasChildValue = (fallback.children || []).some(function(childId){
        return hasMeaningfulRaw(rawValueForField(out, childId));
      });
      if (hasChildValue) {
        out[parentId] = fallback.value || '1';
      }
    });
    return out;
  }

  function labelForInput(el){
    const label = el && el.closest ? el.closest('label') : null;
    return label && label.textContent ? label.textContent.replace(/\s+/g, ' ').trim() : '';
  }

  function optionLookups(elements){
    const byValue = {};
    const byLabel = {};
    elements.forEach(function(el){
      if (!el) return;
      if (el.tagName === 'SELECT') {
        Array.from(el.options || []).forEach(function(option){
          byValue[lower(option.value)] = option.value;
          byLabel[lower(option.text)] = option.value;
        });
        return;
      }
      if (el.type === 'checkbox' || el.type === 'radio') {
        byValue[lower(el.value)] = el.value;
        byLabel[lower(labelForInput(el))] = el.value;
      }
    });
    return { byValue: byValue, byLabel: byLabel };
  }

  function resolvedSelections(raw, elements){
    const lookups = optionLookups(elements);
    return splitSelected(raw).map(function(value){
      const key = lower(value);
      if (Object.prototype.hasOwnProperty.call(lookups.byValue, key)) return lookups.byValue[key];
      if (Object.prototype.hasOwnProperty.call(lookups.byLabel, key)) return lookups.byLabel[key];
      return value;
    }).filter(Boolean);
  }

  function groupCurrentInputs(){
    const groups = {};
    if (!saveForm) return groups;
    qsa('[name^="wapf[field_"]', saveForm).forEach(function(el){
      if (isSkippableInput(el)) return;
      const id = fieldIdFromName(el.name);
      if (!id) return;
      if (!groups[id]) groups[id] = [];
      groups[id].push(el);
    });
    return groups;
  }

  function triggerFieldEvents(elements, options){
    if (options && options.silent) return;
    elements.forEach(function(el){
      if (!el) return;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  function phaseAllows(id, phase){
    const isParent = parentBooleanIds.indexOf(id) !== -1;
    if (phase === 'parents') return isParent;
    if (phase === 'children') return !isParent;
    return true;
  }

  function cloneIndex(el){
    const raw = rawFieldIdFromName(el && el.name);
    const match = raw.match(/_(?:clone_)?(\d+)$/);
    if (!match) return 0;
    const parsed = parseInt(match[1], 10);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function repeatableInputs(id){
    if (!saveForm) return [];
    return qsa('[name^="wapf[field_' + id + '"]', saveForm)
      .filter(function(el){ return !isSkippableInput(el) && fieldIdFromName(el.name) === id && el.type !== 'checkbox' && el.type !== 'radio'; })
      .sort(function(a, b){ return cloneIndex(a) - cloneIndex(b); });
  }

  function findRepeatableAddButton(id, inputs){
    const scope = (inputs[0] && inputs[0].closest('.wapf-field, .wapf-field-container, .wapf-repeatable, .wapf-field-row')) || saveForm;
    if (!scope) return null;
    const candidates = qsa('button,a,[role="button"],input[type="button"]', scope);
    return candidates.find(function(btn){
      const text = lower((btn.getAttribute('class') || '') + ' ' + (btn.getAttribute('data-action') || '') + ' ' + (btn.value || '') + ' ' + (btn.textContent || ''));
      return text.indexOf('add') !== -1 || text.indexOf('clone') !== -1 || text.indexOf('repeat') !== -1 || text.indexOf('adauga') !== -1 || text.indexOf('adaugă') !== -1 || text === '+';
    }) || null;
  }

  function repeatableQtyInput(id){
    return saveForm ? saveForm.querySelector('#field_' + id + '_qty, [name="wapf[field_' + id + '_qty]"]') : null;
  }

  function syncRepeatableQty(id){
    const qty = repeatableQtyInput(id);
    if (qty) {
      qty.value = String(Math.max(0, repeatableInputs(id).length - 1));
    }
  }

  function wapfWrapper(){
    if (!window.jQuery || !saveForm) return null;
    const wrapper = saveForm.closest('[data-product-page-preselected-id]') || saveForm;
    return window.jQuery(wrapper);
  }

  function repeatableFieldElement(id){
    if (!window.jQuery || !saveForm) return null;
    return window.jQuery(saveForm).find('.field-' + id).first();
  }

  function clearNewRepeatableClone(node, id){
    if (!node) return [];
    const changed = [];
    qsa('[name^="wapf[field_' + id + '_"]', node).forEach(function(el){
      if (isSkippableInput(el)) return;
      if (el.type === 'checkbox' || el.type === 'radio') {
        el.checked = false;
      } else {
        el.value = '';
      }
      changed.push(el);
    });
    if (changed.length) {
      triggerFieldEvents(changed);
    }
    return changed;
  }

  function createRepeatableClone(id){
    markCloneButtonsAsNonSubmit();
    const $wrapper = wapfWrapper();
    const $field = repeatableFieldElement(id);
    if (window.WAPF && window.WAPF.Util && typeof window.WAPF.Util.repeat === 'function' && $wrapper && $field && $field.length) {
      const $clone = window.WAPF.Util.repeat($wrapper, $field);
      const $cloner = window.jQuery(saveForm).find('.cloner-' + id).first();
      if ($clone && $clone.length && $cloner && $cloner.length) {
        $cloner.appendTo($clone);
      }
      if (window.WAPF.Pricing && typeof window.WAPF.Pricing.calculateAll === 'function') {
        try { window.WAPF.Pricing.calculateAll($wrapper); } catch (e) {}
      }
      markCloneButtonsAsNonSubmit();
      syncRepeatableQty(id);
      return true;
    }

    return false;
  }

  function removeRepeatableClone(id){
    const $wrapper = wapfWrapper();
    const $field = repeatableFieldElement(id);
    if (window.WAPF && window.WAPF.Util && typeof window.WAPF.Util.unrepeat === 'function' && $wrapper && $field && $field.length) {
      try {
        const $cloner = window.jQuery(saveForm).find('.cloner-' + id).first();
        const cache = $field.data('dupe') || [];
        const $target = cache.length > 1 ? cache[cache.length - 2] : $field;
        if ($cloner && $cloner.length && $target && $target.length) {
          $cloner.appendTo($target);
        }
        window.WAPF.Util.unrepeat($wrapper, $field, 1);
        syncRepeatableQty(id);
        return true;
      } catch (e) {}
    }
    const inputs = repeatableInputs(id);
    const last = inputs[inputs.length - 1];
    const row = last && last.closest('.field-' + id + ', .wapf-field-container, .wapf-field');
    if (row && inputs.length > 1) {
      row.parentNode.removeChild(row);
      syncRepeatableQty(id);
      return true;
    }
    return false;
  }

  function ensureRepeatableInputs(id, values){
    let inputs = repeatableInputs(id);
    let guard = 0;
    const targetCount = Math.max(1, values.length || 1);
    while (inputs.length < targetCount && guard < targetCount + 3) {
      if (!createRepeatableClone(id)) break;
      inputs = repeatableInputs(id);
      guard += 1;
    }
    guard = 0;
    while (inputs.length > targetCount && guard < 6) {
      if (!removeRepeatableClone(id)) break;
      inputs = repeatableInputs(id);
      guard += 1;
    }
    syncRepeatableQty(id);
    return inputs;
  }

  function bindManualRepeatableCloneReset(){
    if (!window.jQuery || !saveForm) return;
    window.jQuery(document).off('wapf/cloned.teinvitBirthdayAdmin').on('wapf/cloned.teinvitBirthdayAdmin', function(e, fieldId, cloneNumber, clone){
      const id = normalizeFieldId(fieldId);
      if (repeatableFieldIds.indexOf(id) === -1 || isHydratingWapf) return;
      const node = clone && clone[0] ? clone[0] : clone;
      clearNewRepeatableClone(node, id);
      syncRepeatableQty(id);
    });
  }

  function applyRepeatableField(id, raw){
    const values = splitSelected(raw).slice(0, 4);
    let inputs = ensureRepeatableInputs(id, values.length ? values : ['']);
    if (!inputs.length) return [];
    if (values.length > 1 && inputs.length < values.length) {
      inputs[0].value = values.join(', ');
      return [inputs[0]];
    }
    inputs.forEach(function(el, index){
      el.value = values[index] || (index === 0 ? String(raw || '') : '');
    });
    return inputs;
  }

  function applyStandardField(id, elements, raw){
    const changed = [];
    const checkboxes = elements.filter(function(el){ return el.type === 'checkbox'; });
    const radios = elements.filter(function(el){ return el.type === 'radio'; });
    const selects = elements.filter(function(el){ return el.tagName === 'SELECT'; });
    const textControls = elements.filter(function(el){ return el.type !== 'checkbox' && el.type !== 'radio' && el.tagName !== 'SELECT'; });

    if (checkboxes.length) {
      const selections = resolvedSelections(raw, checkboxes);
      const selectedLower = selections.map(lower);
      const parentTruthy = parentBooleanIds.indexOf(id) !== -1 && isTruthyRaw(raw);
      const hasExactCheckboxMatch = checkboxes.some(function(el){
        return selectedLower.indexOf(lower(el.value)) !== -1 || selectedLower.indexOf(lower(labelForInput(el))) !== -1;
      });
      checkboxes.forEach(function(el, index){
        const checked = selectedLower.indexOf(lower(el.value)) !== -1 || selectedLower.indexOf(lower(labelForInput(el))) !== -1 || (parentTruthy && !hasExactCheckboxMatch && index === 0);
        el.checked = checked;
        changed.push(el);
      });
    }

    if (radios.length) {
      const selections = resolvedSelections(raw, radios);
      const wanted = lower(selections[0] || raw);
      radios.forEach(function(el){
        el.checked = lower(el.value) === wanted || lower(labelForInput(el)) === wanted;
        changed.push(el);
      });
    }

    selects.forEach(function(el){
      const selections = resolvedSelections(raw, [el]);
      el.value = selections[0] || String(raw || '');
      changed.push(el);
    });

    textControls.forEach(function(el){
      el.value = Array.isArray(raw) ? raw.join(', ') : String(raw || '');
      changed.push(el);
    });

    return changed;
  }

  function runWapfDependencies(){
    if (!saveForm) return;
    markCloneButtonsAsNonSubmit();
    if (window.jQuery) {
      const $wrapper = wapfWrapper() || window.jQuery(saveForm);
      if (window.WAPF && window.WAPF.Util && typeof window.WAPF.Util.doDependencies === 'function') {
        try { window.WAPF.Util.doDependencies($wrapper); } catch (e) {}
      }
      if (window.WAPF && window.WAPF.Pricing && typeof window.WAPF.Pricing.calculateAll === 'function') {
        try { window.WAPF.Pricing.calculateAll($wrapper); } catch (e) {}
      }
      window.jQuery(document).trigger('wapf/init_datepickers', [$wrapper]);
    }
  }

  function flushWapfDependencies(){
    if (!saveForm) return;
    parentBooleanIds.forEach(function(id){
      qsa('[name="wapf[field_' + id + '][]"], [name="wapf[field_' + id + ']"]', saveForm).forEach(function(el){
        el.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    runWapfDependencies();
  }

  function serializeParentCheckedState(){
    const holder = document.getElementById('teinvit-parent-checked-json');
    if (!holder || !saveForm) return;
    const payload = {};
    parentBooleanIds.forEach(function(id){
      payload[id] = qsa('[name="wapf[field_' + id + '][]"], [name="wapf[field_' + id + ']"]', saveForm).some(function(el){ return !!el.checked; }) ? 1 : 0;
    });
    holder.value = JSON.stringify(payload);
  }

  function setWapfValues(map, options){
    const opts = options || {};
    const sourceMap = mapWithInferredParents(map || {});
    const shouldTrigger = opts.triggerEvents !== false;
    const phases = opts.phase ? [opts.phase] : ['parents', 'children'];
    let changed = [];

    phases.forEach(function(phase){
      const groups = groupCurrentInputs();
      Object.keys(groups).forEach(function(id){
        if (!phaseAllows(id, phase)) return;
        const raw = rawValueForField(sourceMap, id);
        if (repeatableFieldIds.indexOf(id) !== -1) {
          changed = changed.concat(applyRepeatableField(id, raw));
          return;
        }
        changed = changed.concat(applyStandardField(id, groups[id], raw));
      });

      if (phase === 'parents') {
        triggerFieldEvents(changed, { silent: !shouldTrigger });
        runWapfDependencies();
      }
    });

    serializeParentCheckedState();
    triggerFieldEvents(changed, { silent: !shouldTrigger });
    runWapfDependencies();
    return changed;
  }

  function hydrateWapf(map, options){
    const opts = options || {};
    const shouldAnnounce = opts.announce !== false;
    isHydratingWapf = true;
    markCloneButtonsAsNonSubmit();
    window.__TEINVIT_BIRTHDAY_WAPF_READY__ = false;
    if (opts.invitation) {
      window.TEINVIT_INVITATION_DATA = opts.invitation;
    }
    setWapfValues(map || {}, { phase: 'parents', triggerEvents: false });
    flushWapfDependencies();
    setWapfValues(map || {}, { phase: 'children', triggerEvents: false });
    document.dispatchEvent(new CustomEvent('teinvit:variant-applied'));
    window.setTimeout(function(){
      markCloneButtonsAsNonSubmit();
      setWapfValues(map || {}, { phase: 'children', triggerEvents: false });
      flushWapfDependencies();
      serializeParentCheckedState();
      isHydratingWapf = false;
      if (shouldAnnounce) {
        window.__TEINVIT_BIRTHDAY_WAPF_READY__ = true;
        document.dispatchEvent(new CustomEvent('teinvit:birthday-wapf-hydrated', { detail: { initial: !!opts.initial } }));
      }
    }, 90);
  }

  function saveConfirmationMessage(){
    return editsRemaining === 1
      ? 'Aceasta este ultima modificare disponibil\u0103. Po\u021bi achizi\u021biona altele oric\u00e2nd. Salvezi modific\u0103rile?'
      : 'Aceast\u0103 ac\u021biune consum\u0103 o modificare disponibil\u0103 din ' + editsRemaining + '. Salvezi modific\u0103rile?';
  }

  function installSaveSubmitGuard(){
    if (!saveForm || saveSubmitGuardInstalled) return;
    saveSubmitGuardInstalled = true;
    markCloneButtonsAsNonSubmit();
    saveForm.addEventListener('submit', function(e){
      const submitter = e.submitter || document.activeElement;
      const isSaveButton = !!(submitter && submitter.id === 'teinvit-save-btn');
      if (isHydratingWapf || !isSaveButton) {
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
      }

      serializeParentCheckedState();
      if (editsRemaining <= 0) {
        e.preventDefault();
        return false;
      }
      if (!window.confirm(saveConfirmationMessage())) {
        e.preventDefault();
        return false;
      }
      e.stopImmediatePropagation();
      return true;
    }, true);
  }

  markCloneButtonsAsNonSubmit();
  installSaveSubmitGuard();
  bindManualRepeatableCloneReset();

  document.addEventListener('DOMContentLoaded', function(){
    bindManualRepeatableCloneReset();

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

    window.setTimeout(function(){
      hydrateWapf(initialWapf || {}, { invitation: initialInvitation, initial: true });
    }, 30);

    document.querySelectorAll('.teinvit-variant-radio').forEach(function(radio){
      radio.addEventListener('change', function(){
        const id = parseInt(radio.value || '0', 10);
        const found = variants.find(function(v){ return parseInt(v.id || '0', 10) === id; });
        if (found && found.wapf_fields) {
          hydrateWapf(found.wapf_fields, { invitation: found.invitation || null });
        }
        window.location.href = baseUrl + '?selected_version_id=' + encodeURIComponent(id);
      });
    });

    if (saveForm) {
      serializeParentCheckedState();
      saveForm.addEventListener('input', serializeParentCheckedState);
      saveForm.addEventListener('change', serializeParentCheckedState);
      installSaveSubmitGuard();
    }
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
