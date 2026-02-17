<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TeInvit_Wedding_Preview_Renderer {

    public static function get_wapf_field_ids() {
        return [
            '6963a95e66425','6963aa37412e4','6963aa782092d','6967752ab511b','696445d6a9ce9','6964461d67da5','6964466afe4d1','69644689ee7e1','696446dfabb7b','696448f2ae763','69644a3415fb9','69644a5822ddc','69644d9e814ef','69644f2b40023','69644f85d865e','69644fd5c832b','69645088f4b73','696450ee17f9e','696450ffe7db4','69645104b39f4','696451a951467','696451d204a8a','696452023cdcd','696452478586d','8dec5e7','32f74cc','a4a0fca',
        ];
    }

    /* =====================================================
       PREVIEW – PAGINA DE PRODUS
    ===================================================== */
    public static function render_from_product( WC_Product $product ) {

        if ( ! $product instanceof WC_Product ) {
            return '<p>Previzualizare indisponibilă.</p>';
        }

        // CONTEXT PREVIEW (implicit)
        $GLOBALS['TEINVIT_RENDER_CONTEXT'] = 'preview';

        $invitation = [
            'theme'        => 'editorial',
            'names'        => '',
            'message'      => '',
            'show_parents' => false,
            'parents'      => [],
            'show_nasi'    => false,
            'nasi'         => [],
            'events'       => [],
        ];

        $GLOBALS['product']    = $product;
        $GLOBALS['invitation'] = $invitation;

        self::print_assets();

        ob_start();
        include TEINVIT_WEDDING_MODULE_PATH . 'templates/template.php';
        return ob_get_clean();
    }

    /* =====================================================
       PREVIEW – /i/{token}  &  PDF – /pdf/{token}
       🔑 renderer-ul rămâne AGNOSTIC
    ===================================================== */
    public static function render_from_order( WC_Order $order ) {

        if ( ! $order instanceof WC_Order ) {
            return '<p>Invitație indisponibilă.</p>';
        }

        $items = $order->get_items();
        if ( empty( $items ) ) {
            return '<p>Invitație indisponibilă.</p>';
        }

        /** @var WC_Order_Item_Product $item */
        $item = reset( $items );

        $wapf_data = self::normalize_wapf_meta( $item->get_meta( '_wapf_meta' ), $item, self::get_wapf_field_ids() );

        $invitation = self::build_invitation_from_wapf_data( $wapf_data );

        $GLOBALS['order']      = $order;
        $GLOBALS['invitation'] = $invitation;

        self::print_assets();

        ob_start();
        echo '<script>window.TEINVIT_INVITATION_DATA = ' . wp_json_encode( $invitation ) . ';</script>';
        include TEINVIT_WEDDING_MODULE_PATH . 'templates/template.php';
        return ob_get_clean();
    }

    public static function get_order_wapf_field_map( WC_Order $order ) {
        $items = $order->get_items();
        if ( empty( $items ) ) {
            return [];
        }

        $item = reset( $items );
        $normalized = self::normalize_wapf_meta( $item->get_meta( '_wapf_meta' ), $item, self::get_wapf_field_ids() );
        $map = [];
        foreach ( $normalized as $field ) {
            if ( empty( $field['id'] ) ) {
                continue;
            }
            $clean_id = self::clean_field_id( (string) $field['id'] );
            $map[ $clean_id ] = self::value_to_string( $field['value'] ?? '' );
        }

        return $map;
    }

    public static function get_order_invitation_data( WC_Order $order ) {
        $items = $order->get_items();
        if ( empty( $items ) ) {
            return [];
        }

        $item = reset( $items );
        $wapf_data = self::normalize_wapf_meta( $item->get_meta( '_wapf_meta' ), $item, self::get_wapf_field_ids() );

        return self::build_invitation_from_wapf_data( $wapf_data );
    }

    public static function render_from_invitation_data( array $invitation, $order_or_product = null ) {
        $defaults = [
            'theme'        => 'editorial',
            'names'        => '',
            'message'      => '',
            'show_parents' => false,
            'parents'      => [],
            'show_nasi'    => false,
            'nasi'         => [],
            'events'       => [],
        ];

        $invitation = wp_parse_args( $invitation, $defaults );

        $order = null;
        $product = null;
        if ( $order_or_product instanceof WC_Order ) {
            $order = $order_or_product;
            $GLOBALS['order'] = $order_or_product;
        } elseif ( $order_or_product instanceof WC_Product ) {
            $product = $order_or_product;
            $GLOBALS['product'] = $order_or_product;
        }
        $GLOBALS['invitation'] = $invitation;

        self::print_assets();

        ob_start();
        echo '<script>window.TEINVIT_INVITATION_DATA = ' . wp_json_encode( $invitation ) . ';</script>';
        include TEINVIT_WEDDING_MODULE_PATH . 'templates/template.php';
        return ob_get_clean();
    }

    private static function build_invitation_from_wapf_data( array $wapf_data ) {
        $invitation = [
            'theme'        => 'editorial',
            'names'        => '',
            'message'      => '',
            'show_parents' => false,
            'parents'      => [],
            'show_nasi'    => false,
            'nasi'         => [],
            'events'       => [],
        ];

        $get = function ( $id ) use ( $wapf_data ) {
            foreach ( $wapf_data as $f ) {
                if ( isset( $f['id'] ) && self::clean_field_id( (string) $f['id'] ) === $id ) {
                    return self::value_to_string( $f['value'] ?? '' );
                }
            }
            return '';
        };

        $has = function ( $id ) use ( $wapf_data ) {
            foreach ( $wapf_data as $f ) {
                if ( isset( $f['id'] ) && self::clean_field_id( (string) $f['id'] ) === $id ) {
                    return true;
                }
            }
            return false;
        };

        /* =========================
           DATE INVITAȚIE
        ========================= */
        $invitation['names'] = trim( implode( ' & ', array_filter([
            $get('6963a95e66425'),
            $get('6963aa37412e4'),
        ])));

        $invitation['message'] = $get('6963aa782092d');

        $theme_label = strtolower( $get('6967752ab511b') );
        if ( strpos( $theme_label, 'romantic' ) !== false ) {
            $invitation['theme'] = 'romantic';
        } elseif ( strpos( $theme_label, 'modern' ) !== false ) {
            $invitation['theme'] = 'modern';
        } elseif ( strpos( $theme_label, 'classic' ) !== false ) {
            $invitation['theme'] = 'classic';
        }

        if ( $has('696445d6a9ce9') ) {
            $invitation['show_parents'] = true;
            $invitation['parents'] = [
                'mireasa' => trim( implode( ' & ', array_filter([
                    $get('6964461d67da5'),
                    $get('6964466afe4d1'),
                ]))),
                'mire' => trim( implode( ' & ', array_filter([
                    $get('69644689ee7e1'),
                    $get('696446dfabb7b'),
                ]))),
            ];
        }

        if ( $has('696448f2ae763') ) {
            $invitation['show_nasi'] = true;
            $invitation['nasi'] = trim( implode( ' & ', array_filter([
                $get('69644a3415fb9'),
                $get('69644a5822ddc'),
            ])));
        }

        if ( $has('69644d9e814ef') ) {
            $invitation['events'][] = [
                'title' => 'Cununie civilă',
                'loc'   => $get('69644f2b40023'),
                'date'  => self::format_date_time_line( $get('69644f85d865e'), $get('8dec5e7') ),
                'waze'  => $get('69644fd5c832b'),
            ];
        }

        if ( $has('69645088f4b73') ) {
            $invitation['events'][] = [
                'title' => 'Ceremonie religioasă',
                'loc'   => $get('696450ee17f9e'),
                'date'  => self::format_date_time_line( $get('696450ffe7db4'), $get('32f74cc') ),
                'waze'  => $get('69645104b39f4'),
            ];
        }

        if ( $has('696451a951467') ) {
            $invitation['events'][] = [
                'title' => 'Petrecerea',
                'loc'   => $get('696451d204a8a'),
                'date'  => self::format_date_time_line( $get('696452023cdcd'), $get('a4a0fca') ),
                'waze'  => $get('696452478586d'),
            ];
        }

        return $invitation;
    }

    /**
     * Normalizează datele WAPF într-un format unitar:
     * [ [ 'id' => 'field_xxx', 'value' => '...' ], ... ]
     *
     * @param mixed $raw_wapf
     * @param WC_Order_Item_Product $item
     * @param array<int, string> $known_ids
     * @return array<int, array{id:string,value:string}>
     */
    private static function normalize_wapf_meta( $raw_wapf, WC_Order_Item_Product $item, array $known_ids ) {

        $normalized = [];
        $known_id_lookup = array_fill_keys( $known_ids, true );

        $push = function ( $id, $value ) use ( &$normalized, $known_id_lookup ) {
            $clean_id = self::clean_field_id( self::value_to_string( $id ) );
            if ( $clean_id === '' || ! isset( $known_id_lookup[ $clean_id ] ) ) {
                return;
            }

            $normalized[] = [
                'id'    => 'field_' . $clean_id,
                'value' => self::value_to_string( $value ),
            ];
        };

        $consume = function ( $node ) use ( &$consume, $push ) {
            if ( is_array( $node ) ) {
                if ( isset( $node['id'] ) ) {
                    $push( $node['id'], $node['value'] ?? '' );
                }

                foreach ( $node as $k => $v ) {
                    if ( is_string( $k ) ) {
                        if ( strpos( $k, 'field_' ) === 0 ) {
                            $push( $k, $v );
                        } elseif ( preg_match( '/^wapf\[field_([^\]]+)\]$/', $k, $m ) ) {
                            $push( $m[1], $v );
                        }
                    }

                    if ( is_array( $v ) || is_object( $v ) ) {
                        $consume( $v );
                    }
                }
                return;
            }

            if ( is_object( $node ) ) {
                $arr = json_decode( wp_json_encode( $node ), true );
                if ( is_array( $arr ) ) {
                    $consume( $arr );
                }
            }
        };

        if ( is_array( $raw_wapf ) || is_object( $raw_wapf ) ) {
            $consume( $raw_wapf );
        } elseif ( is_string( $raw_wapf ) && $raw_wapf !== '' ) {
            $decoded = json_decode( $raw_wapf, true );
            if ( json_last_error() === JSON_ERROR_NONE && ( is_array( $decoded ) || is_object( $decoded ) ) ) {
                $consume( $decoded );
            }

            if ( empty( $normalized ) && function_exists( 'maybe_unserialize' ) ) {
                $unserialized = maybe_unserialize( $raw_wapf );
                if ( is_array( $unserialized ) || is_object( $unserialized ) ) {
                    $consume( $unserialized );
                }
            }
        }

        if ( ! empty( $normalized ) ) {
            return $normalized;
        }

        foreach ( $item->get_meta_data() as $meta ) {
            $key = '';
            $val = '';

            if ( is_object( $meta ) ) {
                if ( isset( $meta->key ) ) {
                    $key = self::value_to_string( $meta->key );
                }
                if ( isset( $meta->value ) ) {
                    $val = $meta->value;
                }
            } elseif ( is_array( $meta ) ) {
                $key = self::value_to_string( $meta['key'] ?? '' );
                $val = $meta['value'] ?? '';
            }

            if ( $key === '' ) {
                continue;
            }

            if ( strpos( $key, 'field_' ) === 0 ) {
                $push( $key, $val );
                continue;
            }

            if ( preg_match( '/^wapf\[field_([^\]]+)\]$/', $key, $m ) ) {
                $push( $m[1], $val );
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     */
    private static function value_to_string( $value ) {

        if ( is_string( $value ) ) {
            return trim( $value );
        }

        if ( is_null( $value ) ) {
            return '';
        }

        if ( is_bool( $value ) ) {
            return $value ? '1' : '';
        }

        if ( is_int( $value ) || is_float( $value ) ) {
            return (string) $value;
        }

        if ( is_array( $value ) ) {
            $parts = [];
            array_walk_recursive( $value, function ( $leaf ) use ( &$parts ) {
                if ( is_scalar( $leaf ) || is_null( $leaf ) ) {
                    $str = self::value_to_string( $leaf );
                    if ( $str !== '' ) {
                        $parts[] = $str;
                    }
                }
            } );
            return implode( ' ', $parts );
        }

        if ( is_object( $value ) ) {
            return self::value_to_string( json_decode( wp_json_encode( $value ), true ) );
        }

        return '';
    }

    private static function clean_field_id( $raw_id ) {

        $id = self::value_to_string( $raw_id );
        if ( strpos( $id, 'wapf[field_' ) === 0 && substr( $id, -1 ) === ']' ) {
            $id = substr( $id, 11, -1 );
        }

        if ( strpos( $id, 'field_' ) === 0 ) {
            $id = substr( $id, 6 );
        }

        if ( strpos( $id, '_' ) === 0 ) {
            $id = substr( $id, 1 );
        }

        return trim( $id );
    }

    private static function normalize_display_date( $date ) {

        $date = self::value_to_string( $date );
        if ( $date === '' ) {
            return '';
        }

        // WAPF standard (mm-dd-yyyy) -> dd-mm-yyyy
        if ( preg_match( '/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])-(\d{4})$/', $date, $m ) ) {
            return $m[2] . '-' . $m[1] . '-' . $m[3];
        }

        // Defensive: yyyy-mm-dd -> dd-mm-yyyy
        if ( preg_match( '/^(\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $date, $m ) ) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        // Already dd-mm-yyyy -> keep as-is
        if ( preg_match( '/^(0[1-9]|[12]\d|3[01])-(0[1-9]|1[0-2])-(\d{4})$/', $date ) ) {
            return $date;
        }

        return $date;
    }

    private static function format_date_time_line( $date, $time ) {

        $date = self::normalize_display_date( $date );
        $time = self::value_to_string( $time );

        if ( $date === '' ) {
            return '';
        }

        if ( preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
            return $date . ' ora ' . $time;
        }

        return $date;
    }

    /* =====================================================
       ASSETS – CONTEXT AWARE (SINGURUL LOC)
    ===================================================== */
    private static function print_assets() {

        static $loaded = false;
        if ( $loaded ) {
            return;
        }
        $loaded = true;

        /* ===============================
           FONTURI – COMUNE
        =============================== */
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?'
            . 'family=Playfair+Display:wght@600&'
            . 'family=Source+Serif+4:wght@400&'
            . 'family=Raleway:wght@600&'
            . 'family=Parisienne&'
            . 'family=Crimson+Text:wght@400;600&'
            . 'family=DM+Sans:wght@600&'
            . 'family=Inter:wght@400;600&display=swap">';

        /* ===============================
           CONTEXT PDF
        =============================== */
        if ( isset( $GLOBALS['TEINVIT_RENDER_CONTEXT'] ) && $GLOBALS['TEINVIT_RENDER_CONTEXT'] === 'pdf' ) {

            echo '<link rel="stylesheet" href="' . esc_url(
                TEINVIT_WEDDING_MODULE_URL . 'preview/pdf.css'
            ) . '">';

            echo '<script>window.__TEINVIT_PDF_MODE__ = true;</script>';

            echo '<script src="' . esc_url(
                TEINVIT_WEDDING_MODULE_URL . 'preview/preview.js'
            ) . '"></script>';

            return;
        }

        /* ===============================
           CONTEXT PREVIEW
        =============================== */
        echo '<link rel="stylesheet" href="' . esc_url(
            TEINVIT_WEDDING_MODULE_URL . 'preview/preview.css'
        ) . '">';

        echo '<script src="' . esc_url(
            TEINVIT_WEDDING_MODULE_URL . 'preview/preview.js'
        ) . '"></script>';
    }
}
