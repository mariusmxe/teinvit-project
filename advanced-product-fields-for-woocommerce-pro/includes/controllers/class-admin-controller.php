<?php

namespace SW_WAPF_PRO\Includes\Controllers {

	use SW_WAPF_PRO\Includes\Classes\Cache;
    use SW_WAPF_PRO\Includes\Classes\Config;
    use SW_WAPF_PRO\Includes\Classes\Design_Helper;
    use SW_WAPF_PRO\Includes\Classes\Field_Groups;
    use SW_WAPF_PRO\Includes\Classes\File_Upload;
	use SW_WAPF_PRO\Includes\Classes\Helper;
	use SW_WAPF_PRO\Includes\Classes\Html;
    use SW_WAPF_PRO\Includes\Classes\Licensing;
    use SW_WAPF_PRO\Includes\Classes\wapf_List_Table;
    use SW_WAPF_PRO\Includes\Classes\Woocommerce_Service;
    use SW_WAPF_PRO\Includes\Models\ConditionRule;
    use SW_WAPF_PRO\Includes\Models\ConditionRuleGroup;
    use SW_WAPF_PRO\Includes\Models\Field;
    use SW_WAPF_PRO\Includes\Models\FieldGroup;

    class Admin_Controller{ 

        private $licensing;
        private $notices = [];
        private $db_updates = [
            '2.0.0' => 'db_update_200_add_attachment_ids'
        ];

        public function __construct()
        {

	        add_action('init',                                                      [$this, 'plugin_updater']);
            add_action('admin_enqueue_scripts',                                     [$this, 'register_assets']);
            add_action('admin_menu',                                                [$this, 'admin_menus']);
            add_filter('plugin_action_links_' . wapf_get_setting('basename'), [$this, 'add_plugin_action_links']);
            add_action('admin_notices',                                             [$this, 'show_admin_notices']);
            add_action('init',                                                      [$this, 'load']);
	        add_action('admin_init',                                                [$this, 'deactivate_free_version']);
	        add_action('admin_init',                                                [$this, 'remove_unwanted_notifications']);

	        add_filter('set-screen-option',                             [$this, 'save_screen_options'], 10, 3);

            add_action('current_screen',                                [$this, 'setup_screen']);
            add_action('admin_notices',                                 [$this, 'display_preloader']);
            add_action('save_post_wapf_product',                        [$this, 'save_post'], 10, 3);

            add_filter('woocommerce_settings_tabs_array',               [$this, 'woocommerce_settings_tab'], 100);
            add_action('woocommerce_settings_wapf_settings',            [$this, 'woocommerce_settings_screen']);
            add_action('woocommerce_update_options_wapf_settings',      [$this, 'update_woo_settings']);
	        add_action('woocommerce_sections_wapf_settings',            [$this, 'add_sections_to_settings_page']);
            add_action( 'admin_init',                                   [$this, 'maybe_revert_design_changes']);

	        add_action( 'woocommerce_admin_field_wapf_license_key',     [$this, 'licence_key_admin_field']);
	        add_action( 'woocommerce_admin_field_wapf_design',          [$this, 'design_admin_field']);

            add_filter('woocommerce_product_data_tabs',                 [$this, 'add_product_tab']);
            add_action('woocommerce_product_data_panels',               [$this, 'customfields_options_product_tab_content']);
            add_action('woocommerce_process_product_meta',              [$this, 'save_fieldgroup_on_product']);

	        add_action('woocommerce_product_duplicate',                 [$this,'on_product_duplication'],10,2);

            add_action('wp_ajax_wapf_search_products',                  [$this, 'search_woo_products']);
            add_action('wp_ajax_wapf_search_products_conditional',      [$this, 'search_woo_products_for_group_conditional']);
            add_action('wp_ajax_wapf_search_coupons',                   [$this, 'search_woo_coupons']);
            add_action('wp_ajax_wapf_search_tag',                       [$this, 'search_woo_tags']);
            add_action('wp_ajax_wapf_search_cat',                       [$this, 'search_woo_categories']);
            add_action('wp_ajax_wapf_search_variations',                [$this, 'search_woo_variations']);
            add_action('wp_ajax_wapf_search_attributes',                [$this, 'search_woo_attributes']);

	        add_filter( 'pll_get_post_types',                           [$this, 'add_cpt_to_polylang'], 10, 2 );

	        add_action( 'woocommerce_product_options_pricing',          [$this, 'add_settings_to_pricing_tab']);
	        add_action( 'woocommerce_process_product_meta',             [ $this, 'save_single_product_data' ] );

	        add_action( 'woocommerce_order_item_add_action_buttons',    [ $this, 'add_order_item_action_buttons'] );

        }

        public function add_order_item_action_buttons( $order ) {

	        if( ! current_user_can( 'manage_woocommerce' ) ) {
		        return;
	        }

	        if( ! class_exists('ZipArchive')) {
		        return;
	        }

	        $order_items_options = wapf_get_options_from_order( $order );

	        if( empty( $order_items_options ) || ! is_array( $order_items_options ) ) {
	        	return;
	        }

	        $has_file_upload_fields = false;

	        foreach ( $order_items_options as $oio) {
	        	foreach ($oio['options'] as $field) {
			        if( $field['type']  === 'file') {
				        $has_file_upload_fields = true;
				        break;
			        }
		        }
	        }

	        if( $has_file_upload_fields) {

                $files = [];

                foreach ( $order_items_options as $oio ) {
                    foreach ( $oio['options'] as $field ) {
                        if( $field['type']  === 'file' && ! empty( $field['value'] ) ) {
                            $new_files = explode( ', ', $field['value'] );
                            foreach ( $new_files as $nf ) {
                                if( strpos( $nf, '<a href=') === 0 ) { 
                                    $nf = Helper::extract_url_from_a_tag($nf);
                                }
                                $the_file = $this->url_to_path( $nf );
                                if( file_exists( $the_file ) ) $files[] = $the_file;
                            }
                        }
                    }
                }

                if( ! empty( $files ) ) {

                    if( isset( $_GET['wapf_download_zip'] ) && $_GET['wapf_download_zip'] ) {

                        $result = File_Upload::create_zip( $files, 'files-order-' . $order->get_id() );

                        if( is_string( $result ) ) {

                            register_shutdown_function( function() use ( $result ) {
                               unlink( $result );
                            });

                            header( "Content-Type: application/zip" );
                            header( "Content-Disposition: attachment; filename=" . 'files-order-' . $order->get_id() . '.zip' );
                            header( "Content-Length: " . filesize( $result ) );
                            ob_clean();
                            flush();
                            readfile( $result );

                        }

                    }

                    if( isset( $_GET['wapf_delete_files'] ) && $_GET['wapf_delete_files'] ) {

                        File_Upload::delete_files( $files );

                        wp_safe_redirect( add_query_arg( ['post' => $order->get_id(), 'action' => 'edit' ], admin_url( 'post.php') ) );
                    }

                    $url = add_query_arg(
                        [
                            'post'	=> $order->get_id(),
                            'action'	=> 'edit',
                            'wapf_download_zip'	=> 'true'
                        ],
                        admin_url( 'post.php' )
                    );

                    printf(
                        '<a href="%s" class="button wapf-download-files">%s</a> <a href="#" data-order="%s" class="button wapf-delete-files">%s</a>',
                        esc_url( $url ),
                        __( 'Download files', 'sw-wapf' ),
                        $order->get_id(),
                        __( 'Delete files', 'sw-wapf' )
                    );

                    $delete_url = add_query_arg(
                        [
                            'post'	=> $order->get_id(),
                            'action'	=> 'edit',
                            'wapf_delete_files'	=> 'true'
                        ],
                        admin_url( 'post.php' )
                    );

                    $text = __("Are you sure you want to delete uploaded files from this order? This action can't be undone.", 'sw-wapf');

                    echo '<script>var wapfdbtn = document.querySelector(".wapf-delete-files");wapfdbtn.addEventListener("click", function(e){e.preventDefault();if( confirm("' . $text . '") ) { window.location.href="' . $delete_url . '"; } });</script>';

                }

	        }

        }

        private function url_to_path( $url ) {
            $parsed_url = parse_url( $url );
            if( empty( $parsed_url['path'])) return '';
            return ABSPATH . ltrim( $parsed_url['path'], '/');
        }

        public function add_settings_to_pricing_tab() {

            if( apply_filters( 'wapf/features/change_price_html', true ) ) {

                               woocommerce_wp_select(
                    [
                        'id' => '_wapf_price_display',
                        'label' => __( 'Price display', 'sw-wapf' ),
                        'desc_tip' => true,
                        'description' => __( 'How to display the price? You can display an extra label before/after the price. You can also hide the price completely, or replace it with your own text.', 'sw-wapd' ),
                        'options' => [
                            '' => __( 'Default', 'sw-wapf' ),
                            'hide' => __( 'Hide the price', 'sw-wapf' ),
                            'before' => __( 'Add a label before the price', 'sw-wapf' ),
                            'after' => __( 'Add a label after the price', 'sw-wapf' ),
                            'replace' => __( 'Replace WooCommerce price with text', 'sw-wapf' )
                        ]
                    ]
                );

                woocommerce_wp_text_input(
                    [
                        'id' => '_wapf_price_label',
                        'label' => __( 'Extra price label', 'sw-wapf' ),
                        'desc_tip' => true,
                        'description' => __( 'Additional or replacement text for the price display. If or how this is displayed depends on the setting above.', 'sw-wapf' ),
                    ]
                );
            }

                    }

        public function save_single_product_data( $post_id ) {

	        $product = wc_get_product( $post_id );

	        if( ! empty( $_POST['_wapf_price_label'] ) ) {
		        $product->update_meta_data( '_wapf_price_label', sanitize_text_field( $_POST['_wapf_price_label'] ) );
	        } else {
	        	$product->delete_meta_data( '_wapf_price_label' );
	        }

	        if( ! empty( $_POST['_wapf_price_display'] ) ) {
	        	$product->update_meta_data( '_wapf_price_display', sanitize_text_field( $_POST['_wapf_price_display'] ) );
	        } else {
	        	$product->delete_meta_data( '_wapf_price_display' );
	        }

	        $product->save();

        }

		public function add_cpt_to_polylang( $post_types, $is_settings ) {

		    if ( $is_settings ) {
			    unset( $post_types['wapf_product'] );
		    } else {
			    $post_types['wapf_product'] = 'wapf_product';
		    }

		    return $post_types;

	    }

                public function remove_unwanted_notifications() {

            if( isset( $_GET['page'] ) && $_GET['page'] === 'wc-settings' && isset( $_GET['tab'] ) && $_GET['tab'] === 'wapf_settings' ) {
                global $wp_filter;

                if (isset($wp_filter['admin_notices'])) {
                    foreach ( $wp_filter['admin_notices']->callbacks as $priority => $callbacks ) {
                        foreach ($callbacks as $id => $callback) {
                            if ( strpos( $id, 'woocommerce') !== false ) {
                                continue;
                            }
                            remove_action('admin_notices', $callback['function'], $priority);
                        }
                    }
                }
            }

        }

	    public function deactivate_free_version() {

		    		    if( function_exists('wapf') && current_user_can('activate_plugins') ) {
			    deactivate_plugins( 'advanced-product-fields-for-woocommerce/advanced-product-fields-for-woocommerce.php' );
		    }


		    		    }

        #region Basics

        public function register_assets() {

            $is_field_groups_page = isset( $_GET['page'] ) && $_GET['page'] === 'wapf-field-groups';
            $is_apf_settings_page = isset( $_GET['page'] ) && $_GET['page'] === 'wc-settings' && isset( $_GET['tab'] ) && $_GET['tab'] === 'wapf_settings';

            if( $is_field_groups_page || $is_apf_settings_page || $this->is_screen( 'wapf_product' ) || $this->is_screen( 'product' ) ) {

                                $url                    = wapf_get_setting('url') . 'assets/';
                $version                = wapf_get_setting('version');
                $is_design_settings     = $is_apf_settings_page && isset( $_GET['section'] ) && $_GET['section'] === 'design';
                $script_dependencies    = $is_design_settings ? [ 'jquery', 'wp-color-picker', 'wp-element', 'wp-components', 'wp-i18n', 'wp-data' ] : [ 'jquery', 'wp-color-picker' ]; 

                                    if( $this->is_screen( 'wapf_product' ) ) {
	                wp_enqueue_script( 'wapf-tiptip', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip.min.js', ['jquery'], $version,false );
                }

                                if( ! $is_apf_settings_page ) {

                    wp_enqueue_script( 'wapf-admin-js',  $url . 'js/admin.min.js', $script_dependencies, $version, false ); 
                    wp_enqueue_style( 'wapf-admin-css', $url . 'css/admin.min.css', [], $version );
                    wp_enqueue_media();

                    wp_enqueue_style( 'wp-color-picker' );

                                    }

                if( $is_design_settings ) {
                    wp_register_script('wapf-deps-only', false, $script_dependencies, $version, false);
                    wp_enqueue_script('wapf-deps-only');
                    wp_enqueue_style( 'wp-components' );
                }

                                wp_localize_script( 'wapf-admin-js', 'wapf_language', [
                    'title_required'        => __("Please add a field group title first.", 'sw-wapf'),
                    'fields_required'       => __("Please add some fields first.", 'sw-wapf'),
                ]);

                $info = Licensing::get_license_info();
                $key = $info !== null && ! empty( $info->key ) ? $info->key : '';

                                wp_localize_script( 'wapf-admin-js', 'wapf_config', [
                    'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
                    'isWooProductScreen'    => $this->is_screen('product'),
                    'key'                   => $key
                ]);

                wp_dequeue_script('autosave');

                add_action( 'admin_head', function() {
                    global $_wp_admin_css_colors;
                    $active_color_scheme = get_user_option('admin_color');
                    echo '<style>:root{ --apf-primary-color: ' . $_wp_admin_css_colors[ $active_color_scheme]->colors[2] . ';--apf-secondary-color: ' . Helper::lighten_darken_hex( $_wp_admin_css_colors[ $active_color_scheme]->colors[2], 0.2 ) . ';--apf-tertiary-color: ' . Helper::lighten_darken_hex( $_wp_admin_css_colors[ $active_color_scheme]->colors[2], 0.9 ) . ' }</style>';
                });

                            }

        }

        public function admin_menus() {

            $cap = wapf_get_setting('capability');

            $hook = add_submenu_page(
                'woocommerce',
                __('Product Fields','sw-wapf'),
                __('Product Fields','sw-wapf'),
                $cap,
                'wapf-field-groups',
                [$this,'render_field_group_list']
            );

            if($hook) {
	            add_action('load-' .$hook , [$this, 'add_screen_options']);
            }

        }

        public function add_screen_options() {
	        if(!$this->is_screen('woocommerce_page_wapf-field-groups'))
		        return;

	        $args = [
		        'label' => __('Field groups per page', 'sw-wapf'),
		        'default' => 20,
		        'option' => 'wapf_per_page'
	        ];

	        add_screen_option( 'per_page', $args );
        }

        public function save_screen_options($status, $option, $value) {
	        if ( 'wapf_per_page' === $option ) return $value;
	        return $status;
        }

        public function show_admin_notices() {

            foreach( $this->notices as $notice ) {
                echo '<div class="notice is-dismissible notice-' . esc_html($notice['class']) . '"><p>' . esc_html($notice['message']) . '</p></div>';
            }

        }

        public function plugin_updater() {

	        $doing_cron = defined( 'DOING_CRON' ) && DOING_CRON;
	        if ( ! current_user_can( 'manage_options' ) && ! $doing_cron ) {
		        return;
	        }

	        $this->licensing = new Licensing();

        }

        public function load() {

            $nonce = isset($_POST['_wapf_license_nonce']) ? $_POST['_wapf_license_nonce'] : false;

            if($nonce){

                if(isset($_REQUEST['wapf_license_activate']) && wp_verify_nonce($nonce,'wapf_license_activate')  ) {
                    $activated = $this->licensing->activate_license();

                    $notice = $activated === true ? __('License activated. You can now add custom fields by going to WooCommerce > Product Fields or by editing a product individually.','sw-wapf') : $activated;
                    $this->notices[] = [
                        'class' => $activated === true ? 'success' : 'error',
                        'message' => __($notice, 'sw-wapf')
                    ];
                }

                if(isset($_REQUEST['wapf_license_activate']) && wp_verify_nonce($nonce,'wapf_license_deactivate')){
                    $deactivated = $this->licensing->deactivate_license();
                    $this->notices[] = [
                        'class' => 'success',
                        'message' => __('License deactivated','sw-wapf')
                    ];
                }
            }

        }

        public function add_plugin_action_links($links) {
            $has_license = Licensing::get_license_info() != null;

            $links = array_merge( [
                '<a href="' . esc_url( admin_url( '/admin.php?page=wc-settings&tab=wapf_settings' ) ) . '">' . __( $has_license ? 'Settings' : 'Activate license', 'sw-wapf' ) . '</a>',
                '<a href="' . esc_url( admin_url( '/admin.php?page=wapf-field-groups' ) ) . '">' . __( 'Global fields', 'sw-wapf' ) . '</a>'
            ], $links );

            return $links;
        }

        public function maybe_duplicate() {

            if(empty($_GET['wapf_duplicate']))
                return false;

            $post_id = intval($_GET['wapf_duplicate']);
            if($post_id === 0)
                return false;

            $post = get_post($post_id);
            if(!$post)
                return false;

            $fg = Field_Groups::get_by_id($post_id);

            if($fg === null)
                return false;

			$this->make_unique($fg);

            remove_action('save_post_wapf_product', [$this, 'save_post'], 10);

	        Field_Groups::save($fg,$post->post_type,null,$post->post_title . ' - '. __('Copy','sw-wapf'), 'publish' );

            return true;
        }

        #endregion

        #region WooCommerce product backend

	    public function on_product_duplication($duplicate, $product) {

		    if($duplicate->meta_exists('_wapf_fieldgroup')) {

			    $field_group =  Field_Groups::process_data($duplicate->get_meta( '_wapf_fieldgroup', true, 'edit' ) );

			    $id_map = $this->make_unique($field_group);
			    $field_group->id = 'p_' . $duplicate->get_id();

			    update_post_meta($duplicate->get_id(),'_wapf_fieldgroup', Helper::wp_slash( $field_group->to_array() ) );

			    do_action('wapf/admin/after_product_duplication', $duplicate, $product, $field_group, $id_map);

		    }
	    }

        public function add_product_tab($tabs) {
            $tabs['customfields'] = [
                'label'		=> __( 'Custom fields', 'sw-wapf' ),
                'target'	=> 'customfields_options',
                'class'		=> apply_filters('wapf/admin/tab_classes', ['show_if_simple', 'show_if_variable'] ),
            ];
            return $tabs;
        }

        public function customfields_options_product_tab_content() {

	        $this->display_variables_help();

        	echo '<div id="customfields_options" class="panel woocommerce_options_panel">';

                        echo '<div class="wapf-admin">';

	        do_action('wapf/admin/product_tab_content_start');

            echo '<div class="wapf-product-admin-title" style="text-align: right;margin: 0;background: white;border: none;">';
            Html::partial('admin/tools', ['product_mode' => true] );
            echo '</div>';

	        echo '<h4 class="wapf-product-admin-title" style="margin-top: 0">' .  __('Fields','sw-wapf') .' &mdash; <span style="opacity:.5;">'.__('Add custom fields to this group.','sw-wapf').'</span>' . '</h4>';

            $is_licensed = Licensing::get_license_info() != null;
            if(!$is_licensed) {
                echo '<p>'. __('Thank you for installing our plugin. Please <a href="' . admin_url('/admin.php?page=wc-settings&tab=wapf_settings') . '">activate your license</a> first.', 'sw-wapf') . '</p>';
                echo '</div>';
                return;
            }
            $this->display_field_group_fields(true);

            echo '<div style="display:none;">';
            $this->display_field_group_conditions(true);
            echo '</div>';

            echo '<h4 class="wapf-product-admin-title">' .  __('Layout & design settings','sw-wapf') . '</h4>';
            $this->display_field_group_layout(true);

	        echo '<h4 class="wapf-product-admin-title"><a class="modal_help_icon" style="padding:5px;" href="#" onclick="javascript:event.preventDefault();jQuery(\'.wapf--varaible-help\').show();"><i class="dashicons-before dashicons-editor-help"></i></a>' .  __('Custom variables','sw-wapf') .' &mdash; <span style="opacity:.5;">'.__('Create dynamic variables to use with formula-based pricing','sw-wapf').'</span>' . '</h4>';
	        $this->display_field_group_variable_builder(true);

	        do_action('wapf/admin/product_tab_content_end');

            echo '</div>';
            echo '</div>';
        }

        public function save_fieldgroup_on_product($post_id) {

        	$product = wc_get_product($post_id);
        	if(!$product)
        		return;

        	if(!in_array($product->get_type(), apply_filters('wapf/admin/allowed_product_types',array('variable','simple'))))
        		return;

            $empty_fields = empty($_POST['wapf-fields']) || $_POST['wapf-fields'] === '[]';
            $empty_variables = empty($_POST['wapf-variables']) || $_POST['wapf-variables'] === '[]';

            if( ( $empty_fields && $empty_variables) ||
                empty($_POST['wapf-conditions']) ||
                empty($_POST['wapf-layout'])
            ) {
                delete_post_meta($post_id,'_wapf_fieldgroup');
                return;
            }

            $this->save($post_id, false);

        }

        #endregion

        #region WooCommerce setting page configuration

	    public function licence_key_admin_field($model) {

        	$license_id = isset($model['license_id']) ? sanitize_text_field($model['license_id']) : 'wapf_license';
			$has_license = isset($model['has_license']) ? $model['has_license'] : Licensing::get_license_info() != null;

		    echo Html::view('admin/licensing', [
			    'has_license' => $has_license,
			    'license_id' => $license_id
		    ]);
	    }

                public function design_admin_field( $model ) {

            echo Html::view('admin/design', $model );

                    }

	    public function add_sections_to_settings_page() {
		    global $current_section;

		    $license_info = Licensing::get_license_info();
		    $has_license = $license_info != null;

		    $sections = apply_filters('wapf/admin/settings_sections', [
		    	'wapf'      => __('General', 'sw-wapf'),
		    	'design'    => __('Design', 'sw-wapf'),
		    ]);

		    if(count($sections) <= 1 || !$has_license) return;

		    echo '<ul class="subsubsub">';

		    foreach( $sections as $id => $label ) {
		    	$last_key = array_keys($sections)[count($sections)-1];
			    echo '<li><a href="' . admin_url( 'admin.php?page=wc-settings&tab=wapf_settings&section=' . $id ) . '" class="' . ( ( $current_section === $id || ( ! $current_section && $id === 'wapf' ) ) ? 'current' : '' ) . '">' . $label . '</a> ' .($id === $last_key ? '' : ' | '). '</li>';
		    }

		    echo '</ul><br class="clear" />';

	    }

                public function maybe_revert_design_changes() {

            $current_tab        = empty( $_GET['tab'] ) ? false : sanitize_title( wp_unslash( $_GET['tab'] ) );
            $current_section    = empty( $_GET['section'] ) ? false : sanitize_title( wp_unslash( $_GET['section'] ) );
            $can_revert_changes = isset( $_POST['apf-revert-design'] ) && $_POST['apf-revert-design'] === 'apf_revert_design';

            if( $can_revert_changes && $current_tab === 'wapf_settings' && $current_section === 'design' && isset( $_POST['apf-revert-design'] ) ) {

                check_admin_referer( 'woocommerce-settings' );

                                delete_option( 'wapf_design_settings' );
                delete_option( 'wapf_design_settings_hash' );

                                Design_Helper::generate_css(); 

                \WC_Admin_Settings::add_message( 'Succesfully reset to theme defaults.' );

            }

                    }

                public function update_woo_settings() {

            if ( ! empty($_POST['wapf-design-settings'] ) ) {

                $raw_settings = wp_unslash( $_POST['wapf-design-settings'] );
                $settings = json_decode( $raw_settings, true );

                if ( json_last_error() === JSON_ERROR_NONE ) {

                                        $result = Design_Helper::generate_css( $settings );

                                        if( is_string( $result ) ) {
                        \WC_Admin_Settings::add_error( 'Something went wrong saving the design settings. Here\'s the error: ' . $result . ' Please try again or contact plugin support to help out.' );
                        return;
                    } else {
                        update_option( 'wapf_design_settings', $result, false );
                        update_option( 'wapf_design_settings_hash', md5( json_encode( $result ) ), false );
                    }
                } else {

                                        Design_Helper::set_default_css();

                                        \WC_Admin_Settings::add_error( 'Something went wrong saving the design settings. Please try again or contact plugin support to help out.' );
                    return;
                }
            }

            woocommerce_update_options( $this->get_settings() );

                    }

                public function woocommerce_settings_screen() {
            woocommerce_admin_fields( $this->get_settings() );
        }


                public function get_settings() {

        	global $current_section;

	        $license_info = Licensing::get_license_info();
	        $has_license = $license_info != null;

            $settings = [];

            if( ! $has_license ) {

	            $settings[] = [
		            'name'  => __( 'License key', 'sw-wapf' ),
		            'type'	=> 'wapf_license_key',
		            'id'	=> 'wapf_license_key',
		            'license_id' => 'wapf_license',
		            'has_license' => false,
	            ];

	            return $settings;

            }

            if($current_section === 'wapf' || $current_section === '') {

            	$settings[] = [
		            'name'  => __( 'License key', 'sw-wapf' ),
		            'type'	=> 'wapf_license_key',
		            'id'	=> 'wapf_license_key',
		            'license_id' => 'wapf_license',
		            'has_license' => $has_license,
	            ];

	            $settings[] = [
		            'name' => __( 'General settings', 'sw-wapf' ),
		            'type' => 'title',
	            ];

	            $settings[] = [
		            'name'     => __( 'Show in cart', 'sw-wapf' ),
		            'id'       => 'wapf_settings_show_in_cart',
		            'type'     => 'checkbox',
		            'default'  => 'yes',
		            'desc'     => __( "Show on customer's cart page.", 'sw-wapf' ),
		            'desc_tip' => __( 'When a user has filled out your fields, should they be summarized on their cart page after adding the product to their cart?', 'sw-wapf' )
	            ];

	            $settings[] = [
		            'name'     => __( 'Show on checkout', 'sw-wapf' ),
		            'id'       => 'wapf_settings_show_in_checkout',
		            'type'     => 'checkbox',
		            'default'  => 'yes',
		            'desc'     => __( "Show on the checkout page.", 'sw-wapf' ),
		            'desc_tip' => __( 'When a user has filled out your fields, should they be summarized on their checkout page?', 'sw-wapf' )
	            ];

	            $settings[] = [
		            'name'     => __( 'Show in mini cart', 'sw-wapf' ),
		            'id'       => 'wapf_settings_show_in_mini_cart',
		            'type'     => 'checkbox',
		            'default'  => 'no',
		            'desc'     => __( "Show in mini cart.", 'sw-wapf' ),
		            'desc_tip' => __( 'When a user has filled out your fields, should they be summarized on the mini (floating) cart?', 'sw-wapf' )
	            ];

	            $settings[] = [
		            'name'      => __( 'Display pricing summary', 'sw-wapf' ),
		            'id'        => 'wapf_pricing_summary',
		            'type'		=> 'select',
		            'desc_tip'	=> __( 'How to display the pricing summary on the product page', 'sw-wapf' ),
		            'default'	=> 'all',
		            'options'   => [
			            'lines' => __( 'Show 3-line summary below the options', 'sw-wapf' ),
			            'grand' => __( 'Show only grand total below the options', 'sw-wapf' ),
			            'hide'  => __( 'Hide the summary', 'woocommerce' ),
		            ],
	            ];

	            $settings[] = [
		            'name'     => __( 'Show price hints', 'sw-wapf' ),
		            'id'       => 'wapf_show_pricing_hints',
		            'type'     => 'checkbox',
		            'default'  => 'yes',
		            'desc'     => __( "Show price hints.", 'sw-wapf' ),
		            'desc_tip' => __( 'When an option changes the final product price, a pricing hint is shown next to the option. You can disable that here.', 'sw-wapf' )
	            ];

                $settings[] = [
                    'name'     => __( 'Pricing hint format', 'sw-wapf' ),
                    'type'     => 'text',
                    'id'       => 'wapf_hint_format',
                    'desc'     => __( 'The format for all pricing hints. Use {x} to represent the price.', 'sw-wapf' ),
                    'default'  => __( '(+{x})', 'sw-wapf' )
                ];

	            $settings[] = [
		            'name'     => __( 'Edit cart', 'sw-wapf' ),
		            'id'       => 'wapf_edit_cart',
		            'type'     => 'checkbox',
		            'default'  => 'no',
		            'desc'     => __( "Enable editing from cart.", 'sw-wapf' ),
		            'desc_tip' => __( 'When enabled, users can edit product options from their cart.', 'sw-wapf' )
	            ];

	            $settings[] = [
		            'type' => 'sectionend',
	            ];

	            $settings[] = [
		            'name' => __( 'Advanced field settings', 'sw-wapf' ),
		            'type' => 'title',
	            ];

	            $settings[] = [
		            'name'     => __( 'Modern uploader', 'sw-wapf' ),
		            'type'     => 'checkbox',
		            'id'       => 'wapf_upload_ajax',
		            'desc'     => __( "Enable the modern (ajax) uploader.", 'sw-wapf' ),
		            'desc_tip' => __( "Replace the default browser file upload with a more modern uploader. You may need to enable this if your theme is not compatible with the default file upload.", 'sw-wapf' ),
		            'default'  => 'no'
	            ];

	            $settings[] = [
		            'name'     => __( 'Must be logged in', 'sw-wapf' ),
		            'id'       => 'wapf_settings_upload_login',
		            'type'     => 'checkbox',
		            'default'  => 'no',
		            'desc'     => __( "Users must be logged in to upload files.", 'sw-wapf' ),
		            'desc_tip' => __( 'Customers must log in to your site before uploading files.', 'sw-wapf' )
	            ];

	            $settings[] = [
		            'name'     => __( 'Enable date fields', 'sw-wapf' ),
		            'desc'     => __( "Enable date fields.", 'sw-wapf' ),
		            'id'       => 'wapf_datepicker',
		            'type'     => 'checkbox',
		            'default'  => 'no',
		            'desc_tip' => __( 'The date field requires additional scripts. Enabling this will load those scripts and allow you to create date fields.', 'sw-wapf' )
	            ];

	            $settings[] = [
		            'name'     => __( 'Date format', 'sw-wapf' ),
		            'type'     => 'text',
		            'id'       => 'wapf_date_format',
		            'desc'     => __( 'Available date placeholders: mm, m, dd, d, yyyy, yy. You can include symbols (like dot, dash, or slash) but no extra letters or numbers.', 'sw-wapf' ),
		            'desc_tip' => __( 'What date format should be used for the date picker (both on the site and order admin screens)?', 'sw-wapf' ),
		            'default'  => __( 'mm-dd-yyyy', 'sw-wapf' )
	            ];

	            $settings[] = [
		            'type' => 'sectionend',
	            ];

	            $settings[] = [
		            'name' => __( 'labels', 'sw-wapf' ),
		            'type' => 'title',
	            ];

	            $settings[] = [
		            'name'     => __( '"Add to cart" button on shop page', 'sw-wapf' ),
		            'type'     => 'text',
		            'id'       => 'wapf_add_to_cart_text',
		            'desc_tip' => __( 'When a product has custom fields, what should the "add to cart" button text be on the shop page?', 'sw-wapf' ),
		            'default'  => __( 'Select options', 'sw-wapf' )
	            ];

	            $settings[] = [
		            'name'     => __( '"Must be logged in" text', 'sw-wapf' ),
		            'type'     => 'text',
		            'id'       => 'wapf_settings_upload_msg',
		            'desc_tip' => __( 'If users need to log in before uploading files, display a message to let them know.', 'sw-wapf' ),
		            'default'  => __( 'You need to be logged in to upload files.', 'sw-wapf' )
	            ];

	            $settings[] = [
		            'type' => 'sectionend',
	            ];

                	            $settings = apply_filters( 'wapf/settings', $settings );

                            } else if( $current_section === 'design' ) {

                $GLOBALS['hide_save_button'] = true;

                                $settings[] = [
                    'name' => __( 'Design settings', 'sw-wapf' ),
                    'type' => 'title',
                ];

                                $design_settings = Design_Helper::get_design_config();
                $design_setting_values  = get_option( 'wapf_design_settings', [] );

                foreach( $design_settings as $section ) {

                    foreach ( $section['settings'] as $setting ) {

                                                if( isset( $setting['start_with'] ) && ! isset( $design_setting_values[ $setting['id'] ] ) ) {
                            $design_setting_values[ $setting['id'] ] = $setting['start_with'];
                        }

                        if( $setting['type'] === 'states' ) {
                            foreach( $setting['inner_settings'] as $is ) {

                                $used_in_states = $is['states'] ?? array_column($setting['states'], 'id');

                                                                foreach( $setting['states'] as $state ) {

                                    $isid = $setting['id'] . '-' . $is['partial_id'];
                                    if( $state['id'] !== 'default' ) 
                                        $isid .= ( '-' . $state['id'] );

                                                                        $the_idx = array_search( $state['id'], $used_in_states );

                                    if( $the_idx === false ) continue;

                                                                        if( isset( $is['start_with'] ) && ! isset( $design_setting_values[ $isid ] ) ) {

                                                                                if( is_array(  $is['start_with'] ) && count( $is['start_with'] ) > 1 ) $v = $is['start_with'][ $the_idx ];
                                        else $v = ((array)$is['start_with'])[0];
                                        $design_setting_values[ $isid ] = $v;

                                    }
                                }

                            }
                        }

                                            }

                                    }

                                $settings[] = [
                    'name'              => __( 'Design settings', 'sw-wapf' ),
                    'type'	            => 'wapf_design',
                    'id'	            => 'wapf_design',
                    'settings'          => $design_settings,
                    'setting_values'    => $design_setting_values
                ];

                                $settings[] = [
                    'type' => 'sectionend',
                ];

                            } else {
            	$settings = apply_filters('wapf/admin/settings_' . $current_section, $settings);
            }

            return $settings;
        }

        public function woocommerce_settings_tab($tabs) {
            $tabs['wapf_settings'] = __( 'Product fields', 'sw-wapf' );
            return $tabs;
        }
        #endregion

        #region Ajax Functions

        public function search_woo_categories() {

            if( !current_user_can(wapf_get_setting('capability')) ) {
                echo json_encode([]);
                wp_die();
            }

            echo json_encode(Woocommerce_Service::find_category_by_name($_POST['term']));
            wp_die();
        }

        public function search_woo_tags() {

            if( !current_user_can(wapf_get_setting('capability')) ) {
                echo json_encode([]);
                wp_die();
            }

            echo json_encode(Woocommerce_Service::find_tags_by_name($_POST['term']));
            wp_die();
        }

        public function search_woo_coupons() {

            if( !current_user_can(wapf_get_setting('capability')) ) {
                echo json_encode([]);
                wp_die();
            }

            echo json_encode(Woocommerce_Service::find_coupons_by_name($_POST['term']));
            wp_die();
        }

        public function search_woo_variations() {

            if( !current_user_can(wapf_get_setting('capability')) ) {
                echo json_encode([]);
                wp_die();
            }

            echo json_encode(Woocommerce_Service::find_variations_by_name($_POST['term']));
            wp_die();
        }

	    public function search_woo_attributes() {

		    if( !current_user_can(wapf_get_setting('capability')) ) {
			    echo json_encode([]);
			    wp_die();
		    }

		    echo json_encode(Woocommerce_Service::find_attributes_by_name($_POST['term']));
		    wp_die();
	    }

        public function search_woo_products_for_group_conditional() {

            if( ! current_user_can( wapf_get_setting( 'capability' ) ) ) {
                echo json_encode( [] );
                wp_die();
            }

            echo json_encode( Woocommerce_Service::find_products_by_name( $_POST['term'], false, true ) );
            wp_die();
        }

        public function search_woo_products() {

            if( !current_user_can( wapf_get_setting( 'capability' ) ) ) {
                echo json_encode( [] );
                wp_die();
            }

            echo json_encode( Woocommerce_Service::find_products_by_name( $_POST['term'] ) );
            wp_die();
        }

        #endregion

        #region Save to Backend

        public function save_post($post_id, $post) {

            if (defined('DOING_AUTOSAVE') || is_int(wp_is_post_autosave($post)) || is_int(wp_is_post_revision($post))) {
                return;
            }

            if (defined('DOING_AJAX') && DOING_AJAX) {
                return;
            }

            if (isset($post->post_status) && $post->post_status === 'auto-draft')
                return;

            if( !current_user_can(wapf_get_setting('capability')) ) {
                return;
            }

            if(wp_verify_nonce($_POST['_wpnonce'],'update-post_' . $post_id) === false)
                return;

            $this->save($post_id, true);

        }

        private function save($post_id, $saving_cpt = true) {

            Cache::clear();

            $raw = [
                'id'            => $post_id,
                'fields'        => [],
                'conditions'    => [],
                'type'          => $_REQUEST['wapf-fieldgroup-type']
            ];

            if(isset($_POST['wapf-fields']))
                $raw['fields'] = json_decode(wp_unslash($_POST['wapf-fields']), true);

            if(isset($_POST['wapf-conditions']))
                $raw['conditions'] = json_decode(wp_unslash($_POST['wapf-conditions']), true);

            if(isset($_POST['wapf-layout']))
                $raw['layout'] = json_decode(wp_unslash($_POST['wapf-layout']), true);

	        if(isset($_POST['wapf-variables']))
		        $raw['variables'] = json_decode(wp_unslash($_POST['wapf-variables']), true);

            $fg = Field_Groups::raw_json_to_field_group($raw);

                        if( empty( $fg ) ) {
                return;
            }

            if( $saving_cpt ) {
                remove_action('save_post_wapf_product', [$this, 'save_post'], 10);

                               Field_Groups::save($fg,$_REQUEST['wapf-fieldgroup-type'], $post_id);

            } else { 
                $fg->id = 'p_' . $fg->id; 
                update_post_meta( $post_id, '_wapf_fieldgroup', Helper::wp_slash($fg->to_array()));
            }

        }

        #endregion

        #region Display functions

        public function display_preloader() {

            if( ! $this->is_screen( 'wapf_product' ) )
                return;

            echo '<div class="wapf-preloader" style="position: absolute;z-index: 2000;top:0;left:-20px;right: 0;height: 100%;background-color: rgba(0,0,0,.65);">';
            echo '<svg style="position: fixed;z-index:3000;top:30%;left:50%;margin-left:-23px;" width="45" height="45" viewBox="0 0 45 45" xmlns="http://www.w3.org/2000/svg" stroke="#fff"><g fill="none" fill-rule="evenodd" transform="translate(1 1)" stroke-width="2"><circle cx="22" cy="22" r="6" stroke-opacity="0"><animate attributeName="r" begin="1.5s" dur="3s" values="6;22" calcMode="linear" repeatCount="indefinite" /><animate attributeName="stroke-opacity" begin="1.5s" dur="3s" values="1;0" calcMode="linear" repeatCount="indefinite" /><animate attributeName="stroke-width" begin="1.5s" dur="3s" values="2;0" calcMode="linear" repeatCount="indefinite" /></circle><circle cx="22" cy="22" r="6" stroke-opacity="0"> <animate attributeName="r" begin="3s" dur="3s" values="6;22" calcMode="linear" repeatCount="indefinite" /><animate attributeName="stroke-opacity" begin="3s" dur="3s" values="1;0" calcMode="linear" repeatCount="indefinite" /><animate attributeName="stroke-width" begin="3s" dur="3s" values="2;0" calcMode="linear" repeatCount="indefinite" /></circle><circle cx="22" cy="22" r="8"><animate attributeName="r" begin="0s" dur="1.5s" values="6;1;2;3;4;5;6" calcMode="linear" repeatCount="indefinite" /></circle></g></svg>';
            echo '</div>';

			$this->display_conditions_help();
			$this->display_variables_help();

                    }

        public function setup_screen() {

            if($this->is_screen('woocommerce_page_wapf-field-groups')) {
            	if($this->maybe_duplicate()) {
            		wp_safe_redirect(admin_url('admin.php?page=wapf-field-groups'));
            		exit;
	            }
            }

            if( $this->is_screen( 'wapf_product' ) ) {

                add_filter('admin_body_class', function($classes) {
                    $classes .= ' wapf-admin';
                    return $classes;
                });

                add_meta_box(
                    'wapf-tools',
                    __('Tools','sw-wapf'),
                    [ $this, 'display_field_group_tools' ], 
                    'wapf_product',
                    'side',
                    'low'
                );

                add_meta_box(
                    'wapf-field-list',
                    __('Fields','sw-wapf') .' &mdash; <span style="opacity:.5;">'.__('Add custom fields to this group.','sw-wapf').'</span>',
                    [ $this, 'display_field_group_fields' ],
                    'wapf_product',
                    'normal',
                    'high'
                );

                add_meta_box(
                    'wapf-field-group-conditions',
	                '<a class="modal_help_icon" style="padding:5px;" href="#" onclick="javascript:event.preventDefault();jQuery(\'.wapf--conditions-help\').show();"><i class="dashicons-before dashicons-editor-help"></i></a>' . __('Conditions','sw-wapf') .' &mdash; <span style="opacity:.5;">'.__('When should this field group be displayed?','sw-wapf').'</span>',
                    [ $this, 'display_field_group_conditions' ],
                    'wapf_product',
                    'normal',
                    'high'
                );

                add_meta_box(
                    'wapf-field-group-layout',
                    __('Layout & design','sw-wapf') .' &mdash; <span style="opacity:.5;">'.__('Field group layout settings','sw-wapf').'</span>',
                    [ $this, 'display_field_group_layout' ],
                    'wapf_product',
                    'normal',
                    'high'
                );

                add_meta_box(
                	'wapf-field-group-variables',
	                '<a class="modal_help_icon" style="padding:5px;" href="#" onclick="javascript:event.preventDefault();jQuery(\'.wapf--varaible-help\').show();"><i class="dashicons-before dashicons-editor-help"></i></a>' . __('Custom variables','sw-wapf') . ' &mdash; <span style="opacity:.5;">'. __('Create dynamic variables to use with formula-based pricing','sw-wapf').'</span>',
	                [ $this, 'display_field_group_variable_builder' ],
                    'wapf_product',
	                'normal',
	                'low'
                );

            }

        }

        public function display_field_group_variable_builder($for_product_admin = false) {
        	$model = $this->create_variables_model($for_product_admin);
	        echo Html::view("admin/variable-builder", $model);
        }

        public function display_field_group_layout($for_product_admin = false) {

            $model = $this->create_layout_model($for_product_admin);
            echo Html::view("admin/layout", $model);

        }

        public function display_field_group_conditions($for_product_admin = false) {

            $model = $this->create_conditions_model($for_product_admin);
            echo Html::view("admin/conditions", $model);

        }

        public function display_field_group_tools() {
            echo Html::view('admin/tools', ['product_mode' => false]);
        }

        public function display_field_group_fields($for_product_admin = false) {

            $model = $this->create_field_group_model($for_product_admin);
            echo Html::view("admin/field-list", $model);

        }


        private function create_variables_model($for_product_admin = false) {

        	$model = [
        		'variables' => []
	        ];

	        global $post;
	        if(is_bool($for_product_admin) && $for_product_admin)
		        $field_group =Field_Groups::process_data(get_post_meta($post->ID, '_wapf_fieldgroup', true));
	        else $field_group = Field_Groups::get_by_id($post->ID);

	        if(!empty($field_group) && !empty($field_group->variables)) {
	        	$model['variables'] = $field_group->variables;
	        }

	        return $model;
        }

        private function create_layout_model($for_product_admin = false) {

           $fg = new FieldGroup();
           $model = [
               'layout' => $fg->layout,
               'type'   => $fg->type
           ];

            global $post;
            if(is_bool($for_product_admin) && $for_product_admin)
                $field_group = Field_Groups::process_data(get_post_meta($post->ID, '_wapf_fieldgroup', true));
            else $field_group = Field_Groups::get_by_id($post->ID);

            if(isset($field_group->layout)) {
                $model['layout'] = $field_group->layout;
                $model['type'] = $field_group->type;
            }

            return $model;
        }

        private function create_conditions_model( $for_product_admin = false ): array {

            $model = [
                'condition_options' => Config::get_fieldgroup_visibility_conditions(),
                'conditions'        => [],
                'post_type'         => isset($_GET['post_type']) ? $_GET['post_type'] : 'wapf_product'
            ];

            global $post;

            if(is_bool($for_product_admin) && $for_product_admin) {

                $field_group_raw = get_post_meta($post->ID, '_wapf_fieldgroup', true);

                if(empty($field_group_raw)) {
                    $model['post_type'] = 'wapf_product';
                    $field_group = $this->prepare_fieldgroup_for_product($post->ID);
                } else {
                    $field_group = Field_Groups::process_data($field_group_raw);
                }
            } else
                $field_group = Field_Groups::get_by_id($post->ID);

            if(!empty($field_group)) {
                $model['type']          = $field_group->type;
                $model['conditions']    = $field_group->rules_groups;
                $model['post_type']     = $field_group->type;
            }

            return $model;

        }

        private function create_field_group_model($for_product_admin = false) {

            $model = [
                'fields'            => [],
                'condition_options' => Config::get_field_visibility_conditions(),
                'type'              => 'wapf_product'
            ];

            global $post;

            if( is_bool( $for_product_admin ) && $for_product_admin )
                $field_group = Field_Groups::process_data( get_post_meta( $post->ID, '_wapf_fieldgroup', true ) );
            else $field_group = Field_Groups::get_by_id( $post->ID );

            if( ! empty( $field_group ) ) {
                $model['fields'] = Field_Groups::field_group_to_raw_fields_json( $field_group );
                $model['type'] = $field_group->type;
            }

            return $model;

        }

        public function render_field_group_list() {

            $cap = wapf_get_setting('capability');

            $list = new Wapf_List_Table();
            $list->prepare_items();

            $model = [
                'title'         => __('Product Field Groups', 'sw-wapf'),
                'can_create'    => current_user_can($cap),
                'is_licensed'   => Licensing::get_license_info() != null
            ];

            Html::wp_list_table('cpt-list-table',$model,$list);

        }
        #endregion

        #region Private Helpers

	    private function make_unique(&$fg) {

            $uniqueId = function() {
                $result = '';
                $characters = 'abcdef0123456789abcdef012345acef';
                $char_length = strlen( $characters );

                for ( $i = 0; $i < 7; $i++ ) {
                    $result .= $characters[ rand( 0, $char_length - 1 ) ];
                }

                return $result;
            };

		    $fg->id = null;

		    $id_map = [];

		    foreach($fg->fields as $f) {

			    $old_id = $f->id;
			    $new_id = $uniqueId();
			    $f->id = $new_id;
			    $id_map[ $old_id ] = $new_id;

			    foreach ($fg->fields as $f2) {
				    if($f2->has_conditionals()) {
					    foreach($f2->conditionals as $c) {
						    foreach ($c->rules as $r) {
							    if($r->field === $old_id)
								    $r->field = $f->id;
						    }
					    }
				    }

                    if( $f2->pricing_enabled() ) {
                        if( $f2->is( 'multi_choice' ) && !empty( $f2->options['choices'] ) ) {
                            for($i = 0; $i < count( $f2->options['choices'] ); $i++ ) {
                                if( isset($f2->options['choices'][$i]['pricing_type']) && $f2->options['choices'][$i]['pricing_type'] === 'fx') {
                                    $f2->options['choices'][$i]['pricing_amount'] = str_replace( $old_id, $new_id, '' . $f2->options['choices'][$i]['pricing_amount'] );
                                }
                            }
                        } else if( $f2->pricing->type === 'fx' ) {
                            $f2->pricing->amount = str_replace($old_id, $new_id, '' . $f2->pricing->amount);
                        }
                    }

                    if( $f2->type === 'calc' ) {
                        if( ! empty( $f2->options['formula'] ) ) {
                            $f2->options['formula'] = str_replace( $old_id, $new_id, '' . $f2->options['formula'] );
                        }
                    }

			    }

                if( $fg->has_gallery_image_rules()) {
                    for($i = 0; $i < count( $fg->layout['gallery_images'] ); $i++ ) {
                        for($j = 0; $j < count( $fg->layout['gallery_images'][$i]['values'] ); $j++ ) {
                            if( $fg->layout['gallery_images'][$i]['values'][$j]['field'] === $old_id )
                                $fg->layout['gallery_images'][$i]['values'][$j]['field'] = $new_id;
                        }
                    }
                }

			    if($fg->has_variables()) {

                    for($i = 0; $i < count($fg->variables); $i++ ) {

                        $fg->variables[$i]['default'] = str_replace($old_id, $new_id, $fg->variables[$i]['default']);

                        if( isset($fg->variables[$i]['rules'] ) && is_array( $fg->variables[$i]['rules'] ) ) {
                            for($j = 0;$j < count( $fg->variables[$i]['rules'] ); $j++ ) {
                                $fg->variables[$i]['rules'][$j]['variable'] = str_replace($old_id, $new_id, $fg->variables[$i]['rules'][$j]['variable'] );
                                if($fg->variables[$i]['rules'][$j]['type'] === 'field' && $fg->variables[$i]['rules'][$j]['field'] === $old_id)
                                    $fg->variables[$i]['rules'][$j]['field'] = $f->id;
                            }
                        }
                    }

			    }

		    }

		    return $id_map;

	    }

	    private function display_conditions_help() {

		    echo Html::view('admin/modal', [
			    'class'     => 'wapf--conditions-help',
			    'title'     => __('Help with conditions', 'sw-wapf'),
			    'content'   => __('In the "conditions" section, you can define on which products your options should be shown. Here are a few examples of what you can do:<ul><li>Only show the options on products from a certain category</li><li>Only show the options to users with a certain role.</li><li>Only show these options on variable products.</li></ul>','sw-wapf')
		    ]);
	    }

	    private function display_variables_help() {
		    echo Html::view('admin/modal', [
			    'class'     => 'wapf--varaible-help',
			    'title'     => __('Help with custom variables', 'sw-wapf'),
			    'content'   => __('You can create dynamic variables which can then be used in formulas. A variable can dynamically change value depending on other values.<br/><br/><a href="https://www.studiowombat.com/knowledge-base/formulas-and-variables-explained/?ref=wapf_admin#variables" target="_blank">Read more about variables here</a>','sw-wapf')
		    ]);
	    }

        private function is_screen( $id = '', $action = '' ) {

            if( ! function_exists('get_current_screen') ) return false;

            $current_screen = get_current_screen();

            if( ! $current_screen ) return false;

            if( ! empty( $action ) ) {

                if( ! isset( $current_screen->action ) ) return false;

                if( is_array( $action ) && ! in_array( $current_screen->action, $action ) ) return false;

                if( ! is_array( $action ) && $action !== $current_screen->action ) return false;
            }

            if( ! empty( $id ) ) {
                if( is_array( $id ) && ! in_array( $current_screen->id, $id ) ) return false;
                if( ! is_array( $id ) && $id !== $current_screen->id ) return false;
            }

           return true;
        }

        private function prepare_fieldgroup_for_product( $post_id ) {

            $rule_group = new ConditionRuleGroup();
            $rule = new ConditionRule();
            $rule->subject = 'product';
            $rule->condition = 'product';
            $rule->value = [['id' => $post_id, 'text' => '']];
            $rule_group->rules[] = $rule;

            $field_group = new FieldGroup();
            $field_group->type = 'wapf_product';
            $field_group->rules_groups[] = $rule_group;

            return $field_group;

                    }

                #endregion

    }

}