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
        edits_admin_remaining int NOT NULL DEFAULT 0,
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
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return 0;
    }

    if ( function_exists( 'teinvit_get_order_token_row' ) ) {
        $row = teinvit_get_order_token_row( $token );
        if ( is_array( $row ) && ! empty( $row['order_id'] ) ) {
            return (int) $row['order_id'];
        }
    }

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
    return max( 0, (int) $settings['edits_free_remaining'] + (int) ( $settings['edits_admin_remaining'] ?? 0 ) + (int) $settings['edits_paid_remaining'] );
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


function teinvit_order_contains_product_id( WC_Order $order, $product_id ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return false;
    }

    foreach ( $order->get_items( 'line_item' ) as $item ) {
        $pid = (int) $item->get_product_id();
        $vid = (int) $item->get_variation_id();
        if ( $pid === $product_id || $vid === $product_id ) {
            return true;
        }
    }

    return false;
}

function teinvit_order_contains_any_product_ids( WC_Order $order, array $product_ids ) {
    $normalized = array_values( array_filter( array_map( 'intval', $product_ids ), static function( $id ) {
        return $id > 0;
    } ) );

    if ( empty( $normalized ) ) {
        return false;
    }

    foreach ( $order->get_items( 'line_item' ) as $item ) {
        $pid = (int) $item->get_product_id();
        $vid = (int) $item->get_variation_id();
        if ( in_array( $pid, $normalized, true ) || in_array( $vid, $normalized, true ) ) {
            return true;
        }
    }

    return false;
}

function teinvit_token_has_premium_upgrade_addon( $token ) {
    global $wpdb;

    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return false;
    }

    if ( function_exists( 'teinvit_token_has_premium_admin_grant' ) && teinvit_token_has_premium_admin_grant( $token ) ) {
        return true;
    }

    if ( function_exists( 'teinvit_get_invitation' ) ) {
        $inv = teinvit_get_invitation( $token );
        $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : [];
        if ( ! empty( $config['premium_upgrade_active'] ) ) {
            return true;
        }
    }

    if ( function_exists( 'teinvit_order_token_addon_active_premium_upgrade_state' ) ) {
        $addon_ledger_state = teinvit_order_token_addon_active_premium_upgrade_state( $token );
        if ( $addon_ledger_state !== null ) {
            return (bool) $addon_ledger_state;
        }
    }

    $catalog = function_exists( 'teinvit_get_catalog_for_token' ) ? teinvit_get_catalog_for_token( $token ) : ( function_exists( 'teinvit_get_custom_product_ids' ) ? teinvit_get_custom_product_ids() : [] );
    $upgrade_ids = function_exists( 'teinvit_catalog_role_ids' ) ? teinvit_catalog_role_ids( $catalog, 'premium_upgrade_addon_ids' ) : [];
    if ( empty( $upgrade_ids ) ) {
        return false;
    }

    $statuses = [ 'wc-processing', 'wc-completed' ];
    $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

    $sql = "
        SELECT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_token ON pm_token.post_id = p.ID AND pm_token.meta_key = '_teinvit_token_target' AND pm_token.meta_value = %s
        INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_id = p.ID AND oi.order_item_type = 'line_item'
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oim.order_item_id = oi.order_item_id AND oim.meta_key = '_product_id' AND oim.meta_value = %s
        WHERE p.post_type = 'shop_order'
          AND p.post_status IN ($status_placeholders)
        LIMIT 1
    ";

    foreach ( $upgrade_ids as $upgrade_id ) {
        $args = array_merge( [ $token, (string) (int) $upgrade_id ], $statuses );
        $order_id = (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
        if ( $order_id > 0 ) {
            return true;
        }
    }

    return false;
}

function teinvit_resolve_token_product_state( $token ) {
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return 'premium_native';
    }

    if ( function_exists( 'teinvit_get_order_token_row' ) ) {
        $order_token_row = teinvit_get_order_token_row( $token );
        if ( is_array( $order_token_row ) ) {
            $package_type = sanitize_key( (string) ( $order_token_row['package_type'] ?? '' ) );
            if ( ( $package_type === '' || $package_type === 'unknown' ) && function_exists( 'teinvit_order_token_package_type_for_product' ) ) {
                $package_type = teinvit_order_token_package_type_for_product(
                    (int) ( $order_token_row['product_id'] ?? 0 ),
                    (int) ( $order_token_row['variation_id'] ?? 0 ),
                    (string) ( $order_token_row['vertical'] ?? '' )
                );
            }
            if ( $package_type === 'premium' ) {
                return 'premium_native';
            }
            if ( $package_type === 'basic' ) {
                return teinvit_token_has_premium_upgrade_addon( $token ) ? 'basic_upgraded' : 'basic_pure';
            }
        }
    }

    $catalog = function_exists( 'teinvit_get_catalog_for_token' ) ? teinvit_get_catalog_for_token( $token ) : ( function_exists( 'teinvit_get_custom_product_ids' ) ? teinvit_get_custom_product_ids() : [] );
    $basic_ids = function_exists( 'teinvit_catalog_role_ids' ) ? teinvit_catalog_role_ids( $catalog, 'basic_product_ids' ) : [];
    $premium_native_ids = function_exists( 'teinvit_catalog_role_ids' ) ? teinvit_catalog_role_ids( $catalog, 'premium_native_product_ids' ) : [];

    $order_id = function_exists( 'teinvit_get_order_id_by_token' ) ? (int) teinvit_get_order_id_by_token( $token ) : 0;
    $order = $order_id > 0 ? wc_get_order( $order_id ) : null;

    if ( $order instanceof WC_Order ) {
        if ( teinvit_order_contains_any_product_ids( $order, $premium_native_ids ) ) {
            return 'premium_native';
        }

        if ( teinvit_order_contains_any_product_ids( $order, $basic_ids ) ) {
            return teinvit_token_has_premium_upgrade_addon( $token ) ? 'basic_upgraded' : 'basic_pure';
        }
    }

    return teinvit_token_has_premium_upgrade_addon( $token ) ? 'basic_upgraded' : 'premium_native';
}

function teinvit_capabilities_for_token( $token ) {
    $state = teinvit_resolve_token_product_state( $token );

    $capabilities = [
        'state' => $state,
        'can_save_invitation_info' => true,
        'can_save_rsvp_config' => true,
        'can_share_invitation' => true,
        'can_set_active_version' => true,
        'can_save_version_snapshot' => true,
        'can_manage_gifts' => true,
        'can_buy_extra_edits' => true,
        'can_buy_extra_gifts' => true,
        'can_buy_premium_upgrade' => false,
    ];

    if ( $state === 'basic_pure' ) {
        $capabilities['can_save_invitation_info'] = false;
        $capabilities['can_save_rsvp_config'] = false;
        $capabilities['can_share_invitation'] = false;
        $capabilities['can_set_active_version'] = false;
        $capabilities['can_save_version_snapshot'] = false;
        $capabilities['can_manage_gifts'] = false;
        $capabilities['can_buy_extra_edits'] = false;
        $capabilities['can_buy_extra_gifts'] = false;
        $capabilities['can_buy_premium_upgrade'] = true;
    }

    return $capabilities;
}

function teinvit_catalog_ids_to_csv( $ids ) {
    $ids = function_exists( 'teinvit_parse_product_ids_csv' ) ? teinvit_parse_product_ids_csv( $ids ) : array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
    return implode( ',', $ids );
}

function teinvit_catalog_slots_map_to_csv( $map ) {
    if ( ! is_array( $map ) || empty( $map ) ) {
        return '';
    }

    $pairs = [];
    foreach ( $map as $product_id => $slots ) {
        $product_id = (int) $product_id;
        $slots = (int) $slots;
        if ( $product_id > 0 && $slots > 0 ) {
            $pairs[] = $product_id . ':' . $slots;
        }
    }

    return implode( ',', $pairs );
}

function teinvit_catalog_first_id( array $catalog, $role_key ) {
    $ids = function_exists( 'teinvit_catalog_role_ids' ) ? teinvit_catalog_role_ids( $catalog, $role_key ) : [];
    return ! empty( $ids ) ? (int) $ids[0] : 0;
}

function teinvit_gifts_used_count_for_token( $token ) {
    global $wpdb;
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return 0;
    }
    $gifts_table = function_exists( 'teinvit_gifts_table_for_token' ) ? teinvit_gifts_table_for_token( $token ) : '';
    if ( $gifts_table === '' && function_exists( 'teinvit_db_tables' ) ) {
        $t = teinvit_db_tables();
        $gifts_table = (string) ( $t['gifts'] ?? '' );
    }
    if ( $gifts_table === '' ) {
        return 0;
    }

    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$gifts_table} WHERE token=%s AND (gift_name<>'' OR gift_link<>'')",
            $token
        )
    );
    return max( 0, $count );
}

function teinvit_gifts_allocation_sort_key( array $allocation ) {
    $kind = (string) ( $allocation['kind'] ?? '' );
    $kind_rank = $kind === 'base' ? 0 : 1;
    $order_id = (int) ( $allocation['order_id'] ?? 0 );
    $item_id = (int) ( $allocation['item_id'] ?? 0 );
    $created = (string) ( $allocation['applied_at'] ?? '' );
    return sprintf( '%d|%s|%010d|%010d', $kind_rank, $created, $order_id, $item_id );
}

function teinvit_build_gifts_summary_for_token( $token, $config = null ) {
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return [ 'base_slots' => 0, 'addon_slots' => 0, 'admin_slots' => 0, 'total_slots' => 0, 'used_slots' => 0, 'available_slots' => 0, 'allocations' => [] ];
    }

    if ( ! is_array( $config ) ) {
        $inv = function_exists( 'teinvit_get_invitation' ) ? teinvit_get_invitation( $token ) : null;
        $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : [];
    }

    $allocations = isset( $config['gifts_allocations'] ) && is_array( $config['gifts_allocations'] ) ? $config['gifts_allocations'] : [];
    $normalized = [];
    foreach ( $allocations as $allocation ) {
        if ( ! is_array( $allocation ) ) {
            continue;
        }
        $slots_total = max( 0, (int) ( $allocation['slots_total'] ?? 0 ) );
        if ( $slots_total <= 0 ) {
            continue;
        }
        $allocation['status'] = (string) ( $allocation['status'] ?? 'applied' );
        if ( $allocation['status'] !== 'applied' && $allocation['status'] !== 'reverted' ) {
            $allocation['status'] = 'applied';
        }
        $allocation['kind'] = (string) ( $allocation['kind'] ?? 'addon' );
        $allocation['slots_total'] = $slots_total;
        $allocation['slots_remaining'] = max( 0, (int) ( $allocation['slots_remaining'] ?? $slots_total ) );
        $allocation['allocation_key'] = sanitize_text_field( (string) ( $allocation['allocation_key'] ?? '' ) );
        if ( $allocation['allocation_key'] === '' ) {
            $allocation['allocation_key'] = ( $allocation['kind'] === 'base' ? 'base' : 'addon' ) . ':' . (int) ( $allocation['order_id'] ?? 0 ) . ':' . (int) ( $allocation['item_id'] ?? 0 );
        }
        $normalized[] = $allocation;
    }

    if ( function_exists( 'teinvit_gifts_ensure_base_and_legacy_allocations' ) ) {
        $normalized = teinvit_gifts_ensure_base_and_legacy_allocations( $token, $config, $normalized );
    } elseif ( empty( $normalized ) ) {
        $base_slots = isset( $config['gifts_base_slots_applied'] ) ? max( 0, (int) $config['gifts_base_slots_applied'] ) : 20;
        if ( $base_slots > 0 ) {
            $normalized[] = [
                'allocation_key' => 'legacy-base',
                'kind' => 'base',
                'order_id' => 0,
                'item_id' => 0,
                'slots_total' => $base_slots,
                'slots_remaining' => $base_slots,
                'status' => 'applied',
                'applied_at' => '',
            ];
        }
    }

    usort( $normalized, static function( $a, $b ) {
        return strcmp( teinvit_gifts_allocation_sort_key( $a ), teinvit_gifts_allocation_sort_key( $b ) );
    } );

    $used = teinvit_gifts_used_count_for_token( $token );
    $remaining_to_consume = $used;
    $base_slots = 0;
    $addon_slots = 0;
    $admin_slots = 0;
    foreach ( $normalized as &$allocation ) {
        if ( (string) $allocation['status'] !== 'applied' ) {
            $allocation['slots_remaining'] = max( 0, (int) $allocation['slots_remaining'] );
            continue;
        }
        $total = (int) $allocation['slots_total'];
        $consume = min( $remaining_to_consume, $total );
        $remaining_to_consume -= $consume;
        $allocation['slots_remaining'] = max( 0, $total - $consume );
        if ( (string) $allocation['kind'] === 'base' ) {
            $base_slots += $total;
        } elseif ( (string) $allocation['kind'] === 'admin_grant' ) {
            $admin_slots += $total;
        } else {
            $addon_slots += $total;
        }
    }
    unset( $allocation );

    $total_slots = max( 0, $base_slots + $addon_slots + $admin_slots );
    $available = max( 0, $total_slots - $used );

    return [
        'base_slots' => $base_slots,
        'addon_slots' => $addon_slots,
        'admin_slots' => $admin_slots,
        'total_slots' => $total_slots,
        'used_slots' => $used,
        'available_slots' => $available,
        'allocations' => $normalized,
    ];
}

function teinvit_custom_products_vertical_labels() {
    return [
        'wedding' => 'Invitații digitale pentru Nuntă',
        'baptism' => 'Invitații digitale pentru Botez',
        'birthday' => 'Invitații digitale pentru Zile de naștere',
        'private_party' => 'Invitații digitale pentru Petreceri private',
    ];
}

function teinvit_register_custom_products_admin_page() {
    $labels = teinvit_custom_products_vertical_labels();
    $parent = function_exists( 'teinvit_admin_root_slug' ) ? teinvit_admin_root_slug() : 'woocommerce';
    $capability = function_exists( 'teinvit_admin_capability' ) ? teinvit_admin_capability() : 'manage_woocommerce';

    add_submenu_page(
        $parent,
        'Te Invit Custom Products',
        'Te Invit Custom Products',
        $capability,
        'teinvit-custom-products-wedding',
        function() {
            teinvit_render_custom_products_admin_page( 'wedding' );
        }
    );

    foreach ( $labels as $vertical => $label ) {
        $slug = 'teinvit-custom-products-' . $vertical;
        if ( $vertical === 'wedding' ) {
            continue;
        }

        add_submenu_page(
            $parent,
            $label,
            $label,
            $capability,
            $slug,
            function() use ( $vertical ) {
                teinvit_render_custom_products_admin_page( $vertical );
            }
        );
    }
}
add_action( 'admin_menu', 'teinvit_register_custom_products_admin_page', 30 );

function teinvit_custom_products_sold_individually_rows( $vertical, array $catalog ) {
    $vertical = sanitize_key( (string) $vertical );
    $rows = [];
    $roles = [
        'basic_product_ids' => 'Basic',
        'premium_native_product_ids' => 'Premium',
    ];

    foreach ( $roles as $role_key => $package_label ) {
        $product_ids = function_exists( 'teinvit_catalog_role_ids' ) ? teinvit_catalog_role_ids( $catalog, $role_key ) : [];
        foreach ( $product_ids as $product_id ) {
            $product_id = (int) $product_id;
            if ( $product_id <= 0 ) {
                continue;
            }

            $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
            $sold_individually = is_object( $product ) && method_exists( $product, 'is_sold_individually' )
                ? (bool) $product->is_sold_individually()
                : false;

            $rows[] = [
                'vertical' => $vertical,
                'package' => $package_label,
                'product_id' => $product_id,
                'product_name' => is_object( $product ) && method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '',
                'exists' => is_object( $product ),
                'sold_individually' => $sold_individually,
                'edit_url' => get_edit_post_link( $product_id, '' ),
            ];
        }
    }

    return $rows;
}

function teinvit_render_sold_individually_checklist( $vertical, array $catalog ) {
    $rows = teinvit_custom_products_sold_individually_rows( $vertical, $catalog );

    echo '<hr style="margin:28px 0 18px;">';
    echo '<h2>Checklist Sold individually</h2>';
    echo '<p class="description">Diagnostic pentru produsele configurabile TeInvit. Nu se aplica automat nicio setare.</p>';

    if ( empty( $rows ) ) {
        echo '<p>Nu exista produse Basic/Premium configurate pentru aceasta verticala.</p>';
        return;
    }

    echo '<table class="widefat striped" style="max-width:1100px;margin-top:12px;">';
    echo '<thead><tr><th>Verticala</th><th>Tip pachet</th><th>Product ID</th><th>Produs</th><th>Sold individually</th><th>Editare</th></tr></thead><tbody>';
    foreach ( $rows as $row ) {
        $status = ! $row['exists'] ? 'Produs lipsa' : ( $row['sold_individually'] ? 'OK' : 'Lipsa' );
        $status_style = $row['exists'] && $row['sold_individually'] ? 'color:#067a46;font-weight:600;' : 'color:#b32d2e;font-weight:600;';
        echo '<tr>';
        echo '<td>' . esc_html( $row['vertical'] ) . '</td>';
        echo '<td>' . esc_html( $row['package'] ) . '</td>';
        echo '<td><code>' . esc_html( (string) $row['product_id'] ) . '</code></td>';
        echo '<td>' . esc_html( $row['product_name'] !== '' ? $row['product_name'] : '-' ) . '</td>';
        echo '<td><span style="' . esc_attr( $status_style ) . '">' . esc_html( $status ) . '</span></td>';
        echo '<td>' . ( $row['edit_url'] ? '<a href="' . esc_url( $row['edit_url'] ) . '">Editeaza produs</a>' : '-' ) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

function teinvit_render_custom_products_admin_page( $vertical = 'wedding' ) {
    $capability = function_exists( 'teinvit_admin_capability' ) ? teinvit_admin_capability() : 'manage_woocommerce';
    if ( ! current_user_can( $capability ) ) {
        wp_die( 'Nu ai acces la această pagină.' );
    }

    $labels = teinvit_custom_products_vertical_labels();
    $vertical = isset( $labels[ $vertical ] ) ? $vertical : 'wedding';

    $catalog_all = function_exists( 'teinvit_get_custom_products_catalog' ) ? teinvit_get_custom_products_catalog() : [];
    $catalog = isset( $catalog_all[ $vertical ] ) ? $catalog_all[ $vertical ] : ( function_exists( 'teinvit_custom_product_defaults' ) ? teinvit_custom_product_defaults() : [] );

    $saved = false;

    $nonce_action = 'teinvit_save_custom_products_' . $vertical;
    $nonce_field = 'teinvit_custom_products_nonce_' . $vertical;

    if ( isset( $_POST[ $nonce_field ] ) && wp_verify_nonce( wp_unslash( $_POST[ $nonce_field ] ), $nonce_action ) ) {
        $extra_gifts_addon_ids = function_exists( 'teinvit_parse_product_ids_csv' ) ? teinvit_parse_product_ids_csv( sanitize_text_field( wp_unslash( $_POST['extra_gifts_addon_ids'] ?? '' ) ) ) : [];
        $extra_gifts_addon_slots = function_exists( 'teinvit_parse_addon_slots_csv' )
            ? teinvit_parse_addon_slots_csv( sanitize_text_field( wp_unslash( $_POST['extra_gifts_addon_slots'] ?? '' ) ), $extra_gifts_addon_ids, 10 )
            : [];

        $catalog_all[ $vertical ] = [
            'basic_product_ids' => function_exists( 'teinvit_parse_product_ids_csv' ) ? teinvit_parse_product_ids_csv( sanitize_text_field( wp_unslash( $_POST['basic_product_ids'] ?? '' ) ) ) : [],
            'premium_upgrade_addon_ids' => function_exists( 'teinvit_parse_product_ids_csv' ) ? teinvit_parse_product_ids_csv( sanitize_text_field( wp_unslash( $_POST['premium_upgrade_addon_ids'] ?? '' ) ) ) : [],
            'premium_native_product_ids' => function_exists( 'teinvit_parse_product_ids_csv' ) ? teinvit_parse_product_ids_csv( sanitize_text_field( wp_unslash( $_POST['premium_native_product_ids'] ?? '' ) ) ) : [],
            'extra_edits_addon_ids' => function_exists( 'teinvit_parse_product_ids_csv' ) ? teinvit_parse_product_ids_csv( sanitize_text_field( wp_unslash( $_POST['extra_edits_addon_ids'] ?? '' ) ) ) : [],
            'extra_gifts_addon_ids' => $extra_gifts_addon_ids,
            'extra_gifts_addon_slots' => $extra_gifts_addon_slots,
            'default_free_gift_slots' => max( 0, (int) ( $_POST['default_free_gift_slots'] ?? 20 ) ),
            'default_included_edits' => function_exists( 'teinvit_normalize_nonnegative_catalog_int' ) ? teinvit_normalize_nonnegative_catalog_int( wp_unslash( $_POST['default_included_edits'] ?? 2 ), 2 ) : max( 0, (int) ( $_POST['default_included_edits'] ?? 2 ) ),
        ];

        update_option( 'teinvit_custom_products_catalog', $catalog_all, false );

        // Compat pentru versiuni vechi care mai citesc opțiunea legacy.
        if ( $vertical === 'wedding' ) {
            update_option( 'teinvit_custom_product_ids', $catalog_all[ $vertical ], false );
        }

        $catalog_all = function_exists( 'teinvit_get_custom_products_catalog' ) ? teinvit_get_custom_products_catalog() : $catalog_all;
        $catalog = isset( $catalog_all[ $vertical ] ) ? $catalog_all[ $vertical ] : $catalog;
        $saved = true;
    }

    echo '<div class="wrap"><h1>Te Invit Custom Products — ' . esc_html( $labels[ $vertical ] ) . '</h1>';
    if ( $saved ) {
        echo '<div class="notice notice-success"><p>Setările au fost salvate.</p></div>';
    }

    echo '<form method="post">';
    wp_nonce_field( $nonce_action, $nonce_field );

    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row"><label for="basic_product_ids">Produs Basic (ID-uri)</label></th><td><input type="text" id="basic_product_ids" name="basic_product_ids" value="' . esc_attr( teinvit_catalog_ids_to_csv( $catalog['basic_product_ids'] ?? [] ) ) . '" class="regular-text" /><p class="description">Ex: 560,561</p></td></tr>';
    echo '<tr><th scope="row"><label for="premium_upgrade_addon_ids">Addon Upgrade Premium (ID-uri)</label></th><td><input type="text" id="premium_upgrade_addon_ids" name="premium_upgrade_addon_ids" value="' . esc_attr( teinvit_catalog_ids_to_csv( $catalog['premium_upgrade_addon_ids'] ?? [] ) ) . '" class="regular-text" /><p class="description">Ex: 526,700</p></td></tr>';
    echo '<tr><th scope="row"><label for="premium_native_product_ids">Produse Premium Native (ID-uri)</label></th><td><input type="text" id="premium_native_product_ids" name="premium_native_product_ids" value="' . esc_attr( implode( ',', (array) ( $catalog['premium_native_product_ids'] ?? [] ) ) ) . '" class="regular-text" /><p class="description">Ex: 70,286</p></td></tr>';
    echo '<tr><th scope="row"><label for="extra_edits_addon_ids">Addon Modificări suplimentare (ID-uri)</label></th><td><input type="text" id="extra_edits_addon_ids" name="extra_edits_addon_ids" value="' . esc_attr( teinvit_catalog_ids_to_csv( $catalog['extra_edits_addon_ids'] ?? [] ) ) . '" class="regular-text" /><p class="description">Ex: 301,701</p></td></tr>';
    echo '<tr><th scope="row"><label for="extra_gifts_addon_ids">Addon cadouri extra (ID-uri)</label></th><td><input type="text" id="extra_gifts_addon_ids" name="extra_gifts_addon_ids" value="' . esc_attr( teinvit_catalog_ids_to_csv( $catalog['extra_gifts_addon_ids'] ?? [] ) ) . '" class="regular-text" /><p class="description">Ex: 298,702</p></td></tr>';
    echo '<tr><th scope="row"><label for="extra_gifts_addon_slots">Sloturi cadouri / addon</label></th><td><input type="text" id="extra_gifts_addon_slots" name="extra_gifts_addon_slots" value="' . esc_attr( teinvit_catalog_slots_map_to_csv( $catalog['extra_gifts_addon_slots'] ?? [] ) ) . '" class="regular-text" /><p class="description">Format: product_id:sloturi,product_id:sloturi. Ex: 298:10,702:20. Dacă lipsește pentru un ID, fallback-ul este 10.</p></td></tr>';
    echo '<tr><th scope="row"><label for="default_free_gift_slots">Sloturi cadouri gratuite (default)</label></th><td><input type="number" min="0" step="1" id="default_free_gift_slots" name="default_free_gift_slots" value="' . esc_attr( (string) max( 0, (int) ( $catalog['default_free_gift_slots'] ?? 20 ) ) ) . '" class="small-text" /><p class="description">Valoare curentă per verticală. Se snapshot-uiește istoric la completed pentru comenzile principale.</p></td></tr>';
    echo '<tr><th scope="row"><label for="default_included_edits">Modificări incluse în Premium (default)</label></th><td><input type="number" min="0" step="1" id="default_included_edits" name="default_included_edits" value="' . esc_attr( (string) ( function_exists( 'teinvit_catalog_default_included_edits' ) ? teinvit_catalog_default_included_edits( $catalog, 2 ) : max( 0, (int) ( $catalog['default_included_edits'] ?? 2 ) ) ) ) . '" class="small-text" /><p class="description">Numărul de modificări gratuite incluse implicit pentru tokenurile Premium ale acestei verticale. Se aplică tokenurilor Premium noi și upgrade-urilor viitoare.</p></td></tr>';
    echo '</table>';

    submit_button( 'Salvează' );
    echo '</form>';

    teinvit_render_sold_individually_checklist( $vertical, $catalog );

    echo '</div>';
}

function teinvit_render_product_background_field() {
    global $post;

    if ( ! $post || (string) $post->post_type !== 'product' ) {
        return;
    }

    $attachment_id = (int) get_post_meta( (int) $post->ID, '_teinvit_background_image_id', true );
    $image_url = $attachment_id > 0 ? wp_get_attachment_image_url( $attachment_id, 'large' ) : '';

    echo '<div class="options_group teinvit-product-bg-field">';
    echo '<p class="form-field"><label for="teinvit_background_image_id">Background invitație (Media Library)</label>';
    echo '<input type="hidden" id="teinvit_background_image_id" name="teinvit_background_image_id" value="' . esc_attr( (string) $attachment_id ) . '" />';
    echo '<button type="button" class="button" id="teinvit-background-select">Selectează imagine</button> ';
    echo '<button type="button" class="button" id="teinvit-background-remove" ' . ( $attachment_id > 0 ? '' : 'style="display:none;"' ) . '>Elimină</button>';
    echo '<span class="description" style="display:block;margin-top:6px;">Imaginea aleasă va fi folosită în preview și PDF pentru acest produs.</span>';
    echo '</p>';

    echo '<p id="teinvit-background-preview" style="margin-left:162px;' . ( $image_url ? '' : 'display:none;' ) . '">';
    if ( $image_url ) {
        echo '<img src="' . esc_url( $image_url ) . '" alt="Background" style="max-width:220px;height:auto;border:1px solid #ddd;border-radius:6px;" />';
    }
    echo '</p>';
    echo '</div>';
}
add_action( 'woocommerce_product_options_general_product_data', 'teinvit_render_product_background_field' );

add_action( 'woocommerce_process_product_meta', function( $post_id ) {
    $attachment_id = isset( $_POST['teinvit_background_image_id'] ) ? (int) $_POST['teinvit_background_image_id'] : 0;
    if ( $attachment_id > 0 ) {
        update_post_meta( (int) $post_id, '_teinvit_background_image_id', $attachment_id );
    } else {
        delete_post_meta( (int) $post_id, '_teinvit_background_image_id' );
    }
} );

add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( ! in_array( $hook, [ 'post-new.php', 'post.php' ], true ) ) {
        return;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || (string) $screen->post_type !== 'product' ) {
        return;
    }

    wp_enqueue_media();
    wp_add_inline_script( 'jquery-core', "jQuery(function($){var frame;var field=$('#teinvit_background_image_id');var preview=$('#teinvit-background-preview');function render(url){if(!url){preview.hide().html('');$('#teinvit-background-remove').hide();return;}preview.html('<img src=\"'+url+'\" alt=\"Background\" style=\"max-width:220px;height:auto;border:1px solid #ddd;border-radius:6px;\" />').show();$('#teinvit-background-remove').show();}$('#teinvit-background-select').on('click',function(e){e.preventDefault();if(frame){frame.open();return;}frame=wp.media({title:'Selectează background invitație',button:{text:'Folosește această imagine'},multiple:false});frame.on('select',function(){var a=frame.state().get('selection').first().toJSON();field.val(a.id||'');render((a.sizes&&a.sizes.large?a.sizes.large.url:a.url)||'');});frame.open();});$('#teinvit-background-remove').on('click',function(e){e.preventDefault();field.val('');render('');});});" );
} );


function teinvit_normalize_wapf_field_id( $raw ) {
    $id = is_scalar( $raw ) ? (string) $raw : '';
    $id = trim( $id );

    if ( strpos( $id, 'field_' ) === 0 ) {
        $id = substr( $id, 6 );
    }
    if ( strpos( $id, 'wapf[field_' ) === 0 && substr( $id, -1 ) === ']' ) {
        $id = substr( $id, 11, -1 );
    }
    if ( strpos( $id, '_' ) === 0 ) {
        $id = substr( $id, 1 );
    }

    return trim( $id );
}

function teinvit_extract_wapf_definitions_from_product( $product_id ) {
    $product_id = (int) $product_id;
    if ( $product_id <= 0 ) {
        return [];
    }

    $allowed_invitation_only_ids = function_exists( 'teinvit_wedding_invitation_only_wapf_field_ids' ) ? teinvit_wedding_invitation_only_wapf_field_ids() : [];
    $allowed = array_fill_keys( $allowed_invitation_only_ids, true );
    $definitions = [];

    if ( function_exists( 'wapf_get_field_groups_of_product' ) ) {
        $groups = wapf_get_field_groups_of_product( $product_id );
        if ( is_array( $groups ) ) {
            foreach ( $groups as $group ) {
                $fields = is_object( $group ) && isset( $group->fields ) ? (array) $group->fields : [];
                foreach ( $fields as $field ) {
                    if ( ! is_object( $field ) ) {
                        continue;
                    }

                    $id = teinvit_normalize_wapf_field_id( $field->id ?? '' );
                    if ( $id === '' || ! isset( $allowed[ $id ] ) ) {
                        continue;
                    }

                    if ( ! isset( $definitions[ $id ] ) ) {
                        $definitions[ $id ] = [
                            'id' => $id,
                            'label' => (string) ( $field->label ?? ( 'Field ' . $id ) ),
                            'type' => (string) ( $field->type ?? 'text' ),
                            'options' => [],
                            'conditions' => [],
                            'order' => isset( $field->order ) ? (int) $field->order : 0,
                        ];
                    }

                    $choices = [];
                    if ( isset( $field->options ) && is_array( $field->options ) && isset( $field->options['choices'] ) && is_array( $field->options['choices'] ) ) {
                        $choices = $field->options['choices'];
                    }

                    foreach ( $choices as $opt ) {
                        if ( ! is_array( $opt ) && ! is_object( $opt ) ) {
                            continue;
                        }

                        $opt_arr = is_object( $opt ) ? json_decode( wp_json_encode( $opt ), true ) : $opt;
                        $label = trim( (string) ( $opt_arr['label'] ?? $opt_arr['text'] ?? $opt_arr['value'] ?? $opt_arr['slug'] ?? $opt_arr['id'] ?? $opt_arr['key'] ?? '' ) );
                        $value = trim( (string) ( $opt_arr['value'] ?? $opt_arr['slug'] ?? $opt_arr['id'] ?? $opt_arr['key'] ?? $opt_arr['code'] ?? $label ) );
                        if ( $label === '' && $value === '' ) {
                            continue;
                        }

                        $definitions[ $id ]['options'][] = [
                            'value' => $value !== '' ? $value : $label,
                            'label' => $label !== '' ? $label : $value,
                        ];
                    }
                }
            }
        }
    }

    if ( empty( $definitions ) ) {
        $raw_meta = get_post_meta( $product_id );

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
                $label = (string) ( $opt['label'] ?? $opt['text'] ?? $opt['value'] ?? $opt['slug'] ?? $opt['id'] ?? $opt['key'] ?? '' );
                $value = (string) ( $opt['value'] ?? $opt['slug'] ?? $opt['id'] ?? $opt['key'] ?? $opt['code'] ?? $label );
                if ( $label === '' && $value === '' ) {
                    continue;
                }
                $out[] = [ 'value' => $value, 'label' => $label !== '' ? $label : $value ];
            }

            return $out;
        };

        $consume = function( $node ) use ( &$consume, &$definitions, $allowed, $parse_options ) {
            if ( is_array( $node ) ) {
                if ( isset( $node['id'] ) && isset( $node['type'] ) ) {
                    $id = teinvit_normalize_wapf_field_id( $node['id'] );
                    if ( $id !== '' && isset( $allowed[ $id ] ) && ! isset( $definitions[ $id ] ) ) {
                        $definitions[ $id ] = [
                            'id' => $id,
                            'label' => (string) ( $node['label'] ?? $node['title'] ?? ('Field ' . $id) ),
                            'type' => (string) $node['type'],
                            'options' => $parse_options( $node ),
                            'conditions' => [],
                            'order' => isset( $node['order'] ) ? (int) $node['order'] : 0,
                        ];
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
    }

    if ( empty( $definitions ) ) {
        foreach ( $allowed_invitation_only_ids as $id ) {
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

function teinvit_build_initial_snapshot( $order_id, $token, $context = [] ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $context = is_array( $context ) ? $context : [];
    $vertical = sanitize_key( (string) ( $context['vertical'] ?? 'wedding' ) );
    if ( $vertical === '' ) {
        $vertical = 'wedding';
    }
    $order_item = null;
    $order_item_id = max( 0, (int) ( $context['order_item_id'] ?? 0 ) );
    if ( $order_item_id > 0 && method_exists( $order, 'get_item' ) ) {
        $candidate_item = $order->get_item( $order_item_id );
        if ( $candidate_item instanceof WC_Order_Item_Product ) {
            $order_item = $candidate_item;
        }
    }

    $settings = teinvit_get_settings( $token );
    if ( ! $settings ) {
        $default_included_edits = function_exists( 'teinvit_default_included_edits_fallback' ) ? teinvit_default_included_edits_fallback() : 2;
        $legacy_edit_config = [
            'edits_free_remaining' => $default_included_edits,
            'edits_admin_remaining' => 0,
            'edits_paid_remaining' => 0,
        ];
        if ( function_exists( 'teinvit_config_apply_initial_edit_entitlement' ) ) {
            $catalog_entry = function_exists( 'teinvit_get_custom_product_ids' )
                ? teinvit_get_custom_product_ids( $vertical )
                : ( function_exists( 'teinvit_get_catalog_for_order' ) ? teinvit_get_catalog_for_order( $order ) : [] );
            $is_premium = sanitize_key( (string) ( $context['package_type'] ?? '' ) ) === 'premium';
            if ( empty( $context ) && function_exists( 'teinvit_order_should_receive_initial_included_edits' ) ) {
                $is_premium = teinvit_order_should_receive_initial_included_edits( $order, $catalog_entry );
            }
            $legacy_edit_config = teinvit_config_apply_initial_edit_entitlement(
                $legacy_edit_config,
                $catalog_entry,
                $is_premium,
                'token_generated',
                (int) $order_id
            );
        }

        global $wpdb;
        $t = teinvit_tables();
        $wpdb->insert( $t['settings'], [
            'token' => $token,
            'order_id' => $order_id,
            'user_id' => (int) $order->get_user_id(),
            'edits_free_remaining' => max( 0, (int) ( $legacy_edit_config['edits_free_remaining'] ?? $default_included_edits ) ),
            'edits_admin_remaining' => max( 0, (int) ( $legacy_edit_config['edits_admin_remaining'] ?? 0 ) ),
            'edits_paid_remaining' => max( 0, (int) ( $legacy_edit_config['edits_paid_remaining'] ?? 0 ) ),
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

    if ( $order_item && function_exists( 'teinvit_build_invitation_payload_from_order_item' ) ) {
        $payload = teinvit_build_invitation_payload_from_order_item( $vertical, $order, $order_item, $token, $context );
    } else {
        $invitation = TeInvit_Wedding_Preview_Renderer::get_order_invitation_data( $order );
        $wapf_map = TeInvit_Wedding_Preview_Renderer::get_order_wapf_field_map( $order );
        $payload = [
            'invitation' => $invitation,
            'wapf_fields' => $wapf_map,
        ];
    }

    global $wpdb;
    $t = teinvit_tables();
    $wpdb->insert( $t['versions'], [
        'token' => $token,
        'order_id' => $order_id,
        'version' => 0,
        'data_json' => wp_json_encode( $payload ),
        'pdf_url' => empty( $context['is_multi_token_order'] ) ? $order->get_meta( '_teinvit_pdf_url' ) : '',
        'pdf_path' => '',
        'created_by_user_id' => (int) $order->get_user_id(),
        'created_at' => current_time( 'mysql' ),
    ] );
}
add_action( 'teinvit_token_generated', 'teinvit_build_initial_snapshot', 20, 3 );


/* Legacy /client-admin route removed intentionally. */


function teinvit_client_admin_owner_check( $token ) {
    $settings = teinvit_get_settings( $token );
    if ( ! $settings ) {
        return new WP_Error( 'not_found', 'Token invalid', [ 'status' => 404 ] );
    }
    if ( ! is_user_logged_in() ) {
        return new WP_Error( 'forbidden', 'Access denied', [ 'status' => 403 ] );
    }
    if ( current_user_can( 'teinvit_manage_all_tokens' ) ) {
        return $settings;
    }
    if ( (int) $settings['user_id'] !== get_current_user_id() ) {
        return new WP_Error( 'forbidden', 'Access denied', [ 'status' => 403 ] );
    }
    return $settings;
}

add_action( 'init', function() {
    $role = get_role( 'administrator' );
    if ( $role && ! $role->has_cap( 'teinvit_manage_all_tokens' ) ) {
        $role->add_cap( 'teinvit_manage_all_tokens' );
    }
}, 5 );

function teinvit_pdf_filename_for_version( WC_Order $order, $version, $token = '', $version_id = 0 ) {
    $token = sanitize_text_field( (string) $token );
    $version_id = (int) $version_id;
    if ( $token !== '' && $version_id > 0 && function_exists( 'teinvit_phase4_pdf_filename_for_version' ) ) {
        return teinvit_phase4_pdf_filename_for_version( $token, $version_id, max( 0, (int) $version ) );
    }

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

function teinvit_generate_pdf_for_version( $token, $order_id, $filename, $version_id = 0 ) {
    if ( function_exists( 'teinvit_generate_pdf_for_token_version' ) && (int) $version_id > 0 ) {
        $result = teinvit_generate_pdf_for_token_version( $token, (int) $version_id, true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [
            'pdf_url' => esc_url_raw( (string) ( $result['pdf_url'] ?? '' ) ),
            'pdf_path' => '/pdf/' . (int) $order_id . '/' . sanitize_file_name( (string) ( $result['pdf_filename'] ?? $filename ) ),
            'pdf_filename' => sanitize_file_name( (string) ( $result['pdf_filename'] ?? $filename ) ),
            'variant_number' => (int) ( $result['variant_number'] ?? 0 ),
        ];
    }

    $payload = [ 'token' => $token, 'order_id' => (int) $order_id, 'filename' => sanitize_file_name( (string) $filename ) ];
    if ( (int) $version_id > 0 ) {
        $payload['version_id'] = (int) $version_id;
    }
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
    $pdf_url = ! empty( $data['pdf_url'] )
        ? esc_url_raw( teinvit_pdf_public_base_url() . $data['pdf_url'] )
        : teinvit_pdf_public_url_from_filename( (int) $order_id, $filename );
    return [
        'pdf_url' => $pdf_url,
        'pdf_path' => '/pdf/' . (int) $order_id . '/' . sanitize_file_name( (string) $filename ),
        'pdf_filename' => sanitize_file_name( (string) $filename ),
    ];
}

/* Legacy REST v1 routes removed intentionally. */


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
    $target_context = function_exists( 'teinvit_resolve_token_context' ) ? teinvit_resolve_token_context( $token ) : null;
    $catalog = is_array( $target_context ) && ! empty( $target_context['valid'] )
        ? teinvit_addon_catalog_for_token_context( $target_context )
        : ( function_exists( 'teinvit_get_catalog_for_token' ) ? teinvit_get_catalog_for_token( $token ) : ( function_exists( 'teinvit_get_custom_product_ids' ) ? teinvit_get_custom_product_ids() : [] ) );
    $pid = 0;
    $addon_type = 'extra_edits';

    if ( $type === 'gifts' ) {
        $pid = teinvit_catalog_first_id( $catalog, 'extra_gifts_addon_ids' );
        $addon_type = 'extra_gifts';
    } elseif ( $type === 'premium_upgrade' ) {
        $pid = teinvit_catalog_first_id( $catalog, 'premium_upgrade_addon_ids' );
        $addon_type = 'premium_upgrade';
    } else {
        $pid = teinvit_catalog_first_id( $catalog, 'extra_edits_addon_ids' );
    }

    if ( $pid <= 0 ) {
        return wc_get_cart_url();
    }

    return add_query_arg( [ 'add-to-cart' => $pid, 'quantity' => 1, 'teinvit_token' => rawurlencode( $token ), 'teinvit_addon_type' => $addon_type ], wc_get_cart_url() );
}

function teinvit_addon_customer_block_message() {
    return 'Acest pachet suplimentar trebuie cumpărat din pagina de administrare a invitației pentru care doriți să îl aplicați.';
}

function teinvit_addon_role_map() {
    return [
        'extra_edits' => 'extra_edits_addon_ids',
        'extra_gifts' => 'extra_gifts_addon_ids',
        'premium_upgrade' => 'premium_upgrade_addon_ids',
    ];
}

function teinvit_addon_type_labels() {
    return [
        'extra_edits' => 'modificari suplimentare',
        'extra_gifts' => 'cadouri suplimentare',
        'premium_upgrade' => 'upgrade Premium',
    ];
}

function teinvit_addon_capability_for_type( $addon_type ) {
    $map = [
        'extra_edits' => 'can_buy_extra_edits',
        'extra_gifts' => 'can_buy_extra_gifts',
        'premium_upgrade' => 'can_buy_premium_upgrade',
    ];

    $addon_type = sanitize_key( (string) $addon_type );
    return isset( $map[ $addon_type ] ) ? $map[ $addon_type ] : '';
}

function teinvit_addon_capability_changed_for_type( $addon_type ) {
    $map = [
        'extra_edits' => 'edits_paid_remaining',
        'extra_gifts' => 'gifts_extra_slots',
        'premium_upgrade' => 'premium_upgrade_active',
    ];

    $addon_type = sanitize_key( (string) $addon_type );
    return isset( $map[ $addon_type ] ) ? $map[ $addon_type ] : '';
}

function teinvit_addon_catalog_for_vertical( $vertical ) {
    $vertical = sanitize_key( (string) $vertical );
    $catalogs = function_exists( 'teinvit_get_custom_products_catalog' ) ? teinvit_get_custom_products_catalog() : [];
    if ( is_array( $catalogs ) && $vertical !== '' && isset( $catalogs[ $vertical ] ) && is_array( $catalogs[ $vertical ] ) ) {
        return $catalogs[ $vertical ];
    }

    return function_exists( 'teinvit_custom_product_defaults' ) ? teinvit_custom_product_defaults() : [];
}

function teinvit_addon_catalog_for_token_context( array $target_context ) {
    $vertical = sanitize_key( (string) ( $target_context['vertical'] ?? '' ) );
    $catalog = teinvit_addon_catalog_for_vertical( $vertical );
    if ( ! empty( $catalog ) ) {
        return $catalog;
    }

    $token = sanitize_text_field( (string) ( $target_context['token'] ?? '' ) );
    return $token !== '' && function_exists( 'teinvit_get_catalog_for_token' ) ? teinvit_get_catalog_for_token( $token ) : [];
}

function teinvit_addon_product_context( $product_id, $variation_id = 0, $preferred_vertical = '' ) {
    $product_ids = array_values( array_filter( array_map( 'intval', [ $product_id, $variation_id ] ), static function( $id ) {
        return $id > 0;
    } ) );

    if ( empty( $product_ids ) || ! function_exists( 'teinvit_get_custom_products_catalog' ) ) {
        return null;
    }

    $catalogs = teinvit_get_custom_products_catalog();
    if ( ! is_array( $catalogs ) ) {
        return null;
    }

    $preferred_vertical = sanitize_key( (string) $preferred_vertical );
    $vertical_order = [];
    if ( $preferred_vertical !== '' && isset( $catalogs[ $preferred_vertical ] ) ) {
        $vertical_order[] = $preferred_vertical;
    }
    foreach ( array_keys( $catalogs ) as $vertical_key ) {
        $vertical_key = sanitize_key( (string) $vertical_key );
        if ( $vertical_key !== '' ) {
            $vertical_order[] = $vertical_key;
        }
    }
    $vertical_order = array_values( array_unique( $vertical_order ) );

    foreach ( $vertical_order as $vertical_key ) {
        $catalog = isset( $catalogs[ $vertical_key ] ) && is_array( $catalogs[ $vertical_key ] ) ? $catalogs[ $vertical_key ] : [];
        foreach ( teinvit_addon_role_map() as $addon_type => $role_key ) {
            $matches = function_exists( 'teinvit_catalog_product_id_matches_role' )
                ? teinvit_catalog_product_id_matches_role( $catalog, $role_key, $product_ids )
                : ! empty( array_intersect( $product_ids, array_map( 'intval', (array) ( $catalog[ $role_key ] ?? [] ) ) ) );
            if ( $matches ) {
                return [
                    'is_addon' => true,
                    'addon_type' => $addon_type,
                    'role_key' => $role_key,
                    'vertical' => $vertical_key,
                    'catalog' => $catalog,
                ];
            }
        }
    }

    return null;
}

function teinvit_addon_capabilities_from_token_context( array $target_context ) {
    if ( isset( $target_context['capabilities'] ) && is_array( $target_context['capabilities'] ) ) {
        return $target_context['capabilities'];
    }

    $product_state = sanitize_key( (string) ( $target_context['product_state'] ?? '' ) );
    $package_type = sanitize_key( (string) ( $target_context['package_type'] ?? 'unknown' ) );

    if ( in_array( $product_state, [ 'premium_native', 'basic_upgraded' ], true ) || $package_type === 'premium' ) {
        return [
            'state' => $product_state !== '' ? $product_state : 'premium_native',
            'can_buy_extra_edits' => true,
            'can_buy_extra_gifts' => true,
            'can_buy_premium_upgrade' => false,
        ];
    }

    if ( $product_state === 'basic_pure' || $package_type === 'basic' ) {
        return [
            'state' => $product_state !== '' ? $product_state : 'basic_pure',
            'can_buy_extra_edits' => false,
            'can_buy_extra_gifts' => false,
            'can_buy_premium_upgrade' => true,
        ];
    }

    return [
        'state' => $product_state,
        'can_buy_extra_edits' => false,
        'can_buy_extra_gifts' => false,
        'can_buy_premium_upgrade' => false,
    ];
}

function teinvit_token_has_other_premium_upgrade_order( $token, $current_order_id, array $catalog ) {
    global $wpdb;

    $token = sanitize_text_field( (string) $token );
    $current_order_id = max( 0, (int) $current_order_id );
    if ( $token === '' || ! function_exists( 'teinvit_catalog_role_ids' ) ) {
        return false;
    }

    $upgrade_ids = teinvit_catalog_role_ids( $catalog, 'premium_upgrade_addon_ids' );
    if ( empty( $upgrade_ids ) ) {
        return false;
    }

    $statuses = [ 'wc-processing', 'wc-completed' ];
    $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
    $sql = "
        SELECT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_token ON pm_token.post_id = p.ID AND pm_token.meta_key = '_teinvit_token_target' AND pm_token.meta_value = %s
        INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_id = p.ID AND oi.order_item_type = 'line_item'
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oim.order_item_id = oi.order_item_id AND oim.meta_key = '_product_id' AND oim.meta_value = %s
        WHERE p.post_type = 'shop_order'
          AND p.ID <> %d
          AND p.post_status IN ($status_placeholders)
        LIMIT 1
    ";

    foreach ( $upgrade_ids as $upgrade_id ) {
        $args = array_merge( [ $token, (string) (int) $upgrade_id, $current_order_id ], $statuses );
        $found_order_id = (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
        if ( $found_order_id > 0 ) {
            return true;
        }
    }

    return false;
}

function teinvit_token_base_state_allows_premium_upgrade( array $target_context, $current_order_id = 0 ) {
    $token = sanitize_text_field( (string) ( $target_context['token'] ?? '' ) );
    $catalog = teinvit_addon_catalog_for_token_context( $target_context );
    $package_type = sanitize_key( (string) ( $target_context['package_type'] ?? 'unknown' ) );
    $product_state = sanitize_key( (string) ( $target_context['product_state'] ?? '' ) );

    if ( $package_type === 'premium' || $product_state === 'premium_native' ) {
        return false;
    }

    $inv = $token !== '' && function_exists( 'teinvit_get_invitation' ) ? teinvit_get_invitation( $token ) : null;
    $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : [];
    if ( ! empty( $config['premium_upgrade_active'] ) || ! empty( $config['premium_admin_grant_active'] ) ) {
        return false;
    }

    if ( teinvit_token_has_other_premium_upgrade_order( $token, $current_order_id, $catalog ) ) {
        return false;
    }

    $order = $target_context['order'] ?? null;
    $order_id = (int) ( $target_context['order_id'] ?? 0 );
    if ( ! is_object( $order ) && $order_id > 0 && function_exists( 'wc_get_order' ) ) {
        $order = wc_get_order( $order_id );
    }

    $premium_native_ids = function_exists( 'teinvit_catalog_role_ids' ) ? teinvit_catalog_role_ids( $catalog, 'premium_native_product_ids' ) : [];
    $basic_ids = function_exists( 'teinvit_catalog_role_ids' ) ? teinvit_catalog_role_ids( $catalog, 'basic_product_ids' ) : [];
    if ( is_object( $order ) && function_exists( 'teinvit_order_contains_any_product_ids' ) ) {
        if ( teinvit_order_contains_any_product_ids( $order, $premium_native_ids ) ) {
            return false;
        }
        if ( teinvit_order_contains_any_product_ids( $order, $basic_ids ) ) {
            return true;
        }
    }

    return $package_type === 'basic' || $product_state === 'basic_pure';
}

function teinvit_validate_addon_for_token( $product_id, $variation_id, $token, $expected_addon_type = '' ) {
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return new WP_Error( 'teinvit_addon_missing_token', teinvit_addon_customer_block_message() );
    }

    if ( ! function_exists( 'teinvit_resolve_token_context' ) ) {
        return new WP_Error( 'teinvit_addon_resolver_missing', teinvit_addon_customer_block_message() );
    }

    $target_context = teinvit_resolve_token_context( $token );
    if ( ! is_array( $target_context ) || empty( $target_context['valid'] ) ) {
        return new WP_Error( 'teinvit_addon_invalid_token', teinvit_addon_customer_block_message() );
    }

    $target_vertical = sanitize_key( (string) ( $target_context['vertical'] ?? '' ) );
    $addon_context = teinvit_addon_product_context( $product_id, $variation_id, $target_vertical );
    if ( ! $addon_context ) {
        $any_vertical_context = teinvit_addon_product_context( $product_id, $variation_id );
        $code = $any_vertical_context ? 'teinvit_addon_incompatible_vertical' : 'teinvit_addon_unknown_product';
        return new WP_Error( $code, teinvit_addon_customer_block_message() );
    }

    $expected_addon_type = sanitize_key( (string) $expected_addon_type );
    if ( $expected_addon_type !== '' && $expected_addon_type !== $addon_context['addon_type'] ) {
        return new WP_Error( 'teinvit_addon_type_mismatch', teinvit_addon_customer_block_message() );
    }

    $capabilities = teinvit_addon_capabilities_from_token_context( $target_context );
    $capability_key = teinvit_addon_capability_for_type( $addon_context['addon_type'] );
    if ( $capability_key === '' || empty( $capabilities[ $capability_key ] ) ) {
        return new WP_Error( 'teinvit_addon_capability_blocked', teinvit_addon_customer_block_message() );
    }

    $addon_context['target_token'] = $token;
    $addon_context['target_context'] = $target_context;
    $addon_context['target_vertical'] = $target_vertical;
    $addon_context['capabilities'] = $capabilities;
    $addon_context['capability_key'] = $capability_key;

    return $addon_context;
}

function teinvit_addon_token_from_cart_data_or_request( array $cart_item_data = [] ) {
    if ( ! empty( $cart_item_data['teinvit_token'] ) ) {
        return sanitize_text_field( (string) $cart_item_data['teinvit_token'] );
    }

    if ( isset( $_REQUEST['teinvit_token'] ) ) {
        return sanitize_text_field( wp_unslash( $_REQUEST['teinvit_token'] ) );
    }

    return '';
}

function teinvit_addon_type_from_buy_query() {
    $map = [
        'teinvit_buy_edits_token' => 'extra_edits',
        'teinvit_buy_gifts_token' => 'extra_gifts',
        'teinvit_buy_premium_upgrade_token' => 'premium_upgrade',
    ];

    foreach ( $map as $query_key => $addon_type ) {
        if ( ! isset( $_GET[ $query_key ] ) ) {
            continue;
        }

        $token = sanitize_text_field( wp_unslash( $_GET[ $query_key ] ) );
        if ( $token !== '' ) {
            return [ $addon_type, $token ];
        }
    }

    return [ '', '' ];
}

function teinvit_addon_add_customer_notice( $message = '' ) {
    $message = $message !== '' ? $message : teinvit_addon_customer_block_message();
    if ( ! function_exists( 'wc_add_notice' ) ) {
        return;
    }
    if ( function_exists( 'wc_has_notice' ) && wc_has_notice( $message, 'error' ) ) {
        return;
    }
    wc_add_notice( $message, 'error' );
}

function teinvit_addon_cart_item_data_from_validation( array $validation ) {
    return [
        'teinvit_token' => sanitize_text_field( (string) ( $validation['target_token'] ?? '' ) ),
        'teinvit_addon_type' => sanitize_key( (string) ( $validation['addon_type'] ?? '' ) ),
        'teinvit_target_vertical' => sanitize_key( (string) ( $validation['target_vertical'] ?? '' ) ),
    ];
}

add_action( 'template_redirect', function() {
    list( $addon_type, $token ) = teinvit_addon_type_from_buy_query();

    if ( $addon_type === '' || $token === '' ) {
        return;
    }

    $target_context = function_exists( 'teinvit_resolve_token_context' ) ? teinvit_resolve_token_context( $token ) : null;
    if ( ! is_array( $target_context ) || empty( $target_context['valid'] ) ) {
        teinvit_addon_add_customer_notice();
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }

    $catalog = teinvit_addon_catalog_for_token_context( $target_context );
    $role_map = teinvit_addon_role_map();
    $product_id = isset( $role_map[ $addon_type ] ) ? teinvit_catalog_first_id( $catalog, $role_map[ $addon_type ] ) : 0;
    if ( $product_id <= 0 ) {
        teinvit_addon_add_customer_notice();
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }

    $validation = teinvit_validate_addon_for_token( $product_id, 0, $token, $addon_type );
    if ( is_wp_error( $validation ) ) {
        teinvit_addon_add_customer_notice( $validation->get_error_message() );
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }

    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        wp_safe_redirect( add_query_arg( [
            'add-to-cart' => $product_id,
            'quantity' => 1,
            'teinvit_token' => $token,
            'teinvit_addon_type' => $addon_type,
        ], wc_get_cart_url() ) );
        exit;
    }

    if ( WC()->session ) {
        WC()->session->set( 'teinvit_token_target', $token );
    }
    WC()->cart->add_to_cart( $product_id, 1, 0, [], teinvit_addon_cart_item_data_from_validation( $validation ) );
    wp_safe_redirect( wc_get_cart_url() );
    exit;
}, 5 );
function teinvit_credit_gifts_extra_slots_for_invitation( $target_token, $qty, $slots_per_unit = 10 ) {
    $target_token = sanitize_text_field( (string) $target_token );
    $qty = max( 0, (int) $qty );
    $slots_per_unit = max( 1, (int) $slots_per_unit );
    if ( $target_token === '' || $qty <= 0 || ! function_exists( 'teinvit_get_invitation' ) || ! function_exists( 'teinvit_save_invitation_config' ) ) {
        return false;
    }

    $inv = teinvit_get_invitation( $target_token );
    if ( ! $inv ) {
        return false;
    }

    $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : [];
    $current_extra = isset( $config['gifts_extra_slots'] ) ? (int) $config['gifts_extra_slots'] : 0;
    $config['gifts_extra_slots'] = max( 0, $current_extra ) + ( $qty * $slots_per_unit );

    teinvit_save_invitation_config( $target_token, [ 'config' => $config ] );

    return true;
}

function teinvit_order_gifts_allocations_get( WC_Order $order ) {
    $entries = $order->get_meta( '_teinvit_gifts_allocations', true );
    return is_array( $entries ) ? $entries : [];
}

function teinvit_order_gifts_allocations_upsert( WC_Order $order, array $allocation ) {
    $entries = teinvit_order_gifts_allocations_get( $order );
    $key = sanitize_text_field( (string) ( $allocation['allocation_key'] ?? '' ) );
    if ( $key === '' ) {
        return;
    }
    $entries[ $key ] = $allocation;
    $order->update_meta_data( '_teinvit_gifts_allocations', $entries );
}

function teinvit_token_upsert_gifts_allocation( $token, array $allocation ) {
    if ( ! function_exists( 'teinvit_get_invitation' ) || ! function_exists( 'teinvit_save_invitation_config' ) ) {
        return false;
    }
    $token = sanitize_text_field( (string) $token );
    if ( $token === '' ) {
        return false;
    }

    $inv = teinvit_get_invitation( $token );
    if ( ! $inv && function_exists( 'teinvit_seed_invitation_if_missing' ) && function_exists( 'teinvit_get_order_id_by_token' ) ) {
        $order_id = (int) teinvit_get_order_id_by_token( $token );
        if ( $order_id > 0 ) {
            teinvit_seed_invitation_if_missing( $token, $order_id );
            $inv = teinvit_get_invitation( $token );
        }
    }
    if ( ! $inv ) {
        return false;
    }

    $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : [];
    $list = isset( $config['gifts_allocations'] ) && is_array( $config['gifts_allocations'] ) ? $config['gifts_allocations'] : [];
    $key = sanitize_text_field( (string) ( $allocation['allocation_key'] ?? '' ) );
    if ( $key === '' ) {
        return false;
    }

    $updated = false;
    foreach ( $list as $idx => $entry ) {
        if ( ! is_array( $entry ) ) {
            continue;
        }
        if ( sanitize_text_field( (string) ( $entry['allocation_key'] ?? '' ) ) === $key ) {
            $list[ $idx ] = array_merge( $entry, $allocation );
            $updated = true;
            break;
        }
    }
    if ( ! $updated ) {
        $list[] = $allocation;
    }

    $config['gifts_allocations'] = array_values( $list );
    $summary = teinvit_build_gifts_summary_for_token( $token, $config );
    $config['gifts_base_slots_applied'] = (int) $summary['base_slots'];
    $config['gifts_extra_slots'] = (int) $summary['addon_slots'];
    $config['gifts_admin_slots'] = (int) ( $summary['admin_slots'] ?? 0 );
    $config['gifts_total_slots_applied'] = (int) $summary['total_slots'];
    $config['gifts_slots_used'] = (int) $summary['used_slots'];
    $config['gifts_slots_available'] = (int) $summary['available_slots'];
    $config['gifts_allocations'] = $summary['allocations'];

    teinvit_save_invitation_config( $token, [ 'config' => $config ] );
    return true;
}

function teinvit_credit_paid_edits_for_invitation( $target_token, $qty ) {
    $target_token = sanitize_text_field( (string) $target_token );
    $qty = max( 0, (int) $qty );
    if ( $target_token === '' || $qty <= 0 || ! function_exists( 'teinvit_get_invitation' ) || ! function_exists( 'teinvit_save_invitation_config' ) ) {
        return false;
    }

    $inv = teinvit_get_invitation( $target_token );
    if ( ! $inv ) {
        return false;
    }

    $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : [];
    if ( function_exists( 'teinvit_config_ensure_edit_balance_keys' ) ) {
        $config = teinvit_config_ensure_edit_balance_keys( $config );
    }
    $current_paid = isset( $config['edits_paid_remaining'] ) ? (int) $config['edits_paid_remaining'] : 0;
    $config['edits_paid_remaining'] = max( 0, $current_paid ) + $qty;

    $saved = teinvit_save_invitation_config( $target_token, [ 'config' => $config ] );
    if ( $saved === false ) {
        return false;
    }
    if ( function_exists( 'teinvit_sync_legacy_edit_balance_from_config' ) ) {
        teinvit_sync_legacy_edit_balance_from_config( $target_token, $config );
    }

    return true;
}

function teinvit_addon_cart_item_validation( array $cart_item ) {
    $product_id = (int) ( $cart_item['product_id'] ?? 0 );
    $variation_id = (int) ( $cart_item['variation_id'] ?? 0 );
    $expected_addon_type = sanitize_key( (string) ( $cart_item['teinvit_addon_type'] ?? '' ) );
    $addon_context = teinvit_addon_product_context( $product_id, $variation_id );

    if ( ! $addon_context && $expected_addon_type === '' ) {
        return null;
    }

    if ( ! $addon_context ) {
        return new WP_Error( 'teinvit_addon_unknown_product', teinvit_addon_customer_block_message() );
    }

    $target_token = sanitize_text_field( (string) ( $cart_item['teinvit_token'] ?? '' ) );
    if ( $target_token === '' ) {
        return new WP_Error( 'teinvit_addon_missing_token', teinvit_addon_customer_block_message() );
    }

    return teinvit_validate_addon_for_token( $product_id, $variation_id, $target_token, $expected_addon_type );
}

function teinvit_cart_addon_validation_errors() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return [];
    }

    $errors = [];
    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        $validation = teinvit_addon_cart_item_validation( is_array( $cart_item ) ? $cart_item : [] );
        if ( is_wp_error( $validation ) ) {
            $errors[ $cart_item_key ] = $validation;
        }
    }

    return $errors;
}

function teinvit_addon_add_cart_validation_notices() {
    foreach ( teinvit_cart_addon_validation_errors() as $error ) {
        teinvit_addon_add_customer_notice( $error->get_error_message() );
    }
}

add_filter( 'woocommerce_add_to_cart_validation', function( $passed, $product_id, $quantity, $variation_id = 0, $variations = [], $cart_item_data = [] ) {
    if ( ! $passed ) {
        return false;
    }

    $addon_context = teinvit_addon_product_context( $product_id, $variation_id );
    if ( ! $addon_context ) {
        return $passed;
    }

    $token = teinvit_addon_token_from_cart_data_or_request( is_array( $cart_item_data ) ? $cart_item_data : [] );
    $expected_addon_type = isset( $cart_item_data['teinvit_addon_type'] )
        ? sanitize_key( (string) $cart_item_data['teinvit_addon_type'] )
        : ( isset( $_REQUEST['teinvit_addon_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['teinvit_addon_type'] ) ) : '' );
    $validation = teinvit_validate_addon_for_token( $product_id, $variation_id, $token, $expected_addon_type );
    if ( is_wp_error( $validation ) ) {
        teinvit_addon_add_customer_notice( $validation->get_error_message() );
        return false;
    }

    return $passed;
}, 20, 6 );

add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id, $variation_id = 0, $quantity = 1 ) {
    $addon_context = teinvit_addon_product_context( $product_id, $variation_id );
    if ( ! $addon_context ) {
        return $cart_item_data;
    }

    $token = teinvit_addon_token_from_cart_data_or_request( is_array( $cart_item_data ) ? $cart_item_data : [] );
    $expected_addon_type = isset( $cart_item_data['teinvit_addon_type'] )
        ? sanitize_key( (string) $cart_item_data['teinvit_addon_type'] )
        : ( isset( $_REQUEST['teinvit_addon_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['teinvit_addon_type'] ) ) : '' );
    $validation = teinvit_validate_addon_for_token( $product_id, $variation_id, $token, $expected_addon_type );
    if ( is_wp_error( $validation ) ) {
        return $cart_item_data;
    }

    return array_merge( $cart_item_data, teinvit_addon_cart_item_data_from_validation( $validation ) );
}, 20, 4 );

add_filter( 'woocommerce_get_cart_item_from_session', function( $cart_item, $values ) {
    foreach ( [ 'teinvit_token', 'teinvit_addon_type', 'teinvit_target_vertical' ] as $key ) {
        if ( isset( $values[ $key ] ) && $values[ $key ] !== '' ) {
            $cart_item[ $key ] = $key === 'teinvit_token'
                ? sanitize_text_field( (string) $values[ $key ] )
                : sanitize_key( (string) $values[ $key ] );
        }
    }

    return $cart_item;
}, 20, 2 );

add_action( 'woocommerce_check_cart_items', 'teinvit_addon_add_cart_validation_notices', 20 );
add_action( 'woocommerce_checkout_process', 'teinvit_addon_add_cart_validation_notices', 5 );

add_action( 'woocommerce_checkout_create_order_line_item', function( $item, $cart_item_key, $values ) {
    if ( ! empty( $values['teinvit_token'] ) ) {
        $item->add_meta_data( '_teinvit_token_target', sanitize_text_field( $values['teinvit_token'] ), true );
    }
    if ( ! empty( $values['teinvit_addon_type'] ) ) {
        $item->add_meta_data( '_teinvit_addon_type', sanitize_key( $values['teinvit_addon_type'] ), true );
    }
    if ( ! empty( $values['teinvit_target_vertical'] ) ) {
        $item->add_meta_data( '_teinvit_target_vertical', sanitize_key( $values['teinvit_target_vertical'] ), true );
    }
}, 10, 3 );

function teinvit_addon_record_ledger_for_order_item( $order, $item, $target_token, $addon_type, $vertical, $status, $error_message = '', array $debug_context = [] ) {
    if ( ! function_exists( 'teinvit_upsert_order_token_addon_ledger' ) || ! is_object( $order ) || ! is_object( $item ) ) {
        return false;
    }

    $order_id = method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0;
    $order_item_id = method_exists( $item, 'get_id' ) ? (int) $item->get_id() : 0;
    $product_id = method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0;
    $variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;

    return teinvit_upsert_order_token_addon_ledger( [
        'target_token' => sanitize_text_field( (string) $target_token ),
        'order_id' => $order_id,
        'order_item_id' => $order_item_id,
        'product_id' => $product_id,
        'variation_id' => $variation_id,
        'addon_type' => sanitize_key( (string) $addon_type ),
        'vertical' => sanitize_key( (string) $vertical ),
        'status' => sanitize_key( (string) $status ),
        'capability_changed' => teinvit_addon_capability_changed_for_type( $addon_type ),
        'error_message' => $error_message,
        'debug_context' => $debug_context,
        'applied_at' => $status === 'applied' ? current_time( 'mysql' ) : null,
    ] );
}

add_action( 'woocommerce_checkout_order_created', function( $order ) {
    if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) ) {
        return;
    }

    foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
        $target_token = sanitize_text_field( (string) $item->get_meta( '_teinvit_token_target', true ) );
        $addon_type = sanitize_key( (string) $item->get_meta( '_teinvit_addon_type', true ) );
        $product_id = (int) $item->get_product_id();
        $variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
        $addon_context = teinvit_addon_product_context( $product_id, $variation_id, sanitize_key( (string) $item->get_meta( '_teinvit_target_vertical', true ) ) );
        if ( ! $addon_context && $addon_type === '' ) {
            continue;
        }

        $validation = teinvit_validate_addon_for_token( $product_id, $variation_id, $target_token, $addon_type );
        if ( is_wp_error( $validation ) ) {
            teinvit_addon_record_ledger_for_order_item(
                $order,
                $item,
                $target_token,
                $addon_type !== '' ? $addon_type : ( $addon_context['addon_type'] ?? 'unknown' ),
                $addon_context['vertical'] ?? '',
                'blocked',
                $validation->get_error_code(),
                [ 'phase' => 'checkout_order_created' ]
            );
            $order->add_order_note( sprintf( '[TeInvit] Addon blocat la creare comanda: item %d, token "%s", motiv %s.', (int) $item_id, $target_token, $validation->get_error_code() ) );
            continue;
        }

        teinvit_addon_record_ledger_for_order_item(
            $order,
            $item,
            $validation['target_token'],
            $validation['addon_type'],
            $validation['target_vertical'],
            'pending',
            '',
            [ 'phase' => 'checkout_order_created' ]
        );
    }
}, 20 );

add_action( 'woocommerce_checkout_create_order', function( $order, $data ) {
    if ( ! $order ) {
        return;
    }

    $token = '';
    if ( function_exists( 'WC' ) && WC()->cart ) {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( empty( $cart_item['teinvit_token'] ) ) {
                continue;
            }

            $token = sanitize_text_field( (string) $cart_item['teinvit_token'] );
            if ( $token !== '' ) {
                break;
            }
        }
    }

    if ( $token === '' && function_exists( 'WC' ) && WC()->session ) {
        $token = sanitize_text_field( (string) WC()->session->get( 'teinvit_token_target', '' ) );
    }

    if ( $token !== '' ) {
        $order->update_meta_data( '_teinvit_token_target', $token );
    }
}, 10, 2 );

function teinvit_apply_premium_upgrade_for_invitation( $target_token, $order, $item, array $catalog ) {
    $target_token = sanitize_text_field( (string) $target_token );
    if ( $target_token === '' || ! function_exists( 'teinvit_get_invitation' ) ) {
        return false;
    }

    $target_context = function_exists( 'teinvit_resolve_token_context' ) ? teinvit_resolve_token_context( $target_token ) : [];
    $target_vertical = is_array( $target_context ) ? sanitize_key( (string) ( $target_context['vertical'] ?? '' ) ) : '';
    $target_order_id = is_array( $target_context ) ? (int) ( $target_context['order_id'] ?? 0 ) : 0;
    $source_order_id = is_object( $order ) && method_exists( $order, 'get_id' ) ? (int) $order->get_id() : 0;
    $source_item_id = is_object( $item ) && method_exists( $item, 'get_id' ) ? (int) $item->get_id() : 0;

    $inv = teinvit_get_invitation( $target_token );
    if ( ! $inv && function_exists( 'teinvit_seed_invitation_if_missing' ) && $target_order_id > 0 ) {
        teinvit_seed_invitation_if_missing( $target_token, $target_order_id );
        $inv = teinvit_get_invitation( $target_token );
    }
    if ( ! $inv ) {
        return false;
    }

    $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : [];
    $config['premium_upgrade_active'] = 1;
    $config['premium_upgrade_last_order_id'] = $source_order_id;
    $config['premium_upgrade_last_order_item_id'] = $source_item_id;
    $config['premium_upgrade_applied_at'] = current_time( 'mysql' );
    if ( function_exists( 'teinvit_config_apply_default_included_edits' ) ) {
        $config = teinvit_config_apply_default_included_edits( $config, $catalog, 'woo_upgrade', $source_order_id );
    }

    if ( function_exists( 'teinvit_save_invitation_config_for_token' ) ) {
        $saved = teinvit_save_invitation_config_for_token( $target_token, [ 'config' => $config ], $target_vertical );
    } elseif ( function_exists( 'teinvit_save_invitation_config' ) ) {
        $saved = teinvit_save_invitation_config( $target_token, [ 'config' => $config ] );
    } else {
        $saved = false;
    }

    if ( $saved !== false && function_exists( 'teinvit_sync_legacy_edit_balance_from_config' ) ) {
        teinvit_sync_legacy_edit_balance_from_config( $target_token, $config );
    }

    return $saved !== false;
}

add_action( 'woocommerce_order_status_completed', function( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $did_update = false;
    $processed_changed = false;
    $processed_key = '_teinvit_completed_item_ids_processed';
    $processed = $order->get_meta( $processed_key, true );
    if ( ! is_array( $processed ) ) {
        $processed = [];
    }

    $main_token = sanitize_text_field( (string) $order->get_meta( '_teinvit_token', true ) );
    if ( $main_token === '' ) {
        $main_token = sanitize_text_field( (string) get_post_meta( (int) $order_id, '_teinvit_token', true ) );
    }
    if ( $main_token !== '' ) {
        $catalog_for_main = function_exists( 'teinvit_get_catalog_for_token' ) ? teinvit_get_catalog_for_token( $main_token ) : [];
        $base_slots = max( 0, (int) ( $catalog_for_main['default_free_gift_slots'] ?? 20 ) );
        $base_key = 'base:' . (int) $order_id . ':0';
        $base_saved = teinvit_token_upsert_gifts_allocation( $main_token, [
            'allocation_key' => $base_key,
            'kind' => 'base',
            'order_id' => (int) $order_id,
            'item_id' => 0,
            'product_id' => 0,
            'qty' => 1,
            'slots_per_unit' => $base_slots,
            'slots_total' => $base_slots,
            'slots_remaining' => $base_slots,
            'status' => 'applied',
            'applied_at' => current_time( 'mysql' ),
        ] );
        $order->add_order_note( sprintf( '[TeInvit Debug Gifts] main token=%s base_slots=%d upsert=%s', $main_token, $base_slots, $base_saved ? 'yes' : 'no' ) );
        teinvit_order_gifts_allocations_upsert( $order, [
            'allocation_key' => $base_key,
            'kind' => 'base',
            'order_id' => (int) $order_id,
            'item_id' => 0,
            'slots_total' => $base_slots,
            'slots_per_unit' => $base_slots,
            'status' => 'applied',
        ] );
        $order->update_meta_data( '_teinvit_base_gift_slots_applied', $base_slots );
        $did_update = true;
    } else {
        $order_token_rows = function_exists( 'teinvit_get_order_tokens_for_order' ) ? teinvit_get_order_tokens_for_order( (int) $order_id ) : [];
        if ( empty( $order_token_rows ) ) {
            $order->add_order_note( '[TeInvit Debug Gifts] main token missing at completed.' );
        }
    }

    $order_target_token = sanitize_text_field( (string) $order->get_meta( '_teinvit_token_target', true ) );
    $legacy_gifts_names = [ 'Pachet cadouri suplimentare (+10)' ];

    foreach ( $order->get_items() as $item_id => $item ) {
        if ( in_array( (int) $item_id, array_map( 'intval', $processed ), true ) ) {
            continue;
        }

        $product_id = (int) $item->get_product_id();
        $variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;
        $name = (string) $item->get_name();
        $qty = max( 0, (int) $item->get_quantity() );
        $target_token = sanitize_text_field( (string) $item->get_meta( '_teinvit_token_target', true ) );
        if ( $target_token === '' ) {
            $target_token = $order_target_token;
        }
        $addon_type = sanitize_key( (string) $item->get_meta( '_teinvit_addon_type', true ) );
        $target_vertical = sanitize_key( (string) $item->get_meta( '_teinvit_target_vertical', true ) );
        $addon_context = teinvit_addon_product_context( $product_id, $variation_id, $target_vertical );
        if ( ! $addon_context && $target_token !== '' && function_exists( 'teinvit_resolve_token_context' ) ) {
            $target_context_for_vertical = teinvit_resolve_token_context( $target_token );
            if ( is_array( $target_context_for_vertical ) && ! empty( $target_context_for_vertical['valid'] ) ) {
                $addon_context = teinvit_addon_product_context( $product_id, $variation_id, sanitize_key( (string) ( $target_context_for_vertical['vertical'] ?? '' ) ) );
            }
        }
        if ( ! $addon_context ) {
            $addon_context = teinvit_addon_product_context( $product_id, $variation_id );
        }

        $legacy_gifts_context = false;
        if ( ! $addon_context && $target_token !== '' && in_array( $name, $legacy_gifts_names, true ) && function_exists( 'teinvit_resolve_token_context' ) ) {
            $legacy_target_context = teinvit_resolve_token_context( $target_token );
            if ( is_array( $legacy_target_context ) && ! empty( $legacy_target_context['valid'] ) ) {
                $legacy_caps = teinvit_addon_capabilities_from_token_context( $legacy_target_context );
                if ( ! empty( $legacy_caps['can_buy_extra_gifts'] ) ) {
                    $legacy_gifts_context = true;
                    $addon_context = [
                        'is_addon' => true,
                        'addon_type' => 'extra_gifts',
                        'role_key' => 'extra_gifts_addon_ids',
                        'vertical' => sanitize_key( (string) ( $legacy_target_context['vertical'] ?? '' ) ),
                        'catalog' => teinvit_addon_catalog_for_token_context( $legacy_target_context ),
                        'target_token' => $target_token,
                        'target_context' => $legacy_target_context,
                        'target_vertical' => sanitize_key( (string) ( $legacy_target_context['vertical'] ?? '' ) ),
                        'capabilities' => $legacy_caps,
                        'capability_key' => 'can_buy_extra_gifts',
                    ];
                }
            }
        }

        if ( ! $addon_context && $addon_type === '' ) {
            continue;
        }

        if ( $qty <= 0 ) {
            $processed[] = (int) $item_id;
            $processed_changed = true;
            continue;
        }

        if ( $target_token === '' ) {
            $resolved_addon_type = $addon_type !== '' ? $addon_type : ( $addon_context['addon_type'] ?? 'unknown' );
            teinvit_addon_record_ledger_for_order_item( $order, $item, '', $resolved_addon_type, $addon_context['vertical'] ?? '', 'blocked', 'missing_target_token', [ 'phase' => 'completed' ] );
            $order->add_order_note( sprintf( '[TeInvit] Addon blocat: item %d, product %d nu are token tinta valid.', (int) $item_id, $product_id ) );
            $processed[] = (int) $item_id;
            $processed_changed = true;
            continue;
        }

        if ( $legacy_gifts_context ) {
            $validation = $addon_context;
        } else {
            $validation = teinvit_validate_addon_for_token( $product_id, $variation_id, $target_token, $addon_type );
        }

        $candidate_addon_type = $addon_type !== '' ? $addon_type : ( $addon_context['addon_type'] ?? '' );
        if ( is_wp_error( $validation ) && $validation->get_error_code() === 'teinvit_addon_capability_blocked' && $candidate_addon_type === 'premium_upgrade' && function_exists( 'teinvit_resolve_token_context' ) ) {
            $target_context_for_upgrade = teinvit_resolve_token_context( $target_token );
            if ( is_array( $target_context_for_upgrade ) && ! empty( $target_context_for_upgrade['valid'] ) && teinvit_token_base_state_allows_premium_upgrade( $target_context_for_upgrade, (int) $order_id ) && is_array( $addon_context ) ) {
                $validation = array_merge( $addon_context, [
                    'target_token' => $target_token,
                    'target_context' => $target_context_for_upgrade,
                    'target_vertical' => sanitize_key( (string) ( $target_context_for_upgrade['vertical'] ?? '' ) ),
                    'catalog' => teinvit_addon_catalog_for_token_context( $target_context_for_upgrade ),
                    'capabilities' => [
                        'state' => 'basic_pure',
                        'can_buy_extra_edits' => false,
                        'can_buy_extra_gifts' => false,
                        'can_buy_premium_upgrade' => true,
                    ],
                    'capability_key' => 'can_buy_premium_upgrade',
                ] );
            }
        }

        if ( is_wp_error( $validation ) ) {
            $resolved_addon_type = $addon_type !== '' ? $addon_type : ( $addon_context['addon_type'] ?? 'unknown' );
            teinvit_addon_record_ledger_for_order_item( $order, $item, $target_token, $resolved_addon_type, $addon_context['vertical'] ?? '', 'blocked', $validation->get_error_code(), [ 'phase' => 'completed' ] );
            $order->add_order_note( sprintf( '[TeInvit] Addon blocat: item %d, product %d, token "%s", motiv %s.', (int) $item_id, $product_id, $target_token, $validation->get_error_code() ) );
            $processed[] = (int) $item_id;
            $processed_changed = true;
            continue;
        }

        $target_token = sanitize_text_field( (string) $validation['target_token'] );
        $addon_type = sanitize_key( (string) $validation['addon_type'] );
        $target_vertical = sanitize_key( (string) $validation['target_vertical'] );
        $catalog = is_array( $validation['catalog'] ?? null ) ? $validation['catalog'] : [];
        $labels = teinvit_addon_type_labels();
        $applied = false;

        if ( $addon_type === 'extra_edits' ) {
            $applied = teinvit_credit_paid_edits_for_invitation( $target_token, $qty );
        } elseif ( $addon_type === 'extra_gifts' ) {
            $slots_per_unit = function_exists( 'teinvit_catalog_extra_gifts_slots_for_product' )
                ? teinvit_catalog_extra_gifts_slots_for_product( $catalog, $product_id, 10 )
                : 10;
            $allocation_key = 'addon:' . (int) $order_id . ':' . (int) $item_id;
            $applied = function_exists( 'teinvit_token_upsert_gifts_allocation' ) && teinvit_token_upsert_gifts_allocation( $target_token, [
                'allocation_key' => $allocation_key,
                'kind' => 'addon',
                'order_id' => (int) $order_id,
                'item_id' => (int) $item_id,
                'product_id' => $product_id,
                'qty' => $qty,
                'slots_per_unit' => $slots_per_unit,
                'slots_total' => $qty * $slots_per_unit,
                'slots_remaining' => $qty * $slots_per_unit,
                'status' => 'applied',
                'applied_at' => current_time( 'mysql' ),
            ] );
            if ( $applied ) {
                teinvit_order_gifts_allocations_upsert( $order, [
                    'allocation_key' => $allocation_key,
                    'kind' => 'addon',
                    'order_id' => (int) $order_id,
                    'item_id' => (int) $item_id,
                    'product_id' => $product_id,
                    'qty' => $qty,
                    'slots_per_unit' => $slots_per_unit,
                    'slots_total' => $qty * $slots_per_unit,
                    'status' => 'applied',
                ] );
                $item->update_meta_data( '_teinvit_gift_slots_applied_per_unit', $slots_per_unit );
                $item->update_meta_data( '_teinvit_gift_slots_applied_total', $qty * $slots_per_unit );
                if ( method_exists( $item, 'save' ) ) {
                    $item->save();
                }
                $settings = teinvit_get_settings( $target_token );
                if ( $settings ) {
                    teinvit_update_settings( $target_token, [
                        'gifts_paid_capacity' => (int) $settings['gifts_paid_capacity'] + ( $qty * $slots_per_unit ),
                    ] );
                }
            }
        } elseif ( $addon_type === 'premium_upgrade' ) {
            $applied = teinvit_apply_premium_upgrade_for_invitation( $target_token, $order, $item, $catalog );
        }

        if ( $applied ) {
            teinvit_addon_record_ledger_for_order_item( $order, $item, $target_token, $addon_type, $target_vertical, 'applied', '', [ 'phase' => 'completed', 'qty' => $qty ] );
            $order->add_order_note( sprintf( '[TeInvit] Addon aplicat: %s pe token %s (item %d, qty %d).', $labels[ $addon_type ] ?? $addon_type, $target_token, (int) $item_id, $qty ) );
            $did_update = true;
        } else {
            teinvit_addon_record_ledger_for_order_item( $order, $item, $target_token, $addon_type, $target_vertical, 'failed', 'apply_failed', [ 'phase' => 'completed', 'qty' => $qty ] );
            $order->add_order_note( sprintf( '[TeInvit] Addon esuat: %s nu a putut fi aplicat pe token %s (item %d).', $labels[ $addon_type ] ?? $addon_type, $target_token, (int) $item_id ) );
        }

        $processed[] = (int) $item_id;
        $processed_changed = true;
        continue;
    }

    if ( $did_update || $processed_changed ) {
        $order->update_meta_data( $processed_key, array_values( array_unique( array_map( 'intval', $processed ) ) ) );
        $order->save();
    }
}, 20 );

add_action( 'woocommerce_order_refunded', function( $order_id, $refund_id ) {
    if ( function_exists( 'teinvit_refund_legacy_gift_allocation_hook_enabled' ) && ! teinvit_refund_legacy_gift_allocation_hook_enabled() ) {
        return;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $token_candidates = [];
    $main_token = sanitize_text_field( (string) $order->get_meta( '_teinvit_token', true ) );
    if ( $main_token !== '' ) {
        $token_candidates[] = $main_token;
    }
    $target_token = sanitize_text_field( (string) $order->get_meta( '_teinvit_token_target', true ) );
    if ( $target_token !== '' ) {
        $token_candidates[] = $target_token;
    }
    foreach ( $order->get_items() as $item ) {
        $item_token = sanitize_text_field( (string) $item->get_meta( '_teinvit_token_target', true ) );
        if ( $item_token !== '' ) {
            $token_candidates[] = $item_token;
        }
    }
    $token_candidates = array_values( array_unique( array_filter( $token_candidates ) ) );

    foreach ( $token_candidates as $token ) {
        $inv = function_exists( 'teinvit_get_invitation' ) ? teinvit_get_invitation( $token ) : null;
        if ( ! $inv ) {
            continue;
        }
        $config = is_array( $inv['config'] ?? null ) ? $inv['config'] : [];
        $summary = teinvit_build_gifts_summary_for_token( $token, $config );
        $allocations = $summary['allocations'];
        $changed = false;

        foreach ( $allocations as &$allocation ) {
            if ( (string) ( $allocation['status'] ?? '' ) !== 'applied' ) {
                continue;
            }
            if ( (string) ( $allocation['kind'] ?? '' ) === 'admin_grant' ) {
                continue;
            }
            if ( (int) ( $allocation['order_id'] ?? 0 ) !== (int) $order_id ) {
                continue;
            }
            $total = max( 0, (int) ( $allocation['slots_total'] ?? 0 ) );
            $remaining = max( 0, (int) ( $allocation['slots_remaining'] ?? 0 ) );
            if ( $total > 0 && $remaining === $total ) {
                $allocation['status'] = 'reverted';
                $allocation['reverted_at'] = current_time( 'mysql' );
                $allocation['reverted_by_refund_id'] = (int) $refund_id;
                $changed = true;
            }
        }
        unset( $allocation );

        if ( ! $changed ) {
            continue;
        }

        $config['gifts_allocations'] = $allocations;
        $summary_after = teinvit_build_gifts_summary_for_token( $token, $config );
        $config['gifts_allocations'] = $summary_after['allocations'];
        $config['gifts_base_slots_applied'] = (int) $summary_after['base_slots'];
        $config['gifts_extra_slots'] = (int) $summary_after['addon_slots'];
        $config['gifts_admin_slots'] = (int) ( $summary_after['admin_slots'] ?? 0 );
        $config['gifts_total_slots_applied'] = (int) $summary_after['total_slots'];
        $config['gifts_slots_used'] = (int) $summary_after['used_slots'];
        $config['gifts_slots_available'] = (int) $summary_after['available_slots'];
        teinvit_save_invitation_config( $token, [ 'config' => $config ] );
    }
}, 20, 2 );

/* Legacy guest RSVP UI injection removed intentionally. */
