<?php

namespace SW_WAPF_PRO\Includes\Models {

    use SW_WAPF_PRO\Includes\Classes\Config;

    class FieldGroup
    {
        public $id;

        public $type = 'wapf_product';

        public $rules_groups = [];

        public $fields = [];

        public $layout;

        public $variables = [];

        public function __construct()
        {
            $this->layout = [
                'labels_position'       => 'above',
                'instructions_position' => 'field',
                'mark_required'         => true,
	            'enable_gallery_images' => false,
	            'gallery_images'        => []
            ];

                    }

        public function __clone() {

            foreach ( $this->rules_groups as $i => $group ) {
                $this->rules_groups[$i] = clone $group;
            }

            foreach ( $this->fields as $i => $field ) {
                $this->fields[$i] = clone $field;
            }

                    }

        public function from_array( $a ): FieldGroup
        {

			$this->id = $a['id'];
			$this->type = $a['type'];
			$this->layout = $a[ 'layout' ] ?? [];
			$this->variables = $a[ 'variables' ] ?? [];

			foreach($a['rule_groups'] as $rg) {
				$rulegroup = new ConditionRuleGroup();
				foreach($rg['rules'] as $r) {
					$rule = new ConditionRule();
					$rule->value = $r['value'];
					$rule->condition = $r['condition'];
					$rule->subject = $r['subject'];
					$rulegroup->rules[] = $rule;
				}
				$this->rules_groups[] = $rulegroup;
			}

            $all_field_definitions = Config::get_field_definitions();

            			foreach( $a['fields'] as $f ) {
				$field = new Field();
				$this->fields[] = $field->from_array( $f, false );

                $the_type = $field->type . ( $field->subtype ? ( '-' . $field->subtype ) : '' );
                $field->meta = $all_field_definitions[ $the_type ] ?? false;
			}

			return $this;
        }

        public function to_array(): array {
			$a = [
				'id'            => $this->id,
				'type'          => $this->type,
				'layout'        => $this->layout,
				'variables'     => $this->variables,
				'fields'        => [],
				'rule_groups'   => [],
			];

			foreach( $this->fields as $f ) {
				$a['fields'][] = $f->to_array();
			}

			foreach( $this->rules_groups as $rule_group ) {
				$rg = ['rules' => []];
				foreach($rule_group->rules as $rule) {
					$r = [
						'value'     => $rule->value,
						'condition' => $rule->condition,
						'subject'   => $rule->subject
					];
					$rg['rules'][] = $r;
				}
				$a['rule_groups'][] = $rg;
			}

			return $a;
		}

        public function has_gallery_image_rules(): bool {

        	if( ! isset( $this->layout[ 'enable_gallery_images' ] ) ) {
                return false;
            }

        	return $this->layout['enable_gallery_images'] == true && !empty( $this->layout['gallery_images'] );
        }

        public function has_variables(): bool {
        	return ! empty( $this->variables );
        }

        public function get_gallery_image_rules(): array {

        	$result = [
        		'images'    => [],
		        'rules'     => [],
	        ];

        	foreach ( $this->layout['gallery_images'] as $gallery_image ) {

        		if( empty( $gallery_image['id'] ) || empty( $gallery_image['values'] ) ) {
                    continue;
                }

        		$result['rules'][] = [
        			'values'    => $gallery_image['values'],
			        'image'     => $gallery_image['id']
		        ];

                if ( $gallery_image['source'] === 'upload' ) {
                    $found = false;
                    foreach ( $result['images'] as $image ) {
                        if ( $image['image_id'] === $gallery_image['id'] ) {
                            $found = true;
                            break;
                        }
                    }
                    if( ! $found ) {
                        $result['images'][] = array_merge( 
                            wc_get_product_attachment_props( $gallery_image['id'] ), 
                            [ 'image_id' => $gallery_image['id'] ] 
                        );
                    }
                }
        	}

        	return $result;

        }
    }
}