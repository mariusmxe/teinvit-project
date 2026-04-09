<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_baptism_field_ids() {
    return [
        'children' => '2d8d1ce',
        'show_parents' => '3ec4ca5',
        'mother' => '080362c',
        'father' => '23feecb',
        'show_godparents' => '1f32dd0',
        'godmother' => '7cff5b7',
        'godfather' => '5c0ffa4',
        'message' => '4c3baec',
        'show_religious' => '1eceab7',
        'religious_location' => '2f1dbe2',
        'religious_date' => '10adb6f',
        'religious_time' => '4c5ae13',
        'religious_waze' => '40ec33f',
        'show_party' => 'b4fca64',
        'party_location' => '3f4cc5a',
        'party_date' => 'c1aaf27',
        'party_time' => 'da5f0dc',
        'party_waze' => 'c95ca58',
        'theme' => '33fef24',
    ];
}

function teinvit_baptism_payload_builder( array $context = [] ) {
    $order = isset( $context['order'] ) && $context['order'] instanceof WC_Order ? $context['order'] : null;
    if ( ! $order ) {
        return [ 'invitation' => [], 'wapf_fields' => [] ];
    }

    $ids = teinvit_baptism_field_ids();
    $wapf = teinvit_extract_order_wapf_field_map( $order );
    $val = static function( $key ) use ( $wapf, $ids ) {
        $id = isset( $ids[ $key ] ) ? $ids[ $key ] : '';
        return $id !== '' && isset( $wapf[ $id ] ) ? trim( (string) $wapf[ $id ] ) : '';
    };
    $has = static function( $key ) use ( $val ) {
        $raw = strtolower( $val( $key ) );
        return $raw !== '' && ! in_array( $raw, [ '0', 'false', 'off', 'no' ], true );
    };

    $children = array_values( array_filter( array_map( 'trim', preg_split( '/\s*,\s*/', $val( 'children' ) ) ) ) );
    $children = array_slice( $children, 0, 3 );

    $theme = function_exists( 'teinvit_resolve_theme_key_from_wapf_value' ) ? teinvit_resolve_theme_key_from_wapf_value( $val( 'theme' ) ) : 'editorial';

    $parents_enabled = $has( 'show_parents' );
    $godparents_enabled = $has( 'show_godparents' );
    $religious_enabled = $has( 'show_religious' );
    $party_enabled = $has( 'show_party' );

    $format_dt = static function( $date, $time ) {
        $date = trim( (string) $date );
        $time = trim( (string) $time );
        if ( $date === '' ) {
            return '';
        }
        return $time !== '' ? ( $date . ' ora ' . $time ) : $date;
    };

    $invitation = [
        'vertical' => 'baptism',
        'theme' => $theme,
        'model_key' => 'invn01',
        'children' => $children,
        'headline' => implode( ' și ', $children ),
        'message' => $val( 'message' ),
        'parents' => [
            'enabled' => $parents_enabled,
            'mother' => $val( 'mother' ),
            'father' => $val( 'father' ),
        ],
        'godparents' => [
            'enabled' => $godparents_enabled,
            'godmother' => $val( 'godmother' ),
            'godfather' => $val( 'godfather' ),
        ],
        'events' => [
            'religious' => [
                'enabled' => $religious_enabled,
                'title' => 'Ceremonie religioasă',
                'loc' => $val( 'religious_location' ),
                'date' => $format_dt( $val( 'religious_date' ), $val( 'religious_time' ) ),
                'waze' => $val( 'religious_waze' ),
            ],
            'party' => [
                'enabled' => $party_enabled,
                'title' => 'Petrecere',
                'loc' => $val( 'party_location' ),
                'date' => $format_dt( $val( 'party_date' ), $val( 'party_time' ) ),
                'waze' => $val( 'party_waze' ),
            ],
        ],
    ];

    return [
        'invitation' => $invitation,
        'wapf_fields' => $wapf,
    ];
}

function teinvit_baptism_renderer( array $context = [] ) {
    $invitation = isset( $context['invitation'] ) && is_array( $context['invitation'] ) ? $context['invitation'] : [];
    $order = isset( $context['order'] ) && $context['order'] instanceof WC_Order ? $context['order'] : null;
    $is_pdf = ( isset( $context['render_context'] ) && $context['render_context'] === 'pdf' );

    $product_id = 0;
    if ( $order ) {
        $items = $order->get_items();
        if ( ! empty( $items ) ) {
            $item = reset( $items );
            $product_id = $item ? (int) $item->get_product_id() : 0;
        }
    }

    $background_url = function_exists( 'teinvit_get_product_background_url' ) ? teinvit_get_product_background_url( $product_id ) : '';
    $theme_class = function_exists( 'teinvit_theme_class_from_key' ) ? teinvit_theme_class_from_key( $invitation['theme'] ?? 'editorial' ) : 'theme-editorial-luxury';

    $events = [];
    if ( ! empty( $invitation['events']['religious']['enabled'] ) ) {
        $events[] = $invitation['events']['religious'];
    }
    if ( ! empty( $invitation['events']['party']['enabled'] ) ) {
        $events[] = $invitation['events']['party'];
    }

    $html = '';
    $html .= '<div class="teinvit-wedding teinvit-baptism">';
    $html .= '<div class="teinvit-page"><div class="teinvit-container"><div class="teinvit-preview">';
    if ( $background_url ) {
        $html .= '<img src="' . esc_url( $background_url ) . '" alt="" class="teinvit-bg" draggable="false">';
    }
    $html .= '<div class="teinvit-canvas canvas--spread ' . esc_attr( $theme_class ) . '">';
    $html .= '<div class="inv-names">' . esc_html( (string) ( $invitation['headline'] ?? '' ) ) . '</div>';
    $html .= '<div class="inv-divider" aria-hidden="true"></div>';

    if ( ! empty( $invitation['parents']['enabled'] ) ) {
        $html .= '<div class="inv-parents-wrapper"><div class="section-title">Împreună cu părinții</div><div class="inv-parents inv-parents-grid">';
        $html .= '<div class="inv-parent-col inv-parent-mireasa">' . esc_html( (string) ( $invitation['parents']['mother'] ?? '' ) ) . '</div>';
        $html .= '<div class="inv-parent-col inv-parent-mire">' . esc_html( (string) ( $invitation['parents']['father'] ?? '' ) ) . '</div>';
        $html .= '</div></div>';
    }

    if ( ! empty( $invitation['godparents']['enabled'] ) ) {
        $nasi = trim( implode( ' & ', array_filter( [ (string) ( $invitation['godparents']['godmother'] ?? '' ), (string) ( $invitation['godparents']['godfather'] ?? '' ) ] ) ) );
        $html .= '<div class="inv-nasi"><div class="section-title">Și cu nașii</div><div class="nasi-row">' . esc_html( $nasi ) . '</div></div>';
    }

    $html .= '<div class="inv-message">' . esc_html( (string) ( $invitation['message'] ?? '' ) ) . '</div>';

    if ( ! empty( $events ) ) {
        $top = array_slice( $events, 0, 1 );
        $bottom = array_slice( $events, 1, 1 );
        $render_event = static function( $event ) {
            $loc = esc_html( (string) ( $event['loc'] ?? '' ) );
            $date = esc_html( (string) ( $event['date'] ?? '' ) );
            $waze = esc_url( (string) ( $event['waze'] ?? '' ) );
            $out = '<div class="inv-event"><strong>' . esc_html( (string) ( $event['title'] ?? '' ) ) . '</strong>';
            $out .= '<div>' . $loc . '</div><div>' . $date . '</div>';
            if ( $waze !== '' ) {
                $out .= '<a href="' . $waze . '" target="_blank" rel="noopener">Waze</a>';
            }
            $out .= '</div>';
            return $out;
        };

        $html .= '<div class="inv-events"><div class="events-row top">';
        foreach ( $top as $event ) {
            $html .= $render_event( $event );
        }
        $html .= '</div><div class="events-row bottom">';
        foreach ( $bottom as $event ) {
            $html .= $render_event( $event );
        }
        $html .= '</div></div>';
    }

    $html .= '</div></div></div></div></div>';

    static $assets_loaded = false;
    if ( ! $assets_loaded ) {
        $assets_loaded = true;
        $html = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Source+Serif+4:wght@400&family=Raleway:wght@600&family=Parisienne&family=Crimson+Text:wght@400;600&family=DM+Sans:wght@600&family=Inter:wght@400;600&display=swap">'
            . '<link rel="stylesheet" href="' . esc_url( TEINVIT_WEDDING_MODULE_URL . ( $is_pdf ? 'preview/pdf.css' : 'preview/preview.css' ) ) . '">' . $html;
    }

    return $html;
}
