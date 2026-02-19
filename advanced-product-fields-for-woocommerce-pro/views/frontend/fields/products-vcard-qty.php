<?php

/** @var array $model */

use SW_WAPF_PRO\Includes\Classes\Html;
use \SW_WAPF_PRO\Includes\Classes\Util;
use \SW_WAPF_PRO\Includes\Classes\Helper;

$cols           = isset( $model['field']->options['items_per_row']) ? intval($model['field']->options['items_per_row']) : 2;
$cols_tablet    = isset( $model['field']->options['items_per_row_tablet']) ? intval($model['field']->options['items_per_row_tablet']) : 1;
$cols_mobile    = isset( $model['field']->options['items_per_row_mobile']) ? intval($model['field']->options['items_per_row_mobile']) : 1;
$img_fit        = $model[ 'field' ]->options[ 'img_fit' ] ?? 'contain';
$incl_img       = ! empty( $model['field']->options['incl_img'] );
$incl_desc      = ! empty( $model['field']->options['incl_desc'] );
$use_plusminus  = isset( $model['field']->options['display'] ) && $model['field']->options['display'] === 'plus_min';

$get_info = function( $key, $option ) use( $model ) {
    
    if( empty( $model['field']->options[ 'incl_' . $key ] ) ) return false;
    if( empty( $option[$key] ) ) return false;

    return '<div class="wapf-card-info wapf-card-' . $key . '">' . $option[$key] . '</div>';
        
};

if( ! empty( $model['data']['product_choices'] ) ) {
    
    echo '<style>.field-' . $model['field']->id .'{ --wapf-cols:'.$cols.';--wapf-cols-t:'.$cols_tablet.';--wapf-cols-m:'.$cols_mobile .';--apf-img-fit:'.$img_fit.'}</style>';

    $ids = [];
    echo '<div class="wapf-card-wrap">';
    
    foreach ( $model['data']['product_choices'] as $i => $option ) {
        
        $class_atts         = Html::get_option_classes_and_attributes( $model['field'], $model['product'], $option, [], false, false, true, [] );
        $wrapper_attributes = Html::image_swatch_wrapper_attributes( $option, $model['field'] );
        $default            = is_array( $model['default'] ) && isset( $model['default'][$i] ) ? $model['default'][$i] : 0;
        $ids[]              = $option['slug'];

        if( empty( $class_atts['atts']['disabled'] ) && ! $option['product']->is_in_stock() ) {
            $class_atts['classes'][]                = 'wapf-disabled';
            $class_atts['atts']['disabled']         = 'disabled';
            $class_atts['atts']['data-disabled']    = '1';
            $default                                = 0;
        }

        $slot_1 = Html::get_cart_info( $option, 'slot_1', $model['field'] );
        
        ?>

        <div class="wapf-card is-qty-select wapf-card-vertical <?php echo join( ' ', $class_atts['classes'] ) ?>" <?php echo Util::array_to_attributes( $wrapper_attributes ) ?> >
            <div class="wapf-card-inner">
                
                <?php if( $incl_img ) { ?>
                    <div class="wapf-card-img">
                        <?php echo Html::get_swatch_image_html( $model['field'], $model['product'], $option ) ?>
                    </div>
                <?php } ?>
                
                <div class="wapf-card-body">

                    <div class="wapf-card-row">
                    
                        <div class="wapf-card-title" style="<?php echo empty( $slot_1 ) ? 'text-align:center' : '' ?>">
                            <label for="<?php echo $class_atts['id'] ?>" style="display: none">
                                <?php echo $option['label'] ?? '' ?>
                            </label>
                            <span><?php echo $option['label'] ?? '' ?></span>
                        </div>

                        <?php echo $slot_1 ?>

                    </div>
               
                    <?php if( $incl_desc ) { ?>
                    <div class="wapf-card-row">
                        <div class="wapf-card-desc">
                            <?php echo Helper::shorten_text( strip_tags( $option['desc'], [ 'em', 'b', 'strong', 'i', 'u', 'mark' ] ), 110 ) ?>
                        </div>
                    </div>
                    <?php } ?>
                    
                    <?php
                        $slot_2 = Html::get_cart_info( $option, 'slot_2', $model['field'] );
                        $slot_3 = Html::get_cart_info( $option, 'slot_3', $model['field'] );
                    ?>
                    <div class="wapf-card-row">

                        <div class="wapf-card-qty">
                            <?php echo Html::quantity_input_html( $option['label'], $default, $class_atts['atts'], $use_plusminus ) ?>
                        </div>

                        <?php 
                        if( empty( $slot_2 ) xor empty( $slot_3 ) ) {
                            echo $slot_2;
                            echo $slot_3;
                        }
                        ?>
                        
                    </div>

                    <?php
                        if( ! empty( $slot_2 ) && ! empty( $slot_3 ) ) {
                            echo '<div class="wapf-card-row">' . $slot_2 . $slot_3 . '</div>';
                        }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    echo '<input type="hidden" class="wapf-tf-h" data-fid="'.$model['field']->id.'" value="' . join( ',', $ids ) . '" name="wapf[field_' . $model['field']->id . ']" />';

    echo '</div>';

}
