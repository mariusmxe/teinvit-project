<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$mode = isset( $GLOBALS['teinvit_tokenized_mode'] ) ? (string) $GLOBALS['teinvit_tokenized_mode'] : '';
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

if ( $mode === 'invitati' ) {
    $GLOBALS['TEINVIT_IN_CPT_TEMPLATE'] = true;
}

get_header();
?>
<div class="teinvit-invitation-layout teinvit-mode-<?php echo esc_attr( $mode ); ?>">
  <div class="teinvit-invitation-content" style="max-width:1200px;margin:0 auto;padding:12px;">
    <?php the_content(); ?>
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
unset( $GLOBALS['TEINVIT_IN_CPT_TEMPLATE'] );
