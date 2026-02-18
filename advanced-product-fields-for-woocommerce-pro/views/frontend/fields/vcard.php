<?php

/** @var array $model */

use SW_WAPF_PRO\Includes\Classes\Html;
use \SW_WAPF_PRO\Includes\Classes\Util;
use \SW_WAPF_PRO\Includes\Classes\Helper;

$cols           = isset($model['field']->options['items_per_row']) ? intval($model['field']->options['items_per_row']) : 2;
$cols_tablet    = isset($model['field']->options['items_per_row_tablet']) ? intval($model['field']->options['items_per_row_tablet']) : 1;
$cols_mobile    = isset($model['field']->options['items_per_row_mobile']) ? intval($model['field']->options['items_per_row_mobile']) : 1;
$img_fit        = $model[ 'field' ]->options[ 'img_fit' ] ?? 'contain';

echo '<style>.field-' . $model['field']->id .'{--wapf-cols:'.$cols.';--wapf-cols-t:'.$cols_tablet.';--wapf-cols-m:'.$cols_mobile .';--apf-img-fit:' . $img_fit . ';}</style>';

echo '<div class="wapf-card-wrap">';
echo '<input type="hidden" class="wapf-tf-h" data-fid="'.$model['field']->id.'" value="0" name="wapf[field_'.$model['field']->id.'][]" />';

foreach ( $model['field']->options['choices'] as $option ) {

    $incl_img       = ! empty( $option['attachment'] );
    $incl_desc      = ! empty( $option['options']['desc'] );
    $incl_link      = ! empty( $option['options']['link'] );
    
    $class_atts = Html::get_option_classes_and_attributes( $model['field'], $model['product'], $option, $model['default'], true, true, false, [] );
    $wrapper_attributes = Html::image_swatch_wrapper_attributes( $option, $model['field'] );

    ?>
    <div class="wapf-card wapf-card-vertical <?php echo join( ' ', $class_atts['classes'] ) ?>" <?php echo Util::array_to_attributes( $wrapper_attributes ) ?> >
        <div class="wapf-card-inner">
            <input type="checkbox" <?php echo Util::array_to_attributes( $class_atts['atts'] ) ?>/>
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
                    <?php
                    $price_hint = Html::frontend_option_pricing_hint( $option, $model['field'], $model['product'] );
                    if( ! empty( $price_hint ) ) { ?>
                        <div class="wapf-card-info">
                            <?php echo $price_hint ?>
                        </div>
                    <?php } ?>
                </div>

                <?php if( $incl_desc || $incl_link ) { ?>
                    <?php if( $incl_desc ) { ?>
                        <div class="wapf-card-desc">
                            <?php echo Helper::shorten_text( strip_tags( $option['options']['desc'], [ 'em', 'b', 'strong', 'i', 'u', 'mark' ] ), 80 ) ?>
                        </div>
                    <?php } ?>
                    <?php if( $incl_link ) { ?>
                        <div class="wapf-card-link">
                            <a href="<?php echo esc_url( $option['options']['link'] ) ?>" target="_blank" class="button small">
                                <?php _e( 'Details' ) ?>
                            </a>
                        </div>
                    <?php } ?>
                <?php } ?>

            </div>
        </div>
    </div>
    <?php
}

echo '</div>';
    
