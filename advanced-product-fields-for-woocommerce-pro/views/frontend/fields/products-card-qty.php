<?php

/** @var array $model */

use SW_WAPF_PRO\Includes\Classes\Html;
use \SW_WAPF_PRO\Includes\Classes\Util;
use \SW_WAPF_PRO\Includes\Classes\Helper;

$cols           = isset( $model['field']->options['items_per_row'] ) ? intval( $model['field']->options['items_per_row'] ) : 2;
$cols_tablet    = isset( $model['field']->options['items_per_row_tablet'] ) ? intval( $model['field']->options['items_per_row_tablet'] ) : 1;
$cols_mobile    = isset( $model['field']->options['items_per_row_mobile'] ) ? intval( $model['field']->options['items_per_row_mobile'] ) : 1;
$incl_img       = ! empty( $model['field']->options['incl_img'] );
$incl_desc      = ! empty( $model['field']->options['incl_desc'] );
$use_plusminus  = isset( $model['field']->options['display'] ) && $model['field']->options['display'] === 'plus_min';

if( ! empty( $model['data']['product_choices'] ) ) {

    echo '<style>.field-' . $model['field']->id .'{ --wapf-cols:'.$cols.';--wapf-cols-t:'.$cols_tablet.';--wapf-cols-m:'.$cols_mobile .';}</style>';

    echo '<div class="wapf-card-wrap">';
    $ids = [];
    
    foreach ( $model['data']['product_choices'] as $i => $option ) {

        $class_atts         = Html::get_option_classes_and_attributes( $model['field'], $model['product'], $option, [], false, false, true, [] );
        $wrapper_attributes = Html::image_swatch_wrapper_attributes( $option, $model['field'] );
        $default            = is_array( $model['default'] ) && isset( $model['default'][$i] ) ?  $model['default'][$i] : 0;
        $ids[]              = $option['slug'];

        if( empty( $class_atts['atts']['disabled'] ) && ! $option['product']->is_in_stock() ) {
            $class_atts['classes'][] = 'wapf-disabled';
            $class_atts['atts']['disabled'] = 'disabled';
            $class_atts['atts']['data-disabled'] = '1';
            $default = 0;
        }
        
        ?>
        <div class="wapf-card is-qty-select <?php echo join( ' ', $class_atts['classes'] ) ?>" <?php echo Util::array_to_attributes( $wrapper_attributes ) ?> >
            <div class="wapf-card-inner">

                <?php if( $incl_img ) { ?>
                    <div class="wapf-card-img">
                        <?php echo Html::get_swatch_image_html( $model['field'], $model['product'], $option ) ?>
                    </div>
                <?php } ?>

                <div class="wapf-card-body">

                    <div class="wapf-card-row">

                        <div class="wapf-card-title">
                            <label for="<?php echo $class_atts['id'] ?>" style="display: none">
                                <?php echo $option['label'] ?? '' ?>
                            </label>
                            <span><?php echo $option['label'] ?? '' ?></span>
                        </div>

                        <?php echo Html::get_cart_info( $option, 'slot_1', $model['field'] ); ?>

                    </div>

                    <?php if( $incl_desc ) { ?>
                        <div class="wapf-card-row">
                            <div class="wapf-card-desc">
                                <?php echo Helper::shorten_text( strip_tags( $option['desc'] ?? '', [ 'em', 'b', 'strong', 'i', 'u', 'mark' ] ), 110 ) ?>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="wapf-card-row">

                        <div class="wapf-card-qty">
                            <?php echo Html::quantity_input_html( $option['label'], $default, $class_atts['atts'], $use_plusminus ) ?>
                        </div>

                        <?php
                        $slot_2 = Html::get_cart_info( $option, 'slot_2', $model['field'] );
                        $slot_3 = Html::get_cart_info( $option, 'slot_3', $model['field'] );

                        if( ! empty( $slot_2 ) || ! empty( $slot_3 ) ) {
                            ?>
                            <div class="wapf-card-row">

                                <?php echo $slot_2 ?>

                                <?php echo $slot_3 ?>

                            </div>
                        <?php } ?>

                    </div>

                </div>
            </div>
        </div>
        <?php
    }
    
    echo '<input type="hidden" class="wapf-tf-h" data-fid="'.$model['field']->id.'" value="' . join( ',', $ids ) . '" name="wapf[field_' . $model['field']->id . ']" />';

    echo '</div>';

}
