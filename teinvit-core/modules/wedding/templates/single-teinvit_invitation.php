<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$mode = isset( $GLOBALS['teinvit_tokenized_mode'] ) ? (string) $GLOBALS['teinvit_tokenized_mode'] : '';
$token = isset( $GLOBALS['teinvit_tokenized_token'] ) ? (string) $GLOBALS['teinvit_tokenized_token'] : '';
$post_id = isset( $GLOBALS['teinvit_tokenized_post_id'] ) ? (int) $GLOBALS['teinvit_tokenized_post_id'] : 0;

$post = $post_id ? get_post( $post_id ) : null;
if ( ! $post || $post->post_type !== 'teinvit_invitation' ) {
    status_header( 404 );
    get_header();
    echo '<p>Invitația nu a fost găsită.</p>';
    get_footer();
    return;
}

$GLOBALS['post'] = $post;
setup_postdata( $post );
$GLOBALS['TEINVIT_IN_CPT_TEMPLATE'] = true;

$preview_html = '';
if ( $token !== '' && function_exists( 'teinvit_get_order_id_by_token' ) ) {
    $order_id = (int) teinvit_get_order_id_by_token( $token );
    $order = $order_id ? wc_get_order( $order_id ) : null;
    if ( $order ) {
        $active = function_exists( 'teinvit_get_active_version_data' ) ? teinvit_get_active_version_data( $token ) : null;
        $payload = $active ? json_decode( (string) $active['data_json'], true ) : [];
        if ( empty( $payload ) && function_exists( 'teinvit_get_active_snapshot' ) ) {
            $active = teinvit_get_active_snapshot( $token );
            $payload = $active ? json_decode( (string) $active['snapshot'], true ) : [];
        }

        if ( ! empty( $payload['invitation'] ) && is_array( $payload['invitation'] ) ) {
            $preview_html = TeInvit_Wedding_Preview_Renderer::render_from_invitation_data( $payload['invitation'], $order );
        } else {
            $preview_html = TeInvit_Wedding_Preview_Renderer::render_from_order( $order );
        }
    }
}

get_header();
?>
<div class="teinvit-invitation-layout teinvit-mode-<?php echo esc_attr( $mode ); ?>">
  <div class="teinvit-invitation-content" style="max-width:1200px;margin:0 auto;padding:12px;">
    <?php the_content(); ?>
  </div>

  <div class="teinvit-slot teinvit-slot-preview" data-teinvit-slot="preview" style="max-width:1200px;margin:0 auto;padding:12px;">
    <?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
  </div>

  <?php if ( $mode === 'admin-client' ) : ?>
    <div class="teinvit-slot teinvit-slot-admin" data-teinvit-slot="admin" style="max-width:1200px;margin:0 auto;padding:12px;">
      <?php include TEINVIT_WEDDING_MODULE_PATH . 'templates/page-admin-client.php'; ?>
    </div>
  <?php elseif ( $mode === 'invitati' ) : ?>
    <div class="teinvit-slot teinvit-slot-rsvp" data-teinvit-slot="rsvp" style="max-width:1200px;margin:0 auto;padding:12px;">
      <?php include TEINVIT_WEDDING_MODULE_PATH . 'templates/page-invitati.php'; ?>
    </div>
  <?php endif; ?>
</div>
<?php
get_footer();
wp_reset_postdata();
