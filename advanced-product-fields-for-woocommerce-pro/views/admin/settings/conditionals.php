<?php /* @var $model array */ ?>

<div style="width:100%;"  class="wapf-field__conditionals">

    <div class="wapf-field__conditionals__container">
        
        <div rv-if="fieldsForConditionals.fields | isEmpty" class="wapf-lighter">
            <?php _e('You need atleast 2 fields to create conditional rules. Add another field first.','sw-wapf');?>
        </div>
        
        <div rv-if="fieldsForConditionals.fields | isNotEmpty">
            
            <div style="padding-top:10px" rv-if="field.conditionals | isEmpty">
                <button class="apf-button" rv-on-click="addConditional">
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM232 344c0 13.3 10.7 24 24 24s24-10.7 24-24V280h64c13.3 0 24-10.7 24-24s-10.7-24-24-24H280V168c0-13.3-10.7-24-24-24s-24 10.7-24 24v64H168c-13.3 0-24 10.7-24 24s10.7 24 24 24h64v64z"></path></svg>
                    </span>
                    <span style="padding-left: 6px"><?php esc_html_e( 'Add new rule group','sw-wapf' ); ?></span>
                </button>
            </div>
        
            <div rv-if="field.conditionals | isNotEmpty">
            
            <div rv-show="field.conditionals|isNotEmpty">
                <strong style="display: inline-block;padding-bottom: 10px;"><?php _e('Show this field if','sw-wapf'); ?>:</strong>
            </div>
            
            <div class="apf-conditions-wrapper">
                
                <div class="apf-condition-group-wrapper">
                    <div rv-each-conditional="field.conditionals">
                        <div class="apf-conditions-divider" rv-if="$index | gt 0">
                            <span><?php esc_html_e( 'or', 'sw-wapf' ) ?></span>
                        </div>
                        <table style="padding-bottom:10px;width:100%;" class="wapf-field__conditional">
                            <tr class="conditional__rule hide_del" rv-each-rule="conditional.rules">
                                <td style="width: 31%">
                                    <select rv-on-change="onConditionalFieldChange" rv-value="rule.field">
                                        <option rv-each-fieldobj="fieldsForConditionals.fields" rv-value="fieldobj.id">{fieldobj.label}</option>
                                    </select>
                                </td>
                                <td style="width: 21%">
                                    <select rv-on-change="onConditionalConditionChange" rv-value="rule.condition">
                                        <option rv-each-condition="rule.possibleConditions" rv-value="condition.value">{ condition.label }</option>
                                    </select>
                                </td>
                                <td>
                                    <input rv-if="rule.selectedCondition.type | eq 'text'" rv-on-keyup="onChange" type="text" rv-value="rule.value" />
                                    <input rv-if="rule.selectedCondition.type | eq 'number'" step="any" rv-on-change="onChange" rv-on-keyup="onChange" type="number" rv-value="rule.value" />
                                    <!--<select rv-if="rule.selectedCondition.type | eq 'options'" rv-on-change="onChange" rv-value="rule.value">
                                        <option rv-each-w="fields | query 'getChoices' rule.field" rv-value="w.slug">{w.label}</option>
                                    </select>-->
                                    <select rv-if="rule.selectedCondition.type | eq 'options'" rv-on-change="onChange" rv-value="rule.value">
                                        <option rv-each-w="rule.selectedCondition._choices" rv-value="w.slug">{w.label}</option>
                                    </select>
                                    <select rv-if="rule.selectedCondition.type | eq 'product_choice'" rv-on-change="onChange" rv-value="rule.value">
                                        <option rv-each-w="rule.selectedCondition._choices" rv-value="w.slug">{w.label}</option>
                                    </select>
                                    <input rv-if="rule.selectedCondition.type | eq 'product_id'" placeholder="<?php _e( 'Product ID', 'sw-wapf' ) ?>" step="1" rv-on-change="onChange" rv-on-keyup="onChange" type="number" rv-value="rule.value" />
                                    <input rv-if="rule.selectedCondition.type | eq false" disabled type="text"/>
                                    <div style="opacity: .7;font-size: 90%;padding-top: 5px;display: inline-block" rv-if="rule.selectedCondition.desc | isNotEmpty">{rule.selectedCondition.desc}</div>
                                </td>
        
                                <td style="width: 40px">
                                    <button class="apf-button" rv-on-click="addRule" rv-show="conditional.rules | isLastIteration $index">
                                        <span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM232 344c0 13.3 10.7 24 24 24s24-10.7 24-24V280h64c13.3 0 24-10.7 24-24s-10.7-24-24-24H280V168c0-13.3-10.7-24-24-24s-24 10.7-24 24v64H168c-13.3 0-24 10.7-24 24s10.7 24 24 24h64v64z"></path></svg>
                                        </span>
                                        <span style="padding-left: 6px;text-transform: uppercase"><?php esc_html_e( 'And','sw-wapf' ); ?></span>
                                    </button>
                                </td>
                                <td style="width: 30px;vertical-align: middle">
                                    <button class="apf-button-transparent" rv-on-click="deleteRule" title="<?php esc_attr_e( 'Delete', 'sw-wapf' ) ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" width="14px" height="14px"><path d="M170.5 51.6L151.5 80h145l-19-28.4c-1.5-2.2-4-3.6-6.7-3.6H177.1c-2.7 0-5.2 1.3-6.7 3.6zm147-26.6L354.2 80H368h48 8c13.3 0 24 10.7 24 24s-10.7 24-24 24h-8V432c0 44.2-35.8 80-80 80H112c-44.2 0-80-35.8-80-80V128H24c-13.3 0-24-10.7-24-24S10.7 80 24 80h8H80 93.8l36.7-55.1C140.9 9.4 158.4 0 177.1 0h93.7c18.7 0 36.2 9.4 46.6 24.9zM80 128V432c0 17.7 14.3 32 32 32H336c17.7 0 32-14.3 32-32V128H80zm80 64V400c0 8.8-7.2 16-16 16s-16-7.2-16-16V192c0-8.8 7.2-16 16-16s16 7.2 16 16zm80 0V400c0 8.8-7.2 16-16 16s-16-7.2-16-16V192c0-8.8 7.2-16 16-16s16 7.2 16 16zm80 0V400c0 8.8-7.2 16-16 16s-16-7.2-16-16V192c0-8.8 7.2-16 16-16s16 7.2 16 16z"></path></svg>
                                    </button>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="apf-conditions-footer">
                    <button class="apf-button" rv-on-click="addConditional">
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 512 512"><path d="M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM232 344c0 13.3 10.7 24 24 24s24-10.7 24-24V280h64c13.3 0 24-10.7 24-24s-10.7-24-24-24H280V168c0-13.3-10.7-24-24-24s-24 10.7-24 24v64H168c-13.3 0-24 10.7-24 24s10.7 24 24 24h64v64z"></path></svg>
                        </span>
                        <span style="padding-left: 6px"><?php esc_html_e( 'Or add new rule group','sw-wapf' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
            
        </div>
        
    </div>

</div>
