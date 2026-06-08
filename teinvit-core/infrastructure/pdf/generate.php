<?php
/**
 * TeInvit – PDF Generator
 * CANONIC + DEBUG (Order Notes)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_pdf_public_base_url() {
    $base_url = defined( 'TEINVIT_NODE_ENDPOINT' )
        ? preg_replace( '#/api/render/?$#', '', (string) TEINVIT_NODE_ENDPOINT )
        : 'https://pdf.teinvit.com';
    $base_url = rtrim( (string) $base_url, '/' );

    return $base_url !== '' ? $base_url : 'https://pdf.teinvit.com';
}

function teinvit_pdf_public_url_from_filename( $order_id, $filename ) {
    $order_id = (int) $order_id;
    $filename = basename( str_replace( '\\', '/', (string) $filename ) );
    $filename = (string) preg_replace( '/[\x00-\x1F\x7F]/', '', $filename );
    if ( $order_id <= 0 || $filename === '' ) {
        return '';
    }

    return esc_url_raw( teinvit_pdf_public_base_url() . '/pdf/' . $order_id . '/' . rawurlencode( $filename ) );
}

function teinvit_phase4_pdf_slug_segment( $value, $fallback = 'invitatie' ) {
    $value = trim( (string) $value );
    if ( function_exists( 'remove_accents' ) ) {
        $value = remove_accents( $value );
    }

    $slug = sanitize_title( $value );
    if ( $slug === '' ) {
        $slug = sanitize_title( $fallback );
    }

    return $slug !== '' ? strtolower( $slug ) : 'invitatie';
}

function teinvit_phase4_pdf_token_segment( $token ) {
    $token = strtolower( sanitize_file_name( (string) $token ) );
    $token = preg_replace( '/[^a-z0-9\-]/', '', $token );

    return $token !== '' ? $token : 'token';
}

/* =====================================================
   CONFIG
===================================================== */
define( 'TEINVIT_MAX_PDF_ATTEMPTS', 3 );
define( 'TEINVIT_NODE_ENDPOINT', 'https://pdf.teinvit.com/api/render' );
define( 'TEINVIT_NODE_DELETE_ENDPOINT', 'https://pdf.teinvit.com/api/delete' );
define( 'TEINVIT_PDF_CLEANUP_HOOK', 'teinvit_pdf_cleanup_nightly' );

/* =====================================================
   HELPER – verifică dacă /i/{token} e public (ROBUST)
===================================================== */
function teinvit_invitation_url_exists( $token ) {

    $response = wp_remote_get(
        home_url( '/i/' . $token ),
        array(
            'timeout'     => 15,
            'redirection' => 5,
        )
    );

    if ( is_wp_error( $response ) ) {
        return false;
    }

    $code = wp_remote_retrieve_response_code( $response );

    // Acceptăm 2xx și 3xx
    return ( $code >= 200 && $code < 400 );
}

/* =====================================================
   CANONIC – FUNCȚIA UNICĂ DE GENERARE PDF
===================================================== */
function teinvit_phase4_pdf_tables_for_token( $token ) {
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return [];
    }

    $vertical = function_exists( 'teinvit_resolve_token_vertical' ) ? teinvit_resolve_token_vertical( $token ) : '';
    if ( function_exists( 'teinvit_storage_tables_for_existing_token' ) ) {
        $tables = teinvit_storage_tables_for_existing_token( $token, $vertical );
        return is_array( $tables ) ? $tables : [];
    }

    return function_exists( 'teinvit_db_tables' ) ? teinvit_db_tables() : [];
}

function teinvit_phase4_pdf_version_row( $token, $version_id = 0 ) {
    global $wpdb;

    $token = sanitize_text_field( (string) $token );
    $version_id = (int) $version_id;
    $tables = teinvit_phase4_pdf_tables_for_token( $token );
    if ( $token === '' || empty( $tables['versions'] ) ) {
        return null;
    }

    if ( $version_id > 0 ) {
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$tables['versions']} WHERE token = %s AND id = %d LIMIT 1", $token, $version_id ),
            ARRAY_A
        );
        return is_array( $row ) ? $row : null;
    }

    if ( ! empty( $tables['invitations'] ) ) {
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT v.* FROM {$tables['versions']} v INNER JOIN {$tables['invitations']} i ON i.active_version_id = v.id WHERE i.token = %s LIMIT 1",
                $token
            ),
            ARRAY_A
        );
        if ( is_array( $row ) ) {
            return $row;
        }
    }

    $row = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$tables['versions']} WHERE token = %s ORDER BY id ASC LIMIT 1", $token ),
        ARRAY_A
    );

    return is_array( $row ) ? $row : null;
}

function teinvit_phase4_pdf_variant_number_for_version( $token, $version_id ) {
    global $wpdb;

    $token = sanitize_text_field( (string) $token );
    $version_id = (int) $version_id;
    $tables = teinvit_phase4_pdf_tables_for_token( $token );
    if ( $token === '' || $version_id <= 0 || empty( $tables['versions'] ) ) {
        return 0;
    }

    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['versions']} WHERE token = %s AND id <= %d",
            $token,
            $version_id
        )
    );

    return max( 0, $count - 1 );
}

function teinvit_phase4_pdf_filename_for_version( $token, $version_id, $variant_number = null ) {
    $token = sanitize_text_field( (string) $token );
    $version_id = (int) $version_id;
    if ( $token === '' || $version_id <= 0 ) {
        return '';
    }

    $context = function_exists( 'teinvit_resolve_token_context' ) ? teinvit_resolve_token_context( $token ) : [];
    $product_slug = is_array( $context ) ? sanitize_title( (string) ( $context['product_slug'] ?? '' ) ) : '';
    if ( $product_slug === '' && is_array( $context ) ) {
        $product_slug = teinvit_phase4_pdf_slug_segment( (string) ( $context['product_name'] ?? '' ) );
    }
    if ( $product_slug === '' ) {
        $product_slug = 'invitatie';
    }

    if ( $variant_number === null ) {
        $variant_number = teinvit_phase4_pdf_variant_number_for_version( $token, $version_id );
    }

    return strtolower( sanitize_file_name( sprintf(
        '%s-%s-v%d-id%d.pdf',
        teinvit_phase4_pdf_slug_segment( $product_slug ),
        teinvit_phase4_pdf_token_segment( $token ),
        max( 0, (int) $variant_number ),
        $version_id
    ) ) );
}

function teinvit_phase4_order_note_once( WC_Order $order, $key, $note ) {
    $key = sanitize_key( 'phase4_pdf_' . md5( (string) $key ) );
    if ( $key === '' ) {
        return false;
    }

    $stored = $order->get_meta( '_teinvit_phase4_pdf_note_keys', true );
    if ( ! is_array( $stored ) ) {
        $stored = [];
    }

    if ( in_array( $key, $stored, true ) ) {
        return false;
    }

    $order->add_order_note( (string) $note );
    $stored[] = $key;
    $order->update_meta_data( '_teinvit_phase4_pdf_note_keys', array_values( array_unique( $stored ) ) );

    return true;
}

function teinvit_phase4_update_order_token_pdf_status( $token, $status, $last_error = '' ) {
    if ( ! function_exists( 'teinvit_get_order_token_row' ) || ! function_exists( 'teinvit_update_order_token_row' ) ) {
        return false;
    }

    $row = teinvit_get_order_token_row( $token );
    if ( ! is_array( $row ) || ! empty( $row['legacy'] ) ) {
        return false;
    }

    return teinvit_update_order_token_row( (int) $row['id'], [
        'pdf_status' => sanitize_key( (string) $status ),
        'last_error' => sanitize_textarea_field( (string) $last_error ),
        'debug_context' => [
            'phase' => 'phase4_pdf_generation',
            'pdf_status' => sanitize_key( (string) $status ),
        ],
    ] );
}

function teinvit_phase4_update_version_pdf_status( $token, $version_id, $status, $filename = '', $pdf_url = '' ) {
    global $wpdb;

    $token = sanitize_text_field( (string) $token );
    $version_id = (int) $version_id;
    $tables = teinvit_phase4_pdf_tables_for_token( $token );
    if ( $token === '' || $version_id <= 0 || empty( $tables['versions'] ) ) {
        return false;
    }

    $status = sanitize_key( (string) $status );
    $data = [ 'pdf_status' => $status ];
    if ( $filename !== '' ) {
        $data['pdf_filename'] = sanitize_file_name( $filename );
    }
    if ( $status === 'generated' ) {
        $data['pdf_url'] = esc_url_raw( (string) $pdf_url );
        $data['pdf_generated_at'] = current_time( 'mysql' );
    } elseif ( $status === 'failed' ) {
        $data['pdf_generated_at'] = current_time( 'mysql' );
    }

    return $wpdb->update(
        $tables['versions'],
        $data,
        [ 'id' => $version_id, 'token' => $token ]
    );
}

function teinvit_phase4_call_node_for_pdf( $token, $order_id, $filename, $version_id ) {
    $payload = [
        'token' => sanitize_text_field( (string) $token ),
        'order_id' => (int) $order_id,
        'filename' => sanitize_file_name( (string) $filename ),
        'version_id' => (int) $version_id,
    ];

    $response = wp_remote_post(
        TEINVIT_NODE_ENDPOINT,
        [
            'timeout' => 240,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body' => wp_json_encode( $payload ),
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $body = (string) wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );
    if ( $code < 200 || $code >= 300 || ! is_array( $data ) || ( $data['status'] ?? '' ) !== 'ok' ) {
        $message = is_array( $data ) && ! empty( $data['message'] ) ? (string) $data['message'] : 'PDF generation failed.';
        return new WP_Error( 'teinvit_pdf_render_failed', $message, [ 'http_code' => $code, 'body' => substr( $body, 0, 500 ) ] );
    }

    $pdf_url = ! empty( $data['pdf_url'] )
        ? esc_url_raw( teinvit_pdf_public_base_url() . (string) $data['pdf_url'] )
        : teinvit_pdf_public_url_from_filename( $order_id, $filename );

    return [
        'pdf_url' => $pdf_url,
        'pdf_filename' => sanitize_file_name( (string) $filename ),
        'node_response' => $data,
    ];
}

function teinvit_generate_pdf_for_token_version( $token, $version_id = 0, $manual = false, array $args = [] ) {
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return new WP_Error( 'empty_token', 'Token missing.' );
    }

    $context = function_exists( 'teinvit_resolve_token_context' ) ? teinvit_resolve_token_context( $token ) : [];
    if ( ! is_array( $context ) || empty( $context['valid'] ) ) {
        return new WP_Error( 'invalid_token', 'Token invalid.' );
    }

    $order_id = (int) ( $context['order_id'] ?? 0 );
    $order = $order_id > 0 && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
    if ( ! $order ) {
        return new WP_Error( 'invalid_order', 'Order invalid.' );
    }

    $version_row = teinvit_phase4_pdf_version_row( $token, $version_id );
    if ( ! is_array( $version_row ) && function_exists( 'teinvit_ensure_active_snapshot_payload' ) ) {
        teinvit_ensure_active_snapshot_payload( $token, $order );
        $version_row = teinvit_phase4_pdf_version_row( $token, $version_id );
    }
    if ( ! is_array( $version_row ) ) {
        teinvit_phase4_update_order_token_pdf_status( $token, 'failed', 'missing_version' );
        return new WP_Error( 'missing_version', 'PDF version missing.' );
    }

    $version_id = (int) ( $version_row['id'] ?? 0 );
    if ( $version_id <= 0 ) {
        teinvit_phase4_update_order_token_pdf_status( $token, 'failed', 'missing_version_id' );
        return new WP_Error( 'missing_version_id', 'PDF version id missing.' );
    }

    $variant_number = teinvit_phase4_pdf_variant_number_for_version( $token, $version_id );
    $filename = teinvit_phase4_pdf_filename_for_version( $token, $version_id, $variant_number );
    if ( $filename === '' ) {
        teinvit_phase4_update_order_token_pdf_status( $token, 'failed', 'missing_filename' );
        return new WP_Error( 'missing_filename', 'PDF filename missing.' );
    }

    $existing_status = sanitize_key( (string) ( $version_row['pdf_status'] ?? '' ) );
    $existing_url = esc_url_raw( (string) ( $version_row['pdf_url'] ?? '' ) );
    $existing_filename = sanitize_file_name( (string) ( $version_row['pdf_filename'] ?? '' ) );
    if ( ! $manual && $existing_status === 'generated' && $existing_url !== '' && $existing_filename === $filename ) {
        teinvit_phase4_update_order_token_pdf_status( $token, 'generated', '' );
        return [
            'status' => 'generated',
            'skipped' => true,
            'pdf_url' => $existing_url,
            'pdf_filename' => $filename,
            'version_id' => $version_id,
            'variant_number' => $variant_number,
        ];
    }

    teinvit_phase4_update_order_token_pdf_status( $token, 'pending', '' );
    teinvit_phase4_update_version_pdf_status( $token, $version_id, 'processing', $filename, '' );
    teinvit_phase4_order_note_once(
        $order,
        'started_' . $token . '_' . $version_id . '_' . $filename,
        sprintf( 'TeInvit: PDF generation started for token %s version v%d-id%d: %s', $token, $variant_number, $version_id, $filename )
    );
    $order->save();

    $result = teinvit_phase4_call_node_for_pdf( $token, $order_id, $filename, $version_id );
    if ( is_wp_error( $result ) ) {
        $message = $result->get_error_message();
        teinvit_phase4_update_order_token_pdf_status( $token, 'failed', $message );
        teinvit_phase4_update_version_pdf_status( $token, $version_id, 'failed', $filename, '' );
        teinvit_phase4_order_note_once(
            $order,
            'failed_' . $token . '_' . $version_id . '_' . md5( $message ),
            sprintf( 'TeInvit: PDF failed for token %s version v%d-id%d: %s', $token, $variant_number, $version_id, $message )
        );
        $order->save();

        return $result;
    }

    $pdf_url = esc_url_raw( (string) ( $result['pdf_url'] ?? '' ) );
    teinvit_phase4_update_version_pdf_status( $token, $version_id, 'generated', $filename, $pdf_url );
    teinvit_phase4_update_order_token_pdf_status( $token, 'generated', '' );
    teinvit_phase4_order_note_once(
        $order,
        'generated_' . $token . '_' . $version_id . '_' . $filename,
        sprintf( 'TeInvit: generated PDF for token %s version v%d-id%d: %s (%s)', $token, $variant_number, $version_id, $filename, $pdf_url )
    );
    $order->save();

    return [
        'status' => 'generated',
        'skipped' => false,
        'pdf_url' => $pdf_url,
        'pdf_filename' => $filename,
        'version_id' => $version_id,
        'variant_number' => $variant_number,
    ];
}

function teinvit_generate_pdfs_for_order_tokens( $order_id, $manual = false ) {
    $order_id = (int) $order_id;
    $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
    if ( ! $order || ! function_exists( 'teinvit_get_order_tokens_for_order' ) ) {
        return null;
    }

    $rows = teinvit_get_order_tokens_for_order( $order_id );
    if ( empty( $rows ) ) {
        return null;
    }

    $generated = 0;
    $skipped = 0;
    $failed = 0;

    foreach ( $rows as $row ) {
        $token = sanitize_text_field( (string) ( $row['token'] ?? '' ) );
        if ( $token === '' || ! empty( $row['legacy'] ) ) {
            continue;
        }

        $result = teinvit_generate_pdf_for_token_version( $token, 0, $manual );
        if ( is_wp_error( $result ) ) {
            $failed++;
            continue;
        }

        if ( ! empty( $result['skipped'] ) ) {
            $skipped++;
        } else {
            $generated++;
        }
    }

    teinvit_phase4_order_note_once(
        $order,
        'summary_' . $order_id . '_' . ( $manual ? 'manual' : 'auto' ) . '_' . $generated . '_' . $skipped . '_' . $failed,
        sprintf( 'TeInvit: token-level PDF summary: generated %d, reused %d, failed %d.', $generated, $skipped, $failed )
    );
    $order->save();

    return [
        'generated' => $generated,
        'skipped' => $skipped,
        'failed' => $failed,
    ];
}

function teinvit_phase4_record_token_generated_pdf_result( $order_id, array $context, $result ) {
    static $batches = [];

    $order_id = (int) $order_id;
    $expected = max( 1, (int) ( $context['eligible_token_count'] ?? 1 ) );
    if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
        return;
    }

    $key = $order_id . ':' . $expected;
    if ( ! isset( $batches[ $key ] ) ) {
        $batches[ $key ] = [
            'total' => 0,
            'generated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];
    }

    $batches[ $key ]['total']++;
    if ( is_wp_error( $result ) ) {
        $batches[ $key ]['failed']++;
    } elseif ( ! empty( $result['skipped'] ) ) {
        $batches[ $key ]['skipped']++;
    } else {
        $batches[ $key ]['generated']++;
    }

    if ( $batches[ $key ]['total'] < $expected ) {
        return;
    }

    $order = wc_get_order( $order_id );
    if ( $order ) {
        teinvit_phase4_order_note_once(
            $order,
            'hook_summary_' . $key . '_' . $batches[ $key ]['generated'] . '_' . $batches[ $key ]['skipped'] . '_' . $batches[ $key ]['failed'],
            sprintf(
                'TeInvit: initial token-level PDF summary: generated %d, reused %d, failed %d.',
                (int) $batches[ $key ]['generated'],
                (int) $batches[ $key ]['skipped'],
                (int) $batches[ $key ]['failed']
            )
        );
        $order->save();
    }

    unset( $batches[ $key ] );
}

/* Legacy entry point: routes new order_tokens orders to Phase 4 token PDFs. */
function teinvit_try_generate_pdf( $order_id, $manual = false ) {

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    if ( function_exists( 'teinvit_get_order_tokens_for_order' ) ) {
        $order_token_rows = teinvit_get_order_tokens_for_order( (int) $order_id );
        if ( is_array( $order_token_rows ) && ! empty( $order_token_rows ) ) {
            teinvit_generate_pdfs_for_order_tokens( (int) $order_id, (bool) $manual );
            return;
        }
    }

    /* =========================
       TOKEN
    ========================= */
    $token = get_post_meta( $order_id, '_teinvit_token', true );
    if ( empty( $token ) ) {
        $order->add_order_note(
            '[TeInvit DEBUG] Token missing in DB. PDF generation aborted.'
        );
        return;
    }

    /* =========================
       STATUS CHECK (automat)
    ========================= */
    if ( ! $manual ) {
        $status = $order->get_meta( '_teinvit_pdf_status' );
        if ( $status === 'generated' ) {
            return;
        }
    }

    /* =========================
       ATTEMPTS
    ========================= */
    $attempts = (int) $order->get_meta( '_teinvit_pdf_attempts' );
    if ( ! $manual && $attempts >= TEINVIT_MAX_PDF_ATTEMPTS ) {
        $order->update_meta_data( '_teinvit_pdf_status', 'error' );
        $order->add_order_note(
            '[TeInvit DEBUG] Max PDF attempts reached.'
        );
        $order->save();
        return;
    }

    /* =========================
       PREVIEW VALID (automat)
    ========================= */
    if ( ! $manual && ! teinvit_invitation_url_exists( $token ) ) {
        $order->add_order_note(
            '[TeInvit DEBUG] Preview not public yet. Retry scheduled.'
        );
        wp_schedule_single_event(
            time() + 180,
            'teinvit_retry_pdf_generation',
            array( $order_id )
        );
        return;
    }

    $order->update_meta_data( '_teinvit_pdf_attempts', $attempts + 1 );
    $order->update_meta_data( '_teinvit_pdf_status', 'pending' );
    $order->save();

    /* =========================
       PDF FILENAME – CANONIC
       {nume produs} - {order_id}.pdf
    ========================= */
    $items = $order->get_items();
    $product_name = '';

    if ( ! empty( $items ) ) {
        $first_item   = reset( $items );
        $product_name = $first_item->get_name();
    }

    if ( empty( $product_name ) ) {
        $product_name = 'Produs';
    }

    // Curățare nume fișier (filesystem-safe)
    $safe_product_name = sanitize_file_name( $product_name );

    $filename = $safe_product_name . ' - ' . $order_id . '.pdf';

    /* =========================
       CALL NODE (DEBUG)
    ========================= */
    $payload = array(
        'token'    => $token,
        'order_id' => $order_id,
        'filename' => $filename,
    );

    $order->add_order_note(
        "[TeInvit DEBUG] Calling Node endpoint:\n" .
        TEINVIT_NODE_ENDPOINT . "\nPayload:\n" .
        print_r( $payload, true )
    );

    $response = wp_remote_post(
        TEINVIT_NODE_ENDPOINT,
        array(
            'timeout' => 240,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( $payload ),
        )
    );

    if ( is_wp_error( $response ) ) {
        $order->update_meta_data( '_teinvit_pdf_status', 'error' );
        $order->add_order_note(
            '[TeInvit DEBUG] Node unreachable: ' .
            $response->get_error_message()
        );
        $order->save();
        return;
    }

    $http_code   = wp_remote_retrieve_response_code( $response );
    $headers     = wp_remote_retrieve_headers( $response );
    $body        = wp_remote_retrieve_body( $response );
    $body_sample = substr( $body, 0, 500 );

    $order->add_order_note(
        "[TeInvit DEBUG] Node response received\n" .
        "HTTP code: {$http_code}\n" .
        "Content-Type: " . ( $headers['content-type'] ?? 'n/a' ) . "\n" .
        "Body (first 500 chars):\n" .
        $body_sample
    );

    $data = json_decode( $body, true );

    if ( isset( $data['status'] ) && $data['status'] === 'ok' ) {

        $order->update_meta_data(
            '_teinvit_pdf_url',
            esc_url_raw( teinvit_pdf_public_base_url() . $data['pdf_url'] )
        );

        $order->update_meta_data( '_teinvit_pdf_status', 'generated' );
        $order->save();

        $order->add_order_note(
            $manual
                ? 'TeInvit PDF generated manually.'
                : 'TeInvit PDF generated automatically.'
        );

    } else {

        $order->update_meta_data( '_teinvit_pdf_status', 'error' );
        $order->add_order_note(
            '[TeInvit DEBUG] Node response invalid after JSON decode.'
        );
        $order->save();
    }
}

/* =====================================================
   TRIGGER CANONIC – DUPĂ TOKEN
===================================================== */
add_action(
    'teinvit_token_generated',
    function ( $order_id, $token = '', $context = [] ) {
        $token = sanitize_text_field( (string) $token );
        $source = is_array( $context ) ? sanitize_key( (string) ( $context['source'] ?? '' ) ) : '';
        $is_order_token = $source === 'order_tokens';
        if ( ! $is_order_token && $token !== '' && function_exists( 'teinvit_get_order_token_row' ) ) {
            $is_order_token = is_array( teinvit_get_order_token_row( $token ) );
        }

        if ( $token !== '' && $is_order_token ) {
            $result = teinvit_generate_pdf_for_token_version( $token, 0, false, [ 'context' => is_array( $context ) ? $context : [] ] );
            teinvit_phase4_record_token_generated_pdf_result( (int) $order_id, is_array( $context ) ? $context : [], $result );
            return;
        }

        teinvit_try_generate_pdf( $order_id, false );
    },
    10,
    3
);

/* =====================================================
   RETRY
===================================================== */
add_action( 'teinvit_retry_pdf_generation', function ( $order_id ) {
    teinvit_try_generate_pdf( $order_id, false );
});

function teinvit_pdf_cleanup_shared_secret() {
    $secret = defined( 'TEINVIT_NODE_SHARED_SECRET' ) ? (string) TEINVIT_NODE_SHARED_SECRET : '';
    if ( $secret === '' ) {
        $secret = (string) getenv( 'TEINVIT_NODE_SHARED_SECRET' );
    }
    return trim( $secret );
}

function teinvit_pdf_cleanup_parse_event_date( $raw_date ) {
    $raw_date = trim( (string) $raw_date );
    if ( $raw_date === '' ) {
        return 0;
    }

    $raw_date = preg_replace( '/\s+ora\s+.*/iu', '', $raw_date );
    $raw_date = trim( (string) $raw_date );
    if ( $raw_date === '' ) {
        return 0;
    }

    if ( preg_match( '/^(\d{4})[\/\.-](\d{1,2})[\/\.-](\d{1,2})$/', $raw_date, $m ) ) {
        $year  = (int) $m[1];
        $month = (int) $m[2];
        $day   = (int) $m[3];
        if ( checkdate( $month, $day, $year ) ) {
            return strtotime( sprintf( '%04d-%02d-%02d 00:00:00', $year, $month, $day ) );
        }
    }

    if ( preg_match( '/^(\d{1,2})[\/\.-](\d{1,2})[\/\.-](\d{4})$/', $raw_date, $m ) ) {
        $part_a = (int) $m[1];
        $part_b = (int) $m[2];
        $year   = (int) $m[3];

        $day = $part_a;
        $month = $part_b;
        if ( $part_a <= 12 && $part_b > 12 ) {
            $day = $part_b;
            $month = $part_a;
        }

        if ( checkdate( $month, $day, $year ) ) {
            return strtotime( sprintf( '%04d-%02d-%02d 00:00:00', $year, $month, $day ) );
        }
    }

    return 0;
}

function teinvit_pdf_cleanup_max_event_ts_from_snapshot( array $snapshot ) {
    $invitation = isset( $snapshot['invitation'] ) && is_array( $snapshot['invitation'] ) ? $snapshot['invitation'] : [];
    $events = isset( $invitation['events'] ) && is_array( $invitation['events'] ) ? $invitation['events'] : [];
    $max_ts = 0;
    foreach ( $events as $event ) {
        if ( ! is_array( $event ) ) {
            continue;
        }
        $event_ts = teinvit_pdf_cleanup_parse_event_date( (string) ( $event['date'] ?? '' ) );
        if ( $event_ts > $max_ts ) {
            $max_ts = $event_ts;
        }
    }
    return $max_ts;
}

function teinvit_pdf_cleanup_eligibility( array $invitation, array $versions ) {
    $order_id = (int) ( $invitation['order_id'] ?? 0 );
    $max_event_ts = 0;
    foreach ( $versions as $version ) {
        $snapshot = json_decode( (string) ( $version['snapshot'] ?? '' ), true );
        if ( ! is_array( $snapshot ) ) {
            continue;
        }
        $candidate = teinvit_pdf_cleanup_max_event_ts_from_snapshot( $snapshot );
        if ( $candidate > $max_event_ts ) {
            $max_event_ts = $candidate;
        }
    }

    if ( $max_event_ts > 0 ) {
        $delete_from_ts = strtotime( '+6 months +1 day', $max_event_ts );
        return [
            'mode' => 'event_plus_6m_1d',
            'max_event_ts' => $max_event_ts,
            'delete_from_ts' => $delete_from_ts ?: 0,
            'source' => 'versions_snapshot_events',
        ];
    }

    $order = $order_id > 0 && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
    if ( ! $order ) {
        return [
            'mode' => 'no_order',
            'max_event_ts' => 0,
            'delete_from_ts' => 0,
            'source' => 'fallback_order_missing',
        ];
    }

    $created = $order->get_date_created();
    if ( ! $created ) {
        return [
            'mode' => 'no_order_created_date',
            'max_event_ts' => 0,
            'delete_from_ts' => 0,
            'source' => 'fallback_order_created_missing',
        ];
    }

    $created_ts = (int) $created->getTimestamp();
    $delete_from_ts = strtotime( '+2 years', $created_ts );
    return [
        'mode' => 'fallback_order_plus_2y',
        'max_event_ts' => 0,
        'delete_from_ts' => $delete_from_ts ?: 0,
        'source' => 'fallback_order_created',
    ];
}

function teinvit_pdf_cleanup_table_exists( $table_name ) {
    global $wpdb;

    $table_name = (string) $table_name;
    if ( $table_name === '' ) {
        return false;
    }

    if ( function_exists( 'teinvit_database_table_exists' ) ) {
        return teinvit_database_table_exists( $table_name );
    }

    $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) );
    return (string) $found === $table_name;
}

function teinvit_pdf_cleanup_vertical_table_sets() {
    $sets = [];
    $verticals = function_exists( 'teinvit_vertical_keys' ) ? teinvit_vertical_keys() : [ 'wedding', 'baptism', 'birthday' ];

    foreach ( $verticals as $vertical ) {
        $vertical = function_exists( 'teinvit_normalize_vertical_key' )
            ? teinvit_normalize_vertical_key( $vertical )
            : sanitize_key( (string) $vertical );

        if ( function_exists( 'teinvit_storage_tables_for_vertical' ) ) {
            $tables = teinvit_storage_tables_for_vertical( $vertical );
        } elseif ( $vertical === 'wedding' && function_exists( 'teinvit_db_tables' ) ) {
            $tables = teinvit_db_tables();
        } else {
            $tables = [];
        }

        if ( ! is_array( $tables ) || empty( $tables['invitations'] ) || empty( $tables['versions'] ) ) {
            continue;
        }

        $key = $tables['invitations'] . '|' . $tables['versions'];
        if ( isset( $sets[ $key ] ) ) {
            continue;
        }

        $sets[ $key ] = [
            'vertical' => $vertical !== '' ? $vertical : 'wedding',
            'invitations' => (string) $tables['invitations'],
            'versions' => (string) $tables['versions'],
        ];
    }

    if ( empty( $sets ) && function_exists( 'teinvit_db_tables' ) ) {
        $tables = teinvit_db_tables();
        if ( ! empty( $tables['invitations'] ) && ! empty( $tables['versions'] ) ) {
            $sets['fallback'] = [
                'vertical' => 'wedding',
                'invitations' => (string) $tables['invitations'],
                'versions' => (string) $tables['versions'],
            ];
        }
    }

    return array_values( $sets );
}

function teinvit_pdf_cleanup_filename_from_version( array $version ) {
    $filename = sanitize_file_name( (string) ( $version['pdf_filename'] ?? '' ) );
    if ( $filename === '' && ! empty( $version['pdf_url'] ) ) {
        $path = wp_parse_url( (string) $version['pdf_url'], PHP_URL_PATH );
        if ( is_string( $path ) && $path !== '' ) {
            $filename = sanitize_file_name( rawurldecode( basename( str_replace( '\\', '/', $path ) ) ) );
        }
    }

    if ( $filename === '' || ! preg_match( '/\.pdf$/i', $filename ) ) {
        return '';
    }

    return $filename;
}

function teinvit_pdf_cleanup_summary_seed() {
    $vertical_seed = [
        'tokens' => 0,
        'candidate_files' => 0,
        'eligible_files' => 0,
        'deleted_files' => 0,
        'failed_files' => 0,
        'skipped_files' => 0,
    ];

    $summary = [
        'tokens' => 0,
        'candidate_files' => 0,
        'eligible_files' => 0,
        'deleted_files' => 0,
        'failed_files' => 0,
        'skipped_files' => 0,
        'by_vertical' => [],
    ];

    $verticals = function_exists( 'teinvit_vertical_keys' ) ? teinvit_vertical_keys() : [ 'wedding', 'baptism', 'birthday' ];
    foreach ( $verticals as $vertical ) {
        $summary['by_vertical'][ sanitize_key( (string) $vertical ) ] = $vertical_seed;
    }

    return $summary;
}

function teinvit_pdf_cleanup_ensure_vertical_summary( array &$summary, $vertical ) {
    $vertical = sanitize_key( (string) $vertical );
    if ( $vertical === '' ) {
        $vertical = 'wedding';
    }

    if ( empty( $summary['by_vertical'][ $vertical ] ) ) {
        $summary['by_vertical'][ $vertical ] = [
            'tokens' => 0,
            'candidate_files' => 0,
            'eligible_files' => 0,
            'deleted_files' => 0,
            'failed_files' => 0,
            'skipped_files' => 0,
        ];
    }
}

function teinvit_pdf_cleanup_collect_candidates( array $args = [] ) {
    global $wpdb;

    $now = isset( $args['now'] ) ? (int) $args['now'] : time();
    $limit = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 300;
    $filter_order_id = isset( $args['order_id'] ) ? max( 0, (int) $args['order_id'] ) : 0;
    $filter_token = isset( $args['token'] ) ? sanitize_text_field( (string) $args['token'] ) : '';
    $filter_vertical = isset( $args['vertical'] ) ? sanitize_key( (string) $args['vertical'] ) : '';

    $plan = [
        'dry_run' => true,
        'now' => $now,
        'generated_at' => current_time( 'mysql' ),
        'summary' => teinvit_pdf_cleanup_summary_seed(),
        'tokens' => [],
        'eligible_files' => [],
        'delete_groups' => [],
        'skipped' => [],
    ];

    $processed_tokens = 0;
    foreach ( teinvit_pdf_cleanup_vertical_table_sets() as $set ) {
        $vertical = sanitize_key( (string) ( $set['vertical'] ?? 'wedding' ) );
        if ( $filter_vertical !== '' && $filter_vertical !== $vertical ) {
            continue;
        }

        $invitations_table = (string) ( $set['invitations'] ?? '' );
        $versions_table = (string) ( $set['versions'] ?? '' );
        if ( ! teinvit_pdf_cleanup_table_exists( $invitations_table ) || ! teinvit_pdf_cleanup_table_exists( $versions_table ) ) {
            continue;
        }

        teinvit_pdf_cleanup_ensure_vertical_summary( $plan['summary'], $vertical );

        $where = [ "v.pdf_url IS NOT NULL AND v.pdf_url <> ''" ];
        $params = [];
        if ( $filter_token !== '' ) {
            $where[] = 'i.token = %s';
            $params[] = $filter_token;
        }
        if ( $filter_order_id > 0 ) {
            $where[] = 'i.order_id = %d';
            $params[] = $filter_order_id;
        }

        $remaining = max( 1, $limit - $processed_tokens );
        $params[] = $remaining;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT i.token, i.order_id
                 FROM {$invitations_table} i
                 INNER JOIN {$versions_table} v ON v.token = i.token
                 WHERE " . implode( ' AND ', $where ) . "
                 GROUP BY i.token, i.order_id
                 ORDER BY i.order_id ASC
                 LIMIT %d",
                $params
            ),
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            continue;
        }

        foreach ( $rows as $row ) {
            if ( $processed_tokens >= $limit ) {
                break 2;
            }

            $token = sanitize_text_field( (string) ( $row['token'] ?? '' ) );
            $order_id = (int) ( $row['order_id'] ?? 0 );
            $token_context = [];
            if ( $token !== '' && function_exists( 'teinvit_resolve_token_context' ) ) {
                $token_context = teinvit_resolve_token_context( $token );
                if ( $order_id <= 0 && is_array( $token_context ) ) {
                    $order_id = max( 0, (int) ( $token_context['order_id'] ?? 0 ) );
                }
            }

            if ( $token === '' || $order_id <= 0 ) {
                $plan['summary']['skipped_files']++;
                $plan['summary']['by_vertical'][ $vertical ]['skipped_files']++;
                $plan['skipped'][] = [
                    'vertical' => $vertical,
                    'token' => $token,
                    'order_id' => $order_id,
                    'reason' => 'missing_token_or_order',
                ];
                continue;
            }

            $versions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, snapshot, pdf_url, pdf_status, pdf_filename, pdf_generated_at FROM {$versions_table} WHERE token = %s ORDER BY id ASC",
                    $token
                ),
                ARRAY_A
            );
            if ( empty( $versions ) ) {
                continue;
            }

            $processed_tokens++;
            $plan['summary']['tokens']++;
            $plan['summary']['by_vertical'][ $vertical ]['tokens']++;

            $order_token_row = function_exists( 'teinvit_get_order_token_row' ) ? teinvit_get_order_token_row( $token ) : null;
            $order_token_status = is_array( $order_token_row ) ? sanitize_key( (string) ( $order_token_row['status'] ?? '' ) ) : '';
            $is_refunded_order_token = is_array( $order_token_row ) && $order_token_status === 'refunded';
            $token_key = $vertical . ':' . $token;
            $plan['tokens'][ $token_key ] = [
                'vertical' => $vertical,
                'token' => $token,
                'order_id' => $order_id,
                'versions_table' => $versions_table,
                'invitations_table' => $invitations_table,
                'has_order_token' => is_array( $order_token_row ),
                'legacy_order_token' => is_array( $order_token_row ) && ! empty( $order_token_row['legacy'] ),
                'order_token_status' => $order_token_status,
                'eligible' => false,
                'eligibility' => [],
                'versions' => [],
            ];

            $eligibility = $is_refunded_order_token
                ? [
                    'mode' => 'order_token_refunded',
                    'max_event_ts' => 0,
                    'delete_from_ts' => $now,
                    'source' => 'order_tokens_status_refunded',
                    'order_token_status' => $order_token_status,
                ]
                : teinvit_pdf_cleanup_eligibility( [ 'order_id' => $order_id ], $versions );
            $delete_from_ts = (int) ( $eligibility['delete_from_ts'] ?? 0 );
            $is_due = $delete_from_ts > 0 && $now >= $delete_from_ts;
            $plan['tokens'][ $token_key ]['eligible'] = $is_due;
            $plan['tokens'][ $token_key ]['eligibility'] = $eligibility;

            foreach ( $versions as $version ) {
                $pdf_url = trim( (string) ( $version['pdf_url'] ?? '' ) );
                if ( $pdf_url === '' ) {
                    continue;
                }

                $status = sanitize_key( (string) ( $version['pdf_status'] ?? '' ) );
                $filename = teinvit_pdf_cleanup_filename_from_version( $version );
                $candidate = [
                    'vertical' => $vertical,
                    'token' => $token,
                    'order_id' => $order_id,
                    'version_id' => (int) ( $version['id'] ?? 0 ),
                    'pdf_filename' => $filename,
                    'pdf_url' => esc_url_raw( $pdf_url ),
                    'pdf_status' => $status,
                    'pdf_generated_at' => (string) ( $version['pdf_generated_at'] ?? '' ),
                    'versions_table' => $versions_table,
                    'invitations_table' => $invitations_table,
                    'delete_from_ts' => $delete_from_ts,
                    'eligibility_mode' => (string) ( $eligibility['mode'] ?? 'unknown' ),
                    'max_event_ts' => (int) ( $eligibility['max_event_ts'] ?? 0 ),
                    'has_order_token' => is_array( $order_token_row ),
                    'legacy_order_token' => is_array( $order_token_row ) && ! empty( $order_token_row['legacy'] ),
                    'order_token_status' => $order_token_status,
                    'token_context_source' => is_array( $token_context ) ? (string) ( $token_context['source'] ?? '' ) : '',
                    'eligible' => false,
                    'skip_reason' => '',
                ];

                $plan['summary']['candidate_files']++;
                $plan['summary']['by_vertical'][ $vertical ]['candidate_files']++;

                if ( $status === 'delete_in_progress' ) {
                    $candidate['skip_reason'] = 'delete_in_progress';
                } elseif ( ! $is_due ) {
                    $candidate['skip_reason'] = 'retention_not_due';
                } elseif ( $filename === '' ) {
                    $candidate['skip_reason'] = 'missing_safe_filename';
                } else {
                    $candidate['eligible'] = true;
                }

                $plan['tokens'][ $token_key ]['versions'][] = $candidate;

                if ( ! $candidate['eligible'] ) {
                    $plan['summary']['skipped_files']++;
                    $plan['summary']['by_vertical'][ $vertical ]['skipped_files']++;
                    $plan['skipped'][] = $candidate;
                    continue;
                }

                $plan['summary']['eligible_files']++;
                $plan['summary']['by_vertical'][ $vertical ]['eligible_files']++;
                $plan['eligible_files'][] = $candidate;

                $group_key = (string) $order_id;
                if ( empty( $plan['delete_groups'][ $group_key ] ) ) {
                    $plan['delete_groups'][ $group_key ] = [
                        'order_id' => $order_id,
                        'filenames' => [],
                        'candidates' => [],
                    ];
                }
                $plan['delete_groups'][ $group_key ]['filenames'][] = $filename;
                $plan['delete_groups'][ $group_key ]['filenames'] = array_values( array_unique( $plan['delete_groups'][ $group_key ]['filenames'] ) );
                $plan['delete_groups'][ $group_key ]['candidates'][] = $candidate;
            }
        }
    }

    return $plan;
}

function teinvit_pdf_cleanup_update_version_row( array $candidate, array $data ) {
    global $wpdb;

    $versions_table = (string) ( $candidate['versions_table'] ?? '' );
    $version_id = (int) ( $candidate['version_id'] ?? 0 );
    $token = sanitize_text_field( (string) ( $candidate['token'] ?? '' ) );
    if ( $versions_table === '' || $version_id <= 0 || $token === '' ) {
        return false;
    }

    return $wpdb->update(
        $versions_table,
        $data,
        [ 'id' => $version_id, 'token' => $token ]
    );
}

function teinvit_pdf_cleanup_refresh_order_token_pdf_status( $token, $versions_table ) {
    global $wpdb;

    $token = sanitize_text_field( (string) $token );
    $versions_table = (string) $versions_table;
    if ( $token === '' || $versions_table === '' || ! function_exists( 'teinvit_get_order_token_row' ) || ! function_exists( 'teinvit_update_order_token_row' ) ) {
        return false;
    }

    $order_token_row = teinvit_get_order_token_row( $token );
    if ( ! is_array( $order_token_row ) || ! empty( $order_token_row['legacy'] ) ) {
        return false;
    }

    $remaining = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$versions_table} WHERE token = %s AND pdf_url IS NOT NULL AND pdf_url <> '' AND pdf_status <> 'deleted_on_server'",
            $token
        )
    );
    $next_status = $remaining > 0 ? 'generated' : 'deleted_on_server';

    return teinvit_update_order_token_row( (int) $order_token_row['id'], [
        'pdf_status' => $next_status,
        'last_error' => '',
        'debug_context' => [
            'phase' => 'pdf_cleanup',
            'pdf_status' => $next_status,
            'remaining_pdf_versions' => $remaining,
        ],
    ] );
}

function teinvit_pdf_cleanup_maybe_delete_order_pdf_meta( $order, array $filenames ) {
    if ( ! $order || ! method_exists( $order, 'get_meta' ) || ! method_exists( $order, 'delete_meta_data' ) ) {
        return false;
    }

    $pdf_url = (string) $order->get_meta( '_teinvit_pdf_url' );
    if ( $pdf_url === '' ) {
        return false;
    }

    $path = wp_parse_url( $pdf_url, PHP_URL_PATH );
    $meta_filename = is_string( $path ) && $path !== ''
        ? sanitize_file_name( rawurldecode( basename( str_replace( '\\', '/', $path ) ) ) )
        : '';

    if ( $meta_filename === '' || ! in_array( $meta_filename, $filenames, true ) ) {
        return false;
    }

    $order->delete_meta_data( '_teinvit_pdf_url' );
    return true;
}

function teinvit_pdf_cleanup_order_note_lines( array $candidates, $status, $reason ) {
    $lines = [];
    foreach ( $candidates as $candidate ) {
        $lines[] = sprintf(
            'file order=%d token=%s vertical=%s version_id=%d filename=%s status=%s reason=%s',
            (int) ( $candidate['order_id'] ?? 0 ),
            sanitize_text_field( (string) ( $candidate['token'] ?? '' ) ),
            sanitize_key( (string) ( $candidate['vertical'] ?? '' ) ),
            (int) ( $candidate['version_id'] ?? 0 ),
            sanitize_file_name( (string) ( $candidate['pdf_filename'] ?? '' ) ),
            sanitize_key( (string) $status ),
            sanitize_text_field( (string) $reason )
        );
    }

    return $lines;
}

function teinvit_pdf_cleanup_log_summary( array $summary, $dry_run = false ) {
    $vertical_bits = [];
    foreach ( (array) ( $summary['by_vertical'] ?? [] ) as $vertical => $data ) {
        $vertical_bits[] = sprintf(
            '%s:candidates=%d eligible=%d deleted=%d failed=%d skipped=%d',
            sanitize_key( (string) $vertical ),
            (int) ( $data['candidate_files'] ?? 0 ),
            (int) ( $data['eligible_files'] ?? 0 ),
            (int) ( $data['deleted_files'] ?? 0 ),
            (int) ( $data['failed_files'] ?? 0 ),
            (int) ( $data['skipped_files'] ?? 0 )
        );
    }

    error_log( sprintf(
        '[TeInvit PDF Cleanup] summary dry_run=%s tokens=%d candidates=%d eligible=%d deleted=%d failed=%d skipped=%d verticals=%s',
        $dry_run ? 'yes' : 'no',
        (int) ( $summary['tokens'] ?? 0 ),
        (int) ( $summary['candidate_files'] ?? 0 ),
        (int) ( $summary['eligible_files'] ?? 0 ),
        (int) ( $summary['deleted_files'] ?? 0 ),
        (int) ( $summary['failed_files'] ?? 0 ),
        (int) ( $summary['skipped_files'] ?? 0 ),
        implode( '; ', $vertical_bits )
    ) );
}

function teinvit_pdf_cleanup_node_delete( $order_id, array $filenames = [], $require_filenames = false ) {
    $payload = [
        'order_id'  => (int) $order_id,
    ];
    $clean_filenames = array_values( array_unique( array_filter( array_map( static function( $f ) {
        return sanitize_file_name( (string) $f );
    }, $filenames ) ) ) );
    if ( ! empty( $clean_filenames ) ) {
        $payload['filenames'] = $clean_filenames;
    }
    if ( $require_filenames && empty( $clean_filenames ) ) {
        return new WP_Error( 'cleanup_missing_filenames', 'Cleanup requires explicit PDF filenames.' );
    }

    $headers = [ 'Content-Type' => 'application/json' ];
    $secret = teinvit_pdf_cleanup_shared_secret();
    if ( $secret !== '' ) {
        $headers['X-TeInvit-Secret'] = $secret;
    }

    $response = wp_remote_post(
        TEINVIT_NODE_DELETE_ENDPOINT,
        [
            'timeout' => 120,
            'headers' => $headers,
            'body'    => wp_json_encode( $payload ),
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    if ( $code < 200 || $code >= 300 || ! is_array( $data ) || ( $data['status'] ?? '' ) !== 'ok' ) {
        return new WP_Error( 'node_delete_failed', 'Node delete failed', [ 'http_code' => $code, 'body' => $data ] );
    }
    $deleted_files = array_values( array_filter( array_map( 'sanitize_file_name', (array) ( $data['deleted_files'] ?? [] ) ) ) );
    $missing_files = array_values( array_filter( array_map( 'sanitize_file_name', (array) ( $data['missing_files'] ?? [] ) ) ) );
    $folder_deleted = ! empty( $data['folder_deleted'] );
    $folder_missing = ! empty( $data['folder_missing'] );
    if ( ! $folder_missing && empty( $deleted_files ) && empty( $missing_files ) && ! $folder_deleted ) {
        return new WP_Error(
            'node_delete_no_effect',
            'Node delete finished without deleting any files',
            [ 'http_code' => $code, 'body' => $data ]
        );
    }

    return $data;
}

function teinvit_pdf_cleanup_run_nightly( $dry_run = false, array $args = [] ) {
    $dry_run = (bool) $dry_run;
    $plan = teinvit_pdf_cleanup_collect_candidates( $args );
    $plan['dry_run'] = $dry_run;

    if ( $dry_run || empty( $plan['delete_groups'] ) ) {
        teinvit_pdf_cleanup_log_summary( $plan['summary'], $dry_run );
        return $plan;
    }

    foreach ( $plan['delete_groups'] as $group ) {
        $order_id = (int) ( $group['order_id'] ?? 0 );
        $filenames = array_values( array_unique( array_filter( array_map( 'sanitize_file_name', (array) ( $group['filenames'] ?? [] ) ) ) ) );
        $candidates = (array) ( $group['candidates'] ?? [] );
        if ( $order_id <= 0 || empty( $filenames ) || empty( $candidates ) ) {
            continue;
        }

        foreach ( $candidates as $candidate ) {
            teinvit_pdf_cleanup_update_version_row( $candidate, [ 'pdf_status' => 'delete_in_progress' ] );
        }

        $result = teinvit_pdf_cleanup_node_delete( $order_id, $filenames, true );
        $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;

        if ( is_wp_error( $result ) ) {
            foreach ( $candidates as $candidate ) {
                teinvit_pdf_cleanup_update_version_row( $candidate, [ 'pdf_status' => 'delete_error' ] );
                $vertical = sanitize_key( (string) ( $candidate['vertical'] ?? 'wedding' ) );
                teinvit_pdf_cleanup_ensure_vertical_summary( $plan['summary'], $vertical );
                $plan['summary']['failed_files']++;
                $plan['summary']['by_vertical'][ $vertical ]['failed_files']++;
            }

            if ( $order ) {
                $lines = teinvit_pdf_cleanup_order_note_lines( $candidates, 'failed', $result->get_error_message() );
                $order->add_order_note(
                    "[TeInvit PDF Cleanup] order={$order_id} status=failed ERROR=" . $result->get_error_message() . "\n" . implode( "\n", $lines )
                );
            }
            continue;
        }

        $affected_tokens = [];
        foreach ( $candidates as $candidate ) {
            teinvit_pdf_cleanup_update_version_row(
                $candidate,
                [
                    'pdf_status' => 'deleted_on_server',
                    'pdf_url' => '',
                    'pdf_generated_at' => current_time( 'mysql' ),
                ]
            );

            $token = sanitize_text_field( (string) ( $candidate['token'] ?? '' ) );
            $versions_table = (string) ( $candidate['versions_table'] ?? '' );
            if ( $token !== '' && $versions_table !== '' ) {
                $affected_tokens[ $token . '|' . $versions_table ] = [ $token, $versions_table ];
            }

            $vertical = sanitize_key( (string) ( $candidate['vertical'] ?? 'wedding' ) );
            teinvit_pdf_cleanup_ensure_vertical_summary( $plan['summary'], $vertical );
            $plan['summary']['deleted_files']++;
            $plan['summary']['by_vertical'][ $vertical ]['deleted_files']++;
        }

        foreach ( $affected_tokens as $token_table ) {
            teinvit_pdf_cleanup_refresh_order_token_pdf_status( $token_table[0], $token_table[1] );
        }

        if ( $order ) {
            $meta_deleted = teinvit_pdf_cleanup_maybe_delete_order_pdf_meta( $order, $filenames );
            $order->save();
            $lines = teinvit_pdf_cleanup_order_note_lines( $candidates, 'success', 'deleted_on_server' );
            $order->add_order_note(
                sprintf(
                    "[TeInvit PDF Cleanup] order=%d status=success requested_files=%s deleted_files=%s missing_files=%s folder_deleted=%s folder_missing=%s legacy_meta_deleted=%s\n%s",
                    $order_id,
                    implode( ',', array_map( 'sanitize_text_field', $filenames ) ),
                    implode( ',', array_map( 'sanitize_text_field', (array) ( $result['deleted_files'] ?? [] ) ) ),
                    implode( ',', array_map( 'sanitize_text_field', (array) ( $result['missing_files'] ?? [] ) ) ),
                    ! empty( $result['folder_deleted'] ) ? 'yes' : 'no',
                    ! empty( $result['folder_missing'] ) ? 'yes' : 'no',
                    $meta_deleted ? 'yes' : 'no',
                    implode( "\n", $lines )
                )
            );
        }
    }

    teinvit_pdf_cleanup_log_summary( $plan['summary'], false );
    return $plan;
}

function teinvit_pdf_cleanup_dry_run( array $args = [] ) {
    return teinvit_pdf_cleanup_run_nightly( true, $args );
}

function teinvit_pdf_cleanup_unschedule_internal_once() {
    $flag_option = 'teinvit_pdf_cleanup_internal_schedule_removed_v1';
    if ( get_option( $flag_option, '0' ) === '1' ) {
        return;
    }

    if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
        wp_clear_scheduled_hook( TEINVIT_PDF_CLEANUP_HOOK );
    }

    update_option( $flag_option, '1', false );
}
add_action( 'plugins_loaded', 'teinvit_pdf_cleanup_unschedule_internal_once', 40 );

add_action( TEINVIT_PDF_CLEANUP_HOOK, 'teinvit_pdf_cleanup_run_nightly' );
