<?php

namespace SW_WAPF_PRO\Includes\Classes {

	use SW_WAPF_PRO\Includes\Models\Field;
	use DateTime;

	class Cart {

		public static function get_cart_item_base_price($product, $quantity,$cart_item) {

			$price = floatval($product->get_price()); 

			$price = apply_filters('wapf/pricing/base', $price, $product, $quantity); 
			$price = apply_filters('wapf/pricing/cart_item_base', $price, $product, $quantity, $cart_item);

			return $price;

		}

        public static function calculate_cart_item_prices( &$cart_item, $add_on_item = false ) {

            if( empty( $cart_item['wapf'] ) ) {
                return false;
            }

            $quantity   = $cart_item[ 'quantity' ] ?? 1;
            $product_id = empty( $cart_item['variation_id'] ) ? $cart_item['product_id'] : $cart_item['variation_id'];
            $product    = wc_get_product( $product_id );

            if( empty( $product ) ) {
                return false;
            }

                        $base               = self::get_cart_item_base_price( $product, $quantity, $cart_item );
            $show_pricing_hints = Util::show_pricing_hints();
            $formula_base       = apply_filters( 'wapf/pricing/cart_item_base_for_formulas', $base, $product, $quantity, $cart_item );
            $options_total      = 0;
            $data               = [ 'options_total' => 0, 'base' => $base ];

                        foreach ( $cart_item['wapf'] as $field_idx => $field ) {

                if( ! Util::should_use_cart_field( $field ) ) {
                    continue;
                }

                $clone_idx = $field[ 'clone_idx' ] ?? ( $cart_item[ 'wapf_clone' ] ?? 0 ); 
                $show_hints = $show_pricing_hints && empty( $field['hide_price_hint'] );

                if( ! empty( $field['values'] ) ) {

                    foreach ( $field['values'] as $idx => $value ) {

                        if( $value['price'] === 0 || $value['price_type'] === 'none' ) continue;

                        $qty_based      = ( isset( $field['clone_type'] ) && $field['clone_type'] === 'qty' )  || ! empty( $field['qty_based'] ); 
                        $v              = $value[ 'label' ] ?? $field[ 'raw' ];
                        $price          = Fields::do_pricing( $qty_based, $value['price_type'], $value['price'], $base, $formula_base, $quantity, $v, $product_id, $cart_item['wapf'], $cart_item['wapf_field_groups'], $clone_idx, $options_total );
                        $options_total  = $options_total + $price;

                                                $cart_item['wapf'][$field_idx]['values'][$idx]['calc_price'] = $price;
                        $cart_item['wapf'][$field_idx]['values'][$idx]['pricing_hint'] = $show_hints ? Helper::format_pricing_hint( $value['price_type'], $price, $product, 'cart' ) : '';

                                                if( ! $add_on_item ) { 
                            WC()->cart->cart_contents[ $cart_item['key'] ]['wapf'][$field_idx]['values'][$idx]['calc_price'] = $price;
                            WC()->cart->cart_contents[ $cart_item['key'] ]['wapf'][$field_idx]['values'][$idx]['pricing_hint'] = $show_hints ? Helper::format_pricing_hint($value['price_type'], $price, $product, 'cart') : '';
                        }

                    }

                }

            }

            $options_total = apply_filters( 'wapf/pricing/cart_item_options', $options_total, $product, $quantity, $cart_item );

            if( $options_total === 0 ) return false;

            $data['options_total'] = $options_total;

            return $data;

        }

		public static function validate_cart_data( $field_groups, $passed, $product_id, $qty, $variation_id = null, $get_value_from_cart_item = false, $cart_item_data = null ) {

			$get_value = function( Field $field, $i, $get_value_from_cartitem, $cart_item_data ) {
				if( $get_value_from_cartitem ) {

					if( ! isset( $cart_item_data['wapf'] ) ) return null;
					$the_field = Enumerable::from($cart_item_data['wapf'])->firstOrDefault( function($x) use($field) { return $x['id'] === $field->id; });
					if( ! $the_field ) return null;

					return $the_field['raw'];

				} else {
					$value = Fields::get_raw_field_value_from_request( $field, $i, true );
				}

				return $value;
			};

			$parent_sections = [];
            $the_product_id = $variation_id === null ? $product_id : $variation_id;

                			foreach ( $field_groups as $field_group ) {

				foreach ( $field_group->fields as $field ) {
					if( $field->type === 'section' )
						$parent_sections[] = $field;
					if( $field->type === 'sectionend' )
						array_pop( $parent_sections );

					if( ! $field->is_category( 'field' ) || $field->type === 'calc' ) continue;

					$loop = 0;
					$clone_type = $field->get_clone_type( true );
					if( $clone_type === 'qty' ) {
						$loop = $qty - 1;
					} else if( $clone_type === 'button') {
						$type_for_field_only  = $field->get_clone_type();
						$the_clone_field_id = empty( $type_for_field_only ) ? $parent_sections[ count( $parent_sections ) - 1 ]->id : $field->id;
						$loop = isset( $_REQUEST['wapf'] ) && isset( $_REQUEST['wapf'][ 'field_' . $the_clone_field_id . '_qty' ] ) ?  intval( $_REQUEST['wapf'][ 'field_' . $the_clone_field_id . '_qty' ] ) : 0;
					}

					$filter = 'wapf/validate/' . $field->id;
					if ( has_filter( $filter ) ) {
						for( $i = 0;$i <= $loop; $i++ ) {
							$clone_idx = $i > 0 ? ( $i + 1 ): 0;
							$value = $get_value($field, $clone_idx, $get_value_from_cart_item, $cart_item_data);
							$error = apply_filters( $filter, [ 'error' => ! $passed ], $value, $field, $the_product_id, $clone_idx );
							if ( $error['error'] )
								return $error['message'];
						}
					}

					for( $i = 0; $i <= $loop; $i++ ) {

                        						$clone_idx = $i > 0 ? ( $i + 1 ) : 0;

						if ( $field->type === 'file' ) {
							$file_err = self::validate_file_field( $field, $clone_idx );
							if( is_string( $file_err ) )
								return $file_err;
						}

						$value = $get_value( $field, $clone_idx, $get_value_from_cart_item, $cart_item_data );

						if( $value === '' || $value === null ) {
							if( ! Fields::should_field_be_filled_out( $field_group, $field, $the_product_id, $clone_idx ) )
								continue;
							if( $field->required )
								return sprintf( __( 'The field "%s" is required.', 'sw-wapf' ), $field->get_label() );
						}

						if( $value !== null && $value !== '' ) {

                            							$valid = true;

                            if( $field->is( 'multi_select' ) ) {
								$valid = self::validate_multiple_choice_field( $field, $value );
							}

							if( $field->type === 'date' ) {
								$valid = self::validate_date_field( $field, $value );
							}

							if( $field->type === 'number' ) {
								$valid = self::validate_number_field( $field, $value );
							}

                            $err = is_string( $valid ) ? ['error' => true, 'message' => $valid] : [ 'error' => ! $passed ];

							$err = apply_filters( 'wapf/validate', $err, $value, $field, $the_product_id, $clone_idx, $qty, $get_value_from_cart_item, $cart_item_data );

							if ( $err['error'] ) {
                                return $err[ 'message' ] ?? '';
                            }

						}

					}
				}
			}

			return $passed;

		}

		public static function to_cart_item_field( Field $field, $clone_idx = 0, $raw_values = null ) {

			if( ! $raw_values ) {
				$raw_values = Fields::get_raw_field_value_from_request( $field, $clone_idx );
			}

			$values = Fields::raw_to_cartfield_values( $field, $raw_values, $clone_idx );

            $clone_type = $field->get_clone_type( true );

			$cart_item_field = [
				'id'            => $field->id,
				'type'          => $field->type,
				'raw'           => is_array( $raw_values ) ? array_map( 'sanitize_textarea_field', $raw_values ) : sanitize_textarea_field( '' . $raw_values ),
				'values'        => $values,
				'clone_type'    => $clone_type,
                'clone_idx'     => $clone_idx,
				'label'         => esc_html( $field->get_label() ),
				'hide_cart'     => self::should_hide_on( 'cart', $field, $raw_values ),
				'hide_checkout' => self::should_hide_on( 'checkout', $field, $raw_values ),
				'hide_order'    => self::should_hide_on( 'order', $field, $raw_values ),
			];

            			if( $clone_type === 'button' ) {

				if( $clone_idx > 0) { 
					$clone_label = $field->get_clone_label();
					if ( empty ( $clone_label ) ) {
						$clone_label = empty( $field->parent_clone['label'] ) ? $cart_item_field['label'] : sprintf( '%s - %s', str_replace( '{n}', $clone_idx, $field->parent_clone['label'] ), $cart_item_field['label'] );
					} else {
						$clone_label = str_replace( '{n}', $clone_idx, $clone_label );
					}
					$cart_item_field['label'] = $clone_label;
				}
			}

			return apply_filters('wapf/cart/cart_item_field', $cart_item_field, $field, $clone_idx );

		}

		#region Private Helpers
		private static function should_hide_on( $page, Field $field, $raw_value ) {

			if( $field->type === 'number' && isset( $field->options['hide_zero'] ) && $field->options['hide_zero'] && $raw_value === '0' ) {
                return true;
            }

                        return Util::should_hide_on( $field, $page );

		}

		private static function validate_file_field(Field $field, $clone_idx) {
			$files = Cache::get_files();

			if(File_Upload::is_ajax_upload()) {
				$value = Fields::get_raw_field_value_from_request( $field, $clone_idx, true );
				$return = File_Upload::validate_ajax_upload_for_cart($value);
			}
			else
				$return = File_Upload::validate_files_for_field( $files, $field );

			return $return;
		}

		private static function validate_date_field(Field $field, $value) {
			$date_format = get_option('wapf_date_format','mm-dd-yyyy');
			$regex = '/'.Helper::date_format_to_regex($date_format).'/';
			if(!preg_match($regex,$value))
				return sprintf(__('The field "%s" has an incorrect date format.','sw-wapf'), $field->get_label());

			$disable_past = isset($field->options['disable_past']) && $field->options['disable_past'];
			$disable_future = isset($field->options['disable_future']) && $field->options['disable_future'];
			$disable_today = isset($field->options['disable_today']) && $field->options['disable_today'];

			$date = DateTime::createFromFormat(Helper::date_format_to_php_format($date_format),$value, wp_timezone())->setTime(0,0,0);
			$now = new DateTime('now', wp_timezone());
			$now->setTime(0,0,0);
			$interval = $now->diff($date);

			if($disable_today && $interval->days == 0)
				return sprintf(__("\"%s\" can't be equal to today.",'sw-wapf'), $field->get_label());
			if($disable_future && $interval->invert === 0)
				return sprintf(__("\"%s\" can't be in the future.",'sw-wapf'), $field->get_label());
			if($disable_past && $interval->invert === 1)
				return sprintf(__("\"%s\" can't be in the past.",'sw-wapf'), $field->get_label());

			return true;
		}

		private static function validate_number_field(Field $field, $value) {
			$value = floatval($value);

			if(isset($field->options['minimum']) && $field->options['minimum'] != '' && $value < floatval($field->options['minimum']) )
				return sprintf(__( 'The field "%s" requires a minimum of %s.', 'sw-wapf' ), $field->get_label(), $field->options['minimum']);

			if(isset($field->options['maximum']) && $field->options['maximum'] != '' && $value > floatval($field->options['maximum']) )
				return sprintf(__( 'The field "%s" requires a maximum of %s.', 'sw-wapf' ), $field->get_label(), $field->options['maximum']);

			return true;
		}

        private static function validate_quantity_selector( Field $field, $value ) {

            $value = array_values( (array) $value ); 
            $total = array_sum( $value );

            if( isset( $field->options['min_choices'] ) && $field->options['min_choices'] != '' && $total < floatval( $field->options['min_choices'] ) )
                return sprintf(__( '"%s" requires a minimum of %s choices.', 'sw-wapf' ), $field->get_label(), $field->options['min_choices']);

            if( isset( $field->options['max_choices'] ) && $field->options['max_choices'] != '' && $total > floatval( $field->options['max_choices'] ) )
                return sprintf(__( '"%s" requires a maximum of %s choices.', 'sw-wapf' ), $field->get_label(), $field->options['max_choices']);

            if( ! empty( $field->options['choices'] ) ) { 

                for( $i = 0; $i < count( $field->options['choices'] ); $i++ ) {

                                        $v = $value[$i];
                    $choice = $field->options['choices'][$i];

                                        if( isset( $choice['options']['min'] ) && $choice['options']['min'] != '' && $v < floatval( $choice['options']['min'] ) )
                        return sprintf( __( 'The item "%s" requires a minimum quantity of %s.', 'sw-wapf' ), $choice['label'], $choice['options']['min'] );

                    if( isset( $choice['options']['max']) && $choice['options']['max'] != '' && $v > floatval( $choice['options']['max'] ) )
                        return sprintf( __( 'The item "%s" requires a maximum quantity of %s.', 'sw-wapf' ), $choice['label'], $choice['options']['max'] );

                }

            }

            return true;

                    }

		private static function validate_multiple_choice_field(Field $field,$value) {

			if ( ! empty( $field->options['min_choices'] ) && count( (array) $value ) < intval( $field->options['min_choices'] ) )
				return sprintf(__( 'The field "%s" requires at minimum %s selections.', 'sw-wapf' ), $field->get_label(),$field->options['min_choices']);

			if ( ! empty( $field->options['max_choices'] ) && count( (array) $value ) > intval( $field->options['max_choices'] ) )
				return sprintf(__( 'The field "%s" requires at maximum %s selections.', 'sw-wapf' ), $field->get_label(), $field->options['max_choices'] );

			return true;
		}
		#endregion

	}
}