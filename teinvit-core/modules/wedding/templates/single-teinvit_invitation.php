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

if ( $mode === 'invitati' ) {
    $names = trim( (string) ( $preview_invitation_data['names'] ?? '' ) );
    $message = trim( (string) ( $preview_invitation_data['message'] ?? '' ) );

    $meta_title = $names !== '' ? ( 'Invitație ' . $names ) : 'Invitație | Te Invit';
    $meta_desc  = $message !== '' ? $message : ( $names !== '' ? ( 'Te invităm cu drag la evenimentul nostru, ' . $names . '.' ) : 'Te invităm cu drag la evenimentul nostru.' );
    if ( $vertical_key !== 'wedding' && function_exists( 'teinvit_vertical_share_payload' ) ) {
        $share_payload = teinvit_vertical_share_payload( $vertical_key, $preview_invitation_data, home_url( '/invitati/' . rawurlencode( $token ) ) );
        $meta_title = (string) ( $share_payload['title'] ?? $meta_title );
        $meta_desc = (string) ( $share_payload['text'] ?? $meta_desc );
    }
    $meta_desc  = function_exists( 'wp_trim_words' ) ? wp_trim_words( $meta_desc, 30, '…' ) : $meta_desc;
    $meta_url   = home_url( '/invitati/' . rawurlencode( $token ) );
    $logo_id = (int) get_theme_mod( 'custom_logo' );
    $logo_url = $logo_id > 0 ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
    $meta_image = $logo_url ? $logo_url : ( TEINVIT_WEDDING_MODULE_URL . 'assets/backgrounds/invn01.png' );
    $site_name  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

    // Keep runtime queried-object title aligned with invitation metadata to avoid generic token title fallbacks.
    if ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post ) {
        $GLOBALS['post']->post_title = $meta_title;
    }

    add_filter( 'pre_get_document_title', function( $title ) use ( $meta_title ) {
        return $meta_title !== '' ? $meta_title : $title;
    }, 999 );
    add_filter( 'document_title_parts', function( $parts ) use ( $meta_title ) {
        if ( is_array( $parts ) ) {
            $parts['title'] = $meta_title;
        }
        return $parts;
    }, 999 );
    add_filter( 'wp_title', fn() => $meta_title, 999 );
    add_filter( 'single_post_title', fn() => $meta_title, 999 );

    add_action( 'wp_head', function() use ( $meta_title, $meta_desc, $meta_url, $meta_image, $site_name ) {
        echo "\n" . '<link rel="canonical" href="' . esc_url( $meta_url ) . '" />' . "\n";
        echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $meta_title ) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url( $meta_url ) . '" />' . "\n";
        echo '<meta property="og:type" content="website" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
        echo '<meta property="og:image" content="' . esc_url( $meta_image ) . '" />' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $meta_title ) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
        echo '<meta name="twitter:image" content="' . esc_url( $meta_image ) . '" />' . "\n";
    }, 0 );

    // Yoast SEO overrides
    add_filter( 'wpseo_title', fn() => $meta_title, 999 );
    add_filter( 'wpseo_metadesc', fn() => $meta_desc, 999 );
    add_filter( 'wpseo_canonical', fn() => $meta_url, 999 );
    add_filter( 'wpseo_opengraph_title', fn() => $meta_title, 999 );
    add_filter( 'wpseo_opengraph_desc', fn() => $meta_desc, 999 );
    add_filter( 'wpseo_opengraph_url', fn() => $meta_url, 999 );
    add_filter( 'wpseo_opengraph_image', fn() => $meta_image, 999 );
    add_filter( 'wpseo_twitter_title', fn() => $meta_title, 999 );
    add_filter( 'wpseo_twitter_description', fn() => $meta_desc, 999 );
    add_filter( 'wpseo_twitter_image', fn() => $meta_image, 999 );

    // Rank Math overrides
    add_filter( 'rank_math/frontend/title', fn() => $meta_title, 999 );
    add_filter( 'rank_math/frontend/description', fn() => $meta_desc, 999 );
    add_filter( 'rank_math/frontend/canonical', fn() => $meta_url, 999 );
    add_filter( 'rank_math/opengraph/facebook/title', fn() => $meta_title, 999 );
    add_filter( 'rank_math/opengraph/facebook/description', fn() => $meta_desc, 999 );
    add_filter( 'rank_math/opengraph/facebook/url', fn() => $meta_url, 999 );
    add_filter( 'rank_math/opengraph/facebook/image', fn() => $meta_image, 999 );
    add_filter( 'rank_math/opengraph/twitter/title', fn() => $meta_title, 999 );
    add_filter( 'rank_math/opengraph/twitter/description', fn() => $meta_desc, 999 );
    add_filter( 'rank_math/opengraph/twitter/image', fn() => $meta_image, 999 );
    add_filter( 'rank_math/opengraph/twitter/url', fn() => $meta_url, 999 );

    // Jetpack Open Graph fallback
    add_filter( 'jetpack_enable_open_graph', '__return_false', 999 );
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
