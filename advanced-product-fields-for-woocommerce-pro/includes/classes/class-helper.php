<?php

namespace SW_WAPF_PRO\Includes\Classes {

    use Automattic\WooCommerce\Admin\API\AI\Product;
    use SW_WAPF_PRO\Includes\Models\Field;

    class Helper
    {

                public static $allowed_html_minimal = [
            'a' => [
                'href' => [],
                'title' => [],
                'target' => [],
                'class' => []
            ],
            'b' => ['class' => []],
            'em' => ['class' => []],
            'strong' => ['class' => []],
            'i' => ['class' => []],
            'span' => ['style' => [],'class' => []],
            'ul' => ['class' => []],
            'ol' => ['class' => []],
            'li' => ['class' => []],
            'br' => [],
            'img' => ['style' => [], 'class' => [],'src' => [] ],
        ];

                public static function is_rest_for_mini_cart() {

            if( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
                global $wp;

                if( isset( $wp->query_vars['rest_route'] ) && ( strpos( $wp->query_vars['rest_route'], '/cart/') !== false || substr( $wp->query_vars['rest_route'], -5 ) === '/cart' ) ) {

                    $ref = wp_get_referer();
                    $page_id = wc_get_page_id( 'cart' );

                    return empty( $ref ) || url_to_postid( $ref ) === $page_id;
                }

                            }

                        return false;

                    }

                public static function is_rest(): bool {

                        return defined('REST_REQUEST' ) && REST_REQUEST;

                    }

        public static function is_rest_for( $for = 'cart' ): bool {

                        if( ! self::is_rest() ) {
                return false;
            }

            global $wp;
            $ref = wp_get_referer();
            $page_id = wc_get_page_id( $for );

            if ( empty( $page_id ) || $page_id < 1 ) {
                return false;
            }

            $ref_id = ! empty( $ref ) ? url_to_postid( $ref ) : 0;

            if( isset( $wp->query_vars['rest_route'] ) && ( strpos( $wp->query_vars['rest_route'], '/' . $for . '/' ) !== false || substr( $wp->query_vars['rest_route'], ((strlen( $for )+1)*-1) ) === '/' . $for ) ) {
                return empty( $ref ) || $ref_id === $page_id;
            }

            return ! empty( $ref ) && $ref_id === $page_id;

                    }

                public static function is_rest_for_cart() {

                        if( defined('REST_REQUEST' ) && REST_REQUEST ) {

                global $wp;

                                $ref = wp_get_referer();
                $page_id = wc_get_page_id( 'cart' );

                if( isset( $wp->query_vars['rest_route'] ) && ( strpos( $wp->query_vars['rest_route'], '/cart/') !== false || substr( $wp->query_vars['rest_route'], -5 ) === '/cart' ) ) {
                    return empty( $ref ) || url_to_postid( $ref ) === $page_id;
                }

                return ! empty( $ref ) && url_to_postid( $ref ) === $page_id;

                            }

                        return false;

                    }

        public static function is_rest_for_checkout() {

            if( defined('REST_REQUEST') && REST_REQUEST ) {

                global $wp;
                if( isset( $wp->query_vars['rest_route'] ) && strpos( $wp->query_vars['rest_route'], '/checkout/') !== false ) {
                    return true;
                }

                                $ref = wp_get_referer();
                if( empty( $ref ) ) {
                    return false;
                }

                                $page_id = wc_get_page_id( 'checkout' );
                return $page_id === url_to_postid( $ref );

                        }

            return false;

                    }

            	#region Date functions

        		public static function date_format_to_php_format($date_format) {

			$search = ['mm', 'dd', 'yyyy' ];
			$replace = [ 'm', 'd', 'Y'];
			$search2 = [ 'm', 'd', 'yy' ];
			$replace2 = [ 'n', 'j', 'y' ];

			for($i=0; $i<count($search); $i++) {
				$c=0;
				$date_format = str_replace($search[$i],$replace[$i],$date_format,$c);
				if($c === 0)
					$date_format = str_replace($search2[$i],$replace2[$i],$date_format);
			}

			return $date_format;

		}

    	public static function date_format_to_regex($date_format) {
		    return str_replace(
			    [
				    'mm',
				    'dd',
				    'yyyy',
				    'm',
				    'd',
				    'yy',
				    '.',
				    '/'
			    ],
			    [
				    '(0[1-9]|1[012])',
				    '(0[1-9]|[12][0-9]|3[01])',
				    '[0-9]{4}',
				    '([1-9]|1[012])',
				    '([1-9]|[12][0-9]|3[01])',
				    '[0-9]{2}',
				    '\.',
				    '\/',
			    ],
			    $date_format
		    );
	    }

        	    #endregion

		#region String functions

                public static function shorten_text( $text, $max_length = 100 ) {

            $max_length = apply_filters( 'wapf/shorten_text_limit', $max_length );

                        if ( mb_strlen($text) <= $max_length ) {
                return $text;
            }

                        return mb_substr( $text, 0, $max_length ) . '...';

                    }

                public static function sanitize( $data, $type, $fallback = null, $extra = [] ) {

                        switch( $type ) {

                case 'text':
                    $data = trim( sanitize_text_field( $data ) );
                    return empty( $data ) && $data !== '0' ? $fallback : $data;

                                case 'textarea':
                    $data = trim( sanitize_textarea_field( $data ) );
                    return empty( $data ) && $data !== '0' ? $fallback : $data;

                                    case 'options':
                    $data = trim( sanitize_text_field( '' . $data ) );
                    return in_array( $data, $extra, false ) ? $data : $fallback;

                                    case 'int':
                    return trim( '' . $data) === '' ? $fallback : intval( $data );

                                    case 'decimal':
                case 'float':
                    return trim( '' . $data) === '' ? $fallback : floatval( $data );

                                    case 'bool': return rest_sanitize_boolean( $data );

                                case 'html': return wp_kses( trim( '' . $data ), self::$allowed_html_minimal );

                                case 'url': $url = esc_url_raw( trim( '' . $data ) ); return empty( $url ) ? $fallback : $url;

                                default: return $data;

                                }

                    }

        public static function sanitize_textfield_without_tags( $str ) {

            if ( is_object( $str ) || is_array( $str ) ) {
                return '';
            }

            $str = (string) $str;

            $filtered = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', wp_check_invalid_utf8( $str ) );

            $filtered = preg_replace( '/[\r\n\t ]+/', ' ', $filtered );

            $filtered = trim( $filtered );

            $found = false;
            while ( preg_match( '/%[a-f0-9]{2}/i', $filtered, $match ) ) {
                $filtered = str_replace( $match[0], '', $filtered );
                $found    = true;
            }

            if ( $found ) {
                $filtered = trim( preg_replace( '/ +/', ' ', $filtered ) );
            }

            return $filtered;
        }

        		public static function string_to_date($str, $format = 'm-d-Y' ) {
			$split = explode('-',$str);
			if(sizeof($split) === 2) $str .= ('-' . date('Y'));
			$day = \DateTime::createFromFormat($format,$str , wp_timezone());
			$day->setTime(0,0,0);
			return $day;
		}

		public static function extract_upload_urls_from_html($html) {

			$wp_upload_dir = wp_upload_dir();
			$path = $wp_upload_dir['baseurl'] . '/' . File_Upload::$upload_parent_dir;

			$urls = [];
			$htmls = explode(', ', $html);

			foreach( $htmls as $h ) {
				if( empty( $h ) ) continue;
				$url = self::extract_url_from_a_tag( $h );
				$urls[] = str_replace( trailingslashit( $path ), '', $url );
			}

			return $urls;
		}

		public static function extract_url_from_a_tag($html) {
			if(empty($html)) return '';
			if(strpos($html,'<a href=') === false) return $html; 
			$url = preg_match('/<a href="(.+?)"/', $html, $match);
			if(count($match) >1) return $match[1];
			return '';
		}

		#endregion

		public static function split_multibyte_string($str) {
			return function_exists('mb_str_split') ? mb_str_split($str) :  preg_split('//u', $str, null, PREG_SPLIT_NO_EMPTY);
		}

    	public static function wp_slash( $value ) {
		    if ( is_array( $value ) ) {
			    $value = array_map( [ self::class, 'wp_slash' ], $value );
		    }
		    if ( is_string( $value ) ) {
			    return addslashes( $value );
		    }
		    return $value;
	    }

        public static function get_all_roles() {
            $roles = get_editable_roles();
            $result = [];

            foreach ( $roles as $id => $role ) {
                $result[] = [ 'id' => $id, 'text' => $role['name'] ];
            }

            return $result;
        }

        public static function thing_to_html_attribute_string($thing) {

            _deprecated_function( __FUNCTION__, '3.0', 'Util::to_html_attribute_string' );
            return Util::to_html_attribute_string( $thing );

        }

	    public static function normalize_string_decimal($number)
	    {
		    return preg_replace('/\.(?=.*\.)/', '', (str_replace(',', '.', '' . $number)));
	    }

        public static function lighten_darken_hex( string $hex, float $percent ): string {

            $hex = ltrim($hex, '#');
            $hex = array_map('hexdec', str_split($hex, 2));

            foreach ($hex as & $color) {
                $adjustableLimit = $percent < 0 ? $color : 255 - $color;
                $adjustAmount = ceil($adjustableLimit * $percent);
                $color = str_pad(dechex($color + $adjustAmount), 2, '0', STR_PAD_LEFT);
            }

            return '#' . implode($hex);
        }

        #region Price functions

        private static function get_real_tax_multiplier( $product ) {

            $multiplier = 1;

                        if( $product->is_taxable() ) {

                if ( ! empty( WC()->customer ) && WC()->customer->get_is_vat_exempt() ) {
                    $multiplier = 1;
                } else {
                    $tax_rates  = \WC_Tax::get_rates( $product->get_tax_class() );
                    $taxes      = \WC_Tax::calc_tax( 1, $tax_rates );
                    $multiplier = 1 + array_sum( $taxes );
                }
            }

                        return $multiplier;

                        }

        public static function get_product_price_for_calc( $product ) {

            if( ! wc_tax_enabled() ) {
                return $product->get_price();
            }

            $incl_tax = wc_prices_include_tax();

                        if( $incl_tax ) {

                $tax_display = get_option( 'woocommerce_tax_display_shop' );

                if( $tax_display === 'excl' ) {
                    $multiplier = 1 / self::get_real_tax_multiplier( $product );
                    return floatval( $product->get_price() ) * $multiplier;
                }

                            }

                        return wc_get_price_to_display( $product, ['qty' => 1, 'price' => 1] );

                    }

                public static function get_tax_multiplier2( $product, $based_on_display_setting = true ) {

            $multiplier = 1; 

            if( ! wc_tax_enabled() ) {
                return $multiplier;
            }

                        if( $based_on_display_setting ) { 

                                $incl_tax = wc_prices_include_tax(); 

                if( $incl_tax ) { 
                    $tax_display = get_option( 'woocommerce_tax_display_shop' );

                    if( $tax_display === 'excl' ) {
                        $multiplier = 1 / self::get_real_tax_multiplier( $product );
                    }

                                    } else { 
                    $multiplier = wc_get_price_to_display( $product, ['qty' => 1, 'price' => 1] );
                }

            } else { 

                                $multiplier = self::get_real_tax_multiplier( $product );

                            }

                        return $multiplier;

                    }

        public static function get_tax_multiplier( $product ) {

            $multiplier = 1;

                        if( $product->is_taxable() ) {

                if ( ! empty( WC()->customer ) && WC()->customer->get_is_vat_exempt() ) {
                    $multiplier = 1;
                } else {
                    $tax_rates  = \WC_Tax::get_rates( $product->get_tax_class() );
                    $taxes      = \WC_Tax::calc_tax( 1, $tax_rates );
                    $multiplier = 1 + array_sum( $taxes );
                }
            }

                        return $multiplier;

                    }

                public static function price_excl_tax( $product, $price ) {

            static $tax_enabled = null;

            if( $tax_enabled === null ) {
                $tax_enabled = wc_tax_enabled();
            }

                        if( ! $tax_enabled ) {
                return $price;
            }


            $args = [ 'qty' => 1, 'price' => $price ];

            return wc_get_price_excluding_tax( $product, $args );

                    }

		public static function maybe_add_tax($product, $price, $for_page = 'shop'){

            static $tax_enabled = null;

            if( $tax_enabled === null ) {
                $tax_enabled = wc_tax_enabled();
            }

            if( empty( $price ) || $price < 0 || ! $tax_enabled ) {
                return apply_filters( 'wapf/pricing/price_with_tax', $price, $price, $product, $for_page );
            }

            if( is_int( $product ) ) {
                $product = wc_get_product($product);
            }

            $args = [ 'qty' => 1, 'price' => $price ];

            if( $for_page === 'cart' ) {
                if( get_option('woocommerce_tax_display_cart') === 'incl' )
                    $price_with_tax = wc_get_price_including_tax($product, $args);
                else
                    $price_with_tax = wc_get_price_excluding_tax($product, $args);
            }
            else
                $price_with_tax = wc_get_price_to_display( $product, $args );

            return apply_filters( 'wapf/pricing/price_with_tax', $price_with_tax, $price, $product, $for_page );

		}

                public static function addon_value_for_calculations( $product, $amount, $type ) {

            if( $amount === 0 ) {
                return 0;
            }

            return $amount;

        }

	    public static function adjust_addon_price( $product, $amount, $type, string $for = 'shop', bool $maybe_add_tax = true ) {

		    if( $amount === 0 ) {
                return 0;
            }

            		    if( $type === 'p' || $type === 'percent' ) {
                return $amount;
            }

            if( $maybe_add_tax ) {
                $amount = self::maybe_add_tax( $product, $amount, $for );
            }

		    return apply_filters( 'wapf/pricing/addon', $amount, $product, $type, $for );

	    }

        public static function format_product_price( \WC_Product $product ): string {

                        if( $product->get_price() === '' ) {
                return apply_filters( 'woocommerce_empty_price_html', '', $product );
            } 

                        $current_price = wc_get_price_to_display( $product );

                        if( $product->is_on_sale() ) {
                $reg_price = wc_get_price_to_display( $product, [ 'price' => $product->get_regular_price() ] );
                $label = wc_format_sale_price( self::format_price( $reg_price ), self::format_price( $current_price ) );
            } else {
                $label = self::format_price( $current_price );
            }

                        return apply_filters( 'woocommerce_get_price_html', $label, $product );

                    }

        	    public static function format_price( $amount ): string {

            $args = WooCommerce_Service::get_price_display_options();

            $price = (float) $amount;
            $negative = $price < 0;
            $price = $negative ? $price * -1 : $price;
            $price = number_format( $price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'] );

            if ( $args['trim_zeroes'] && $args['decimals'] > 0 ) {
                $price = wc_trim_zeros( $price );
            }

            return ( $negative ? '-' : '' ) . sprintf( $args['price_format'], $args['symbol'], $price );

	    }

        public static function format_pricing_hint( $type, $amount, $product, $for_page = 'shop', $field = null, $option = null) {

            $format = apply_filters( 'wapf/html/pricing_hint/format', Util::pricing_hint_format(), $product, $amount, $type );
            $amount = apply_filters( 'wapf/html/pricing_hint/amount', $amount, $product, $type, $for_page );
			$ar_sign = empty( $amount ) ? '+' : ( $amount < 0 ? '' : '+' ); 

	        if( $for_page === 'shop' && ( $type === 'percent' || $type === 'p' ) )
                return apply_filters( 'wapf/html/pricing_hint', str_replace( ['{x}', '+'], [ ( empty( $amount ) ? 0 : $amount ) . '%', $ar_sign ], $format), $product, $amount, $type, $field, $option );

	        $price_output = self::format_price( self::adjust_addon_price($product, empty($amount) ? 0 : $amount, $type, $for_page ) );

	        if( $type === 'fx' ) {
                return apply_filters('wapf/html/pricing_hint', str_replace( ['{x}', '+'], [ $amount === '' ? '...' : $price_output, $ar_sign ], $format), $product, $amount, $type, $field, $option );
            }

            if( $for_page === 'shop' ) {

                if( $type === 'charq' || $type == 'char' ) {
                    return apply_filters('wapf/html/pricing_hint', str_replace( ['{x}', '+'], [ sprintf('%s %s', $price_output, __( 'per character', 'sw-wapf' ) ) , $ar_sign ], $format), $product, $amount, $type, $field, $option );
                }

                if( $type === 'nrq' || $type === 'nr' ) {
                    return apply_filters('wapf/html/pricing_hint', str_replace( ['{x}', '+' ], [ '&times;' . $price_output, '' ], $format), $product, $amount, $type, $field, $option );
                }

            }

            return apply_filters('wapf/html/pricing_hint', str_replace( ['{x}', '+'], [ $price_output , $ar_sign ], $format), $product, $amount, $type, $field, $option );

        }

        #endregion

        #region language functions

        public static function get_available_languages() {

            if( function_exists( 'pll_languages_list' ) ) {
                $languages = pll_languages_list(['fields' => null]);

                if( is_array( $languages ) ) {
                    $result = [];
                    foreach ( $languages as $x ) {
                        $result[] = [ 'id' => $x->locale, 'text' => $x->name ];
                    }
                    return $result;
                }
            }

            if( function_exists( 'icl_get_languages' ) ) {
                $languages = icl_get_languages('skip_missing=0&orderby=code');

                if ( is_array( $languages ) ) {
                    $result = [];
                    foreach ($languages as $x) {
                        $result[] = [ 'id' => $x['code'], 'text' => $x['native_name'] ];
                    }
                    return $result;
                }
            }

            return [];
        }

        	    public static function get_current_language() {

		    if(function_exists('pll_current_language')) {
		    	return pll_current_language('locale');
		    }

			if(defined('ICL_LANGUAGE_CODE'))
				return ICL_LANGUAGE_CODE;

		    return 'default';
	    }

		#endregion

		#region Formula functions

		private static $formula_functions = [];

	    public static function add_formula_function( $func, $callback ) {
	    	self::$formula_functions[ $func ] = $callback;
	    }

	    public static function get_all_formula_functions() {
	    	return apply_filters( 'wapf/fx/functions', array_keys( self::$formula_functions ) );
	    }

	    public static function split_formula_variables( $str ): array {

            	    	$open = 0;
	    	$paramStr = '';
	    	$params = [];
	    	$chars = self::split_multibyte_string( $str );
			$len = count( $chars );

	    	for( $i=0; $i < $len; $i++ ) {
			    if ($chars[$i] === ';' && $open === 0) {
				    $params[] = $paramStr;
				    $paramStr = '';
				    continue;
			    }
			    if ($chars[$i] === '(')
				    $open++;
			    if ($chars[$i] === ')')
				    $open--;
			    $paramStr .= $chars[$i];
		    }

		    if (strlen($paramStr) > 0 || count($params) === 0) {
			    $params[] = $paramStr;
		    }

            		    return array_map( 'trim', $params );

            	    }

		public static function closing_bracket_index($str,$from_pos) {
			$arr = str_split($str);
			$openBrackets = 1;

			for($i = $from_pos;$i<strlen($str);$i++) {

				if($arr[$i] === '(')
					$openBrackets++;
				if($arr[$i] === ')') {
					$openBrackets--;
					if($openBrackets === 0)
						return $i;
				}
			}
			return strlen($str)-1;
		}

		public static function replace_in_formula( $str, $qty, $base_price, $val, $options_total = 0, $cart_fields = [], $product_id = null, $clone_idx = 0 ) {


	    				$str = str_replace( ['[qty]','[price]','[x]'], [$qty,$base_price,$val], $str );


						$str = preg_replace_callback( '/\[field\..+?]/', function( $matches ) use ( $cart_fields, $clone_idx ) {

                $field_id_parts = explode('_', str_replace( ['[field.',']'],'', $matches[0] ) );
				$field_id = $field_id_parts[0];

				$field = Enumerable::from($cart_fields)->firstOrDefault( function( $f ) use ( $field_id, $clone_idx ) { return $f['id'] === $field_id && $f['clone_idx'] == $clone_idx; } );
                if( $clone_idx > 0 && !$field)
                    $field = Enumerable::from($cart_fields)->firstOrDefault( function( $f ) use ( $field_id, $clone_idx ) { return $f['id'] === $field_id; } );

                if( ! $field ) return '';

                if( count( $field_id_parts ) > 1 && count( $field['values'] ) > 1 ) { 
                    $option_slug = $field_id_parts[1];
                    $field_value = Enumerable::from( $field['values'] )->firstOrDefault( function($x) use($option_slug){ return $x['slug'] && $x['slug'] === $option_slug; } );
                    return  $field_value && isset( $field_value['label']) ?  $field_value['label'] : '0'; 
                }

                if( $field['type'] === 'number' ) {
                    return empty( $field['values'][0]['label'] ) ? '0' : $field['values'][0]['label'];
                }

				return $field[ 'values' ][ 0 ][ 'label' ] ?? '';
			}, $str );

            $str = preg_replace_callback( '/\[price\..+?]/', function( $matches ) use ( $cart_fields, $clone_idx ) {

                                $field_id_parts = explode( '_', str_replace( [ '[price.', ']' ],'', $matches[0] ) );
                $field_id = $field_id_parts[0];

                $field = Enumerable::from($cart_fields)->firstOrDefault( function( $f ) use ( $field_id, $clone_idx ) { return $f['id'] === $field_id && $f['clone_idx'] == $clone_idx; } );
                if( $clone_idx > 0 && ! $field )
                    $field = Enumerable::from( $cart_fields )->firstOrDefault( function( $f ) use ( $field_id, $clone_idx ) { return $f['id'] === $field_id; } );

                if( ! $field ) return '0';

                $price = 0;

                                foreach ( $field['values'] as $idx => $value ) {
                    $price = $price + ( $value['calc_price'] ?? 0 );
                }

                return '' . $price;

                            }, $str );


						return $str;

					}

		public static function find_nearest($value, $axis) {

			if( isset( $axis[$value] ) )
				return $value;

			$keys = array_keys( $axis );
			$value = floatval( $value );

			if( $value <= floatval($keys[0] ) )
				return $keys[0];

			for( $i=0; $i < count( $keys ); $i++ ) {
				if( $value > floatval( $keys[$i] ) && $value <= floatval( $keys[$i+1] ) )
					return $keys[$i+1];
			}

            return $keys[$i];

        }

        public static function parse_math_string( $str, $cart_fields = [], $evaluate = true, $additional_info = [] ) {

            	        $str = htmlspecialchars_decode( $str ); 

	    	$functions = self::get_all_formula_functions();

	        for($i=0;$i<sizeof($functions);$i++) {
		        $test = $functions[$i] . '(';

		        while (($idx = strpos($str, $test)) !== false) {

			        $l = $idx + strlen($test);
			        $b = self::closing_bracket_index($str,$l);
			        $args = self::split_formula_variables(substr($str,$l,$b-$l));

			        $solution = '';

			        if( isset( self::$formula_functions[ $functions[$i] ] ) ) {
			        	$callable = self::$formula_functions[$functions[$i]];
				        $solution = $callable( $args, [
				        	'fields'        => $cart_fields,
					        'product_id'    => $additional_info[ 'product_id' ] ?? null,
                            'clone_index'   => $additional_info[ 'clone_index' ] ?? 0,
				        ] );
			        } else {
			        	$solution = apply_filters( 'wapf/fx/solve', $solution, $functions[$i], $args );
			        }

                    			        $str = substr( $str, 0, $idx ) . $solution . substr( $str,$b + 1 );

		        }

	        }

	        return $evaluate ? self::evaluate_math_string($str) : $str;

        }

		public static function evaluate_math_string($str, $clean = true, $false_on_error = false) {

			$__eval = function ($str) use(&$__eval,$clean,$false_on_error) {
				$error = false;
				$div_mul = false;
				$add_sub = false;
				$result = 0;
				if($clean)
					$str = preg_replace('/[^\d.+\-*\/()E]/i','',$str);
				$str = rtrim(trim($str, '/*+'),'-');
				if ((strpos($str, '(') !== false &&  strpos($str, ')') !== false)) {
					$regex = '/\(([\d.+\-*\/]+)\)/';
					preg_match($regex, $str, $matches);
					if (isset($matches[1])) {
						return $__eval(preg_replace($regex, $__eval($matches[1]), $str, 1));
					}
				}
				$str = str_replace( [ '(', ')' ], '', $str);
				if ((strpos($str, '/') !== false ||  strpos($str, '*') !== false)) {
					$div_mul = true;
					$operators = [ '/','*' ];
					while(!$error && $operators) {
						$operator = array_pop($operators);
						while($operator && strpos($str, $operator) !== false) {
							if ($error) {
								break;
							}
							$regex = '/([\d.]+(?:E[+\-]?\d+)?)\\'.$operator.'(\-?[\d.]+(?:E[+\-]?\d+)?)/';
							preg_match($regex, $str, $matches);
							if (isset($matches[1]) && isset($matches[2])) {
								if ($operator=='+') $result = (float)$matches[1] + (float)$matches[2];
								if ($operator=='-') $result = (float)$matches[1] - (float)$matches[2];
								if ($operator=='*') $result = (float)$matches[1] * (float)$matches[2];
								if ($operator=='/') {
									if ((float)$matches[2]) {
										$result = (float)$matches[1] / (float)$matches[2];
									} else {
										$error = true;
									}
								}
								$str = preg_replace($regex, $result, $str, 1);
								$str = str_replace( [ '++', '--', '-+', '+-' ], [ '+', '+', '-', '-' ], $str);
							} else {
								$error = true;
							}
						}
					}
				}

				if (!$error && (strpos($str, '+') !== false ||  strpos($str, '-') !== false)) {
					$str = str_replace('--', '+', $str);
					$add_sub = true;
					preg_match_all('/([\d\.]+(?:E[+\-]?\d+)?|[\+\-])/', $str, $matches);
					if (isset($matches[0])) {
						$result = 0;
						$operator = '+';
						$tokens = $matches[0];
						$count = count($tokens);
						for ($i=0; $i < $count; $i++) {
							if ($tokens[$i] == '+' || $tokens[$i] == '-') {
								$operator = $tokens[$i];
							} else {
								$result = ($operator == '+') ? ($result + (float)$tokens[$i]) : ($result - (float)$tokens[$i]);
							}
						}
					}
				}

				if (!$error && !$div_mul && !$add_sub) {
					if($false_on_error && !is_numeric($str)) return false; 
					$result = (float)$str;
				}

				if($error && $false_on_error)
					return false;

				return $error ? 0 : $result;
			};

			return $__eval($str);

		}

		public static function evaluate_variables( $str, $fields, $variables, $product_id, $clone_idx, $base_price, $val, $qty, $options_total, $cart_item_fields ) {
			return preg_replace_callback( '/\[var_.+?]/', function ( $matches ) use ( $variables,$fields,$product_id, $clone_idx, $base_price, $cart_item_fields, $options_total, $val, $qty) {
				$var_name = str_replace( [ '[var_', ']' ], '', $matches[0] );

				$var = Enumerable::from( $variables )->firstOrDefault( function ( $x ) use ( $var_name ) {
					return $x['name'] === $var_name;
				});

				if( $var ) {

                    					$value = $var['default'];

					foreach ( $var['rules'] as $rule ) {
						if( Fields::is_valid_rule( $fields, $rule['field'], $rule['condition'], $rule['value'], $product_id, $cart_item_fields, $clone_idx, $qty ) ) {
							$value = $rule['variable'];
							break;
						}
					}

                    $evaluated_vars = Helper::evaluate_variables( $value, $fields, $variables, $product_id, $clone_idx, $base_price, $val, $qty, $options_total, $cart_item_fields );
                    $replaced       = Helper::replace_in_formula( $evaluated_vars, $qty, $base_price, $val, $options_total, $cart_item_fields, $product_id, $clone_idx );
                    $evaluated      = Helper::parse_math_string( $replaced, $cart_item_fields, true, ['product_id' => $product_id ] );

                                        return $evaluated;

                    				}

				return '0';
			}, $str );
		}

        		#endregion

                public static function values_to_simple_string(  $cartitem_field, $cart_item, $context = 'cart' ) {

            if( $context === 'cart' ) {
                $str = Enumerable::from( $cartitem_field[ 'values' ] )->join( function( $x ) {
                    return $x[ 'label' ];
                }, ', ' );

                return apply_filters( 'wapf/cart/item_values_label', $str, $cartitem_field, $cart_item, true );
            }

            return Enumerable::from( $cartitem_field['values'] )->join( function ( $x ) use( $cartitem_field ) {

                $label = $x[ 'formatted_label' ] ?? $x[ 'label' ]; 

                if( ! empty( $x['pricing_hint'] ) ) {
                    $label = sprintf( '%s %s', $label, $x['pricing_hint'] );
                }

                return $label;

            }, ', ' );

                    }

		public static function values_to_display_string( $cartitem_field, $cart_item ) {

	    	            $str = Enumerable::from( $cartitem_field['values'] )->join( function ( $x ) use( $cartitem_field ) {

                $label = $x[ 'formatted_label' ] ?? $x[ 'label' ];

                if( ! empty( $x['pricing_hint'] ) ) {
                    $label = sprintf( '%s <span class="wapf-pricing-hint">%s</span>', $label, $x['pricing_hint'] );
                }

                return $label;

            }, ', ' );

                        return apply_filters( 'wapf/cart/item_values_label', $str, $cartitem_field, $cart_item, false ); 

            		}

		public static function edit_cart_clones( $edit_cart_clones = [], $type = 1): array {

	    	if( empty( $edit_cart_clones ) ) return [];

	    	$edit_cart = [];

			if( $type === 1) { 

				foreach ( $edit_cart_clones as $clone ) {
					$arr = [];
					foreach ( $clone['values'] as $v ) {
						$arr[] = isset( $v['slug'] ) && empty( $v['use_label'] ) ? $v['slug'] : $v['label'];
					}

					$edit_cart[] = $arr;
				}

			} else { 

				foreach ($edit_cart_clones as $clone_section) {
					$section = [];
					foreach($clone_section as $clone) {
						$arr = [];
						foreach ( $clone['values'] as $v ) {
							$arr[] = isset( $v['slug'] ) && empty( $v['use_label'] ) ? $v['slug'] : $v['label'];
						}
						$section[] = $arr;
					}
					$edit_cart[] = $section;
				}

			}

            			return $edit_cart;

		}

        	}
}