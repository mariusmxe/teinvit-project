<?php
/**
 * Read-only WP-CLI diagnostic for Wedding WAPF product resolution.
 *
 * Run from the WordPress root:
 * wp eval-file docs/teinvit-wapf-product-diagnostic.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    fwrite( STDERR, "This script must be run through WP-CLI after WordPress is loaded.\n" );
    return;
}

global $wpdb;

$cases = [
    [
        'label' => 'wedding_premium',
        'token' => '1528-998a9bef72581de4d564',
        'order_id' => 1528,
        'order_item_id' => 684,
        'product_id' => 610,
    ],
    [
        'label' => 'wedding_basic',
        'token' => '1528-ac07c3577aece96b1b62',
        'order_id' => 1528,
        'order_item_id' => 685,
        'product_id' => 600,
    ],
];

$post_summary = static function( $post_id ) use ( $wpdb ) {
    $post_id = max( 0, (int) $post_id );
    $row = $post_id > 0
        ? $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_type, post_status, post_title, post_parent FROM {$wpdb->posts} WHERE ID = %d LIMIT 1", $post_id ), ARRAY_A )
        : null;

    $product_type_terms = [];
    if ( $post_id > 0 && taxonomy_exists( 'product_type' ) ) {
        $terms = wp_get_object_terms( $post_id, 'product_type', [ 'fields' => 'names' ] );
        if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
            $product_type_terms = array_values( $terms );
        }
    }

    return [
        'id' => $post_id,
        'exists_in_wp_posts' => is_array( $row ),
        'post_type' => is_array( $row ) ? (string) $row['post_type'] : '',
        'post_status' => is_array( $row ) ? (string) $row['post_status'] : '',
        'post_title' => is_array( $row ) ? (string) $row['post_title'] : '',
        'post_parent' => is_array( $row ) ? (int) $row['post_parent'] : 0,
        'product_type_terms' => $product_type_terms,
    ];
};

$product_summary = static function( $product ) {
    if ( ! $product instanceof WC_Product ) {
        return [
            'valid' => false,
            'class' => is_object( $product ) ? get_class( $product ) : gettype( $product ),
        ];
    }

    return [
        'valid' => true,
        'class' => get_class( $product ),
        'id' => (int) $product->get_id(),
        'type' => method_exists( $product, 'get_type' ) ? (string) $product->get_type() : '',
        'status' => method_exists( $product, 'get_status' ) ? (string) $product->get_status() : '',
        'name' => method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '',
        'slug' => method_exists( $product, 'get_slug' ) ? (string) $product->get_slug() : '',
        'parent_id' => method_exists( $product, 'get_parent_id' ) ? (int) $product->get_parent_id() : 0,
    ];
};

$local_wapf_meta_summary = static function( $post_id ) {
    $post_id = max( 0, (int) $post_id );
    $raw = $post_id > 0 ? get_post_meta( $post_id, '_wapf_fieldgroup', true ) : null;
    $fields = is_array( $raw ) && isset( $raw['fields'] ) && is_array( $raw['fields'] ) ? $raw['fields'] : [];
    $variables = is_array( $raw ) && isset( $raw['variables'] ) && is_array( $raw['variables'] ) ? $raw['variables'] : [];

    return [
        'post_id' => $post_id,
        'exists' => ! empty( $raw ),
        'raw_type' => gettype( $raw ),
        'id' => is_array( $raw ) && isset( $raw['id'] ) ? (string) $raw['id'] : '',
        'fields_count' => count( $fields ),
        'variables_count' => count( $variables ),
    ];
};

$wapf_groups_summary = static function( $product ) {
    if ( ! $product instanceof WC_Product || ! function_exists( 'wapf_get_field_groups_of_product' ) ) {
        return [
            'can_check' => false,
            'count' => 0,
            'groups' => [],
        ];
    }

    $groups = wapf_get_field_groups_of_product( $product );
    $summary = [];
    if ( is_array( $groups ) ) {
        foreach ( $groups as $group ) {
            $fields = is_object( $group ) && isset( $group->fields ) ? (array) $group->fields : [];
            $variables = is_object( $group ) && isset( $group->variables ) ? (array) $group->variables : [];
            $summary[] = [
                'id' => is_object( $group ) && isset( $group->id ) ? (string) $group->id : '',
                'type' => is_object( $group ) && isset( $group->type ) ? (string) $group->type : '',
                'fields_count' => count( $fields ),
                'variables_count' => count( $variables ),
            ];
        }
    }

    return [
        'can_check' => true,
        'count' => is_array( $groups ) ? count( $groups ) : 0,
        'groups' => $summary,
    ];
};

$order_tokens_table = function_exists( 'teinvit_order_tokens_table' ) ? teinvit_order_tokens_table() : $wpdb->prefix . 'teinvit_order_tokens';
$order_tokens_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $order_tokens_table ) ) === $order_tokens_table;

$token_row_summary = static function( $token ) use ( $wpdb, $order_tokens_table, $order_tokens_table_exists ) {
    if ( ! $order_tokens_table_exists ) {
        return [
            'table_exists' => false,
            'row' => null,
        ];
    }

    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$order_tokens_table} WHERE token = %s LIMIT 1", $token ), ARRAY_A );
    return [
        'table_exists' => true,
        'row' => is_array( $row ) ? $row : null,
    ];
};

$order_item_summary = static function( $order_id, $order_item_id ) use ( $product_summary, $wapf_groups_summary ) {
    $order = function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order_id ) : null;
    $item = $order && method_exists( $order, 'get_item' ) ? $order->get_item( (int) $order_item_id ) : null;
    $item_product = $item instanceof WC_Order_Item_Product && method_exists( $item, 'get_product' ) ? $item->get_product() : null;

    return [
        'order_valid' => $order instanceof WC_Order,
        'item_valid' => $item instanceof WC_Order_Item_Product,
        'item_id' => $item instanceof WC_Order_Item_Product && method_exists( $item, 'get_id' ) ? (int) $item->get_id() : 0,
        'item_name' => $item instanceof WC_Order_Item_Product && method_exists( $item, 'get_name' ) ? (string) $item->get_name() : '',
        'item_get_product_id' => $item instanceof WC_Order_Item_Product && method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0,
        'item_get_variation_id' => $item instanceof WC_Order_Item_Product && method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0,
        'meta_product_id' => function_exists( 'wc_get_order_item_meta' ) ? (int) wc_get_order_item_meta( (int) $order_item_id, '_product_id', true ) : 0,
        'meta_variation_id' => function_exists( 'wc_get_order_item_meta' ) ? (int) wc_get_order_item_meta( (int) $order_item_id, '_variation_id', true ) : 0,
        'meta_wapf_field_groups' => function_exists( 'wc_get_order_item_meta' ) ? wc_get_order_item_meta( (int) $order_item_id, '_wapf_field_groups', true ) : null,
        'item_get_product' => $product_summary( $item_product ),
        'item_product_wapf_groups' => $wapf_groups_summary( $item_product ),
    ];
};

$result = [
    'environment' => [
        'wp_version' => get_bloginfo( 'version' ),
        'wc_version' => defined( 'WC_VERSION' ) ? WC_VERSION : '',
        'woocommerce_loaded' => function_exists( 'wc_get_product' ) && class_exists( 'WC_Product' ),
        'wapf_display_function_exists' => function_exists( 'wapf_display_field_groups_for_product' ),
        'wapf_get_function_exists' => function_exists( 'wapf_get_field_groups_of_product' ),
        'order_tokens_table' => $order_tokens_table,
        'order_tokens_table_exists' => $order_tokens_table_exists,
    ],
    'cases' => [],
];

foreach ( $cases as $case ) {
    $product_id = (int) $case['product_id'];
    $direct_product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

    $result['cases'][] = [
        'label' => $case['label'],
        'token' => $case['token'],
        'expected_order_id' => (int) $case['order_id'],
        'expected_order_item_id' => (int) $case['order_item_id'],
        'expected_product_id' => $product_id,
        'order_tokens' => $token_row_summary( $case['token'] ),
        'wp_posts' => $post_summary( $product_id ),
        'wc_get_product' => $product_summary( $direct_product ),
        'local_wapf_meta' => $local_wapf_meta_summary( $product_id ),
        'direct_product_wapf_groups' => $wapf_groups_summary( $direct_product ),
        'order_item' => $order_item_summary( $case['order_id'], $case['order_item_id'] ),
    ];
}

echo wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
