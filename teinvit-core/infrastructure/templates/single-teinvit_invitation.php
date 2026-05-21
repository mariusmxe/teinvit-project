<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'teinvit_build_fse_template_part_block' ) ) {
    function teinvit_build_fse_template_part_block( $slug, $tag_name, $class_name = '' ) {
        $slug = sanitize_key( (string) $slug );
        if ( $slug === '' ) {
            return '';
        }

        $attrs = [
            'slug' => $slug,
            'theme' => get_stylesheet(),
            'tagName' => $tag_name,
        ];

        if ( $class_name !== '' ) {
            $attrs['className'] = sanitize_html_class( $class_name );
        }

        return sprintf(
            '<!-- wp:template-part %s /-->',
            wp_json_encode( $attrs )
        );
    }
}

if ( ! function_exists( 'teinvit_render_fse_template_part_to_string' ) ) {
    function teinvit_render_fse_template_part_to_string( $slug, $tag_name, $class_name = '' ) {
        $block = teinvit_build_fse_template_part_block( $slug, $tag_name, $class_name );
        if ( $block === '' ) {
            return '';
        }

        return (string) do_blocks( $block );
    }
}

if ( ! function_exists( 'teinvit_prime_fse_template_part_cache' ) ) {
    function teinvit_prime_fse_template_part_cache() {
        if ( isset( $GLOBALS['teinvit_fse_header_html'], $GLOBALS['teinvit_fse_footer_html'] ) ) {
            return;
        }

        $GLOBALS['teinvit_fse_header_html'] = teinvit_render_fse_template_part_to_string( 'header', 'header', 'site-header' );
        $GLOBALS['teinvit_fse_footer_html'] = teinvit_render_fse_template_part_to_string( 'footer', 'footer', 'site-footer' );
    }
}

if ( ! function_exists( 'teinvit_render_layout_header' ) ) {
    function teinvit_render_layout_header() {
        if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
            teinvit_prime_fse_template_part_cache();

            echo '<!doctype html>';
            ?>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
            wp_body_open();
            echo '<div class="wp-site-blocks">';
            echo $GLOBALS['teinvit_fse_header_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        get_header();
    }
}

if ( ! function_exists( 'teinvit_render_layout_footer' ) ) {
    function teinvit_render_layout_footer() {
        if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
            teinvit_prime_fse_template_part_cache();

            echo $GLOBALS['teinvit_fse_footer_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</div>';
            wp_footer();
            echo '</body></html>';
            return;
        }

        get_footer();
    }
}

$mode = isset( $GLOBALS['teinvit_tokenized_mode'] ) ? (string) $GLOBALS['teinvit_tokenized_mode'] : '';
$token = isset( $GLOBALS['teinvit_tokenized_token'] ) ? (string) $GLOBALS['teinvit_tokenized_token'] : '';
$post_id = isset( $GLOBALS['teinvit_tokenized_post_id'] ) ? (int) $GLOBALS['teinvit_tokenized_post_id'] : 0;
$vertical_key = isset( $GLOBALS['teinvit_tokenized_vertical'] ) ? (string) $GLOBALS['teinvit_tokenized_vertical'] : '';
$vertical_key = $vertical_key !== '' && function_exists( 'teinvit_normalize_vertical_key' )
    ? teinvit_normalize_vertical_key( $vertical_key )
    : ( function_exists( 'teinvit_resolve_token_vertical' ) ? teinvit_resolve_token_vertical( $token ) : 'wedding' );

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
$preview_invitation_data = [];
if ( $mode === 'invitati' && $token !== '' && function_exists( 'teinvit_get_order_id_by_token' ) ) {
    $order_id = (int) teinvit_get_order_id_by_token( $token );
    $order = $order_id ? wc_get_order( $order_id ) : null;
    if ( $order ) {
        $payload = function_exists( 'teinvit_ensure_active_snapshot_payload' )
            ? teinvit_ensure_active_snapshot_payload( $token, $order )
            : ( function_exists( 'teinvit_get_modular_active_payload' ) ? teinvit_get_modular_active_payload( $token ) : [] );

        if ( ! empty( $payload['invitation'] ) && is_array( $payload['invitation'] ) ) {
            $preview_invitation_data = $payload['invitation'];
            if ( $vertical_key === 'wedding' ) {
                $preview_html = TeInvit_Wedding_Preview_Renderer::render_from_invitation_data( $payload['invitation'], $order );
            } else {
                $product_id = function_exists( 'teinvit_get_order_primary_product_id' ) ? (int) teinvit_get_order_primary_product_id( $order ) : 0;
                $preview_html = function_exists( 'teinvit_render_invitation_html_for_vertical' )
                    ? teinvit_render_invitation_html_for_vertical( $vertical_key, $payload['invitation'], $order, 'preview', $product_id )
                    : '';
            }
            $preview_html = preg_replace( '/<script>\s*window\.TEINVIT_INVITATION_DATA\s*=.*?<\/script>/s', '', (string) $preview_html );
            $preview_html = preg_replace( '/window\.TEINVIT_INVITATION_DATA\s*=\s*.*?;\s*/s', '', (string) $preview_html );
        }
    }

    $GLOBALS['TEINVIT_IN_CPT_TEMPLATE'] = true;
}

if ( $mode === 'invitati' && function_exists( 'teinvit_share_build_payload' ) && function_exists( 'teinvit_share_render_meta' ) ) {
    $share_payload = teinvit_share_build_payload( $token, $vertical_key, $preview_invitation_data, 'invitati' );
    teinvit_share_render_meta( $share_payload );
}

teinvit_render_layout_header();
?>
<div class="teinvit-invitation-layout teinvit-mode-<?php echo esc_attr( $mode ); ?>">
  <div class="teinvit-invitation-content">
    <?php the_content(); ?>
  </div>

  <?php if ( $mode === 'admin-client' ) : ?>
    <div class="teinvit-slot teinvit-slot-admin" data-teinvit-slot="admin">
      <?php
      if ( $vertical_key === 'wedding' ) {
          include TEINVIT_WEDDING_MODULE_PATH . 'templates/page-admin-client.php';
      } elseif ( $vertical_key === 'birthday' && defined( 'TEINVIT_BIRTHDAY_MODULE_PATH' ) && file_exists( TEINVIT_BIRTHDAY_MODULE_PATH . 'templates/page-admin-client.php' ) ) {
          include TEINVIT_BIRTHDAY_MODULE_PATH . 'templates/page-admin-client.php';
      } elseif ( function_exists( 'teinvit_render_vertical_admin_client_foundation' ) ) {
          $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? (int) teinvit_get_order_id_by_token( $token ) : 0;
          $order = $order_id ? wc_get_order( $order_id ) : null;
          teinvit_render_vertical_admin_client_foundation( $token, $vertical_key, $order );
      } else {
          echo '<p>Administrarea invitației nu este disponibilă.</p>';
      }
      ?>
    </div>
  <?php elseif ( $mode === 'invitati' ) : ?>
    <?php if ( $vertical_key === 'wedding' ) : ?>
      <?php if ( ! empty( $preview_invitation_data ) ) : ?>
        <script>window.TEINVIT_INVITATION_DATA = <?php echo wp_json_encode( $preview_invitation_data ); ?>;</script>
      <?php endif; ?>
      <div class="teinvit-slot teinvit-slot-preview" data-teinvit-slot="preview">
        <?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      </div>
      <div class="teinvit-slot teinvit-slot-rsvp" data-teinvit-slot="rsvp">
        <?php include TEINVIT_WEDDING_MODULE_PATH . 'templates/page-invitati.php'; ?>
      </div>
    <?php elseif ( $vertical_key === 'birthday' && defined( 'TEINVIT_BIRTHDAY_MODULE_PATH' ) && file_exists( TEINVIT_BIRTHDAY_MODULE_PATH . 'templates/page-invitati.php' ) ) : ?>
      <?php if ( ! empty( $preview_invitation_data ) ) : ?>
        <script>window.TEINVIT_INVITATION_DATA = <?php echo wp_json_encode( $preview_invitation_data ); ?>;</script>
      <?php endif; ?>
      <div class="teinvit-slot teinvit-slot-preview" data-teinvit-slot="preview">
        <?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      </div>
      <div class="teinvit-slot teinvit-slot-rsvp" data-teinvit-slot="rsvp">
        <?php include TEINVIT_BIRTHDAY_MODULE_PATH . 'templates/page-invitati.php'; ?>
      </div>
    <?php elseif ( function_exists( 'teinvit_render_vertical_invitati_foundation' ) ) : ?>
      <?php if ( ! empty( $preview_invitation_data ) ) : ?>
        <script>window.TEINVIT_INVITATION_DATA = <?php echo wp_json_encode( $preview_invitation_data ); ?>;</script>
      <?php endif; ?>
      <?php
      $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? (int) teinvit_get_order_id_by_token( $token ) : 0;
      $order = $order_id ? wc_get_order( $order_id ) : null;
      teinvit_render_vertical_invitati_foundation( $token, $vertical_key, $order, $preview_invitation_data, $preview_html );
      ?>
    <?php else : ?>
      <p>Pagina invitaților nu este disponibilă.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php
teinvit_render_layout_footer();
wp_reset_postdata();
unset( $GLOBALS['TEINVIT_IN_CPT_TEMPLATE'] );
unset( $GLOBALS['teinvit_tokenized_vertical'] );
unset( $GLOBALS['teinvit_fse_header_html'], $GLOBALS['teinvit_fse_footer_html'] );
