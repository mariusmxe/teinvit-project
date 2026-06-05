<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_product_gallery_demo_payload( $vertical, array $theme ) {
    $vertical = function_exists( 'teinvit_normalize_vertical_key' )
        ? teinvit_normalize_vertical_key( $vertical )
        : sanitize_key( (string) $vertical );
    $theme_key = sanitize_key( (string) ( $theme['theme_key_internal'] ?? '' ) );

    if ( $vertical === 'birthday' ) {
        return [
            'vertical'          => 'birthday',
            'theme'             => $theme_key,
            'model_key'         => 'invn01',
            'celebrants'        => [ 'Eva' ],
            'name_units'        => [ 'Eva' ],
            'name_line_limit'   => 22,
            'headline'          => 'Eva',
            'headline_display'  => 'Eva implineste 7 ani',
            'age'               => [
                'enabled' => false,
                'value'   => '7',
                'line'    => 'Implinesc 7 ani!',
            ],
            'event_name'        => [
                'enabled' => false,
                'value'   => '',
                'line'    => '',
            ],
            'message'           => 'Te invitam cu drag sa sarbatorim impreuna o zi plina de bucurie.',
            'events'            => [
                'party' => [
                    'enabled' => true,
                    'title'   => 'PETRECERE',
                    'loc'     => 'Wonderland Events',
                    'weekday' => '',
                    'date'    => '10 Octombrie 2026 ora 17:00',
                    'waze'    => '',
                ],
            ],
        ];
    }

    if ( $vertical === 'baptism' ) {
        return [
            'vertical'         => 'baptism',
            'theme'            => $theme_key,
            'model_key'        => 'invn01',
            'children'         => [ 'Sofia Maria' ],
            'name_units'       => [ 'Sofia Maria' ],
            'name_line_limit'  => 22,
            'headline'         => 'Sofia Maria',
            'headline_display' => 'Sofia Maria',
            'message'          => 'Cu bucurie in suflet, va invitam sa fiti alaturi de noi la botezul micutei noastre.',
            'parents'          => [
                'enabled' => false,
                'title'   => 'IMPREUNA CU PARINTII',
                'mother'  => '',
                'father'  => '',
            ],
            'godparents'       => [
                'enabled'   => false,
                'title'     => 'SI CU NASII',
                'godmother' => '',
                'godfather' => '',
            ],
            'events'           => [
                'religious' => [
                    'enabled' => true,
                    'title'   => 'Slujba de botez',
                    'loc'     => 'Biserica Sfantul Gheorghe',
                    'date'    => '20 Septembrie 2026 ora 13:00',
                    'waze'    => '',
                ],
                'party'     => [
                    'enabled' => true,
                    'title'   => 'Petrecerea',
                    'loc'     => 'Restaurant Garden Events',
                    'date'    => '20 Septembrie 2026 ora 16:00',
                    'waze'    => '',
                ],
            ],
        ];
    }

    return [
        'theme'        => $theme_key !== '' ? $theme_key : 'editorial',
        'names'        => 'Andreea & Mihai',
        'message'      => 'Impreuna cu familiile lor, va invita sa le fiti alaturi in ziua nuntii.',
        'show_parents' => false,
        'parents'      => [
            'mireasa' => '',
            'mire'    => '',
        ],
        'show_nasi'    => false,
        'nasi'         => '',
        'events'       => [
            [
                'title' => 'Cununia religioasa',
                'loc'   => 'Biserica Sfantul Nicolae',
                'date'  => '15 August 2026 ora 16:00',
                'waze'  => '',
            ],
            [
                'title' => 'Petrecerea',
                'loc'   => 'Restaurant Maison Events',
                'date'  => '15 August 2026 ora 19:00',
                'waze'  => '',
            ],
        ],
    ];
}
