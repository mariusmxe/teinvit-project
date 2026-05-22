<?php
/* @var $model array */
use SW_WAPF_PRO\Includes\Classes\Util;

$field_defs = \SW_WAPF_PRO\Includes\Classes\Config::get_field_definitions( 'admin' );

foreach ( $field_defs as &$def ) {
    unset( $def['icon'] );
    unset( $def['multi_choice'] );
    unset( $def['multi_select'] );
    unset( $def['group_label'] );
}

?>
<div rv-controller="FieldListCtrl"
     data-field-definitions="<?php echo Util::to_html_attribute_string( $field_defs ); ?>"
     data-raw-fields="<?php echo Util::to_html_attribute_string($model['fields']); ?>"
     data-field-conditions="<?php echo Util::to_html_attribute_string($model['condition_options']); ?>"
     style="position: relative"
>
    
    <input type="hidden" name="wapf-fields" rv-value="fieldsJson" />

    <div class="wapf-performance wapf-list--empty" rv-if="hiddenForPerformance">
        <a href="#" class="button button-primary button-large" rv-on-click="renderFields"><?php _e('View all fields','sw-wapf');?></a>
        <div style="padding-top: 15px">
            <?php _e('To ensure optimal page load performance, the field list is not displayed yet.<br/>If you want to edit or add fields, click the button above to view the list. Rendering may take a moment to complete.','sw-wapf');?>
        </div>
    </div>

    <div rv-if="hiddenForPerformance | eq false" class="wapf-field-list">

        <div class="wapf-field-list__body">
            <span rv-show="renderedFields | isEmpty" class="wapf-list--empty" style="display: <?php echo empty($model['fields']) ? 'block' : 'none';?>;">
                <a href="#" class="button button-primary button-large" rv-on-click="addField"><?php _e('Add your first field','sw-wapf');?></a>
            </span>

            <?php \SW_WAPF_PRO\Includes\Classes\Html::admin_field([], $model['type']); ?>

        </div>

        <div class="wapf-block-pro" style="display: none;padding:20px;background:#9f2a2a;)color:white;margin:15px;text-align: center">
            <span>
                <strong style="color: #fff;">
                    <?php _e('It appears you are using the paid version without a valid license. Features are temporarily disabled.<br/>If this is a mistake, please <a style="color: #fff;text-decoration: underline" target="_blank" href="https://www.studiowombat.com/request-support/">contact support</a> for help.', 'sw-wapf' ); ?>
                </strong>
            </span>
        </div>

        <div rv-cloak>

            <div rv-show="renderedFields | isNotEmpty" class="wapf-field-list__footer">
                <a href="#" class="button button-primary button-large" rv-on-click="addField"><?php _e('Add a Field','sw-wapf');?></a>
            </div>

            <div rv-if="maxPages | gt 1">
                <div class="wapf-pagination" style="border-top:1px solid #e2e2e2;text-align:center;padding-top:15px;">
                    <span rv-show="page | gt 1"><a href="#" class="wapf-prev"><?php _e('Previous page') ?></a></span>
                    <span style="padding:0 10px;opacity:.85">{page} / {maxPages}</span>
                    <span rv-show="page | lt maxPages"><a class="wapf-next" href="#"><?php _e('Next page') ?></a></span>
                </div>
            </div>

        </div>

    </div>

</div>