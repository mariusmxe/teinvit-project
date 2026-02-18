<?php

namespace SW_WAPF_PRO\Includes\Classes {

    class Woocommerce_Service {

        public static function find_tags_by_name( $term ): array {

            if( empty( $term ) ) {
                return [];
            }

            $tag_args = [
                'taxonomy'   => 'product_tag',
                'orderby'    => 'name',
                'order'      => 'ASC',
                'hide_empty' => false,
                'name__like' => $term
            ];

            $product_tags = get_terms( $tag_args );

            if( !is_array( $product_tags ) ) {
                return [];
            }

                        return array_map( function( $term ) {
                return [ 
                    'id' => $term->term_id, 
                    'name' => $term->name
                ];
            }, $product_tags );

                    }

        public static function find_category_by_name( $term ): array {

                        if( empty( $term ) ) {
                return [];
            }

            $tag_args = [
                'taxonomy'   => 'product_cat',
                'orderby'    => 'name',
                'order'      => 'ASC',
                'hide_empty' => false,
                'name__like' => $term
            ];

            $product_tags = get_terms( $tag_args );

            if( ! is_array( $product_tags ) ) {
                return [];
            }

            return array_map( function( $term ) {
                return [
                    'id' => $term->term_id, 
                    'name' => $term->name
                ];
            }, $product_tags );

                    }

        public static function find_coupons_by_name( $term ): array {

            if( empty( $term ) ) {
                return [];
            }

            $args = [
                'posts_per_page'   => 10,
                'orderby'          => 'title',
                'order'            => 'asc',
                'post_type'        => 'shop_coupon',
                'post_status'      => 'publish',
            ];

            $coupons = get_posts( $args );

            return array_map( function( $coupon ) {
                return [
                    'name' => $coupon->post_title,
                    'id' => $coupon->ID
                ];
            }, $coupons );

                    }

        public static function find_products_by_name( $term = '', $include_variations = false, $include_variable_products = false, $exclude = [] ): array {

            if( empty( $term ) ) {
                return [];
            }

            $limit      = absint( apply_filters( 'woocommerce_json_search_limit', 10 ) );
            $data_store = \WC_Data_Store::load( 'product' );
            $ids        = $data_store->search_products( $term, '', (bool) $include_variations, true, $limit );
            $products   = [];

            foreach ( $ids as $id ) {

                if( empty( $id ) ) continue; 

                                if( in_array( $id, $exclude ) ) continue;

                $product_object = wc_get_product( $id );

                if ( ! wc_products_array_filter_readable( $product_object ) ) continue;

                                $formatted_name = self::get_formatted_product_name( $product_object );

                if( strpos( $product_object->get_type(), 'variable' ) !== false && ! $include_variable_products ) {
                    continue; 
                }

                               $products[] = [
                    'name' => rawurldecode( $formatted_name ),
                    'id' => $product_object->get_id()
                ];

            }

            return $products;

        }

        public static function find_attributes_by_name( $term ): array {

	       	        if( empty( $term ) ) {
                return [];
            }

	        $searchterms = explode( '-', $term );

			$attrs = wc_get_attribute_taxonomies();
			if( empty( $attrs ) ) {
                return [];
            }

			$searchterm = strtolower( trim( $searchterms[0] ) );

            $filtered = [];

            foreach ( $attrs as $a ) {
                if ( preg_match( '/' . $searchterm . '/', strtolower( $a->attribute_label ) ) === 1 ) {
                    $filtered[] = [
                        'name' => $a->attribute_label,
                        'id' => $a->attribute_name
                    ];
                }
            }

			$filtered_with_terms = [];

			foreach( $filtered as $f ) {

                				$terms = get_terms( [
					'taxonomy' => wc_attribute_taxonomy_name($f['id']),
					'hide_empty' => false
				] );

                				if( ! empty( $terms ) ) {
					$filtered_with_terms[] = [
						'name' => $f['name'] . ' - ' .  __('Any value', 'sw-wapf'),
						'id'   => $f['id'] . '|*'
					];
					foreach ( $terms as $t ) {
						$filtered_with_terms[] = [
							'name' => $f['name'] . ' - ' . $t->name,
							'id'   => $f['id'] . '|' . $t->slug
						];
					}
				}

                			}

			return $filtered_with_terms;
        }

        public static function find_variations_by_name($term): array {

            if( empty( $term) ) {
                return [];
            }

            $args = [
                'posts_per_page'    => -1,
                'post_type'         => 'product_variation',
                'post_status'       => [ 'publish', 'pending', 'draft', 'future', 'private', 'inherit' ],
                'fields'            => 'ids',
                's'                 => $term
            ];

            $variable_product_ids = get_posts($args);

            $products = [];

            foreach( $variable_product_ids as $id ) {

                $product = wc_get_product( $id );
                if($product === null) continue;

                $attributes = $product->get_variation_attributes();

                foreach ( $attributes as $key => $attribute ) {
                    if ($attribute === '')
                        $attributes[$key] = __('any', 'sw-wapf') . ' ' .  strtolower(wc_attribute_label(str_replace('attribute_', '', $key)));
                }

                $products[] = [
                    'name'  => sprintf('%s (%s)', $product->get_title(), join(', ',$attributes)),
                    'id'    => $id
                ];

            }

            return $products;

        }

                public static function get_current_page_type(): string {

                        if( is_product() )
                return 'product';
            if( is_shop() )
                return 'shop';
            if( is_cart() )
                return 'cart';
            if( is_checkout() )
                return 'checkout';

            return 'other';

                    }

        public static function get_price_display_options( $for_frontend = false ) {

            static $display_options = null;

            if( $display_options === null ) {

                $display_options = [
                    'ex_tax_label'      => false,
                    'currency'          => '',
                    'decimal_separator' => wc_get_price_decimal_separator(),
                    'thousand_separator'=> wc_get_price_thousand_separator(),
                    'decimals'          => wc_get_price_decimals(),
                    'price_format'      => get_woocommerce_price_format(),
                    'trim_zeroes'       => apply_filters( 'woocommerce_price_trim_zeros', false ),
                    'symbol'            => get_woocommerce_currency_symbol(),
                    'tax_enabled'       => wc_tax_enabled(),
                    'price_incl_tax'    => wc_prices_include_tax(),
                    'tax_display'       => get_option( 'woocommerce_tax_display_shop'),
                    'tax_suffix'        => get_option( 'woocommerce_price_display_suffix' ),
                ];

            }

            if( $for_frontend ) {
                $to_return = [
                    'format'        => $display_options['price_format'],
                    'symbol'        => $display_options['symbol'],
                    'decimals'      => $display_options['decimals'],
                    'decimal'       => $display_options['decimal_separator'],
                    'thousand'      => $display_options['thousand_separator'],
                    'trim_zeroes'   => $display_options['trim_zeroes'],
                    'tax_suffix'    => $display_options['tax_suffix'],
                    'tax_enabled'   => $display_options['tax_enabled'],
                    'price_incl_tax'=> $display_options['price_incl_tax'],
                    'tax_display'   => $display_options['tax_display'],
                ];
            } else {
                $to_return = $display_options;
            }

            return apply_filters( 'wapf/pricing/display_options', $to_return, $for_frontend );

        }

	    public static function get_product_attributes( $product, bool $strict = false ): array
        {
		    $attributes = [];

		    if( is_int( $product ) ) {
                $product = wc_get_product( $product );
            }

		    if ( ! $strict && $product->is_type( 'variation' ) ) {
			    $product_id = $product->get_parent_id();
			    $product = wc_get_product( $product_id );
		    }

		    if ( ! is_object( $product ) ) {
                return $attributes;
            }

		    $product_attributes = $product->get_attributes();

		    foreach ( $product_attributes as $key => $attribute ) {
				if( is_string( $attribute ) ) {
					$attributes[ $key ] = [$attribute];
				} else {
					if ( ! $attribute->is_taxonomy() ) {
						continue;
					}

					$terms = [];

					foreach ( $attribute->get_terms() as $term ) {
						$terms[] = $term->slug;
					}

					if ( ! empty( $terms ) ) {
						$attributes[ $attribute->get_name() ] = $terms;
					}
				}
            }

		    return $attributes;
	    }

        public static function get_products_by_query( $product_query, $main_product, $ids_only = false ): array {

                        if( empty( $product_query['query_id'] ) || ! is_int( $product_query['query_id'] ) ) {
                return [];
            }

            $limit      = isset( $product_query['limit'] ) ? min( 50, max( intval( $product_query['limit'] ), 1 ) ) : 10;
            $order_by   = isset( $product_query['sort'] ) ? str_replace( [ '_asc', '_desc' ], '', $product_query['sort'] ) : 'date';
            $sort       = isset( $product_query['sort'] ) ? ( str_contains( $product_query['sort'], 'desc' ) ? 'DESC' : 'ASC' ) : 'DESC';

                        $args = [
                'status'                => [ 'publish' ],
                'limit'                 => $limit,
                'orderby'               => $order_by,
                'order'                 => $sort,
                'type'                  => 'simple',
                'product_category_id'   => $product_query['query_id'],
                'exclude'               => [ is_object( $main_product ) ? $main_product->get_id() : $main_product ]
            ];

            if( get_option( 'woocommerce_hide_out_of_stock_items' ) === 'yes' ) {
                $args['stock_status'] = 'instock';
            }

                        if( $ids_only ) {
                $args['return'] = 'ids';
            }

            $args = apply_filters( 'wapf/products/query', $args, $product_query, $main_product );

                        return wc_get_products( $args );

                    }

                public static function get_product_choices( $product_ids, $product ): array {

                        if( empty( $product_ids ) ) return [];

                        $product_ids = array_filter( $product_ids, function($x) use ( $product ) { return $x !== $product->get_id(); } );

            $args = [
                'status'    => [ 'publish' ],
                'include'   => $product_ids,
                'orderby'   => 'include',
                'type'      => [ 'simple', 'variable', 'variation' ],
                'limit'     => 50
            ];

            if( get_option( 'woocommerce_hide_out_of_stock_items' ) === 'yes' ) {
                $args['stock_status'] = 'instock';
            }

            $products = wc_get_products( $args );

            $res = [];

                        foreach ( $products as $product ) {
                $res[ $product->get_id() ] = $product;
            }

                        return $res;

        }

        public static function get_products_by_id( $ids = [] ) {

                        if( empty( $ids ) ) {
                return [];
            }

            $args = [
                'status'    => [ 'publish' ],
                'include'   => $ids,
                'type'      => [ 'simple', 'variable', 'variation', 'subscription', 'variable-subscription', 'subscription-variation' ]
            ];

                        return wc_get_products( $args );

                    }

        private static function get_formatted_product_name(  $product ): string {

            if ( $product->get_sku() ) {
                $identifier = $product->get_sku();
            } else {
                $identifier = $product->get_id();
            }

            $formatted_variation_list = '';
            if( strpos( $product->get_type(), 'variable') !== false)
                $formatted_variation_list = wc_get_formatted_variation( $product, true, true, true );

            return sprintf( '%s%s (%s)', $product->get_name(), empty( $formatted_variation_list ) ? '' : ' - ' . $formatted_variation_list, $identifier );
        }

            }
}
