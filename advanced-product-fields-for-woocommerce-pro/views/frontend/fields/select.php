<?php
/** @var array $model */
use SW_WAPF_PRO\Includes\Classes\Enumerable;
use SW_WAPF_PRO\Includes\Classes\Html;
use SW_WAPF_PRO\Includes\Classes\Util;

?>

<select <?php echo $model['field_attributes']; ?>>
    <?php
        if(isset($model['field']->options['choices'])) {

            if(!$model['field']->required || ($model['field']->required && !Enumerable::from($model['field']->options['choices'])->any(function($x){
                return isset($x['selected']) && $x['selected'] === true;
            })))
                echo '<option value="">' . esc_html( $model['data']['i18n_choose_option'] ) . '</option>';

            foreach($model['field']->options['choices'] as $option) {

	            $attributes = Html::select_option_attributes( $option, $model['field'], $model['product'], $model['is_edit'], $model['default']);

                echo sprintf(
                    '<option %s>%s</option>',
                    Util::array_to_attributes( $attributes ),
                    esc_html($option['label']) .  ' ' . Html::frontend_option_pricing_hint( $option, $model['field'], $model['product'] )
                );
            }
        }
    ?>
</select>