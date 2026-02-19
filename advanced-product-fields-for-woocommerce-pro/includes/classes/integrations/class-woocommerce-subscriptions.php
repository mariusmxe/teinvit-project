<?php

namespace SW_WAPF_PRO\Includes\Classes\Integrations {

	class WooCommerce_Subscriptions {

        public $skip_cart_validation = false;

		public function __construct() {
			add_filter('wapf/admin/allowed_product_types',                  [$this, 'allowed_product_types']);

            add_action('wp_footer',                                         [$this, 'add_javascript'], 100);
            add_filter('wapf/pricing/cart_item_base',                       [$this, 'cart_item_base_price'], 10, 4);

            add_action('wcs_before_early_renewal_setup_cart_subscription',  [$this, 'skip_cart_validation'], 10, 1 );
            add_action('wcs_before_renewal_setup_cart_subscriptions',       [$this, 'skip_cart_validation'], 10, 2 );
            add_action('wcs_after_early_renewal_setup_cart_subscription',   [$this, 'unset_skip_cart_validation'], 10, 1 );
            add_action('wcs_after_renewal_setup_cart_subscriptions',        [$this, 'unset_skip_cart_validation'], 10, 2 );
            add_filter( 'wapf/skip_cart_validation',                        [$this, 'set_skip_cart_validation'], 10, 1 );
		}

        public function set_skip_cart_validation( $skip ) {
            return $this->skip_cart_validation;
        }

        public function skip_cart_validation() {
            $this->skip_cart_validation = true;
        }

        public function unset_skip_cart_validation() {
            $this->skip_cart_validation = false;
        }

		public function cart_item_base_price($price,$product, $quantity, $cart_item) {
			if(in_array($product->get_type(),['subscription','variable-subscription','subscription_variation']))
				return floatval(\WC_Subscriptions_Product::get_price($product)); 

			return $price;
		}

		public function allowed_product_types($product_types) {
			$product_types[] = 'subscription';
			$product_types[] = 'variable-subscription';
			return $product_types;
		}

		public function add_javascript() {
			?>
			<script>
                WAPF.Filter.add('wapf/pricing/base',function(price, data) {
                    if(WAPF.Util.currentProductType(data.parent) === 'variable-subscription') {
                        var v = WAPF.Util.getVariation(data.parent);
                        if(v)
                            price = v.display_price;
                    }
                    return price;
                });
			</script>
			<?php
		}

	}

}