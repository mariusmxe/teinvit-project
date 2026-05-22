<?php
namespace SW_WAPF_PRO\Includes\Classes\Integrations {

    use TierPricingTable\PriceManager;
    use TierPricingTable\Frontend\CartPriceManager;

    class Tiered_Pricing_Table{

        public function __construct() {
            add_action('wp_footer',                     [$this, 'add_javascript'], 100);
            add_filter('wapf/pricing/cart_item_base',   [$this, 'get_base_price'], 10, 4);
            add_filter('woocommerce_cart_item_price',   [$this, 'change_cart_item_display_price'],10,2);
        }

        public function change_cart_item_display_price($price, $cart_item) {

            if(!isset($cart_item['wapf_item_price'])) return $price;

            $needs_recalculation = apply_filters( 'tier_pricing_table/cart/need_price_recalculation/item', true, $cart_item );

            if(!$needs_recalculation) return $price;

            $show_discount_in_cart = get_option('tier_pricing_table_show_discount_in_cart','yes') === 'yes';

            $is_premium = function_exists('tpt_fs') && tpt_fs()->is_premium();
            if(!$is_premium) {
                return wc_price(floatval($cart_item['wapf_item_price']['base']) + floatval( $cart_item['wapf_item_price']['options_total'] ));
            }

            if(!$show_discount_in_cart) {
                return wc_price(floatval($cart_item['wapf_item_price']['base']) + floatval( $cart_item['wapf_item_price']['options_total'] ));
            } else {
                if (strpos($price,'<del>') !== false) {
                    $product = wc_get_product( $cart_item['data']->get_id() );
                    $regular_price = wc_get_price_to_display($product);
                    return '<del> ' . wc_price( $regular_price+ floatval($cart_item['wapf_item_price']['options_total']) ) . ' </del> <ins> ' . wc_price( floatval($cart_item['wapf_item_price']['base']) + floatval( $cart_item['wapf_item_price']['options_total'] ) ) . ' </ins>';
                }
            }

            return $price;

        }

        public function add_javascript() {
            ?>
            <script>
                WAPF.Filter.add('wapf/pricing/base', function(price, data) {
                    if(data.parent.find('.price-rule-active').length)
                        return data.parent.find('.price-rule-active').data('price-rules-price');
                    else if( data.parent.find('.tiered-pricing--active').length )
                        return data.parent.find('.tiered-pricing--active').data('tiered-price');
                    return price;
                });
                jQuery('.tpt__tiered-pricing').on('click', '.tiered-pricing-option,.tiered-pricing-block', function(e){
                    e.currentTarget.click();
                });

                jQuery(document).on('show_variation', function(){
                    setTimeout( function(){jQuery('.tiered-pricing--active').trigger('click')}, 10);
                });

            </script>
            <?php
        }

        public function get_base_price($price, $product, $quantity, $cart_item) {

            if (get_option('tier_pricing_table_summarize_variations', 'no' ) === 'yes' ) {
                $quantity = 0;
                foreach ( wc()->cart->cart_contents as $cart_content ) {
                    if ( $cart_content['product_id'] == $cart_item['product_id'] ) {
                        $quantity += $cart_content['quantity'];
                    }
                }
            }
            $p = PriceManager::getPriceByRules($quantity,$product->get_id());
            return $p ? $p : $price;
        }

    }

}