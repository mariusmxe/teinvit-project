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
