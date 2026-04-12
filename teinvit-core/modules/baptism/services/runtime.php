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

function teinvit_baptism_theme_value_map() {
    return [
        '58a6u' => 'editorial',
        'trs1l' => 'romantic',
        'pu7cd' => 'modern',
        'h1ww0' => 'classic',
    ];
}

function teinvit_baptism_resolve_theme_key( $raw_theme ) {
    $raw_theme = trim( (string) $raw_theme );
    $map = teinvit_baptism_theme_value_map();
    if ( $raw_theme !== '' && isset( $map[ $raw_theme ] ) ) {
        return $map[ $raw_theme ];
    }

    return function_exists( 'teinvit_resolve_theme_key_from_wapf_value' )
        ? teinvit_resolve_theme_key_from_wapf_value( $raw_theme, $map )
        : 'editorial';
}

function teinvit_baptism_theme_class( $theme_key ) {
    $theme_key = strtolower( trim( (string) $theme_key ) );
    $shared = function_exists( 'teinvit_theme_class_from_key' ) ? teinvit_theme_class_from_key( $theme_key ) : 'theme-editorial-luxury';

    $vertical = 'theme-baptism-editorial';
    if ( $theme_key === 'romantic' ) {
        $vertical = 'theme-baptism-romantic';
    } elseif ( $theme_key === 'modern' ) {
        $vertical = 'theme-baptism-modern';
    } elseif ( $theme_key === 'classic' ) {
        $vertical = 'theme-baptism-classic';
    }

    return trim( $vertical . ' ' . $shared );
}

function teinvit_baptism_payload_from_wapf_map( array $wapf, array $context = [] ) {
    $ids = teinvit_baptism_field_ids();
    $product_id = isset( $context['product_id'] ) ? (int) $context['product_id'] : 0;
    $defs = $product_id > 0 && function_exists( 'teinvit_get_wapf_defs_for_product' ) ? teinvit_get_wapf_defs_for_product( $product_id ) : [];

    $label_lookup = [];
    foreach ( $defs as $def ) {
        if ( ! is_array( $def ) ) {
            continue;
        }
        $id = function_exists( 'teinvit_normalize_wapf_field_id' ) ? teinvit_normalize_wapf_field_id( $def['id'] ?? '' ) : trim( (string) ( $def['id'] ?? '' ) );
        $label = strtolower( trim( (string) ( $def['label'] ?? '' ) ) );
        if ( $id === '' || $label === '' ) {
            continue;
        }
        $label_lookup[ $id ] = $label;
    }

    $fallback_labels = [
        'show_parents' => [ 'afișează numele părinților', 'afiseaza numele parintilor' ],
        'mother' => [ 'nume mamă', 'nume mama' ],
        'father' => [ 'nume tată', 'nume tata' ],
        'show_godparents' => [ 'afișează numele nașilor', 'afiseaza numele nasilor' ],
        'godmother' => [ 'nume nașă', 'nume nasa' ],
        'godfather' => [ 'nume naș', 'nume nas' ],
        'show_religious' => [ 'afișează locația ceremonie religioase', 'afiseaza locatia ceremonie religioase' ],
        'religious_location' => [ 'denumire locației', 'denumire locatiei' ],
        'religious_date' => [ 'data' ],
        'religious_time' => [ 'ora' ],
        'religious_waze' => [ 'link waze' ],
        'show_party' => [ 'afișează locația petrecerii', 'afiseaza locatia petrecerii' ],
        'party_location' => [ 'denumire locației', 'denumire locatiei' ],
        'party_date' => [ 'data' ],
        'party_time' => [ 'ora' ],
        'party_waze' => [ 'link waze' ],
    ];

    $resolve_ids = static function( $key ) use ( $ids, $label_lookup, $fallback_labels ) {
        $resolved = [];
        if ( isset( $ids[ $key ] ) && $ids[ $key ] !== '' ) {
            $resolved[] = (string) $ids[ $key ];
        }
        if ( ! isset( $fallback_labels[ $key ] ) ) {
            return $resolved;
        }

        $needles = $fallback_labels[ $key ];
        foreach ( $label_lookup as $id => $label ) {
            foreach ( $needles as $needle ) {
                if ( strpos( $label, strtolower( $needle ) ) !== false ) {
                    $resolved[] = (string) $id;
                    break;
                }
            }
        }

        return array_values( array_unique( array_filter( $resolved ) ) );
    };

    $collect_matches_for_ids = static function( array $candidate_ids ) use ( $wapf ) {
        $matches = [];
        foreach ( $wapf as $field_id => $raw_value ) {
            $field_id = trim( (string) $field_id );
            foreach ( $candidate_ids as $id ) {
                if ( $field_id === $id || strpos( $field_id, $id . '_' ) === 0 || strpos( $field_id, $id . '-' ) === 0 ) {
                    $parts = array_values( array_filter( array_map( 'trim', explode( ',', (string) $raw_value ) ) ) );
                    if ( ! empty( $parts ) ) {
                        $matches = array_merge( $matches, $parts );
                    }
                    break;
                }
            }
        }
        return $matches;
    };

    $val = static function( $key ) use ( $ids, $resolve_ids, $collect_matches_for_ids ) {
        $explicit_id = isset( $ids[ $key ] ) ? trim( (string) $ids[ $key ] ) : '';
        if ( $explicit_id !== '' ) {
            $matches = $collect_matches_for_ids( [ $explicit_id ] );
            if ( ! empty( $matches ) ) {
                return implode( ', ', array_values( array_unique( $matches ) ) );
            }
        }

        $candidate_ids = array_values( array_filter( $resolve_ids( $key ), static function( $id ) use ( $explicit_id ) {
            return $id !== '' && $id !== $explicit_id;
        } ) );
        if ( empty( $candidate_ids ) ) {
            return '';
        }

        $matches = $collect_matches_for_ids( $candidate_ids );

        if ( empty( $matches ) ) {
            return '';
        }

        return implode( ', ', array_values( array_unique( $matches ) ) );
    };
    $has = static function( $key ) use ( $val ) {
        $raw = strtolower( $val( $key ) );
        return $raw !== '' && ! in_array( $raw, [ '0', 'false', 'off', 'no' ], true );
    };

    $children = array_values( array_filter( array_map( 'trim', preg_split( '/\s*,\s*/', $val( 'children' ) ) ), static function( $name ) {
        $name = trim( (string) $name );
        if ( $name === '' ) {
            return false;
        }
        if ( preg_match( '/^\d+$/', $name ) ) {
            return false;
        }
        return true;
    } ) );
    $children = array_slice( $children, 0, 3 );
    $theme = teinvit_baptism_resolve_theme_key( $val( 'theme' ) );

    $format_dt = static function( $date, $time ) {
        $date = trim( (string) $date );
        $time = trim( (string) $time );
        if ( $date === '' ) {
            return '';
        }
        return $time !== '' ? ( $date . ' ora ' . $time ) : $date;
    };

    $message_raw = $val( 'message' );
    $message = function_exists( 'mb_substr' ) ? mb_substr( $message_raw, 0, 255 ) : substr( $message_raw, 0, 255 );

    $headline = '';
    $count = count( $children );
    if ( $count === 1 ) {
        $headline = $children[0];
    } elseif ( $count === 2 ) {
        $headline = $children[0] . ' și ' . $children[1];
    } elseif ( $count > 2 ) {
        $headline = implode( ' & ', array_slice( $children, 0, -1 ) ) . ' și ' . $children[ $count - 1 ];
    }

    return [
        'invitation' => [
            'vertical' => 'baptism',
            'theme' => $theme,
            'model_key' => 'invn01',
            'children' => $children,
            'headline' => $headline,
            'message' => $message,
            'parents' => [
                'enabled' => $has( 'show_parents' ),
                'mother' => $val( 'mother' ),
                'father' => $val( 'father' ),
            ],
            'godparents' => [
                'enabled' => $has( 'show_godparents' ),
                'godmother' => $val( 'godmother' ),
                'godfather' => $val( 'godfather' ),
            ],
            'events' => [
                'religious' => [
                    'enabled' => $has( 'show_religious' ),
                    'title' => 'Ceremonie religioasă',
                    'loc' => $val( 'religious_location' ),
                    'date' => $format_dt( $val( 'religious_date' ), $val( 'religious_time' ) ),
                    'waze' => $val( 'religious_waze' ),
                ],
                'party' => [
                    'enabled' => $has( 'show_party' ),
                    'title' => 'Petrecere',
                    'loc' => $val( 'party_location' ),
                    'date' => $format_dt( $val( 'party_date' ), $val( 'party_time' ) ),
                    'waze' => $val( 'party_waze' ),
                ],
            ],
        ],
        'wapf_fields' => $wapf,
    ];
}

function teinvit_baptism_payload_builder( array $context = [] ) {
    $order = isset( $context['order'] ) && $context['order'] instanceof WC_Order ? $context['order'] : null;
    if ( ! $order ) {
        return [ 'invitation' => [], 'wapf_fields' => [] ];
    }

    $wapf = teinvit_extract_order_wapf_field_map( $order );
    return teinvit_baptism_payload_from_wapf_map( $wapf );
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
    if ( $product_id <= 0 ) {
        $product_id = isset( $context['product_id'] ) ? (int) $context['product_id'] : 0;
    }

    $background_url = function_exists( 'teinvit_get_product_background_url' ) ? teinvit_get_product_background_url( $product_id ) : '';
    $theme_class = teinvit_baptism_theme_class( $invitation['theme'] ?? 'editorial' );

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
        $mother = trim( (string) ( $invitation['parents']['mother'] ?? '' ) );
        $father = trim( (string) ( $invitation['parents']['father'] ?? '' ) );
        $father_display = ( $mother !== '' && $father !== '' ) ? ( '& ' . $father ) : $father;
        $html .= '<div class="inv-parents-wrapper"><div class="section-title">Împreună cu părinții</div><div class="inv-parents inv-parents-grid">';
        $html .= '<div class="inv-parent-col inv-parent-mireasa">' . esc_html( $mother ) . '</div>';
        $html .= '<div class="inv-parent-col inv-parent-mire">' . esc_html( $father_display ) . '</div>';
        $html .= '</div></div>';
    }

    if ( ! empty( $invitation['godparents']['enabled'] ) ) {
        $nasi = trim( implode( ' & ', array_filter( [ (string) ( $invitation['godparents']['godmother'] ?? '' ), (string) ( $invitation['godparents']['godfather'] ?? '' ) ] ) ) );
        $html .= '<div class="inv-nasi"><div class="section-title">Și cu nașii</div><div class="nasi-row">' . esc_html( $nasi ) . '</div></div>';
    }

    $html .= '<div class="inv-message">' . esc_html( (string) ( $invitation['message'] ?? '' ) ) . '</div>';

    if ( ! empty( $events ) ) {
        $render_event = static function( $event ) {
            $loc = esc_html( (string) ( $event['loc'] ?? '' ) );
            $date = esc_html( (string) ( $event['date'] ?? '' ) );
            $waze = esc_url( (string) ( $event['waze'] ?? '' ) );
            $out = '<div class="inv-event"><strong>' . esc_html( (string) ( $event['title'] ?? '' ) ) . '</strong>';
            $out .= '<div>' . $loc . '</div><div>' . $date . '</div>';
            if ( $waze !== '' ) {
                $out .= '<a href="' . $waze . '" target="_blank" rel="noopener">Deschide în Waze</a>';
            }
            $out .= '</div>';
            return $out;
        };

        $html .= '<div class="inv-events"><div class="events-row top">';
        foreach ( $events as $event ) {
            $html .= $render_event( $event );
        }
        $html .= '</div><div class="events-row bottom"></div></div>';
    }

    $html .= '</div></div></div></div></div>';
    if ( $is_pdf ) {
        $html .= '<script>window.__TEINVIT_PDF_READY__ = true;</script>';
    }

    static $assets_loaded = false;
    if ( ! $assets_loaded ) {
        $assets_loaded = true;
        $html = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Source+Serif+4:wght@400&family=Raleway:wght@600&family=Parisienne&family=Crimson+Text:wght@400;600&family=DM+Sans:wght@600&family=Inter:wght@400;600&display=swap">'
            . '<link rel="stylesheet" href="' . esc_url( TEINVIT_WEDDING_MODULE_URL . ( $is_pdf ? 'preview/pdf.css' : 'preview/preview.css' ) ) . '">'
            . '<link rel="stylesheet" href="' . esc_url( TEINVIT_BAPTISM_MODULE_URL . 'preview/themes.css' ) . '">' . $html;
    }

    if ( ! $is_pdf ) {
        $html .= '<script>window.TEINVIT_INVITATION_DATA = ' . wp_json_encode( $invitation ) . ';</script>';
        $html .= '<script>window.__TEINVIT_PDF_MODE__ = false;</script>';
        $html .= '<script>window.teinvitBaptismPreviewConfig = ' . wp_json_encode( [ 'previewBuildUrl' => esc_url_raw( rest_url( 'teinvit/v2/preview/build' ) ) ] ) . ';</script>';
        $is_product_page = function_exists( 'is_product' ) ? (bool) is_product() : false;
        if ( ! $is_product_page ) {
            $html .= '<script src="' . esc_url( TEINVIT_BAPTISM_MODULE_URL . 'preview/preview.js' ) . '"></script>';
        }
    }

    return $html;
}
