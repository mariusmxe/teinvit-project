<?php
/**
 * TeInvit – PDF Generator
 * CANONIC + DEBUG (Order Notes)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
function teinvit_try_generate_pdf( $order_id, $manual = false ) {

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
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
            esc_url_raw( 'https://pdf.teinvit.com' . $data['pdf_url'] )
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
    function ( $order_id ) {
        teinvit_try_generate_pdf( $order_id, false );
    },
    10,
    1
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

function teinvit_pdf_cleanup_node_delete( $order_id, array $filenames ) {
    $payload = [
        'order_id'  => (int) $order_id,
        'filenames' => array_values( array_unique( array_filter( array_map( static function( $f ) {
            return sanitize_file_name( (string) $f );
        }, $filenames ) ) ) ),
    ];

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

    return $data;
}

function teinvit_pdf_cleanup_run_nightly() {
    global $wpdb;

    if ( ! function_exists( 'teinvit_db_tables' ) ) {
        return;
    }
    $t = teinvit_db_tables();
    $invitations_table = $t['invitations'] ?? '';
    $versions_table = $t['versions'] ?? '';
    if ( $invitations_table === '' || $versions_table === '' ) {
        return;
    }

    $rows = $wpdb->get_results(
        "SELECT i.token, i.order_id
         FROM {$invitations_table} i
         INNER JOIN {$versions_table} v ON v.token = i.token
         WHERE v.pdf_url IS NOT NULL AND v.pdf_url <> ''
         GROUP BY i.token, i.order_id
         ORDER BY i.order_id ASC
         LIMIT 300",
        ARRAY_A
    );
    if ( empty( $rows ) ) {
        return;
    }

    $now = time();
    foreach ( $rows as $row ) {
        $token = sanitize_text_field( (string) ( $row['token'] ?? '' ) );
        $order_id = (int) ( $row['order_id'] ?? 0 );
        if ( $token === '' || $order_id <= 0 ) {
            continue;
        }

        $versions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, snapshot, pdf_url, pdf_status, pdf_filename FROM {$versions_table} WHERE token = %s ORDER BY id ASC",
                $token
            ),
            ARRAY_A
        );
        if ( empty( $versions ) ) {
            continue;
        }

        $invitation = function_exists( 'teinvit_get_invitation' ) ? teinvit_get_invitation( $token ) : [ 'order_id' => $order_id ];
        if ( ! is_array( $invitation ) ) {
            $invitation = [ 'order_id' => $order_id ];
        }

        $eligibility = teinvit_pdf_cleanup_eligibility( $invitation, $versions );
        $delete_from_ts = (int) ( $eligibility['delete_from_ts'] ?? 0 );
        $mode = (string) ( $eligibility['mode'] ?? 'unknown' );
        $max_event_ts = (int) ( $eligibility['max_event_ts'] ?? 0 );

        if ( $delete_from_ts <= 0 || $now < $delete_from_ts ) {
            continue;
        }

        $pdf_version_ids = [];
        $pdf_filenames = [];
        foreach ( $versions as $version ) {
            $pdf_url = trim( (string) ( $version['pdf_url'] ?? '' ) );
            $status = trim( (string) ( $version['pdf_status'] ?? '' ) );
            if ( $pdf_url === '' && $status === 'deleted_on_server' ) {
                continue;
            }
            if ( $status === 'delete_in_progress' ) {
                continue;
            }
            $pdf_version_ids[] = (int) ( $version['id'] ?? 0 );
            $filename = sanitize_file_name( (string) ( $version['pdf_filename'] ?? '' ) );
            if ( $filename !== '' ) {
                $pdf_filenames[] = $filename;
            }
        }
        $pdf_version_ids = array_values( array_filter( array_unique( $pdf_version_ids ) ) );
        if ( empty( $pdf_version_ids ) ) {
            continue;
        }

        foreach ( $pdf_version_ids as $version_id ) {
            $wpdb->update(
                $versions_table,
                [ 'pdf_status' => 'delete_in_progress' ],
                [ 'id' => (int) $version_id ]
            );
        }

        $result = teinvit_pdf_cleanup_node_delete( $order_id, $pdf_filenames );
        $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;

        if ( is_wp_error( $result ) ) {
            foreach ( $pdf_version_ids as $version_id ) {
                $wpdb->update(
                    $versions_table,
                    [ 'pdf_status' => 'delete_error' ],
                    [ 'id' => (int) $version_id ]
                );
            }
            if ( $order ) {
                $order->add_order_note(
                    sprintf(
                        '[TeInvit PDF Cleanup] order=%d token=%s mode=%s delete_from=%s ERROR=%s',
                        $order_id,
                        $token,
                        $mode,
                        gmdate( 'Y-m-d', $delete_from_ts ),
                        $result->get_error_message()
                    )
                );
            }
            continue;
        }

        foreach ( $pdf_version_ids as $version_id ) {
            $wpdb->update(
                $versions_table,
                [
                    'pdf_status' => 'deleted_on_server',
                    'pdf_url' => '',
                    'pdf_generated_at' => current_time( 'mysql' ),
                ],
                [ 'id' => (int) $version_id ]
            );
        }

        if ( $order ) {
            $order->delete_meta_data( '_teinvit_pdf_url' );
            $order->save();
            $order->add_order_note(
                sprintf(
                    '[TeInvit PDF Cleanup] order=%d token=%s max_event=%s mode=%s fallback_2y=%s delete_from=%s deleted_files=%s folder_deleted=%s folder_missing=%s',
                    $order_id,
                    $token,
                    $max_event_ts > 0 ? gmdate( 'Y-m-d', $max_event_ts ) : 'n/a',
                    $mode,
                    $mode === 'fallback_order_plus_2y' ? 'yes' : 'no',
                    gmdate( 'Y-m-d', $delete_from_ts ),
                    implode( ',', array_map( 'sanitize_text_field', (array) ( $result['deleted_files'] ?? [] ) ) ),
                    ! empty( $result['folder_deleted'] ) ? 'yes' : 'no',
                    ! empty( $result['folder_missing'] ) ? 'yes' : 'no'
                )
            );
        }
    }
}

add_action( 'init', function() {
    $next = wp_next_scheduled( TEINVIT_PDF_CLEANUP_HOOK );
    if ( ! $next ) {
        $now = current_time( 'timestamp' );
        $run_at = strtotime( 'tomorrow 02:15:00', $now );
        if ( ! $run_at ) {
            $run_at = time() + HOUR_IN_SECONDS;
        }
        wp_schedule_event( $run_at, 'daily', TEINVIT_PDF_CLEANUP_HOOK );
    }
}, 20 );

add_action( TEINVIT_PDF_CLEANUP_HOOK, 'teinvit_pdf_cleanup_run_nightly' );
