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
            'message'           => 'Ziua aceasta specială merită să fie plină de râsete, culoare și oameni frumoși. Vă invităm cu drag să sărbătorim împreună, să ne bucurăm și să creăm amintiri minunate, pline de voie bună.',
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
            'message'          => 'Un suflet mic aduce în viața noastră o bucurie imensă, iar botezul lui este un moment pe care vrem să îl trăim alături de voi. Vă așteptăm cu drag, emoție, lumină și multă iubire în suflet.',
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
        'message'      => 'Dragostea ne-a adus aici, iar bucuria va fi deplină doar dacă ne veți fi aproape. Vă invităm să sărbătorim împreună o zi plină de emoție, iubire și momente care vor rămâne în suflet.',
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
