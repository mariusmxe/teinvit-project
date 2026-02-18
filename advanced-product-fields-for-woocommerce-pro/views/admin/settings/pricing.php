<?php
/* @var $model array */

?>

<div class="wapf-toggle" rv-unique-checkbox>
    <input rv-on-change="onChange" rv-checked="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.pricing.enabled" type="checkbox" >
    <label class="wapf-toggle-label" for="wapf-toggle-">
        <span class="wapf-toggle-inner" data-true="<?php _e('Yes','sw-wapf'); ?>" data-false="<?php _e('No','sw-wapf'); ?>"></span>
        <span class="wapf-toggle-switch"></span>
    </label>
</div>

<div class="wapf-setting__pricing" rv-show="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.pricing.enabled">
    <div>
        <select class="wapf-pricing-list" rv-on-change="onChange" rv-value="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.pricing.type">
            <?php
            foreach(\SW_WAPF_PRO\Includes\Classes\Config::get_pricing_options($model['field_type']) as $k => $v) {
                echo '<option value="'.$k.'">'.$v.'</option>';
            }
            ?>
        </select>
    </div>
    <div style="flex: 1">
        <input rv-if="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.pricing.type | neq 'fx'" placeholder="<?php _e('Amount','sw-wapf');?>" rv-on-change="onChange" type="number" step="any" rv-value="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.pricing.amount" />
        <div rv-if="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.pricing.type | eq 'fx'" class="wapf-input-prepend-append">
            <div class="wapf-input-prepend" rv-on-click="openFormulaBuilder" style="cursor: pointer;opacity: .75"><i style="display: flex" class="dashicons-before dashicons-editor-help"></i></div>
            <input placeholder="<?php _e('Enter formula','sw-wapf');?>" rv-on-change="onChange" type="text" rv-value="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.pricing.amount" />
        </div>
    </div>

</div>

<div style="margin-top:10px;text-align: right" rv-show="<?php echo $model['is_field_setting'] ? 'field' : 'settings'; ?>.pricing.enabled">
    <a href="https://www.studiowombat.com/knowledge-base/all-pricing-options-explained/?ref=wapf_admin" target="_blank">
        <?php _e('Help with pricing','sw-wapf'); ?>
    </a>
</div>