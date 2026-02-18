<?php

namespace SW_WAPF_PRO\Includes\Controllers {

    if ( !defined( 'ABSPATH' ) ) {
        die;
    }

    class Integrations_Controller
    {

        private $apply_qty = 0;

                private $available_integrations = [
	        'Barn2\Plugin\WC_Quick_View_Pro\Quick_View_Plugin'  => 'Quickview',
	        'WC_Product_Table_Plugin'                           => 'Product_Table',
	        'TierPricingTable\TierPricingTablePlugin'           => 'Tiered_Pricing_Table',
	        'WOOCS'                                             => 'Woocs',
	        'YITH_Request_Quote'                                => 'Yith_RAQ',
	        'WC_Subscriptions'                                  => 'WooCommerce_Subscriptions',
	        'Wdr\App\Controllers\DiscountCalculator'            => 'Woo_Discount_Rules',
	        'WC_Aelia_CurrencySwitcher'                         => 'Aelia',
        ];

        private $available_themes = [
        	'Woodmart'  => 'Woodmart',
        	'Flatsome'  => 'Flatsome',
	        'Astra'     => 'Astra'
        ];

        public function __construct()
        {
        	add_action( 'plugins_loaded',                           [$this,'add_integrations'], 50 );

            add_action( 'woocommerce_coupon_options',               [$this, 'add_wc_coupon_options'] );
            add_action( 'woocommerce_coupon_options_save',          [$this, 'save_extra_coupon_data'] );
            add_filter( 'woocommerce_coupon_get_discount_amount',   [$this, 'recalculate_coupon_discount'],1000, 5 );
            add_filter( 'woocommerce_coupon_get_apply_quantity',    [$this, 'capture_apply_quantity'], 10, 4 );

        }

        function capture_apply_quantity( $apply_quantity, $item, $coupon, $cart ) {
            $this->apply_qty =  max( 0, $apply_quantity );
            return $apply_quantity;
        }

                public function recalculate_coupon_discount( $discount, $price_to_discount, $cart_item, $single, $coupon ) {

                        if( !empty( $cart_item['wapf_item_price'] ) && $coupon->get_discount_type() === 'percent' && $coupon->get_meta( 'wapf_excl_addons' ) === 'yes' ) {
                $price_to_discount = $price_to_discount - ( ( $cart_item['wapf_item_price']['options_total'] ?? 0 ) * $this->apply_qty );
                return (float) ( $price_to_discount * ( $coupon->get_amount() / 100 ) );
            }

                        return $discount;

                    }

                public function add_wc_coupon_options() {

                        echo '<div class="wapf-discount-option" style="display: none">';

                        woocommerce_wp_checkbox( [ 
                'id'            => 'wapf_excl_addons', 
                'label'         => __( 'Calculate only on base price', 'sw-wapf' ), 
                'description'   => sprintf( __( 'Calculate the percentage only on the base product price & exclude any additional pricing set by <a href="https://www.studiowombat.com/plugin/advanced-product-fields-for-woocommerce/">Advanced Product Fields for WooCommerce</a>.', 'sw-wapf' ) ) 
            ] );

                        echo '</div><script>document.addEventListener("DOMContentLoaded", function() { function toggle() { document.querySelector(".wapf-discount-option").style.display = document.getElementById("discount_type").value === "percent" ? "block" : "none"; } toggle(); document.getElementById("discount_type").addEventListener( "change", toggle ); });</script>';

                  }

        public function save_extra_coupon_data( $post_id ) {

            if ( isset( $_POST['wapf_excl_addons' ] ) && $_POST['wapf_excl_addons' ] === 'yes' ) {
                update_post_meta( $post_id, 'wapf_excl_addons', sanitize_text_field( $_POST['wapf_excl_addons'] ) );
            } else {
                delete_post_meta( $post_id, 'wapf_excl_addons' );
            }

             }

        public function add_integrations() {

	        foreach ( $this->available_integrations as $integration => $class ) {
		        if( class_exists( $integration ) ) {
		        	$n = 'SW_WAPF_PRO\\Includes\\Classes\\Integrations\\' . $class;
			        new $n();
		        }
	        }

	        $theme = wp_get_theme();
			$parent_theme = $theme->parent() ? $theme->parent()->Name  : '';

	        if( $theme->exists() ) {
		        foreach( $this->available_themes as $theme_name => $class ) {
			        if($theme->Name === $theme_name || $parent_theme === $theme_name ) {
				        $n = 'SW_WAPF_PRO\\Includes\\Classes\\Integrations\\' . $class;
				        new $n();
			        }
		        }
	        }

        }

            }
}