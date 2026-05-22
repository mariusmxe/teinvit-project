<?php

namespace SW_WAPF_PRO\Includes\Models {

        class ConditionRuleGroup
    {
        public $rules = [];

        public function __clone() {
            foreach ($this->rules as $i => $rule) {
                $this->rules[$i] = clone $rule;
            }
        }

        public function get_variation_rules(): array {

                    	if( empty( $this->rules ) ) {
                return [];
            }

            return array_filter( $this->rules, function( $rule ) {
                return ! empty( $rule->subject ) && ( $rule->subject === 'var_att' || $rule->subject === 'product_variation' );
            });

                    }

            }
}