<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_share_enqueue_assets() {
    if ( ! defined( 'TEINVIT_CORE_PATH' ) || ! defined( 'TEINVIT_CORE_URL' ) ) {
        return;
    }

    $css_path = TEINVIT_CORE_PATH . 'infrastructure/frontend/share.css';
    $js_path = TEINVIT_CORE_PATH . 'infrastructure/frontend/share.js';

    if ( file_exists( $css_path ) ) {
        wp_enqueue_style(
            'teinvit-share',
            TEINVIT_CORE_URL . 'infrastructure/frontend/share.css',
            [],
            TEINVIT_CORE_VERSION . '-' . filemtime( $css_path )
        );
    }

    if ( file_exists( $js_path ) ) {
        wp_enqueue_script(
            'teinvit-share',
            TEINVIT_CORE_URL . 'infrastructure/frontend/share.js',
            [],
            TEINVIT_CORE_VERSION . '-' . filemtime( $js_path ),
            true
        );
    }
}

add_action( 'wp_enqueue_scripts', function() {
    if ( get_query_var( 'teinvit_admin_client_token' ) ) {
        teinvit_share_enqueue_assets();
    }
} );

function teinvit_share_button_url_facebook( array $payload ) {
    $url = function_exists( 'teinvit_share_normalize_url' )
        ? teinvit_share_normalize_url( (string) ( $payload['url'] ?? '' ) )
        : esc_url_raw( (string) ( $payload['url'] ?? '' ) );

    return $url !== '' ? 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ) : '';
}

function teinvit_share_button_url_whatsapp( array $payload ) {
    $message = trim( (string) ( $payload['message'] ?? '' ) );
    if ( $message === '' ) {
        $text = trim( (string) ( $payload['text'] ?? '' ) );
        $url = trim( (string) ( $payload['url'] ?? '' ) );
        $message = trim( $text . ( $url !== '' ? "\n" . $url : '' ) );
    }

    return $message !== '' ? 'https://wa.me/?text=' . rawurlencode( $message ) : '';
}

function teinvit_share_render_buttons( array $payload, array $args = [] ) {
    teinvit_share_enqueue_assets();

    $defaults = [
        'id' => 'teinvit-share-card',
        'class' => '',
        'title' => 'Distribuie invitația',
        'help_text' => 'Trimite rapid invitația către familie și prieteni. Pe telefon poți folosi butonul „Distribuie”, iar în rest ai opțiuni rapide mai jos.',
        'status_id' => 'teinvit-share-status',
        'show_icons' => true,
        'show_native' => true,
        'show_copy' => true,
        'show_facebook' => true,
        'show_whatsapp' => true,
        'show_instagram' => true,
        'enabled' => true,
    ];
    $args = wp_parse_args( $args, $defaults );

    if ( empty( $args['enabled'] ) ) {
        return;
    }

    $payload = [
        'title' => (string) ( $payload['title'] ?? '' ),
        'text' => (string) ( $payload['text'] ?? '' ),
        'message' => (string) ( $payload['message'] ?? '' ),
        'url' => function_exists( 'teinvit_share_normalize_url' )
            ? teinvit_share_normalize_url( (string) ( $payload['url'] ?? '' ) )
            : esc_url_raw( (string) ( $payload['url'] ?? '' ) ),
    ];
    if ( $payload['message'] === '' ) {
        $payload['message'] = trim( $payload['text'] . ( $payload['url'] !== '' ? "\n" . $payload['url'] : '' ) );
    }

    $facebook_url = teinvit_share_button_url_facebook( $payload );
    $whatsapp_url = teinvit_share_button_url_whatsapp( $payload );
    $icon_base = defined( 'TEINVIT_CORE_URL' ) ? trailingslashit( TEINVIT_CORE_URL . 'infrastructure/assets/icons/social' ) : '';
    $card_id = sanitize_html_class( (string) $args['id'] );
    $status_id = sanitize_html_class( (string) $args['status_id'] );
    $classes = trim( 'teinvit-zone teinvit-share-card ' . (string) $args['class'] );
    $payload_json = wp_json_encode( $payload );
    if ( ! is_string( $payload_json ) ) {
        $payload_json = '{}';
    }
    ?>
    <div class="<?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( $card_id ); ?>" data-teinvit-share-card data-share-payload="<?php echo esc_attr( $payload_json ); ?>">
      <h3><?php echo esc_html( (string) $args['title'] ); ?></h3>
      <?php if ( trim( (string) $args['help_text'] ) !== '' ) : ?>
        <p class="teinvit-share-help"><?php echo esc_html( (string) $args['help_text'] ); ?></p>
      <?php endif; ?>
      <div class="teinvit-share-actions">
        <?php if ( ! empty( $args['show_native'] ) ) : ?>
          <button type="button" class="button button-primary" id="teinvit-share-native" data-teinvit-share-action="native">Distribuie</button>
        <?php endif; ?>
        <?php if ( ! empty( $args['show_copy'] ) ) : ?>
          <button type="button" class="button" id="teinvit-share-copy-main" data-teinvit-share-action="copy">Copiază link</button>
        <?php endif; ?>
      </div>
      <div class="teinvit-share-quick">
        <?php if ( ! empty( $args['show_facebook'] ) && $facebook_url !== '' ) : ?>
          <div class="teinvit-share-row">
            <?php if ( ! empty( $args['show_icons'] ) && $icon_base !== '' ) : ?><span class="teinvit-share-icon-wrap"><img src="<?php echo esc_url( $icon_base . 'facebook.svg' ); ?>" alt="" aria-hidden="true"></span><?php endif; ?>
            <a class="button teinvit-share-social-btn" href="<?php echo esc_url( $facebook_url ); ?>" target="_blank" rel="noopener" data-teinvit-share-action="facebook">Facebook</a>
          </div>
        <?php endif; ?>
        <?php if ( ! empty( $args['show_whatsapp'] ) && $whatsapp_url !== '' ) : ?>
          <div class="teinvit-share-row">
            <?php if ( ! empty( $args['show_icons'] ) && $icon_base !== '' ) : ?><span class="teinvit-share-icon-wrap"><img src="<?php echo esc_url( $icon_base . 'whatsapp.svg' ); ?>" alt="" aria-hidden="true"></span><?php endif; ?>
            <a class="button teinvit-share-social-btn" id="teinvit-share-whatsapp" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener" data-teinvit-share-action="whatsapp">WhatsApp</a>
          </div>
        <?php endif; ?>
        <?php if ( ! empty( $args['show_instagram'] ) ) : ?>
          <div class="teinvit-share-row">
            <?php if ( ! empty( $args['show_icons'] ) && $icon_base !== '' ) : ?><span class="teinvit-share-icon-wrap"><img src="<?php echo esc_url( $icon_base . 'instagram.svg' ); ?>" alt="" aria-hidden="true"></span><?php endif; ?>
            <button type="button" class="button teinvit-share-social-btn" id="teinvit-share-instagram" data-teinvit-share-action="instagram">Instagram</button>
          </div>
        <?php endif; ?>
      </div>
      <p class="teinvit-share-status" id="<?php echo esc_attr( $status_id ); ?>" data-teinvit-share-status aria-live="polite"></p>
    </div>
    <?php
}
