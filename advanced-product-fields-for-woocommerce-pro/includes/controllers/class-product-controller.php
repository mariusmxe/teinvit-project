<?php

namespace SW_WAPF_PRO\Includes\Controllers {

    use SW_WAPF_PRO\Includes\Classes\Cache;
	use SW_WAPF_PRO\Includes\Classes\Cart;
	use SW_WAPF_PRO\Includes\Classes\Enumerable;
    use SW_WAPF_PRO\Includes\Classes\Field_Groups;
    use SW_WAPF_PRO\Includes\Classes\Fields;
    use SW_WAPF_PRO\Includes\Classes\File_Upload;
	use SW_WAPF_PRO\Includes\Classes\Helper;
	use SW_WAPF_PRO\Includes\Classes\Html;
    use SW_WAPF_PRO\Includes\Classes\Util;
    use SW_WAPF_PRO\Includes\Models\Field;
    use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;

    class Product_Controller
    {

    	public $show_fieldgroup = false;
        public $is_admin_order = false;

                private $in_mini_cart = false;

        public function __construct()
        {
            add_action( 'woocommerce_before_add_to_cart_button',            [ $this, 'display_field_groups' ] );

            add_action( 'wp_footer',                                        [ $this, 'add_custom_js_and_html' ], 999999 );

            add_filter('woocommerce_add_to_cart_validation',                [ $this, 'validate_cart_data' ], 10, 6 );

            add_action('woocommerce_after_checkout_validation',             [ $this, 'validate_at_checkout' ], 10, 2 );

	        add_filter('woocommerce_add_cart_item_data',                    [ $this, 'add_fields_to_cart_item' ], 10, 4 );

            add_action('woocommerce_add_to_cart',                           [ $this, 'split_cart_items_by_quantity' ], 10, 6 );

            add_action( 'woocommerce_before_calculate_totals',               [ $this, 'add_prices_to_cart_item' ], 2000 );

	        add_action( 'woocommerce_before_mini_cart',                      [ $this, 'mini_cart_subtotal' ] );

            add_action( 'mark_not_in_mini_cart',                            [ $this, 'mark_not_in_mini_cart' ] );

            add_filter('woocommerce_get_item_data',                         [ $this, 'display_fields_on_cart_and_checkout' ], 10, 2 );

            add_action('woocommerce_checkout_create_order_line_item',       [ $this, 'add_meta_to_order_item' ], 20, 4 );

	        add_filter('woocommerce_order_item_get_formatted_meta_data',    [ $this, 'format_fields_in_order' ], 10, 2);

            add_action('woocommerce_before_order_itemmeta',                 [ $this, 'set_admin_screen_flag' ] );
            add_action('woocommerce_after_order_itemmeta',                  [ $this, 'unset_admin_screen_flag' ] );

            add_filter('woocommerce_product_add_to_cart_text',              [ $this, 'change_add_to_cart_text' ], 10, 2 );

            add_filter('woocommerce_product_supports',                      [ $this, 'check_product_support'], 10, 3 );

            add_filter('woocommerce_product_add_to_cart_url',               [ $this, 'set_add_to_cart_url'], 10, 2 );

            add_filter('woocommerce_single_product_image_thumbnail_html',   [ $this, 'add_attachment_id_to_html'], 10, 2 );

	        add_filter('woocommerce_order_again_cart_item_data',            [ $this, 'order_again_cart_item_data'], 10, 3 );
	        add_filter('woocommerce_add_order_again_cart_item',             [ $this, 'calculate_prices_for_ordered_again_item' ], 11, 2 );

	        add_action('wp_head',                                           [ $this, 'add_lookup_tables' ] );
	        add_action('wp_footer',                                         [ $this, 'add_lookup_tables_code' ] );

	        add_action('woocommerce_after_cart_item_name',                  [ $this, 'add_edit_link' ], 10, 2 );
            add_action('woocommerce_blocks_loaded',                         [ $this, 'extend_store_api' ] ); 
            add_action('wp_footer',                                         [ $this, 'add_edit_link_for_cart_block' ] ); 
	        add_filter('woocommerce_product_single_add_to_cart_text',       [ $this, 'change_add_to_cart_button_text' ] );
	        add_filter('woocommerce_add_to_cart_redirect',                  [ $this, 'change_add_to_cart_redirect' ], 10, 2 );
	        add_filter('woocommerce_quantity_input_args',                   [ $this, 'change_add_to_cart_quantity' ], 20, 2 );

	        add_filter('woocommerce_get_price_html',                        [ $this, 'change_price_html' ], 100, 2 );

            add_filter('woocommerce_available_variation',                   [ $this, 'set_variant_data' ], 10, 3 );

        }

        public function set_variant_data( $variant_data, $product, $variation ) {
            $variant_data['apf_base']  = apply_filters( 'wapf/pricing/product', $variation->get_price(), $variation ); 
            $variant_data['tax']  = Helper::get_tax_multiplier( $variation );
            return $variant_data;
        }

                public function change_price_html( $price, $product ) {

            if( ! apply_filters( 'wapf/features/change_price_html', true ) ) {
                return $price;
            }

	        if( ! in_array( $product->get_type(), ['simple','subscription'] ) ) {
                return $price;
            }

	        $placement = $product->get_meta('_wapf_price_display');

	        if( empty( $placement ) ) return $price;

	        $price_label = $product->get_meta('_wapf_price_label');
			$price_label = empty( $price_label ) ? '' : $price_label;

	        switch ($placement) {
		        case 'hide':
		        	$price = '';
		        	break;
		        case 'before':
		        	$price = sprintf(
				        '<span class="wapf-price-before">%s</span> %s',
				        $price_label,
				        $price
		            );
		        	break;
		        case 'after':
			        $price = sprintf(
				        '%s <span class="wapf-price-after">%s</span>',
				        $price,
				        $price_label
			        );
			        break;
		        case 'replace':
			        $price = sprintf(
				        '<span class="wapf-price-replace">%s</span>',
				        $price_label
			        );
			        break;
	        }

	        return $price;

        }

        public function change_add_to_cart_quantity($args, $product) {

        	if( isset( $_GET['_edit'] ) && Util::can_edit_in_cart() ) {
		        $cart_item_key = sanitize_text_field($_GET['_edit']);
		        $cart = WC()->cart->cart_contents;
		        if(isset($cart[$cart_item_key]) && isset($cart[$cart_item_key]['wapf'])) {
			       $args['input_value'] = isset($cart[$cart_item_key]['quantity']) ? $cart[$cart_item_key]['quantity'] : 1;
		        }
	        }

	        return $args;
        }

        public function change_add_to_cart_redirect($url, $adding_to_cart) {

            $change_add_to_cart_redirect_on_edit = '';

	        if( $adding_to_cart && isset( $_POST['_wapf_edit'] ) && Util::can_edit_in_cart() ) {
                $redirect_on_edit = apply_filters('wapf/add_to_cart_redirect_when_editing', true);
                if( $redirect_on_edit)
	        	    return wc_get_cart_url();
	        }

	        return $url;
        }

        public function change_add_to_cart_message( $message, $products, $show_qty ) {
			if( isset( $_POST['_wapf_edit'] ) && Util::can_edit_in_cart() ) {
				return esc_html(__( 'Cart updated.', 'woocommerce' ) );
			}
			return $message;
        }

	    public function change_add_to_cart_button_text( $txt ) {

                    	if( isset( $_GET['_edit'] ) ) {
		        $cart_item_key = sanitize_text_field( trim( $_GET['_edit'] ) );
		        $cart = WC()->cart->cart_contents;
                if( Util::can_edit_cart_item( $cart[ $cart_item_key ] ) ) {
                    return __( 'Update cart', 'woocommerce' );
                }
	        }

        	return $txt;
	    }

                public function extend_store_api() {

            if( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {

                woocommerce_store_api_register_endpoint_data( 
                    [
                        'endpoint' => CartItemSchema::IDENTIFIER,
                        'namespace' => 'apf',
                        'data_callback' => function( $cart_item ) {

                                                     $data = [];

                                                        if( Util::can_edit_cart_item( $cart_item ) ) {
                                $link = $this->get_edit_link( $cart_item );
                                if ( ! empty( $link ) ) {
                                    $data[ 'editLink' ] = $link;
                                }
                            }

                                                        return apply_filters( 'wapf/store_api/cart/data_callback', $data, $cart_item );

                                                    },
                        'schema_callback' => function() {

                                                    $schema = [
                                'editLink' => [
                                    'description' => __( 'Link to edit the cart item.', 'sw-wapf' ),
                                    'type' => 'string',
                                    'context' => [ 'view', 'edit' ],
                                    'readonly' => true,
                                ]    
                            ];

                                                        return apply_filters( 'wapf/store_api/cart/schema_callback', $schema );

                                                    },
                        'schema_type' => ARRAY_A,
                    ]
                );
            }

                    }

                public function add_edit_link( $cart_item, $cart_item_key ) {

        	if( Util::can_edit_cart_item( $cart_item ) ) {

                                $link = $this->get_edit_link( $cart_item );

                                if( ! empty( $link ) ) {
                    echo '&nbsp;' . $link;
                }

                	        }
        }

                private function get_edit_link( $cart_item ) {

                        $product = $cart_item['data'];

                        if( apply_filters('wapf/disable_cart_edit_when_invisible', true ) && !$product->is_visible()) 
                return '';

            $permalink = add_query_arg(
                '_edit',
                $cart_item['key'],
                $product->get_permalink( $cart_item )
            );

                        $cart_text = apply_filters('wapf/cart_edit_text', '(' . __('edit','sw-wapf') . ')', $cart_item );

            return '<a class="wapf-edit-cartitem" href="'.esc_html($permalink).'">' . $cart_text . '</a>';

        }

                public function add_edit_link_for_cart_block() {
            if( ! Util::can_edit_in_cart() ) return;
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if( window.wc && window.wc.blocksCheckout ) {
                        window.wc.blocksCheckout.registerCheckoutFilters('apf-editlink', {
                            itemName: function( defaultVal, extensions, args ) {
                                if( args && args.context === 'cart' && args.cartItem.extensions?.apf.editLink ) {
                                    return defaultVal + ' ' + args.cartItem.extensions?.apf.editLink;
                                }
                                return defaultVal;
                            }
                        });
                    }
                });
            </script>
            <?php
        }

        public function check_product_support( $support, $feature, $product ) {

            if( $feature === 'ajax_add_to_cart' && Field_Groups::product_has_field_group( $product ) ) {
                $support = false;
            }

            return $support;
        }

        public function change_add_to_cart_text( $text, $product ) {

            if( ! $product->is_in_stock() ) {
                return $text;
            }

            if ( in_array( $product->get_type(), [ 'grouped', 'external' ] ) ) {
                return $text;
            }

            if( Field_Groups::product_has_field_group( $product ) ) {
                return esc_html( get_option( 'wapf_add_to_cart_text', __( 'Select options', 'sw-wapf' ) ) );
            }

                        return $text;

        }

        public function set_add_to_cart_url( $url, $product ) {

            if( $product->get_type() === 'external' ) {
                return $url;
            }

            if( Field_Groups::product_has_field_group( $product ) ) {
                return apply_filters( 'wapf/add_to_cart_url', $product->get_permalink(), $product, $url );
            }

            return $url;

        }

        public function validate_at_checkout( $data, $errors ) {

            if( apply_filters('wapf/skip_cart_validation', false ) ) return;

            if( empty( WC()->cart ) ) return;

            $cart_items = WC()->cart->get_cart();
            if( empty( $cart_items ) ) return;

            foreach ( $cart_items as $cart_item ) {

                if( empty( $cart_item['wapf'] ) ) continue;

                $product = $cart_item['data'];
                $field_groups = Field_Groups::get_field_groups_of_product( $product );

                $validation = Cart::validate_cart_data( $field_groups, true, $cart_item['product_id'], $cart_item['quantity'], $cart_item['variation_id'], true, $cart_item );

                if( is_string( $validation ) ) {
                    $err_message = __('Your cart contains incorrect or outdated information. ', 'sw-wapf');
                    $errors->add( 'cart', $err_message . $validation );
                    return;
                }

            }


        }

        public function validate_cart_data( $passed, $product_id, $qty, $variation_id = null, $variations = null, $cart_item_data = null ) {

	        $skip_validation = apply_filters( 'wapf/skip_cart_validation', false );
	        if( $skip_validation ) return $passed;

            $is_order_again = isset( $cart_item_data['wapf_order_again'] ) && $cart_item_data['wapf_order_again'];
	        $the_product_id = empty( $variation_id ) ? intval( $product_id ) : intval( $variation_id );

	                    if( empty( $the_product_id ) ) return $passed; 

                    	$field_groups = Field_Groups::get_field_groups_of_product( $the_product_id );

	        if( empty( $field_groups ) ) {
                return $passed;
            }

	        $skip_fieldgroup_validation = false;
	        $field_group_ids = isset( $_REQUEST['wapf_field_groups'] ) ? sanitize_text_field( $_REQUEST['wapf_field_groups'] ) : false;

	        if( ! empty( $cart_item_data ) && $is_order_again ) {
                $skip_fieldgroup_validation = true;
            }

	        if( isset( $_GET['add-to-cart'] ) && is_numeric( $_GET['add-to-cart'] ) && ! $field_group_ids ) {
                $skip_fieldgroup_validation = true;
            }

	        $skip_fieldgroup_validation = apply_filters( 'wapf/skip_fieldgroup_validation', $skip_fieldgroup_validation );

	        if( ! $skip_fieldgroup_validation ) {
		        if ( ! isset( $_REQUEST['wapf_field_groups'] ) ) {
			        wc_add_notice( esc_html( __( 'Error adding product to cart.', 'sw-wapf' ) ), 'error' );
			        return false;
		        }

		        $field_group_ids = explode( ',', sanitize_text_field( $_REQUEST['wapf_field_groups'] ) );
		        foreach ( $field_groups as $fg ) {
			        if ( ! in_array( $fg->id, $field_group_ids ) ) {
				        wc_add_notice( esc_html( __( 'Error adding product to cart.', 'sw-wapf' ) ), 'error' );
				        return false;
			        }
		        }
	        }

	        $files = File_Upload::create_uploaded_file_array();
	        Cache::set_files( $files ); 

            $validation = Cart::validate_cart_data( $field_groups, $passed, $product_id, $qty, $variation_id, $is_order_again, $cart_item_data );

            if( is_string( $validation ) ) {
	            wc_add_notice( esc_html( $validation ), 'error' );
	            return false;
            }

            if( $passed && ! empty( $files ) && ! File_Upload::is_ajax_upload() ) {
            	$files_upload_result = File_Upload::handle_files_array( $field_groups, $files );
				if( is_string( $files_upload_result ) ) {
					wc_add_notice( esc_html( $files_upload_result ), 'error' );
					return false;
				}
	            Cache::set_files( $files_upload_result );
            }

            return $passed;

        }

                public function add_fields_to_cart_item( $cart_item_data, $product_id, $variation_id, $quantity = 1 ) {

	        if( isset( $cart_item_data['wapf'] ) || ! isset( $_REQUEST['wapf_field_groups'] ) ) {
                return $cart_item_data;
            }

            if( empty( $product_id ) && empty( $variation_id ) ) {
                return $cart_item_data;
            }

            $skip = apply_filters('wapf/skip_add_to_cart', false );
            if( $skip ) {
                return $cart_item_data;
            }

	        $field_group_ids    = explode( ',', sanitize_text_field( $_REQUEST['wapf_field_groups'] ) );
	        $field_groups       = Field_Groups::get_by_ids($field_group_ids);
	        $fields             = Enumerable::from($field_groups)->merge( function($x){return $x->fields; })->toArray();
			$files              = Cache::get_files();
            $wapf_data          = [];
			$clones             = [];
			$parent_sections    = [];

	        foreach( $fields as $field ) {
            	if( $field->type === 'section' ) {
                    $parent_sections[] = $field;
                }

                            	if( $field->type === 'sectionend' ) {
                    array_pop( $parent_sections );
                }

            	if( ! $field->is_category( 'field' ) ) continue;

	            $key = 'field_' . $field->id;

                                if( ( $field->type === 'file' && isset( $files[$key] ) ) || ( isset( $_REQUEST['wapf'] ) && isset( $_REQUEST['wapf'][$key] ) ) ) {
                    $cart_item_field = Cart::to_cart_item_field( $field, 0 );
                    $wapf_data[] = $cart_item_field;
                }

            	$clone_type = $field->get_clone_type( true );

            	if( ! empty( $clone_type ) ) {
		            switch ( $clone_type ) {

                        			            case 'button':

                            				            $type_for_field_only  = $field->get_clone_type();
				            $the_clone_field_id = empty( $type_for_field_only ) ? $parent_sections[ count( $parent_sections ) - 1 ]->id : $field->id;
				            if ( isset( $_REQUEST['wapf'] ) && isset( $_REQUEST['wapf'][ 'field_' . $the_clone_field_id . '_qty' ] ) ) {

					            $qty = intval( $_REQUEST['wapf'][ 'field_' . $the_clone_field_id . '_qty' ] );

					            if ( $qty > 0 ) {
						            for ( $i = 1; $i <= $qty; $i ++ ) {

							            $key = 'field_' . $field->id . '_clone_' . ( $i + 1 );

                                        if ( ( $field->type === 'file' && isset( $files[ $key ] ) ) || ( isset( $_REQUEST['wapf'] ) && isset( $_REQUEST['wapf'][ $key ] ) ) ) {
                                            $cart_item_field = Cart::to_cart_item_field( $field, $i + 1 );
                                            $wapf_data[] = $cart_item_field;
							            }
						            }
					            }
				            }
				            break;

                        default : 
				            if ( $quantity > 1 ) {
					            for ( $i = 2; $i <= $quantity; $i ++ ) {
						            $key = 'field_' . $field->id . '_clone_' . $i;
                                    if ( ( $field->type === 'file' && isset( $files[ $key ] ) ) || ( isset( $_REQUEST['wapf'] ) && isset( $_REQUEST['wapf'][ $key ] ) ) ) {
						            	$cart_item_field = Cart::to_cart_item_field( $field, $i );
 $clones[  $i - 2 ][] = $cart_item_field;
						            }
					            }
				            }
		            }
	            }
            }

	        $wapf_unique_key = $this->generate_cart_item_id( $product_id, $variation_id, $wapf_data );

	        if( ! empty( $clones ) )
		        Cache::add_clone( $wapf_unique_key, $clones );

	        if( ! empty( $wapf_data ) ) {
		        $cart_item_data['wapf']                 = $wapf_data;
		        $cart_item_data['wapf_key']             = $wapf_unique_key;
		        $cart_item_data['wapf_field_groups']    = $field_group_ids;
	        }

            	        if( ! empty( $_POST['_wapf_edit'] ) && Util::can_edit_in_cart() ) {
		        WC()->cart->remove_cart_item( $_POST['_wapf_edit'] );
		        add_filter( 'wc_add_to_cart_message_html', [ $this, 'change_add_to_cart_message' ], 10, 3 );
	        }

	        return $cart_item_data;

        }

        	    public function split_cart_items_by_quantity( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {

		    if( $quantity === 1 ) return;

		    if( empty( $cart_item_data['wapf'] ) || ! is_array( $cart_item_data['wapf'] ) || empty( $cart_item_data['wapf_field_groups'] ) )
			    return;

            $fingerprint = $cart_item_data['wapf_key'] ?? false;

		    $cart_data_clones = empty( $fingerprint ) ? false : Cache::get_clone( $cart_item_data['wapf_key'] );

		    if( empty( $cart_data_clones ) ) return;

		    $main_item_qty = isset( WC()->cart->cart_contents[$cart_item_key]['quantity'] ) ? WC()->cart->cart_contents[$cart_item_key]['quantity'] : 1;

		    $field_groups = Field_Groups::get_by_ids( $cart_item_data['wapf_field_groups'] );
		    $fields = Enumerable::from( $field_groups )->merge( function( $x ){ return $x->fields; } )->toArray();
		    for( $i = 0; $i < count( $cart_data_clones ); $i++ ) {
			    $clone_group = $cart_data_clones[$i];
			    $complete_clone  = [];

			    foreach ( $fields as $field ) {

				    $field_in_clone = Enumerable::from( $clone_group )->firstOrDefault( function( $clone_field ) use ( $field ) {
					    return $field->id === $clone_field['id'];
				    });

                   				    if( $field_in_clone )
					    $complete_clone[] = $field_in_clone;
				    else {
                        if( empty( $field->get_clone_type( true ) ) ) {
                            $field_in_parent = Enumerable::from( $cart_item_data[ 'wapf' ] )->firstOrDefault( function( $parent_field ) use ( $field ) {
                                return $field->id === $parent_field[ 'id' ];
                            } );
                            if ( $field_in_parent )
                                $complete_clone[] = $field_in_parent;
                        }
				    }

			    }

			    $clone_fingerprint = $this->generate_cart_item_id( $product_id, $variation_id, $complete_clone );

			    if ( $clone_fingerprint !== $fingerprint ) {

				    $content = WC()->cart->cart_contents;
				    $is_in_cart = false;
				    foreach ( $content as $key => $item_in_cart ) {
					    if ( empty( $item_in_cart['wapf'] ) || $key === $cart_item_key )
						    continue;

					    $item_fingerprint = $item_in_cart['wapf_key'];

					    if ( $item_fingerprint === $clone_fingerprint ) {
						    $old_qty = empty( $item_in_cart['quantity'] ) ? 1 : $item_in_cart['quantity'];
						    WC()->cart->set_quantity( $key, $old_qty + 1 );
						    $is_in_cart = true;
						    break;
					    }
				    }

                    $main_item_qty = $main_item_qty - 1;
                    if($main_item_qty < 1)
                        $main_item_qty = 1;
                    WC()->cart->set_quantity( $cart_item_key, $main_item_qty );

				    if ( ! $is_in_cart ) {

					    $new_cart_item_data = [
                            '_wapf_clone'       => true,
						    'wapf'              => $complete_clone,
						    'wapf_key'          => $clone_fingerprint,
						    'wapf_field_groups' => $cart_item_data['wapf_field_groups'],
					    ];

					    remove_action( 'woocommerce_add_to_cart', [ $this, 'split_cart_items_by_quantity' ] );
					    remove_action( 'woocommerce_add_cart_item_data', [ $this, 'add_fields_to_cart_item' ] );
                        remove_action( 'woocommerce_before_calculate_totals', [ $this, 'add_prices_to_cart_item' ], 2000 );

                        					    WC()->cart->add_to_cart( $product_id, 1, $variation_id, $variation, $new_cart_item_data );

					    add_action( 'woocommerce_add_to_cart', [ $this, 'split_cart_items_by_quantity' ], 10, 6 );
					    add_action( 'woocommerce_add_cart_item_data', [ $this, 'add_fields_to_cart_item' ], 10, 4 );
                        add_action( 'woocommerce_before_calculate_totals', [ $this, 'add_prices_to_cart_item' ], 2000, 1 );
				    }

			    }
		    }

	    }

	    public function mini_cart_subtotal() {

            $this->in_mini_cart = true;

            		    if ( is_cart() || is_checkout() ) {
			    return;
		    }

            if ( ! is_null( WC()->cart ) ) {
                WC()->cart->calculate_totals();
            }

	    }

                public function mark_not_in_mini_cart() {
            $this->in_mini_cart = false;
        }

	    public function add_prices_to_cart_item( $cart_obj ) {

		    if ( is_admin() && ! wp_doing_ajax() ) return;

            if ( did_action( 'woocommerce_before_calculate_totals' ) > 1 ) {
                return;
            }

            		    foreach( $cart_obj->get_cart() as $key => $item ) {

                			    if( ! empty( $item['wapf'] ) ) {

				    $pricing = Cart::calculate_cart_item_prices( $item );

				    if( $pricing !== false ) {
					    WC()->cart->cart_contents[ $key ]['wapf_item_price'] = $pricing;

                        $item_price = floatval( $pricing['base'] ) + floatval( $pricing['options_total'] );
					    $item['data']->set_price( max( $item_price, 0 ) );
				    }

			    }

                		    }

                    }

        public function display_fields_on_cart_and_checkout( $item_data, $cart_item ) {

            if ( ! is_array( $item_data ) ) {
                $item_data = [];
            }

            if( empty( $cart_item['wapf'] ) || ! is_array( $cart_item['wapf'] ) ) {
                return $item_data;
            }

            $is_rest        = Helper::is_rest();

            if( ! $is_rest ) {
                $is_mini_cart   = $this->in_mini_cart;
                $is_cart        = ! $is_mini_cart && is_cart();
                $is_checkout    = ! $is_mini_cart && ! $is_cart && is_checkout();
            } else { 
                $is_checkout    = Helper::is_rest_for( 'checkout' );
                $is_cart        = ! $is_checkout && Helper::is_rest_for( 'cart' );
                $is_mini_cart   = ! $is_checkout && ! $is_cart;
            }

                        if(
	            ( $is_cart && get_option('wapf_settings_show_in_cart','yes') === 'yes' ) || 
	            ( $is_checkout && get_option('wapf_settings_show_in_checkout','yes') === 'yes') || 
	            ( $is_mini_cart && get_option('wapf_settings_show_in_mini_cart','no') === 'yes' ) 
            ) {

	            foreach( $cart_item['wapf'] as $field ) {

                	if( ! isset( $field['values'] ) )
                		continue;

                    if( ! Util::should_use_cart_field( $field ) ) {
                        continue;
                    }

		            if( $is_cart && isset( $field['hide_cart'] ) && $field['hide_cart'] )
			            continue;

		            if( $is_checkout && isset( $field['hide_checkout'] ) && $field['hide_checkout'] )
			            continue;

		            if( $is_mini_cart && isset( $field['hide_cart'] ) && $field['hide_cart'] )
		            	continue;

                    if (Enumerable::from($field['values'])->any( function($x) use( $cart_item ) {
                    	return isset( $x['label'] ) && strlen( $x['label'] ) > 0;
                    } ) ) {

                        $item_data[] = [
                    		'key'       => $field['label'],
		                    'value'     => Helper::values_to_simple_string( $field, $cart_item ),
		                    'display'   => Helper::values_to_display_string( $field, $cart_item )
	                    ];

                    }

                }
            }

            return apply_filters('wapf/cart/item_data', $item_data, $cart_item);

        }

	    #region Frontend
	    public function display_field_groups() {

		    global $product;

		    if( ! $product ) {
                return;
            }

		    if( in_array( $product->get_type(), [ 'grouped', 'external' ] ) ) return;

		    $field_groups = Field_Groups::get_field_groups_of_product( $product );

            		    if( empty( $field_groups ) ) return;

		    $this->show_fieldgroup = true;

		    $cart_item_fields = [];

		    if( ! empty( $_GET['_edit'] ) && Util::can_edit_in_cart() ) {

		    	$cart_item_key  = sanitize_text_field( $_GET['_edit'] );
			    $cart           = WC()->cart->cart_contents;
                $fields         = [];

                if( isset( $cart[ $cart_item_key ] ) ) {
                    $fields = array_merge( $cart[ $cart_item_key ][ 'wapf' ] ?? [], $cart[ $cart_item_key ][ '_wapf_children' ] ?? [] );
                }

                			    foreach( $fields as $f ) {
			    	if( ! isset( $cart_item_fields[ $f['id'] ] ) ) {
					    $cart_item_fields[ $f['id'] ] = [ 'value' => $f, 'clones' => [] ];
				    } else {
			    		$cart_item_fields[ $f['id'] ]['clones'][] = $f;
				    }
			    }

                		    }

		    echo Html::display_field_groups( $field_groups, $product, $cart_item_fields );

        }

	    public function add_custom_js_and_html() {

            echo '<div class="wttw" aria-hidden="true"><div class="wapf-ttp"></div></div>';

		    if( ! $this->show_fieldgroup )
			    return;

		    echo '<script>document.addEventListener("wapf/delete_var", function(){ if(jQuery.fn) Object.defineProperty(jQuery.fn, "wc_variations_image_update", { value: function() {}, writable: false}) } )</script>';

	    }

        public function add_attachment_id_to_html($html, $attachment_id) {

            return preg_replace('/<div /', '<div data-wapf-att-id="' . $attachment_id . '" ', $html, 1 );
        }
	    #endregion

	    #region Order
	    public function add_meta_to_order_item( $order_item, $cart_item_key, $cart_item, $order ) {

            $_wapf_meta = [];

            if( isset( $cart_item[ '_wapf_child' ] ) ) {
                $_wapf_meta['_wapf_child'] = $cart_item[ '_wapf_child' ];
            }

		    if ( ! empty( $cart_item['wapf'] ) ) {

                $fields_meta = [];
                $fields_meta_settings = [];

                for ( $i = 0 ; $i < count( $cart_item[ 'wapf' ] ) ; $i++ ) {

                    $field = $cart_item[ 'wapf' ][ $i ];

                    if ( Enumerable::from( $field[ 'values' ] )->any( function( $x ) {
                        return isset( $x[ 'label' ] ) && strlen( $x[ 'label' ] ) > 0;
                    } ) ) {

                        $value = Helper::values_to_simple_string( $field, $cart_item, 'order' );

                        $order_item->add_meta_data(
                            $field[ 'label' ],
                            $value
                        );

                        $meta_field = [
                            'id' => $field[ 'id' ],
                            'type' => $field[ 'type' ],
                            'label' => $field[ 'label' ],
                            'value' => $value,
                            'values' => $field[ 'values' ],
                        ];

                        if ( isset( $field[ 'clone_idx' ] ) && $field[ 'clone_idx' ] > 0 ) {
                            $meta_field[ 'clone_idx' ] = $field[ 'clone_idx' ];
                        }

                        $meta_key = $field[ 'id' ] . ( isset( $field[ 'clone_idx' ] ) && $field[ 'clone_idx' ] > 0 ? ( '_' . $field[ 'clone_idx' ] ) : '' );

                        $fields_meta[ $meta_key ] = apply_filters( 'wapf/order/order_item_field', $meta_field, $cart_item, $field );

                        $meta_settings = [
                            'field' => $meta_key,
                            'hide' => $field[ 'hide_order' ] || ( $field['type'] === 'products' && Util::should_add_linked_products_to_cart() )
                        ];

                        if ( isset( $fields_meta_settings[ $field[ 'label' ] ] ) ) {
                            $fields_meta_settings[ $field[ 'label' ] ][] = $meta_settings;
                        } else {
                            $fields_meta_settings[ $field[ 'label' ] ] = [ $meta_settings ];
                        }

                    }

                }

            }

            if( ! empty( $fields_meta ) ) {
                $_wapf_meta['version']          = wapf_get_setting( 'version' );
                $_wapf_meta['fields']           = $fields_meta;
                $_wapf_meta['settings']         = $fields_meta_settings;
                $_wapf_meta['cart_item_key']    = $cart_item_key;
            }

                        if( ! empty( $_wapf_meta ) ) {
                $order_item->add_meta_data( '_wapf_meta', $_wapf_meta );
            }

					    }

        public function set_admin_screen_flag() {
            $this->is_admin_order = true;
        }

        public function unset_admin_screen_flag() {
            $this->is_admin_order = false;
        }

	    public function format_fields_in_order($formatted_meta, $order_item) {

            		    $wapf_meta = $order_item->get_meta( '_wapf_meta', true );

		    if( empty( $wapf_meta ) || empty( $wapf_meta['settings'] ) ) {
                return $formatted_meta;
            }

		    $already_occured = [];

		    foreach( $formatted_meta as $meta_id => $formatted ) {

			    if( isset( $wapf_meta['settings'][$formatted->key] ) ) {

				    $exists = isset($already_occured[$formatted->key]);

				    $settings = $wapf_meta['settings'][$formatted->key][$exists ? $already_occured[$formatted->key] : 0];

				    $already_occured[$formatted->key] = $exists ? $already_occured[$formatted->key] +1 : 1;

				    if( is_array( $settings ) ) {

					    if ( ! $this->is_admin_order && isset( $settings['hide'] ) && $settings['hide'] ) {
						    unset( $formatted_meta[ $meta_id ] );
						    continue;
					    }

					    if ( isset( $wapf_meta['fields'][ $settings['field'] ] ) ) {

							$the_field = $wapf_meta['fields'][ $settings['field'] ];

                            $formatted_label = $the_field[ 'display' ] ?? Fields::format_value_label( $the_field[ 'type' ], $formatted->value );

                            $display_value = apply_filters('wapf/order_item/meta_display_value',
                                $formatted_label,
                                $the_field
                            );

							if( $display_value !== $formatted->value ) {
                                $formatted->display_value = $display_value;
                            }

					    }

				    }

			    }
		    }

			return $formatted_meta;

	    }

	    #endregion

        #region Order Again
        public function order_again_cart_item_data( $cart_item_data, $order_item, $order ) {

            $meta_data = $order_item->get_meta( '_wapf_meta' );

            $cart_item_data['wapf_order_again'] = true;

            if( ! empty( $meta_data['_wapf_child'] ) ) {
                $cart_item_data['_wapf_child'] = $meta_data['_wapf_child'];
            }

            if( ! empty( $meta_data['cart_item_key'] ) ) {
                $cart_item_data['old_cart_item_key'] = $meta_data['cart_item_key'];
            }

                        if( is_array( $meta_data ) ) {
                $wapf = [];
                $groups = wapf_get_field_groups_of_product($order_item->get_product_id());
                $fields = Enumerable::from($groups)->merge(function($x){return $x->fields; })->toArray();

                if( isset( $meta_data['fields'] ) ) { 
                    foreach( $meta_data['fields'] as $field_meta ) {
                        $field = Enumerable::from($fields)->firstOrDefault(function($x) use($field_meta){ return $x->id === $field_meta['id'];});
                        if(!$field) continue;
                        $raw_values = empty($field_meta['values']) ? null : Enumerable::from($field_meta['values'])->select(function($x) {
                            return isset( $x['slug'] ) && empty( $x['use_label'] )  ? $x['slug'] : $x['label'];
                        })->toArray();

                        if( count( $raw_values ) === 1 )
                            $raw_values = $raw_values[0];

                        $clone_idx = $field_meta[ 'clone_idx' ] ?? 0;

                        do_action( 'wapf/order_again/before_cart_item_field',  $order_item, $field, $clone_idx, $raw_values );

                        $cart_field  = Cart::to_cart_item_field( $field, $clone_idx, $raw_values );

                                                $wapf[] = $cart_field;

                                            }
                }
                else { 
                    foreach ( $meta_data as $field_id => $field_meta ) {
                        $field = Enumerable::from($fields)->firstOrDefault(function($x) use($field_id){ return $x->id === $field_id;});
                        if( ! $field || empty( $field_meta['raw'] ) ) continue;
                        $cart_field  = Cart::to_cart_item_field( $field, 0, $field_meta['raw'] );
                        $wapf[] = $cart_field;
                    }
                }

                if( ! empty( $wapf ) ) {
                    $cart_item_data['wapf'] = $wapf;
                    $cart_item_data['wapf_key'] = $this->generate_cart_item_id($order_item->get_product_id(), $order_item->get_variation_id(), $wapf, false);
                    $cart_item_data['wapf_field_groups'] = Enumerable::from($groups)->select(function($x){return $x->id;})->toArray();
                }

            }

            return $cart_item_data;

        }

        public function calculate_prices_for_ordered_again_item( $cart_item, $cart_id ) {

            $pricing = Cart::calculate_cart_item_prices( $cart_item );

            if($pricing !== false) {
                $cart_item['wapf_item_price'] = $pricing;
            }

                        return $cart_item;

        }
        #endregion

        	    #region Lookup tables functionality
	    public function add_lookup_tables() {
		    $tables = apply_filters( 'wapf/lookup_tables', [] );
		    if( ! empty( $tables ) ) {
			    Cache::set( 'lookup_tables', true );
			    echo '<script>var wapf_lookup_tables=' . wp_json_encode( $tables ) . ';</script>';
		    }
	    }

	    public function add_lookup_tables_code() {

		    if( ! Cache::get('lookup_tables') )
			    return;

		    Html::partial('frontend/lookup-tables');

	    }
	    #endregion

	    #region Private Helpers

	    private function generate_cart_item_id($product_id, $variation_id, $data, $edit = true): string {

            if( $edit ) {
                $data = array_values(Enumerable::from($data)->orderBy(function ($x) {
                    return $x['id'];
                })->toArray());
                foreach ($data as &$d) {
                    unset($d['clone_idx']);
                }
            }

		    return md5(json_encode( [
			    (int) $product_id,
			    (int) $variation_id,
			    $data
		    ] ));

	    }

	    #endregion

    }
}