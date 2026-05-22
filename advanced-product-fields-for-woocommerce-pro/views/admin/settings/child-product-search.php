<?php
/* @var $model array */
$has_more_inputs = ! empty( $model['inputs'] );
$has_quantities = isset( $model['has_quantities'] ) && $model['has_quantities'];
?>

<div style="padding-bottom: 15px;">
    
    <div style="display: flex" class="wapf-flex wapf-flex-center">
        <span style="font-weight:500;padding-right: 10px">
            <?php _e( 'Product selection: ', 'sw-wapf' ) ?>
        </span>
        <select style="flex: 1" rv-on-change="onChange" rv-default="field.product_selection" data-default="manual" rv-value="field.product_selection">
            <option value="manual"><?php _e( 'Select specific products', 'sw-wapf' ) ?></option>
            <option value="category"><?php _e( 'Auto-populate by category', 'sw-wapf' ) ?></option>
        </select>
    </div>
    
</div>

<div rv-if="field.product_selection | eq 'category'">
    
    
    <div class="wapf-option-table">
        <div class="wapf-options__body">
            <div class="wapf-option">
                
                <div style="width:100px" class="wapf-option-cell"><?php _e('Category','sw-wapf'); ?></div>

                <div class="wapf-option-cell wapf-option__flex">
                    <select
                        data-select2-keys="query_id,query_label"
                        rv-on-change="onChange"
                        rv-select2="field.product_query" 
                        multiple="multiple"
                        class="wapf-select2"
                        data-select2-placeholder="<?php _e("Search a category...",'sw-wapf') ?>"
                        data-select2-action="wapf_search_cat"
                        data-select2-single="true"
                    >
                    </select>
                </div>
                
            </div>
            
            <div class="wapf-option">
                <div style="width:100px" class="wapf-option-cell"><?php _e('Max. limit','sw-wapf'); ?></div>
                <div class="wapf-option-cell wapf-option__flex">
                    <input type="number" rv-value="field.product_query.limit" rv-default="field.product_query.limit" data-default="5" min="1" max="50" />
                </div>
            </div>
            
            <div class="wapf-option">
                <div style="width:100px" class="wapf-option-cell"><?php _e('Sorting','sw-wapf'); ?></div>

                <div class="wapf-option-cell wapf-option__flex">
                    <select rv-default="field.product_query.sort" data-default="date_desc" rv-value="field.product_query.sort">
                        <option value="date_desc"><?php _e( 'Creation date (newest first)', 'sw-wapf' ) ?></option>
                        <option value="date_asc"><?php _e( 'Creation date (oldest first)', 'sw-wapf' ) ?></option>
                        <option value="name_asc"><?php _e( 'Title (A-Z)', 'sw-wapf' ) ?></option>
                        <option value="name_desc"><?php _e( 'Title (Z-A)', 'sw-wapf' ) ?></option>
                    </select>
                </div>
            </div>

            <div class="wapf-option">
                <div style="width:100px" class="wapf-option-cell"><?php _e('Price','sw-wapf'); ?></div>
                <div class="wapf-option-cell wapf-option__flex">
                    <select rv-value="field.product_query.pricing_type" rv-default="field.product_query.pricing_type" data-default="fixed">
                        <option value="none"><?php _e( 'Free', 'sw-wapf') ?></option>
                        <option value="fixed"><?php _e( 'Product price', 'sw-wapf') ?></option>
                    </select>
                </div>
            </div>
            
        </div>
    </div>
</div>

<div rv-if="field.product_selection | eq 'manual'">
    <div class="wapf-option-table" rv-show="field.choices | isNotEmpty">
        <div class="wapf-options__header">
            <div class="wapf-option-cell wapf-option__sort"></div>
            <div class="wapf-option-less">
                <div class="wapf-option-cell wapf-option__flex"><?php _e('Product','sw-wapf'); ?></div>
                <div class="wapf-option-cell wapf-option__flex"><?php _e('Price','sw-wapf'); ?></div>
                <?php if( ! $has_quantities ) { ?>
                <div class="wapf-option-cell wapf-option__selected"><?php _e('Select', 'sw-wapf'); ?></div>
                <?php } ?>
                <div class="wapf-option-cell wapf-option__disabled"><?php _e('Disable', 'sw-wapf'); ?></div>
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
            <div class="wapf-option" rv-each-choice="field.choices" rv-data-option-slug="choice.slug">
                <div class="wapf-option-cell wapf-option__sort"><span rv-sortable-option class="wapf-option-sort">☰</span></div>
                <div class="wapf-option-less wapf-flex">
                    <div class="wapf-option-cell wapf-option__flex">
                       <select
                            data-select2-keys="id,label"
                            rv-on-change="onChange"
                            rv-select2="choice"
                            multiple="multiple"
                            class="wapf-select2"
                            data-select2-placeholder="<?php __("Search a product...",'sw-wapf') ?>"
                            data-select2-action="wapf_product_picker"
                            data-select2-single="true"
                        >
                        </select>
                    </div>
                    <div class="wapf-option-cell wapf-option__flex">
                        <select rv-value="choice.pricing_type">
                            <option value="none"><?php _e( 'Free', 'sw-wapf') ?></option>
                            <option value="fixed"><?php _e( 'Product price', 'sw-wapf') ?></option>
                        </select>
                    </div>
                    <?php if( ! $has_quantities ) { ?>
                    <div class="wapf-option-cell wapf-option__selected">
                        <div class="wapf-toggle wapf-toggle--small" rv-unique-checkbox>
                            <input data-multi-option="<?php echo !empty( $model['multi_option'] ) ? '1' : '0' ;?>" rv-on-change="field.checkSelected" rv-checked="choice.selected" type="checkbox" />
                            <label class="wapf-toggle-label" for="wapf-toggle-">
                                <span class="wapf-toggle-inner" data-true="" data-false=""></span>
                                <span class="wapf-toggle-switch"></span>
                            </label>
                        </div>
                    </div>
                    <?php } ?>
                    <div class="wapf-option-cell wapf-option__disabled">
                        <div class="wapf-toggle wapf-toggle--small" rv-unique-checkbox>
                            <input rv-on-change="field.checkDisabled" rv-checked="choice.disabled" type="checkbox" />
                            <label class="wapf-toggle-label" for="wapf-toggle-">
                                <span class="wapf-toggle-inner" data-true="" data-false=""></span>
                                <span class="wapf-toggle-switch"></span>
                            </label>
                        </div>
                    </div>
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

    <div>
        <a href="#" rv-on-click="field.addChoiceEvent" class="button"><?php _e('Add product','sw-wapf'); ?></a>
    </div>

</div>