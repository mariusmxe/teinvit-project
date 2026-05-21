<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_share_normalize_url( $url ) {
    $url = trim( (string) $url );
    if ( $url === '' ) {
        return '';
    }

    if ( strpos( $url, '//' ) === 0 ) {
        $url = 'https:' . $url;
    } elseif ( strpos( $url, '/' ) === 0 ) {
        $url = home_url( $url );
    } elseif ( ! preg_match( '#^[a-z][a-z0-9+\-.]*://#i', $url ) ) {
        $url = home_url( '/' . ltrim( $url, '/' ) );
    }

    $url = esc_url_raw( $url );
    if ( $url === '' ) {
        return '';
    }

    return function_exists( 'set_url_scheme' ) ? set_url_scheme( $url, 'https' ) : preg_replace( '/^http:/i', 'https:', $url );
}

function teinvit_share_guest_url( $token ) {
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return '';
    }

    return teinvit_share_normalize_url( home_url( '/invitati/' . rawurlencode( $token ) ) );
}

function teinvit_share_get_vertical_texts( $vertical, array $invitation ) {
    $vertical = function_exists( 'teinvit_normalize_vertical_key' ) ? teinvit_normalize_vertical_key( $vertical ) : 'wedding';
    $semantics = function_exists( 'teinvit_vertical_semantics' ) ? teinvit_vertical_semantics( $vertical ) : [];
    $share = isset( $semantics['share'] ) && is_array( $semantics['share'] ) ? $semantics['share'] : [];

    if ( $vertical === 'birthday' ) {
        $names = isset( $invitation['celebrants'] ) && is_array( $invitation['celebrants'] ) ? $invitation['celebrants'] : [];
        $joined_names = function_exists( 'teinvit_join_ro_names' ) ? teinvit_join_ro_names( $names ) : implode( ', ', array_filter( array_map( 'trim', $names ) ) );
        $count = count( array_filter( $names ) );
        $event_name = '';

        if ( isset( $invitation['event_name'] ) && is_array( $invitation['event_name'] ) && ! empty( $invitation['event_name']['enabled'] ) ) {
            $event_name = trim( (string) ( $invitation['event_name']['value'] ?? $invitation['event_name']['line'] ?? '' ) );
        }

        if ( $joined_names !== '' ) {
            if ( $event_name !== '' ) {
                $text = $joined_names . ' te invită la ' . $event_name;
                $title = $count <= 1 ? ( 'Te invit la ' . $event_name ) : ( 'Te invităm la ' . $event_name );
            } else {
                $text = $joined_names . ( $count <= 1 ? ' te invită la aniversarea sa' : ' te invită la aniversarea lor' );
                $title = $count <= 1 ? 'Te invit la aniversarea mea' : 'Te invităm la aniversarea noastră';
            }
        } else {
            $text = (string) ( $share['fallback_message'] ?? 'Te invităm cu drag la petrecerea aniversară' );
            $title = (string) ( $share['fallback_title'] ?? 'Invitație aniversare - Te Invit' );
        }

        return [
            'title' => $title,
            'short_text' => $text,
            'description' => $text,
        ];
    }

    if ( $vertical === 'baptism' ) {
        $children = isset( $invitation['children'] ) && is_array( $invitation['children'] ) ? $invitation['children'] : [];
        $joined_children = function_exists( 'teinvit_join_ro_names' ) ? teinvit_join_ro_names( $children ) : implode( ', ', array_filter( array_map( 'trim', $children ) ) );
        $children_title_len = function_exists( 'mb_strlen' ) ? mb_strlen( $joined_children ) : strlen( $joined_children );
        $use_children_in_title = $joined_children !== '' && $children_title_len <= 70;

        $short_text = $joined_children !== ''
            ? html_entity_decode( 'Te invit&#259;m cu drag la botez, al&#259;turi de ', ENT_QUOTES, 'UTF-8' ) . $joined_children
            : (string) ( $share['fallback_message'] ?? html_entity_decode( 'Te invit&#259;m cu drag la botez', ENT_QUOTES, 'UTF-8' ) );

        $description = $joined_children !== ''
            ? html_entity_decode( 'Te invit&#259;m cu drag la Slujba de botez &#537;i la petrecerea de botez, al&#259;turi de ', ENT_QUOTES, 'UTF-8' ) . $joined_children . '.'
            : html_entity_decode( 'Te invit&#259;m cu drag la Slujba de botez &#537;i la petrecerea de botez.', ENT_QUOTES, 'UTF-8' );

        $title = $use_children_in_title
            ? html_entity_decode( 'Te invit&#259;m la botezul lui ', ENT_QUOTES, 'UTF-8' ) . $joined_children
            : html_entity_decode( 'Te invit&#259;m la botez', ENT_QUOTES, 'UTF-8' );

        return [
            'title' => $title,
            'short_text' => $short_text,
            'description' => $description,
        ];
    }

    $names = trim( (string) ( $invitation['names'] ?? '' ) );
    $message = trim( (string) ( $invitation['message'] ?? '' ) );
    $fallback_text = (string) ( $share['fallback_message'] ?? 'Te invităm cu drag la evenimentul nostru! Vezi invitația aici:' );

    return [
        'title' => $names !== '' ? ( 'Invitație ' . $names ) : 'Invitație | Te Invit',
        'short_text' => $fallback_text,
        'description' => $message !== '' ? $message : ( $names !== '' ? ( 'Te invităm cu drag la evenimentul nostru, ' . $names . '.' ) : 'Te invităm cu drag la evenimentul nostru.' ),
    ];
}

function teinvit_share_get_social_image( $vertical ) {
    $vertical = function_exists( 'teinvit_normalize_vertical_key' ) ? teinvit_normalize_vertical_key( $vertical ) : 'wedding';
    $files = [
        'wedding' => 'social-preview-wedding-v1.png',
        'birthday' => 'social-preview-birthday-v1.png',
        'baptism' => 'social-preview-baptism-v1.png',
    ];

    $filename = $files[ $vertical ] ?? $files['wedding'];
    $base_path = defined( 'TEINVIT_CORE_PATH' ) ? trailingslashit( TEINVIT_CORE_PATH ) . 'infrastructure/assets/social/' : '';
    $base_url = defined( 'TEINVIT_CORE_URL' ) ? trailingslashit( TEINVIT_CORE_URL ) . 'infrastructure/assets/social/' : '';

    if ( $base_path !== '' && $base_url !== '' && file_exists( $base_path . $filename ) ) {
        return [
            'url' => teinvit_share_normalize_url( $base_url . $filename ),
            'width' => 1200,
            'height' => 630,
        ];
    }

    if ( $base_path !== '' && $base_url !== '' && file_exists( $base_path . $files['wedding'] ) ) {
        return [
            'url' => teinvit_share_normalize_url( $base_url . $files['wedding'] ),
            'width' => 1200,
            'height' => 630,
        ];
    }

    if ( $vertical === 'baptism' && defined( 'TEINVIT_BAPTISM_MODULE_URL' ) ) {
        return [
            'url' => teinvit_share_normalize_url( TEINVIT_BAPTISM_MODULE_URL . 'preview/social-preview-baptism-v3.png' ),
            'width' => 1200,
            'height' => 630,
        ];
    }

    if ( defined( 'TEINVIT_WEDDING_MODULE_URL' ) ) {
        return [
            'url' => teinvit_share_normalize_url( TEINVIT_WEDDING_MODULE_URL . 'assets/backgrounds/invn01.png' ),
            'width' => 0,
            'height' => 0,
        ];
    }

    return [
        'url' => '',
        'width' => 0,
        'height' => 0,
    ];
}

function teinvit_share_build_payload( $token, $vertical, array $invitation, $context = 'invitati', $url = '' ) {
    $vertical = function_exists( 'teinvit_normalize_vertical_key' ) ? teinvit_normalize_vertical_key( $vertical ) : 'wedding';
    $url = $url !== '' ? teinvit_share_normalize_url( $url ) : teinvit_share_guest_url( $token );
    $texts = teinvit_share_get_vertical_texts( $vertical, $invitation );
    $image = teinvit_share_get_social_image( $vertical );

    $title = trim( (string) ( $texts['title'] ?? '' ) );
    $text = trim( (string) ( $texts['short_text'] ?? $texts['text'] ?? '' ) );
    $description = trim( (string) ( $texts['description'] ?? $text ) );
    $message = trim( $text . ( $url !== '' ? "\n" . $url : '' ) );

    return [
        'vertical' => $vertical,
        'context' => (string) $context,
        'title' => $title,
        'text' => $text,
        'description' => $description,
        'message' => $message,
        'url' => $url,
        'image' => (string) ( $image['url'] ?? '' ),
        'image_width' => max( 0, (int) ( $image['width'] ?? 0 ) ),
        'image_height' => max( 0, (int) ( $image['height'] ?? 0 ) ),
    ];
}
