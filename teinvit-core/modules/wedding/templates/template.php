<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * $product / $order / $invitation sunt injectate de renderer
 */

$is_pdf = (
    isset( $GLOBALS['TEINVIT_RENDER_CONTEXT'] ) &&
    $GLOBALS['TEINVIT_RENDER_CONTEXT'] === 'pdf'
);

/* =========================
   BACKGROUND IMAGE
========================= */
$product_id_for_background = 0;
$background_url = '';
$token_background_context = isset( $GLOBALS['TEINVIT_RENDER_TOKEN_CONTEXT'] ) && is_array( $GLOBALS['TEINVIT_RENDER_TOKEN_CONTEXT'] )
    ? $GLOBALS['TEINVIT_RENDER_TOKEN_CONTEXT']
    : [];
$token_background_product_id = isset( $GLOBALS['TEINVIT_RENDER_PRODUCT_ID'] ) ? max( 0, (int) $GLOBALS['TEINVIT_RENDER_PRODUCT_ID'] ) : 0;
if ( isset( $product ) && $product instanceof WC_Product ) {
    $product_id_for_background = (int) $product->get_id();
    if ( $token_background_product_id <= 0 ) {
        $token_background_product_id = $product_id_for_background;
    }
} elseif ( isset( $order ) && $order instanceof WC_Order ) {
    if ( function_exists( 'teinvit_get_token_background_url' ) && ( ! empty( $token_background_context ) || $token_background_product_id > 0 ) ) {
        $background_url = teinvit_get_token_background_url( $token_background_context, $order, $token_background_product_id );
    }
    if ( $background_url === '' ) {
        $items = $order->get_items();
        if ( ! empty( $items ) ) {
            $first_item = reset( $items );
            $product_id_for_background = $first_item ? (int) $first_item->get_product_id() : 0;
        }
    }
}
if ( $background_url === '' && function_exists( 'teinvit_get_token_background_url' ) && ( ! empty( $token_background_context ) || $token_background_product_id > 0 ) ) {
    $background_url = teinvit_get_token_background_url( $token_background_context, isset( $order ) && $order instanceof WC_Order ? $order : null, $token_background_product_id );
}
if ( $background_url === '' && $product_id_for_background <= 0 ) {
    $product_id_for_background = $token_background_product_id;
}
if ( $background_url === '' ) {
    $background_url = function_exists( 'teinvit_get_product_background_url' )
    ? teinvit_get_product_background_url( $product_id_for_background )
    : '';
}
?>

<div class="teinvit-wedding">

<?php if ( ! $is_pdf ) : ?>

<!-- =================================================
     PREVIEW ORIGINAL (produs + /i/{token})
     ⚠️ preview.js depinde de această structură
================================================== -->

<div class="teinvit-page">
  <div class="teinvit-container">

    <div class="teinvit-preview">

        <?php if ( $background_url ) : ?>
            <img
                src="<?php echo esc_url( $background_url ); ?>"
                alt=""
                class="teinvit-bg"
                draggable="false"
            >
        <?php endif; ?>

        <div class="teinvit-canvas canvas--spread">

            <div class="inv-names"></div>
            <div class="inv-divider" aria-hidden="true"></div>

            <div class="inv-parents-wrapper" style="display:none;">
                <div class="section-title">ÎMPREUNĂ CU PĂRINȚII</div>
                <div class="inv-parents inv-parents-grid">
                    <div class="inv-parent-col inv-parent-mireasa"></div>
                    <div class="inv-parent-col inv-parent-mire"></div>
                </div>
            </div>

            <div class="inv-nasi" style="display:none;">
                <div class="section-title">ȘI CU NAȘII</div>
                <div class="nasi-row"></div>
            </div>

            <div class="inv-message"></div>

            <div class="inv-events" style="display:none;">
                <div class="events-row top"></div>
                <div class="events-row bottom"></div>
            </div>

        </div>
    </div>

  </div>
</div>


<?php else : ?>

    <!-- =================================================
     PDF – STRUCTURĂ STATICĂ
     🔒 FĂRĂ preview.js logic
     🔒 FĂRĂ auto-fit
     🔒 CSS = pdf.css
================================================== -->

    <?php if ( ! is_product() ) : ?>
<div class="teinvit-page">
  <div class="teinvit-container">
<?php endif; ?>

    <div class="teinvit-preview">

        <?php if ( $background_url ) : ?>
            <img
                src="<?php echo esc_url( $background_url ); ?>"
                alt=""
                class="teinvit-bg"
                draggable="false"
            >
        <?php endif; ?>

        <div class="teinvit-canvas canvas--spread">

            <div class="inv-names"></div>
            <div class="inv-divider" aria-hidden="true"></div>

<div class="inv-parents-wrapper" style="display:none;">
                <div class="section-title">ÎMPREUNĂ CU PĂRINȚII</div>
                <div class="inv-parents inv-parents-grid">
                    <div class="inv-parent-col inv-parent-mireasa"></div>
                    <div class="inv-parent-col inv-parent-mire"></div>           
                </div>
            </div>

           <div class="inv-nasi" style="display:none;">
                <div class="section-title">ȘI CU NAȘII</div>
                <div class="nasi-row"></div>
            </div>

            <div class="inv-message"></div>

            <div class="inv-events" style="display:none;">
                <div class="events-row top"></div>
                <div class="events-row bottom"></div>
            </div>

        </div>
    </div>

      <?php if ( ! is_product() ) : ?>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

</div>
