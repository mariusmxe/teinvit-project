<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'teinvit_render_fse_template_part' ) ) {
    function teinvit_render_fse_template_part( $slug, $tag_name, $class_name = '' ) {
        $slug = sanitize_key( (string) $slug );
        if ( $slug === '' ) {
            return;
        }

        $attrs = [
            'slug' => $slug,
            'theme' => get_stylesheet(),
            'tagName' => $tag_name,
        ];

        if ( $class_name !== '' ) {
            $attrs['className'] = sanitize_html_class( $class_name );
        }

        $block = sprintf(
            '<!-- wp:template-part %s /-->',
            wp_json_encode( $attrs )
        );

        echo do_blocks( $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

if ( ! function_exists( 'teinvit_render_layout_header' ) ) {
    function teinvit_render_layout_header() {
        if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
            get_header();
            echo '<div class="wp-site-blocks">';
            teinvit_render_fse_template_part( 'header', 'header', 'site-header' );
            return;
        }

        get_header();
    }
}

if ( ! function_exists( 'teinvit_render_layout_footer' ) ) {
    function teinvit_render_layout_footer() {
        if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
            teinvit_render_fse_template_part( 'footer', 'footer', 'site-footer' );
            echo '</div>';
            get_footer();
            return;
        }

        get_footer();
    }
}

$mode = isset( $GLOBALS['teinvit_tokenized_mode'] ) ? (string) $GLOBALS['teinvit_tokenized_mode'] : '';
$token = isset( $GLOBALS['teinvit_tokenized_token'] ) ? (string) $GLOBALS['teinvit_tokenized_token'] : '';
$post_id = isset( $GLOBALS['teinvit_tokenized_post_id'] ) ? (int) $GLOBALS['teinvit_tokenized_post_id'] : 0;

$post = $post_id ? get_post( $post_id ) : null;
if ( ! $post || $post->post_type !== 'teinvit_invitation' ) {
    status_header( 404 );
    teinvit_render_layout_header();
    echo '<p>Invitația nu a fost găsită.</p>';
    teinvit_render_layout_footer();
    return;
}

$GLOBALS['post'] = $post;
setup_postdata( $post );

$preview_html = '';
if ( $mode === 'invitati' && $token !== '' && function_exists( 'teinvit_get_order_id_by_token' ) ) {
    $order_id = (int) teinvit_get_order_id_by_token( $token );
    $order = $order_id ? wc_get_order( $order_id ) : null;
    if ( $order ) {
        $payload = function_exists( 'teinvit_ensure_active_snapshot_payload' )
            ? teinvit_ensure_active_snapshot_payload( $token, $order )
            : ( function_exists( 'teinvit_get_modular_active_payload' ) ? teinvit_get_modular_active_payload( $token ) : [] );

        if ( ! empty( $payload['invitation'] ) && is_array( $payload['invitation'] ) ) {
            $preview_html = TeInvit_Wedding_Preview_Renderer::render_from_invitation_data( $payload['invitation'], $order );
        }
    }

    $GLOBALS['TEINVIT_IN_CPT_TEMPLATE'] = true;
}

teinvit_render_layout_header();
?>
<div class="teinvit-invitation-layout teinvit-mode-<?php echo esc_attr( $mode ); ?>">
  <div class="teinvit-invitation-content">
    <?php the_content(); ?>
  </div>

  <?php if ( $mode === 'admin-client' ) : ?>
    <div class="teinvit-slot teinvit-slot-admin" data-teinvit-slot="admin">
      <?php include TEINVIT_WEDDING_MODULE_PATH . 'templates/page-admin-client.php'; ?>
    </div>
  <?php elseif ( $mode === 'invitati' ) : ?>
    <div class="teinvit-slot teinvit-slot-preview" data-teinvit-slot="preview">
      <?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
    <div class="teinvit-slot teinvit-slot-rsvp" data-teinvit-slot="rsvp">
      <?php include TEINVIT_WEDDING_MODULE_PATH . 'templates/page-invitati.php'; ?>
    </div>
  <?php endif; ?>
</div>
<?php
teinvit_render_layout_footer();
wp_reset_postdata();
unset( $GLOBALS['TEINVIT_IN_CPT_TEMPLATE'] );
