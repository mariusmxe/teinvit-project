<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_product_description_current_product() {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return null;
    }

    global $product;
    if ( class_exists( 'WC_Product' ) && $product instanceof WC_Product ) {
        return $product;
    }

    $product_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
    if ( $product_id <= 0 && function_exists( 'get_the_ID' ) ) {
        $product_id = (int) get_the_ID();
    }

    return $product_id > 0 ? wc_get_product( $product_id ) : null;
}

function teinvit_product_description_product_is_te_invit( $product ) {
    if ( ! class_exists( 'WC_Product' ) || ! $product instanceof WC_Product ) {
        return false;
    }
    if ( ! function_exists( 'teinvit_find_catalog_vertical_for_product_id' ) ) {
        return false;
    }

    $product_ids = [ (int) $product->get_id() ];
    if ( method_exists( $product, 'get_parent_id' ) ) {
        $parent_id = (int) $product->get_parent_id();
        if ( $parent_id > 0 ) {
            $product_ids[] = $parent_id;
        }
    }

    foreach ( array_unique( array_filter( $product_ids ) ) as $product_id ) {
        if ( teinvit_find_catalog_vertical_for_product_id( $product_id ) !== '' ) {
            return true;
        }
    }

    return false;
}

function teinvit_product_description_should_filter_current_product() {
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return false;
    }

    return teinvit_product_description_product_is_te_invit( teinvit_product_description_current_product() );
}

function teinvit_product_description_is_token_route() {
    foreach ( [ 'teinvit_token', 'teinvit_pdf_token', 'teinvit_admin_client_token' ] as $query_var ) {
        if ( function_exists( 'get_query_var' ) && get_query_var( $query_var ) ) {
            return true;
        }
    }

    return false;
}

function teinvit_product_description_diag_state() {
    if ( ! isset( $GLOBALS['teinvit_product_description_diag'] ) || ! is_array( $GLOBALS['teinvit_product_description_diag'] ) ) {
        $GLOBALS['teinvit_product_description_diag'] = [
            'included' => 1,
            'product_id' => 0,
            'is_teinvit' => 0,
            'short_filter_called' => 0,
            'tabs_wrapper_called' => 0,
            'tabs_callback_called' => 0,
            'the_content_called' => 0,
            'helper_calls' => 0,
            'replacements' => 0,
            'markers_before' => 0,
            'markers_after' => 0,
        ];
    }

    return $GLOBALS['teinvit_product_description_diag'];
}

function teinvit_product_description_diag_set( $key, $value ) {
    $diag = teinvit_product_description_diag_state();
    $diag[ (string) $key ] = $value;
    $GLOBALS['teinvit_product_description_diag'] = $diag;
}

function teinvit_product_description_diag_increment( $key, $amount = 1 ) {
    $diag = teinvit_product_description_diag_state();
    $key = (string) $key;
    $diag[ $key ] = (int) ( $diag[ $key ] ?? 0 ) + (int) $amount;
    $GLOBALS['teinvit_product_description_diag'] = $diag;
}

function teinvit_product_description_diag_context_key( $context, $suffix ) {
    $context = strtolower( preg_replace( '/[^a-z0-9_]+/i', '_', (string) $context ) );
    $context = trim( $context, '_' );
    if ( $context === '' ) {
        $context = 'helper';
    }

    return $context . '_' . (string) $suffix;
}

function teinvit_product_description_diag_can_render() {
    if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
        return false;
    }

    return function_exists( 'current_user_can' )
        && ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) );
}

function teinvit_product_description_diag_capture_product_context() {
    $product = teinvit_product_description_current_product();
    $product_id = ( class_exists( 'WC_Product' ) && $product instanceof WC_Product ) ? (int) $product->get_id() : 0;

    teinvit_product_description_diag_set( 'product_id', $product_id );
    teinvit_product_description_diag_set( 'is_teinvit', teinvit_product_description_product_is_te_invit( $product ) ? 1 : 0 );
}

function teinvit_product_description_has_checkmark_markers( $html ) {
    $html = (string) $html;
    if ( $html === '' ) {
        return false;
    }
    if ( strpos( $html, teinvit_product_description_checkmark_emoji() ) !== false ) {
        return true;
    }
    if ( stripos( $html, '2705.svg' ) !== false ) {
        return true;
    }

    return (bool) preg_match( '/\bclass\s*=\s*(?:"[^"]*\bemoji\b[^"]*"|\'[^\']*\bemoji\b[^\']*\'|[^\s>]*\bemoji\b[^\s>]*)/i', $html );
}

function teinvit_product_description_should_filter_the_content( $html ) {
    if ( ! teinvit_product_description_has_checkmark_markers( $html ) ) {
        return false;
    }
    if ( teinvit_product_description_is_token_route() ) {
        return false;
    }
    if ( ! function_exists( 'is_product' ) || ! is_product() ) {
        return false;
    }

    $post_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
    if ( $post_id <= 0 || ! function_exists( 'get_post_type' ) || get_post_type( $post_id ) !== 'product' ) {
        return false;
    }

    $product = function_exists( 'wc_get_product' ) ? wc_get_product( $post_id ) : null;
    if ( ! $product ) {
        $product = teinvit_product_description_current_product();
    }

    return teinvit_product_description_product_is_te_invit( $product );
}

function teinvit_product_description_checkmark_emoji() {
    return html_entity_decode( '&#x2705;', ENT_QUOTES, 'UTF-8' );
}

function teinvit_product_description_checkmark_markup() {
    return '<span class="teinvit-checkmark teinvit-checkmark--brand">' . html_entity_decode( '&#10003;', ENT_QUOTES, 'UTF-8' ) . '</span>';
}

function teinvit_product_description_decode_attr_value( $value ) {
    $decoded = (string) $value;
    for ( $i = 0; $i < 2; $i++ ) {
        $next = html_entity_decode( $decoded, ENT_QUOTES, 'UTF-8' );
        if ( $next === $decoded ) {
            break;
        }
        $decoded = $next;
    }

    return $decoded;
}

function teinvit_product_description_img_attr( $tag, $attr ) {
    $attr = preg_quote( (string) $attr, '/' );
    if ( ! preg_match( '/\b' . $attr . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', (string) $tag, $matches ) ) {
        return '';
    }

    $value = '';
    if ( isset( $matches[1] ) && $matches[1] !== '' ) {
        $value = $matches[1];
    } elseif ( isset( $matches[2] ) && $matches[2] !== '' ) {
        $value = $matches[2];
    } elseif ( isset( $matches[3] ) && $matches[3] !== '' ) {
        $value = $matches[3];
    }

    return teinvit_product_description_decode_attr_value( $value );
}

function teinvit_product_description_is_wordpress_checkmark_emoji_img( $tag ) {
    $class = teinvit_product_description_img_attr( $tag, 'class' );
    if ( $class === '' || ! preg_match( '/(?:^|\s)emoji(?:\s|$)/i', $class ) ) {
        return false;
    }

    $alt = teinvit_product_description_img_attr( $tag, 'alt' );
    if ( $alt === teinvit_product_description_checkmark_emoji() ) {
        return true;
    }

    $src = teinvit_product_description_img_attr( $tag, 'src' );
    return $src !== '' && strpos( $src, '/2705.svg' ) !== false;
}

function teinvit_product_description_replace_wordpress_emoji_imgs( $html ) {
    $replaced = preg_replace_callback(
        '/<img\b[^>]*>/i',
        static function( $matches ) {
            $tag = (string) ( $matches[0] ?? '' );
            return teinvit_product_description_is_wordpress_checkmark_emoji_img( $tag )
                ? teinvit_product_description_checkmark_markup()
                : $tag;
        },
        $html
    );

    return is_string( $replaced ) ? $replaced : $html;
}

function teinvit_product_description_count_wordpress_checkmark_emoji_imgs( $html ) {
    if ( ! preg_match_all( '/<img\b[^>]*>/i', (string) $html, $matches ) ) {
        return 0;
    }

    $count = 0;
    foreach ( (array) ( $matches[0] ?? [] ) as $tag ) {
        if ( teinvit_product_description_is_wordpress_checkmark_emoji_img( (string) $tag ) ) {
            $count++;
        }
    }

    return $count;
}

function teinvit_product_description_count_markers( $html ) {
    $html = (string) $html;

    return substr_count( $html, teinvit_product_description_checkmark_emoji() )
        + teinvit_product_description_count_wordpress_checkmark_emoji_imgs( $html );
}

function teinvit_product_description_count_branded_spans( $html ) {
    return preg_match_all( '/<span\b(?=[^>]*\bteinvit-checkmark\b)[^>]*>/i', (string) $html );
}

function teinvit_product_description_record_helper_diag( $context, $before_html, $after_html ) {
    $before_markers = teinvit_product_description_count_markers( $before_html );
    $after_markers = teinvit_product_description_count_markers( $after_html );
    $before_spans = teinvit_product_description_count_branded_spans( $before_html );
    $after_spans = teinvit_product_description_count_branded_spans( $after_html );
    $replacements = max( 0, (int) $after_spans - (int) $before_spans );

    teinvit_product_description_diag_increment( 'helper_calls' );
    teinvit_product_description_diag_increment( 'replacements', $replacements );
    teinvit_product_description_diag_increment( 'markers_before', $before_markers );
    teinvit_product_description_diag_increment( 'markers_after', $after_markers );

    teinvit_product_description_diag_increment( teinvit_product_description_diag_context_key( $context, 'helper_calls' ) );
    teinvit_product_description_diag_increment( teinvit_product_description_diag_context_key( $context, 'replacements' ), $replacements );
    teinvit_product_description_diag_increment( teinvit_product_description_diag_context_key( $context, 'markers_before' ), $before_markers );
    teinvit_product_description_diag_increment( teinvit_product_description_diag_context_key( $context, 'markers_after' ), $after_markers );
}

function teinvit_product_description_replace_raw_checkmarks( $html ) {
    $parts = preg_split( '/(<[^>]+>)/', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE );
    if ( ! is_array( $parts ) ) {
        return str_replace(
            teinvit_product_description_checkmark_emoji(),
            teinvit_product_description_checkmark_markup(),
            (string) $html
        );
    }

    foreach ( $parts as $index => $part ) {
        if ( $part !== '' && $part[0] === '<' ) {
            continue;
        }

        $parts[ $index ] = str_replace(
            teinvit_product_description_checkmark_emoji(),
            teinvit_product_description_checkmark_markup(),
            $part
        );
    }

    return implode( '', $parts );
}

function teinvit_product_description_replace_checkmarks_outside_existing_markup( $html ) {
    $html = (string) teinvit_product_description_replace_wordpress_emoji_imgs( (string) $html );

    return teinvit_product_description_replace_raw_checkmarks( $html );
}

function teinvit_product_description_brand_checkmarks( $html, $context = 'helper' ) {
    $html = (string) $html;
    if ( $html === '' ) {
        teinvit_product_description_record_helper_diag( $context, $html, $html );
        return $html;
    }

    $original_html = $html;
    $protected_pattern = '/(<span\b(?=[^>]*\bteinvit-checkmark\b)[^>]*>.*?<\/span>|<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>|<textarea\b[^>]*>.*?<\/textarea>)/is';

    if ( stripos( $html, 'teinvit-checkmark' ) === false && ! preg_match( '/<(?:script|style|textarea)\b/i', $html ) ) {
        $updated = teinvit_product_description_replace_checkmarks_outside_existing_markup( $html );
        teinvit_product_description_record_helper_diag( $context, $original_html, $updated );
        return $updated;
    }

    $parts = preg_split(
        $protected_pattern,
        $html,
        -1,
        PREG_SPLIT_DELIM_CAPTURE
    );

    if ( ! is_array( $parts ) ) {
        $updated = teinvit_product_description_replace_checkmarks_outside_existing_markup( $html );
        teinvit_product_description_record_helper_diag( $context, $original_html, $updated );
        return $updated;
    }

    foreach ( $parts as $index => $part ) {
        if ( preg_match( '/^(?:<span\b(?=[^>]*\bteinvit-checkmark\b)[^>]*>.*?<\/span>|<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>|<textarea\b[^>]*>.*?<\/textarea>)$/is', $part ) ) {
            continue;
        }

        $parts[ $index ] = teinvit_product_description_replace_checkmarks_outside_existing_markup( $part );
    }

    $updated = implode( '', $parts );
    teinvit_product_description_record_helper_diag( $context, $original_html, $updated );

    return $updated;
}

function teinvit_product_description_enqueue_checkmark_css() {
    if ( ! teinvit_product_description_should_filter_current_product() ) {
        return;
    }
    teinvit_product_description_diag_capture_product_context();

    $css_path = TEINVIT_CORE_PATH . 'infrastructure/frontend/product-description.css';
    if ( ! file_exists( $css_path ) ) {
        return;
    }

    wp_enqueue_style(
        'teinvit-product-description',
        TEINVIT_CORE_URL . 'infrastructure/frontend/product-description.css',
        [],
        TEINVIT_CORE_VERSION . '-' . filemtime( $css_path )
    );
}
add_action( 'wp_enqueue_scripts', 'teinvit_product_description_enqueue_checkmark_css', 30 );

function teinvit_product_description_filter_short_description( $short_description ) {
    teinvit_product_description_diag_increment( 'short_filter_called' );
    if ( ! teinvit_product_description_should_filter_current_product() ) {
        teinvit_product_description_diag_increment( 'short_filter_blocked' );
        return $short_description;
    }
    teinvit_product_description_diag_increment( 'short_filter_passed' );
    teinvit_product_description_diag_capture_product_context();

    return teinvit_product_description_brand_checkmarks( $short_description, 'short' );
}
add_filter( 'woocommerce_short_description', 'teinvit_product_description_filter_short_description', 999 );

function teinvit_product_description_filter_the_content( $content ) {
    teinvit_product_description_diag_increment( 'the_content_called' );
    teinvit_product_description_diag_set( 'the_content_last_post_id', function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0 );
    teinvit_product_description_diag_set( 'the_content_last_post_type', function_exists( 'get_post_type' ) ? (string) get_post_type() : '' );
    if ( ! teinvit_product_description_should_filter_the_content( $content ) ) {
        teinvit_product_description_diag_increment( 'the_content_blocked' );
        return $content;
    }
    teinvit_product_description_diag_increment( 'the_content_passed' );
    teinvit_product_description_diag_capture_product_context();

    return teinvit_product_description_brand_checkmarks( $content, 'the_content' );
}
add_filter( 'the_content', 'teinvit_product_description_filter_the_content', 999 );

function teinvit_product_description_filter_product_tabs( $tabs ) {
    teinvit_product_description_diag_increment( 'tabs_wrapper_called' );
    if ( ! teinvit_product_description_should_filter_current_product() ) {
        teinvit_product_description_diag_increment( 'tabs_wrapper_blocked' );
        return $tabs;
    }
    teinvit_product_description_diag_increment( 'tabs_wrapper_passed' );
    teinvit_product_description_diag_capture_product_context();
    if ( empty( $tabs['description']['callback'] ) || ! is_callable( $tabs['description']['callback'] ) ) {
        teinvit_product_description_diag_increment( 'tabs_description_callback_missing' );
        return $tabs;
    }
    if ( ! empty( $tabs['description']['teinvit_checkmark_wrapped'] ) ) {
        return $tabs;
    }

    $original_callback = $tabs['description']['callback'];
    $tabs['description']['callback'] = static function( $key, $tab ) use ( $original_callback ) {
        teinvit_product_description_diag_increment( 'tabs_callback_called' );
        ob_start();
        call_user_func( $original_callback, $key, $tab );
        $html = (string) ob_get_clean();

        echo teinvit_product_description_brand_checkmarks( $html, 'tab' );
    };
    $tabs['description']['teinvit_checkmark_wrapped'] = true;

    return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'teinvit_product_description_filter_product_tabs', 999 );

function teinvit_product_description_should_start_buffers() {
    if ( function_exists( 'is_admin' ) && is_admin() ) {
        return false;
    }
    if ( teinvit_product_description_is_token_route() ) {
        return false;
    }

    return teinvit_product_description_should_filter_current_product();
}

function teinvit_product_description_start_hook_buffer( $context ) {
    if ( ! teinvit_product_description_should_start_buffers() ) {
        return;
    }

    teinvit_product_description_diag_capture_product_context();
    teinvit_product_description_diag_increment( teinvit_product_description_diag_context_key( $context, 'buffer_started' ) );

    ob_start();
    $stack = isset( $GLOBALS['teinvit_product_description_hook_buffer_stack'] ) && is_array( $GLOBALS['teinvit_product_description_hook_buffer_stack'] )
        ? $GLOBALS['teinvit_product_description_hook_buffer_stack']
        : [];
    $stack[] = [
        'context' => (string) $context,
        'level' => ob_get_level(),
    ];
    $GLOBALS['teinvit_product_description_hook_buffer_stack'] = $stack;
}

function teinvit_product_description_end_hook_buffer( $context ) {
    $stack = isset( $GLOBALS['teinvit_product_description_hook_buffer_stack'] ) && is_array( $GLOBALS['teinvit_product_description_hook_buffer_stack'] )
        ? $GLOBALS['teinvit_product_description_hook_buffer_stack']
        : [];
    if ( empty( $stack ) ) {
        return;
    }

    $entry = array_pop( $stack );
    $GLOBALS['teinvit_product_description_hook_buffer_stack'] = $stack;
    if ( empty( $entry['context'] ) || (string) $entry['context'] !== (string) $context ) {
        return;
    }
    if ( ob_get_level() < (int) ( $entry['level'] ?? 0 ) ) {
        return;
    }

    $html = (string) ob_get_clean();
    teinvit_product_description_diag_increment( teinvit_product_description_diag_context_key( $context, 'buffer_ended' ) );

    echo teinvit_product_description_brand_checkmarks( $html, $context );
}

function teinvit_product_description_start_summary_buffer() {
    teinvit_product_description_start_hook_buffer( 'woo_summary' );
}
add_action( 'woocommerce_single_product_summary', 'teinvit_product_description_start_summary_buffer', 1 );

function teinvit_product_description_end_summary_buffer() {
    teinvit_product_description_end_hook_buffer( 'woo_summary' );
}
add_action( 'woocommerce_single_product_summary', 'teinvit_product_description_end_summary_buffer', 999 );

function teinvit_product_description_start_after_summary_buffer() {
    teinvit_product_description_start_hook_buffer( 'woo_after_summary' );
}
add_action( 'woocommerce_after_single_product_summary', 'teinvit_product_description_start_after_summary_buffer', 1 );

function teinvit_product_description_end_after_summary_buffer() {
    teinvit_product_description_end_hook_buffer( 'woo_after_summary' );
}
add_action( 'woocommerce_after_single_product_summary', 'teinvit_product_description_end_after_summary_buffer', 999 );

function teinvit_product_description_diag_comment() {
    $diag = teinvit_product_description_diag_state();
    $diag['short_hook_registered'] = ( function_exists( 'has_filter' ) && has_filter( 'woocommerce_short_description', 'teinvit_product_description_filter_short_description' ) !== false ) ? 1 : 0;
    $diag['tabs_hook_registered'] = ( function_exists( 'has_filter' ) && has_filter( 'woocommerce_product_tabs', 'teinvit_product_description_filter_product_tabs' ) !== false ) ? 1 : 0;
    $diag['the_content_hook_registered'] = ( function_exists( 'has_filter' ) && has_filter( 'the_content', 'teinvit_product_description_filter_the_content' ) !== false ) ? 1 : 0;

    $preferred = [
        'product_id',
        'is_teinvit',
        'short_hook_registered',
        'tabs_hook_registered',
        'the_content_hook_registered',
        'short_filter_called',
        'short_filter_passed',
        'tabs_wrapper_called',
        'tabs_wrapper_passed',
        'tabs_callback_called',
        'the_content_called',
        'the_content_passed',
        'woo_summary_buffer_started',
        'woo_summary_buffer_ended',
        'woo_after_summary_buffer_started',
        'woo_after_summary_buffer_ended',
        'page_buffer_started',
        'page_buffer_called',
        'helper_calls',
        'replacements',
        'markers_before',
        'markers_after',
    ];

    $parts = [];
    foreach ( $preferred as $key ) {
        if ( array_key_exists( $key, $diag ) ) {
            $value = str_replace( [ "\r", "\n", '-->' ], [ ' ', ' ', '--&gt;' ], (string) $diag[ $key ] );
            $parts[] = $key . '=' . $value;
        }
    }

    ksort( $diag );
    foreach ( $diag as $key => $value ) {
        if ( in_array( $key, $preferred, true ) ) {
            continue;
        }
        $value = str_replace( [ "\r", "\n", '-->' ], [ ' ', ' ', '--&gt;' ], (string) $value );
        $parts[] = $key . '=' . $value;
    }

    return '<!-- TEINVIT_CHECKMARK_DIAG ' . implode( ' ', $parts ) . ' -->';
}

function teinvit_product_description_inject_diag_comment( $html ) {
    if ( ! teinvit_product_description_diag_can_render() ) {
        return $html;
    }

    $comment = "\n" . teinvit_product_description_diag_comment() . "\n";
    $updated = preg_replace( '/<\/body>/i', $comment . '</body>', (string) $html, 1 );

    return is_string( $updated ) ? $updated : ( (string) $html . $comment );
}

function teinvit_product_description_filter_page_buffer( $html ) {
    $html = (string) $html;
    teinvit_product_description_diag_increment( 'page_buffer_called' );
    teinvit_product_description_diag_set( 'page_buffer_markers_before', teinvit_product_description_count_markers( $html ) );

    $updated = teinvit_product_description_brand_checkmarks( $html, 'page_buffer' );
    teinvit_product_description_diag_set( 'page_buffer_markers_after', teinvit_product_description_count_markers( $updated ) );

    return teinvit_product_description_inject_diag_comment( $updated );
}

function teinvit_product_description_maybe_start_page_buffer() {
    if ( ! teinvit_product_description_should_start_buffers() ) {
        return;
    }

    teinvit_product_description_diag_capture_product_context();
    teinvit_product_description_diag_increment( 'page_buffer_started' );
    ob_start( 'teinvit_product_description_filter_page_buffer' );
}
add_action( 'template_redirect', 'teinvit_product_description_maybe_start_page_buffer', 1 );
