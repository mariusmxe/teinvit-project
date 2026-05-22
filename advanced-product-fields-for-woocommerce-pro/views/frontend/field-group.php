<?php
/** @var \SW_WAPF_PRO\Includes\Models\FieldGroup $field_group */

/** @var array $cart_item_fields */
/** @var WC_Product $product */

use \SW_WAPF_PRO\Includes\Classes\Html;
use \SW_WAPF_PRO\Includes\Classes\Helper;
use \SW_WAPF_PRO\Includes\Classes\Util;

$label_position         = $field_group->layout[ 'labels_position' ] ?? 'above';
$instructions_position  = $field_group->layout[ 'instructions_position' ] ?? 'field';
$mark_required          = isset($field_group->layout['mark_required']) && $field_group->layout['mark_required'];
$open_sections          = 0;
$section_buttons_stack  = []; // Fixed: Use stack to properly handle nested sections with buttons

//todo: why variables & gallery images in HTML, maybe better in script?
?>
<div
    class="wapf-field-group label-<?php echo $label_position ?>"
    data-group="<?php echo $field_group->id; ?>"
    data-variables="<?php echo Util::to_html_attribute_string($field_group->variables); ?>"
<?php if($field_group->has_gallery_image_rules()) { ?>
    data-wapf-st="<?php echo isset($field_group->layout['swap_type']) ? esc_attr($field_group->layout['swap_type']) : 'rules';?>"
    data-wapf-gi="<?php echo Util::to_html_attribute_string($field_group->get_gallery_image_rules()); ?>"
<?php } ?>
>
    <?php

    foreach( $field_group->fields as $field ) {
        
        $cart_item_field    = $cart_item_fields[ $field->id ] ?? [ 'value' => [], 'clones' => [] ];
        $width              = empty( $field->width ) ? 100 : floatval( $field->width );
        $has_width          = $width !== 100;
        $is_section_end     = $field->type === 'sectionend';
        $attributes         = [];
        
        if( ! empty( $field->conditionals ) ) {

            $attributes['data-wapf-d'] = Util::to_html_attribute_string( $field->conditionals );
            
        }

        if( ! $is_section_end && $field->type === 'section' ) {

            $open_sections++;
            if( $field->get_clone_type() === 'button' ) {
                // Fixed: Push section info to stack instead of overwriting global variables
                $section_buttons_stack[] = [
                    'level' => $open_sections,
                    'field' => $field,
                    'clones' => []
                ];
            }
            
            echo '<div data-field-id="' . $field->id . '" class="'.Html::section_container_classes($field).'" style="width: '.$width.'%;" ' . Html::field_container_attributes( $field, $attributes ) . '>';

            continue;
        }

        if( $is_section_end ) {

            // Fixed: Check if current section level has buttons using stack
            $current_button_section = end($section_buttons_stack);
            if( $current_button_section && $current_button_section['level'] === $open_sections) {
                Html::partial('frontend/repeater-button', [
                    'field'             => $current_button_section['field'],
                    'edit_cart_clones'  => Helper::edit_cart_clones( $current_button_section['clones'], 2 )
                ] );
                // Fixed: Remove current section from stack
                array_pop( $section_buttons_stack );
            }

            $open_sections--;

            echo '</div>';

            continue;
        }

        // Fixed: Add clones to the current section's clone collection (if any)
        $current_button_section = end($section_buttons_stack);
        if( $current_button_section && $current_button_section['level'] === $open_sections) {
            foreach ($cart_item_field['clones'] as $key => $clone) {
                if(! isset($section_buttons_stack[count($section_buttons_stack)-1]['clones'][ $key ])) {
                    $section_buttons_stack[count($section_buttons_stack)-1]['clones'][ $key ] = [];
                }
                $section_buttons_stack[count($section_buttons_stack)-1]['clones'][ $key ][] = $clone;
            }
        }

        echo '<div class="'. Html::field_container_classes( $field,$product ) . ($has_width ? ' has-width' : '') . '" style="width:'.$width.'%;" ' . Html::field_container_attributes( $field, $attributes ).' >';

        if( ! empty( $field->label ) && ( $label_position === 'above' || $label_position === 'left' ) ) {
            echo sprintf(
                '<div class="wapf-field-label">%s%s</div>%s',
                Html::field_label($field,$product,$mark_required),
                $instructions_position === 'tooltip' ? Html::field_description_tooltip( $field ) : '',
                $instructions_position === 'label' ? Html::field_description( $field ) : ''
            );
        }

        echo '<div class="wapf-field-input">'. Html::field( $product, $field, $field_group->id, $cart_item_field[ 'value' ] ) .'</div>';

        if( $instructions_position === 'field' )
            echo Html::field_description( $field );

        if( ! empty( $field->label ) && ( $label_position === 'below' || $label_position === 'right' ) ) {
            echo sprintf(
                '<div class="wapf-field-label">%s%s</div>%s',
                Html::field_label($field,$product,$mark_required),
                $instructions_position === 'tooltip' ? Html::field_description_tooltip( $field ) : '',
                $instructions_position === 'label' ? Html::field_description($field) : ''
            );
        }

        // We add the cloner here, right underneath the 'wapf-field-input' but still inside wapf-field-container so that it
        // also benefits from conditional settings.
        if( $field->get_clone_type() === 'button' ) {
            Html::partial('frontend/repeater-button', [
                'field'             => $field,
                'edit_cart_clones'  => Helper::edit_cart_clones( $cart_item_field['clones'] )
            ] );
        }

        echo '</div>'; // Closing the "wapf-field-container"

    }

    // Fixed: Handle any remaining open sections with buttons using stack
    for( $i=0; $i < $open_sections; $i++ ) {
        $current_button_section = end($section_buttons_stack);
        if( $current_button_section && $current_button_section['level'] === ($open_sections - $i)) {
            Html::partial('frontend/repeater-button', [
                'field'             => $current_button_section['field'],
                'edit_cart_clones'  => Helper::edit_cart_clones( $current_button_section['clones'], 2 )
            ] );
            array_pop($section_buttons_stack);
        }
        echo '</div>'; // closing sections that don't have an "section end" set on the backend.
    }
    ?>

</div>