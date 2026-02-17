<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_tables() {
    global $wpdb;
    return [
        'settings' => $wpdb->prefix . 'teinvit_client_settings',
        'versions' => $wpdb->prefix . 'teinvit_invitation_versions',
        'gifts'    => $wpdb->prefix . 'teinvit_gifts',
        'rsvp'     => $wpdb->prefix . 'teinvit_rsvp_submissions',
        'rsvp_g'   => $wpdb->prefix . 'teinvit_rsvp_gifts',
    ];
}

function teinvit_install_client_admin_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $t = teinvit_tables();
    $charset = $wpdb->get_charset_collate();

    dbDelta("CREATE TABLE {$t['settings']} (
        token varchar(191) NOT NULL,
        order_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        edits_free_remaining int NOT NULL DEFAULT 2,
        edits_paid_remaining int NOT NULL DEFAULT 0,
        gifts_free_capacity int NOT NULL DEFAULT 10,
        gifts_paid_capacity int NOT NULL DEFAULT 0,
        rsvp_flags longtext NULL,
        active_version int NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (token),
        KEY order_id (order_id),
        KEY user_id (user_id)
    ) $charset;");

    dbDelta("CREATE TABLE {$t['versions']} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        token varchar(191) NOT NULL,
        order_id bigint(20) unsigned NOT NULL,
        version int NOT NULL,
        data_json longtext NOT NULL,
        pdf_path text NULL,
        pdf_url text NULL,
        created_by_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY token_version (token, version),
        KEY token (token)
    ) $charset;");

    dbDelta("CREATE TABLE {$t['gifts']} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        token varchar(191) NOT NULL,
        position int NOT NULL,
        title varchar(255) NOT NULL,
        url text NULL,
        delivery_address text NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY token (token),
        KEY token_position (token, position)
    ) $charset;");

    dbDelta("CREATE TABLE {$t['rsvp']} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        token varchar(191) NOT NULL,
        guest_last_name varchar(191) NOT NULL,
        guest_first_name varchar(191) NOT NULL,
        phone varchar(20) NOT NULL,
        attendees_count int NOT NULL DEFAULT 1,
        fields longtext NULL,
        created_at datetime NOT NULL,
        ip varchar(64) NULL,
        PRIMARY KEY (id),
        KEY token (token)
    ) $charset;");

    dbDelta("CREATE TABLE {$t['rsvp_g']} (
        submission_id bigint(20) unsigned NOT NULL,
        gift_id bigint(20) unsigned NOT NULL,
        PRIMARY KEY (submission_id, gift_id),
        UNIQUE KEY uniq_gift_id (gift_id),
        KEY gift_id (gift_id)
    ) $charset;");
}

function teinvit_run_schema_migrations() {
    global $wpdb;
    $t = teinvit_tables();

    $dup_rows = $wpdb->get_results(
        "SELECT gift_id, MIN(submission_id) AS keep_submission_id
         FROM {$t['rsvp_g']}
         GROUP BY gift_id
         HAVING COUNT(*) > 1",
        ARRAY_A
    );

    if ( ! empty( $dup_rows ) ) {
        foreach ( $dup_rows as $dup ) {
            $gift_id = (int) $dup['gift_id'];
            $keep_submission_id = (int) $dup['keep_submission_id'];
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$t['rsvp_g']} WHERE gift_id = %d AND submission_id <> %d",
                    $gift_id,
                    $keep_submission_id
                )
            );
        }
    }

    $index = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$t['rsvp_g']} WHERE Key_name = %s", 'uniq_gift_id' ) );
    if ( ! $index ) {
        $wpdb->query( "ALTER TABLE {$t['rsvp_g']} ADD UNIQUE KEY uniq_gift_id (gift_id)" );
    }

    $idx_tp = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$t['gifts']} WHERE Key_name = %s", 'token_position' ) );
    if ( ! $idx_tp ) {
        $wpdb->query( "ALTER TABLE {$t['gifts']} ADD KEY token_position (token, position)" );
    }
}

function teinvit_get_order_id_by_token( $token ) {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_teinvit_token' AND meta_value = %s LIMIT 1",
        $token
    ) );
}

function teinvit_default_flags() {
    return [
        'civil' => false,
        'religious' => false,
        'party' => false,
        'kids' => false,
        'lodging' => false,
        'vegetarian' => false,
        'allergies' => false,
        'gifts_enabled' => false,
    ];
}

function teinvit_get_settings( $token ) {
    global $wpdb;
    $t = teinvit_tables();
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['settings']} WHERE token = %s", $token ), ARRAY_A );
    if ( ! $row ) {
        return null;
    }
    $row['rsvp_flags'] = json_decode( (string) $row['rsvp_flags'], true );
    if ( ! is_array( $row['rsvp_flags'] ) ) {
        $row['rsvp_flags'] = [];
    }
    $row['rsvp_flags'] = wp_parse_args( $row['rsvp_flags'], teinvit_default_flags() );
    return $row;
}

function teinvit_get_remaining_edits( $settings ) {
    return max( 0, (int) $settings['edits_free_remaining'] + (int) $settings['edits_paid_remaining'] );
}

function teinvit_get_gifts_capacity( $settings ) {
    return max( 0, (int) $settings['gifts_free_capacity'] + (int) $settings['gifts_paid_capacity'] );
}

function teinvit_update_settings( $token, $data ) {
    global $wpdb;
    $t = teinvit_tables();
    $data['updated_at'] = current_time( 'mysql' );
    if ( isset( $data['rsvp_flags'] ) && is_array( $data['rsvp_flags'] ) ) {
        $data['rsvp_flags'] = wp_json_encode( wp_parse_args( $data['rsvp_flags'], teinvit_default_flags() ) );
    }
    return $wpdb->update( $t['settings'], $data, [ 'token' => $token ] );
}

function teinvit_get_versions( $token ) {
    global $wpdb;
    $t = teinvit_tables();
    return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['versions']} WHERE token = %s ORDER BY version ASC", $token ), ARRAY_A );
}

function teinvit_get_active_version_data( $token ) {
    global $wpdb;
    $t = teinvit_tables();
    $settings = teinvit_get_settings( $token );
    if ( ! $settings ) {
        return null;
    }
    $v = (int) $settings['active_version'];
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['versions']} WHERE token = %s AND version = %d", $token, $v ), ARRAY_A );
    return $row;
}

function teinvit_get_gifts( $token ) {
    global $wpdb;
    $t = teinvit_tables();
    return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['gifts']} WHERE token = %s ORDER BY position ASC", $token ), ARRAY_A );
}

function teinvit_get_booked_gift_ids( $token ) {
    global $wpdb;
    $t = teinvit_tables();
    return $wpdb->get_col( $wpdb->prepare(
        "SELECT rg.gift_id FROM {$t['rsvp_g']} rg INNER JOIN {$t['rsvp']} rs ON rs.id = rg.submission_id WHERE rs.token = %s",
        $token
    ) );
}


function teinvit_get_order_primary_product_id( WC_Order $order ) {
    $items = $order->get_items();
    if ( empty( $items ) ) {
        return 0;
    }

    $item = reset( $items );
    $product = $item ? $item->get_product() : null;
    return $product ? (int) $product->get_id() : 0;
}

function teinvit_normalize_wapf_field_id( $raw ) {
    $id = is_scalar( $raw ) ? (string) $raw : '';
    $id = trim( $id );

    if ( strpos( $id, 'field_' ) === 0 ) {
        $id = substr( $id, 6 );
    }
    if ( strpos( $id, 'wapf[field_' ) === 0 && substr( $id, -1 ) === ']' ) {
        $id = substr( $id, 11, -1 );
    }

    return trim( $id );
}

function teinvit_extract_wapf_definitions_from_product( $product_id ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return [];
    }

    $known = array_fill_keys( TeInvit_Wedding_Preview_Renderer::get_wapf_field_ids(), true );
    $raw_meta = get_post_meta( $product_id );
    $definitions = [];

    $parse_options = function( $field ) {
        $out = [];
        $nodes = [];
        if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
            $nodes = $field['options'];
        } elseif ( isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
            $nodes = $field['choices'];
        }

        foreach ( $nodes as $opt ) {
            if ( is_string( $opt ) ) {
                $out[] = [ 'value' => $opt, 'label' => $opt ];
                continue;
            }
            if ( ! is_array( $opt ) ) {
                continue;
            }
            $label = (string) ( $opt['label'] ?? $opt['text'] ?? $opt['value'] ?? '' );
            $value = (string) ( $opt['value'] ?? $label );
            if ( $label === '' && $value === '' ) {
                continue;
            }
            $out[] = [ 'value' => $value, 'label' => $label !== '' ? $label : $value ];
        }

        return $out;
    };

    $parse_conditions = function( $field ) {
        $candidates = [];
        if ( isset( $field['conditions'] ) ) {
            $candidates[] = $field['conditions'];
        }
        if ( isset( $field['conditionals'] ) ) {
            $candidates[] = $field['conditionals'];
        }
        if ( isset( $field['conditional_logic'] ) ) {
            $candidates[] = $field['conditional_logic'];
        }

        $rules = [];
        $walk = function( $node ) use ( &$walk, &$rules ) {
            if ( is_array( $node ) ) {
                $field = $node['field'] ?? $node['field_id'] ?? $node['id'] ?? null;
                $value = $node['value'] ?? $node['compare'] ?? null;
                if ( $field !== null ) {
                    $fid = teinvit_normalize_wapf_field_id( $field );
                    if ( $fid !== '' ) {
                        $rules[] = [
                            'field' => $fid,
                            'operator' => (string) ( $node['operator'] ?? $node['comparison'] ?? '==' ),
                            'value' => is_scalar( $value ) ? (string) $value : '',
                        ];
                    }
                }
                foreach ( $node as $v ) {
                    if ( is_array( $v ) || is_object( $v ) ) {
                        $walk( $v );
                    }
                }
            } elseif ( is_object( $node ) ) {
                $walk( json_decode( wp_json_encode( $node ), true ) );
            }
        };

        foreach ( $candidates as $candidate ) {
            $walk( $candidate );
        }

        return $rules;
    };

    $consume = function( $node ) use ( &$consume, &$definitions, $known, $parse_options, $parse_conditions ) {
        if ( is_array( $node ) ) {
            if ( isset( $node['id'] ) && isset( $node['type'] ) ) {
                $id = teinvit_normalize_wapf_field_id( $node['id'] );
                if ( $id !== '' && isset( $known[ $id ] ) ) {
                    if ( ! isset( $definitions[ $id ] ) ) {
                        $definitions[ $id ] = [
                            'id' => $id,
                            'label' => (string) ( $node['label'] ?? $node['title'] ?? ('Field ' . $id) ),
                            'type' => (string) $node['type'],
                            'options' => $parse_options( $node ),
                            'conditions' => $parse_conditions( $node ),
                            'order' => isset( $node['order'] ) ? (int) $node['order'] : 0,
                        ];
                    }
                }
            }

            foreach ( $node as $value ) {
                if ( is_array( $value ) || is_object( $value ) ) {
                    $consume( $value );
                }
            }
            return;
        }

        if ( is_object( $node ) ) {
            $consume( json_decode( wp_json_encode( $node ), true ) );
        }
    };

    foreach ( $raw_meta as $meta_key => $meta_values ) {
        if ( strpos( (string) $meta_key, 'wapf' ) === false && strpos( (string) $meta_key, '_wapf' ) === false ) {
            continue;
        }

        foreach ( (array) $meta_values as $meta_value ) {
            $decoded = maybe_unserialize( $meta_value );
            if ( is_string( $decoded ) ) {
                $json = json_decode( $decoded, true );
                if ( json_last_error() === JSON_ERROR_NONE ) {
                    $decoded = $json;
                }
            }
            if ( is_array( $decoded ) || is_object( $decoded ) ) {
                $consume( $decoded );
            }
        }
    }

    if ( empty( $definitions ) ) {
        foreach ( TeInvit_Wedding_Preview_Renderer::get_wapf_field_ids() as $id ) {
            $definitions[ $id ] = [
                'id' => $id,
                'label' => 'Field ' . $id,
                'type' => 'text',
                'options' => [],
                'conditions' => [],
                'order' => 0,
            ];
        }
    }

    uasort( $definitions, function( $a, $b ) {
        if ( (int) $a['order'] === (int) $b['order'] ) {
            return strcmp( $a['id'], $b['id'] );
        }
        return (int) $a['order'] <=> (int) $b['order'];
    } );

    return array_values( $definitions );
}

function teinvit_render_wapf_field_admin( array $def, array $values ) {
    $id = $def['id'];
    $name = 'wapf[field_' . $id . ']';
    $type = strtolower( (string) ( $def['type'] ?? 'text' ) );
    $label = (string) ( $def['label'] ?? ('Field ' . $id ) );
    $value = isset( $values[ $id ] ) ? (string) $values[ $id ] : '';
    $options = is_array( $def['options'] ?? null ) ? $def['options'] : [];
    $conditions = is_array( $def['conditions'] ?? null ) ? $def['conditions'] : [];

    $attrs = 'data-wapf-field-id="' . esc_attr( $id ) . '"';
    if ( ! empty( $conditions ) ) {
        $attrs .= ' data-wapf-conditions="' . esc_attr( wp_json_encode( $conditions ) ) . '"';
    }

    ob_start();
    echo '<div class="teinvit-wapf-field" ' . $attrs . '>';
    echo '<label class="teinvit-wapf-label">' . esc_html( $label ) . '</label>';

    if ( in_array( $type, [ 'textarea' ], true ) ) {
        echo '<textarea name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
    } elseif ( in_array( $type, [ 'select', 'dropdown' ], true ) ) {
        echo '<select name="' . esc_attr( $name ) . '">';
        foreach ( $options as $opt ) {
            $opt_value = (string) ( $opt['value'] ?? '' );
            $opt_label = (string) ( $opt['label'] ?? $opt_value );
            echo '<option value="' . esc_attr( $opt_value ) . '"' . selected( $value, $opt_value, false ) . '>' . esc_html( $opt_label ) . '</option>';
        }
        echo '</select>';
    } elseif ( in_array( $type, [ 'radio' ], true ) ) {
        foreach ( $options as $idx => $opt ) {
            $opt_value = (string) ( $opt['value'] ?? '' );
            $opt_label = (string) ( $opt['label'] ?? $opt_value );
            $rid = 'teinvit-radio-' . esc_attr( $id ) . '-' . $idx;
            echo '<label for="' . $rid . '"><input id="' . $rid . '" type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $opt_value ) . '"' . checked( $value, $opt_value, false ) . '> ' . esc_html( $opt_label ) . '</label>';
        }
    } elseif ( in_array( $type, [ 'checkbox' ], true ) ) {
        $checkbox_name = $name . '[]';
        if ( ! empty( $options ) ) {
            foreach ( $options as $idx => $opt ) {
                $opt_value = (string) ( $opt['value'] ?? '' );
                $opt_label = (string) ( $opt['label'] ?? $opt_value );
                $cid = 'teinvit-check-' . esc_attr( $id ) . '-' . $idx;
                $checked = ( $value !== '' && ( $value === $opt_value || $value === $opt_label ) );
                echo '<label for="' . $cid . '"><input id="' . $cid . '" type="checkbox" name="' . esc_attr( $checkbox_name ) . '" value="' . esc_attr( $opt_value !== '' ? $opt_value : $opt_label ) . '"' . checked( $checked, true, false ) . '> ' . esc_html( $opt_label ) . '</label>';
            }
        } else {
            $cid = 'teinvit-check-' . esc_attr( $id );
            $checked = $value !== '';
            echo '<label for="' . $cid . '"><input id="' . $cid . '" type="checkbox" name="' . esc_attr( $checkbox_name ) . '" value="1"' . checked( $checked, true, false ) . '> ' . esc_html( $label ) . '</label>';
        }
    } else {
        echo '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
    }

    echo '</div>';
    return ob_get_clean();
}

function teinvit_build_initial_snapshot( $order_id, $token ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $settings = teinvit_get_settings( $token );
    if ( ! $settings ) {
        global $wpdb;
        $t = teinvit_tables();
        $wpdb->insert( $t['settings'], [
            'token' => $token,
            'order_id' => $order_id,
            'user_id' => (int) $order->get_user_id(),
            'edits_free_remaining' => 2,
            'edits_paid_remaining' => 0,
            'gifts_free_capacity' => 10,
            'gifts_paid_capacity' => 0,
            'rsvp_flags' => wp_json_encode( teinvit_default_flags() ),
            'active_version' => 0,
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ] );
    }

    $versions = teinvit_get_versions( $token );
    if ( ! empty( $versions ) ) {
        return;
    }

    $invitation = TeInvit_Wedding_Preview_Renderer::get_order_invitation_data( $order );
    $wapf_map = TeInvit_Wedding_Preview_Renderer::get_order_wapf_field_map( $order );
    $payload = [
        'invitation' => $invitation,
        'wapf_fields' => $wapf_map,
    ];

    global $wpdb;
    $t = teinvit_tables();
    $wpdb->insert( $t['versions'], [
        'token' => $token,
        'order_id' => $order_id,
        'version' => 0,
        'data_json' => wp_json_encode( $payload ),
        'pdf_url' => $order->get_meta( '_teinvit_pdf_url' ),
        'pdf_path' => '',
        'created_by_user_id' => (int) $order->get_user_id(),
        'created_at' => current_time( 'mysql' ),
    ] );
}
add_action( 'teinvit_token_generated', 'teinvit_build_initial_snapshot', 20, 2 );

function teinvit_client_admin_rewrite() {
    add_rewrite_rule( '^client-admin/([^/]+)/?$', 'index.php?teinvit_client_admin_token=$matches[1]', 'top' );
}
add_action( 'init', 'teinvit_client_admin_rewrite' );

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'teinvit_client_admin_token';
    return $vars;
} );

function teinvit_client_admin_template_redirect() {
    $token = get_query_var( 'teinvit_client_admin_token' );
    if ( ! $token ) {
        return;
    }

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( home_url( '/client-admin/' . rawurlencode( $token ) ) ) );
        exit;
    }

    status_header( 200 );
    nocache_headers();
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    wp_head();
    echo '</head><body class="teinvit-client-admin-page">';
    echo do_shortcode( '[teinvit_client_admin token="' . esc_attr( $token ) . '"]' );
    wp_footer();
    echo '</body></html>';
    exit;
}
add_action( 'template_redirect', 'teinvit_client_admin_template_redirect', 1 );

function teinvit_client_admin_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'token' => '' ], $atts );
    $token = $atts['token'] ?: get_query_var( 'teinvit_client_admin_token' );
    if ( ! $token ) {
        return '<p>Token invalid.</p>';
    }

    $settings = teinvit_get_settings( $token );
    if ( ! $settings ) {
        return '<p>Configurația nu există.</p>';
    }

    if ( (int) $settings['user_id'] !== get_current_user_id() ) {
        return '<p>Access denied.</p>';
    }

    $active = teinvit_get_active_version_data( $token );
    $payload = $active ? json_decode( (string) $active['data_json'], true ) : [];
    $wapf = isset( $payload['wapf_fields'] ) && is_array( $payload['wapf_fields'] ) ? $payload['wapf_fields'] : [];
    $remaining = teinvit_get_remaining_edits( $settings );

    $order = wc_get_order( (int) $settings['order_id'] );
    $order_product_id = $order ? teinvit_get_order_primary_product_id( $order ) : 0;
    $wapf_definitions = teinvit_extract_wapf_definitions_from_product( $order_product_id );

    $title_names = trim( implode( ' & ', array_filter( [ $wapf['6963a95e66425'] ?? '', $wapf['6963aa37412e4'] ?? '' ] ) ) );
    if ( $title_names === '' && ! empty( $payload['invitation']['names'] ) ) {
        $title_names = (string) $payload['invitation']['names'];
    }

    wp_enqueue_style( 'teinvit-client-admin', TEINVIT_WEDDING_MODULE_URL . 'assets/client-admin.css', [], TEINVIT_CORE_VERSION );
    wp_enqueue_script( 'teinvit-client-admin', TEINVIT_WEDDING_MODULE_URL . 'assets/client-admin.js', [ 'jquery' ], TEINVIT_CORE_VERSION, true );
    wp_localize_script( 'teinvit-client-admin', 'TEINVIT_CLIENT_ADMIN', [
        'token' => $token,
        'rest' => esc_url_raw( rest_url( 'teinvit/v1' ) ),
        'nonce' => wp_create_nonce( 'wp_rest' ),
        'settings' => $settings,
        'versions' => teinvit_get_versions( $token ),
        'gifts' => teinvit_get_gifts( $token ),
        'remaining' => $remaining,
        'capacity' => teinvit_get_gifts_capacity( $settings ),
        'bookedGiftIds' => array_map( 'intval', teinvit_get_booked_gift_ids( $token ) ),
        'buyEditsUrl' => esc_url_raw( teinvit_get_purchase_url( 'edits', $token ) ),
        'buyGiftsUrl' => esc_url_raw( teinvit_get_purchase_url( 'gifts', $token ) ),
        'inviteUrl' => home_url( '/i/' . $token ),
        'wapfDefinitions' => $wapf_definitions,
        'wapfValues' => $wapf,
    ] );

    $preview = '';
    if ( ! empty( $payload['invitation'] ) && is_array( $payload['invitation'] ) ) {
        $preview = TeInvit_Wedding_Preview_Renderer::render_from_invitation_data( $payload['invitation'], $order );
    } elseif ( $order ) {
        $preview = TeInvit_Wedding_Preview_Renderer::render_from_order( $order );
    }

    ob_start();
    ?>
    <div class="teinvit-client-admin" data-token="<?php echo esc_attr( $token ); ?>">
        <h1>Administrare Invitație - <?php echo esc_html( $title_names ); ?></h1>
        <div class="section edit-section">
            <div class="left"><?php echo $preview; ?></div>
            <div class="right">
                <div id="teinvit-wapf-fields">
                <?php foreach ( $wapf_definitions as $def ) : ?>
                    <?php echo teinvit_render_wapf_field_admin( $def, $wapf ); ?>
                <?php endforeach; ?>
                </div>
                <p class="edits-counter" id="teinvit-edits-counter"></p>
                <button id="teinvit-save-version" class="button button-primary">Salvează modificările</button>
                <a id="teinvit-buy-edits" class="button" href="<?php echo esc_url( teinvit_get_purchase_url( 'edits', $token ) ); ?>">Cumpără modificări suplimentare</a>
            </div>
        </div>

        <div class="section">
            <h3>Alege ce variantă de invitație este afișată în pagina invitaților</h3>
            <select id="teinvit-active-version"></select>
            <button id="teinvit-set-active" class="button">Setează ca activă</button>
            <p><a href="<?php echo esc_url( home_url( '/i/' . $token ) ); ?>" target="_blank">Vezi pagina pentru invitați</a></p>
        </div>

        <div class="section">
            <h3>Informații invitați</h3>
            <p>Bifați informațiile care doriți să apară în pagina invitaților:</p>
            <div id="teinvit-rsvp-flags"></div>
            <button id="teinvit-save-flags" class="button">Salveaza Informatii</button>
        </div>

        <div class="section" id="teinvit-gifts-section">
            <h3>Cadouri</h3>
            <p id="teinvit-gifts-counter"></p>
            <div id="teinvit-gifts-list"></div>
            <button id="teinvit-add-gift" class="button">Adaugă Cadou</button>
            <a id="teinvit-buy-gifts" class="button" href="<?php echo esc_url( teinvit_get_purchase_url( 'gifts', $token ) ); ?>">Cumpără pachet +10 cadouri</a>
            <button id="teinvit-save-gifts" class="button">Salvează cadouri</button>
        </div>

        <div class="section">
            <h3>Raport invitați</h3>
            <div id="teinvit-rsvp-report"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'teinvit_client_admin', 'teinvit_client_admin_shortcode' );

function teinvit_client_admin_owner_check( $token ) {
    $settings = teinvit_get_settings( $token );
    if ( ! $settings ) {
        return new WP_Error( 'not_found', 'Token invalid', [ 'status' => 404 ] );
    }
    if ( ! is_user_logged_in() || (int) $settings['user_id'] !== get_current_user_id() ) {
        return new WP_Error( 'forbidden', 'Access denied', [ 'status' => 403 ] );
    }
    return $settings;
}

function teinvit_pdf_filename_for_version( WC_Order $order, $version ) {
    $items = $order->get_items();
    $product_name = 'Produs';
    if ( ! empty( $items ) ) {
        $first = reset( $items );
        $product_name = $first->get_name() ?: $product_name;
    }
    $base = sanitize_file_name( $product_name ) . ' - ' . $order->get_id();
    if ( (int) $version > 0 ) {
        $base .= ' - v' . (int) $version;
    }
    return $base . '.pdf';
}

function teinvit_generate_pdf_for_version( $token, $order_id, $filename ) {
    $payload = [ 'token' => $token, 'order_id' => (int) $order_id, 'filename' => $filename ];
    $response = wp_remote_post( TEINVIT_NODE_ENDPOINT, [
        'timeout' => 240,
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body' => wp_json_encode( $payload ),
    ] );
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $data['status'] ) || $data['status'] !== 'ok' ) {
        return new WP_Error( 'pdf_error', 'PDF generation failed.' );
    }
    $pdf_url = esc_url_raw( 'https://pdf.teinvit.com' . $data['pdf_url'] );
    return [
        'pdf_url' => $pdf_url,
        'pdf_path' => '/wp-content/uploads/teinvit/orders/' . (int) $order_id . '/' . $filename,
    ];
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'teinvit/v1', '/client-admin/(?P<token>[a-zA-Z0-9\-]+)/data', [
        'methods' => 'GET',
        'permission_callback' => function( $req ) { return ! is_wp_error( teinvit_client_admin_owner_check( $req['token'] ) ); },
        'callback' => function( $req ) {
            $token = $req['token'];
            $settings = teinvit_get_settings( $token );
            $active = teinvit_get_active_version_data( $token );
            return [
                'settings' => $settings,
                'versions' => teinvit_get_versions( $token ),
                'active' => $active,
                'gifts' => teinvit_get_gifts( $token ),
                'rsvp' => teinvit_get_rsvp_report( $token ),
            ];
        }
    ] );

    register_rest_route( 'teinvit/v1', '/client-admin/(?P<token>[a-zA-Z0-9\-]+)/save-version', [
        'methods' => 'POST',
        'permission_callback' => function( $req ) { return ! is_wp_error( teinvit_client_admin_owner_check( $req['token'] ) ); },
        'callback' => function( $req ) {
            global $wpdb;
            $token = $req['token'];
            $settings = teinvit_get_settings( $token );
            if ( teinvit_get_remaining_edits( $settings ) <= 0 ) {
                return new WP_Error( 'no_edits', 'Nu mai ai modificări.', [ 'status' => 400 ] );
            }
            $order = wc_get_order( (int) $settings['order_id'] );
            $payload = $req->get_json_params();
            $inv = isset( $payload['invitation'] ) && is_array( $payload['invitation'] ) ? $payload['invitation'] : [];
            $wapf = isset( $payload['wapf_fields'] ) && is_array( $payload['wapf_fields'] ) ? $payload['wapf_fields'] : [];

            $t = teinvit_tables();
            $max = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(version) FROM {$t['versions']} WHERE token=%s", $token ) );
            $new_v = $max + 1;
            $filename = teinvit_pdf_filename_for_version( $order, $new_v );
            $pdf = teinvit_generate_pdf_for_version( $token, $settings['order_id'], $filename );
            if ( is_wp_error( $pdf ) ) {
                return $pdf;
            }

            $wpdb->insert( $t['versions'], [
                'token' => $token,
                'order_id' => (int) $settings['order_id'],
                'version' => $new_v,
                'data_json' => wp_json_encode( [ 'invitation' => $inv, 'wapf_fields' => $wapf ] ),
                'pdf_path' => $pdf['pdf_path'],
                'pdf_url' => $pdf['pdf_url'],
                'created_by_user_id' => get_current_user_id(),
                'created_at' => current_time( 'mysql' ),
            ] );

            if ( (int) $settings['edits_free_remaining'] > 0 ) {
                $settings['edits_free_remaining'] = (int) $settings['edits_free_remaining'] - 1;
            } else {
                $settings['edits_paid_remaining'] = max( 0, (int) $settings['edits_paid_remaining'] - 1 );
            }
            teinvit_update_settings( $token, [
                'edits_free_remaining' => (int) $settings['edits_free_remaining'],
                'edits_paid_remaining' => (int) $settings['edits_paid_remaining'],
            ] );

            return [ 'ok' => true, 'version' => $new_v, 'pdf' => $pdf ];
        }
    ] );

    register_rest_route( 'teinvit/v1', '/client-admin/(?P<token>[a-zA-Z0-9\-]+)/set-active-version', [
        'methods' => 'POST',
        'permission_callback' => function( $req ) { return ! is_wp_error( teinvit_client_admin_owner_check( $req['token'] ) ); },
        'callback' => function( $req ) {
            $version = (int) ( $req->get_json_params()['version'] ?? 0 );
            teinvit_update_settings( $req['token'], [ 'active_version' => $version ] );
            return [ 'ok' => true ];
        }
    ] );

    register_rest_route( 'teinvit/v1', '/client-admin/(?P<token>[a-zA-Z0-9\-]+)/flags', [
        'methods' => 'POST',
        'permission_callback' => function( $req ) { return ! is_wp_error( teinvit_client_admin_owner_check( $req['token'] ) ); },
        'callback' => function( $req ) {
            $flags = $req->get_json_params()['flags'] ?? [];
            teinvit_update_settings( $req['token'], [ 'rsvp_flags' => $flags ] );
            return [ 'ok' => true ];
        }
    ] );

    register_rest_route( 'teinvit/v1', '/client-admin/(?P<token>[a-zA-Z0-9\-]+)/gifts', [
        'methods' => 'POST',
        'permission_callback' => function( $req ) { return ! is_wp_error( teinvit_client_admin_owner_check( $req['token'] ) ); },
        'callback' => function( $req ) {
            global $wpdb;
            $token = $req['token'];
            $settings = teinvit_get_settings( $token );
            $capacity = teinvit_get_gifts_capacity( $settings );
            $input_gifts = (array) ( $req->get_json_params()['gifts'] ?? [] );

            $normalized = [];
            $next_position = 1;
            foreach ( $input_gifts as $gift ) {
                $title = sanitize_text_field( $gift['title'] ?? '' );
                if ( $title === '' ) {
                    continue;
                }
                $normalized[] = [
                    'id' => isset( $gift['id'] ) && $gift['id'] !== '' ? (int) $gift['id'] : 0,
                    'title' => $title,
                    'url' => esc_url_raw( $gift['url'] ?? '' ),
                    'delivery_address' => sanitize_textarea_field( $gift['delivery_address'] ?? '' ),
                    'position' => isset( $gift['position'] ) ? max( 1, (int) $gift['position'] ) : $next_position,
                ];
                $next_position++;
            }

            if ( count( $normalized ) > $capacity ) {
                return new WP_Error( 'capacity', 'Depășește capacitatea.', [ 'status' => 400 ] );
            }

            $t = teinvit_tables();
            $existing = teinvit_get_gifts( $token );
            $existing_by_id = [];
            foreach ( $existing as $gift ) {
                $existing_by_id[ (int) $gift['id'] ] = $gift;
            }

            $incoming_ids = [];
            foreach ( $normalized as $gift ) {
                if ( $gift['id'] > 0 ) {
                    $incoming_ids[] = $gift['id'];
                }
            }
            $incoming_ids = array_values( array_unique( $incoming_ids ) );

            $existing_ids = array_keys( $existing_by_id );
            $ids_to_delete = array_values( array_diff( $existing_ids, $incoming_ids ) );

            $booked_ids = array_map( 'intval', teinvit_get_booked_gift_ids( $token ) );
            $blocked_delete = array_values( array_intersect( $ids_to_delete, $booked_ids ) );
            if ( ! empty( $blocked_delete ) ) {
                $blocked_titles = [];
                foreach ( $blocked_delete as $bid ) {
                    if ( isset( $existing_by_id[ $bid ]['title'] ) ) {
                        $blocked_titles[] = $existing_by_id[ $bid ]['title'];
                    }
                }
                return new WP_Error(
                    'gift_booked_delete',
                    'Nu poți șterge un cadou deja ales de invitați.',
                    [ 'status' => 400, 'gift_ids' => $blocked_delete, 'gift_titles' => $blocked_titles ]
                );
            }

            $wpdb->query( 'START TRANSACTION' );
            try {
                foreach ( $normalized as $gift ) {
                    if ( $gift['id'] > 0 && isset( $existing_by_id[ $gift['id'] ] ) ) {
                        $ok = $wpdb->update(
                            $t['gifts'],
                            [
                                'position' => $gift['position'],
                                'title' => $gift['title'],
                                'url' => $gift['url'],
                                'delivery_address' => $gift['delivery_address'],
                                'updated_at' => current_time( 'mysql' ),
                            ],
                            [ 'id' => $gift['id'], 'token' => $token ]
                        );
                        if ( $ok === false ) {
                            throw new Exception( 'update_failed' );
                        }
                        continue;
                    }

                    $ok = $wpdb->insert( $t['gifts'], [
                        'token' => $token,
                        'position' => $gift['position'],
                        'title' => $gift['title'],
                        'url' => $gift['url'],
                        'delivery_address' => $gift['delivery_address'],
                        'created_at' => current_time( 'mysql' ),
                        'updated_at' => current_time( 'mysql' ),
                    ] );
                    if ( $ok === false ) {
                        throw new Exception( 'insert_failed' );
                    }
                }

                if ( ! empty( $ids_to_delete ) ) {
                    foreach ( $ids_to_delete as $id_to_delete ) {
                        $ok = $wpdb->delete( $t['gifts'], [ 'id' => (int) $id_to_delete, 'token' => $token ] );
                        if ( $ok === false ) {
                            throw new Exception( 'delete_failed' );
                        }
                    }
                }

                $wpdb->query( 'COMMIT' );
            } catch ( Exception $e ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'gift_save_failed', 'Nu s-au putut salva cadourile.', [ 'status' => 500 ] );
            }

            return [ 'ok' => true, 'gifts' => teinvit_get_gifts( $token ) ];
        }
    ] );

    register_rest_route( 'teinvit/v1', '/client-admin/(?P<token>[a-zA-Z0-9\-]+)/rsvp', [
        'methods' => 'GET',
        'permission_callback' => function( $req ) { return ! is_wp_error( teinvit_client_admin_owner_check( $req['token'] ) ); },
        'callback' => function( $req ) { return teinvit_get_rsvp_report( $req['token'] ); }
    ] );

    register_rest_route( 'teinvit/v1', '/invite/(?P<token>[a-zA-Z0-9\-]+)/rsvp', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => 'teinvit_submit_rsvp',
    ] );
} );

function teinvit_submit_rsvp( WP_REST_Request $req ) {
    global $wpdb;
    $token = $req['token'];
    $settings = teinvit_get_settings( $token );
    if ( ! $settings ) {
        return new WP_Error( 'not_found', 'Token invalid', [ 'status' => 404 ] );
    }

    $p = $req->get_json_params();
    $phone = preg_replace( '/\D+/', '', (string) ( $p['phone'] ?? '' ) );
    if ( ! preg_match( '/^07\d{8}$/', $phone ) ) {
        return new WP_Error( 'phone', 'Telefon invalid.', [ 'status' => 400 ] );
    }

    $t = teinvit_tables();
    $gift_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $p['gift_ids'] ?? [] ) ) ) ) );

    $gift_map = [];
    if ( ! empty( $gift_ids ) ) {
        $placeholders = implode( ',', array_fill( 0, count( $gift_ids ), '%d' ) );
        $sql = $wpdb->prepare(
            "SELECT id, title FROM {$t['gifts']} WHERE token = %s AND id IN ($placeholders)",
            array_merge( [ $token ], $gift_ids )
        );
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        foreach ( $rows as $row ) {
            $gift_map[ (int) $row['id'] ] = $row['title'];
        }
    }

    $invalid = [];
    foreach ( $gift_ids as $gid ) {
        if ( ! isset( $gift_map[ $gid ] ) ) {
            $invalid[] = $gid;
        }
    }
    if ( ! empty( $invalid ) ) {
        return new WP_Error( 'invalid_gifts', 'Selecția de cadouri conține valori invalide.', [ 'status' => 400, 'gift_ids' => $invalid ] );
    }

    $wpdb->query( 'START TRANSACTION' );

    $ok = $wpdb->insert( $t['rsvp'], [
        'token' => $token,
        'guest_last_name' => sanitize_text_field( $p['guest_last_name'] ?? '' ),
        'guest_first_name' => sanitize_text_field( $p['guest_first_name'] ?? '' ),
        'phone' => $phone,
        'attendees_count' => max( 1, (int) ( $p['attendees_count'] ?? 1 ) ),
        'fields' => wp_json_encode( $p['fields'] ?? [] ),
        'created_at' => current_time( 'mysql' ),
        'ip' => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
    ] );

    if ( $ok === false ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'rsvp_insert_failed', 'Nu s-a putut salva RSVP.', [ 'status' => 500 ] );
    }

    $submission_id = (int) $wpdb->insert_id;
    $conflicts = [];

    foreach ( $gift_ids as $gift_id ) {
        $gift_ok = $wpdb->insert( $t['rsvp_g'], [ 'submission_id' => $submission_id, 'gift_id' => $gift_id ] );
        if ( $gift_ok === false ) {
            if ( stripos( (string) $wpdb->last_error, 'Duplicate entry' ) !== false ) {
                $conflicts[] = [
                    'id' => $gift_id,
                    'title' => $gift_map[ $gift_id ] ?? ( 'Cadou #' . $gift_id ),
                ];
                continue;
            }

            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'rsvp_gift_insert_failed', 'Nu s-au putut salva cadourile.', [ 'status' => 500 ] );
        }
    }

    if ( ! empty( $conflicts ) ) {
        $wpdb->query( 'ROLLBACK' );
        $titles = wp_list_pluck( $conflicts, 'title' );
        return new WP_Error(
            'gift_already_booked',
            'Unul sau mai multe cadouri au fost deja rezervate: ' . implode( ', ', $titles ) . '. Reîncarcă și alege altul.',
            [ 'status' => 409, 'conflicts' => $conflicts ]
        );
    }

    $wpdb->query( 'COMMIT' );
    return [ 'ok' => true ];
}

function teinvit_get_rsvp_report( $token ) {
    global $wpdb;
    $t = teinvit_tables();
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['rsvp']} WHERE token=%s ORDER BY created_at DESC", $token ), ARRAY_A );
    foreach ( $rows as &$row ) {
        $row['fields'] = json_decode( (string) $row['fields'], true ) ?: [];
        $row['gift_ids'] = $wpdb->get_col( $wpdb->prepare( "SELECT gift_id FROM {$t['rsvp_g']} WHERE submission_id=%d", $row['id'] ) );
    }
    return $rows;
}

function teinvit_find_product_id_by_name( $name ) {
    $post = get_page_by_title( $name, OBJECT, 'product' );
    return $post ? (int) $post->ID : 0;
}

function teinvit_get_purchase_url( $type, $token ) {
    $product_name = $type === 'gifts' ? 'Pachet cadouri suplimentare (+10)' : 'Modificări suplimentare invitație';
    $pid = teinvit_find_product_id_by_name( $product_name );
    if ( ! $pid ) {
        return wc_get_cart_url();
    }
    return add_query_arg( [ 'add-to-cart' => $pid, 'quantity' => 1, 'teinvit_token' => rawurlencode( $token ) ], wc_get_cart_url() );
}

add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id ) {
    $token = isset( $_REQUEST['teinvit_token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['teinvit_token'] ) ) : '';
    if ( $token ) {
        $cart_item_data['teinvit_token'] = $token;
    }
    return $cart_item_data;
}, 10, 2 );

add_action( 'woocommerce_checkout_create_order_line_item', function( $item, $cart_item_key, $values ) {
    if ( ! empty( $values['teinvit_token'] ) ) {
        $item->add_meta_data( '_teinvit_token_target', sanitize_text_field( $values['teinvit_token'] ), true );
    }
}, 10, 3 );

add_action( 'woocommerce_order_status_completed', function( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    $edits_name = 'Modificări suplimentare invitație';
    $gifts_name = 'Pachet cadouri suplimentare (+10)';

    foreach ( $order->get_items() as $item ) {
        $target_token = $item->get_meta( '_teinvit_token_target', true );
        if ( ! $target_token ) continue;
        $settings = teinvit_get_settings( $target_token );
        if ( ! $settings ) continue;

        $qty = (int) $item->get_quantity();
        $name = $item->get_name();
        if ( $name === $edits_name ) {
            teinvit_update_settings( $target_token, [
                'edits_paid_remaining' => (int) $settings['edits_paid_remaining'] + $qty,
            ] );
        }
        if ( $name === $gifts_name ) {
            teinvit_update_settings( $target_token, [
                'gifts_paid_capacity' => (int) $settings['gifts_paid_capacity'] + ( $qty * 10 ),
            ] );
        }
    }
}, 20 );

function teinvit_render_guest_sections( WC_Order $order ) {
    $token = teinvit_get_token_by_order( $order->get_id() );
    $settings = teinvit_get_settings( $token );
    if ( ! $settings ) return;

    $flags = $settings['rsvp_flags'];
    $gifts = teinvit_get_gifts( $token );
    $booked = array_map( 'intval', teinvit_get_booked_gift_ids( $token ) );

    wp_enqueue_style( 'teinvit-client-admin', TEINVIT_WEDDING_MODULE_URL . 'assets/client-admin.css', [], TEINVIT_CORE_VERSION );
    wp_enqueue_script( 'teinvit-client-admin', TEINVIT_WEDDING_MODULE_URL . 'assets/client-admin.js', [ 'jquery' ], TEINVIT_CORE_VERSION, true );
    wp_localize_script( 'teinvit-client-admin', 'TEINVIT_GUEST', [
        'token' => $token,
        'rest' => esc_url_raw( rest_url( 'teinvit/v1' ) ),
        'flags' => $flags,
        'gifts' => $gifts,
        'bookedGiftIds' => $booked,
    ] );

    echo '<div id="teinvit-guest-rsvp"></div>';
}
add_action( 'teinvit_guest_page_preview', 'teinvit_render_guest_sections', 20 );
