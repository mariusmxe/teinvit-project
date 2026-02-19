<?php 
/* @var $model array */ 
$has_more_inputs = ! empty( $model['inputs'] );
?>

<div style="width: 100%;" class="wapf-option-table" rv-show="field.choices | isNotEmpty">
    <div class="wapf-options__header">
        <div class="wapf-option-cell wapf-option__sort"></div>
        <div class="wapf-option-cell wapf-option__image"><?php _e('Image','sw-wapf'); ?></div>
        <div class="wapf-option-less">
            <div class="wapf-option-cell wapf-option__flex"><?php _e('Label','sw-wapf'); ?></div>
            <?php if(isset($model['show_pricing_options']) && $model['show_pricing_options']) { ?>
                <div class="wapf-option-cell wapf-option__flex"><?php _e('Price type','sw-wapf'); ?></div>
                <div class="wapf-option-cell wapf-option__flex"><?php _e('Pricing','sw-wapf'); ?></div>
            <?php } ?>
            <?php if( $model['field_type'] !== 'image-swatch-qty') { ?>
                <div class="wapf-option-cell wapf-option__selected"><?php _e('Select', 'sw-wapf'); ?></div>
                <div class="wapf-option-cell wapf-option__disabled"><?php _e('Disable', 'sw-wapf'); ?></div>
            <?php } ?>
            <?php if( $has_more_inputs ) { ?>
                <div class="wapf-option-cell wapf-option-moreless""></div>
            <?php } ?>
        </div>
        <div class="wapf-option-more wapf-flex" style="display: none">
            <?php if( $has_more_inputs ) { foreach ($model['inputs'] as $input) { ?>
                <div class="wapf-option-cell wapf-option__flex"><?php echo isset($input['title']) ? esc_html($input['title']) : ''; ?></div>
                <?php } ?>
                <div class="wapf-option-cell wapf-option-moreless""></div>
            <?php } ?>
        </div>
    </div>
    <div rv-sortable-options="field.choices" class="wapf-options__body">
        <div class="wapf-option" style="display: flex;align-items: center" rv-each-choice="field.choices" rv-data-option-slug="choice.slug">
            <div class="wapf-option-cell wapf-option__sort"><span rv-sortable-option class="wapf-option-sort">☰</span></div>
            <div class="wapf-option-cell wapf-option__image">
                <div class="wapf-media-selector">
                    <input rv-mediaselector="choice.image" data-return-type="attachment" rv-on-change="onChange" type="hidden" rv-value="choice.image" />
                    <a class="wapf-btn-add-media" href="#">
                        <img rv-show="choice.image | isNotEmpty" rv-src="choice.image" />
                        <span><?php _e('Select', 'sw-wapf'); ?></span>
                    </a>
                </div>
            </div>
            <div class="wapf-option-less wapf-flex">
                <div class="wapf-option-cell wapf-option__flex"><input rv-on-keyup="onChange" rv-on-change="onChange" type="text" class="choice-label" rv-value="choice.label"/></div>
                <?php if(isset($model['show_pricing_options']) && $model['show_pricing_options']) { ?>
                    <div class="wapf-option-cell wapf-option__flex">
                        <select class="wapf-pricing-list" rv-on-change="onChange" rv-value="choice.pricing_type">
                            <option value="none"><?php _e('No price change','sw-wapf'); ?></option>
                            <?php
                            foreach(\SW_WAPF_PRO\Includes\Classes\Config::get_pricing_options( $model['field_type'] ) as $k => $v) {
                                echo '<option value="'.$k.'">'.$v.'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="wapf-option-cell wapf-option__flex">
                        <div rv-if="choice.pricing_type | eq 'fx'" class="wapf-input-prepend-append">
                            <div class="wapf-input-prepend small" rv-on-click="openFormulaBuilder" style="cursor: pointer;opacity: .75"><i style="display:flex;" class="dashicons-before dashicons-editor-help"></i></div>
                            <input placeholder="<?php _e('Enter formula','sw-wapf');?>" rv-on-change="onChange" type="text" rv-value="choice.pricing_amount" />
                        </div>
                        <input rv-disabled="choice.pricing_type | eq 'none'" rv-if="choice.pricing_type | neq 'fx'" placeholder="<?php _e('Amount','sw-wapf');?>" rv-on-change="onChange" type="number" step="any" rv-value="choice.pricing_amount" />
                    </div>
                <?php } ?>

                <?php if( $model['field_type'] !== 'image-swatch-qty') { ?>
                    <div class="wapf-option-cell wapf-option__selected">
                        <div class="wapf-toggle wapf-toggle--small" rv-unique-checkbox>
                            <input data-multi-option="<?php echo !empty( $model['multi_option'] ) ? '1' : '0' ;?>" rv-on-change="field.checkSelected" rv-checked="choice.selected" type="checkbox" />
                            <label class="wapf-toggle-label" for="wapf-toggle-">
                                <span class="wapf-toggle-inner" data-true="" data-false=""></span>
                                <span class="wapf-toggle-switch"></span>
                            </label>
                        </div>
                    </div>
                    <div class="wapf-option-cell wapf-option__disabled">
                        <div class="wapf-toggle wapf-toggle--small" rv-unique-checkbox>
                            <input rv-on-change="field.checkDisabled" rv-checked="choice.disabled" type="checkbox" />
                            <label class="wapf-toggle-label" for="wapf-toggle-">
                                <span class="wapf-toggle-inner" data-true="" data-false=""></span>
                                <span class="wapf-toggle-switch"></span>
                            </label>
                        </div>
                    </div>
                <?php } ?>
                <?php if( $has_more_inputs ) { ?>
                <div class="wapf-option-cell wapf-option-moreless">
                    <a class="wapf-option-more-link" href="#" style="text-decoration: none"><?php _e('more','sw-wapf') ?> &rarr;</a>
                </div>
                <?php } ?>
            </div>

            <div class="wapf-option-more wapf-flex" style="display: none">
                <?php if( $has_more_inputs ) { foreach ($model['inputs'] as $input) { ?>
                    <div class="wapf-option-cell wapf-option__flex">
                        <?php echo \SW_WAPF_PRO\Includes\Classes\Html::admin_choice_option_extra_input($input); ?>
                    </div>
                <?php } } ?>
                
                <div class="wapf-option-cell wapf-option-moreless">
                    <a class="wapf-option-less-link" href="#" style="text-decoration: none">&larr;<?php _e('less','sw-wapf') ?></a>
                </div>
            </div>
            <a style="position:absolute;right:-11px;top:12px" href="#" rv-on-click="field.deleteChoice" class="wapf-button--tiny-rounded wapf-del"></a>
        </div>
    </div>
</div>

<div style="display: flex;align-items: center;justify-content: space-between">
    <div>
        <a href="#" rv-on-click="field.addChoiceEvent" class="button"><?php _e('Add option','sw-wapf'); ?></a>
    </div>
    <div>
        <ul>
            <li style="display: inline-block;"><a href="https://www.studiowombat.com/knowledge-base/all-pricing-options-explained/?ref=wapf_admin" target="_blank"><?php _e('Pricing help','sw-wapf'); ?></a></li>
        </ul>
    </div>
</div>