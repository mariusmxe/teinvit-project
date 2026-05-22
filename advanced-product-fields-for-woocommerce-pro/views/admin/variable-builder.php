<?php
/* @var $model array */
use SW_WAPF_PRO\Includes\Classes\Util;
?>
<div rv-controller="VariablesCtrl" data-variables="<?php echo Util::to_html_attribute_string($model['variables']); ?>">
    <input type="hidden" name="wapf-variables" rv-value="json" />

    <div class="wapf-collapsible__holder" style="padding:0 25px;" rv-show="variables|isNotEmpty">
        <div rv-each-variable="variables" class="wapf-collapsible__wrapper" rv-data-variable-id="variable.name">
            <div class="wapf-collapsible__header">
                <div class="wapf-collapsible__sort" title="<?php _e('Drag & drop','sw-wapf');?>">☰</div>
                <div class="wapf-collapsible__name">
                    var_{variable.name}
                </div>
                <div class="wapf-collapsible__actions">
                    <a class="wapf-variable-dupe" href="#" rv-on-click="duplicateVariable" title="<?php _e('Duplicate variable','sw-wapf');?>"><?php _e('Duplicate', 'sw-wapf') ?></a>
                    <a href="#" style="color: #a00 !important" title="<?php _e('Delete variable','sw-wapf');?>" rv-on-click="deleteVariable"><?php _e('Delete', 'sw-wapf') ?></a>
                </div>
            </div>
            <div class="wapf-collapsible__body">
                <div class="wapf-field__setting">
                    <div class="wapf-setting__label">
                        <label>
                            <?php _e('Variable name','sw-wapf');?>
                        </label>
                        <p class="wapf-description">
                            <?php _e('A unique key to identify your variable. Use this key in pricing formulas.','sw-wapf'); ?>
                        </p>
                    </div>
                    <div class="wapf-setting__input">
                        <div>
                            <div class="wapf-input-with-prepend">
                                <div class="wapf-input-prepend">var_</div>
                                <input type="text" rv-value="variable.name" rv-on-keyup="onChangeVariableName"/>
                            </div>
                        </div>
                        <div class="wapf-option-note">
                            <?php _e('Should only contain letters, numbers, or underscores.','sw-wapf'); ?>
                        </div>
                    </div>
                </div>
                <div class="wapf-field__setting">
                    <div class="wapf-setting__label">
                        <label>
                            <?php _e('Standard value','sw-wapf');?>
                        </label>
                        <p class="wapf-description">
                            <?php _e('The default value of your variable.','sw-wapf'); ?>
                        </p>
                    </div>
                    <div class="wapf-setting__input">
                        <input type="text" rv-value="variable.default" rv-on-change="onChange"  />
                        <p style="opacity:.7"><?php _e('This should be a number or a formula.','sw-wapf'); ?></p>
                    </div>
                </div>
                <div class="wapf-field__setting">
                    <div class="wapf-setting__label">
                        <label>
                            <?php _e('Value changes','sw-wapf');?>
                        </label>
                        <p class="wapf-description">
                            <?php _e('Add rules when the value of this variable should change.','sw-wapf'); ?>
                        </p>
                    </div>
                    <div class="wapf-setting__input">
                        <div class="variable_rule__wrapper">

                            <table>
                                <tr rv-each-variablerule="variable.rules" class="hide_del">
                                    <td>
                                        <strong><?php _e('If this happens','sw-wapf'); ?></strong>
                                        <select rv-on-change="onVariableRuleTypeChange" rv-value="variablerule.type">
                                            <option rv-disabled="canAddFieldToVariableRule|neq true" value="field"><?php _e('Field value changes','sw-wapf');?></option>
                                            <option value="qty"><?php _e('Product quantity changes','sw-wapf');?></option>
                                        </select>
                                    </td>
                                    <td rv-show="variablerule.type|eq 'qty'">
                                        <strong><?php _e('And quantity','sw-wapf'); ?></strong>
                                        <select rv-value="variablerule.condition" rv-on-change="onChange">
                                            <option value="=="><?php _e(' is equal to','sw-wapf'); ?></option>
                                            <option value="!="><?php _e('is not equal to','sw-wapf'); ?></option>
                                            <option value="gt"><?php _e('is greater than','sw-wapf'); ?></option>
                                            <option value="lt"><?php _e('is lesser than','sw-wapf'); ?></option>
                                        </select>
                                    </td>
                                    <td rv-show="variablerule.type|eq 'qty'">
                                        <input step="any" min="1" rv-on-change="onChange" rv-on-keyup="onChange" type="number" rv-value="variablerule.value" />
                                    </td>
                                    <td rv-show="variablerule.type|eq 'qty'">&nbsp;</td>
                                    <td rv-show="variablerule.type |eq 'field'" style="width: 20%;">
                                        <strong><?php _e('This field changes','sw-wapf'); ?></strong>
                                        <select rv-value="variablerule.field" rv-on-change="onChange" >
                                            <option rv-each-field="fields" rv-value="field.id">{field.label}</option>
                                        </select>
                                    </td>
                                    <td rv-show="variablerule.type |eq 'field'" style="width: 20%">
                                        <select rv-value="variablerule.condition" rv-on-change="onChange" >
                                            <option rv-each-condition="availableConditions | filterConditions variablerule.field fields" rv-value="condition.value">{ condition.label }</option>
                                        </select>
                                    </td>
                                    <td rv-show="variablerule.type |eq 'field'" style="width: 20%;">
                                        
                                        <input rv-if="variablerule.condition | conditionNeedsValue availableConditions 'text' fields variablerule.field" rv-on-keyup="onChange" type="text" rv-value="variablerule.value" />
                                        
                                        <input rv-if="variablerule.condition | conditionNeedsValue availableConditions 'number' fields variablerule.field" step="any" rv-on-change="onChange" rv-on-keyup="onChange" type="number" rv-value="variablerule.value" />
                                       
                                        <select rv-if="variablerule.condition | conditionNeedsValue availableConditions 'options' fields variablerule.field" rv-on-change="onChange" rv-value="variablerule.value">
                                            <option rv-each-v="fields | query 'first' 'id' '===' variablerule.field 'get' 'choices'" rv-value="v.slug">{v.label}</option>
                                        </select>
                                       
                                        <input rv-if="variablerule.condition | conditionDoesntNeedValue availableConditions fields variablerule.field" disabled type="text" />
                                       
                                        <select rv-if="variablerule.condition | conditionNeedsValue availableConditions 'product_choice' fields variablerule.field" rv-on-change="onChange" rv-value="variablerule.value">
                                            <option rv-each-v="fields | query 'first' 'id' '===' variablerule.field 'get' 'choices'" rv-value="v.slug">{v.label}</option>
                                        </select>
                                        
                                        <input rv-if="variablerule.condition | conditionNeedsValue availableConditions 'product_id' fields variablerule.field" placeholder="<?php _e( 'Product ID', 'sw-wapf' ) ?>" step="1" rv-on-change="onChange" rv-on-keyup="onChange" type="number" rv-value="variablerule.value" />
                                    </td>
                                    <td>
                                        <strong><?php _e('Variable value is','sw-wapf');?></strong>
                                        <input type="text" rv-value="variablerule.variable" rv-on-change="onChange"/>
                                    </td>
                                    <td style="width: 30px;vertical-align: bottom;padding-bottom:1em">
                                        <button class="apf-button-transparent" rv-on-click="deleteVariableRule" title="<?php esc_attr_e( 'Delete', 'sw-wapf' ) ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="14px" height="14px"><path d="M170.5 51.6L151.5 80h145l-19-28.4c-1.5-2.2-4-3.6-6.7-3.6H177.1c-2.7 0-5.2 1.3-6.7 3.6zm147-26.6L354.2 80H368h48 8c13.3 0 24 10.7 24 24s-10.7 24-24 24h-8V432c0 44.2-35.8 80-80 80H112c-44.2 0-80-35.8-80-80V128H24c-13.3 0-24-10.7-24-24S10.7 80 24 80h8H80 93.8l36.7-55.1C140.9 9.4 158.4 0 177.1 0h93.7c18.7 0 36.2 9.4 46.6 24.9zM80 128V432c0 17.7 14.3 32 32 32H336c17.7 0 32-14.3 32-32V128H80zm80 64V400c0 8.8-7.2 16-16 16s-16-7.2-16-16V192c0-8.8 7.2-16 16-16s16 7.2 16 16zm80 0V400c0 8.8-7.2 16-16 16s-16-7.2-16-16V192c0-8.8 7.2-16 16-16s16 7.2 16 16zm80 0V400c0 8.8-7.2 16-16 16s-16-7.2-16-16V192c0-8.8 7.2-16 16-16s16 7.2 16 16z"></path></svg>
                                        </button>
                                    </td>
                                </tr>
                            </table>

                        </div>
                        <div style="padding-top:15px;">
                            <button class="apf-button" rv-on-click="addVariableRule">
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM232 344c0 13.3 10.7 24 24 24s24-10.7 24-24V280h64c13.3 0 24-10.7 24-24s-10.7-24-24-24H280V168c0-13.3-10.7-24-24-24s-24 10.7-24 24v64H168c-13.3 0-24 10.7-24 24s10.7 24 24 24h64v64z"></path></svg>
                                </span>
                                <span style="padding-left: 6px"><?php esc_html_e( 'Add new rule','sw-wapf' ); ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="wapf-list--empty">
        <a href="#" class="button button-primary button-large" rv-on-click="addEmptyVariable"><?php _e('Add new variable','sw-wapf'); ?></a>
    </div>

</div>