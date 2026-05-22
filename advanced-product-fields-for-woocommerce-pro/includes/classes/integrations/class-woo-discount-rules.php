<?php
namespace SW_WAPF_PRO\Includes\Classes\Integrations {

	class Woo_Discount_Rules {

        private $skip_add_to_cart = false;

		public function __construct() {
			add_filter('advanced_woo_discount_rules_cart_strikeout_price_html',                     [$this, 'strikethrough_price'],10,4);

			add_filter('wapf/pricing/cart_item_base',                                               [$this, 'get_cart_item_base_price'],10,3);

			add_filter('wapf/pricing/product',                                                      [$this, 'get_product_display_price'],10,2);

			add_filter('woocommerce_available_variation',                                           [$this, 'set_variant_price'], 10,3);

			add_filter('advanced_woo_discount_rules_product_price_on_before_calculate_discount',    [$this, 'product_price_before_discount_calculation'], 10, 5);

            add_filter( 'advanced_woo_discount_rules_free_product_cart_item_data',                  [$this, 'set_skip_add_to_cart'], 10, 2);
            add_action( 'advanced_woo_discount_rules_after_free_product_count_updated',             [$this, 'reset_skip_add_to_cart'], 10, 1);
            add_filter( 'wapf/skip_add_to_cart',                                                    [$this, 'set_add_cart_skip']);
            add_filter( 'wapf/skip_cart_validation',                                                [$this, 'set_add_cart_skip']);
		}

        public function set_add_cart_skip( $skip ) {
            if( $skip ) {
                return $skip;
            }
            return $this->skip_add_to_cart;
        }

        public function set_skip_add_to_cart( $cart_item_data, $existing_cart_item ) {
            $this->skip_add_to_cart = true;
            return $cart_item_data;
        }

        public function reset_skip_add_to_cart( $cart_item_key ) {
            $this->skip_add_to_cart = false;
        }

		public function product_price_before_discount_calculation($product_price, $product, $quantity, $cart_item, $calculate_discount_from) {
			if(!empty($cart_item) && !empty($cart_item['wapf_item_price'])) {
				$total_price = floatval($cart_item['wapf_item_price']['base']) + floatval( $cart_item['wapf_item_price']['options_total'] );
				return $total_price;
			}
			return $product_price;
		}

		public function strikethrough_price($html,$price,$cart_item,$cartitem_key) {
			if(!isset($cart_item['wapf']))
				return $html;
			return $price;
		}

		public function get_cart_item_base_price($price,$product,$qty) {
			$new_price = $this->calculate_product_discount_price($price,$product,$qty,0,true);
			return $new_price === false ? $price : $new_price;
		}

		public function get_product_display_price($price, $product) {
			$new_price = $this->calculate_product_discount_price($price,$product,1,$price);
			return $new_price === false ? $price : $new_price;
		}

		public function set_variant_price($variation_data,$product,$variations) {
			$new_price = $this->calculate_product_discount_price($variation_data['display_price'],$product, 1, $variation_data['display_price']);
			if($new_price !== false && $new_price !== $variation_data['display_price'])
				$variation_data['display_price'] = $new_price;

			return $variation_data;
		}

		private function calculate_product_discount_price($price,$product, $qty = 1,$custom_price=0, $is_cart = false) {
			add_filter('advanced_woo_discount_rules_do_recalculate_total', [$this, 'dont_recalc_totals'], 10, 1);
			$p = apply_filters('advanced_woo_discount_rules_get_product_discount_price_from_custom_price',$price,$product, $qty, $custom_price, 'discounted_price', false, $is_cart);
			remove_filter('advanced_woo_discount_rules_do_recalculate_total', [$this,'dont_recalc_totals'] );
			return $p;
		}

		public function dont_recalc_totals($r) { return false; }

	}

}