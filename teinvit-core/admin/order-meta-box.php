<?php
/**
 * TeInvit – Admin Order Meta Box (HPOS SAFE + DEBUG)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register meta box in WooCommerce order admin
 */
add_action( 'add_meta_boxes', function () {

    // WooCommerce Legacy Orders
    add_meta_box(
        'teinvit_order_box',
        'TeInvit – Date invitație',
        'teinvit_render_order_meta_box',
        'shop_order',
        'side',
        'high'
    );

} );

add_action( 'add_meta_boxes_woocommerce_page_wc-orders', function () {

    // WooCommerce HPOS Orders
    add_meta_box(
        'teinvit_order_box',
        'TeInvit – Date invitație',
        'teinvit_render_order_meta_box',
        null,
        'side',
        'high'
    );

} );

/**
 * Render meta box content
 */
function teinvit_render_order_meta_box( $context ) {

    /* =========================
       HPOS / LEGACY SAFE ORDER
    ========================= */
    if ( $context instanceof WC_Order ) {
        $order    = $context;
        $order_id = $order->get_id();

    } elseif ( $context instanceof WP_Post ) {
        $order_id = $context->ID;
        $order    = wc_get_order( $order_id );

    } elseif ( is_numeric( $context ) ) {
        $order_id = (int) $context;
        $order    = wc_get_order( $order_id );

    } else {
        echo '<em>Order context invalid</em>';
        return;
    }

    if ( ! $order ) {
        echo '<em>Order not found</em>';
        return;
    }

    /* =========================
       META (CANONIC)
    ========================= */

    // 🔑 TOKEN – EXACT CA ÎNAINTE
    $token = get_post_meta( $order_id, '_teinvit_token', true );

    // PDF meta – HPOS safe
    $pdf_url  = $order->get_meta( '_teinvit_pdf_url' );
    $pdf_stat = $order->get_meta( '_teinvit_pdf_status' );

    if ( empty( $pdf_stat ) ) {
        $pdf_stat = 'pending';
    }
    ?>

    <div style="line-height:1.6">

        <strong>Token invitație:</strong><br>
        <?php echo $token ? esc_html( $token ) : '<em>— nealocat —</em>'; ?>

        <hr>

        <strong>Status PDF:</strong><br>
        <?php
        switch ( $pdf_stat ) {
            case 'generated':
                echo '<span style="color:green;font-weight:bold">Generat</span>';
                break;

            case 'error':
                echo '<span style="color:red;font-weight:bold">Eroare</span>';
                break;

            default:
                echo '<span style="color:#999">În așteptare</span>';
        }
        ?>

        <hr>

        <strong>PDF:</strong><br>
        <?php if ( $pdf_url ) : ?>
            <a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank">
                Deschide PDF
            </a>
        <?php else : ?>
            <em>Nu este disponibil</em>
        <?php endif; ?>

        <hr>

        <!-- DEBUG / FALLBACK – MEREU DISPONIBIL -->
        <a
            href="<?php echo esc_url(
                admin_url(
                    'admin-post.php?action=teinvit_generate_pdf&order_id=' . $order_id
                )
            ); ?>"
            class="button button-secondary"
        >
            Generează PDF acum (debug)
        </a>

    </div>
    <?php
}

/**
 * Buton manual „Generează PDF”
 */
add_action( 'admin_post_teinvit_generate_pdf', function () {

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Unauthorized' );
    }

    if ( empty( $_GET['order_id'] ) ) {
        wp_die( 'Missing order ID' );
    }

    $order_id = (int) $_GET['order_id'];

    teinvit_try_generate_pdf( $order_id, true );

    wp_redirect( wp_get_referer() );
    exit;
} );
