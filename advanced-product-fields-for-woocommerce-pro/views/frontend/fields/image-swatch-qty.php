<?php
/** @var array $model */

use SW_WAPF_PRO\Includes\Classes\Html;
use \SW_WAPF_PRO\Includes\Classes\Util;

$cols = isset( $model['field']->options['items_per_row'] ) ? intval( $model['field']->options['items_per_row'] ) : 3;
$cols_tablet = isset( $model['field']->options['items_per_row_tablet'] ) ? intval( $model['field']->options['items_per_row_tablet'] ) : 3;
$cols_mobile = isset( $model['field']->options['items_per_row_mobile'] ) ? intval( $model['field']->options['items_per_row_mobile'] ) : 3;
$use_plusminus = isset( $model['field']->options['display'] ) && $model['field']->options['display'] === 'plus_min';

if(!empty($model['field']->options['choices'])) {

    $label_pos = $model[ 'field' ]->options[ 'label_pos' ] ?? 'default';
    echo '<div class="wapf-image-swatch-wrapper wapf-swatch-wrapper " style="--wapf-cols:'.$cols.';--wapf-cols-t:'.$cols_tablet.';--wapf-cols-m:'.$cols_mobile.'">';
    echo '<input type="hidden" class="wapf-tf-h" data-fid="'.$model['field']->id.'" value="1" name="wapf[field_'.$model['field']->id.']" />';
    
    for( $i = 0; $i < count( $model['field']->options['choices'] ); $i++ ) {
        
        $option             = $model['field']->options['choices'][$i];
        $class_atts         = Html::get_option_classes_and_attributes( $model['field'], $model['product'], $option, [], false, false, true, [] );
        $wrapper_attributes = Html::image_swatch_wrapper_attributes( $option, $model['field'] );
        $default            = is_array( $model['default'] ) && isset( $model['default'][$i] ) ?  $model['default'][$i] : 0;
        $img_classes        = '';
        if( isset( $wrapper_attributes['data-dir'] ) ) $img_classes = 'wapf-tt-wrap';

       ?>
         <div class="wapf-swatch is-qty-select wapf-swatch--qty <?php echo join( ' ', $class_atts['classes'] ) ?>">
            <div class="qty-swatch-img-wrapper">
                <div class="qty-swatch-img <?php echo $img_classes; ?>"  <?php echo Util::array_to_attributes( $wrapper_attributes ); ?>>
                    <?php echo Html::get_swatch_image_html( $model['field'], $model['product'], $option ) ?>
                    <?php if( $label_pos === 'tooltip' ) echo Html::swatch_label( $model['field'], $option, $model['product'] ); ?>
                </div>
            </div>
            <div class="qty-swatch-inner">
                <?php echo Html::quantity_input_html( $option['label'], $default, $class_atts['atts'], $use_plusminus ) ?>
                <?php if( $label_pos === 'default' ) echo Html::swatch_label($model['field'], $option, $model['product']); ?>
            </div>
        </div>

    <?php } ?>

    </div>

<?php } ?>
