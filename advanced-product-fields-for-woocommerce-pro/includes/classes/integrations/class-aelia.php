<?php
namespace SW_WAPF_PRO\Includes\Classes\Integrations {

    use \WC_Aelia_CurrencySwitcher;

    class Aelia {

        public function __construct() {

            add_filter('wapf/pricing/product',                      [$this, 'set_product_base_price'], 10, 2);

            add_filter('woocommerce_available_variation',           [$this, 'set_variant_base_price'], 10, 3);

            add_filter( 'wapf/pricing/cart_item_base_for_formulas', [$this, 'convert_base_back'], 10, 4);

            add_filter('wapf/html/pricing_hint/amount',             [$this, 'convert_pricing_hint'], 10, 4);

            add_filter('wapf/pricing/cart_item_options',            [$this, 'convert_options_total'], 10, 4 );

            add_filter( 'wapf/linked_products/choice',              [ $this, 'change_product_choice_price' ], 10, 3 );

            add_action('wp_footer',                                 [$this, 'add_footer_script'], 100);

                    }

        public function change_product_choice_price( $choice, $field, $product ) {

            $choice['pricing_amount'] = $this->get_original_product_price( $product );

            return $choice;
        }

                public function convert_base_back(  $price, $product, $quantity, $cart_item ) {

            $info = $this->get_currency_info();

            if( $info['is_default'] ) {
                return $price;
            }

            return $this->get_original_product_price( $cart_item['data'] );

                    }

        public function set_product_base_price($price, $product) {

            $info = $this->get_currency_info();

            if( $info['is_default'] ) {
                return $price;
            }

                        if( in_array( $product->get_type(),['simple','subscription'] ) ) {
                return $this->get_original_product_price( $product );
            }

                        return $price;

                    }

                public function set_variant_base_price( $variant_data, $product, $variations ) {

            $info = $this->get_currency_info();

            if( $info['is_default'] ) {
                return $variant_data;
            }

                        if( in_array( $product->get_type(), ['variable','variable-subscription'] ) ) {
                $variant_data['apf_base'] = $this->get_original_product_price( $product );
            }

            return $variant_data;

                    }

                public function convert_options_total( $options_total, $product, $quantity, $cart_item ) {

            $info = $this->get_currency_info();

            if( ! $info['is_default'] ) {
                return (float) $options_total * $info['exchange_rate'];
            }

            return $options_total;

        }

        public function convert_pricing_hint( $amount, $product, $type, $for_page ) {

            if( $type === 'fx' && $for_page !== 'cart' ) return $amount;

            $info = $this->get_currency_info();
            if( ! $info['is_default'] ) {
                return (float) $amount * $info['exchange_rate'];
            }

            return $amount;
        }

        public function add_footer_script() {

            $info = $this->get_currency_info();

            if( $info['is_default'] ) return;

            ?>
            <script>
                var wapf_aelia_rate = <?php echo $info['exchange_rate']; ?> || 1;

                jQuery( document ).on( 'wapf/pricing', function( e, productTotal, optionsTotal ) {
                    var pt = productTotal * wapf_aelia_rate;
                    var ot = optionsTotal * wapf_aelia_rate;
                    jQuery( '.wapf-product-total' ).html(WAPF.Util.formatMoney(pt,window.wapf_config.display_options));
                    jQuery( '.wapf-options-total' ).html(WAPF.Util.formatMoney(ot,window.wapf_config.display_options));
                    jQuery( '.wapf-grand-total' ).html(WAPF.Util.formatMoney(pt+ot,window.wapf_config.display_options));
                });

                WAPF.Filter.add('wapf/fx/hint', function(price) {
                    return price*wapf_aelia_rate;
                });
            </script>
            <?php

        }

        private function get_currency_info() {

            static $info = null;

            if( $info === null ) {

                $settings = WC_Aelia_CurrencySwitcher::settings();
                $instance = WC_Aelia_CurrencySwitcher::instance();

                $curr = $instance->get_selected_currency();
                $default = $settings->base_currency();
                $exchange = $settings->get_exchange_rate($curr);

                $info = [
                    'current' => $curr,
                    'default' => $default,
                    'is_default' => $curr === $default,
                    'exchange_rate' => $exchange
                ];

            }

            return $info;

        }

                private function get_original_product_price( $product ) {

            $the_product = wc_get_product( $product->get_id() );

            $tax_display = get_option( 'woocommerce_tax_display_shop' );

            return 'incl' === $tax_display ?
                wc_get_price_including_tax(
                    $product,
                    array(
                        'qty'   => 1,
                        'price' => $the_product->get_price( 'edit' ),
                    )
                ) :
                wc_get_price_excluding_tax(
                    $product,
                    array(
                        'qty'   => 1,
                        'price' => $the_product->get_price( 'edit' ),
                    )
                );

        }

            }
}