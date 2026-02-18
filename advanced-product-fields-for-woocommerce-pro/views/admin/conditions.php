<?php
/* @var $model array */
use SW_WAPF_PRO\Includes\Classes\Util;
?>

<div rv-controller="ConditionsCtrl"
     data-raw-conditions="<?php echo Util::to_html_attribute_string($model['conditions']); ?>"
     data-fieldgroup-conditions="<?php echo Util::to_html_attribute_string($model['condition_options']); ?>"
     data-wapf-type="<?php echo $model['post_type']; ?>"
>

    <input type="hidden" name="wapf-conditions" rv-value="conditionsJson" />
    <input type="hidden" name="wapf-fieldgroup-type" value="<?php echo $model['post_type'];?>" />

    <div class="apf-conditions-list">

        <div class="apf-conditions-list__body">

            <div class="wapf-field__setting">
                <div class="wapf-setting__label">
                    <label><?php esc_html_e('Rules','sw-wapf');?></label>
                    <p class="wapf-description">
                        <?php esc_html_e("Add rules to determine for which products the fields should show.", 'sw-wapf'); ?>
                    </p>
                </div>
                <div class="wapf-setting__input">
                    <div rv-show="rulegroups | isEmpty" class="wapf-list--empty" style="display: <?php echo empty($model['conditions']) ? 'block' : 'none';?>;">
                        <a href="#" class="button button-primary button-large" rv-on-click="addRuleGroup"><?php esc_html_e( 'Add your first rule','sw-wapf' ) ?></a>
                        <div style="text-align: center;padding-top:10px">
                            <i><?php esc_html_e( 'If you don\'t add any rules, this field group will display on all products.','sw-wapf' ) ?></i>
                        </div>
                    </div>
                    
                    <div rv-show="rulegroups | isNotEmpty">
                        
                        <div class="apf-info-note">
                            <div class="dashicon dashicons dashicons-info"></div>
                            <div>
                                <?php esc_html_e( 'If you create multiple rules using the \'or\' button, the first group that evaluates to true will be used.', 'sw-wapf' ) ?>
                            </div>
                        </div>
                        
                        <div class="apf-conditions-wrapper">
                            
                            <div style="width: 100%;" rv-each-group="rulegroups" rv-cloak rv-class="$index | prefix 'apf-condition-group-wrapper wapf-rulegroup-'">
                                
                                <div class="apf-conditions-divider" rv-if="$index | gt 0">
                                    <span><?php esc_html_e( 'or', 'sw-wapf' ) ?></span>
                                </div>

                                <table style="width: 100%">
                                    <tr rv-each-rule="group.rules" rv-class="$index | prefix 'hide_del wapf-rulegroup-rule-'">
                                        <td style="width: 21%;">
                                            <select rv-on-change="onChangeRuleSubject" rv-value="rule.subject">
                                                <optgroup rv-each-group="activeConditionOptions" rv-label="group.group">
                                                    <option rv-each-option="group.children" rv-value="option.id">{option.label}</option>
                                                </optgroup>
                                            </select>
                                        </td>
                                       
                                        <td style="width:17%;">
                                            <select rv-show="rule.subject | eq 'auth'">
                                                <option selected value=""><?php esc_html_e( 'User is', 'sw-wapf' ) ?></option>
                                            </select>
                                            <select rv-show="rule.subject | neq 'auth'" rv-on-change="setSelectedCondition" rv-value="rule.condition">
                                                <option rv-each-condition="rule.options.conditions" rv-value="condition.id">{condition.label}</option>
                                            </select>
                                        </td>
                                        <td style="max-width: 450px;" rv-show="rule.subject | eq 'auth'">
                                            <select rv-on-change="setSelectedCondition" rv-value="rule.condition">
                                                <option rv-each-condition="rule.options.conditions" rv-value="condition.id">{condition.label}</option>
                                            </select>
                                        </td>
                                        <td style="max-width: 450px;" rv-show="rule.subject | neq 'auth'">
                                        <div rv-if="rule.selectedCondition.value.type | eq 'text'">
                                            <input rv-on-change="onChange" type="text" rv-value="rule.value" />
                                        </div>
                                        <div rv-if="rule.selectedCondition.value.type | eq 'number'">
                                            <input rv-on-change="onChange" type="number" step="1" rv-value="rule.value" />
                                        </div>
                                        <div rv-if="rule.selectedCondition.value.type | eq 'select'">
                                            <select rv-on-change="onChange" rv-value="rule.value">
                                                <option rv-each-option="rule.selectedCondition.value.data" rv-value="option.id">{option.text}</option>
                                            </select>
                                        </div>
                                        <div rv-if="rule.selectedCondition.value.type | eq 'select2'">
                                            <div class="select2-or">
                                                <select
                                                    rv-on-change="onChange"
                                                    rv-select2="rule.value"
                                                    class="wapf-select2"
                                                    multiple="multiple"
                                                    rv-data-select2-placeholder="rule.selectedCondition.value.placeholder"
                                                    rv-data-select2-action="rule.selectedCondition.value.action"
                                                    rv-data-select2-data="rule.selectedCondition.value.data|tostring"
                                                    rv-data-select2-single="rule.selectedCondition.value.single"
                                                >
                                                </select>
                                            </div>
                                        </div>

                                        </td>
                                        <td style="width: 1%">
                                            <button class="apf-button" rv-on-click="addRule" rv-show="group.rules | isLastIteration $index">
                                                <span>
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM232 344c0 13.3 10.7 24 24 24s24-10.7 24-24V280h64c13.3 0 24-10.7 24-24s-10.7-24-24-24H280V168c0-13.3-10.7-24-24-24s-24 10.7-24 24v64H168c-13.3 0-24 10.7-24 24s10.7 24 24 24h64v64z"></path></svg>
                                                </span>
                                                <span style="padding-left: 6px;text-transform: uppercase"><?php esc_html_e( 'And','sw-wapf' ); ?></span>
                                            </button>
                                        </td>
                                        <td style="width: 1%">
                                            <!--<a style="margin-top: 7px" href="#" rv-on-click="deleteRule" class="wapf-button--tiny-rounded wapf-del"></a>-->
                                            <button class="apf-button-transparent" rv-on-click="deleteRule" title="<?php esc_attr_e( 'Delete', 'sw-wapf' ) ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="14px" height="14px"><path d="M170.5 51.6L151.5 80h145l-19-28.4c-1.5-2.2-4-3.6-6.7-3.6H177.1c-2.7 0-5.2 1.3-6.7 3.6zm147-26.6L354.2 80H368h48 8c13.3 0 24 10.7 24 24s-10.7 24-24 24h-8V432c0 44.2-35.8 80-80 80H112c-44.2 0-80-35.8-80-80V128H24c-13.3 0-24-10.7-24-24S10.7 80 24 80h8H80 93.8l36.7-55.1C140.9 9.4 158.4 0 177.1 0h93.7c18.7 0 36.2 9.4 46.6 24.9zM80 128V432c0 17.7 14.3 32 32 32H336c17.7 0 32-14.3 32-32V128H80zm80 64V400c0 8.8-7.2 16-16 16s-16-7.2-16-16V192c0-8.8 7.2-16 16-16s16 7.2 16 16zm80 0V400c0 8.8-7.2 16-16 16s-16-7.2-16-16V192c0-8.8 7.2-16 16-16s16 7.2 16 16zm80 0V400c0 8.8-7.2 16-16 16s-16-7.2-16-16V192c0-8.8 7.2-16 16-16s16 7.2 16 16z"></path></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </table>
                                
                            </div>

                            <div rv-cloak style="width:100%;">
                                <div class="apf-conditions-footer" rv-show="rulegroups | isNotEmpty">
                                    <button class="apf-button" rv-on-click="addRuleGroup">
                                        <span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM232 344c0 13.3 10.7 24 24 24s24-10.7 24-24V280h64c13.3 0 24-10.7 24-24s-10.7-24-24-24H280V168c0-13.3-10.7-24-24-24s-24 10.7-24 24v64H168c-13.3 0-24 10.7-24 24s10.7 24 24 24h64v64z"></path></svg>
                                        </span>
                                        <span style="padding-left: 6px;text-transform: uppercase"><?php esc_html_e( 'Or','sw-wapf' ); ?></span>
                                    </button>
                                </div>
                            </div>
                            
                        </div>

                        <div rv-show="showWarning" class="apf-info-note apf-error-note">
                            <div class="dashicon dashicons dashicons-warning"></div>
                            <div>
                                <?php echo wp_kses( __( 'It seems you\'ve created multiple \'OR\' conditions that should be grouped into 1 condition. <a href="https://www.studiowombat.com/wp-content/uploads/2025/10/grouping-rules-together-correctly.png" target="_blank">See this screenshot</a> on how to fix it.', 'sw-wapf' ), [ 'a' => [ 'href' => [], 'target' => [] ] ] ) ?>
                            </div>
                        </div>
                        
                    </div>
                    
                </div>
            </div>
        </div>

    </div>
</div>