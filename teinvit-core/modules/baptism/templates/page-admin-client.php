<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$token = get_query_var( 'teinvit_admin_client_token' );
$token = sanitize_text_field( (string) $token );
$inv = function_exists( 'teinvit_get_invitation_record' ) ? teinvit_get_invitation_record( $token, 'baptism' ) : null;
if ( ! is_array( $inv ) ) {
    echo '<p>Invitația nu a fost găsită.</p>';
    return;
}

$order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $inv['order_id'] ) : null;
if ( ! $order ) {
    echo '<p>Comanda nu a fost găsită.</p>';
    return;
}

$product_id = function_exists( 'teinvit_get_order_primary_product_id' ) ? (int) teinvit_get_order_primary_product_id( $order ) : 0;
$product = $product_id ? wc_get_product( $product_id ) : null;
$order_wapf = function_exists( 'teinvit_extract_order_wapf_field_map' ) ? teinvit_extract_order_wapf_field_map( $order ) : [];
$order_payload = function_exists( 'teinvit_build_invitation_payload_from_wapf_map' )
    ? teinvit_build_invitation_payload_from_wapf_map( 'baptism', $order_wapf, $product_id )
    : [ 'invitation' => [], 'wapf_fields' => $order_wapf ];
$order_invitation = isset( $order_payload['invitation'] ) && is_array( $order_payload['invitation'] ) ? $order_payload['invitation'] : [];
$order_pdf_url = (string) $order->get_meta( '_teinvit_pdf_url' );

$versions = function_exists( 'teinvit_get_versions_for_token_from_storage' ) ? teinvit_get_versions_for_token_from_storage( $token, 'baptism' ) : [];
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
$config = function_exists( 'teinvit_baptism_config_with_defaults' )
    ? teinvit_baptism_config_with_defaults( is_array( $inv['config'] ?? null ) ? $inv['config'] : [] )
    : wp_parse_args( is_array( $inv['config'] ?? null ) ? $inv['config'] : [], function_exists( 'teinvit_default_rsvp_config_for_vertical' ) ? teinvit_default_rsvp_config_for_vertical( 'baptism' ) : [] );

$edits_free_remaining = max( 0, (int) ( $config['edits_free_remaining'] ?? 2 ) );
$edits_paid_remaining = max( 0, (int) ( $config['edits_paid_remaining'] ?? 0 ) );
$edits_remaining = $edits_free_remaining + $edits_paid_remaining;
$capabilities = function_exists( 'teinvit_capabilities_for_token' ) ? teinvit_capabilities_for_token( $token ) : [];
$token_state = isset( $capabilities['state'] ) ? (string) $capabilities['state'] : 'premium_native';
$can_save_invitation_info = ! empty( $capabilities['can_save_invitation_info'] );
$basic_copy = function_exists( 'teinvit_vertical_basic_copy' ) ? teinvit_vertical_basic_copy( 'baptism' ) : [];

$show_deadline = ! empty( $config['show_rsvp_deadline'] );
$deadline_date = (string) ( $config['rsvp_deadline_date'] ?? '' );
$show_gifts_section = ! empty( $config['show_gifts_section'] );
$gifts_summary = function_exists( 'teinvit_baptism_build_gifts_summary_for_token' ) ? teinvit_baptism_build_gifts_summary_for_token( $token, $config ) : [ 'total_slots' => 20, 'used_slots' => 0, 'available_slots' => 20 ];
$gifts_max_slots = max( 0, (int) ( $gifts_summary['total_slots'] ?? 20 ) );
$gifts_remaining = max( 0, (int) ( $gifts_summary['available_slots'] ?? $gifts_max_slots ) );

$gift_rows_export = [];
$gifts_table = function_exists( 'teinvit_baptism_gifts_table_for_token' ) ? teinvit_baptism_gifts_table_for_token( $token ) : '';
$rsvp_table = function_exists( 'teinvit_baptism_rsvp_table_for_token' ) ? teinvit_baptism_rsvp_table_for_token( $token ) : '';
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

$catalog = function_exists( 'teinvit_get_catalog_for_token' ) ? teinvit_get_catalog_for_token( $token ) : [];
$gifts_slots_per_purchase = function_exists( 'teinvit_catalog_first_extra_gifts_slots' ) ? (int) teinvit_catalog_first_extra_gifts_slots( $catalog, 10 ) : 10;
$buy_gifts_url = add_query_arg( [ 'teinvit_buy_gifts_token' => $token ], home_url( '/' ) );
$buy_gifts_cta_label = 'Cumpără pachet +' . max( 1, $gifts_slots_per_purchase );
$buy_edits_url = add_query_arg( [ 'teinvit_buy_edits_token' => $token ], home_url( '/' ) );
$buy_premium_upgrade_url = add_query_arg( [ 'teinvit_buy_premium_upgrade_token' => $token ], home_url( '/' ) );

$report_sets = function_exists( 'teinvit_baptism_build_rsvp_report_sets' ) ? teinvit_baptism_build_rsvp_report_sets( $token ) : [ 'history' => [], 'unique' => [], 'multiple_phones_count' => 0, 'unique_phones_count' => 0, 'submissions_count' => 0 ];
$report_unique = is_array( $report_sets['unique'] ?? null ) ? $report_sets['unique'] : [];
$report_history = is_array( $report_sets['history'] ?? null ) ? $report_sets['history'] : [];
$report_kpis = function_exists( 'teinvit_baptism_build_rsvp_report_kpis' ) ? teinvit_baptism_build_rsvp_report_kpis( $report_sets, $config ) : [];
$report_headers = function_exists( 'teinvit_baptism_report_headers' ) ? teinvit_baptism_report_headers() : [];
$report_export_url = wp_nonce_url( admin_url( 'admin-post.php?action=teinvit_baptism_export_guest_report&token=' . rawurlencode( $token ) ), 'teinvit_admin_' . $token );

$guest_page_url = home_url( '/invitati/' . rawurlencode( $token ) );
$guest_page_url = function_exists( 'set_url_scheme' ) ? set_url_scheme( $guest_page_url, 'https' ) : preg_replace( '/^http:/i', 'https:', $guest_page_url );
$share_payload = function_exists( 'teinvit_vertical_share_payload' ) ? teinvit_vertical_share_payload( 'baptism', $current_invitation, $guest_page_url ) : [
    'title' => 'Te invităm la botez',
    'text' => 'Te invităm cu drag la botez',
    'message' => "Te invităm cu drag la botez\n" . $guest_page_url,
    'url' => $guest_page_url,
];
$share_icon_base = defined( 'TEINVIT_WEDDING_MODULE_URL' ) ? trailingslashit( TEINVIT_WEDDING_MODULE_URL . 'assets/icons/social' ) : '';
$download_pdf_nonce = wp_create_nonce( 'teinvit_download_pdf_' . $token );
$subtitle = function_exists( 'teinvit_join_ro_names' )
    ? teinvit_join_ro_names( isset( $current_invitation['children'] ) && is_array( $current_invitation['children'] ) ? $current_invitation['children'] : [] )
    : '';
if ( $subtitle === '' ) {
    $subtitle = 'Invitație botez';
}

$preview_html = function_exists( 'teinvit_render_invitation_html_for_vertical' )
    ? teinvit_render_invitation_html_for_vertical( 'baptism', $current_invitation, $order, 'preview', $product_id )
    : '';
$apf_html = ( $product && function_exists( 'wapf_display_field_groups_for_product' ) ) ? wapf_display_field_groups_for_product( $product ) : '';

$admin_toggle_fields = [
    'show_attending_religious' => 'Permite confirmarea participării la Slujba de botez',
    'show_attending_party' => 'Permite confirmarea participării la petrecerea de botez',
    'show_attending_people_count' => 'Permite completarea numărului de adulți confirmați',
    'show_kids' => 'Permite confirmarea copiilor însoțitori',
    'show_child_menu' => 'Permite solicitarea meniului copil',
    'show_child_seat' => 'Permite solicitarea scaunului copil',
    'show_accommodation' => 'Permite solicitarea de cazare',
    'show_transport' => 'Permite solicitarea de transport între biserică și restaurant',
    'show_vegetarian' => 'Permite selectarea meniului vegetarian',
    'show_allergies' => 'Permite menționarea alergiilor sau restricțiilor alimentare',
    'show_message' => 'Mesaj pentru familie/copil',
];
?>
<style>
.teinvit-admin-page{max-width:1200px;margin:20px auto;padding:16px}.teinvit-admin-page-baptism,.teinvit-admin-page-baptism *{box-sizing:border-box}.teinvit-admin-title-card{border:1px solid #e5e5e5;padding:16px;border-radius:8px;background:#fff;margin:0 0 16px;text-align:center}.teinvit-admin-title-card h1{margin:0}.teinvit-admin-title-card h1+h1{margin-top:6px}.teinvit-zone{border:1px solid #e5e5e5;padding:14px;border-radius:8px;background:#fff;margin:16px 0}.teinvit-two-col{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:20px;align-items:start}.teinvit-info-form{display:flex;flex-direction:column;align-items:center;gap:12px}.teinvit-info-date-wrap{width:min(260px,100%);text-align:center}.teinvit-info-date-wrap input[type=text]{max-width:220px;text-align:center}.teinvit-info-toggle-grid,.teinvit-rsvp-toggle-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 16px;width:100%;max-width:840px}.teinvit-info-toggle-grid label,.teinvit-rsvp-toggle-grid label{display:block}.teinvit-apf-col{min-width:0}.teinvit-apf-col .wapf-wrapper,.teinvit-apf-col .wapf,.teinvit-apf-col form.cart{max-width:100%}.teinvit-admin-page-baptism .teinvit-apf-col #teinvit-save-form{display:block;width:100%;max-width:100%;margin:0}.teinvit-admin-preview-block{display:block!important;min-height:320px;overflow:visible}.teinvit-admin-preview-block .teinvit-wedding{display:flex!important;justify-content:center!important;min-height:320px;padding:0}.teinvit-admin-page .teinvit-page,.teinvit-admin-page .teinvit-container{display:block!important;max-width:100%;overflow:visible}.teinvit-admin-page .teinvit-preview{display:block!important;visibility:visible!important;opacity:1!important;max-width:760px;margin:0 auto;overflow:hidden}.teinvit-share-card h3{margin-top:0}.teinvit-share-actions{display:flex;gap:8px;flex-wrap:wrap}.teinvit-share-quick{display:flex;flex-direction:column;gap:8px;margin-top:8px;max-width:320px}.teinvit-share-row{display:flex;align-items:center;gap:10px}.teinvit-share-icon-wrap{width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center;flex:0 0 26px}.teinvit-share-icon-wrap img{width:18px;height:18px;display:block}.teinvit-share-social-btn{flex:1;display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:4px 10px;line-height:1.2;text-align:center}.teinvit-variant-pdf-actions{display:inline-flex;gap:8px;align-items:center;margin-left:8px;vertical-align:middle;flex-wrap:wrap;max-width:100%}.teinvit-variant-pdf-actions .button{line-height:1.2;min-height:28px;padding:3px 10px}.teinvit-gifts-table-wrap,.teinvit-report-table-wrap{width:100%;overflow-x:auto;background:#fff}.teinvit-gifts-table,.teinvit-report-table{width:max-content;min-width:100%;border-collapse:collapse}.teinvit-gifts-table th,.teinvit-gifts-table td,.teinvit-report-table th,.teinvit-report-table td{border:1px solid #ddd;padding:8px;vertical-align:top}.teinvit-gifts-table input[type=text],.teinvit-gifts-table input[type=url],.teinvit-gifts-table textarea{width:100%}.teinvit-gifts-table textarea{min-height:56px;resize:vertical}.teinvit-gifts-actions,.teinvit-report-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:10px}.teinvit-gifts-save-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:12px 0 0}.teinvit-report-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.teinvit-report-card{border:1px solid #ddd;padding:10px;border-radius:8px;background:#fafafa}.teinvit-report-row-multi{background:#fff2f2}.teinvit-report-view[hidden]{display:none!important}
@media (max-width:1024px){.teinvit-admin-page-baptism .teinvit-two-col{grid-template-columns:minmax(0,1fr)!important}.teinvit-report-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:768px){.teinvit-admin-page-baptism{padding:10px;max-width:100%;overflow-x:hidden}.teinvit-admin-page-baptism .teinvit-two-col{display:grid!important;grid-template-columns:minmax(0,1fr)!important}.teinvit-admin-page-baptism .teinvit-two-col>div,.teinvit-admin-page-baptism .teinvit-zone,.teinvit-admin-page-baptism .teinvit-apf-col,.teinvit-admin-page-baptism #teinvit-save-form,.teinvit-admin-page-baptism .teinvit-admin-preview-block,.teinvit-admin-page-baptism .teinvit-share-card{width:100%!important;max-width:100%!important;min-width:0}.teinvit-admin-page-baptism .teinvit-info-toggle-grid,.teinvit-admin-page-baptism .teinvit-rsvp-toggle-grid,.teinvit-admin-page-baptism .teinvit-report-grid{grid-template-columns:1fr}.teinvit-admin-page-baptism .teinvit-admin-preview-block{display:block!important;overflow:hidden}.teinvit-admin-page-baptism .teinvit-admin-preview-block .teinvit-wedding,.teinvit-admin-page-baptism .teinvit-admin-preview-block .teinvit-page,.teinvit-admin-page-baptism .teinvit-admin-preview-block .teinvit-container,.teinvit-admin-page-baptism .teinvit-admin-preview-block .teinvit-preview{width:100%!important;max-width:100%!important;min-width:0}.teinvit-admin-page-baptism .teinvit-admin-preview-block .teinvit-preview{aspect-ratio:148/210!important;height:auto!important;min-height:0!important;overflow:hidden}.teinvit-admin-page-baptism .teinvit-apf-col .wapf-wrapper,.teinvit-admin-page-baptism .teinvit-apf-col .wapf,.teinvit-admin-page-baptism .teinvit-apf-col form.cart,.teinvit-admin-page-baptism .teinvit-apf-col .wapf-field,.teinvit-admin-page-baptism .teinvit-apf-col .wapf-field-container,.teinvit-admin-page-baptism .teinvit-apf-col .wapf-repeatable,.teinvit-admin-page-baptism .teinvit-apf-col .wapf-field-row,.teinvit-admin-page-baptism .teinvit-apf-col .wapf-input,.teinvit-admin-page-baptism .teinvit-apf-col .wapf-input-wrap{width:100%;max-width:100%;min-width:0}.teinvit-admin-page-baptism .teinvit-apf-col input:not([type=checkbox]):not([type=radio]),.teinvit-admin-page-baptism .teinvit-apf-col select,.teinvit-admin-page-baptism .teinvit-apf-col textarea{width:100%;max-width:100%;min-width:0}.teinvit-variant-pdf-actions{display:flex;margin:6px 0 0 0}.teinvit-share-quick{width:100%;max-width:100%}}
@media (max-width:768px){.teinvit-admin-page-baptism .teinvit-two-col{display:flex!important;flex-direction:column!important}.teinvit-admin-page-baptism .teinvit-admin-main-col{display:contents}.teinvit-admin-page-baptism .teinvit-admin-preview-block{order:1;display:flex!important;justify-content:center!important;align-items:flex-start!important;overflow:hidden}.teinvit-admin-page-baptism .teinvit-apf-col{order:2}.teinvit-admin-page-baptism .teinvit-admin-controls-col{order:3;width:100%;max-width:100%;min-width:0}.teinvit-admin-page-baptism .teinvit-admin-preview-block .teinvit-wedding{display:flex!important;justify-content:center!important;width:100%!important;max-width:100%!important;min-width:0!important;overflow:hidden!important}.teinvit-admin-page-baptism .teinvit-admin-preview-block .teinvit-page,.teinvit-admin-page-baptism .teinvit-admin-preview-block .teinvit-container{width:var(--teinvit-preview-scaled-width,559px)!important;height:var(--teinvit-preview-scaled-height,794px)!important;max-width:100%!important;min-width:0!important;min-height:0!important;margin-left:auto!important;margin-right:auto!important;overflow:visible!important;padding:0!important}.teinvit-admin-page-baptism .teinvit-admin-preview-block .teinvit-preview{width:559px!important;min-width:559px!important;max-width:none!important;height:794px!important;max-height:none!important;aspect-ratio:auto!important;font-size:17.25px!important;margin:0!important;transform:scale(var(--teinvit-preview-scale,1));transform-origin:top left;overflow:hidden!important}}
@media (max-width:768px){body .teinvit-admin-page-baptism{width:100%!important;max-width:none!important;margin-left:auto!important;margin-right:auto!important;padding-left:8px!important;padding-right:8px!important}.teinvit-admin-page-baptism .teinvit-admin-title-card,.teinvit-admin-page-baptism .teinvit-zone{margin-left:auto!important;margin-right:auto!important}.teinvit-admin-page-baptism .teinvit-admin-main-col,.teinvit-admin-page-baptism .teinvit-admin-controls-col,.teinvit-admin-page-baptism .teinvit-apf-col{width:100%!important;max-width:100%!important;margin-left:auto!important;margin-right:auto!important}.teinvit-admin-page-baptism .teinvit-admin-preview-block{width:100%!important;max-width:100%!important;justify-content:center!important}.teinvit-admin-page-baptism .teinvit-admin-preview-block .teinvit-wedding{justify-content:center!important}.teinvit-admin-page-baptism .teinvit-share-actions,.teinvit-admin-page-baptism .teinvit-share-row,.teinvit-admin-page-baptism .teinvit-gifts-actions,.teinvit-admin-page-baptism .teinvit-gifts-save-row,.teinvit-admin-page-baptism .teinvit-report-toolbar{max-width:100%;min-width:0}.teinvit-admin-page-baptism .teinvit-share-social-btn{min-width:0}}
@media (max-width:768px){body.teinvit-admin-client-baptism{overflow-x:hidden}body.teinvit-admin-client-baptism .teinvit-admin-page-baptism{width:100%!important;max-width:100%!important;margin-left:auto!important;margin-right:auto!important;padding-left:8px!important;padding-right:8px!important}.ast-container.teinvit-admin-client-container-baptism,.site-content .ast-container.teinvit-admin-client-container-baptism{width:100%!important;max-width:100%!important;margin-left:auto!important;margin-right:auto!important;padding-left:8px!important;padding-right:8px!important;box-sizing:border-box}.ast-container.teinvit-admin-client-container-baptism .teinvit-admin-page-baptism{padding-left:0!important;padding-right:0!important}}
@media (max-width:768px){body.teinvit-mode-admin-client.teinvit-vertical-baptism,body.teinvit-mode-admin-client.teinvit-vertical-baptism #page,body.teinvit-mode-admin-client.teinvit-vertical-baptism #content{overflow-x:hidden!important}body.teinvit-mode-admin-client.teinvit-vertical-baptism .site-content .ast-container,body.teinvit-mode-admin-client.teinvit-vertical-baptism .ast-container.teinvit-admin-client-container-baptism{width:100%!important;max-width:100%!important;margin-left:auto!important;margin-right:auto!important;padding-left:0!important;padding-right:0!important;box-sizing:border-box}body.teinvit-mode-admin-client.teinvit-vertical-baptism .teinvit-admin-page-baptism{width:100%!important;max-width:980px!important;margin:0 auto!important;padding-left:8px!important;padding-right:8px!important;box-sizing:border-box}body.teinvit-mode-admin-client.teinvit-vertical-baptism .teinvit-admin-title-card,body.teinvit-mode-admin-client.teinvit-vertical-baptism .teinvit-zone{width:100%;max-width:100%;margin-left:auto!important;margin-right:auto!important}}
</style>
<div class="teinvit-admin-page teinvit-admin-page-baptism">
  <div class="teinvit-admin-title-card">
    <h1>Administrare invitație botez</h1>
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
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-baptism-info-form" class="teinvit-info-form">
      <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
      <input type="hidden" name="action" value="teinvit_baptism_save_invitation_info">
      <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
      <label><input type="checkbox" id="date_confirm" name="date_confirm" value="1" <?php checked( $show_deadline ); ?> <?php disabled( ! $can_save_invitation_info ); ?>> Doresc afișarea datei limită pentru confirmări</label>
      <div id="selecteaza-data-wrap" style="<?php echo $show_deadline ? '' : 'display:none;'; ?>" class="acf-field acf-field-date-picker teinvit-info-date-wrap" data-name="selecteaza_data" data-type="date_picker">
        <label for="selecteaza_data">Selectează data</label>
        <div class="acf-input">
          <div class="acf-date-picker acf-input-wrap" data-date_format="dd/mm/yy" data-display_format="dd/mm/yy" data-first_day="1">
            <input type="text" id="selecteaza_data" name="selecteaza_data" placeholder="zz/ll/aaaa" value="<?php echo esc_attr( $deadline_date ); ?>" autocomplete="off" class="input" <?php disabled( ! $can_save_invitation_info ); ?>>
          </div>
        </div>
      </div>
      <div class="teinvit-info-toggle-grid">
        <label><input type="checkbox" name="show_baptism_religious_info" value="1" <?php checked( ! empty( $config['show_baptism_religious_info'] ) ); ?> <?php disabled( ! $can_save_invitation_info ); ?>> Afișează informații despre Slujba de botez</label>
        <label><input type="checkbox" name="show_baptism_party_info" value="1" <?php checked( ! empty( $config['show_baptism_party_info'] ) ); ?> <?php disabled( ! $can_save_invitation_info ); ?>> Afișează informații despre petrecerea de botez</label>
      </div>
      <?php if ( $can_save_invitation_info ) : ?>
        <p><button type="submit" class="button">Salvează informațiile</button></p>
      <?php else : ?>
        <p><em><?php echo esc_html( (string) ( $basic_copy['deadline_locked'] ?? 'Informațiile pot fi publicate pe pagina invitaților doar după upgrade la Premium.' ) ); ?></em></p>
      <?php endif; ?>
    </form>
  </div>

  <div class="teinvit-zone teinvit-two-col">
    <div class="teinvit-admin-main-col">
      <div id="teinvit-vertical-product-preview" class="teinvit-admin-preview-block" data-product-id="<?php echo (int) $product_id; ?>">
        <?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      </div>

      <div class="teinvit-admin-controls-col">

      <h3>Alege varianta afișată invitaților:</h3>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-publish-form">
        <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
        <input type="hidden" name="action" value="teinvit_baptism_set_active_version">
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
      <p id="teinvit-pdf-share-status" aria-live="polite"></p>

      <div class="teinvit-zone">
        <h3 style="text-align:center;margin-top:0;">Setările formularului de confirmare</h3>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-rsvp-config-form">
          <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
          <input type="hidden" name="action" value="teinvit_baptism_save_rsvp_config">
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
      <div class="teinvit-zone teinvit-share-card">
        <h3>Distribuie invitația</h3>
        <p>Trimite rapid invitația la botez către familie și prieteni.</p>
        <div class="teinvit-share-actions">
          <button type="button" class="button button-primary" id="teinvit-share-native">Distribuie</button>
          <button type="button" class="button" id="teinvit-share-copy-main">Copiază link</button>
        </div>
        <div class="teinvit-share-quick">
          <?php if ( $share_icon_base !== '' ) : ?>
          <div class="teinvit-share-row"><span class="teinvit-share-icon-wrap"><img src="<?php echo esc_url( $share_icon_base . 'facebook.svg' ); ?>" alt="" aria-hidden="true"></span><a class="button teinvit-share-social-btn" href="<?php echo esc_url( 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $guest_page_url ) ); ?>" target="_blank" rel="noopener">Facebook</a></div>
          <div class="teinvit-share-row"><span class="teinvit-share-icon-wrap"><img src="<?php echo esc_url( $share_icon_base . 'whatsapp.svg' ); ?>" alt="" aria-hidden="true"></span><a class="button teinvit-share-social-btn" id="teinvit-share-whatsapp" href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( (string) ( $share_payload['message'] ?? '' ) ) ); ?>" target="_blank" rel="noopener">WhatsApp</a></div>
          <div class="teinvit-share-row"><span class="teinvit-share-icon-wrap"><img src="<?php echo esc_url( $share_icon_base . 'instagram.svg' ); ?>" alt="" aria-hidden="true"></span><button type="button" class="button teinvit-share-social-btn" id="teinvit-share-instagram">Instagram</button></div>
          <?php endif; ?>
        </div>
        <p id="teinvit-share-status" aria-live="polite"></p>
      </div>
      <?php endif; ?>
      </div>
    </div>

    <div class="teinvit-apf-col" data-product-page-preselected-id="<?php echo (int) $product_id; ?>">
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-save-form" class="cart">
        <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
        <input type="hidden" name="action" value="teinvit_baptism_save_version_snapshot">
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
          <?php if ( ! empty( $capabilities['can_buy_premium_upgrade'] ) ) : ?><a href="<?php echo esc_url( $buy_premium_upgrade_url ); ?>" class="button">Upgrade la Premium</a><?php endif; ?>
        <?php elseif ( $edits_remaining > 0 ) : ?>
          <button type="submit" class="button button-primary" id="teinvit-save-btn">Salvează modificările</button>
        <?php elseif ( ! empty( $capabilities['can_buy_extra_edits'] ) ) : ?>
          <a href="<?php echo esc_url( $buy_edits_url ); ?>" class="button" target="_blank" rel="noopener">Cumpără modificări suplimentare</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <div class="teinvit-zone">
    <h3 style="text-align:center;margin-top:0;">Lista de cadouri</h3>
    <?php if ( ! empty( $capabilities['can_manage_gifts'] ) ) : ?>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-baptism-gifts-form">
        <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
        <input type="hidden" name="action" value="teinvit_baptism_save_gifts">
        <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
        <label><input type="checkbox" name="show_gifts_section" value="1" id="teinvit-baptism-show-gifts-section" <?php checked( $show_gifts_section ); ?>> Activează secțiunea Cadouri pe pagina invitaților</label>
        <div id="teinvit-baptism-gifts-editor" style="margin-top:10px;<?php echo $show_gifts_section ? '' : 'display:none;'; ?>">
          <div class="teinvit-gifts-table-wrap">
            <table class="teinvit-gifts-table"><thead><tr><th>Selectează</th><th>Denumire produs</th><th>Link produs</th><th>Adresă de livrare</th><th>Rezervat de</th></tr></thead><tbody id="teinvit-baptism-gifts-body"></tbody></table>
          </div>
          <div class="teinvit-gifts-actions">
            <button type="button" class="button" id="teinvit-baptism-add-gift">Adaugă cadou</button>
            <span id="teinvit-baptism-gifts-counter">Mai poți adăuga <?php echo (int) $gifts_remaining; ?> cadouri în listă</span>
            <?php if ( ! empty( $capabilities['can_buy_extra_gifts'] ) ) : ?>
              <a href="<?php echo esc_url( $buy_gifts_url ); ?>" class="button" id="teinvit-baptism-buy-gifts" style="<?php echo $gifts_remaining > 0 ? 'display:none;' : ''; ?>" target="_blank" rel="noopener"><?php echo esc_html( $buy_gifts_cta_label ); ?></a>
            <?php endif; ?>
          </div>
          <p class="teinvit-gifts-save-row"><button type="submit" class="button button-primary" id="teinvit-baptism-save-gifts">Salvează lista</button></p>
        </div>
      </form>
    <?php else : ?>
      <p><em><?php echo esc_html( (string) ( $basic_copy['gifts_locked'] ?? 'Activarea listei de Cadouri pe pagina invitaților este disponibilă doar pentru pachetul Premium.' ) ); ?></em></p>
    <?php endif; ?>
  </div>

  <div class="teinvit-zone">
    <h3 style="text-align:center;margin-top:0;">Raport invitați</h3>
    <?php if ( ! empty( $capabilities['can_save_rsvp_config'] ) ) : ?>
      <div class="teinvit-report-grid">
        <?php foreach ( $report_kpis as $metric => $value ) : ?>
          <div class="teinvit-report-card"><strong><?php echo esc_html( (string) $metric ); ?>:</strong> <?php echo esc_html( (string) $value ); ?></div>
        <?php endforeach; ?>
      </div>
      <div class="teinvit-report-toolbar">
        <label><input type="radio" name="teinvit-baptism-report-view" value="unique" checked> Unic</label>
        <label><input type="radio" name="teinvit-baptism-report-view" value="history"> Istoric</label>
        <label><input type="checkbox" id="teinvit-baptism-filter-multi"> Doar confirmări multiple</label>
        <label><input type="checkbox" id="teinvit-baptism-filter-message"> Doar cu Mesaj completat</label>
        <a href="<?php echo esc_url( $report_export_url ); ?>" class="button">Descarcă raportul (XLSX)</a>
      </div>
      <div class="teinvit-report-table-wrap teinvit-report-view" data-report-view="unique">
        <table class="teinvit-report-table"><thead><tr><?php foreach ( $report_headers as $header ) : ?><th><?php echo esc_html( (string) $header ); ?></th><?php endforeach; ?></tr></thead><tbody>
          <?php foreach ( $report_unique as $row ) : ?><tr class="<?php echo ! empty( $row['is_multi'] ) ? 'teinvit-report-row-multi' : ''; ?>" data-multi="<?php echo ! empty( $row['is_multi'] ) ? '1' : '0'; ?>" data-message="<?php echo trim( (string) ( $row['message_to_family'] ?? '' ) ) !== '' ? '1' : '0'; ?>"><?php foreach ( teinvit_baptism_report_row_values( $row ) as $cell ) : ?><td><?php echo esc_html( (string) $cell ); ?></td><?php endforeach; ?></tr><?php endforeach; ?>
        </tbody></table>
      </div>
      <div class="teinvit-report-table-wrap teinvit-report-view" data-report-view="history" hidden>
        <table class="teinvit-report-table"><thead><tr><?php foreach ( $report_headers as $header ) : ?><th><?php echo esc_html( (string) $header ); ?></th><?php endforeach; ?></tr></thead><tbody>
          <?php foreach ( $report_history as $row ) : ?><tr class="<?php echo ! empty( $row['is_multi'] ) ? 'teinvit-report-row-multi' : ''; ?>" data-multi="<?php echo ! empty( $row['is_multi'] ) ? '1' : '0'; ?>" data-message="<?php echo trim( (string) ( $row['message_to_family'] ?? '' ) ) !== '' ? '1' : '0'; ?>"><?php foreach ( teinvit_baptism_report_row_values( $row ) as $cell ) : ?><td><?php echo esc_html( (string) $cell ); ?></td><?php endforeach; ?></tr><?php endforeach; ?>
        </tbody></table>
      </div>
    <?php else : ?>
      <p><em><?php echo esc_html( (string) ( $basic_copy['rsvp_locked'] ?? 'Raportarea invitaților este disponibilă după upgrade la Premium.' ) ); ?></em></p>
    <?php endif; ?>
  </div>
</div>
<script>
(function(){
  document.body.classList.add('teinvit-admin-client-baptism');
  const baptismAdminRoot = document.querySelector('.teinvit-admin-page-baptism');
  const baptismAstContainer = baptismAdminRoot && baptismAdminRoot.closest ? baptismAdminRoot.closest('.ast-container') : null;
  if (baptismAstContainer) baptismAstContainer.classList.add('teinvit-admin-client-container-baptism');
  window.teinvitBaptismPreviewConfig = Object.assign({}, window.teinvitBaptismPreviewConfig || {}, {
    previewBuildUrl: <?php echo wp_json_encode( esc_url_raw( rest_url( 'teinvit/v2/preview/build' ) ) ); ?>,
    token: <?php echo wp_json_encode( (string) $token ); ?>,
    productId: <?php echo (int) $product_id; ?>,
    adminClient: true
  });
  window.TEINVIT_INVITATION_DATA = <?php echo wp_json_encode( $current_invitation ); ?>;
  const initialWapf = <?php echo wp_json_encode( $current_wapf ); ?>;
  const baseUrl = <?php echo wp_json_encode( home_url( '/admin-client/' . rawurlencode( $token ) ) ); ?>;
  const shareUrl = <?php echo wp_json_encode( $guest_page_url ); ?>;
  const shareTitle = <?php echo wp_json_encode( (string) ( $share_payload['title'] ?? 'Te invităm la botez' ) ); ?>;
  const shareText = <?php echo wp_json_encode( (string) ( $share_payload['text'] ?? 'Te invităm cu drag la botez' ) ); ?>;
  const shareMessage = <?php echo wp_json_encode( (string) ( $share_payload['message'] ?? ( "Te invităm cu drag la botez\n" . $guest_page_url ) ) ); ?>;
  const giftsInitial = <?php echo wp_json_encode( $gift_rows_export ); ?>;
  const giftsMaxSlots = <?php echo (int) $gifts_max_slots; ?>;
  const editsRemaining = <?php echo (int) $edits_remaining; ?>;

  const saveForm = document.getElementById('teinvit-save-form');
  const parentBooleanIds = ['3ec4ca5','1f32dd0','1eceab7','b4fca64'];
  const repeatableFieldIds = ['2d8d1ce'];
  const repeatableMaxById = { '2d8d1ce': 3 };
  const parentChildFallbacks = {
    '3ec4ca5': { value: '1', children: ['080362c','23feecb'] },
    '1f32dd0': { value: '1', children: ['7cff5b7','5c0ffa4'] },
    '1eceab7': { value: '1', children: ['2f1dbe2','10adb6f','4c5ae13','40ec33f'] },
    b4fca64: { value: '1', children: ['3f4cc5a','c1aaf27','da5f0dc','c95ca58'] }
  };
  let isHydratingWapf = true;
  let saveSubmitGuardInstalled = false;
  let repeatableAddLimitGuardInstalled = false;
  const repeatableManualSnapshots = {};

  window.teinvitBaptismPreviewConfig.deferInitialBuild = true;
  window.__TEINVIT_BAPTISM_WAPF_READY__ = false;

  function qsa(selector, root){ return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  function escapeHtml(value){ return String(value == null ? '' : value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function normalizeFieldId(id){ return String(id || '').replace(/^field_/,'').replace(/^wapf\[field_/,'').replace(/\]$/,'').replace(/_(?:clone_)?\d+$/,'').trim(); }
  function rawFieldIdFromName(name){ const match = String(name || '').match(/^wapf\[field_([^\]]+)\]/); return match ? String(match[1] || '').trim() : ''; }
  function fieldIdFromName(name){ return normalizeFieldId(rawFieldIdFromName(name)); }
  function isSkippableInput(el){ return !el || (el.type === 'hidden' && el.classList.contains('wapf-tf-h')) || /_qty\]$/.test(String(el.name || '')); }
  function lower(value){ return String(value || '').replace(/\s+/g,' ').trim().toLowerCase(); }
  function splitSelected(raw){
    if (Array.isArray(raw)) return raw.map(function(value){ return String(value || '').trim(); }).filter(Boolean);
    return String(raw || '').split(/[\n,]+/).map(function(value){ return value.trim(); }).filter(Boolean);
  }
  function rawValueForField(map, id){
    if (!map || typeof map !== 'object') return '';
    const matches = [];
    Object.keys(map).forEach(function(key){
      if (normalizeFieldId(key) !== id) return;
      const value = map[key];
      if (Array.isArray(value)) value.forEach(function(part){ if (String(part || '').trim() !== '') matches.push(part); });
      else if (String(value || '').trim() !== '') matches.push(value);
    });
    return matches.length > 1 ? matches : (matches[0] || '');
  }
  function isTruthyRaw(raw){
    const selected = splitSelected(raw);
    if (!selected.length) return false;
    return selected.some(function(value){
      const normalized = lower(value);
      return normalized !== '' && normalized !== '0' && normalized !== 'false' && normalized !== 'nu' && normalized !== 'no' && normalized !== 'off';
    });
  }
  function hasMeaningfulRaw(raw){ return splitSelected(raw).some(function(value){ return isTruthyRaw(value); }); }
  function mapWithInferredParents(map){
    const out = Object.assign({}, map || {});
    Object.keys(parentChildFallbacks).forEach(function(parentId){
      if (isTruthyRaw(rawValueForField(out, parentId))) return;
      const fallback = parentChildFallbacks[parentId] || {};
      const hasChildValue = (fallback.children || []).some(function(childId){ return hasMeaningfulRaw(rawValueForField(out, childId)); });
      if (hasChildValue) out[parentId] = fallback.value || '1';
    });
    return out;
  }
  function labelForInput(el){
    const label = el && el.closest ? el.closest('label') : null;
    return label && label.textContent ? label.textContent.replace(/\s+/g,' ').trim() : '';
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
  function repeatableMax(id){
    const max = parseInt(repeatableMaxById[id] || 0, 10);
    return Number.isFinite(max) && max > 0 ? max : 0;
  }
  function repeatableQtyInput(id){ return saveForm ? saveForm.querySelector('#field_' + id + '_qty, [name="wapf[field_' + id + '_qty]"]') : null; }
  function syncRepeatableQty(id){
    const qty = repeatableQtyInput(id);
    if (qty) qty.value = String(Math.max(0, repeatableInputs(id).length - 1));
  }
  function repeatableValues(id){
    return repeatableInputs(id).map(function(el){ return el ? String(el.value || '') : ''; });
  }
  function restoreRepeatableValuesAfterAdd(id, previousValues){
    const before = Array.isArray(previousValues) ? previousValues.slice(0, repeatableMax(id) || previousValues.length) : [];
    const run = function(){
      const changed = [];
      const inputs = repeatableInputs(id);
      inputs.forEach(function(el, index){
        if (!el) return;
        const nextValue = index < before.length ? before[index] : '';
        if (String(el.value || '') !== String(nextValue || '')) {
          el.value = nextValue;
          changed.push(el);
        }
      });
      syncRepeatableQty(id);
      syncRepeatableControls(id);
      if (changed.length) triggerFieldEvents(changed);
    };
    window.setTimeout(run, 60);
    window.setTimeout(run, 180);
  }
  function repeatableControlText(control){
    return lower((control && control.getAttribute ? control.getAttribute('class') : '') + ' ' + (control && control.getAttribute ? control.getAttribute('data-action') : '') + ' ' + (control && control.value ? control.value : '') + ' ' + (control && control.textContent ? control.textContent : ''));
  }
  function repeatableControls(id){
    if (!saveForm) return [];
    const scopes = qsa('.cloner-' + id + ', .field-' + id + ', [data-field-id="' + id + '"]', saveForm);
    const seen = [];
    const controls = [];
    scopes.forEach(function(scope){
      qsa('button,a,[role="button"],input[type="button"]', scope).forEach(function(control){
        if (seen.indexOf(control) !== -1) return;
        seen.push(control);
        controls.push(control);
      });
    });
    return controls;
  }
  function isRepeatableAddControl(control){
    const text = repeatableControlText(control);
    if (text.indexOf('delete') !== -1 || text.indexOf('remove') !== -1 || text.indexOf('unrepeat') !== -1 || text.indexOf('del') !== -1 || text.indexOf('sterge') !== -1 || text.indexOf('șterge') !== -1 || text.indexOf('şterge') !== -1 || text === '-') return false;
    return (text.indexOf('add') !== -1 || text.indexOf('clone') !== -1 || text.indexOf('repeat') !== -1 || text.indexOf('adauga') !== -1 || text.indexOf('adaugă') !== -1 || text.indexOf('adaug') !== -1 || text === '+') && text.indexOf('delete') === -1 && text.indexOf('remove') === -1 && text.indexOf('sterge') === -1 && text.indexOf('șterge') === -1;
  }
  function isRepeatableRemoveControl(control){
    const text = repeatableControlText(control);
    if (text.indexOf('șterge') !== -1 || text.indexOf('şterge') !== -1) return true;
    return text.indexOf('delete') !== -1 || text.indexOf('remove') !== -1 || text.indexOf('unrepeat') !== -1 || text.indexOf('sterge') !== -1 || text.indexOf('șterge') !== -1 || text.indexOf('del') !== -1 || text === '-';
  }
  function setRepeatableControlState(control, visible, disabled){
    if (!control) return;
    control.style.display = visible ? '' : 'none';
    if ('disabled' in control) control.disabled = !!disabled;
    if (disabled) control.setAttribute('aria-disabled', 'true');
    else control.removeAttribute('aria-disabled');
  }
  function syncRepeatableControls(id){
    const max = repeatableMax(id);
    const count = repeatableInputs(id).length;
    repeatableControls(id).forEach(function(control){
      if (isRepeatableAddControl(control)) {
        setRepeatableControlState(control, !max || count < max, !!max && count >= max);
      } else if (isRepeatableRemoveControl(control)) {
        setRepeatableControlState(control, count > 1, count <= 1);
      }
    });
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
  function markCloneButtonsAsNonSubmit(){
    if (!saveForm) return;
    qsa('.wapf-add-clone, .wapf-del-clone, .wapf-repeatable-add, .wapf-repeatable-remove, .wapf-clone-add, .wapf-clone-remove', saveForm).forEach(function(btn){
      if (btn && btn.tagName === 'BUTTON') btn.setAttribute('type', 'button');
    });
  }
  function clearNewRepeatableClone(node, id){
    if (!node) return [];
    const changed = [];
    qsa('[name^="wapf[field_' + id + '_"]', node).forEach(function(el){
      if (isSkippableInput(el)) return;
      if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
      else el.value = '';
      changed.push(el);
    });
    if (changed.length) triggerFieldEvents(changed);
    return changed;
  }
  function createRepeatableClone(id){
    markCloneButtonsAsNonSubmit();
    const max = repeatableMax(id);
    if (max && repeatableInputs(id).length >= max) {
      syncRepeatableControls(id);
      return false;
    }
    const $wrapper = wapfWrapper();
    const $field = repeatableFieldElement(id);
    if (window.WAPF && window.WAPF.Util && typeof window.WAPF.Util.repeat === 'function' && $wrapper && $field && $field.length) {
      const $clone = window.WAPF.Util.repeat($wrapper, $field);
      const $cloner = window.jQuery(saveForm).find('.cloner-' + id).first();
      if ($clone && $clone.length && $cloner && $cloner.length) $cloner.appendTo($clone);
      if (window.WAPF.Pricing && typeof window.WAPF.Pricing.calculateAll === 'function') {
        try { window.WAPF.Pricing.calculateAll($wrapper); } catch (e) {}
      }
      markCloneButtonsAsNonSubmit();
      syncRepeatableQty(id);
      syncRepeatableControls(id);
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
        if ($cloner && $cloner.length && $target && $target.length) $cloner.appendTo($target);
        window.WAPF.Util.unrepeat($wrapper, $field, 1);
        syncRepeatableQty(id);
        syncRepeatableControls(id);
        return true;
      } catch (e) {}
    }
    const inputs = repeatableInputs(id);
    const last = inputs[inputs.length - 1];
    const row = last && last.closest('.field-' + id + ', .wapf-field-container, .wapf-field');
    if (row && inputs.length > 1) {
      row.parentNode.removeChild(row);
      syncRepeatableQty(id);
      syncRepeatableControls(id);
      return true;
    }
    return false;
  }
  function ensureRepeatableInputs(id, values){
    let inputs = repeatableInputs(id);
    let guard = 0;
    const max = repeatableMax(id);
    const targetCount = max ? Math.min(max, Math.max(1, values.length || 1)) : Math.max(1, values.length || 1);
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
    syncRepeatableControls(id);
    return inputs;
  }
  function bindManualRepeatableCloneReset(){
    if (!window.jQuery || !saveForm) return;
    window.jQuery(document).off('wapf/cloned.teinvitBaptismAdmin').on('wapf/cloned.teinvitBaptismAdmin', function(e, fieldId, cloneNumber, clone){
      const id = normalizeFieldId(fieldId);
      if (repeatableFieldIds.indexOf(id) === -1) return;
      if (isHydratingWapf) {
        syncRepeatableQty(id);
        syncRepeatableControls(id);
        return;
      }
      const previousValues = repeatableManualSnapshots[id] || repeatableValues(id).slice(0, Math.max(0, repeatableInputs(id).length - 1));
      restoreRepeatableValuesAfterAdd(id, previousValues);
      syncRepeatableQty(id);
      syncRepeatableControls(id);
    });
  }
  function installRepeatableAddLimitGuard(){
    if (!saveForm || repeatableAddLimitGuardInstalled) return;
    repeatableAddLimitGuardInstalled = true;
    saveForm.addEventListener('click', function(e){
      const control = e.target && e.target.closest ? e.target.closest('button,a,[role="button"],input[type="button"]') : null;
      if (!control) return;
      repeatableFieldIds.forEach(function(id){
        const controls = repeatableControls(id);
        if (controls.indexOf(control) === -1) return;
        if (isRepeatableAddControl(control)) {
          const max = repeatableMax(id);
          if (max && repeatableInputs(id).length >= max) {
            e.preventDefault();
            e.stopImmediatePropagation();
            syncRepeatableControls(id);
          } else {
            repeatableManualSnapshots[id] = repeatableValues(id);
            restoreRepeatableValuesAfterAdd(id, repeatableManualSnapshots[id]);
          }
        } else if (isRepeatableRemoveControl(control)) {
          delete repeatableManualSnapshots[id];
          window.setTimeout(function(){ syncRepeatableQty(id); syncRepeatableControls(id); }, 80);
        }
      });
    }, true);
  }
  function applyRepeatableField(id, raw){
    const max = repeatableMax(id);
    const values = splitSelected(raw).slice(0, max || 4);
    const inputs = ensureRepeatableInputs(id, values.length ? values : ['']);
    if (!inputs.length) return [];
    if (values.length > 1 && inputs.length < values.length) {
      inputs[0].value = values.join(', ');
      return [inputs[0]];
    }
    inputs.forEach(function(el, index){ el.value = values[index] || (index === 0 ? String(raw || '') : ''); });
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
        el.checked = selectedLower.indexOf(lower(el.value)) !== -1 || selectedLower.indexOf(lower(labelForInput(el))) !== -1 || (parentTruthy && !hasExactCheckboxMatch && index === 0);
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
        if (repeatableFieldIds.indexOf(id) !== -1) changed = changed.concat(applyRepeatableField(id, raw));
        else changed = changed.concat(applyStandardField(id, groups[id], raw));
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
    isHydratingWapf = true;
    markCloneButtonsAsNonSubmit();
    window.__TEINVIT_BAPTISM_WAPF_READY__ = false;
    if (opts.invitation) window.TEINVIT_INVITATION_DATA = opts.invitation;
    setWapfValues(map || {}, { phase: 'parents', triggerEvents: false });
    flushWapfDependencies();
    setWapfValues(map || {}, { phase: 'children', triggerEvents: false });
    document.dispatchEvent(new CustomEvent('teinvit:variant-applied'));
    window.setTimeout(function(){
      markCloneButtonsAsNonSubmit();
      setWapfValues(map || {}, { phase: 'children', triggerEvents: false });
      flushWapfDependencies();
      serializeParentCheckedState();
      repeatableFieldIds.forEach(syncRepeatableControls);
      isHydratingWapf = false;
      window.__TEINVIT_BAPTISM_WAPF_READY__ = true;
      document.dispatchEvent(new CustomEvent('teinvit:baptism-wapf-hydrated', { detail: { initial: !!opts.initial } }));
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
  installRepeatableAddLimitGuard();
  document.addEventListener('DOMContentLoaded', function(){
    markCloneButtonsAsNonSubmit();
    bindManualRepeatableCloneReset();
    installRepeatableAddLimitGuard();
    if (saveForm) {
      serializeParentCheckedState();
      saveForm.addEventListener('input', serializeParentCheckedState);
      saveForm.addEventListener('change', serializeParentCheckedState);
      installSaveSubmitGuard();
    }
    window.setTimeout(function(){ hydrateWapf(initialWapf || {}, { initial: true, invitation: window.TEINVIT_INVITATION_DATA || null }); }, 30);
  });

  const dateToggle = document.getElementById('date_confirm');
  const dateWrap = document.getElementById('selecteaza-data-wrap');
  if (dateToggle && dateWrap) dateToggle.addEventListener('change', function(){ dateWrap.style.display = dateToggle.checked ? '' : 'none'; });

  qsa('.teinvit-variant-radio').forEach(function(radio){
    radio.addEventListener('change', function(){
      const id = parseInt(radio.value || '0', 10);
      if (id > 0) window.location.href = baseUrl + '?selected_version_id=' + encodeURIComponent(id);
    });
  });

  async function copyText(text){
    if (navigator.clipboard && navigator.clipboard.writeText) { await navigator.clipboard.writeText(text); return; }
    const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
  }
  const shareStatus = document.getElementById('teinvit-share-status');
  const nativeBtn = document.getElementById('teinvit-share-native');
  const copyBtn = document.getElementById('teinvit-share-copy-main');
  const whatsappBtn = document.getElementById('teinvit-share-whatsapp');
  const instaBtn = document.getElementById('teinvit-share-instagram');
  if (whatsappBtn) whatsappBtn.setAttribute('href', 'https://wa.me/?text=' + encodeURIComponent(shareMessage));
  if (nativeBtn) nativeBtn.addEventListener('click', async function(){
    try {
      if (navigator.share) await navigator.share({ title: shareTitle, text: shareText, url: shareUrl });
      else await copyText(shareUrl);
      if (shareStatus) shareStatus.textContent = 'Link pregătit pentru distribuire.';
    } catch(e) {}
  });
  if (copyBtn) copyBtn.addEventListener('click', async function(){ await copyText(shareUrl); if (shareStatus) shareStatus.textContent = 'Link copiat.'; });
  if (instaBtn) instaBtn.addEventListener('click', async function(){ await copyText(shareUrl); if (shareStatus) shareStatus.textContent = 'Link copiat pentru Instagram.'; });
  qsa('.teinvit-share-pdf-btn').forEach(function(btn){
    btn.addEventListener('click', async function(){
      const pdfUrl = btn.getAttribute('data-pdf-url') || '';
      const status = document.getElementById('teinvit-pdf-share-status');
      try {
        if (navigator.share) await navigator.share({ title: shareTitle, text: shareText, url: pdfUrl });
        else await copyText(pdfUrl);
        if (status) status.textContent = 'PDF pregătit pentru distribuire.';
      } catch(e) {}
    });
  });

  const giftsBody = document.getElementById('teinvit-baptism-gifts-body');
  const giftsForm = document.getElementById('teinvit-baptism-gifts-form');
  const showGiftsCheckbox = document.getElementById('teinvit-baptism-show-gifts-section');
  const giftsEditor = document.getElementById('teinvit-baptism-gifts-editor');
  const addGiftBtn = document.getElementById('teinvit-baptism-add-gift');
  const giftsCounter = document.getElementById('teinvit-baptism-gifts-counter');
  const buyGiftsBtn = document.getElementById('teinvit-baptism-buy-gifts');
  function giftRowsCount(){
    if (!giftsBody) return 0;
    let used = 0;
    qsa('tr[data-gift-row="1"]', giftsBody).forEach(function(row){
      const name = (row.querySelector('input[data-field="gift_name"]')?.value || '').trim();
      const link = (row.querySelector('input[data-field="gift_link"]')?.value || '').trim();
      if (name || link) used++;
    });
    return used;
  }
  function refreshGiftsCounter(){
    const remaining = Math.max(0, giftsMaxSlots - giftRowsCount());
    if (giftsCounter) giftsCounter.textContent = 'Mai poți adăuga ' + remaining + ' cadouri în listă';
    if (addGiftBtn) addGiftBtn.disabled = remaining === 0;
    if (buyGiftsBtn) buyGiftsBtn.style.display = remaining === 0 ? 'inline-block' : 'none';
  }
  function createGiftRow(item, index){
    if (!giftsBody) return;
    const tr = document.createElement('tr');
    tr.setAttribute('data-gift-row', '1');
    const locked = Number(item.published_locked || 0) === 1;
    const includeChecked = Number(item.include_in_public || 0) === 1;
    const hasName = String(item.gift_name || '').trim() !== '';
    const hasLink = String(item.gift_link || '').trim() !== '';
    const hasAddress = String(item.gift_delivery_address || '').trim() !== '';
    tr.innerHTML = `
      <td><input type="hidden" name="gifts[${index}][gift_id]" value="${escapeHtml(item.gift_id || '')}"><input type="checkbox" name="gifts[${index}][include_in_public]" value="1" ${includeChecked ? 'checked' : ''}></td>
      <td><input type="text" data-field="gift_name" name="gifts[${index}][gift_name]" value="${escapeHtml(item.gift_name || '')}" ${(locked && hasName) ? 'readonly' : ''}></td>
      <td><input type="url" data-field="gift_link" name="gifts[${index}][gift_link]" value="${escapeHtml(item.gift_link || '')}" ${(locked && hasLink) ? 'readonly' : ''}></td>
      <td><textarea name="gifts[${index}][gift_delivery_address]" ${(locked && hasAddress) ? 'readonly' : ''}>${escapeHtml(item.gift_delivery_address || '')}</textarea></td>
      <td>${escapeHtml(item.reserved_by || 'Disponibil')}</td>`;
    qsa('input[data-field="gift_name"], input[data-field="gift_link"]', tr).forEach(el => el.addEventListener('input', refreshGiftsCounter));
    giftsBody.appendChild(tr);
  }
  function renderGifts(){
    if (!giftsBody) return;
    giftsBody.innerHTML = '';
    giftsInitial.forEach(createGiftRow);
    if (!giftsInitial.length) createGiftRow({ gift_id:'', gift_name:'', gift_link:'', gift_delivery_address:'', include_in_public:1, published_locked:0, reserved_by:'Disponibil' }, 0);
    refreshGiftsCounter();
  }
  if (showGiftsCheckbox && giftsEditor) showGiftsCheckbox.addEventListener('change', function(){ giftsEditor.style.display = showGiftsCheckbox.checked ? '' : 'none'; });
  if (addGiftBtn) addGiftBtn.addEventListener('click', function(){ createGiftRow({ gift_id:'', gift_name:'', gift_link:'', gift_delivery_address:'', include_in_public:1, published_locked:0, reserved_by:'Disponibil' }, qsa('tr[data-gift-row="1"]', giftsBody).length); refreshGiftsCounter(); });
  if (giftsForm) {
    giftsForm.addEventListener('submit', function(e){
      const shouldConfirm = !showGiftsCheckbox || !!showGiftsCheckbox.checked;
      if (!shouldConfirm) return;
      if (!window.confirm('După salvarea listei cadourile completate nu mai pot fi editate. Ești sigur că vrei să o salvezi?')) {
        e.preventDefault();
      }
    });
  }
  renderGifts();

  function refreshReport(){
    const view = document.querySelector('input[name="teinvit-baptism-report-view"]:checked')?.value || 'unique';
    const onlyMulti = !!document.getElementById('teinvit-baptism-filter-multi')?.checked;
    const onlyMessage = !!document.getElementById('teinvit-baptism-filter-message')?.checked;
    qsa('[data-report-view]').forEach(function(wrap){ wrap.hidden = wrap.getAttribute('data-report-view') !== view; });
    qsa('[data-report-view="' + view + '"] tbody tr').forEach(function(row){
      const hidden = (onlyMulti && row.getAttribute('data-multi') !== '1') || (onlyMessage && row.getAttribute('data-message') !== '1');
      row.style.display = hidden ? 'none' : '';
    });
  }
  qsa('input[name="teinvit-baptism-report-view"],#teinvit-baptism-filter-multi,#teinvit-baptism-filter-message').forEach(el => el.addEventListener('change', refreshReport));
  refreshReport();
})();
</script>
