<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TeInvit_Saga_Product_Eligibility {
    private $verticals = [ 'wedding', 'birthday', 'baptism' ];
    private $packages = [ 'basic', 'premium' ];
    private $addons = [ 'premium_upgrade', 'extra_edits', 'extra_gifts' ];

    public function get_item_context( $item ) {
        if ( ! is_object( $item ) ) {
            return null;
        }

        $product_id = method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0;
        $variation_id = method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0;

        if ( function_exists( 'teinvit_get_configurable_product_context' ) ) {
            $context = teinvit_get_configurable_product_context( $product_id, $variation_id );
            if ( $this->is_allowed_package_context( $context ) ) {
                $context['saga_kind'] = 'product';
                return $context;
            }
        }

        if ( function_exists( 'teinvit_addon_product_context' ) ) {
            $context = teinvit_addon_product_context( $product_id, $variation_id );
            if ( $this->is_allowed_addon_context( $context ) ) {
                $context['saga_kind'] = 'addon';
                return $context;
            }
        }

        return null;
    }

    public function order_has_eligible_items( WC_Order $order ) {
        foreach ( $order->get_items( 'line_item' ) as $item ) {
            if ( $this->get_item_context( $item ) ) {
                return true;
            }
        }
        return false;
    }

    private function is_allowed_package_context( $context ) {
        if ( ! is_array( $context ) ) {
            return false;
        }

        $vertical = sanitize_key( (string) ( $context['vertical'] ?? '' ) );
        $package = sanitize_key( (string) ( $context['package_type'] ?? '' ) );

        return in_array( $vertical, $this->verticals, true ) && in_array( $package, $this->packages, true );
    }

    private function is_allowed_addon_context( $context ) {
        if ( ! is_array( $context ) ) {
            return false;
        }

        $vertical = sanitize_key( (string) ( $context['vertical'] ?? '' ) );
        $addon = sanitize_key( (string) ( $context['addon_type'] ?? '' ) );

        return in_array( $vertical, $this->verticals, true ) && in_array( $addon, $this->addons, true );
    }
}
