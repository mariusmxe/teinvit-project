<?php

namespace SW_WAPF_PRO\Includes\Models {

    class ConditionRule
    {

        public $subject;

        public $condition;

        public $value;

                public function requires_frontend_validation(): bool {
            return  $this->subject === 'var_att' || $this->subject === 'product_variation';
        }

            }

}