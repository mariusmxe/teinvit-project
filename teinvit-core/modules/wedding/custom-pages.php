<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_model_background_url( $model_key ) {
    $model_key = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $model_key );
    if ( $model_key === '' ) {
        $model_key = 'invn01';
    }

    $base = TEINVIT_WEDDING_MODULE_PATH . 'assets/backgrounds/' . $model_key;
    if ( file_exists( $base . '.jpg' ) ) {
        return TEINVIT_WEDDING_MODULE_URL . 'assets/backgrounds/' . $model_key . '.jpg';
    }
    if ( file_exists( $base . '.png' ) ) {
        return TEINVIT_WEDDING_MODULE_URL . 'assets/backgrounds/' . $model_key . '.png';
    }

    return TEINVIT_WEDDING_MODULE_URL . 'assets/backgrounds/invn01.png';
}

function teinvit_is_modular_snapshot_complete( $snapshot ) {
    if ( ! is_array( $snapshot ) ) {
        return false;
    }

    $invitation = isset( $snapshot['invitation'] ) && is_array( $snapshot['invitation'] ) ? $snapshot['invitation'] : [];
    $wapf_fields = isset( $snapshot['wapf_fields'] ) && is_array( $snapshot['wapf_fields'] ) ? $snapshot['wapf_fields'] : [];

    return ! empty( $invitation ) && ! empty( $wapf_fields );
}

function teinvit_migrate_legacy_active_to_modular( $token ) {
    global $wpdb;

    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return false;
    }

    $inv = teinvit_get_invitation( $token );
    if ( ! $inv ) {
        return false;
    }

    $active_snapshot = teinvit_get_active_snapshot( $token );
    $active_payload = $active_snapshot ? json_decode( (string) $active_snapshot['snapshot'], true ) : [];
    if ( teinvit_is_modular_snapshot_complete( $active_payload ) ) {
        return true;
    }

    if ( ! function_exists( 'teinvit_tables' ) ) {
        return false;
    }

    $legacy = teinvit_tables();
    $legacy_settings = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$legacy['settings']} WHERE token = %s LIMIT 1", $token ), ARRAY_A );
    if ( ! $legacy_settings ) {
        return false;
    }

    $legacy_version = (int) ( $legacy_settings['active_version'] ?? 0 );
    $legacy_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$legacy['versions']} WHERE token = %s AND version = %d LIMIT 1", $token, $legacy_version ), ARRAY_A );
    if ( ! $legacy_row || empty( $legacy_row['data_json'] ) ) {
        return false;
    }

    $legacy_payload = json_decode( (string) $legacy_row['data_json'], true );
    if ( ! is_array( $legacy_payload ) ) {
        return false;
    }

    $snapshot = [
        'invitation' => isset( $legacy_payload['invitation'] ) && is_array( $legacy_payload['invitation'] ) ? $legacy_payload['invitation'] : [],
        'wapf_fields' => isset( $legacy_payload['wapf_fields'] ) && is_array( $legacy_payload['wapf_fields'] ) ? $legacy_payload['wapf_fields'] : [],
        'meta' => [
            'migrated_from_legacy' => true,
            'legacy_version' => $legacy_version,
            'order_id' => (int) ( $inv['order_id'] ?? 0 ),
        ],
    ];

    if ( ! teinvit_is_modular_snapshot_complete( $snapshot ) ) {
        return false;
    }

    $t = teinvit_db_tables();
    $wpdb->insert( $t['versions'], [
        'token' => $token,
        'snapshot' => wp_json_encode( $snapshot ),
        'created_at' => current_time( 'mysql' ),
    ] );

    $new_version_id = (int) $wpdb->insert_id;
    if ( $new_version_id <= 0 ) {
        return false;
    }

    teinvit_save_invitation_config( $token, [ 'active_version_id' => $new_version_id ] );
    return true;
}

function teinvit_get_modular_active_payload( $token ) {
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return [];
    }

    $active_snapshot = teinvit_get_active_snapshot( $token );
    $payload = $active_snapshot ? json_decode( (string) $active_snapshot['snapshot'], true ) : [];
    if ( teinvit_is_modular_snapshot_complete( $payload ) ) {
        return $payload;
    }

    teinvit_migrate_legacy_active_to_modular( $token );
    $active_snapshot = teinvit_get_active_snapshot( $token );
    $payload = $active_snapshot ? json_decode( (string) $active_snapshot['snapshot'], true ) : [];

    return is_array( $payload ) ? $payload : [];
}

function teinvit_get_wapf_defs_for_product( $product_or_id ) {
    $product = $product_or_id instanceof WC_Product ? $product_or_id : wc_get_product( (int) $product_or_id );
    if ( ! $product ) {
        return [];
    }

    if ( function_exists( 'wapf_get_field_groups_of_product' ) ) {
        $groups = wapf_get_field_groups_of_product( $product );
        if ( is_array( $groups ) && ! empty( $groups ) ) {
            $defs = [];
            foreach ( $groups as $group ) {
                $fields = is_object( $group ) && isset( $group->fields ) ? (array) $group->fields : [];
                foreach ( $fields as $field ) {
                    if ( ! is_object( $field ) ) {
                        continue;
                    }
                    $id = function_exists( 'teinvit_normalize_wapf_field_id' ) ? teinvit_normalize_wapf_field_id( $field->id ?? '' ) : trim( (string) ( $field->id ?? '' ) );
                    if ( $id === '' ) {
                        continue;
                    }

                    $choices = [];
                    if ( isset( $field->options ) && is_array( $field->options ) && isset( $field->options['choices'] ) && is_array( $field->options['choices'] ) ) {
                        $choices = $field->options['choices'];
                    }

                    $options = [];
                    foreach ( $choices as $choice ) {
                        $choice_arr = is_object( $choice ) ? json_decode( wp_json_encode( $choice ), true ) : ( is_array( $choice ) ? $choice : [] );
                        if ( empty( $choice_arr ) ) {
                            continue;
                        }
                        $label = trim( (string) ( $choice_arr['label'] ?? $choice_arr['text'] ?? $choice_arr['value'] ?? $choice_arr['slug'] ?? $choice_arr['id'] ?? $choice_arr['key'] ?? '' ) );
                        $value = trim( (string) ( $choice_arr['value'] ?? $choice_arr['slug'] ?? $choice_arr['id'] ?? $choice_arr['key'] ?? $choice_arr['code'] ?? $label ) );
                        if ( $label === '' && $value === '' ) {
                            continue;
                        }
                        $options[] = [
                            'value' => $value !== '' ? $value : $label,
                            'label' => $label !== '' ? $label : $value,
                        ];
                    }

                    $defs[] = [
                        'id' => $id,
                        'label' => (string) ( $field->label ?? ( 'Field ' . $id ) ),
                        'type' => (string) ( $field->type ?? 'text' ),
                        'options' => $options,
                        'conditions' => [],
                        'order' => isset( $field->order ) ? (int) $field->order : 0,
                    ];
                }
            }

            if ( ! empty( $defs ) ) {
                return $defs;
            }
        }
    }

    return function_exists( 'teinvit_extract_wapf_definitions_from_product' ) ? teinvit_extract_wapf_definitions_from_product( $product->get_id() ) : [];
}

function teinvit_build_invitation_from_wapf_map_canonical( array $wapf_map, array $defs = [] ) {
    $product_defs = is_array( $defs ) && ! empty( $defs ) ? $defs : [];
    $option_maps = teinvit_extract_wapf_option_maps_from_product( 0 );

    if ( ! empty( $product_defs ) ) {
        $option_maps = [];
        foreach ( $product_defs as $def ) {
            if ( ! is_array( $def ) ) {
                continue;
            }
            $field_id = function_exists( 'teinvit_normalize_wapf_field_id' ) ? teinvit_normalize_wapf_field_id( $def['id'] ?? '' ) : trim( (string) ( $def['id'] ?? '' ) );
            if ( $field_id === '' ) {
                continue;
            }
            $type = strtolower( trim( (string) ( $def['type'] ?? '' ) ) );
            $by_value = [];
            $by_label = [];
            foreach ( (array) ( $def['options'] ?? [] ) as $opt ) {
                if ( ! is_array( $opt ) ) {
                    continue;
                }
                $label = trim( (string) ( $opt['label'] ?? $opt['text'] ?? $opt['value'] ?? $opt['slug'] ?? $opt['id'] ?? $opt['key'] ?? '' ) );
                $value = trim( (string) ( $opt['value'] ?? $opt['slug'] ?? $opt['id'] ?? $opt['key'] ?? $opt['code'] ?? $label ) );
                if ( $value !== '' ) {
                    $by_value[ $value ] = $label;
                }
                if ( $label !== '' ) {
                    $by_label[ strtolower( $label ) ] = $value !== '' ? $value : $label;
                }
            }
            $option_maps[ $field_id ] = [
                'type' => $type,
                'by_value' => $by_value,
                'by_label' => $by_label,
            ];
        }
    }

    $normalized = teinvit_normalize_wapf_map_with_option_maps( $wapf_map, $option_maps );

    $theme_map = [];
    if ( isset( $option_maps['6967752ab511b']['by_value'] ) && is_array( $option_maps['6967752ab511b']['by_value'] ) ) {
        $theme_map = $option_maps['6967752ab511b']['by_value'];
    }

    $invitation = teinvit_build_invitation_from_wapf_map( $normalized, $theme_map );

    return [
        'invitation' => $invitation,
        'wapf_map' => $normalized,
        'theme_raw' => isset( $normalized['6967752ab511b'] ) ? (string) $normalized['6967752ab511b'] : '',
        'theme_resolved' => isset( $invitation['theme'] ) ? (string) $invitation['theme'] : 'editorial',
    ];
}

function teinvit_ensure_active_snapshot_payload( $token, $order = null ) {
    $payload = teinvit_get_modular_active_payload( $token );
    if ( teinvit_is_modular_snapshot_complete( $payload ) ) {
        return $payload;
    }

    $order_id = 0;
    if ( ! $order && function_exists( 'teinvit_get_order_id_by_token' ) ) {
        $order_id = (int) teinvit_get_order_id_by_token( $token );
        $order = $order_id ? wc_get_order( $order_id ) : null;
    } elseif ( $order instanceof WC_Order ) {
        $order_id = (int) $order->get_id();
    }

    if ( ! $order || $order_id <= 0 ) {
        return [];
    }

    teinvit_seed_invitation_if_missing( $token, $order_id );

    $payload = teinvit_get_modular_active_payload( $token );
    if ( teinvit_is_modular_snapshot_complete( $payload ) ) {
        return $payload;
    }

    $order_wapf = TeInvit_Wedding_Preview_Renderer::get_order_wapf_field_map( $order );
    $defs = teinvit_get_wapf_defs_for_product( teinvit_get_order_primary_product_id( $order ) );
    $built = teinvit_build_invitation_from_wapf_map_canonical( $order_wapf, $defs );

    global $wpdb;
    $t = teinvit_db_tables();
    $wpdb->insert( $t['versions'], [
        'token' => $token,
        'snapshot' => wp_json_encode( [
            'invitation' => $built['invitation'],
            'wapf_fields' => $built['wapf_map'],
            'meta' => [
                'seeded_from_order' => true,
                'order_id' => $order_id,
                'seeded_by' => 'canonical_builder',
            ],
        ] ),
        'created_at' => current_time( 'mysql' ),
    ] );

    $snapshot_id = (int) $wpdb->insert_id;
    if ( $snapshot_id > 0 ) {
        teinvit_save_invitation_config( $token, [ 'active_version_id' => $snapshot_id ] );
    }

    $payload = teinvit_get_modular_active_payload( $token );
    return teinvit_is_modular_snapshot_complete( $payload ) ? $payload : [];
}


function teinvit_render_admin_client_global_content() {
    $page = get_page_by_path( 'teinvit-admin-client-global', OBJECT, 'page' );
    if ( ! $page instanceof WP_Post ) {
        return '';
    }

    $content = (string) $page->post_content;
    if ( $content === '' ) {
        return '';
    }

    return (string) apply_filters( 'the_content', $content );
}


add_action( 'init', function() {
    register_post_type( 'teinvit_invitation', [
        'labels' => [
            'name'          => __( 'TeInvit Invitations', 'teinvit-core' ),
            'singular_name' => __( 'TeInvit Invitation', 'teinvit-core' ),
        ],
        'public'             => false,
        'show_ui'            => true,
        'show_in_rest'       => true,
        'exclude_from_search'=> true,
        'publicly_queryable' => false,
        'rewrite'            => false,
        'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ],
        'menu_position'      => 56,
        'menu_icon'          => 'dashicons-email-alt2',
    ] );

    add_rewrite_rule( '^admin-client/([^/]+)/?$', 'index.php?teinvit_admin_client_token=$matches[1]', 'top' );
    add_rewrite_rule( '^invitati/([^/]+)/?$', 'index.php?teinvit_invitati_token=$matches[1]', 'top' );
} );

function teinvit_get_invitation_post_id_by_token( $token ) {
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return 0;
    }

    $posts = get_posts( [
        'post_type'              => 'teinvit_invitation',
        'post_status'            => [ 'publish', 'draft', 'pending', 'private' ],
        'meta_key'               => 'teinvit_token',
        'meta_value'             => $token,
        'posts_per_page'         => 1,
        'orderby'                => 'ID',
        'order'                  => 'DESC',
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'ignore_sticky_posts'    => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ] );

    return ! empty( $posts ) ? (int) $posts[0] : 0;
}

function teinvit_seed_invitation_post_if_missing( $token, $order_id ) {
    $post_id = teinvit_get_invitation_post_id_by_token( $token );
    if ( $post_id > 0 ) {
        return $post_id;
    }

    $post_id = wp_insert_post( [
        'post_type'    => 'teinvit_invitation',
        'post_status'  => 'publish',
        'post_title'   => sprintf( 'Invitation %s', $token ),
        'post_content' => '',
    ] );

    if ( is_wp_error( $post_id ) || ! $post_id ) {
        return 0;
    }

    update_post_meta( $post_id, 'teinvit_token', $token );
    update_post_meta( $post_id, 'teinvit_order_id', (int) $order_id );

    return (int) $post_id;
}


function teinvit_enqueue_wapf_assets_for_admin_client() {
    if ( ! function_exists( 'wapf_get_setting' ) ) {
        return;
    }

    $base_url = (string) wapf_get_setting( 'url' );
    $base_path = (string) wapf_get_setting( 'path' );
    $version = (string) wapf_get_setting( 'version' );
    if ( $base_url === '' || $base_path === '' ) {
        return;
    }

    $assets_url = trailingslashit( $base_url ) . 'assets/';
    $assets_path = trailingslashit( $base_path ) . 'assets/';
    $frontend_css = $assets_path . 'css/frontend.min.css';
    if ( file_exists( $frontend_css ) ) {
        wp_enqueue_style( 'wapf-frontend', $assets_url . 'css/frontend.min.css', [], $version . '-' . filemtime( $frontend_css ) );
    }
    wp_enqueue_script( 'wapf-frontend', $assets_url . 'js/frontend.min.js', [ 'jquery' ], $version, true );

    if ( get_option( 'wapf_datepicker', 'no' ) === 'yes' ) {
        wp_enqueue_script( 'wapf-dp', $assets_url . 'js/datepicker.min.js', [], $version, true );
        wp_enqueue_style( 'wapf-dp', $assets_url . 'css/datepicker.min.css', [], $version );
    }

    if ( function_exists( 'acf_enqueue_scripts' ) ) {
        acf_enqueue_scripts();
    }

    if ( wp_script_is( 'acf-input', 'registered' ) ) {
        wp_enqueue_script( 'acf-input' );
    }
    if ( wp_style_is( 'acf-input', 'registered' ) ) {
        wp_enqueue_style( 'acf-input' );
    }

    wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_enqueue_style( 'jquery-ui-base', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css', [], '1.13.2' );
}

function teinvit_prepare_tokenized_invitation_request( $mode, $token, $invitation_post_id ) {
    $GLOBALS['teinvit_tokenized_mode'] = (string) $mode;
    $GLOBALS['teinvit_tokenized_token'] = (string) $token;
    $GLOBALS['teinvit_tokenized_post_id'] = (int) $invitation_post_id;

    add_filter( 'template_include', function( $template ) {
        if ( empty( $GLOBALS['teinvit_tokenized_mode'] ) || empty( $GLOBALS['teinvit_tokenized_token'] ) || empty( $GLOBALS['teinvit_tokenized_post_id'] ) ) {
            return $template;
        }

        $tokenized_template = TEINVIT_WEDDING_MODULE_PATH . 'templates/single-teinvit_invitation.php';
        if ( file_exists( $tokenized_template ) ) {
            return $tokenized_template;
        }

        status_header( 500 );
        return $template;
    }, 999 );

    add_filter( 'body_class', function( $classes ) {
        if ( ! empty( $GLOBALS['teinvit_tokenized_mode'] ) ) {
            $classes[] = 'teinvit-tokenized-route';
            $classes[] = 'teinvit-mode-' . sanitize_html_class( (string) $GLOBALS['teinvit_tokenized_mode'] );
        }
        return $classes;
    } );

    global $wp_query;
    if ( $wp_query instanceof WP_Query ) {
        $wp_query->is_404 = false;
        $wp_query->is_singular = true;
        $wp_query->is_single = true;
    }

    status_header( 200 );
    nocache_headers();
}

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'teinvit_admin_client_token';
    $vars[] = 'teinvit_invitati_token';
    return $vars;
} );

add_action( 'template_redirect', function() {
    $token = get_query_var( 'teinvit_admin_client_token' );
    if ( ! $token ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( home_url( '/admin-client/' . rawurlencode( $token ) ) ) );
        exit;
    }

    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? teinvit_get_order_id_by_token( $token ) : 0;
    if ( ! $order_id ) {
        status_header( 404 );
        get_header();
        echo '<p>Token invalid.</p>';
        get_footer();
        exit;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order || (int) $order->get_user_id() !== get_current_user_id() ) {
        status_header( 403 );
        get_header();
        echo '<p>Nu ai permisiunea pentru această invitație.</p>';
        get_footer();
        exit;
    }

    teinvit_seed_invitation_if_missing( $token, $order_id );
    teinvit_enqueue_wapf_assets_for_admin_client();
    $invitation_post_id = teinvit_seed_invitation_post_if_missing( $token, $order_id );
    if ( ! $invitation_post_id ) {
        status_header( 500 );
        get_header();
        echo '<p>Nu s-a putut inițializa invitația WP.</p>';
        get_footer();
        exit;
    }

    teinvit_prepare_tokenized_invitation_request( 'admin-client', $token, $invitation_post_id );
}, 2 );

add_action( 'template_redirect', function() {
    $token = get_query_var( 'teinvit_invitati_token' );
    if ( ! $token ) {
        return;
    }

    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? teinvit_get_order_id_by_token( $token ) : 0;
    if ( ! $order_id ) {
        status_header( 404 );
        get_header();
        echo '<p>Invitația nu a fost găsită.</p>';
        get_footer();
        exit;
    }

    teinvit_seed_invitation_if_missing( $token, $order_id );
    teinvit_touch_invitation_activity( $token );
    $invitation_post_id = teinvit_seed_invitation_post_if_missing( $token, $order_id );
    if ( ! $invitation_post_id ) {
        status_header( 500 );
        get_header();
        echo '<p>Nu s-a putut inițializa invitația WP.</p>';
        get_footer();
        exit;
    }

    teinvit_prepare_tokenized_invitation_request( 'invitati', $token, $invitation_post_id );
}, 2 );


function teinvit_theme_key_from_any_value( $raw ) {
    $value = strtolower( trim( (string) $raw ) );
    if ( $value === '' ) {
        return '';
    }

    if ( in_array( $value, [ 'editorial', 'romantic', 'modern', 'classic' ], true ) ) {
        return $value;
    }

    if ( strpos( $value, 'editorial' ) !== false ) {
        return 'editorial';
    }
    if ( strpos( $value, 'romantic' ) !== false ) {
        return 'romantic';
    }
    if ( strpos( $value, 'modern' ) !== false ) {
        return 'modern';
    }
    if ( strpos( $value, 'classic' ) !== false ) {
        return 'classic';
    }

    return '';
}

function teinvit_extract_theme_options_map_from_product( $product_id ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 || ! function_exists( 'teinvit_extract_wapf_definitions_from_product' ) ) {
        return [];
    }

    $defs = teinvit_extract_wapf_definitions_from_product( $product_id );
    if ( ! is_array( $defs ) ) {
        return [];
    }

    foreach ( $defs as $def ) {
        if ( ! is_array( $def ) ) {
            continue;
        }

        $field_id = function_exists( 'teinvit_normalize_wapf_field_id' )
            ? teinvit_normalize_wapf_field_id( $def['id'] ?? '' )
            : trim( (string) ( $def['id'] ?? '' ) );

        if ( $field_id !== '6967752ab511b' ) {
            continue;
        }

        $map = [];
        foreach ( (array) ( $def['options'] ?? [] ) as $opt ) {
            if ( ! is_array( $opt ) ) {
                continue;
            }

            $option_label = trim( (string) ( $opt['label'] ?? $opt['text'] ?? $opt['value'] ?? $opt['slug'] ?? $opt['id'] ?? $opt['key'] ?? '' ) );
            $option_value = trim( (string) ( $opt['value'] ?? $opt['slug'] ?? $opt['id'] ?? $opt['key'] ?? $opt['code'] ?? $option_label ) );
            if ( $option_value !== '' ) {
                $map[ $option_value ] = $option_label;
            }
        }
        return $map;
    }

    return [];
}

function teinvit_wapf_is_zeroish_value( $raw ) {
    $raw_string = trim( (string) $raw );
    if ( $raw_string === '' ) {
        return false;
    }

    $tokens = preg_split( '/\s*,\s*/', $raw_string );
    if ( ! is_array( $tokens ) || empty( $tokens ) ) {
        return false;
    }

    foreach ( $tokens as $token ) {
        $token = strtolower( trim( (string) $token ) );
        if ( $token === '' ) {
            continue;
        }
        if ( ! in_array( $token, [ '0', 'false', 'off', 'no' ], true ) ) {
            return false;
        }
    }

    return true;
}

function teinvit_wapf_boolean_field_ids() {
    return [
        '696445d6a9ce9',
        '696448f2ae763',
        '69644d9e814ef',
        '69645088f4b73',
        '696451a951467',
    ];
}

function teinvit_extract_wapf_option_maps_from_product( $product_id ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 || ! function_exists( 'teinvit_extract_wapf_definitions_from_product' ) ) {
        return [];
    }

    $defs = teinvit_extract_wapf_definitions_from_product( $product_id );
    if ( ! is_array( $defs ) ) {
        return [];
    }

    $maps = [];
    foreach ( $defs as $def ) {
        if ( ! is_array( $def ) ) {
            continue;
        }
        $field_id = function_exists( 'teinvit_normalize_wapf_field_id' )
            ? teinvit_normalize_wapf_field_id( $def['id'] ?? '' )
            : trim( (string) ( $def['id'] ?? '' ) );
        if ( $field_id === '' ) {
            continue;
        }

        $field_map = [
            'type' => strtolower( trim( (string) ( $def['type'] ?? '' ) ) ),
            'by_value' => [],
            'by_label' => [],
        ];

        foreach ( (array) ( $def['options'] ?? [] ) as $opt ) {
            if ( ! is_array( $opt ) ) {
                continue;
            }
            $label = trim( (string) ( $opt['label'] ?? $opt['text'] ?? $opt['value'] ?? $opt['slug'] ?? $opt['id'] ?? $opt['key'] ?? '' ) );
            $value = trim( (string) ( $opt['value'] ?? $opt['slug'] ?? $opt['id'] ?? $opt['key'] ?? $opt['code'] ?? $label ) );
            if ( $value !== '' ) {
                $field_map['by_value'][ $value ] = $label;
            }
            if ( $label !== '' ) {
                $field_map['by_label'][ strtolower( $label ) ] = $value !== '' ? $value : $label;
            }
        }

        $maps[ $field_id ] = $field_map;
    }

    return $maps;
}

function teinvit_normalize_wapf_map_with_option_maps( array $wapf, array $option_maps ) {
    $normalized = $wapf;

    $boolean_field_ids = array_fill_keys( teinvit_wapf_boolean_field_ids(), true );

    foreach ( $wapf as $field_id => $raw ) {
        $raw_string = trim( (string) $raw );
        if ( isset( $boolean_field_ids[ (string) $field_id ] ) && teinvit_wapf_is_zeroish_value( $raw_string ) ) {
            $normalized[ $field_id ] = '';
            continue;
        }

        if ( ! isset( $option_maps[ $field_id ] ) || ! is_array( $option_maps[ $field_id ] ) ) {
            continue;
        }

        $field_map = $option_maps[ $field_id ];
        $type = strtolower( trim( (string) ( $field_map['type'] ?? '' ) ) );
        $by_value = is_array( $field_map['by_value'] ?? null ) ? $field_map['by_value'] : [];
        $by_label = is_array( $field_map['by_label'] ?? null ) ? $field_map['by_label'] : [];

        if ( $raw_string === '' || ( empty( $by_value ) && empty( $by_label ) ) ) {
            continue;
        }

        $resolve_single = static function( $value ) use ( $by_value, $by_label ) {
            $candidate = trim( (string) $value );
            if ( $candidate === '' || $candidate === '0' ) {
                return '';
            }
            if ( isset( $by_value[ $candidate ] ) ) {
                return $candidate;
            }
            $candidate_lower = strtolower( $candidate );
            if ( isset( $by_label[ $candidate_lower ] ) ) {
                return (string) $by_label[ $candidate_lower ];
            }
            return '';
        };

        if ( in_array( $type, [ 'checkbox', 'checkboxes', 'multi-color-swatch', 'multi-image-swatch', 'multi-text-swatch', 'products-checkbox', 'products-card', 'products-image', 'products-vcard', 'true-false' ], true ) ) {
            $tokens = array_filter( array_map( 'trim', explode( ',', $raw_string ) ), static function( $v ) {
                return $v !== '' && $v !== '0';
            } );
            $resolved = [];

            $direct = $resolve_single( $raw_string );
            if ( $direct !== '' ) {
                $resolved[] = $direct;
            }

            foreach ( $tokens as $token ) {
                $match = $resolve_single( $token );
                if ( $match !== '' ) {
                    $resolved[] = $match;
                }
            }

            $resolved = array_values( array_unique( $resolved ) );
            if ( ! empty( $resolved ) ) {
                $normalized[ $field_id ] = implode( ', ', $resolved );
            } else {
                $normalized[ $field_id ] = '';
            }
            continue;
        }

        $single = $resolve_single( $raw_string );
        if ( $single !== '' ) {
            $normalized[ $field_id ] = $single;
        }
    }

    return $normalized;
}

function teinvit_resolve_theme_key_from_wapf_value( $raw, array $theme_options_map = [] ) {
    $direct = teinvit_theme_key_from_any_value( $raw );
    if ( $direct !== '' ) {
        return $direct;
    }

    $raw_string = trim( (string) $raw );
    if ( $raw_string !== '' && isset( $theme_options_map[ $raw_string ] ) ) {
        $from_label = teinvit_theme_key_from_any_value( $theme_options_map[ $raw_string ] );
        if ( $from_label !== '' ) {
            return $from_label;
        }
    }

    return 'editorial';
}

function teinvit_build_invitation_from_wapf_map( array $wapf, array $theme_options_map = [] ) {
    $get = function( $id ) use ( $wapf ) {
        return isset( $wapf[ $id ] ) ? trim( (string) $wapf[ $id ] ) : '';
    };

    $has = function( $id ) use ( $wapf ) {
        if ( ! isset( $wapf[ $id ] ) ) {
            return false;
        }

        $value = trim( (string) $wapf[ $id ] );
        if ( $value === '' || teinvit_wapf_is_zeroish_value( $value ) ) {
            return false;
        }

        return true;
    };

    $format_date_time = function( $date, $time ) {
        $date = trim( (string) $date );
        $time = trim( (string) $time );
        if ( $date === '' ) {
            return '';
        }
        return $time !== '' ? ( $date . ' ora ' . $time ) : $date;
    };

    $invitation = [
        'theme'        => 'editorial',
        'names'        => trim( implode( ' & ', array_filter( [ $get( '6963a95e66425' ), $get( '6963aa37412e4' ) ] ) ) ),
        'message'      => $get( '6963aa782092d' ),
        'show_parents' => false,
        'parents'      => [],
        'show_nasi'    => false,
        'nasi'         => [],
        'events'       => [],
        'model_key'    => 'invn01',
    ];

    $invitation['theme'] = teinvit_resolve_theme_key_from_wapf_value( $get( '6967752ab511b' ), $theme_options_map );

    if ( $has( '696445d6a9ce9' ) ) {
        $invitation['show_parents'] = true;
        $invitation['parents'] = [
            'mireasa' => trim( implode( ' & ', array_filter( [ $get( '6964461d67da5' ), $get( '6964466afe4d1' ) ] ) ) ),
            'mire'    => trim( implode( ' & ', array_filter( [ $get( '69644689ee7e1' ), $get( '696446dfabb7b' ) ] ) ) ),
        ];
    }

    if ( $has( '696448f2ae763' ) ) {
        $invitation['show_nasi'] = true;
        $invitation['nasi'] = trim( implode( ' & ', array_filter( [ $get( '69644a3415fb9' ), $get( '69644a5822ddc' ) ] ) ) );
    }

    if ( $has( '69644d9e814ef' ) ) {
        $invitation['events'][] = [
            'title' => 'Cununie civilă',
            'loc'   => $get( '69644f2b40023' ),
            'date'  => $format_date_time( $get( '69644f85d865e' ), $get( '8dec5e7' ) ),
            'waze'  => $get( '69644fd5c832b' ),
        ];
    }

    if ( $has( '69645088f4b73' ) ) {
        $invitation['events'][] = [
            'title' => 'Ceremonie religioasă',
            'loc'   => $get( '696450ee17f9e' ),
            'date'  => $format_date_time( $get( '696450ffe7db4' ), $get( '32f74cc' ) ),
            'waze'  => $get( '69645104b39f4' ),
        ];
    }

    if ( $has( '696451a951467' ) ) {
        $invitation['events'][] = [
            'title' => 'Petrecerea',
            'loc'   => $get( '696451d204a8a' ),
            'date'  => $format_date_time( $get( '696452023cdcd' ), $get( 'a4a0fca' ) ),
            'waze'  => $get( '696452478586d' ),
        ];
    }

    return $invitation;
}

function teinvit_extract_posted_wapf_map( array $source ) {
    $out = [];
    $boolean_field_lookup = array_fill_keys( teinvit_wapf_boolean_field_ids(), true );
    $parent_checked_override = [];

    if ( isset( $source['teinvit_parent_checked_json'] ) ) {
        $raw_override = wp_unslash( (string) $source['teinvit_parent_checked_json'] );
        $decoded = json_decode( $raw_override, true );
        if ( is_array( $decoded ) ) {
            foreach ( $decoded as $field_id => $flag ) {
                if ( ! is_scalar( $field_id ) ) {
                    continue;
                }
                $normalized_id = sanitize_text_field( (string) $field_id );
                if ( $normalized_id === '' || ! isset( $boolean_field_lookup[ $normalized_id ] ) ) {
                    continue;
                }
                $flag_string = strtolower( trim( (string) $flag ) );
                $is_checked = in_array( $flag_string, [ '1', 'true', 'yes', 'on' ], true );
                $parent_checked_override[ $normalized_id ] = $is_checked;
            }
        }
    }

    if ( isset( $source['wapf'] ) && is_array( $source['wapf'] ) ) {
        foreach ( $source['wapf'] as $field_key => $field_value ) {
            if ( ! is_string( $field_key ) || strpos( $field_key, 'field_' ) !== 0 ) {
                continue;
            }

            $field_id = sanitize_text_field( substr( $field_key, 6 ) );
            if ( is_array( $field_value ) ) {
                $flat = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $field_value ) );
                $flat = array_values( array_filter( $flat, static function( $v ) { return $v !== ''; } ) );
                if ( isset( $boolean_field_lookup[ $field_id ] ) ) {
                    $selected = [];
                    foreach ( $flat as $value ) {
                        if ( $value === '' || teinvit_wapf_is_zeroish_value( (string) $value ) ) {
                            continue;
                        }
                        $selected[] = (string) $value;
                    }
                    $selected = array_values( array_unique( $selected ) );

                    if ( array_key_exists( $field_id, $parent_checked_override ) ) {
                        if ( ! $parent_checked_override[ $field_id ] ) {
                            $selected = [];
                        }
                    } else {
                        $selected = array_values( array_filter( $selected, static function( $value ) use ( $flat ) {
                            return count( array_keys( $flat, $value, true ) ) >= 2;
                        } ) );
                    }

                    $out[ $field_id ] = implode( ', ', $selected );
                } else {
                    $out[ $field_id ] = implode( ', ', $flat );
                }
            } else {
                $out[ $field_id ] = sanitize_text_field( wp_unslash( (string) $field_value ) );
            }
        }
    }

    foreach ( $source as $key => $value ) {
        if ( ! is_string( $key ) ) {
            continue;
        }
        if ( preg_match( '/^wapf\[field_([^\]]+)\]$/', $key, $m ) ) {
            $field_id = sanitize_text_field( $m[1] );
            if ( is_array( $value ) ) {
                $flat = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $value ) );
                $flat = array_values( array_filter( $flat, static function( $v ) { return $v !== ''; } ) );
                if ( isset( $boolean_field_lookup[ $field_id ] ) ) {
                    $selected = [];
                    foreach ( $flat as $token ) {
                        if ( $token === '' || teinvit_wapf_is_zeroish_value( (string) $token ) ) {
                            continue;
                        }
                        $selected[] = (string) $token;
                    }
                    $selected = array_values( array_unique( $selected ) );

                    if ( array_key_exists( $field_id, $parent_checked_override ) ) {
                        if ( ! $parent_checked_override[ $field_id ] ) {
                            $selected = [];
                        }
                    } else {
                        $selected = array_values( array_filter( $selected, static function( $value ) use ( $flat ) {
                            return count( array_keys( $flat, $value, true ) ) >= 2;
                        } ) );
                    }

                    $out[ $field_id ] = implode( ', ', $selected );
                } else {
                    $out[ $field_id ] = implode( ', ', $flat );
                }
            } else {
                $out[ $field_id ] = sanitize_text_field( wp_unslash( (string) $value ) );
            }
        }
    }

    return $out;
}


function teinvit_active_snapshot_event_flags( $token ) {
    $flags = [
        'civil' => false,
        'religious' => false,
        'party' => false,
    ];

    $active = function_exists( 'teinvit_get_active_snapshot' ) ? teinvit_get_active_snapshot( $token ) : null;
    $payload = $active && ! empty( $active['snapshot'] ) ? json_decode( (string) $active['snapshot'], true ) : [];
    $events = isset( $payload['invitation']['events'] ) && is_array( $payload['invitation']['events'] ) ? $payload['invitation']['events'] : [];

    foreach ( $events as $event ) {
        if ( ! is_array( $event ) ) {
            continue;
        }

        $title = strtolower( trim( (string) ( $event['title'] ?? '' ) ) );
        if ( $title === '' ) {
            continue;
        }

        if ( strpos( $title, 'civil' ) !== false ) {
            $flags['civil'] = true;
        }
        if ( strpos( $title, 'religio' ) !== false ) {
            $flags['religious'] = true;
        }
        if ( strpos( $title, 'petrec' ) !== false ) {
            $flags['party'] = true;
        }
    }

    return $flags;
}

function teinvit_admin_client_read_checkbox( array $source, $key ) {
    return isset( $source[ $key ] ) && ! empty( $source[ $key ] ) ? 1 : 0;
}

function teinvit_admin_client_merge_deadline_from_post( array $config, array $source ) {
    $config['show_rsvp_deadline'] = teinvit_admin_client_read_checkbox( $source, 'date_confirm' );
    $config['rsvp_deadline_date'] = sanitize_text_field( wp_unslash( $source['selecteaza_data'] ?? '' ) );

    return $config;
}

function teinvit_admin_client_merge_selection_toggles_from_post( array $config, array $source, array $event_flags = [] ) {
    $map = [
        'permite_confirmarea_pentru_cununia_civila' => 'show_attending_civil',
        'permite_confirmarea_pentru_ceremonia_religioasa' => 'show_attending_religious',
        'permite_confirmarea_pentru_petrecere' => 'show_attending_party',
        'permite_confirmarea_copiilor' => 'show_kids',
        'permite_solicitarea_de_cazare' => 'show_accommodation',
        'permite_selectarea_meniului_vegetarian' => 'show_vegetarian',
        'permite_mentionarea_alergiilor' => 'show_allergies',
        'permite_trimiterea_unui_mesaj_catre_miri' => 'show_message',
        'activeaza_sectiunea_cadouri' => 'show_gifts_section',
    ];

    $published_order = [];
    foreach ( $map as $field_name => $config_key ) {
        $enabled = teinvit_admin_client_read_checkbox( $source, $field_name );
        $config[ $config_key ] = $enabled;
        if ( $enabled ) {
            $published_order[] = $config_key;
        }
    }

    if ( ! empty( $event_flags ) ) {
        if ( empty( $event_flags['civil'] ) ) {
            $config['show_attending_civil'] = 0;
            $published_order = array_values( array_filter( $published_order, static function( $k ) { return $k !== 'show_attending_civil'; } ) );
        }
        if ( empty( $event_flags['religious'] ) ) {
            $config['show_attending_religious'] = 0;
            $published_order = array_values( array_filter( $published_order, static function( $k ) { return $k !== 'show_attending_religious'; } ) );
        }
        if ( empty( $event_flags['party'] ) ) {
            $config['show_attending_party'] = 0;
            $published_order = array_values( array_filter( $published_order, static function( $k ) { return $k !== 'show_attending_party'; } ) );
        }
    }

    $config['rsvp_zone2_order'] = $published_order;

    return $config;
}

function teinvit_wapf_payload_is_minimally_valid( array $wapf, array $invitation ) {
    $name = trim( (string) ( $invitation['names'] ?? '' ) );
    $theme = trim( (string) ( $invitation['theme'] ?? '' ) );
    if ( $name === '' || $theme === '' ) {
        return false;
    }

    $required_ids = [ '6963a95e66425', '6963aa37412e4', '6967752ab511b' ];
    foreach ( $required_ids as $id ) {
        if ( ! isset( $wapf[ $id ] ) || trim( (string) $wapf[ $id ] ) === '' ) {
            return false;
        }
    }

    return true;
}

function teinvit_admin_post_guard( $token ) {
    if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'teinvit_admin_' . $token ) ) {
        wp_die( 'Nonce invalid' );
    }

    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? teinvit_get_order_id_by_token( $token ) : 0;
    $order = $order_id ? wc_get_order( $order_id ) : null;
    if ( ! $order || (int) $order->get_user_id() !== get_current_user_id() ) {
        wp_die( 'Acces interzis' );
    }

    return [ $order_id, $order ];
}


add_action( 'init', function() {
    if ( function_exists( 'teinvit_ensure_rsvp_email_column' ) ) {
        teinvit_ensure_rsvp_email_column();
    }
    if ( function_exists( 'teinvit_ensure_rsvp_marketing_column' ) ) {
        teinvit_ensure_rsvp_marketing_column();
    }
    if ( function_exists( 'teinvit_ensure_gifts_publish_columns' ) ) {
        teinvit_ensure_gifts_publish_columns();
    }
}, 1 );

add_action( 'admin_post_teinvit_save_invitation_info', function() {
    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    teinvit_admin_post_guard( $token );

    $inv = teinvit_get_invitation( $token );
    $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : teinvit_default_rsvp_config();
    $config = teinvit_admin_client_merge_deadline_from_post( $config, $_POST );
    if ( ! isset( $config['edits_free_remaining'] ) ) {
        $config['edits_free_remaining'] = 2;
    }
    if ( ! isset( $config['edits_paid_remaining'] ) ) {
        $config['edits_paid_remaining'] = 0;
    }

    teinvit_save_invitation_config( $token, [ 'config' => $config ] );

    wp_safe_redirect( home_url( '/admin-client/' . rawurlencode( $token ) . '?saved=info' ) );
    exit;
} );

add_action( 'admin_post_teinvit_save_rsvp_config', function() {
    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    teinvit_admin_post_guard( $token );

    $inv = teinvit_get_invitation( $token );
    $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : teinvit_default_rsvp_config();
    $config = teinvit_admin_client_merge_selection_toggles_from_post( $config, $_POST, teinvit_active_snapshot_event_flags( $token ) );
    if ( ! isset( $config['edits_free_remaining'] ) ) {
        $config['edits_free_remaining'] = 2;
    }
    if ( ! isset( $config['edits_paid_remaining'] ) ) {
        $config['edits_paid_remaining'] = 0;
    }

    teinvit_save_invitation_config( $token, [ 'config' => $config ] );
    wp_safe_redirect( home_url( '/admin-client/' . rawurlencode( $token ) . '?saved=config' ) );
    exit;
} );

add_action( 'admin_post_teinvit_set_active_version', function() {
    global $wpdb;
    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    teinvit_admin_post_guard( $token );

    $version_id = (int) ( $_POST['active_version_id'] ?? 0 );
    $t = teinvit_db_tables();
    $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['versions']} WHERE token=%s AND id=%d", $token, $version_id ) );
    if ( $exists ) {
        teinvit_save_invitation_config( $token, [ 'active_version_id' => $version_id ] );
    }

    wp_safe_redirect( home_url( '/admin-client/' . rawurlencode( $token ) . '?saved=active' ) );
    exit;
} );

add_action( 'admin_post_teinvit_save_version_snapshot', function() {
    global $wpdb;
    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    list( $order_id, $order ) = teinvit_admin_post_guard( $token );

    $inv = teinvit_get_invitation( $token );
    if ( ! $inv ) {
        wp_safe_redirect( home_url( '/admin-client/' . rawurlencode( $token ) . '?error=missing' ) );
        exit;
    }

    $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : teinvit_default_rsvp_config();
    $free_remaining = isset( $config['edits_free_remaining'] ) ? (int) $config['edits_free_remaining'] : 2;
    $paid_remaining = isset( $config['edits_paid_remaining'] ) ? (int) $config['edits_paid_remaining'] : 0;
    $remaining = max( 0, $free_remaining ) + max( 0, $paid_remaining );
    if ( $remaining <= 0 ) {
        wp_safe_redirect( home_url( '/admin-client/' . rawurlencode( $token ) . '?error=noedits' ) );
        exit;
    }

    $wapf = teinvit_extract_posted_wapf_map( $_POST );
    $primary_product_id = teinvit_get_order_primary_product_id( $order );
    $defs = teinvit_get_wapf_defs_for_product( $primary_product_id );
    $canonical = teinvit_build_invitation_from_wapf_map_canonical( $wapf, $defs );
    $wapf = is_array( $canonical['wapf_map'] ?? null ) ? $canonical['wapf_map'] : [];
    $snapshot_invitation = is_array( $canonical['invitation'] ?? null ) ? $canonical['invitation'] : [];
    if ( ! teinvit_wapf_payload_is_minimally_valid( $wapf, $snapshot_invitation ) ) {
        wp_safe_redirect( home_url( '/admin-client/' . rawurlencode( $token ) . '?error=invalid_snapshot' ) );
        exit;
    }

    $snapshot = [
        'invitation' => $snapshot_invitation,
        'wapf_fields' => $wapf,
        'meta' => [ 'order_id' => (int) $order_id ],
    ];

    $t = teinvit_db_tables();
    $wpdb->insert( $t['versions'], [
        'token' => $token,
        'snapshot' => wp_json_encode( $snapshot ),
        'created_at' => current_time( 'mysql' ),
    ] );
    $version_id = (int) $wpdb->insert_id;

    $pdf_status = 'none';
    $pdf_url = '';
    $pdf_filename = '';
    if ( $version_id > 0 && function_exists( 'teinvit_pdf_filename_for_version' ) && function_exists( 'teinvit_generate_pdf_for_version' ) ) {
        $version_index = max( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['versions']} WHERE token = %s AND id <= %d", $token, $version_id ) ) - 1 );
        $pdf_filename = teinvit_pdf_filename_for_version( $order, $version_index );
        $wpdb->update( $t['versions'], [
            'pdf_status' => 'processing',
            'pdf_filename' => $pdf_filename,
        ], [ 'id' => $version_id ] );

        $pdf_result = teinvit_generate_pdf_for_version( $token, $order_id, $pdf_filename, $version_id );
        if ( is_wp_error( $pdf_result ) ) {
            $pdf_status = 'failed';
        } else {
            $pdf_status = 'ready';
            $pdf_url = (string) ( $pdf_result['pdf_url'] ?? '' );
        }

        $wpdb->update( $t['versions'], [
            'pdf_status' => $pdf_status,
            'pdf_url' => $pdf_url,
            'pdf_generated_at' => current_time( 'mysql' ),
            'pdf_filename' => $pdf_filename,
        ], [ 'id' => $version_id ] );
    }

    if ( $free_remaining > 0 ) {
        $config['edits_free_remaining'] = max( 0, $free_remaining - 1 );
    } else {
        $config['edits_paid_remaining'] = max( 0, $paid_remaining - 1 );
    }

    teinvit_save_invitation_config( $token, [
        'config' => $config,
    ] );

    $redirect_url = add_query_arg( [
        'saved' => 'version',
        'selected_version_id' => $version_id,
    ], home_url( '/admin-client/' . rawurlencode( $token ) ) );

    wp_safe_redirect( $redirect_url );
    exit;
} );

add_action( 'admin_post_teinvit_save_gifts', function() {
    global $wpdb;
    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    teinvit_admin_post_guard( $token );

    $inv = teinvit_get_invitation( $token );
    if ( ! $inv ) {
        wp_safe_redirect( home_url( '/admin-client/' . rawurlencode( $token ) . '?error=missing' ) );
        exit;
    }

    $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : teinvit_default_rsvp_config();
    $config['show_gifts_section'] = isset( $_POST['show_gifts_section'] ) ? 1 : 0;

    $gifts_extra_slots = isset( $config['gifts_extra_slots'] ) ? max( 0, (int) $config['gifts_extra_slots'] ) : 0;
    $max_slots = 20 + $gifts_extra_slots;

    $t = teinvit_db_tables();
    $existing_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['gifts']} WHERE token=%s ORDER BY id ASC", $token ), ARRAY_A );
    $existing_map = [];
    foreach ( $existing_rows as $row ) {
        $existing_map[ (string) $row['gift_id'] ] = $row;
    }

    $rows = isset( $_POST['gifts'] ) && is_array( $_POST['gifts'] ) ? $_POST['gifts'] : [];
    $entered_count = 0;
    foreach ( $rows as $gift ) {
        $name = sanitize_text_field( wp_unslash( $gift['gift_name'] ?? '' ) );
        $link = esc_url_raw( wp_unslash( $gift['gift_link'] ?? '' ) );
        if ( $name !== '' && $link !== '' ) {
            $entered_count++;
        }
    }

    if ( $entered_count > $max_slots ) {
        wp_safe_redirect( home_url( '/admin-client/' . rawurlencode( $token ) . '?error=gifts_limit' ) );
        exit;
    }

    $posted_ids = [];
    foreach ( $rows as $index => $gift ) {
        $gift_id = sanitize_text_field( wp_unslash( $gift['gift_id'] ?? '' ) );
        if ( $gift_id === '' ) {
            $gift_id = 'gift-' . wp_generate_password( 10, false, false ) . '-' . $index;
        }
        $posted_ids[] = $gift_id;

        $include = ! empty( $gift['include_in_public'] ) ? 1 : 0;
        $name = sanitize_text_field( wp_unslash( $gift['gift_name'] ?? '' ) );
        $link = esc_url_raw( wp_unslash( $gift['gift_link'] ?? '' ) );
        $address = sanitize_textarea_field( wp_unslash( $gift['gift_delivery_address'] ?? '' ) );
        $is_complete = ( $name !== '' && $link !== '' );

        $existing = isset( $existing_map[ $gift_id ] ) ? $existing_map[ $gift_id ] : null;
        if ( $existing && ! empty( $existing['published_locked'] ) ) {
            $wpdb->update( $t['gifts'], [
                'include_in_public' => $include,
            ], [ 'id' => (int) $existing['id'] ] );
            continue;
        }

        $payload = [
            'token' => $token,
            'gift_id' => $gift_id,
            'gift_name' => $name,
            'gift_link' => $link,
            'gift_delivery_address' => $address,
            'include_in_public' => $include,
            'published_locked' => ( $include && $is_complete ) ? 1 : 0,
            'locked_at' => ( $include && $is_complete ) ? current_time( 'mysql' ) : null,
            'status' => $existing ? (string) $existing['status'] : 'free',
            'reserved_by_rsvp_id' => $existing ? (int) ( $existing['reserved_by_rsvp_id'] ?? 0 ) : null,
            'reserved_at' => $existing ? ( $existing['reserved_at'] ?? null ) : null,
        ];

        if ( $existing ) {
            $wpdb->update( $t['gifts'], $payload, [ 'id' => (int) $existing['id'] ] );
        } else {
            $wpdb->insert( $t['gifts'], $payload );
        }
    }

    if ( ! empty( $existing_rows ) ) {
        foreach ( $existing_rows as $row ) {
            if ( in_array( (string) $row['gift_id'], $posted_ids, true ) ) {
                continue;
            }
            if ( ! empty( $row['published_locked'] ) ) {
                continue;
            }
            $wpdb->delete( $t['gifts'], [ 'id' => (int) $row['id'] ] );
        }
    }

    teinvit_save_invitation_config( $token, [ 'config' => $config ] );

    wp_safe_redirect( home_url( '/admin-client/' . rawurlencode( $token ) . '?saved=gifts' ) );
    exit;
} );


add_action( 'rest_api_init', function() {
    register_rest_route( 'teinvit/v2', '/preview/build', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function( WP_REST_Request $request ) {
            $payload = (array) $request->get_json_params();
            $product_id = isset( $payload['product_id'] ) ? (int) $payload['product_id'] : 0;
            $token = sanitize_text_field( (string) ( $payload['token'] ?? '' ) );

            if ( $product_id <= 0 && $token !== '' && function_exists( 'teinvit_get_order_id_by_token' ) ) {
                $order_id = (int) teinvit_get_order_id_by_token( $token );
                $order = $order_id ? wc_get_order( $order_id ) : null;
                if ( $order ) {
                    $product_id = teinvit_get_order_primary_product_id( $order );
                }
            }

            if ( $product_id <= 0 ) {
                return new WP_Error( 'invalid_product', 'Product invalid', [ 'status' => 400 ] );
            }

            $raw_map = isset( $payload['wapf_map'] ) && is_array( $payload['wapf_map'] ) ? $payload['wapf_map'] : [];
            $wapf_map = [];
            foreach ( $raw_map as $field_id => $value ) {
                if ( ! is_scalar( $field_id ) ) {
                    continue;
                }
                $normalized_id = function_exists( 'teinvit_normalize_wapf_field_id' ) ? teinvit_normalize_wapf_field_id( (string) $field_id ) : trim( (string) $field_id );
                if ( $normalized_id === '' ) {
                    continue;
                }

                if ( is_array( $value ) ) {
                    $flat = array_map( static function( $v ) {
                        return sanitize_text_field( (string) $v );
                    }, $value );
                    $wapf_map[ $normalized_id ] = implode( ', ', array_filter( $flat, static function( $v ) {
                        return $v !== '';
                    } ) );
                } else {
                    $wapf_map[ $normalized_id ] = sanitize_text_field( (string) $value );
                }
            }

            $defs = teinvit_get_wapf_defs_for_product( $product_id );
            $built = teinvit_build_invitation_from_wapf_map_canonical( $wapf_map, $defs );

            return [
                'ok' => true,
                'invitation' => $built['invitation'],
                'wapf_map' => $built['wapf_map'],
                'debug' => [
                    'theme_raw' => $built['theme_raw'],
                    'theme_resolved' => $built['theme_resolved'],
                ],
            ];
        },
    ] );
} );

add_action( 'rest_api_init', function() {
    register_rest_route( 'teinvit/v2', '/invitati/(?P<token>[a-zA-Z0-9\-]+)/rsvp', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function( WP_REST_Request $request ) {
            global $wpdb;
            $token = sanitize_text_field( $request['token'] );
            $inv = teinvit_get_invitation( $token );
            if ( ! $inv ) {
                return new WP_Error( 'not_found', 'Token invalid', [ 'status' => 404 ] );
            }

            $p = (array) $request->get_json_params();
            $phone = trim( (string) ( $p['guest_phone'] ?? '' ) );
            $phone = preg_replace( '/\s+/', '', $phone );
            $is_ro = preg_match( '/^(?:07\d{8}|\+407\d{8})$/', $phone );
            $is_intl = preg_match( '/^\+[1-9]\d{7,14}$/', $phone );
            if ( ! $is_ro && ! $is_intl ) {
                return new WP_Error( 'phone_invalid', 'Telefon invalid', [ 'status' => 400 ] );
            }
            if ( strpos( $phone, '+407' ) === 0 ) {
                $phone = '0' . substr( $phone, 3 );
            }

            $email = sanitize_email( (string) ( $p['guest_email'] ?? '' ) );
            if ( $email === '' || ! is_email( $email ) ) {
                return new WP_Error( 'email_invalid', 'Email invalid', [ 'status' => 400 ] );
            }

            if ( empty( $p['gdpr_accepted'] ) ) {
                return new WP_Error( 'gdpr_required', 'GDPR este obligatoriu', [ 'status' => 400 ] );
            }

            $t = teinvit_db_tables();
            $wpdb->query( 'START TRANSACTION' );
            $wpdb->insert( $t['rsvp'], [
                'token' => $token,
                'guest_first_name' => sanitize_text_field( $p['guest_first_name'] ?? '' ),
                'guest_last_name' => sanitize_text_field( $p['guest_last_name'] ?? '' ),
                'guest_email' => $email,
                'guest_phone' => $phone,
                'attending_people_count' => max( 1, (int) ( $p['attending_people_count'] ?? 1 ) ),
                'attending_civil' => empty( $p['attending_civil'] ) ? 0 : 1,
                'attending_religious' => empty( $p['attending_religious'] ) ? 0 : 1,
                'attending_party' => empty( $p['attending_party'] ) ? 0 : 1,
                'bringing_kids' => empty( $p['bringing_kids'] ) ? 0 : 1,
                'kids_count' => max( 0, (int) ( $p['kids_count'] ?? 0 ) ),
                'needs_accommodation' => empty( $p['needs_accommodation'] ) ? 0 : 1,
                'accommodation_people_count' => max( 0, (int) ( $p['accommodation_people_count'] ?? 0 ) ),
                'vegetarian_requested' => empty( $p['vegetarian_requested'] ) ? 0 : 1,
                'has_allergies' => empty( $p['has_allergies'] ) ? 0 : 1,
                'allergy_details' => sanitize_text_field( $p['allergy_details'] ?? '' ),
                'message_to_couple' => sanitize_textarea_field( $p['message_to_couple'] ?? '' ),
                'gdpr_accepted' => 1,
                'marketing_consent' => empty( $p['marketing_consent'] ) ? 0 : 1,
                'created_at' => current_time( 'mysql' ),
            ] );

            if ( ! $wpdb->insert_id ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'rsvp_failed', 'Nu s-a putut salva RSVP', [ 'status' => 500 ] );
            }

            $rsvp_id = (int) $wpdb->insert_id;
            $selected = array_values( array_unique( array_map( 'sanitize_text_field', (array) ( $p['gift_ids'] ?? [] ) ) ) );

            foreach ( $selected as $gift_id ) {
                $updated = $wpdb->query( $wpdb->prepare(
                    "UPDATE {$t['gifts']} SET status='reserved', reserved_by_rsvp_id=%d, reserved_at=%s WHERE token=%s AND gift_id=%s AND include_in_public=1 AND status='free'",
                    $rsvp_id,
                    current_time( 'mysql' ),
                    $token,
                    $gift_id
                ) );

                if ( $updated !== 1 ) {
                    $wpdb->query( 'ROLLBACK' );
                    return new WP_Error( 'gift_conflict', 'Cadou deja rezervat între timp.', [ 'status' => 409 ] );
                }
            }

            $wpdb->query( 'COMMIT' );
            teinvit_touch_invitation_activity( $token );

            return [ 'ok' => true ];
        },
    ] );
} );

add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'teinvit_cleanup_cron' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'teinvit_cleanup_cron' );
    }
} );

add_action( 'teinvit_cleanup_cron', 'teinvit_cleanup_expired_invitations' );
