<?php

namespace SW_WAPF_PRO\Includes\Classes {

    use SW_WAPF_PRO\Includes\Models\Conditional;
    use SW_WAPF_PRO\Includes\Models\ConditionalRule;
    use SW_WAPF_PRO\Includes\Models\ConditionRule;
    use SW_WAPF_PRO\Includes\Models\ConditionRuleGroup;
    use SW_WAPF_PRO\Includes\Models\Field;
    use SW_WAPF_PRO\Includes\Models\FieldGroup;

	class Field_Groups
    {

        private static $all_groups_cache_key = 'field-groups';
        private static $field_group_cache_key = 'field-group-';
        private static $field_groups_product_cache_key = 'field-groups-product-';

        public static function field_group_to_raw_fields_json( FieldGroup $fg ) {

	        foreach( $fg->fields as $field ) {

                if( $field->type === 'image-swatch' || $field->type === 'multi-image-swatch' ) {
                    if( empty( $field->options['grid_layout'] ) ) {
                        $field->options['grid_layout'] = 'flexible';
                    }
                }

                	        	$conditional_count = count( $field->conditionals );

		        for ( $j = 0; $j < $conditional_count; $j++ ) {

					$rules_count = count( $field->conditionals[$j]->rules );

			        for( $i = 0; $i < $rules_count; $i++ ) {
				        if( in_array( $field->conditionals[$j]->rules[$i]->condition, [ 'product_var', '!product_var', 'patts', '!patts'] ) ) {
					        unset( $field->conditionals[$j]->rules[$i] );
					        continue;
				        }

				        if( isset( $field->conditionals[$j]->rules[$i]->generated )  && $field->conditionals[$j]->rules[$i]->generated === true )
					        unset( $field->conditionals[ $j ]->rules[ $i ] );

			        }

			        $field->conditionals[ $j ]->rules = array_values($field->conditionals[ $j ]->rules);

			        if( empty( $field->conditionals[$j]->rules ) ) {
				        unset( $field->conditionals[ $j ] );
			        }

		        }

		        $field->conditionals = array_values($field->conditionals);

	        }

            foreach ( $fg->rules_groups as $rg ) {
                foreach ( $rg->rules as $r ) {
                    if( $r->condition === 'product' ) $r->condition = 'products';
                    if( $r->condition === '!product' ) $r->condition = '!products';
                    if( $r->condition === '!product_cat' ) $r->condition = '!product_cats';
                    if( $r->condition === 'product_cat' ) $r->condition = 'product_cats';
                }
            }

            	        $json_array = json_decode(json_encode($fg->fields),true);

            foreach( $json_array as &$field ) {

                if ( ! empty( $field["options"] ) ) {
                    foreach ( $field["options"] as $k => $v ) {
                        $field[$k] = $v;
                    }
                    unset( $field['options'] );
                }
            }

            return $json_array;
        }

        public static function raw_json_to_field_group($raw) {

            if( empty( $raw['id'] ) || empty( $raw['type'] ) ) {
                return null;
            }

                        $fg = new FieldGroup();

            $fg->id = Helper::sanitize( $raw['id'], 'text' );
            $fg->type = Helper::sanitize( $raw['type'], 'text' );

			if( isset( $raw['variables'] ) ) {
				foreach($raw['variables'] as $variable){
					$v = [];
					$v['default'] = Helper::sanitize_textfield_without_tags( $variable['default'] );
					$v['name'] = Helper::sanitize( $variable['name'], 'text', '' );
					$v['rules'] = [];

					foreach ($variable['rules'] as $vr) {
						$rule = [];
						$rule['type'] = Helper::sanitize( $vr['type'], 'text', '' );
						$rule['field'] = Helper::sanitize( $vr['field'], 'text', '' );
						$rule['variable'] = Helper::sanitize_textfield_without_tags($vr['variable']);
						$rule['condition'] = Helper::sanitize( $vr['condition'], 'text', '' );
						$rule['value'] = Helper::sanitize( $vr['value'], 'text', '' );
						$v['rules'][] = $rule;
					}
					$fg->variables[] = $v;
				}
			}

            if( isset( $raw['layout'] ) ) {

                if( isset( $raw['layout']['labels_position'] ) )
                    $fg->layout['labels_position'] = Helper::sanitize( $raw['layout']['labels_position'], 'options', 'above', [ 'above', 'below' ]  );

                if( isset($raw['layout']['instructions_position'] ) )
                    $fg->layout['instructions_position'] = Helper::sanitize( $raw['layout']['instructions_position'], 'options', 'field', [ 'label', 'field', 'tooltip' ] );

                if( isset( $raw['layout']['mark_required'] ) )
                    $fg->layout['mark_required'] = Helper::sanitize( $raw['layout']['mark_required'], 'bool', true );

	            if( isset( $raw['layout']['enable_gallery_images'] ) ) {

		            $fg->layout['enable_gallery_images'] = Helper::sanitize( $raw['layout']['enable_gallery_images'], 'bool', false );

		            $fg->layout['swap_type'] = 'rules';
		            if( isset( $raw['layout']['swap_type'] ) )
			            $fg->layout['swap_type'] = $raw['layout']['swap_type'] === 'last' ? 'last' : 'rules';

		            $fg->layout['gallery_images'] = [];
		            if(!empty($raw['layout']['gallery_images'])) {
			            foreach ( $raw['layout']['gallery_images'] as $gallery_image ) {

			            	$new_gallery_image = [
                                'source'    => Helper::sanitize( $gallery_image['source'], 'text' ),
                                'url'       => Helper::sanitize( $gallery_image['url'], 'text' ),
                                'id'        => Helper::sanitize( $gallery_image['id'], 'text' ),
					            'values'    => [],
				            ];

			            	if(!empty($gallery_image['values'])) {
			            		foreach ($gallery_image['values'] as $value) {
			            			$new_gallery_image['values'][] = [
			            				'field' => Helper::sanitize( $value['field'], 'text' ),
			            				'value' => Helper::sanitize( $value['value'], 'text' ),
						            ];
					            }
				            }

				            $fg->layout['gallery_images'][] = $new_gallery_image;

		                }

		            }
	            }

            }

            $reserved_keys = [
                'id', 'key', 'label', 'description', 'default', 'placeholder', 'choices', 
                'conditionals', 'type', 'subtype', 'required', 'options', 'p_content', 
                'image', 'attachment', 'class', 'width', 'pricing', 'parent_clone', 'clone', 
                'hide_cart', 'hide_checkout','hide_order', 'product_selection', 'product_query',
                'min_choices', 'max_choices', 'items_per_row_mobile', 'items_per_row_tablet', 'items_per_row', 
                'display', 'minlength', 'maxlength', 'label_pos', 'grid_layout', 'item_width',
                'disabled_days', 'minimum', 'maximum'
            ];

            foreach( $raw['fields'] as $raw_field ) {

                if( empty( $raw_field['id'] ) || empty( $raw_field['type'] ) ) {
                    continue;
                }

                                $field = new Field();

                $field->id = Helper::sanitize( $raw_field['id'], 'text' );
                $field->type = Helper::sanitize( $raw_field['type'], 'text', 'text' );

	            if( isset( $raw_field['label'] ) )
                    $field->label = Helper::sanitize( $raw_field['label'], 'html' );

                	            if( isset( $raw_field['description'] ) )
                    $field->description = Helper::sanitize( $raw_field['description'], 'html' );

                                if( isset( $raw_field['subtype'] ) )
                   $field->subtype = Helper::sanitize( $raw_field['subtype'], 'text' );

                                $field->required = isset( $raw_field['required'] ) ? Helper::sanitize( $raw_field['required'], 'bool', false ) : false;

                                if( isset( $raw_field['class'] ) )
                    $field->class = implode( ' ', array_map( 'sanitize_html_class', explode( ' ', $raw_field['class'] ) ) );

                                if( isset( $raw_field['width'] ) )
                    $field->width = Helper::sanitize( $raw_field['width'], 'float', 100 );

                if( isset( $raw_field['choices'] ) ) {

                                        $field->options['choices'] = [];

                                        foreach ( $raw_field['choices'] as $raw_choice ) {

                                                if( empty(  $raw_choice['slug'] ) || ! isset( $raw_choice['label'] ) ) {
                            continue;
                        }

                                                $choice = [
                            'slug'      => Helper::sanitize( $raw_choice['slug'], 'text' ),
                            'label'     => Helper::sanitize( $raw_choice['label'], 'html' ),
                            'selected'  => isset( $raw_choice['selected'] ) ? Helper::sanitize( $raw_choice['selected'], 'bool', false ) : false, 
                            'disabled'  => isset( $raw_choice['disabled'] ) ? Helper::sanitize( $raw_choice['disabled'], 'bool', false ) : false,
	                        'options'   => []
                        ];

                                                if(isset($raw_choice['pricing_type']))
                            $choice['pricing_type'] = Helper::sanitize( $raw_choice['pricing_type'], 'text' );

                                                if(isset($raw_choice['pricing_amount']))
                            $choice['pricing_amount'] = $choice['pricing_type'] === 'fx' ?
                                Helper::sanitize_textfield_without_tags( $raw_choice['pricing_amount'] ) :
		                        floatval(Helper::normalize_string_decimal($raw_choice['pricing_amount']));

                                                if( isset( $raw_choice['color'] ) )
                            $choice['color'] = Helper::sanitize( $raw_choice['color'], 'text' );

                                               if( isset( $raw_choice['image'] ) )
                            $choice['image'] = Helper::sanitize( $raw_choice['image'], 'url' );

                       						if( isset( $raw_choice['attachment'] ) )
							$choice['attachment'] = Helper::sanitize( $raw_choice['attachment'], 'int' );

                                                if( isset( $raw_choice['id'] ) )
                            $choice['id'] = Helper::sanitize( $raw_choice['id'], 'int' );

                                                if( isset( $raw_choice['options']) && is_array( $raw_choice['options'] ) ) {
							foreach( $raw_choice['options'] as $key => $val ) {
                                $sanitize_type = $key === 'desc' ? 'textarea' : 'text';
                                $choice['options'][ Helper::sanitize( $key, 'text' ) ] = Helper::sanitize( $val, $sanitize_type );
							}
                        }

                                                $field->options['choices'][] = $choice;

                                            }
                }

                if( isset( $raw_field['placeholder'] ) )
                    $field->options['placeholder'] = Helper::sanitize( $raw_field['placeholder'], 'text' );

                                if( isset( $raw_field['default'] ) ) {
                    switch( $field->type ) {
                        case 'textarea': $field->options['default'] = Helper::sanitize( $raw_field['default'], 'textarea' ); break;
                        case 'number': 
                            $number_type = isset( $raw_field['number_type'] ) && $raw_field['number_type'] === 'any' ? 'decimal' : 'int';
                            if( $raw_field['default'] != '' ) {
                                $field->options['default'] = Helper::sanitize( $raw_field['default'], $number_type, 0 );
                            }
                            break;
                        default: $field->options['default'] = Helper::sanitize( $raw_field['default'], 'text' ); break;
                    }
                }

                if( isset( $raw_field['hide_cart'] ) )
                	$field->options['hide_cart'] = $raw_field['hide_cart'] == 'true';

                	            if( isset( $raw_field['hide_checkout'] ) )
		            $field->options['hide_checkout'] = $raw_field['hide_checkout'] == 'true';

                	            if( isset( $raw_field['hide_order'] ) )
		            $field->options['hide_order'] = $raw_field['hide_order'] == 'true';

                				if( isset( $raw_field['p_content'] ) )
					$field->options['p_content'] = wp_kses( $raw_field['p_content'],  array_merge( Html::$minimal_allowed_html_element, ['img' => ['src' => [],'target' => [], 'class' => [], 'alt' => [], 'style' => [], 'id' => [] ] ] ) );

                	            if( isset( $raw_field['image'] ) )
		            $field->options['image'] = Helper::sanitize( $raw_field['image'], 'url', '' );

	                            if(isset($raw_field['attachment'])) {
	            	$field->options['attachment'] = Helper::sanitize( $raw_field['attachment'], 'int' );
	            }

                if( isset( $raw_field['min_choices'] ) ) {
                    $field->options['min_choices'] = Helper::sanitize( $raw_field['min_choices'], 'int' );
                }

                if( isset( $raw_field['max_choices'] ) ) {
                    $field->options['max_choices'] = Helper::sanitize( $raw_field['max_choices'], 'int' );
                }

                if( isset( $raw_field['minlength'] ) ) {
                    $field->options['minlength'] = Helper::sanitize( $raw_field['minlength'], 'int' );
                }

                if( isset( $raw_field['maxlength'] ) ) {
                    $field->options['maxlength'] = Helper::sanitize( $raw_field['maxlength'], 'int' );
                }

                if( isset( $raw_field['minimum'] ) ) {
                    $field->options['minimum'] = Helper::sanitize( $raw_field['minimum'], 'float' );
                }

                if( isset( $raw_field['maximum'] ) ) {
                    $field->options['maximum'] = Helper::sanitize( $raw_field['maximum'], 'float' );
                }

                if( isset( $raw_field['label_pos'] ) ) {
                    $field->options['label_pos'] = Helper::sanitize( $raw_field['label_pos'], 'options', 'default', [  'default', 'out', 'hide', 'tooltip' ] );
                }

                                if( isset( $raw_field['items_per_row'] ) ) {
                    $field->options['items_per_row'] = Helper::sanitize( $raw_field['items_per_row'], 'int', 1 );
                }

                if( isset( $raw_field['items_per_row_mobile'] ) ) {
                    $field->options['items_per_row_mobile'] = Helper::sanitize( $raw_field['items_per_row_mobile'], 'int', 1 );
                }

                if( isset( $raw_field['items_per_row_tablet'] ) ) {
                    $field->options['items_per_row_tablet'] = Helper::sanitize( $raw_field['items_per_row_tablet'], 'int', 1 );
                }

                if( isset( $raw_field['display'] ) ) {
                    $field->options['display'] = Helper::sanitize( $raw_field['display'], 'text' );
                }

                if( isset( $raw_field['grid_layout'] ) ) {
                    $field->options['grid_layout'] = Helper::sanitize( $raw_field['grid_layout'], 'options', 'fixed', [ 'flexible', 'fixed' ] );
                }

                if( isset( $raw_field['item_width'] ) ) {
                    $field->options['item_width'] = max( 30, Helper::sanitize( $raw_field['item_width'], 'int', 68 ) );
                }

                if( isset( $raw_field['disabled_days'] ) ) {
                    if( $raw_field['disabled_days'] === '0' )
                        $field->options['disabled_days'] = $raw_field['disabled_days'];
                    else $field->options['disabled_days'] = Helper::sanitize( $raw_field['disabled_days'], 'text', '' );
                }

                do_action_ref_array( 'wapf/admin/sanitize_field', array( &$field, &$raw_field ) );

                if( empty( $field ) ) continue;

                foreach( $raw_field as $k => $v ) {

                    $key = Helper::sanitize( $k, 'text', '' );

                    if( empty( $key ) || in_array( $key, $reserved_keys ) ) continue;

                    if( isset( $field->options[ $key ] ) ) continue;

                                        switch( $key ) {
                        case 'formula': $field->options[ $key ] = Helper::sanitize_textfield_without_tags( $v ); break;
                        default: $field->options[ $key ] = Helper::sanitize( $v, 'textarea' , '' ); break;
                    }

                }

	            if( ! empty( $raw_field['clone'] ) ) {
	            	$field->clone['enabled'] = $raw_field['clone']['enabled'] == 'true';
	            	if( $field->clone['enabled'] ) {
			            $field->clone['type'] =  $raw_field['clone']['type'] === 'button' ? 'button' : ( $raw_field['clone']['type'] === 'qty' ? 'qty' : 'field' );
			            if( !empty( $raw_field['clone']['add'] ) ) $field->clone['add'] = Helper::sanitize( $raw_field['clone']['add'], 'text', '' );
			            if( !empty( $raw_field['clone']['del'] ) ) $field->clone['del'] = Helper::sanitize( $raw_field['clone']['del'], 'text', '' );
			            if( !empty( $raw_field['clone']['label'] ) ) $field->clone['label'] = Helper::sanitize( $raw_field['clone']['label'], 'text', '' );
			            if( !empty( $raw_field['clone']['field'] ) ) $field->clone['field'] = Helper::sanitize( $raw_field['clone']['field'], 'text', '' );
			            if( !empty( $raw_field['clone']['max'] ) ) $field->clone['max'] = Helper::sanitize( $raw_field['clone']['max'], 'int', 1 );
		            }
	            }

                if( isset( $raw_field['pricing'] ) && isset( $raw_field['pricing']['type'] ) ) {

                                        $field->pricing->enabled = $raw_field['pricing']['enabled'] == 'true';
	                $field->pricing->type = Helper::sanitize( $raw_field['pricing']['type'], 'text', 'fixed' );

	                $field->pricing->amount = $field->pricing->type === 'fx' ?
                        Helper::sanitize_textfield_without_tags( $raw_field['pricing']['amount'] ) :
		                floatval(Helper::normalize_string_decimal($raw_field['pricing']['amount']));

                                    }

                foreach( $raw_field['conditionals'] as $raw_conditional ) {

                    $conditional = new Conditional();

                    foreach( $raw_conditional['rules'] as $raw_rule ) {

                                                $rule = new ConditionalRule();
                        $rule->field = Helper::sanitize( $raw_rule['field'], 'text', '' );
                        $rule->value = Helper::sanitize( $raw_rule['value'], 'text', '' );
                        $rule->condition = Helper::sanitize( $raw_rule['condition'], 'text', '' ); 

                        $conditional->rules[] = $rule;
                    }

                    $field->conditionals[] = $conditional;

                }

                $fg->fields[] = $field;

            }

            if( ! empty( $raw['conditions'] ) ) {
	            foreach ( $raw['conditions'] as $raw_condition ) {

                    		            $condition = new ConditionRuleGroup();

		            foreach ( $raw_condition['rules'] as $raw_rule ) {
			            $rule = new ConditionRule();
                        $rule->condition = Helper::sanitize( $raw_rule['condition'], 'text', '' );
			            if ( isset( $raw_rule['value'] ) ) {
				            $rule->value = is_string( $raw_rule['value'] ) ? Helper::sanitize( $raw_rule['value'], 'text', '' ) : Enumerable::from( $raw_rule['value'] )->select( function ( $value ) {
					            return [
						            'id'   => Helper::sanitize( $value['id'], 'text', '' ),
						            'text' => Helper::sanitize( $value['text'], 'text', '' )
					            ];
				            } )->toArray();
			            }
			            $rule->subject = Helper::sanitize( $raw_rule['subject'], 'text', '' );

			            $condition->rules[] = $rule;
		            }

		            $fg->rules_groups[] = $condition;

	            }
            }

	        for ( $i = 0; $i < count( $fg->fields ); $i++ ) {

	        	                $clone_type = $fg->fields[$i]->get_clone_type();

	        	if( $fg->fields[$i]->type === 'section' && ! empty( $clone_type ) ) {

                                        $open_level = 0;

	        		for( $j = $i+1; $j < count( $fg->fields ); $j++ ) {

                        if( $fg->fields[$j]->type === 'section' )
                            $open_level++;

						if( $fg->fields[$j]->type === 'sectionend' ) {
                            if( $open_level === 0 ) {
                                break;
                            }
                            $open_level--;
                        }

						$fg->fields[$j]->parent_clone = [
							'type' => $clone_type,
							'label' => $fg->fields[$i]->get_clone_label()
						];

			        }

		        }

	        }

	        for ( $i = 0; $i < count( $fg->fields ); $i++ ) {

                $field = $fg->fields[$i]; 

                                if( $field->type === 'section' && $field->has_conditionals() ) {

                    			        for( $j = $i + 1; $j < count( $fg->fields ); $j++ ) {

			        	if( $fg->fields[$j]->type === 'sectionend' ) {
					        break;
                        }

						$fieldB = $fg->fields[$j]; 

				        if( empty( $fieldB->conditionals ) ) {
					        foreach( $field->conditionals as $fieldA_Condition ) {

                                					        	$c = new Conditional();

                                					        	$c->rules = Enumerable::from( $fieldA_Condition->rules )->select( function( $x ) {
					        		$r = new ConditionalRule();
					        		$r->generated = true;
					        		$r->field = $x->field;
					        		$r->value = $x->value;
					        		$r->condition = $x->condition;
					        		return $r;
						        } )->toArray();

                                					        	$fieldB->conditionals[] = $c;

                                					        }
				        }
				        else { 
				        	$conditionals = [];
				        	foreach ( $fieldB->conditionals as $fieldB_condition ) {
								$ctr = 0;
				        		foreach( $field->conditionals as $fieldA_condition ) {
				        			$ctr++;

				        			$c = new Conditional();

				        			$rules_from_a = Enumerable::from( $fieldA_condition->rules )->select( function( $x ) {
				        				$v = new ConditionalRule();
				        				$v->generated = true;
				        				$v->field = $x->field;
				        				$v->value = $x->value;
				        				$v->condition = $x->condition;
				        				return $v;
				        			})->toArray();

							        $rules_from_b = Enumerable::from($fieldB_condition->rules)->select(function($x) use ($ctr) {
								        $v = new ConditionalRule();
								        $v->generated = $ctr > 1 ? true : $x->generated; 
								        $v->field = $x->field;
								        $v->value = $x->value;
								        $v->condition = $x->condition;
								        return $v;
							        })->toArray();

							        $c->rules = array_merge( $rules_from_b, $rules_from_a );

				        			$conditionals[] = $c;

                                    						        }
					        }

                            				        	$fieldB->conditionals = $conditionals;

                            				        }

			        }

		        }

	        }

            return $fg;

        }

        public static function get_all( $deprecated = null ): array {

                        $cache_key = self::$all_groups_cache_key;

            $cached = Cache::get( $cache_key );

            if( $cached === false ) {

                            	$args = [
		            'post_type'                 => 'wapf_product',
		            'posts_per_page'            => -1,
		            'post_status'               => 'publish',
                    'no_found_rows'             => true, 
		            'update_post_meta_cache'    => false, 
                    'update_post_term_cache'    => false,
	            ];

	            if( function_exists( 'icl_get_languages' ) ) {
		            $args['suppress_filters'] = false;
	            }

	            $posts = get_posts( $args );
                $groups = [];

                foreach ( $posts as $post ) {
                	$processed = self::process_data( $post->post_content );
                	if( ! empty( $processed->fields ) ) {
                        $groups[] = $processed;
                    }
                }

                                $cached = $groups;

                Cache::set( $cache_key, $groups );

                           }

                        return $cached;
        }

        public static function get_by_id( $id ) {

            $cache_key = self::$field_group_cache_key . $id;

            $cached = Cache::get( $cache_key );
            if($cached !== false) {
                return $cached;
            }

            if(strpos($id, 'p_') !== false) {
                $the_group = Field_Groups::process_data(get_post_meta(intval(str_replace('p_','',$id)),'_wapf_fieldgroup', true));
                Cache::set( $cache_key, $the_group );
                return $the_group;
            }

            $types = ['product'];

            foreach($types as $type) {
                $all_groups_cached = Cache::get(self::$all_groups_cache_key . $type);

                if($all_groups_cached !== false) {

                    $the_group = Enumerable::from($all_groups_cached)->firstOrDefault(function($x) use($id) {
                        return $x->id === $id;
                    });

                    if($the_group) {
                        Cache::set($cache_key, $the_group);
                        return $the_group;
                    }
                }
            }

            $post = get_post(intval($id));
            if(!$post || !in_array($post->post_type,wapf_get_setting('cpts')))
                return null;

            $cached = self::process_data($post->post_content);
            Cache::set($cache_key,$cached);

            return $cached;

        }

        public static function get_by_ids(array $ids): array {

            $field_groups = [];

            foreach($ids as $id) {

                $field_group = self::get_by_id($id);
                if($field_group)
                    $field_groups[] = $field_group;
            }

            return $field_groups;

        }

                public static function product_has_field_group( $product ): bool {

            if( is_int( $product ) ) {
                $product = wc_get_product( $product );
            }

            $product_id = $product->get_id();
            $cache_key  = 'product-has-field-group-' . $product_id;
            $cached     = Cache::get( $cache_key, null );

            if( $cached !== null ) {
                return $cached;
            }

                        $field_group_on_product = get_post_meta( $product_id, '_wapf_fieldgroup', true );

            if( ! empty( $field_group_on_product ) && ! empty( $field_group_on_product['fields'] ) ) {
                Cache::set( $cache_key, true );
                return true;
            }

            $field_groups = Field_Groups::get_all();

            foreach ( $field_groups as $group ) {

            	if( empty( $group->fields ) ) continue;

                                if( Conditions::is_field_group_valid_for_product( $group, $product ) ) {
                    Cache::set( $cache_key, true );
                    return true;
                }
            }

            Cache::set( $cache_key, false );
            return false;

        }

        public static function get_field_groups_of_product( $product ) {

	        if( is_int( $product ) ) {
                $product = wc_get_product( $product );
            }

            if( empty( $product ) ) {
                return [];
            }

	        $product_id = $product->get_id();
            $parent_id  = $product->get_parent_id();
	        $cache_key  = self::$field_groups_product_cache_key . $product_id; 
	        $cached     = Cache::get( $cache_key );

	        if( $cached !== false ) {
                return apply_filters( 'wapf/product_field_groups', $cached, $product );
	        }

	        $field_groups       = [];
	        $local_field_group  = self::process_data( get_post_meta( empty( $parent_id ) ? $product_id : $parent_id, '_wapf_fieldgroup', true ) ); 
            $all_field_groups   = self::get_all(); 

            	        if( $local_field_group && ! empty( $local_field_group->fields ) || ! empty( $local_field_group->variables ) ) {
                $field_groups[] = $local_field_group;
            }

	        foreach( $all_field_groups as $fg ) {

                                $meta = [];

                if( Conditions::is_field_group_valid_for_product( $fg, $product, $meta ) ) {

                    $copy_fg = clone $fg;
                    $rules = [];

                                        if( ! empty( $meta['frontend_validation'] ) && ! empty( $meta['valid_rule_group'] ) ) {
                        $rules = Conditions::get_frontend_conditions( $meta['valid_rule_group'] );
                    }

                    foreach ( $copy_fg->fields as $field ) {
                        Conditions::merge_frontend_conditions( $field, $rules );
                    }

                                        $field_groups[] = $copy_fg;

                }

                	        }

	        Cache::set( $cache_key, $field_groups );

	        return apply_filters( 'wapf/product_field_groups', $field_groups, $product );

        }

        public static function save( FieldGroup $fg, string $post_type = 'wapf_product', $post_id = null, $post_title = null, $status = null ): int {

            $post_type  = strtolower( $post_type );
            $fg->type   = $post_type;
            $save       = [ 'post_type' => $post_type ];

            if( $post_id != null ) {
                $save['ID'] = $post_id;
                $fg->id = $post_id;
            }

            if( $status != null ) {
                $save[ 'post_status' ] = $status;
            }

            if( $post_title != null ) {
                $save[ 'post_title' ] = sanitize_text_field( $post_title );
            }

            $save['post_content'] = Helper::wp_slash( serialize( $fg->to_array() ) );

            if( $post_id ) {
                $id = wp_update_post( $save );
            }
            else {

                $id = wp_insert_post( $save );

                $fg->id = $id;
                $update_data = [
                    'ID' => $id,
                    'post_content' => Helper::wp_slash( serialize( $fg->to_array() ) )
                ];

                $id = wp_update_post( $update_data );

                            }

            return $id;

                    }

        public static function process_data( $data ) {

                        if( is_serialized( $data ) ) {

	        	try {

                                       $unserialized = unserialize( $data );

                    					if( is_array( $unserialized ) ) {
						$fg = new FieldGroup();
						return $fg->from_array( $unserialized );
			        }

	        						return false;

		        } catch( \Exception $e ) {
			        return false;
				}

	        }

	        if( is_array( $data ) ) {

		        $fg = new FieldGroup();
		        return $fg->from_array( $data );

	        			}

	        return $data;

        }

    }

}