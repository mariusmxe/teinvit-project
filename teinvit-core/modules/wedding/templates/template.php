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
$model_key = isset( $invitation['model_key'] ) ? (string) $invitation['model_key'] : 'invn01';
$product_id_for_background = 0;
if ( isset( $product ) && $product instanceof WC_Product ) {
    $product_id_for_background = (int) $product->get_id();
} elseif ( isset( $order ) && $order instanceof WC_Order ) {
    $items = $order->get_items();
    if ( ! empty( $items ) ) {
        $first_item = reset( $items );
        $product_id_for_background = $first_item ? (int) $first_item->get_product_id() : 0;
    }
}
$background_url = function_exists( 'teinvit_get_product_background_url' )
    ? teinvit_get_product_background_url( $product_id_for_background, $model_key )
    : ( function_exists( 'teinvit_model_background_url' ) ? teinvit_model_background_url( $model_key ) : ( TEINVIT_WEDDING_MODULE_URL . 'assets/backgrounds/invn01.png' ) );
?>

<div class="teinvit-wedding">

<?php if ( ! $is_pdf ) : ?>

<!-- =================================================
     PREVIEW ORIGINAL (produs + /i/{token})
     ⚠️ preview.js depinde de această structură
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
