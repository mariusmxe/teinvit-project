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

    if ( isset( $matches[1] ) && $matches[1] !== '' ) {
        return teinvit_product_description_decode_attr_value( $matches[1] );
    }
    if ( isset( $matches[2] ) && $matches[2] !== '' ) {
        return teinvit_product_description_decode_attr_value( $matches[2] );
    }
    if ( isset( $matches[3] ) && $matches[3] !== '' ) {
        return teinvit_product_description_decode_attr_value( $matches[3] );
    }

    return '';
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
    return $src !== '' && stripos( $src, '2705.svg' ) !== false;
}

function teinvit_product_description_replace_wordpress_emoji_imgs( $html ) {
    $updated = preg_replace_callback(
        '/<img\b[^>]*>/i',
        static function( $matches ) {
            $tag = (string) ( $matches[0] ?? '' );

            return teinvit_product_description_is_wordpress_checkmark_emoji_img( $tag )
                ? teinvit_product_description_checkmark_markup()
                : $tag;
        },
        (string) $html
    );

    return is_string( $updated ) ? $updated : (string) $html;
}

function teinvit_product_description_replace_text_checkmarks( $html ) {
    $parts = preg_split( '/(<[^>]+>)/', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE );
    if ( ! is_array( $parts ) ) {
        $updated = preg_replace(
            '/(?:\x{2705}|&(?:amp;)?#(?:x2705|9989);)/iu',
            teinvit_product_description_checkmark_markup(),
            (string) $html
        );

        return is_string( $updated ) ? $updated : (string) $html;
    }

    foreach ( $parts as $index => $part ) {
        if ( $part !== '' && $part[0] === '<' ) {
            continue;
        }

        $updated = preg_replace(
            '/(?:\x{2705}|&(?:amp;)?#(?:x2705|9989);)/iu',
            teinvit_product_description_checkmark_markup(),
            $part
        );
        $parts[ $index ] = is_string( $updated ) ? $updated : $part;
    }

    return implode( '', $parts );
}

function teinvit_product_description_replace_checkmarks_outside_existing_markup( $html ) {
    $html = teinvit_product_description_replace_wordpress_emoji_imgs( (string) $html );

    return teinvit_product_description_replace_text_checkmarks( $html );
}

function teinvit_product_description_brand_checkmarks( $html ) {
    $html = (string) $html;
    if ( $html === '' ) {
        return $html;
    }

    $protected_pattern = '/(<span\b(?=[^>]*\bteinvit-checkmark\b)[^>]*>.*?<\/span>|<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>|<textarea\b[^>]*>.*?<\/textarea>)/is';
    $parts = preg_split( $protected_pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE );
    if ( ! is_array( $parts ) ) {
        return teinvit_product_description_replace_checkmarks_outside_existing_markup( $html );
    }

    foreach ( $parts as $index => $part ) {
        if ( preg_match( '/^(?:<span\b(?=[^>]*\bteinvit-checkmark\b)[^>]*>.*?<\/span>|<script\b[^>]*>.*?<\/script>|<style\b[^>]*>.*?<\/style>|<textarea\b[^>]*>.*?<\/textarea>)$/is', $part ) ) {
            continue;
        }

        $parts[ $index ] = teinvit_product_description_replace_checkmarks_outside_existing_markup( $part );
    }

    return implode( '', $parts );
}

function teinvit_product_description_enqueue_checkmark_css() {
    if ( ! teinvit_product_description_should_filter_current_product() ) {
        return;
    }

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
    if ( ! teinvit_product_description_should_filter_current_product() ) {
        return $short_description;
    }

    return teinvit_product_description_brand_checkmarks( $short_description );
}
add_filter( 'woocommerce_short_description', 'teinvit_product_description_filter_short_description', 999 );

function teinvit_product_description_filter_product_tabs( $tabs ) {
    if ( ! teinvit_product_description_should_filter_current_product() ) {
        return $tabs;
    }
    if ( empty( $tabs['description']['callback'] ) || ! is_callable( $tabs['description']['callback'] ) ) {
        return $tabs;
    }
    if ( ! empty( $tabs['description']['teinvit_checkmark_wrapped'] ) ) {
        return $tabs;
    }

    $original_callback = $tabs['description']['callback'];
    $tabs['description']['callback'] = static function( $key = null, $tab = null ) use ( $original_callback ) {
        ob_start();
        $result = call_user_func( $original_callback, $key, $tab );
        $html = (string) ob_get_clean();
        if ( is_string( $result ) && $result !== '' ) {
            $html .= $result;
        }

        echo teinvit_product_description_brand_checkmarks( $html );
    };
    $tabs['description']['teinvit_checkmark_wrapped'] = true;

    return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'teinvit_product_description_filter_product_tabs', 999 );
