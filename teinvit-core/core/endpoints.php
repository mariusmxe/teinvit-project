<?php

/**
 * Te Invit – Public Invitation Endpoints
 *
 * - /i/{token}        → preview public (EXISTENT)
 * - /pdf/{token}     → HTML PRINTABIL (PDF)
 *
 * 🔑 REGULĂ DE AUR:
 * - endpoint-ul NU conține layout
 * - CSS decide TOT (preview.css / pdf.css)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =====================================================
   1. REWRITE RULES
===================================================== */
add_action( 'init', function () {

    add_rewrite_rule(
        '^i/([^/]+)/?$',
        'index.php?teinvit_token=$matches[1]',
        'top'
    );

    add_rewrite_rule(
        '^pdf/([^/]+)/?$',
        'index.php?teinvit_pdf_token=$matches[1]',
        'top'
    );
});

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'teinvit_token';
    $vars[] = 'teinvit_pdf_token';
    return $vars;
});

/* =====================================================
   2. PREVIEW PUBLIC – /i/{token}
   ⚠️ NU ATINGEM
===================================================== */
add_action( 'template_redirect', function () {

    $token = get_query_var( 'teinvit_token' );
    if ( ! $token ) {
        return;
    }

    global $wpdb;

    $order_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_teinvit_token'
             AND meta_value = %s
             LIMIT 1",
            $token
        )
    );

    if ( ! $order_id ) {
        status_header( 404 );
        echo 'Invitația nu a fost găsită.';
        exit;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        status_header( 404 );
        echo 'Comandă invalidă.';
        exit;
    }

    status_header( 200 );
    nocache_headers();

    echo '<!DOCTYPE html>';
    echo '<html lang="ro">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Invitație</title>';
    echo '</head>';
    echo '<body>';

    echo '<div style="display:flex;justify-content:center;">';
do_action( 'teinvit_guest_page_preview', $order );
echo '</div>';



    echo '</body>';
    echo '</html>';
    exit;
});

/* =====================================================
   3. PDF – /pdf/{token}
   🔑 HTML PRINTABIL PUR
===================================================== */
add_action( 'template_redirect', function () {

    $token = get_query_var( 'teinvit_pdf_token' );
    if ( ! $token ) {
        return;
    }

    global $wpdb;

    $order_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_teinvit_token'
             AND meta_value = %s
             LIMIT 1",
            $token
        )
    );

    if ( ! $order_id ) {
        status_header( 404 );
        echo 'PDF invalid.';
        exit;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        status_header( 404 );
        echo 'Comandă invalidă.';
        exit;
    }

    status_header( 200 );
    nocache_headers();

    // 🔑 CONTEXT UNIC PDF
    $GLOBALS['TEINVIT_RENDER_CONTEXT'] = 'pdf';

    echo '<!DOCTYPE html>';
    echo '<html lang="ro" class="teinvit-pdf">';
    echo '<head>';
    echo '<meta charset="utf-8">';

    // 🔒 FIX CRITICAL pentru Chromium PDF
    echo '<meta name="viewport" content="width=148mm, height=210mm, initial-scale=1">';

    echo '<title>Invitație PDF</title>';
    echo '</head>';
    echo '<body style="display:flex;justify-content:center;">';

    echo TeInvit_Wedding_Preview_Renderer::render_from_order( $order );

    echo '</body>';
    echo '</html>';
    exit;
});
