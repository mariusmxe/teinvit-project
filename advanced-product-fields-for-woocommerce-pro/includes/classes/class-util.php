<?php

namespace SW_WAPF_PRO\Includes\Classes {

    use SW_WAPF_PRO\Includes\Models\Field;

    class Util {

        public static function array_to_attributes( $arr ): string {

            $str = '';

            foreach ( $arr as $key => $value ) {
                $str .= ' ' . $key . ( isset( $value ) && strlen( '' . $value ) > 0 ? ( '="' . esc_attr( $value ) . '"' ) : '' );
            }

            return $str;

        }

        public static function to_html_attribute_string( $thing ): string {

            if( is_string( $thing ) ) {
                return _wp_specialchars( $thing, ENT_QUOTES, 'UTF-8', true );
            }

            $encoded = wp_json_encode( $thing );

                        return wc_esc_json( $encoded );

                    }

                public static function pricing_hint_format(): string {

            static $hint = null;

            if( $hint === null ) {
                $hint = get_option('wapf_hint_format', '');
                if( empty( $hint ) ) $hint = '(+{x})';
            }

            return $hint;

        }

		public static function show_pricing_hints(): bool {

            static $show = null;

            if( $show === null ) {
                $show = get_option('wapf_show_pricing_hints','yes') === 'yes';
            }

			return $show;
		}

                public static function can_edit_cart_item( $cart_item ): bool {

            return Util::can_edit_in_cart() && ( isset( $cart_item['wapf'] ) || isset( $cart_item['_wapf_children'] ) );

                    }

        public static function can_edit_in_cart(): bool {

            static $can_edit = null;

            if( $can_edit === null ) {
                $can_edit = get_option( 'wapf_edit_cart', 'no' ) === 'yes';
            }

            return $can_edit;

        }

        public static function should_hide_on( Field $field, $page ): bool {
            return isset( $field->options[ 'hide_' . $page ] ) && $field->options[ 'hide_' . $page ];
        }

                public static function should_add_linked_products_to_cart(): bool {
            return true; 
        }

                public static function should_use_cart_field( $cart_field ): bool {

                        if( Util::should_add_linked_products_to_cart() && isset( $cart_field['type'] ) && $cart_field['type'] === 'products' ) {
                return false;
            }

                        return true;
        }

        	}
}