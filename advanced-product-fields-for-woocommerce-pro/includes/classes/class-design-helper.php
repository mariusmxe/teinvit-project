<?php

namespace SW_WAPF_PRO\Includes\Classes {

        class Design_Helper
    {

                public static function get_design_config(): array {
            return [
                [
                    'title' => __('Layout', 'sw-wapf'),
                    'desc' => __('This section contains basic layout settings.', 'sw-wapf'),
                    'settings' => [
                        [
                            'id' => 'apf-margin-bottom',
                            'label' => __('Row gap', 'sw-wapf'),
                            'tooltip' => __(  'Define the bottom margin between fields.', 'sw-wapf' ),
                            'type' => 'unit',
                            'units' => [ 'px','rem' ],
                            'min' => 0,
                            'max' => 50,
                            'start_with' => '15px',
                            'fallback' => '15px',
                        ]
                    ]
                ], [
                    'title' => __('Field labels', 'sw-wapf'),
                    'desc' => __('The field label is the title which (usually) appears above the input field.', 'sw-wapf'),
                    'settings' => [
                        [
                            'id' => 'apf-label-size',
                            'label' => __('Font size', 'sw-wapf'),
                            'type' => 'unit',
                            'units' => [ 'px','rem' ],
                            'min' => 0,
                            'max' => 50,
                            'start_with' => '1rem',
                            'fallback' => '1rem',
                        ], [
                            'id' => 'apf-label-color',
                            'label' => __('Label color', 'sw-wapf'),
                            'type' => 'color',
                            'start_with' => null,
                            'value_def' => [
                                'id'        => 'inherit',
                                'label'     => __( 'Inherit from theme', 'sw-wapf' ),
                            ],
                            'fallback' => 'inherit',
                            'allowClear' => true,
                        ], [
                            'id' => 'apf-label-weight',
                            'label' => __('Font weight', 'sw-wapf'),
                            'type' => 'dropdown',
                            'options' => [
                                ['value' => 'normal', 'label' => __('400 (normal)', 'sw-wapf')],
                                ['value' => '500', 'label' => __('500 (medium)', 'sw-wapf')],
                                ['value' => '600', 'label' => __('600 (semi-bold)', 'sw-wapf')],
                                ['value' => '700', 'label' => __('700 (bold)', 'sw-wapf')]
                            ],
                            'start_with' => '500',
                            'fallback' => 'normal',
                        ]
                    ]
                ], [
                    'title' => __('Tooltips', 'sw-wapf'),
                    'desc' => __( 'These are the settings for the tooltips that may be shown for certain fields.', 'sw-wapf' ),
                    'settings' => [
                        [
                            'id' => 'apf-tooltip-bg',
                            'label' => __('Background color', 'sw-wapf'),
                            'type' => 'color',
                            'start_with' => '#121212',
                            'fallback' => '#121212',
                        ], [
                            'id' => 'apf-tooltip-color',
                            'label' => __('Text color', 'sw-wapf'),
                            'type' => 'color',
                            'start_with' => '#fff',
                            'fallback' => '#fff',
                        ], [
                            'id' => 'apf-tooltip-icon',
                            'label' => __('Icon color', 'sw-wapf'),
                            'type' => 'color',
                            'allowClear' => true,
                            'start_with' => null,
                            'fallback' => 'inherit',
                            'tooltip' => __(  'The tooltip is triggered by clicking/hovering an icon next to the field\'s label. You can define the icon\'s color here', 'sw-wapf' ),
                            'value_def' => [
                                'id'        => 'inherit',
                                'label'     => __( 'Inherit from theme', 'sw-wapf' ),
                            ],
                        ]
                    ]
                ], [
                    'title' => __('General input settings', 'sw-wapf'),
                    'desc' => __( 'Various settings for text input fields and dropdown lists.', 'sw-wapf' ),
                    'settings' => [
                        [
                            'id' => 'apf-input-color',
                            'label' => __('Text color', 'sw-wapf'),
                            'type' => 'color',
                            'tooltip' => __('Color of text inside input fields and dropdown lists.', 'sw-wapf'),
                            'start_with' => null,
                            'value_def' => [
                                'id'        => 'inherit',
                                'label'     => __( 'Inherit from theme', 'sw-wapf' ),
                            ],
                            'fallback' => 'inherit',
                            'allowClear' => true,
                        ],
 [
                            'id' => 'apf-radius',
                            'label' => __('Field corner radius', 'sw-wapf'),
                            'type' => 'unit',
                            'tooltip' => __('This setting changes the corner radius of text input fields, select (dropdown) lists, and number/quantity selectors.', 'sw-wapf'),
                            'min' => 0,
                            'max' => 50,
                            'start_with' => '4px',
                            'fallback' => '0',
                        ], [
                            'id' => 'apf-input-height',
                            'label' => __('Field height', 'sw-wapf'),
                            'type' => 'unit',
                            'tooltip' => __('This setting changes the height of text input fields and select (dropdown) lists.', 'sw-wapf'),
                            'min' => 10,
                            'max' => 50,
                            'start_with' => '38px',
                            'fallback' => '38px',
                        ], [
                            'id' => 'apf-input-border-width',
                            'label' => __('Border thickness', 'sw-wapf'),
                            'type' => 'border-unit',
                            'min' => 0,
                            'max' => 20,
                            'start_with' => '2px',
                            'fallback' => 0,
                            'tooltip' => __( 'If you don\'t want a border, set this to zero or click the "clear" button.', 'sw-wapf' )
                        ],
 [
                            'id' => 'apf-input',
                            'label' => __('Interaction style', 'sw-wapf'),
                            'type' => 'states',
                            'tooltip' => __('Define styles for the different input states: default and focussed (when typing).', 'sw-wapf'),
                            'states' => [
                                ['id' => 'default', 'label' => __('Default', 'sw-wapf')],
                                ['id' => 'foc', 'label' => __('Focus', 'sw-wapf')],
                            ],
                            'inner_settings' => [
                                [
                                    'partial_id' => 'bg',
                                    'label' => __('Input background color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'states' => ['default'],
                                    'start_with' => ['#ffffff'],
                                    'fallback' => ['transparent' ],
                                    'value_def' => [
                                        [
                                            'id'        => 'transparent',
                                            'label'     => __( 'Transparent', 'sw-wapf' ),
                                        ], 
                                    ],
                                ],
                                [
                                    'partial_id' => 'border-color',
                                    'label' => __('Border color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'value_def' => [
                                        [
                                            'id'        => 'transparent',
                                            'label'     => __( 'Transparent', 'sw-wapf' ),
                                        ], [
                                            'id'        => 'setting:apf-input-border-color',
                                            'label'     => __( 'Same as "default" state', 'sw-wapf' ),
                                        ]
                                    ],
                                    'start_with' => ['#ddd','#121212'],
                                    'fallback' => ['transparent', 'setting:apf-input-border-color' ],
                                    'dependencies' => [
                                        ['setting' => 'apf-input-border-width', 'value' => '!empty']
                                    ],
                                ],
                            ]
                        ],
                    ]
                ],
                [
                    'title' =>  __('Dropdown (select) lists', 'sw-wapf'),
                    'desc' => __('Dropdown lists inherit most of their styling from the "general input settings" section.', 'sw-wapf'),
                    'settings' => [
                        [
                            'id' => 'apf-select-icon-color',
                            'label' => __('Arrow color', 'sw-wapf'),
                            'type' => 'color',
                            'allowClear' => true,
                            'start_with' => '#121212',
                            'fallback' => 'currentColor',
                            'value_def' => [
                                'id'        => 'currentColor',
                                'label'     => __( 'Inherit', 'sw-wapf' ),
                            ],
                            'tooltip' => __( 'A dropdown list has an arrow icon on the right side. Define its color here.', 'sw-wapf' )
                        ],
                    ]
                ], [
                    'title' => __('Checkboxes', 'sw-wapf'),
                    'desc' => __( 'Create custom checkboxes with the below design settings.', 'sw-wapf' ),
                    'settings' => [
                        [
                            'id' => 'apf-cb-display',
                            'label' => __('Display as', 'sw-wapf'),
                            'tooltip' => __('You can choose to style checkboxes or keep their default browser style.', 'sw-wapf'),
                            'type' => 'dropdown',
                            'options' => [
                                ['value' => 'default', 'label' => __('Browser standard (unstyled)', 'sw-wapf')],
                                ['value' => 'styled', 'label' => __('Styled checkboxes', 'sw-wapf')]
                            ],
                            'start_with' => 'styled',
                        ], [
                            'id' => 'apf-cb-radius',
                            'label' => __('Corner radius', 'sw-wapf'),
                            'type' => 'unit',
                            'min' => 0,
                            'max' => 50,
                            'start_with' => '4px',
                            'fallback' => '0px',
                            'dependencies' => [
                                ['setting' => 'apf-cb-display', 'value' => 'styled']
                            ],
                        ], [
                            'id' => 'apf-cb-border-width',
                            'label' => __('Border thickness', 'sw-wapf'),
                            'type' => 'border-unit',
                            'min' => 0,
                            'max' => 10,
                            'start_with' => '2px',
                            'fallback' => 0,
                            'dependencies' => [
                                ['setting' => 'apf-cb-display', 'value' => 'styled']
                            ],
                        ], [
                            'id' => 'apf-cb',
                            'label' => __('Interaction style', 'sw-wapf'),
                            'type' => 'states',
                            'tooltip' => __('Define styles for the different checkbox states: default, mouseover (hover), selected (checkbox is checked).', 'sw-wapf'),
                            'dependencies' => [
                                ['setting' => 'apf-cb-display', 'value' => 'styled']
                            ],
                            'states' => [
                                ['id' => 'default', 'label' => __('Default', 'sw-wapf')],
                                ['id' => 'hov', 'label' => __('Hover', 'sw-wapf')],
                                ['id' => 'sel', 'label' => __('Selected', 'sw-wapf')]
                            ],
                            'inner_settings' => [
                                [
                                    'partial_id' => 'bg',
                                    'label' => __('Background color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'start_with' => ['','','#121212'],
                                    'fallback' => 'transparent',
                                    'value_def' => [
                                        'id'        => 'transparent',
                                        'label'     => __( 'Transparent', 'sw-wapf' ),
                                    ],
                                ],
                                [
                                    'partial_id' => 'border-color',
                                    'label' => __('Border color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'start_with' => ['#ddd','#a4a4a4','#121212'],
                                    'fallback' => 'transparent',
                                    'value_def' => [
                                        'id'        => 'transparent',
                                        'label'     => __( 'Transparent', 'sw-wapf' ),
                                    ],
                                    'dependencies' => [
                                        ['setting' => 'apf-cb-border-width', 'value' => '!empty']
                                    ],
                                ],
                                [
                                    'partial_id' => 'tick-color',
                                    'label' => __('Tick color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => false,
                                    'states' => ['sel'],
                                    'start_with' => '#ffffff',
                                    'fallback' => '#ffffff',
                                    'value_def' => [
                                        'id'        => 'transparent',
                                        'label'     => __( 'Transparent', 'sw-wapf' ),
                                    ],
                                ]
                            ]
                        ],
                    ]
                ], [
                    'title' => __('Radio buttons', 'sw-wapf'),
                    'desc' => __( 'Create custom radio buttons with the below design settings.', 'sw-wapf' ),
                    'settings' => [
                        [
                            'id' => 'apf-radio-display',
                            'label' => __('Display as', 'sw-wapf'),
                            'tooltip' => __('You can choose to style radio buttons or keep their default browser style.', 'sw-wapf'),
                            'type' => 'dropdown',
                            'options' => [
                                ['value' => 'default', 'label' => __('Browser standard (unstyled)', 'sw-wapf')],
                                ['value' => 'styled', 'label' => __('Styled radio buttons', 'sw-wapf')]
                            ],
                            'start_with' => 'styled',
                        ], [
                            'id' => 'apf-radio-border-width',
                            'label' => __('Border thickness', 'sw-wapf'),
                            'type' => 'border-unit',
                            'min' => 0,
                            'max' => 10,
                            'start_with' => '2px',
                            'fallback' => 0,
                            'dependencies' => [
                                ['setting' => 'apf-radio-display', 'value' => 'styled']
                            ],
                        ], [
                            'id' => 'apf-radio',
                            'label' => __('Interaction style', 'sw-wapf'),
                            'type' => 'states',
                            'tooltip' => __('Define styles for the different button states: default, mouseover (hover), selected (radio button is checked).', 'sw-wapf'),
                            'dependencies' => [
                                ['setting' => 'apf-radio-display', 'value' => 'styled']
                            ],
                            'states' => [
                                ['id' => 'default', 'label' => __('Default', 'sw-wapf')],
                                ['id' => 'hov', 'label' => __('Hover', 'sw-wapf')],
                                ['id' => 'sel', 'label' => __('Selected', 'sw-wapf')]
                            ],
                            'inner_settings' => [
                                [
                                    'partial_id' => 'bg',
                                    'label' => __('Background color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'start_with' => ['','','#121212'],
                                    'fallback' => 'transparent',
                                    'value_def' => [
                                        'id'        => 'transparent',
                                        'label'     => __( 'Transparent', 'sw-wapf' ),
                                    ],
                                ], [
                                    'partial_id' => 'border-color',
                                    'label' => __('Border color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'start_with' => ['#ddd','#a4a4a4','#121212'],
                                    'fallback' => 'transparent',
                                    'value_def' => [
                                        'id'        => 'transparent',
                                        'label'     => __( 'Transparent', 'sw-wapf' ),
                                    ],
                                    'dependencies' => [
                                        ['setting' => 'apf-radio-border-width', 'value' => '!empty']
                                    ],
                                ], [
                                    'partial_id' => 'tick-color',
                                    'label' => __('Tick color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => false,
                                    'states' => ['sel'],
                                    'start_with' => '#ffffff',
                                    'fallback' => '#ffffff',
                                    'value_def' => [
                                        'id'        => 'transparent',
                                        'label'     => __( 'Transparent', 'sw-wapf' ),
                                    ],
                                ]
                            ]
                        ],
                    ]
                ],
                [
                    'title' => __('Number & quantity selector', 'sw-wapf'),
                    'desc' => __( 'Number and quantity fields have an option to display the input as a selector with plus and minus buttons. Customize their style here.', 'sw-wapf' ),
                    'settings' => [
                        [
                            'id' => 'apf-ns-width',
                            'label' => __('Width', 'sw-wapf'),
                            'type' => 'unit',
                            'units' => ['%','px'],
                            'min' => 80,
                            'max' => 500,
                            'start_with' => '100%',
                            'fallback' => '100%',
                            'tooltip' => __( 'Add a border to mark the buttons.','sw-wapf' )
                        ],  [
                            'id' => 'apf-ns-color',
                            'label' => __('Button text color', 'sw-wapf'),
                            'type' => 'color',
                            'allowClear' => true,
                            'start_with' => 'inherit',
                            'fallback' => 'inherit',
                            'value_def' => [
                                'id'        => 'inherit',
                                'label'     => __( 'Inherit from theme', 'sw-wapf' ),
                            ],
                        ], [
                            'id' => 'apf-ns-bg',
                            'label' => __('Button background color', 'sw-wapf'),
                            'type' => 'color',
                            'allowClear' => true,
                            'start_with' => 'transparent',
                            'fallback' => 'transparent',
                            'value_def' => [
                                'id'        => 'transparent',
                                'label'     => __( 'Transparent', 'sw-wapf' ),
                            ],
                        ], [
                            'id' => 'apf-ns-override',
                            'label' => __('Override input styles', 'sw-wapf'),
                            'type' => 'toggle',
                            'start_with' => false,
                            'fallback' => false,
                            'note' => __( 'The styles of the "general input settings" section are used. Turn this toggle on if you want to override those settings here.','sw-wapf' )
                        ], [
                            'id' => 'apf-ns-radius',
                            'label' => __('Corner radius', 'sw-wapf'),
                            'type' => 'unit',
                            'min' => 0,
                            'max' => 50,
                            'start_with' => '4px',
                            'fallback' => '0',
                            'dependencies' => [ [ 'setting' => 'apf-ns-override', 'value' => true ] ],
                        ], [
                            'id' => 'apf-ns-border-width',
                            'label' => __('Border thickness', 'sw-wapf'),
                            'type' => 'border-unit',
                            'min' => 0,
                            'max' => 20,
                            'start_with' => '2px',
                            'fallback' => 0,
                            'tooltip' => __( 'If you don\'t want a border, set it to zero or click the "clear" button.', 'sw-wapf' ),
                            'dependencies' => [ ['setting' => 'apf-ns-override', 'value' => true ] ],
                        ], [
                            'id' => 'apf-ns-boxed',
                            'label' => __('Boxed', 'sw-wapf'),
                            'type' => 'toggle',
                            'start_with' => false,
                            'fallback' => false,
                            'note' => __( 'Add inside borders to mark the buttons.','sw-wapf' ),
                            'dependencies' => [ 
                                ['setting' => 'apf-ns-override', 'value' => true ],
                                ['setting' => 'apf-ns-border-width', 'value' => '!empty']
                            ],
                        ], [
                            'id' => 'apf-ns',
                            'label' => __('Interaction style', 'sw-wapf'),
                            'type' => 'states',
                            'tooltip' => __('Define styles for the different input states: default and focussed (when typing in the field).', 'sw-wapf'),
                            'states' => [
                                ['id' => 'default', 'label' => __('Default', 'sw-wapf')],
                                ['id' => 'foc', 'label' => __('Focus', 'sw-wapf')],
                            ],
                            'dependencies' => [ ['setting' => 'apf-ns-override', 'value' => true ] ],
                            'inner_settings' => [
                                [
                                    'partial_id' => 'input-color',
                                    'label' => __('Input text color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'start_with' => 'inherit',
                                    'fallback' => 'inherit',
                                    'states' => ['default'],
                                    'value_def' => [
                                        [
                                            'id'        => 'inherit',
                                            'label'     => __( 'Inherit from theme', 'sw-wapf' ),
                                        ]
                                    ],
                                ], [
                                    'partial_id' => 'input-bg',
                                    'label' => __('Input background color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'start_with' => 'inherit',
                                    'fallback' => 'inherit',
                                    'states' => ['default'],
                                    'value_def' => [
                                        [
                                            'id'        => 'inherit',
                                            'label'     => __( 'Inherit from theme', 'sw-wapf' ),
                                        ]
                                    ],
                                ], [
                                    'partial_id' => 'border-color',
                                    'label' => __('Border color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'value_def' => [
                                        [
                                            'id'        => 'transparent',
                                            'label'     => __( 'Transparent', 'sw-wapf' ),
                                        ], [
                                            'id'        => 'setting:apf-ns-border-color',
                                            'label'     => __( 'Same as "default" state', 'sw-wapf' ),
                                        ]
                                    ],
                                    'start_with' => ['#ddd','#121212'],
                                    'fallback' => ['transparent', 'setting:apf-ns-border-color' ],
                                    'dependencies' => [
                                        ['setting' => 'apf-ns-border-width', 'value' => '!empty']
                                    ],
                                ],
                            ]
                        ],
                    ]
                ],
                [
                    'title' => __('Text Swatches', 'sw-wapf'),
                    'desc' => __('These are the settings for the text swatch field.', 'sw-wapf'),
                    'settings' => [
                        [
                            'id' => 'apf-ts-radius',
                            'label' => __('Corner radius', 'sw-wapf'),
                            'type' => 'unit',
                            'min' => 0,
                            'max' => 50,
                            'start_with' => '4px',
                            'fallback' => '0',
                        ], [
                            'id' => 'apf-ts-border-width',
                            'label' => __('Border thickness', 'sw-wapf'),
                            'type' => 'border-unit',
                            'min' => 0,
                            'max' => 10,
                            'start_with' => '2px',
                            'fallback' => 0,
                            'tooltip' => __( 'If you don\'t want a border, click the red clear button or set the thickness to zero.', 'sw-wapf' ),
                        ], [
                            'id' => 'apf-ts',
                            'label' => __('Interaction style', 'sw-wapf'),
                            'type' => 'states',
                            'states' => [
                                ['id' => 'default', 'label' => __('Default', 'sw-wapf')],
                                ['id' => 'hov', 'label' => __('Hover', 'sw-wapf')],
                                ['id' => 'sel', 'label' => __('Selected', 'sw-wapf')]
                            ],
                            'inner_settings' => [
                                [
                                    'partial_id' => 'color',
                                    'label' => __('Text color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'start_with' => [ null, null, '#fff' ],
                                    'fallback' => 'inherit',
                                    'value_def' => [
                                        'id'        => 'inherit',
                                        'label'     => __( 'Inherit from theme', 'sw-wapf' ),
                                    ],
                                ], [
                                    'partial_id' => 'bg',
                                    'label' => __('Background color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'value_def' => [
                                        'id'        => 'transparent',
                                        'label'     => __( 'Transparent', 'sw-wapf' ),
                                    ],
                                    'start_with' => ['transparent', 'transparent', '#121212' ],
                                    'fallback' => 'transparent',
                                ], [
                                    'partial_id' => 'border-color',
                                    'label' => __('Border color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'value_def' => [
                                        'id'        => 'transparent',
                                        'label'     => __( 'Transparent', 'sw-wapf' ),
                                    ],
                                    'dependencies' => [
                                        ['setting' => 'apf-ts-border-width', 'value' => '!empty']
                                    ],
                                    'start_with' => ['#ccc','#a4a4a4','#121212'],
                                    'fallback' => 'transparent',
                                ]
                            ]
                        ]
                    ]
                ], [
                    'title' => __('Image Swatches', 'sw-wapf'),
                    'desc' => __('These are the settings related to the image swatch field.', 'sw-wapf'),
                    'settings' => [
                        [
                            'id' => 'apf-is-radius',
                            'label' => __('Image corner radius', 'sw-wapf'),
                            'type' => 'unit',
                            'min' => 0,
                            'max' => 50,
                            'units' => ['px', '%'],
                            'start_with' => '4px',
                            'fallback' => 0,
                        ], [
                            'id' => 'apf-is-border-width',
                            'label' => __('Border thickness', 'sw-wapf'),
                            'type' => 'border-unit',
                            'min' => 0,
                            'max' => 50,
                            'start_with' => '2px',
                            'fallback' => 0,
                            'tooltip' => __( 'If you don\'t want a border, click the red clear button or set the thickness to zero.', 'sw-wapf' ),
                        ],  [
                            'id' => 'apf-is-gap',
                            'label' => __('Border gap', 'sw-wapf'),
                            'type' => 'toggle',
                            'tooltip' => __('Using a gap between the image and border helps distinguish the image from the site\'s background.', 'sw-wapf'),
                            'start_with' => true,
                            'fallback' => false,
                            'width' => 50,
                        ], [
                            'id' => 'apf-is',
                            'label' => __('Interaction style', 'sw-wapf'),
                            'type' => 'states',
                            'states' => [
                                ['id' => 'default', 'label' => __('Default', 'sw-wapf')],
                                ['id' => 'hov', 'label' => __('Hover', 'sw-wapf')],
                                ['id' => 'sel', 'label' => __('Selected', 'sw-wapf')]
                            ],
                            'inner_settings' => [
                                [
                                    'partial_id' => 'color',
                                    'label' => __('Text color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'start_with' => '',
                                    'fallback' => 'inherit',
                                    'value_def' => [
                                        'id'        => 'inherit',
                                        'label'     => __( 'Inherit from theme', 'sw-wapf' ),
                                    ],
                                ], [
                                    'partial_id' => 'bg',
                                    'label' => __('Background color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'fallback' => 'transparent',
                                    'start_with' => 'transparent',
                                    'value_def' => [
                                        'id'        => 'transparent',
                                        'label'     => __( 'Transparent', 'sw-wapf' ),
                                    ],
                                ], [
                                    'partial_id' => 'border-color',
                                    'label' => __('Border color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'start_with' => [ '#dddddd', '#a4a4a4', '#121212' ],
                                    'fallback' => 'transparent',
                                    'value_def' => [
                                        'id'        => 'transparent',
                                        'label'     => __( 'Transparent', 'sw-wapf' ),
                                    ],
                                    'dependencies' => [
                                        ['setting' => 'apf-is-border-width', 'value' => '!empty']
                                    ],
                                ]
                            ]
                        ]
                    ]
                ], [
                    'title' => __('Color swatches', 'sw-wapf'),
                    'desc' => __('These are the settings related to the color swatch field.', 'sw-wapf'),
                    'settings' => [
                        [
                            'id' => 'apf-cs-border-width',
                            'label' => __('Border thickness', 'sw-wapf'),
                            'type' => 'unit',
                            'min' => 1,
                            'max' => 20,
                            'start_with' => '2px',
                            'fallback' => 0,
                        ], [
                            'id' => 'apf-cs-gap',
                            'label' => __('Border gap', 'sw-wapf'),
                            'type' => 'toggle',
                            'tooltip' => __('Using a gap between the color and border helps distinguish the image from the site\'s background.', 'sw-wapf'),
                            'start_with' => true,
                            'fallback' => false,
                            'width' => 50,
                        ], [
                            'id' => 'apf-cs-gap-color',
                            'label' => __('Gap color', 'sw-wapf'),
                            'type' => 'color',
                            'start_with' => '#ffffff',
                            'fallback' => '#ffffff',
                            'value_def' => [
                                'id'        => '#ffffff',
                                'label'     => __( '#fff', 'sw-wapf' ),
                            ],
                            'width' => 50,
                            'dependencies' => [
                                [ 'setting' => 'apf-cs-gap', 'value' => true ]
                            ]
                        ], [
                            'id' => 'apf-cs',
                            'label' => __('Interaction style', 'sw-wapf'),
                            'type' => 'states',
                            'states' => [
                                ['id' => 'default', 'label' => __('Default', 'sw-wapf')],
                                ['id' => 'hov', 'label' => __('Hover', 'sw-wapf')],
                                ['id' => 'sel', 'label' => __('Selected', 'sw-wapf')]
                            ],
                            'inner_settings' => [
                                [
                                    'partial_id' => 'border-color',
                                    'label' => __('Selection color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'start_with' => ['#dddddd','#a4a4a4','#121212'],
                                    'fallback' => 'transparent',
                                    'value_def' => [
                                        'id'        => 'transparent',
                                        'label'     => __( 'Transparent', 'sw-wapf' ),
                                    ],
                                ],
                            ]
                        ],
                    ]
                ], [
                    'title' => __('Date picker (calendar)', 'sw-wapf'),
                    'desc' => __( 'These are the design settings related to the date picker field.', 'sw-wapf' ),
                    'settings' => [
                        [
                            'id' => 'apf-date-bg',
                            'label' => __('Background color', 'sw-wapf'),
                            'type' => 'color',
                            'allowClear' => false,
                            'start_with' => '#fff',
                            'fallback' => '#fff',
                        ], [
                            'id' => 'apf-date',
                            'label' => __('Interaction style', 'sw-wapf'),
                            'type' => 'states',
                            'tooltip' => __('Define styles for the different states.', 'sw-wapf'),
                            'states' => [
                                ['id' => 'default', 'label' => __('Default', 'sw-wapf')],
                                ['id' => 'hov', 'label' => __('Hover', 'sw-wapf')],
                                ['id' => 'sel', 'label' => __('Selected', 'sw-wapf')],
                            ],
                            'inner_settings' => [
                                [
                                    'partial_id' => 'color',
                                    'label' => __('Text color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'start_with' => [ 'currentColor', 'currentColor', '#ffffff'],
                                    'fallback' => [ 'currentColor', 'currentColor', '#ffffff'],
                                    'value_def' => [
                                        'id'        => 'currentColor',
                                        'label'     => __( 'Inherit from theme', 'sw-wapf' ),
                                    ],
                                ], [
                                    'partial_id' => 'bg',
                                    'label' => __('Background color', 'sw-wapf'),
                                    'type' => 'color',
                                    'allowClear' => true,
                                    'states'    => ['hov','sel'],
                                    'start_with' => [ '#dddddd', '#212121'],
                                    'fallback' => [ 'transparent', '#212121'],
                                    'value_def' => [
                                        'id'        => 'transparent',
                                        'label'     => __( 'Transparent', 'sw-wapf' ),
                                    ],
                                ]
                            ]
                        ],
                    ]
                ], [
                    'title' => __('File Upload', 'sw-wapf'),
                    'desc' => __('These are the design settings for the (modern) file upload field.', 'sw-wapf'),
                    'settings' => [
                        [
                            'id' => 'apf-file-color',
                            'label' => __('Text color', 'sw-wapf'),
                            'type' => 'color',
                            'allowClear' => true,
                            'start_with' => 'currentColor',
                            'fallback' => 'currentColor',
                            'value_def' => [
                                'id'        => 'currentColor',
                                'label'     => __( 'Inherit from theme', 'sw-wapf' ),
                            ],
                        ], [
                            'id' => 'apf-file-bg',
                            'label' => __('Background color', 'sw-wapf'),
                            'type' => 'color',
                            'allowClear' => true,
                            'start_with' => '',
                            'fallback' => 'none',
                            'value_def' => [
                                'id'        => 'transparent',
                                'label'     => __( 'Transparent', 'sw-wapf' ),
                            ],
                        ], [
                            'id' => 'apf-file-border',
                            'label' => __('Border', 'sw-wapf'),
                            'type' => 'border',
                            'tooltip' => __( 'Define the border color, style, and thickness with this setting. If you don\'t want a border, press the red "clear" button.', 'sw-wapf' ),
                            'start_with' => '2px dashed #121212',
                            'fallback' => 'none',
                            'value_def' => [
                                'id'        => 'none',
                                'label'     => __( 'None', 'sw-wapf' ),
                            ],
                        ], [
                            'id' => 'apf-progress-color',
                            'label' => __('Progress bar color', 'sw-wapf'),
                            'type' => 'color',
                            'start_with' => '#121212',
                            'fallback' => 'currentColor',
                            'value_def' => [
                                'id'        => 'currentColor',
                                'label'     => __( 'Inherit from theme', 'sw-wapf' ),
                            ],
                        ], [
                            'id' => 'apf-progress-bg',
                            'label' => __('Progress bar background color', 'sw-wapf'),
                            'type' => 'color',
                            'start_with' => '#ddd',
                            'fallback' => '#ddd',
                        ]
                    ]
                ],
                ];
        }

        public static function generate_css( $raw_settings = null ) {

            if( ! $raw_settings ) {

                $raw_settings = get_option( 'wapf_design_settings', false );

                if( empty( $raw_settings ) ) {
                    self::set_default_css();
                    return true;
                }

            }

            $setting_config = self::get_design_config();

            $settings_config = [

                                'apf-margin-bottom'             => 'css_unit',

                'apf-radius'                    => 'css_unit',
                'apf-error-color'               => 'color',
                'apf-input-height'              => 'css_unit',
                'apf-input-bg'                  => 'color',
                'apf-input-color'               => 'color',
                'apf-input-border-width'        => 'css_unit',
                'apf-input-border-color'        => 'color',
                'apf-input-border-color-foc'    => 'color',

                'apf-tooltip-bg'                => 'color',
                'apf-tooltip-color'             => 'color',
                'apf-tooltip-icon'              => 'color',

                'apf-label-size'                => 'css_unit',
                'apf-label-color'               => 'color',
                'apf-label-weight'              => ['normal', '500', '600', '700'],

                'apf-select-icon-color'         => 'color',

                'apf-ts-radius'                 => 'css_unit',
                'apf-ts-border-width'           => 'css_unit',
                'apf-ts-color'                  => 'color',
                'apf-ts-bg'                     => 'color',
                'apf-ts-border-color'           => 'color',
                'apf-ts-color-hov'              => 'color',
                'apf-ts-bg-hov'                 => 'color',
                'apf-ts-border-color-hov'       => 'color',
                'apf-ts-color-sel'              => 'color',
                'apf-ts-bg-sel'                 => 'color',
                'apf-ts-border-color-sel'       => 'color',

                'apf-is-gap'                    => 'bool',
                'apf-is-radius'                 => 'css_unit',
                'apf-is-border-width'           => 'css_unit',
                'apf-is-color'                  => 'color',
                'apf-is-bg'                     => 'color',
                'apf-is-border-color'           => 'color',
                'apf-is-color-hov'              => 'color',
                'apf-is-bg-hov'                 => 'color',
                'apf-is-border-color-hov'       => 'color',
                'apf-is-color-sel'              => 'color',
                'apf-is-bg-sel'                 => 'color',
                'apf-is-border-color-sel'       => 'color',

                'apf-cs-border-width'           => 'css_unit',
                'apf-cs-border-color'           => 'color',
                'apf-cs-border-color-hov'       => 'color',
                'apf-cs-border-color-sel'       => 'color',
                'apf-cs-gap'                    => 'bool',
                'apf-cs-gap-color'              => 'color',

                'apf-file-color'                => 'color',
                'apf-file-border'               => 'css_border',
                'apf-file-bg'                   => 'color',
                'apf-progress-color'            => 'color',
                'apf-progress-bg'               => 'color',

                'apf-cb-display'                => ['default', 'styled'],
                'apf-cb-border-width'           => 'css_unit',
                'apf-cb-radius'                 => 'css_unit',
                'apf-cb-border-color'           => 'color',
                'apf-cb-border-color-hov'       => 'color',
                'apf-cb-border-color-sel'       => 'color',
                'apf-cb-bg'                     => 'color',
                'apf-cb-bg-hov'                 => 'color',
                'apf-cb-bg-sel'                 => 'color',
                'apf-cb-tick-color-sel'         => 'color',

                'apf-radio-display'             => ['default', 'styled'],
                'apf-radio-border-width'        => 'css_unit',
                'apf-radio-border-color'        => 'color',
                'apf-radio-border-color-hov'    => 'color',
                'apf-radio-border-color-sel'    => 'color',
                'apf-radio-bg'                  => 'color',
                'apf-radio-bg-hov'              => 'color',
                'apf-radio-bg-sel'              => 'color',
                'apf-radio-tick-color-sel'      => 'color',


                'apf-ns-width'                  => 'css_unit',
                'apf-ns-color'                  => 'color',
                'apf-ns-bg'                     => 'color',
                'apf-ns-override'               => 'bool',
                'apf-ns-radius'                 => 'css_unit',
                'apf-ns-border-width'           => 'css_unit',
                'apf-ns-boxed'                  => 'bool',
                'apf-ns-input-bg'               => 'color',
                'apf-ns-input-color'            => 'color',
                'apf-ns-border-color'           => 'color',
                'apf-ns-border-color-foc'       => 'color',

                'apf-iq-gap'                    => 'css_unit',
                'apf-iq-img-radius'             => 'css_unit',

                                'apf-card-radius'               => 'css_unit',
                'apf-card-border-width'         => 'css_unit',
                'apf-card-shadow'               => [ 'none', '0 2px 4px rgba(0, 0, 0, 0.05),0 1px 2px rgba(0, 0, 0, 0.08)', '0 2px 6px rgba(0,0,0,.1)', '0 2px 8px rgba(0, 0, 0, 0.15)', '0 4px 12px rgba(0, 0, 0, 0.15)' ],
                'apf-card-bg'                   => 'color',
                'apf-card-bg-hov'               => 'color',
                'apf-card-bg-sel'               => 'color',
                'apf-card-border-color'         => 'color',
                'apf-card-border-color-hov'     => 'color',
                'apf-card-border-color-sel'     => 'color',
                'apf-card-icon-sel'             => 'bool',
                'apf-card-color'                => 'color',
                'apf-card-color-hov'            => 'color',
                'apf-card-color-sel'            => 'color',

                                'apf-cq-bg'                     => 'color',
                'apf-cq-color'                  => 'color',
                'apf-cq-radius'                 => 'css_unit',
                'apf-cq-border'                 => 'css_border',
                'apf-cq-shadow'                 => [ 'none', '0 2px 4px rgba(0, 0, 0, 0.05),0 1px 2px rgba(0, 0, 0, 0.08)', '0 2px 6px rgba(0,0,0,.1)', '0 2px 8px rgba(0, 0, 0, 0.15)', '0 4px 12px rgba(0, 0, 0, 0.15)' ],

                                'apf-cqns-color'                => 'color',
                'apf-cqns-bg'                   => 'color',
                'apf-cqns-override'             => 'bool',
                'apf-cqns-radius'               => 'css_unit',
                'apf-cqns-border-width'         => 'css_unit',
                'apf-cqns-input-bg'             => 'color',
                'apf-cqns-input-color'          => 'color',
                'apf-cqns-border-color'         => 'color',
                'apf-cqns-border-color-foc'     => 'color',

                                'apf-date-color'                => 'color',
                'apf-date-color-hov'            => 'color',
                'apf-date-color-sel'            => 'color',
                'apf-date-bg'                   => 'color',
                'apf-date-bg-hov'               => 'color',
                'apf-date-bg-sel'               => 'color',

                            ];

            $sanitized_settings = [];
            foreach ( $settings_config as $key => $config ) {
                $sanitized_settings[$key] = is_array( $config )
                    ? self::sanitize_css_setting( $key, 'options', $raw_settings, $setting_config, $config )
                    : self::sanitize_css_setting( $key, $config, $raw_settings, $setting_config );
            }

                        $write_result = self::write_css( $sanitized_settings );

            if( ! is_string( $write_result ) ) {
                self::clear_cache_plugins();
                return $sanitized_settings;
            }

            self::set_default_css();
            return $write_result;

        }

        public static function set_default_css() {
            $path = wapf_get_setting('path') . 'assets/css/';
            copy( $path . 'frontend-default.min.css', $path . 'frontend.min.css' );
        }

        public static function find_design_setting_by_id( $all_config, $setting_id ) {

            foreach ( $all_config as $section ) {
                foreach( $section['settings'] as $setting ) {

                    if( $setting['id'] === $setting_id ) {
                        return $setting;
                    }

                    if( $setting['type'] === 'states' ) {
                        foreach( $setting['inner_settings'] as $is ) {

                                                        $the_states = $is['states'] ?? array_column( $setting['states'], 'id' );

                                                       foreach( $setting['states'] as $state ) {

                                                                $isid = $setting['id'] . '-' . $is['partial_id'];
                                if( $state['id'] !== 'default' ) $isid .= ( '-' . $state['id'] );

                                if( $isid === $setting_id ) {

                                                                        $the_idx = array_search( $state['id'], $the_states );

                                                                        if( isset( $is['start_with'] ) ) {
                                        $is[ 'start_with' ] = is_array( $is['start_with'] ) ? $is['start_with'][$the_idx] : $is['start_with'];
                                    }
                                    if( isset( $is['fallback'] ) ) {
                                        $is[ 'fallback' ] = is_array( $is['fallback'] ) ? $is['fallback'][$the_idx] : $is['fallback'];
                                    }
                                    if( isset( $is['value_def'] ) ) {
                                        $is[ 'value_def' ] = array_keys( $is['value_def'] )[0] === 0  ? $is['value_def'][$the_idx] : $is['value_def'];
                                    }
                                    return $is;
                                }
                            }
                        }
                    }

                }
            }

            return null;

        }

        public static function clear_cache_plugins(): bool {

            try {
                if ( function_exists( 'rocket_clean_domain' ) ) {
                    rocket_clean_domain();
                }
                if ( function_exists( 'w3tc_flush_all' ) ) w3tc_flush_all();
                if ( function_exists( 'wp_cache_clear_cache' ) ) {
                    wp_cache_clear_cache( is_multisite() ? get_current_blog_id() : 0 );
                }
                if ( class_exists( '\LiteSpeed\Purge' ) && method_exists( '\LiteSpeed\Purge', 'purge_all' ) ) \LiteSpeed\Purge::purge_all();
                if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) \autoptimizeCache::clearall();
                if ( function_exists( 'wpfc_clear_all_cache' ) ) wpfc_clear_all_cache( true );
                if ( class_exists( '\Hummingbird\WP_Hummingbird' ) && method_exists( '\Hummingbird\WP_Hummingbird', 'flush_cache' ) ) \Hummingbird\WP_Hummingbird::flush_cache();
                if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache();
                if ( class_exists( 'PagelyCachePurge' ) && method_exists( 'PagelyCachePurge', 'purgeAll' ) ) \PagelyCachePurge::purgeAll();
                if ( class_exists( "WpeCommon" ) ) {
                    if ( method_exists( 'WpeCommon', 'purge_memcached' ) ) \WpeCommon::purge_memcached();
                    if ( method_exists( 'WpeCommon', 'clear_maxcdn_cache' ) ) \WpeCommon::clear_maxcdn_cache();
                    if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) \WpeCommon::purge_varnish_cache();
                }
            } catch( \Exception $e ) {
                return false; 
            }

            return true;
        }

        private static function sanitize( $data, $sanitizing_type, $return_fallback = null, $extra = [] ) {

            $data = trim( '' . $data ); 

            $is_valid_color = function( $c ) {

                if( in_array( $c, [ 'currentColor', 'currentcolor', 'transparent', 'inherit', 'none' ] ) )
                    return true;

                                $c = sanitize_hex_color( $c );

                return strlen( $c ) === 7 || strlen( $c ) === 4;

            };

                        switch( $sanitizing_type ) {

                                case 'hex': case 'color':
                    return $is_valid_color( $data ) ? $data : $return_fallback;

                                    case 'css_unit':
                    $data = self::sanitize_css_unit( $data );
                    return empty( $data ) ? $return_fallback : $data;

                                    case 'css_border': 
                    if( $data === 'none' ) return 'none';
                    $parts              = explode(' ', $data );
                    $unit               = self::sanitize_css_unit( $parts[0] );
                    $border_type_exists = in_array( $parts[1], [ 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset', 'none', 'hidden' ] );
                    $is_valid_color     = $is_valid_color( $parts[2] );

                                        return !empty( intval( $unit ) ) && $border_type_exists && $is_valid_color ? join( ' ', $parts ) : $return_fallback;

                               default: return Helper::sanitize( $data, $sanitizing_type, $return_fallback, $extra );

                            }

                    }

        private static function sanitize_css_unit( $str ) {

            $allowed_units = ['px', 'em', 'rem', '%', 'pt'];

            $pattern = '/^(\d+(\.\d+)?)(px|rem|em|%|pt)$/';

            $font_size = trim( $str );

            if (preg_match($pattern, $font_size, $matches)) {
                $value = floatval( Helper::normalize_string_decimal( $matches[1]) ); 
                $unit = $matches[3];           

                if ($value > 0 && in_array($unit, $allowed_units, true)) {
                    return '' . $value . $unit; 
                }
            }

            return false;

        }

        private static function write_css( $design_settings ) {

            $source_file        = wapf_get_setting('path') . 'assets/css/frontend-themed.min.css';
            $destination_file   = wapf_get_setting('path') . 'assets/css/frontend.min.css';
            $css_divider        = '/*! CSS_VAR_DIVIDER */';

            if ( ! file_exists( $source_file ) || ! is_readable( $source_file ) ) {
                return __( 'CSS file can not be read.', 'sw-wapf' );
            }

            if( ! is_writable( $source_file ) ) {
                return __( 'CSS file is not writable.', 'sw-wapf' );
            }

            $existing_css = file_get_contents( $source_file );

            if( empty( $existing_css ) ) {
                return __( 'Existing CSS content is empty.', 'sw-wapf' );
            }

            $split = explode( $css_divider, $existing_css );

            if( ! is_array( $split ) || empty( $split ) || count( $split ) !== 2 || strlen( $split[1] ) < 50 ) {
                return __( 'Could not find write-position in the CSS file.', 'sw-wapf' );
            }

            $generated_css = self::design_settings_to_variables_css( $design_settings );

            if( empty( $generated_css ) || strlen( $generated_css ) < 20 ) {
                return __( 'Could not generate the necessary CSS variables.', 'sw-wapf' );
            }

                        $new_css = trim( $generated_css ) . $css_divider . trim( $split[1] );

            $write_result = file_put_contents( $destination_file, $new_css, LOCK_EX );

            if( $write_result === false || ( is_numeric( $write_result) && $write_result < 2000 ) ) {

                return __( 'Couldn\'t write the result to the CSS file.', 'sw-wapf' );

            }

            return true;

        }

                private static function print_var( $var, $value ) {

                        $var = '--' . esc_html( $var ) . ':';

                        if( substr( $value, 0, 8 ) === 'setting:' ) {
                $id =  str_replace( 'setting:', '', $value );
                return $var . 'var(--' . esc_html( sanitize_text_field( $id ) ) . ')';
            }

            $var .= esc_html( $value );

                        return $var;

                    }

        private static function design_settings_to_variables_css( $design_settings ): string {

            $vars = [];

                        foreach ( [
              'apf-tooltip-bg', 'apf-tooltip-color', 'apf-margin-bottom', 
                'apf-input-border-color','apf-input-border-color-foc', 'apf-input-height', 'apf-input-bg', 'apf-input-color',
                'apf-radius', 'apf-tooltip-icon', 'apf-label-color', 'apf-label-size', 'apf-label-weight', 'apf-ts-radius', 'apf-ts-color', 
                'apf-ts-color-hov', 'apf-ts-color-sel', 'apf-ts-bg', 'apf-ts-bg-hov', 'apf-ts-bg-sel', 
                'apf-ts-border-color-hov', 'apf-ts-border-color-sel', 
                'apf-ns-width', 'apf-ns-color', 'apf-ns-bg',
                'apf-is-radius', 'apf-is-border-color-sel',
                'apf-is-border-color-hov', 'apf-is-color', 'apf-is-color-hov', 'apf-is-color-sel', 'apf-is-bg', 
                'apf-is-bg-hov', 'apf-is-bg-sel','apf-cs-select','apf-cs-select-hov','apf-cs-select-sel', 
                'apf-progress-bg', 'apf-progress-color', 'apf-file-bg', 'apf-file-border', 'apf-file-color',
                'apf-cs-border-color-hov', 'apf-cs-border-color-sel',
                'apf-iq-gap', 'apf-iq-img-radius', 
                'apf-card-shadow', 'apf-card-bg', 'apf-card-bg-hov', 'apf-card-bg-sel', 'apf-card-radius', 'apf-card-border-color-hov', 'apf-card-border-color-sel', 'apf-card-color', 'apf-card-color-hov', 'apf-card-color-sel',
                          'apf-cq-bg', 'apf-cq-color', 'apf-cq-radius', 'apf-cq-shadow', 'apf-cq-border',  'apf-cqns-bg', 
                'apf-date-color','apf-date-color-hov', 'apf-date-color-sel', 'apf-date-bg','apf-date-bg-hov','apf-date-bg-sel' ] as $var ) {
                    if( ! empty( $design_settings[ $var ] ) ) {
                        $vars[] = self::print_var( $var, $design_settings[ $var ] );
                    }
            }

                        $input_border = self::create_border( $design_settings['apf-input-border-width'], $design_settings['apf-input-border-color'] );
            $vars[] = '--apf-input-border:'.$input_border;

            $text_swatch_border = self::create_border( $design_settings['apf-ts-border-width'], $design_settings['apf-ts-border-color'] );
            $vars[] = '--apf-ts-border:'.$text_swatch_border;

            $img_swatch_border = self::create_border( $design_settings['apf-is-border-width'], $design_settings['apf-is-border-color'] );
            $vars[] = '--apf-is-border:'.$img_swatch_border;

                        $color_swatch_border = self::create_border( $design_settings['apf-cs-border-width'], $design_settings['apf-cs-border-color'] );
            $vars[] = '--apf-cs-border:'.$color_swatch_border;

            $img_card_border = self::create_border( $design_settings['apf-card-border-width'], $design_settings['apf-card-border-color'] );
            $vars[] = '--apf-card-border:'.$img_card_border;

            $date_border_match = self::get_matching_border_color( $design_settings[ 'apf-date-bg' ] ?? '#ffffff' );
            $vars[] = '--apf-date-border-color:' . $date_border_match;
            $vars[] = '--apf-date-color-muted:' . self::hex_to_rgba( empty( $design_settings[ 'apf-date-color' ] ) ||  $design_settings[ 'apf-date-color' ] === 'currentColor' ? '#212121' : $design_settings[ 'apf-date-color' ], .45 );
            $date_css = self::get_datepicker_dropdown_icon( $design_settings[ 'apf-date-color' ] ?? '#212121' );

                    if( ! empty( $design_settings['apf-ns-override'] ) ) {

                                $ns_border = self::create_border( $design_settings['apf-ns-border-width'], $design_settings['apf-ns-border-color'] );
                $vars[] = '--apf-ns-border:' . $ns_border;

                if( $design_settings['apf-ns-boxed'] ) {
                    $vars[] = '--apf-ns-border-inner: var(--apf-ns-border)';
                }

                                foreach ( [ 'apf-ns-radius', 'apf-ns-border-color-foc', 'apf-ns-input-bg' , 'apf-ns-input-color' ] as $var ) {
                    if( ! empty( $design_settings[ $var ] ) ) {
                        $vars[] = self::print_var( $var, $design_settings[ $var ] );
                    }
                }
            }

                        $cqns_vars = [];

            if( ! empty( $design_settings['apf-cqns-override'] ) ) {

                $cqns_border = self::create_border( $design_settings['apf-cqns-border-width'], $design_settings['apf-cqns-border-color'] );
                $cqns_vars[] = '--apf-ns-border:' . $cqns_border;

                if( ! empty( $design_settings['apf-cqns-border-width'] ) && ( $design_settings['apf-cqns-border-width'] != '0px' || $design_settings['apf-cqns-border-width'] != '0rem' ) ) {
                    $cqns_vars[] = '--apf-ns-border-inner: var(--apf-ns-border)';
                } else $cqns_vars[] = '--apf-ns-border-inner: none';

                                foreach ( [ 'apf-cqns-width', 'apf-cqns-color', 'apf-cqns-bg', 'apf-cqns-radius', 'apf-cqns-border-color-foc', 'apf-cqns-input-bg' , 'apf-cqns-input-color' ] as $var ) {
                    if( ! empty( $design_settings[ $var ] ) ) {
                        $cqns_vars[] = str_replace( '-cqns-', '-ns-', self::print_var( $var, $design_settings[ $var ] ) );
                    }
                }

            }

                        $checkbox_style = '';
            $radio_style = '';
            $card_icon_style = '';

            if( $design_settings['apf-cb-display'] === 'styled' ) {
                $checkbox_border = self::create_border( $design_settings['apf-cb-border-width'], $design_settings['apf-cb-border-color'] );
                foreach ( [ 'apf-cb-radius', 'apf-cb-bg', 'apf-cb-bg-hov', 'apf-cb-bg-sel', 'apf-cb-border-color-hov', 'apf-cb-border-color-sel' ] as $var ) {
                    if( ! empty( $design_settings[ $var ] ) )
                        $vars[] = self::print_var( $var, $design_settings[ $var ] );
                }
                $vars[] = '--apf-cb-border:'.$checkbox_border;
                $checkbox_style = self::get_checkbox_style_css( $design_settings['apf-cb-tick-color-sel'] ?? '#000000' );
            }

            if( $design_settings['apf-radio-display'] === 'styled' ) {
                $radio_border = self::create_border( $design_settings['apf-radio-border-width'], $design_settings['apf-radio-border-color'] );
                foreach ( [ 'apf-radio-bg', 'apf-radio-bg-hov', 'apf-radio-bg-sel', 'apf-radio-border-color-hov', 'apf-radio-border-color-sel' ] as $var ) {
                    if( ! empty( $design_settings[ $var ] ) )
                        $vars[] = self::print_var( $var, $design_settings[ $var ] );
                }
                $vars[] = '--apf-radio-border:'.$radio_border;
                $radio_style = self::get_radio_style_css( $design_settings['apf-radio-tick-color-sel'] ?? '#000000' );
            }

                        if( $design_settings['apf-card-icon-sel'] ) {
                $card_icon_style = self::get_card_icon( $design_settings['apf-card-border-color-sel'] ?? 'currentColor' );
            }

                        $select_style = self::get_select_style_css( $design_settings['apf-select-icon-color'] ?? 'currentColor' );

            if( ! empty( $design_settings['apf-is-radius'] ) && $design_settings['apf-is-radius'] !== '0' && $design_settings['apf-is-radius'] !== '0px' ) {
                $vars[] = '--apf-is-inner-radius:' . ( strpos(  $design_settings['apf-is-radius'], '%') !== false ? $design_settings['apf-is-radius'] : ( (intval( $design_settings['apf-is-radius'] ) / 2) . 'px ' ) );
            }

                        if( $design_settings['apf-is-gap'] ) {
                $vars[] = '--apf-is-padding:3px';
            }

                        if( $design_settings['apf-cs-gap'] ) {
                $vars[] = '--apf-cs-gap: inset 0 0 0 3px ' . $design_settings['apf-cs-gap-color'];
            }

                        $css = ':root{'. join(';', $vars ) .'}';

                        if( ! empty( $cqns_vars ) ) {
                $css .= '.wapf .wapf-card{' . join( ';', $cqns_vars ) . '}';
            }

                        return  $css . $checkbox_style . $radio_style . $select_style . $card_icon_style . $date_css;

        }

        private static function hex_to_rgba( $hex, $opacity ) {

                        $split_hex_color = str_split( str_replace( '#', '',$hex ), 2 ); 	

                     return sprintf(
                'rgba(%s,%s,%s,%s)',
                hexdec( $split_hex_color[0] ), 
                hexdec( $split_hex_color[1] ), 
                hexdec( $split_hex_color[2] ), 
                $opacity
            );
        }

                private static function create_border( $width, $color, $style = 'solid' ): string {

            if( empty( $width ) || empty( $color ) ) {
                return 'none';
            }

            $width_int = intval( str_replace('px','', ''.$width ) );

            if( is_nan( $width_int ) || empty( $width_int ) ) {
                return 'none';
            }

            return $width_int . 'px ' . $style . ' ' . $color;

        }

                private static function sanitize_css_setting( $id, $sanitization_type, $posted_settings, $settings_config, $extra = null ) {

            $setting_config = self::find_design_setting_by_id( $settings_config, $id );

                        if( ! $setting_config ) {
                return null;
            }

                        if( ! isset( $posted_settings[ $id ] ) || ( is_string( $posted_settings[ $id ] ) && trim( $posted_settings[ $id ] ) === '' ) ) {

                return $setting_config[ 'start_with' ] ?? null;

                            }

            if( substr( $posted_settings[ $id ], 0, 8 ) === 'setting:' ) {
                $new_id =  str_replace( 'setting:', '', $posted_settings[ $id ] );
                return 'setting:' . sanitize_text_field( $new_id );
            }

                        return self::sanitize( $posted_settings[ $id ], $sanitization_type, $setting_config[ 'fallback' ] ?? null, $extra );

        }

                private static function get_datepicker_dropdown_icon( $color ): string {
           return "body .wapf-dp-my select.wapf-dp-month{background-image:url(\"data:image/svg+xml,%3Csvg fill='%23" . str_replace( '#', '', $color ) . "' xmlns=\'http://www.w3.org/2000/svg' viewBox='0 0 448 512'%3E%3Cpath d='M224 353.9l17-17L401 177l17-17L384 126.1l-17 17-143 143L81 143l-17-17L30.1 160l17 17L207 337l17 17z'/%3E%3C/svg%3E\")!important;}";
        }

                private static function get_select_style_css( $color ) {

                        $color = str_replace('#', '', '' . $color );

            return
                preg_replace('/ {2,}/', '',
                str_replace( [ "\r", "\n", "\t" ], '', "
                .wapf select{
                    background:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12px' height='12px' viewBox='0 0 448 512'%3E%3Cpath fill='%23" . $color . "' d='M201.4 374.6c12.5 12.5 32.8 12.5 45.3 0l160-160c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L224 306.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l160 160z'/%3E%3C/svg%3E\") calc(100% - 12px) 12px no-repeat;
                    margin:0;
                    cursor:pointer;
                    background-size:12px;
                    -webkit-appearance:none;
                    appearance:none;
                    color:inherit;
                }"
            ));

                    }

                private static function get_card_icon( $icon_color ) {

                        $icon_color = str_replace('#', '', '' . $icon_color );
            return
                preg_replace('/ {2,}/', '',
                str_replace( [ "\r", "\n", "\t" ], '', "
                @media (min-width: 768px) {.wapf-card.wapf-checked:not(.is-qty-select):after{
                  content:'';
                  position:absolute;
                  top:-.6rem;
                  left:-.6rem;
                  width:1.2rem;
                  height:1.2rem;
                  background:#fff;
                  border-radius:50px;
                  background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Cpath fill='%23" . $icon_color . "' d='M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM369 209L241 337c-9.4 9.4-24.6 9.4-33.9 0l-64-64c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l47 47L335 175c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9z'/%3E%3C/svg%3E\");
                }}
            " ));

                    }

                private static function get_checkbox_style_css( $checkmark_color ) {

            $checkmark_color = str_replace('#', '', '' . $checkmark_color );

                        return 
                preg_replace('/ {2,}/', '', 
                str_replace( [ "\r", "\n", "\t" ], '', "
            .wapf-checkbox input[type=checkbox]{
              position:absolute;
              opacity:0;
              width:1px;
              height:1px;
              padding:0;
            }
            .wapf-checkbox .wapf-custom{
              min-height:16px;
              min-width:16px;
              height:1.1em;
              width:1.1em;
              position:relative;
              display:inline-block;
              background:var(--apf-cb-bg, transparent);
              border-radius:var(--apf-cb-radius, 0);
              border:var(--apf-cb-border, none);
            }
            .wapf-checkbox .wapf-input-label:hover .wapf-custom{
              background-color:var(--apf-cb-bg-hov, transparent);
              border-color:var(--apf-cb-border-color-hov, transparent);
            }
            .wapf-checkbox input[type=checkbox]:checked + .wapf-custom{
              background-color:var(--apf-cb-bg-sel, transparent);
              border-color:var(--apf-cb-border-color-sel, transparent);
            }
            .wapf-checkbox input:checked + .wapf-custom:after{
              position:absolute;
              top:0;
              left:0;
              bottom:0;
              right:0;
              content:'';
              background:no-repeat center center url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 45.701 45.7'%3E%3Cpath fill='%23" . $checkmark_color . "' d='M20.687 38.332a5.308 5.308 0 0 1-7.505 0L1.554 26.704A5.306 5.306 0 1 1 9.059 19.2l6.928 6.927a1.344 1.344 0 0 0 1.896 0L36.642 7.368a5.308 5.308 0 0 1 7.505 7.504l-23.46 23.46z'/%3E%3C/svg%3E\");
              background-size:.58em;
            }"));

        }

        private static function get_radio_style_css( $checkmark_color ) {

            $checkmark_color = str_replace('#', '', '' . $checkmark_color );

            return
                preg_replace('/ {2,}/', '',
                str_replace( [ "\r", "\n", "\t" ], '', "
            .wapf-radio input[type=radio]{
              position:absolute;
              opacity:0;
              width:1px;
              height:1px;
              padding:0;
            }
            .wapf-radio .wapf-custom{
              min-height:16px;
              min-width:16px;
              height:1.1em;
              width:1.1em;
              position:relative;
              display:inline-block;
              background:var(--apf-radio-bg, transparent);
              border-radius:50px;
              border:var(--apf-radio-border, none);
            }
            .wapf-radio .wapf-input-label:hover .wapf-custom{
              background-color:var(--apf-radio-bg-hov, transparent);
              border-color:var(--apf-radio-border-color-hov, transparent);
            }
            .wapf-radio input[type=radio]:checked + .wapf-custom{
              background-color:var(--apf-radio-bg-sel, transparent);
              border-color:var(--apf-radio-border-color-sel, transparent);
            }
            .wapf-radio input:checked + .wapf-custom:after{
               content:'';
               position:absolute;
               top:0;
               left:0;
               width:100%;
               height:100%;
               background:no-repeat center url(\"data:image/svg+xml,%3Csvg viewBox='0 0 48 48' fill='%23" . $checkmark_color . "' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='24' cy='24' r='24' /%3E%3C/svg%3E\");
               background-size:.42em;
            }"));

        }

        private static function get_matching_border_color( $hex ): string {

            if( strpos( $hex, '#' ) !== 0 )
                $hex = '#ffffff';

                        $hex = ltrim($hex, '#');

            if( strlen( $hex ) === 3 ) {
                $hex = $hex . $hex;
            }

            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));

            $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

            if ($brightness > 128) {
                $borderR = max($r - 34, 0);
                $borderG = max($g - 34, 0);
                $borderB = max($b - 34, 0);
            } else {
                $borderR = min($r + 34, 255);
                $borderG = min($g + 34, 255);
                $borderB = min($b + 34, 255);
            }

            return sprintf("#%02x%02x%02x", $borderR, $borderG, $borderB);

                    }

    }
}