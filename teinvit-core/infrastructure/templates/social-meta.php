<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_share_render_meta( array $payload ) {
    static $rendered = false;
    if ( $rendered ) {
        return;
    }

    $meta_title = trim( (string) ( $payload['title'] ?? '' ) );
    $meta_desc = trim( (string) ( $payload['description'] ?? $payload['text'] ?? '' ) );
    $meta_url = function_exists( 'teinvit_share_normalize_url' )
        ? teinvit_share_normalize_url( (string) ( $payload['url'] ?? '' ) )
        : esc_url_raw( (string) ( $payload['url'] ?? '' ) );
    $meta_image = function_exists( 'teinvit_share_normalize_url' )
        ? teinvit_share_normalize_url( (string) ( $payload['image'] ?? '' ) )
        : esc_url_raw( (string) ( $payload['image'] ?? '' ) );
    $meta_image_width = max( 0, (int) ( $payload['image_width'] ?? 0 ) );
    $meta_image_height = max( 0, (int) ( $payload['image_height'] ?? 0 ) );

    if ( $meta_title === '' ) {
        $meta_title = 'Te Invit';
    }
    if ( $meta_desc === '' ) {
        $meta_desc = $meta_title;
    }
    if ( function_exists( 'wp_trim_words' ) ) {
        $meta_desc = wp_trim_words( $meta_desc, 30, html_entity_decode( '&hellip;', ENT_QUOTES, 'UTF-8' ) );
    }

    $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $rendered = true;

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

    add_action( 'wp_head', function() use ( $meta_title, $meta_desc, $meta_url, $meta_image, $meta_image_width, $meta_image_height, $site_name ) {
        if ( $meta_url !== '' ) {
            echo "\n" . '<link rel="canonical" href="' . esc_url( $meta_url ) . '" />' . "\n";
        }
        echo '<meta name="description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $meta_title ) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
        if ( $meta_url !== '' ) {
            echo '<meta property="og:url" content="' . esc_url( $meta_url ) . '" />' . "\n";
        }
        echo '<meta property="og:type" content="website" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
        if ( $meta_image !== '' ) {
            echo '<meta property="og:image" content="' . esc_url( $meta_image ) . '" />' . "\n";
            echo '<meta property="og:image:secure_url" content="' . esc_url( $meta_image ) . '" />' . "\n";
            if ( $meta_image_width > 0 && $meta_image_height > 0 ) {
                echo '<meta property="og:image:width" content="' . esc_attr( (string) $meta_image_width ) . '" />' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr( (string) $meta_image_height ) . '" />' . "\n";
            }
        }
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $meta_title ) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr( $meta_desc ) . '" />' . "\n";
        if ( $meta_image !== '' ) {
            echo '<meta name="twitter:image" content="' . esc_url( $meta_image ) . '" />' . "\n";
        }
    }, 0 );

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

    add_filter( 'jetpack_enable_open_graph', '__return_false', 999 );
}
