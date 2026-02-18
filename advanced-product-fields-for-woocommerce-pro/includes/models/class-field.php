<?php

namespace SW_WAPF_PRO\Includes\Models {

    use SW_WAPF_PRO\Includes\Classes\Config;

    class Field
    {

        public $id;

        public $label = '';

        public $description;

        public $type;

        public $subtype;

        public $required = false;

        public $options = [];

        public $conditionals = [];

        public $class;

        public $width;

        public $pricing;

	    public $parent_clone = [];

	    public $clone = [ 'enabled' => false ];

        public $meta = [];

        private $choices_have_pricing = null;

                public function __construct()
        {
            $this->pricing = new FieldPricing();
        }

        public function __clone()
        {
            $this->pricing = clone $this->pricing;

            foreach ( $this->conditionals as $i => $cond ) {
                $this->conditionals[$i] = clone $cond;
            }

            $this->choices_have_pricing = null;

                    }

        public function from_array( $a, $include_meta = true ): Field {

        	$this->id           = $a['id'];
        	$this->label        = $a['label'];
        	$this->description  = $a['description'];
        	$this->type         = $a['type'];
        	$this->required     = $a['required'];
        	$this->class        = $a['class'];
        	$this->width        = $a['width'];
            $this->options      = empty( $a['options'] ) ? [] : $a['options'];

                        if( isset( $a['subtype'] ) ) $this->subtype = $a['subtype'];

                    	if( isset( $a['clone'] ) ) $this->clone = $a['clone'];

	        if( ! empty( $a['parent_clone'] ) ) $this->parent_clone = $a['parent_clone'];

        	        	$p              = new FieldPricing();
        	$p->type        = $a['pricing']['type'];
        	$p->enabled     = $a['pricing']['enabled'];
        	$p->amount      = $a['pricing']['amount'];
        	$this->pricing  = $p;

        	foreach( $a['conditionals'] as $c ) {

        		                $cond = new Conditional();

        		                foreach( $c['rules'] as $r ) {
        			$rule               = new ConditionalRule();
        			$rule->condition    = $r['condition'];
        			$rule->value        = $r['value'];
                    $rule->field        = $r['field'];

                            			if( isset( $r['generated'] ) ) {
                        $rule->generated = $r['generated'];
                    }

        			        			$cond->rules[]  = $rule;
		        }

                        		$this->conditionals[] = $cond;

                	        }

            if( $include_meta ) {
                $the_type = $this->type . ( $this->subtype ? ( '-' . $this->subtype ) : '' );
                $this->meta = Config::get_field_definition_for( $the_type );
            }

                    	return $this;

        }

        public function to_array(): array {

        	$a = [
        		'id'                => $this->id,
        		'label'             => $this->label,
		        'description'       => $this->description,
		        'type'              => $this->type,
		        'required'          => $this->required,
		        'class'             => $this->class,
		        'width'             => $this->width,
		        'parent_clone'      => $this->parent_clone,
		        'options'           => $this->options,
		        'conditionals'      => [],
		        'clone'             => $this->clone,
		        'pricing'           => [
		        	'type'          => $this->pricing->type,
			        'amount'        => $this->pricing->amount,
			        'enabled'       => $this->pricing->enabled
		        ]
	        ];

                        if( isset( $this->subtype ) ) {
                $a['subtype'] = $this->subtype;
            }

                    	foreach ($this->conditionals as $conditional) {
        		$c = ['rules' => [] ];

        		foreach ($conditional->rules as $rule) {
        			$r = [
        				'condition' => $rule->condition,
				        'value'     => $rule->value,
				        'field'     => $rule->field,
				        'generated' => $rule->generated
			        ];
        			$c['rules'][] = $r;
		        }

        		$a['conditionals'][] = $c;

	        }

        	return $a;

        }

        public function get_label(): string {

        	if( ! empty( $this->label ) ) {
                return $this->label;
            }

        	if( $this->type === 'true-false' && ! empty( $this->options['message'] ) ) {
                return $this->options[ 'message' ];
            }

        	return __('N/a','sw-wapf');

        }

        public function get_option( $key, $default = null ) {

        	            if( isset( $this->options[ $key ] ) ) {
                return $this->options[ $key ];
            }

                    	return $default;
        }

                public function is( $subject ): bool {
            return ! empty( $this->meta[ $subject ] );
        }

        public function is_choice_field() {
            return ! empty( $this->meta['multi_choice'] );
        }

        public function is_category( $type ): bool {
            return ! empty( $this->meta['type'] && $this->meta['type'] === $type );
        }

                public function has_conditionals(): bool {
            return ! empty( $this->conditionals );
        }

        public function get_clone_label() {

	        return ! $this->clone['enabled'] || empty( $this->clone['label'] ) ? '' : $this->clone['label'];

        }

        public function get_clone_type( $include_parent = false ) {

        	if( ! $this->clone['enabled'] ) {
        		return $include_parent ? self::get_parent_clone_type() : '';
	        }

        	return $this->clone['type'];
        }

	    public function get_parent_clone_type() {
        	return empty( $this->parent_clone ) ? '' : $this->parent_clone['type'];
	    }

        public function pricing_enabled() {

	        if( $this->is( 'multi_choice' ) && ! empty( $this->options['choices'] ) ) {

		        if( $this->choices_have_pricing != null ) {
                    return $this->choices_have_pricing;
                }

                $this->choices_have_pricing = false;

                                foreach ( $this->options['choices'] as $choice ) {
                    if( isset( $choice['pricing_type'] ) && $choice['pricing_type'] !== 'none' ) {
                        $this->choices_have_pricing = true;
                        break;
                    }
                }

                return $this->choices_have_pricing;

                	        }

	        return $this->pricing->enabled;

        }

    }
}