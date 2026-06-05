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
            'celebrants'        => [ 'Alex' ],
            'name_units'        => [ 'Alex' ],
            'name_line_limit'   => 22,
            'headline'          => 'Alex',
            'headline_display'  => 'Alex',
            'age'               => [
                'enabled' => true,
                'value'   => '23',
                'line'    => 'Împlinesc 23 de ani!',
            ],
            'event_name'        => [
                'enabled' => true,
                'value'   => 'Prima Mega Petrecere',
                'line'    => 'Te invită la Prima Mega Petrecere',
            ],
            'message'           => 'Hai să sărbătorim împreună o zi plină de bucurie.',
            'events'            => [
                'party' => [
                    'enabled' => true,
                    'title'   => 'PETRECERE',
                    'loc'     => 'Wonderland Events',
                    'weekday' => 'MIERCURI',
                    'date'    => '07-07-2027 ora 18:00',
                    'waze'    => 'https://waze.com/ul?q=Wonderland%20Events&navigate=yes',
                ],
            ],
        ];
    }

    if ( $vertical === 'baptism' ) {
        return [
            'vertical'         => 'baptism',
            'theme'            => $theme_key,
            'model_key'        => 'invn01',
            'children'         => [ 'Marius Claudiu' ],
            'name_units'       => [ 'Marius Claudiu' ],
            'name_line_limit'  => 22,
            'headline'         => 'Marius Claudiu',
            'headline_display' => 'Marius Claudiu',
            'message'          => 'Cu bucurie, vă invităm să fiți alături de noi la botezul lui Marius Claudiu.',
            'parents'          => [
                'enabled' => true,
                'title'   => 'ÎMPREUNĂ CU PĂRINȚII',
                'mother'  => 'Elena Munteanu',
                'father'  => 'Marius Munteanu',
            ],
            'godparents'       => [
                'enabled'   => true,
                'title'     => 'ȘI CU NAȘII',
                'godmother' => 'Rodica Cîrjan',
                'godfather' => 'Nicolae Cîrjan',
            ],
            'events'           => [
                'religious' => [
                    'enabled' => true,
                    'title'   => 'Ceremonie religioasă',
                    'loc'     => 'Biserica Sfânta Treime',
                    'date'    => '07-07-2027 ora 13:00',
                    'waze'    => 'https://waze.com/ul?q=Biserica%20Sfanta%20Treime&navigate=yes',
                ],
                'party'     => [
                    'enabled' => true,
                    'title'   => 'Petrecere',
                    'loc'     => 'Garden Events',
                    'date'    => '07-07-2027 ora 16:00',
                    'waze'    => 'https://waze.com/ul?q=Garden%20Events&navigate=yes',
                ],
            ],
        ];
    }

    return [
        'theme'        => $theme_key !== '' ? $theme_key : 'editorial',
        'names'        => 'Ana & Matei',
        'message'      => 'Cu bucurie, vă invităm să ne fiți alături în ziua nunții.',
        'show_parents' => true,
        'parents'      => [
            'mireasa' => 'Maria Popescu & Ion Popescu',
            'mire'    => 'Elena Ionescu & Paul Ionescu',
        ],
        'show_nasi'    => true,
        'nasi'         => 'Ioana Marinescu & Victor Marinescu',
        'events'       => [
            [
                'title' => 'Cununie civilă',
                'loc'   => 'Primăria Sector 1',
                'date'  => '07-07-2027 ora 12:30',
                'waze'  => 'https://waze.com/ul?q=Primaria%20Sector%201&navigate=yes',
            ],
            [
                'title' => 'Ceremonie religioasă',
                'loc'   => 'Biserica Sf. Nicolae',
                'date'  => '07-07-2027 ora 16:00',
                'waze'  => 'https://waze.com/ul?q=Biserica%20Sf%20Nicolae&navigate=yes',
            ],
            [
                'title' => 'Petrecerea',
                'loc'   => 'Maison Events',
                'date'  => '07-07-2027 ora 19:00',
                'waze'  => 'https://waze.com/ul?q=Maison%20Events&navigate=yes',
            ],
        ],
    ];
}
