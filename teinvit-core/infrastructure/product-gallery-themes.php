<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_product_gallery_theme_registry() {
    $birthday = [
        'editorial-luxury'    => 'Editorial Luxury',
        'romantic-floral'     => 'Romantic Floral',
        'modern-minimal'      => 'Modern Minimal',
        'classic-elegant'     => 'Classic Elegant',
        'playful-confetti'    => 'Playful Confetti',
        'candy-pastel'        => 'Candy Pastel',
        'storybook-dream'     => 'Storybook Dream',
        'balloon-party'       => 'Balloon Party',
        'golden-celebration'  => 'Golden Celebration',
        'chic-blush'          => 'Chic Blush',
        'midnight-glam'       => 'Midnight Glam',
        'botanical-grace'     => 'Botanical Grace',
        'royal-blue'          => 'Royal Blue',
        'velvet-noir'         => 'Velvet Noir',
        'sunset-fiesta'       => 'Sunset Fiesta',
    ];

    $baptism = [
        'little-princess'  => 'Little Princess',
        'blush-angel'      => 'Blush Angel',
        'rosy-grace'       => 'Rosy Grace',
        'sweet-peony'      => 'Sweet Peony',
        'pink-cherub'      => 'Pink Cherub',
        'little-prince'    => 'Little Prince',
        'blue-angel'       => 'Blue Angel',
        'gentle-sailor'    => 'Gentle Sailor',
        'sky-blessing'     => 'Sky Blessing',
        'royal-baptism'    => 'Royal Baptism',
        'twin-harmony'     => 'Twin Harmony',
        'triple-blessing'  => 'Triple Blessing',
        'heavenly-stars'   => 'Heavenly Stars',
        'little-miracles'  => 'Little Miracles',
        'angelic-trio'     => 'Angelic Trio',
    ];

    $registry = [
        'wedding' => [
            'editorial' => [
                'vertical'           => 'wedding',
                'theme_key_internal' => 'editorial',
                'label'              => 'Editorial Luxury',
                'css_class'          => 'theme-editorial-luxury',
                'file_key'           => 'editorial-luxury',
                'is_active'          => true,
            ],
            'romantic' => [
                'vertical'           => 'wedding',
                'theme_key_internal' => 'romantic',
                'label'              => 'Romantic Floral',
                'css_class'          => 'theme-romantic-floral',
                'file_key'           => 'romantic-floral',
                'is_active'          => true,
            ],
            'modern' => [
                'vertical'           => 'wedding',
                'theme_key_internal' => 'modern',
                'label'              => 'Modern Minimal',
                'css_class'          => 'theme-modern-minimal',
                'file_key'           => 'modern-minimal',
                'is_active'          => true,
            ],
            'classic' => [
                'vertical'           => 'wedding',
                'theme_key_internal' => 'classic',
                'label'              => 'Classic Elegant',
                'css_class'          => 'theme-classic-elegant',
                'file_key'           => 'classic-elegant',
                'is_active'          => true,
            ],
        ],
        'birthday' => [],
        'baptism'  => [],
    ];

    foreach ( $birthday as $key => $label ) {
        $registry['birthday'][ $key ] = [
            'vertical'           => 'birthday',
            'theme_key_internal' => $key,
            'label'              => $label,
            'css_class'          => 'theme-birthday-' . $key,
            'file_key'           => $key,
            'is_active'          => true,
        ];
    }

    foreach ( $baptism as $key => $label ) {
        $registry['baptism'][ $key ] = [
            'vertical'           => 'baptism',
            'theme_key_internal' => $key,
            'label'              => $label,
            'css_class'          => 'theme-baptism-' . $key,
            'file_key'           => $key,
            'is_active'          => true,
        ];
    }

    return $registry;
}

function teinvit_product_gallery_themes_for_vertical( $vertical ) {
    $vertical = function_exists( 'teinvit_normalize_vertical_key' )
        ? teinvit_normalize_vertical_key( $vertical )
        : sanitize_key( (string) $vertical );
    $registry = teinvit_product_gallery_theme_registry();

    return isset( $registry[ $vertical ] ) && is_array( $registry[ $vertical ] )
        ? array_values( array_filter( $registry[ $vertical ], static function( $theme ) {
            return ! empty( $theme['is_active'] );
        } ) )
        : [];
}

function teinvit_product_gallery_resolve_theme( $vertical, $theme_key ) {
    $vertical = function_exists( 'teinvit_normalize_vertical_key' )
        ? teinvit_normalize_vertical_key( $vertical )
        : sanitize_key( (string) $vertical );
    $theme_key = sanitize_key( (string) $theme_key );
    $registry = teinvit_product_gallery_theme_registry();

    if ( isset( $registry[ $vertical ][ $theme_key ] ) && ! empty( $registry[ $vertical ][ $theme_key ]['is_active'] ) ) {
        return $registry[ $vertical ][ $theme_key ];
    }

    foreach ( $registry[ $vertical ] ?? [] as $theme ) {
        if ( ! empty( $theme['is_active'] ) && sanitize_key( (string) ( $theme['file_key'] ?? '' ) ) === $theme_key ) {
            return $theme;
        }
    }

    return null;
}
