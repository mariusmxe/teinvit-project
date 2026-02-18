<?php

namespace SW_WAPF_PRO\Includes\Classes
{

    use SW_WAPF_PRO\Includes\Models\Field;
    use SW_WAPF_PRO\Includes\Models\FieldGroup;

    class Html
    {
    	public static $minimal_allowed_html = [
		    'br'        => [],
		    'hr'        => ['class' => [], 'style' => [],'id' => []],
		    'a'         => ['href' => [], 'target' => [], 'class' => [], 'style' => [],'id' => []],
		    'i'         => ['class' => [], 'style' => [],'id' => []],
		    'em'        => ['class' => [], 'style' => [],'id' => []],
		    'strong'    => ['class' => [], 'style' => [],'id' => []],
		    'b'         => ['class' => [], 'style' => [],'id' => []],
		    'span'      => ['class' => [], 'style' => [],'id' => []],
		    'div'       => ['class' => [], 'style' => [],'id' => []],
		    'ul'        => ['class' => [], 'style' => [],'id' => []],
		    'ol'        => ['class' => [], 'style' => [],'id' => []],
		    'li'        => ['class' => [], 'style' => [],'id' => []],
	    ];

        public static $minimal_allowed_html_element = [
            'br'        => [],
            'hr'        => ['class' => [], 'style' => [],'id' => []],
            'a'         => ['href' => [], 'target' => [], 'class' => [], 'style' => [],'id' => []],
            'i'         => ['class' => [], 'style' => [],'id' => []],
            'em'        => ['class' => [], 'style' => [],'id' => []],
            'strong'    => ['class' => [], 'style' => [],'id' => []],
            'b'         => ['class' => [], 'style' => [],'id' => []],
            'span'      => ['class' => [], 'style' => [],'id' => []],
            'div'       => ['class' => [], 'style' => [],'id' => []],
            'h1'        => ['class' => [], 'style' => [],'id' => []],
            'h2'        => ['class' => [], 'style' => [],'id' => []],
            'h3'        => ['class' => [], 'style' => [],'id' => []],
            'h4'        => ['class' => [], 'style' => [],'id' => []],
            'h5'        => ['class' => [], 'style' => [],'id' => []],
            'h6'        => ['class' => [], 'style' => [],'id' => []],
	        'ul'        => ['class' => [], 'style' => [],'id' => []],
            'ol'        => ['class' => [], 'style' => [],'id' => []],
            'li'        => ['class' => [], 'style' => [],'id' => []],
	        'table'     => ['class' => [], 'style' => [],'id' => []],
	        'tr'        => ['class' => [], 'style' => [],'id' => []],
	        'td'        => ['class' => [], 'style' => [],'id' => []],
	        'th'        => ['class' => [], 'style' => [],'id' => []],
	        'thead'     => ['class' => [], 'style' => [],'id' => []],
	        'tbody'     => ['class' => [], 'style' => [],'id' => []],
        ];

        #region General views
        public static function partial($view, $model = null)
        {
            ob_start();
            $dir = wapf_get_setting('path') . 'views/' . $view;
            include $dir . '.php';
            echo ob_get_clean();
        }

        public static function view($view, $model = null): string {
            ob_start();

            $dir = wapf_get_setting('path') . 'views/' . $view;
            include $dir . '.php';

            return ob_get_clean();
        }

        #endregion

        #region Admin Functions
	    public static function admin_choice_option_extra_input( $input ): string {

        	$html = '';
            $default = '';
            if( isset( $input['default'] ) )
                $default = ' rv-default="choice.options.' . esc_attr( $input['key'] ) . '" data-default="' . esc_attr( $input['default'] ) . '" ';
            $attrs = '';
            if( !empty( $input['attrs'] ) && is_array( $input['attrs'] ) ) 
                $attrs = ' ' . Util::array_to_attributes( $input['attrs'] ) . ' ';

                        	switch($input['type']) {
		        case 'text':
			        $html = '<input ' . ( $attrs . $default ) . ' type="text" rv-on-change="onChange"
                            placeholder="' . (isset($input['placeholder']) ? esc_attr($input['placeholder']) : '' ). '"
                            rv-value="choice.options.' . esc_attr($input['key']) . '" />';
		        break;
		        case 'number':
			        $html = '<input ' . ( $attrs . $default ) . ' type="number" rv-on-change="onChange"
                            placeholder="' . (isset($input['placeholder']) ? esc_attr($input['placeholder']) : '' ). '"
                            rv-value="choice.options.' . esc_attr($input['key']) . '" />';
			        break;
                case 'textarea':
                    $html = '<textarea style="resize:none;height:64px" ' . ( $attrs . $default ) . ' rv-on-change="onChange" placeholder="' . (isset($input['placeholder']) ? esc_attr($input['placeholder']) : '' ). '" rv-value="choice.options.' . esc_attr($input['key']) . '"></textarea>';
                    break;
                case 'true-false':
		        case 'checkbox':
			        $html = '<input ' . ( $attrs . $default ) . ' rv-on-change="onChange" rv-checked="choice.options.' . esc_attr($input['key']) . '" type="checkbox" />';
			        break;
        	}

        	return $html;

	    }

                public static function settings( $settings = [], $tab = 'general' )
        {

            foreach ( $settings as $field_type => $options ) {

                echo '<div rv-if="field.type | eq \'' . $field_type . '\'" class="wapf_field__options">';

                foreach ( $options as $option ) {
                    if ( ! empty( $option ) && isset( $option[ 'type' ] ) ) {
                        $g = $option[ 'tab' ] ?? 'general';
                        if( $g === $tab )
                            Html::setting( array_merge( $option, ['field_type' => $field_type ] ) );
                    }
                }

                                echo '</div>';
            }
        }

        public static function setting( $model = [] ) {

            if(!isset($model['type']))
                return;

			$show_if = '';

			if(!empty($model['show_if'])) {
				$show_if = 'field.' . esc_attr($model['show_if']);
			}

            			echo sprintf(
				'<div %s class="wapf-field__setting" data-setting="%s"><div class="wapf-setting__label"><label>%s</label>%s</div>',
				empty($show_if) ? '' : 'rv-show="'.$show_if .'"',
                $model[ 'id' ] ?? '',
				__($model['label'],'sw-wapf'),
				isset($model['description']) ? '<p class="wapf-description">' . $model['description'] . '</p>' : ''
			);

			echo '<div class="wapf-setting__input">';

	        ob_start();

	        $view = apply_filters(
		        'wapf/admin_setting_template_path',
		        wapf_get_setting('path') . 'views/admin/settings/' . $model['type'] .'.php',
		        $model['type']
	        );

            include $view;

            echo ob_get_clean();

            echo '</div></div>';
        }

        public static function admin_field($field = [], $type = 'wapf_product') {
            ob_start();
            $path = wapf_get_setting('path') . 'views/admin/field.php';
            include $path;
            echo ob_get_clean();
        }

        public static function wp_list_table($view_name,$model,$list) {
            ob_start();
            $path = wapf_get_setting('path') . 'views/admin/'.$view_name.'.php';
            include $path;
            echo ob_get_clean();
        }

        public static function help_modal($data) {
        	$model = array_merge( [
		        'content'   => '',
		        'title'     => '',
		        'button'    => null,
		        'icon'      => false,
		        'id'        => 'wapf--' . uniqid()
	        ], $data);

	        ob_start();
	        $path = wapf_get_setting('path') . 'views/admin/help-modal.php';
	        include $path;
	        echo ob_get_clean();
        }
        #endregion

        #region Product-related Functions
        public static function product_totals( $product, $field_groups = [] ) {

            $data = apply_filters( 'wapf/html/product_totals/data', [
            	'data-product-id'           => $product->get_id(),
	            'data-product-type'         => $product->get_type() === 'variation' ? 'variable' : $product->get_type(),
                'data-product-price'        => apply_filters( 'wapf/pricing/product', $product->get_price(), $product ), 
                'data-tax'           => Helper::get_tax_multiplier( $product ), 
            ], $product, $field_groups );

            $data_output = Enumerable::from( $data )->join( function( $value, $key ) {
            	return esc_html( $key ) . '="' . esc_html( $value ) . '"';
            },' ' );

            $hide_at_start = in_array( $product->get_type(), ['variable','variation','variable-subscription','subscription_variation'] );
            $totals_html = '<div class="wapf-product-totals" ' . $data_output . ' ' . ( $hide_at_start ? 'style="display:none"' : '' ) . '>';

	        $show_inner = Cache::get( 'pricing_' . $product->get_id() ) === true;
	        $show_inner = apply_filters( 'wapf/pricing_summary', $show_inner ? get_option('wapf_pricing_summary', 'lines') : 'hide', $product );

	        if( $show_inner !== 'hide' ) {
		        ob_start();
		        $path = wapf_get_setting('path') . 'views/frontend/product-totals.php';
		        include $path;
		        $totals_html .= ob_get_clean();
			}

            $totals_html .= '</div>';

            echo apply_filters( 'wapf/html/product_totals', $totals_html, $product );

        }
        #endregion

        #region Field Groups and Fields

	    public static function display_field_groups( $field_groups, $product, $cart_item_fields = [] ) {

		    ob_start();

		    echo '<div class="wapf" id="wapf_' . $product->get_id() . '">';

                do_action( 'wapf_before_wrapper', $product );

                echo '<div class="wapf-wrapper">';

                $group_ids = [];

                foreach ( $field_groups as $field_group ) {

                    $group_ids[] = $field_group->id;
                    echo self::field_group( $product, $field_group, $cart_item_fields );

                }

                echo '<input type="hidden" value="' . implode( ',', $group_ids ) . '" name="wapf_field_groups"/>';

                if( ! empty( $cart_item_fields ) ) {
                    echo '<input type="hidden" name="_wapf_edit" value="' . esc_attr( $_GET['_edit'] ) . '" />';
                }

                                echo '</div>';

                do_action( 'wapf_before_product_totals', $product );

                self::product_totals( $product, $field_groups );

                echo '<div class="tooltip-container" role="alertdialog" id="tooltipText" aria-hidden="true" aria-live="polite"></div>';

                                do_action( 'wapf_after_product_totals', $product );

		    echo '</div>';

		    return ob_get_clean();
	    }

        public static function field_group( $product, FieldGroup $field_group, $cart_item_fields = [] ) {

            if( empty( $field_group->fields ) && empty( $field_group->variables ) ) {
                return '';
            }

            ob_start();

                        include wapf_get_setting( 'path' ) . 'views/frontend/field-group.php';

                        return ob_get_clean();

        }

        public static function field_defaults( Field $field, $cart_item_field ): array {

            $value = [];

            if( empty( $cart_item_field ) ) {

                                if(  $field->is( 'multi_choice' ) ) {

                    if( ! empty( $field->options['choices'] ) ) {
                        foreach( $field->options['choices'] as $choice ) {
                            if( isset( $choice['selected'] ) && $choice['selected'] ) {
                                $value[] = $choice['slug'];
                            }
                        }
                    }

                } else if( $field->is( 'qty_selector' ) ) {

                    if( ! empty( $field->options['choices'] ) ) {
                        foreach( $field->options['choices'] as $choice ) {
                            $value[] = isset( $choice['options'] ) && isset( $choice['options']['default'] )  ? intval( $choice['options']['default'] ) : 0;
                        }
                    }

                }
                else {
                    $value[] = isset( $field->options['default'] ) ? esc_html( $field->options['default'] ) : '';
                }

            }
            else { 

                if( $field->is( 'qty_selector' ) ) {

                    if( ! empty( $field->options['choices'] ) ) {
                        foreach( $field->options['choices'] as $choice ) {
                            $v = Enumerable::from( $cart_item_field['values'] )->firstOrDefault( function($x) use ($choice) { return $x['slug'] == $choice['slug']; } );
                            $value[] = $v ? $v['label'] : 0;
                        }
                    }

                } else {

                    foreach( $cart_item_field['values'] as $v ) {
                        $value[] = isset( $v['slug'] ) && empty( $v['use_label'] ) ? $v['slug'] : $v['label'];
                    }

                }

            }

	        return $value;

                    }

        public static function field( $product, Field $field, $fieldgroup_id, $cart_item_field = [] ) {

        	$field_attributes = self::field_attributes( $product, $field, $fieldgroup_id );
        	$field_attributes_html = Util::array_to_attributes( $field_attributes );

        	$data       = self::field_data( $product, $field, $fieldgroup_id );
        	$defaults   = self::field_defaults( $field, $cart_item_field );

        	$is_edit    = !empty( $cart_item_field );
            $model      = [
                'product'               => $product,
                'field'                 => $field,
                'default'               => $defaults,
                'is_edit'               => $is_edit,
                'field_attributes'      => $field_attributes_html,
                'raw_field_attributes'  => $field_attributes,
                'data'                  => $data
            ];

                        $model = apply_filters('wapf/field_template_model', $model, $field, $fieldgroup_id, $product, $cart_item_field );

                        $file_name = $field->type;

            if( $field->type === 'p' ) {
                $file_name = 'content';
            }

            $view = apply_filters(
            	'wapf/field_template_path',
	            wapf_get_setting('path') . 'views/frontend/fields/' . $file_name . '.php',
	            $field
            );

            ob_start();

            include $view;

            return ob_get_clean();

        }

        private static function field_data( $product, $field, $fieldgroup_id ) {

                        $data = [];

                    	if( $field->type === 'date' ) {
        		global $wp_locale;
        		if( $wp_locale ) {
			        $data = [
						'months'        => array_values( $wp_locale->month ),
						'monthsShort'   => array_values( $wp_locale->month_abbrev ),
						'days'          => array_values( $wp_locale->weekday ),
				        'daysShort'     => array_values( $wp_locale->weekday_initial )
			        ];
		        }

	        } else if ( $field->type === 'select' || $field->type === 'products' ) {
                $data[ 'i18n_choose_option'] = __( 'Choose an option','sw-wapf');
            }

                    	return $data;

                    }

        public static function field_description_tooltip(Field $field) {
            if( empty( $field->description ) )
                return apply_filters('wapf/html/field_description', '', $field);

            $html = '<span tabindex="0" class="wapf-tt-wrap wapf-tt-icon" data-tip="'. Util::to_html_attribute_string( $field->description, true ) . '"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 512 512"><path d="M504 256c0 136.997-111.043 248-248 248S8 392.997 8 256C8 119.083 119.043 8 256 8s248 111.083 248 248zM262.655 90c-54.497 0-89.255 22.957-116.549 63.758-3.536 5.286-2.353 12.415 2.715 16.258l34.699 26.31c5.205 3.947 12.621 3.008 16.665-2.122 17.864-22.658 30.113-35.797 57.303-35.797 20.429 0 45.698 13.148 45.698 32.958 0 14.976-12.363 22.667-32.534 33.976C247.128 238.528 216 254.941 216 296v4c0 6.627 5.373 12 12 12h56c6.627 0 12-5.373 12-12v-1.333c0-28.462 83.186-29.647 83.186-106.667 0-58.002-60.165-102-116.531-102zM256 338c-25.365 0-46 20.635-46 46 0 25.364 20.635 46 46 46s46-20.636 46-46c0-25.365-20.635-46-46-46z"/></svg></span>';

            return apply_filters('wapf/html/field_description', $html, $field);
        }

        public static function field_description(Field $field) {

        	if(empty($field->description))
        		return '';

            $field_description = '<div class="wapf-field-description">'.wp_kses($field->description,self::$minimal_allowed_html).'</div>';

            return apply_filters('wapf/html/field_description', $field_description, $field);
        }

        public static function section_container_classes( Field $field ): string {

            	        $extra_classes = apply_filters( 'wapf/section_classes/' . $field->id, [] ); 
            $extra_classes = apply_filters( 'wapf/html/section_container_classes', $extra_classes, $field ); 

	        $classes = [ 'wapf-section', 'field-' . $field->id ];

	        if( ! empty( $field->class ) ) {
                $classes[] = $field->class;
            }

	        if( $field->has_conditionals() ) {
                $classes[] = 'wapf-hide has-conditions';
            }

	        $clone_type = $field->get_clone_type();
	        if( ! empty( $clone_type ) ) {
	        	$classes[] = 'has-repeat';
	        }

            $parent_clone = $field->get_parent_clone_type();
            if( ! empty( $parent_clone ) ) {
                $classes[] = 'has-parent-repeat';
            }

	        return implode( ' ', array_merge( array_map( 'sanitize_html_class', $extra_classes ), $classes ) );

                    }

        public static function field_container_classes( Field $field, $product ): string {

            $extra_classes = apply_filters( 'wapf/html/field_container_classes', [], $field );
            $classes = [ 'wapf-field-container', 'wapf-field-' . $field->type, 'field-' . $field->id ];

            if( ! empty( $field->class ) ) {
                $classes[] = $field->class;
            }

            if( $field->required ) {
                $classes[] = 'wapf-required';
            }

            if( $field->has_conditionals() ) {
                $classes[] = 'wapf-hide has-conditions';
            }

            if( ( $field->type === 'select' && $field->pricing_enabled() ) || ( ! $field->is( 'multi_choice' ) && $field->pricing_enabled() ) ) {
                $classes[] = 'has-pricing';
	            Cache::set( 'pricing_' . $product->get_id(), true );
            } else if( $field->type === 'calc' ) {
                if( isset( $field->options['calc_type'] ) && $field->options['calc_type'] === 'cost' ) {
                    $classes[] = 'has-pricing';
                    Cache::set( 'pricing_' . $product->get_id(), true );
                }
            }

	        if( isset( $field->options['min_choices'] ) || isset( $field->options['max_choices'] ) ) {
                $classes[] = 'has-minmax';
            }

            	        $clone_type = $field->get_clone_type();
	        if( ! empty( $clone_type ) ) {
	        	$classes[] = 'has-repeat';
	        }

            $parent_clone = $field->get_parent_clone_type();
            if( ! empty( $parent_clone ) ) {
                $classes[] = 'has-parent-repeat';
            }

            return implode( ' ', array_merge( array_map( 'sanitize_html_class', $extra_classes ), $classes ) );

                    }

                public static function field_container_attributes( Field $field, array $attributes = [] ): string {

                        $attributes[ 'for' ] = $field->id;

	        $clone_type = $field->get_clone_type();

	        if( ! empty( $clone_type ) ) {
		        $attributes['data-clone-txt'] = $field->get_clone_label();
		        if( $clone_type === 'qty' ) {
		        	$attributes['data-qty-based'] = '';
		        }
	        }

            	        if( isset( $field->options['min_choices'] ) )
		        $attributes['data-minc'] = intval( $field->options['min_choices'] );
            if( isset( $field->options['max_choices'] ) )
                $attributes['data-maxc'] = intval( $field->options['max_choices'] );

            $attributes = apply_filters( 'wapf/html/field_container_attributes', $attributes, $field );

            return Enumerable::from( $attributes )->join( function( $value, $key ) {
                if( $value )
                    return sanitize_text_field( $key ) . '="' . esc_attr( $value ) .'"';
                else return sanitize_text_field( $key );
            },' ');

        }

        public static function field_label(Field $field, $product, $show_required_symbol = true): string {

            $aria_label = !empty( $field->meta['label_aria'] );
            $label_content = '<span>' . wp_kses($field->label, self::$minimal_allowed_html) .'</span>';

            if($show_required_symbol && $field->required)
                $label_content .= ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce' ) . '">*</abbr>';

            if( $field->type !== 'true-false' && !$field->is( 'multi_choice' ) && $field->pricing_enabled() )
                $label_content .= ' ' . self::frontend_field_pricing_hint( $field, $product );

            $label_content = apply_filters('wapf/html/field_label', $label_content, $field, $product);
            $label = '<label' . ( $aria_label ? ( ' for="'  . ( 'wapf-' . $product->get_id() . '-' . $field->id ) . '"' ) : '' ) . '>';

                       return $label . $label_content . '</label>';

        }

                public static function get_option_classes_and_attributes( $field, $product, $option, $default_value, $multiple_choice = false, $for_swatch = false, $is_qty_field = false, $classes = [] ): array {

                        $id                 = 'wapf-' . $product->get_id() . '-' . $field->id .'-' . $option['slug'];
            $attributes         = [
                'id'            => $id,
                'name'          => sprintf('wapf[field_%s%s]' . ($multiple_choice ? '[]' : ''), $field->id, $field->is( 'qty_selector' ) ? ( '_' . $option['slug'] ) : ''),
                'class'         => 'wapf-input input-' . $field->id,
                'data-field-id' => $field->id,
            ];

                        if( ! $is_qty_field ) { 

                                $attributes['value'] = $option['slug'];
                $attributes['data-wapf-label'] = esc_html( $option['label'] );

                if( ! empty( $option['disabled'] ) ) {
                    $classes[] = 'wapf-disabled';
                    $attributes['disabled'] = 'disabled';
                    $attributes['data-disabled'] = '1';
                }

            } else {

                                $attributes['class']    .= ' is-qty ' . 'input-' . $field->id . '_' . $option['slug'] ;
                $attributes['min']      = 0;
                $attributes['max']      = 999999;

                                if( ! empty ( $option['options'] ) ) {

                                        if( isset( $option['options']['min'] ) && $option['options']['min'] != '' ) {
                        $attributes['min'] = intval( $option['options']['min'] );
                    }

                                        if( isset( $option['options']['max'] ) && $option['options']['max'] != '' ) {
                        $attributes['max'] = intval( $option[ 'options' ][ 'max' ] );
                    }

                                    }

                if( isset( $field->options['max_choices'] ) && $field->options['max_choices'] != '' ) {
                    $attributes['max'] = min( $attributes['max'], intval( $field->options['max_choices'] ) );
                }

                            }

            if( $field->required ) {
                $attributes[ 'required' ] = '';
            }

            if( ! $is_qty_field && in_array( $option['slug'], $default_value ) ) {
                $classes[] = 'wapf-checked';
                $attributes['checked'] = '';
            }

            if( isset( $option['pricing_type'] ) && $option['pricing_type'] !== 'none' ) {

                                $classes[]                          = 'has-pricing';
                $attributes['data-wapf-pricetype']  = $option['pricing_type'];
                $attributes['data-wapf-price']      = $option['pricing_type'] === 'fx' ? $option['pricing_amount'] : Helper::addon_value_for_calculations( $product, $option['pricing_amount'], $option['pricing_type'] );

                Cache::set( 'pricing_' . $product->get_id(), true );

                            }

                        if( $for_swatch ) {

                                $needs_large_image = isset( $field->options['large_image'] ) && $field->options['large_image'] == 1;

                if( ( isset( $field->options['label_pos'] ) && $field->options['label_pos'] === 'tooltip' ) || $needs_large_image ) {
                    $classes[] = 'wapf-tt-wrap';
                }
            }

                        $attributes = apply_filters('wapf/html/option_attributes', $attributes, $field, $product, $option);
            $classes    = apply_filters('wapf/html/option_wrapper_classes', $classes, $field, $product, $option );

                        return [
                'id'        => $id,
                'classes'   => $classes,
                'atts'      => $attributes
            ];

                    }

                public static function image_swatch_wrapper_attributes( $option, Field $field ): array {

            $attributes = [];

            if( isset( $field->options['large_image'] ) && $field->options['large_image'] == 1 && ! empty( $option['attachment'] ) ) {
                $attachment_id = intval( $option['attachment'] );
                $full_src = wp_get_attachment_image_src( $attachment_id, 'full' );

                $attributes['data-zoom-url'] = $full_src[0];
            }

            else if( isset( $field->options['label_pos'] ) && $field->options['label_pos'] === 'tooltip')
                $attributes['data-dir'] = 't';

            return $attributes;

        }

        public static function select_option_attributes( $option, $field, $product, $is_edit_mode, $default_value ) {

        	$attributes = [
		        'value' => $option['slug'],
		        'data-wapf-label' => esc_html( $option['label'] )
	        ];

	        $is_checked = $is_edit_mode ? in_array( $option['slug'], $default_value ) : isset( $option['selected'] ) && $option['selected'] === true;
	        if( $is_checked )
		        $attributes['selected'] = '';

        	if( isset($option['pricing_type']) && $option['pricing_type'] !== 'none' ) {

        		Cache::set( 'pricing_' . $product->get_id(), true );

		        $attributes['data-wapf-pricetype'] = $option['pricing_type'];
                $attributes['data-wapf-price'] = $option['pricing_type'] === 'fx' ? $option['pricing_amount'] : Helper::addon_value_for_calculations( $product, $option['pricing_amount'], $option['pricing_type'] );
		        if( $option['pricing_type'] === 'fx' && Util::show_pricing_hints() ) {
                    $attributes['data-fx-hint'] = esc_html(Helper::format_pricing_hint( 'fx', '', $product, 'shop', $field, $option ) );
		        }

	        }

            if( ! empty( $option['disabled'] ) ) {
                $attributes['disabled'] = '';
            }

            return apply_filters('wapf/html/option_attributes', $attributes, $field, $product, $option);

        }

        private static function field_attributes($product,Field $field, $field_group_id) {

                    	$field_attributes = [
		        'data-field-id' => $field->id,
                'id' => 'wapf-' . $product->get_id() . '-' . $field->id
	        ];

	        if( $field->required ) {
                $field_attributes[ 'required' ] = '';
            }

            	        if( ! $field->is_category( 'content' ) ) {

		        $extra_classes = apply_filters( 'wapf/html/field_classes', [], $field );
		        $classes = [ 'wapf-input', 'input-' . $field->id ];

		        $field_attributes['name'] = 'wapf[field_'.$field->id.']';

		        if ( $field->type !== 'select' ) {
			        if ( $field->pricing_enabled() ) {
				        $field_attributes['data-wapf-price'] = $field->pricing->type === 'fx' ?
					        $field->pricing->amount :
					        Helper::addon_value_for_calculations( $product, $field->pricing->amount, $field->pricing->type );
				        $field_attributes['data-wapf-pricetype'] = $field->pricing->type;
			        }
		        }

		        switch( $field->type ) {
			        case 'date':
				        $field_attributes['data-df'] = get_option('wapf_date_format','mm-dd-yyyy');
						break;
			        case 'true-false':
				        $field_attributes['data-false-label'] = isset($field->options['label_false']) ? $field->options['label_false'] : 'false';
				        $field_attributes['data-true-label'] = isset($field->options['label_true']) ? $field->options['label_true'] : 'true';
				        break;
			        case 'file':
			        	if( ! File_Upload::is_ajax_upload() ) {

					        $field_attributes['name'] = $field_attributes['name'] . '[]';

					        if(!empty($field->options['multiple']))
						        $field_attributes['multiple'] = '';
					        if(isset($field->options['accept'])) {
						        $accept = '.' . str_replace( [',', '|'], ',.' ,$field->get_option('accept'));
						        $field_attributes['accept'] = $accept;
					        }
				        }

			        	break;
		        }

		        $field_attributes['class'] = implode(' ', array_merge( empty( $extra_classes ) ? [] : array_map( 'sanitize_html_class', $extra_classes ), $classes ) );

		        if ( isset( $field->options['placeholder'] ) ) {
			        $field_attributes['placeholder'] = $field->options['placeholder'];
		        }

		        if ( isset( $field->options['minimum'] ) ) {
			        $field_attributes['min'] = $field->options['minimum'];
		        }

		        if ( isset( $field->options['maximum'] ) ) {
			        $field_attributes['max'] = $field->options['maximum'];
		        }

		        if(isset($field->options['number_type']) && $field->options['number_type'] !== 'int')
		        	$field_attributes['step'] = $field->options['number_type'];

		        if ( !empty( $field->options['minlength'] ) ) {
			        $field_attributes['minlength'] = intval($field->options['minlength']);
		        }
		        if ( !empty( $field->options['maxlength'] ) ) {
			        $field_attributes['maxlength'] = intval($field->options['maxlength']);
		        }
		        if ( !empty( $field->options['pattern'] ) ) {
			        $field_attributes['pattern'] = $field->options['pattern'];
		        }

	        }

	        return apply_filters( 'wapf/html/field_attributes', $field_attributes, $field, $product, $field_group_id );

                    }

	    public static function swatch_tooltip($option, Field $field, $product): string {

            $pricing_hint = self::frontend_option_pricing_hint($option, $field, $product);

            return sprintf(
                '<div aria-hidden="true" style="display: none" class="wapf-tt-content"><span class="wapf-ttp"><span>%s%s</span></span></div>',
                esc_html($option['label']),
                empty($pricing_hint) ? '' : '  ' . $pricing_hint
            );

	    }

	    public static function swatch_label(Field $field,$image_swatch_option,$product, $default = 'default'): string {

            $label_position = $field->options[ 'label_pos' ] ?? $default;

            switch($label_position) {
                case 'tooltip' : return self::swatch_tooltip($image_swatch_option, $field, $product);
                case 'hide' : return '';
                default: return '<div class="wapf-swatch-label">' . esc_html($image_swatch_option['label']) . ' ' . Html::frontend_option_pricing_hint( $image_swatch_option, $field, $product ) . '</div>';
            }



	    }

        public static function product_pricing_hint( $product, $choice, $raw = false ) {

            if( ! Util::show_pricing_hints() )
                return '';

                        $format = Util::pricing_hint_format();

                        if( ! $raw ) { 
                $price_html = str_replace( '{x}', $choice[ 'pricing_type' ] === 'none' ? wc_price( 0 ) : $product->get_price_html(), $format );
                return '<span class="wapf-pricing-hint">' . $price_html . '</span>';
            }

                        $args           = Woocommerce_Service::get_price_display_options();
            $has_pricing    = $choice[ 'pricing_type' ] !== 'none';
            $product_price  = $has_pricing ? (float) $product->get_price() : 0;
            $negative       = $product_price < 0;

            $price  = apply_filters( 'formatted_woocommerce_price', number_format( $product_price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'] ), $product_price, $args['decimals'], $args['decimal_separator'], $args['thousand_separator'], $has_pricing ? $product->get_price() : 0 );

            if ( $args['trim_zeroes'] > 0 && $args['decimals'] > 0 ) {
                $price = wc_trim_zeros( $product_price );
            }

                        $formatted_price = ( $negative ? '-' : '' ) . sprintf( $args['price_format'],  get_woocommerce_currency_symbol( $args['currency'] ), $price );

                        if ( $args['ex_tax_label'] && wc_tax_enabled() ) {
                $formatted_price .= ' ' . WC()->countries->ex_tax_or_vat();
            }

                        return str_replace( '{x}', html_entity_decode( $formatted_price ), $format );

        }

	    public static function frontend_option_pricing_hint( $option, $field, $product) {

		    if( ! Util::show_pricing_hints() || empty( $option['pricing_type'] ) || $option['pricing_type'] === 'none' )
		    	return '';

            $hint = Helper::format_pricing_hint( $option['pricing_type'], $option['pricing_type'] === 'fx' ? '' : $option['pricing_amount'], $product, 'shop', $field, $option );

		    return '<span class="wapf-pricing-hint">' . $hint . '</span>';

        }

	    public static function frontend_field_pricing_hint( Field $field, $product ): string {

		    if( ! $field->pricing_enabled() || ! Util::show_pricing_hints() ) {
                return '';
            }

		    return '<span class="wapf-pricing-hint">'. Helper::format_pricing_hint( $field->pricing->type, $field->pricing->type === 'fx' ? '' : $field->pricing->amount, $product,'shop', $field ) . '</span>';
	    }

	    #endregion

	    public static function get_swatch_image_html( $field, $product, $choice ): string {

            $desired_size = apply_filters( 'wapf/html/image_swatch_size', 'medium', $field, $product, $choice );

        	if( empty( $choice['image'] ) && empty( $choice['attachment'] ) ) {
		        return wc_placeholder_img( $desired_size );
	        }

        	if( ! empty( $choice['attachment'] ) ) {

        		$attachment_id = intval( $choice['attachment'] );

		        return wp_get_attachment_image(
			        $attachment_id,
                    $desired_size,
			        false
		        );

	        }

		    return '<img autocomplete="off" src="'. esc_url( $choice['image'] ) .'"/>';

        }

                public static function quantity_input_html( $label, $value, $attributes, $use_plusminus = false ) {

                        $unique_id = uniqid();
            $classes = 'wapf-qty' . ( $use_plusminus ? ' apf-plusmin' : '' );

                        $html = '<div class="' . $classes . '"><label class="screen-reader-text" for="quantity_' . $unique_id . '">' . esc_html( $label ) . ' ' . __( 'quantity','sw-wapf' ) . '</label>';

                        if( $use_plusminus ) {
                $html .='<button type="button" tabindex="-1" aria-label="' . __( 'Reduce', 'sw-wapf' ) . '" class="button apf-minus"><svg xmlns="http://www.w3.org/2000/svg" width="1rem" height="1rem" viewBox="0 0 24 24"><path d="M19 13H5v-2h14v2Z"/></svg></button>';
            }

                        $html .= '<input data-no-zero="1" step="1" type="number" value="' . esc_attr( $value ) . '" ' . Util::array_to_attributes( $attributes ) . ' />';

            if( $use_plusminus ) {
               $html .= '<button type="button" tabindex="-1" aria-label="' . __( 'Increase', 'sw-wapf' ) . '" class="button apf-plus"><svg xmlns="http://www.w3.org/2000/svg" width="1rem" height="1rem" viewBox="0 0 24 24"><path d="M11 4h2v7h7v2h-7v7h-2v-7H4v-2h7V4z"/></svg></button>';
            }

                        $html .= '</div>';

                        return $html;

                    }

        public static function get_cart_info( $card, $key, Field $field ): string {

            $info_piece = $field->options[ $key ] ?? 'none';

            if( $info_piece === 'none' || empty( $card[ $info_piece ] ) ) {
                return '';
            }

            $html = '<div class="wapf-card-info wapf-card-' . $info_piece . '">';

            if( $info_piece === 'link' ) {
                $html .= '<a href="' . esc_url( $card['link'] ) . '" target="_blank">' . __( 'Details' ) . '</a>';
            } else $html .= $card[ $info_piece ];

            return $html . '</div>';

                    }
    }
}