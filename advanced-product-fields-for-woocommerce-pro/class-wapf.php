<?php

namespace SW_WAPF_PRO {

    use SW_WAPF_PRO\Includes\Controllers\Admin_Controller;
    use SW_WAPF_PRO\Includes\Controllers\Integrations_Controller;
    use SW_WAPF_PRO\Includes\Controllers\Product_Controller;
	use SW_WAPF_PRO\Includes\Controllers\Public_Controller;
	if (!defined('ABSPATH')) {
        die;
    }

    class WAPF
    {

        private $settings = [];
        public $controllers = [];

        public function __construct() { }

        public function initialize($version,$base_file) {

	        register_activation_hook( $base_file, function() use ($version) {
		        add_option( 'wapf_db_version', $version, '', 'no' );
		        } );

            $basename = plugin_basename( $base_file );

            $this->settings = [

                // basic
                'name'				=> 'Advanced Product Fields (Extended) for WooCommerce',
                'version'			=> $version,
	            
                'slug'				=> 'advanced-product-fields-for-woocommerce-pro',
	            

                // urls
                'basename'			=> $basename,
                'path'				=> plugin_dir_path( $base_file ),
                'url'				=> plugin_dir_url( $base_file ),

                // options
                'capability'        => 'manage_options',

                // cpts
                'cpts'              => ['wapf_product']
            ];

            // Includes
            include_once( $this->settings['path'] . 'includes/api/api-helpers.php');

            // Language stuff
            add_action('plugins_loaded', [$this, 'load_text_domain']);

            // Actions
            add_action('init', [$this, 'register_post_types']);

	        if(!$this->is_woocommerce_active()) {
	        	return;
	        }

            // Kick off admin page.
            if( is_admin() )
                $this->controllers['admin'] = new Admin_Controller();

	        $this->controllers['public'] = new Public_Controller();

	        $this->controllers['product'] = new Product_Controller();

	        $this->controllers['integrations'] = new Integrations_Controller();

	        }
        
        public function get_controller( $controller ) {
            return $this->controllers[ $controller ] ?? null;
        }

        public function has_setting( $name ): bool {
            return isset($this->settings[ $name ]);
        }

        public function get_setting( $name ) {
            return $this->settings[ $name ] ?? null;
        }

        public function register_post_types() {

            $cap = wapf_get_setting('capability');

            register_post_type('wapf_product', [
                'labels'			=> [
                    'name'					=> __( 'Product Field Groups', 'sw-wapf' ),
                    'singular_name'			=> __( 'Field Group', 'sw-wapf' ),
                    'add_new'				=> __( 'Add New' , 'sw-wapf' ),
                    'add_new_item'			=> __('Add New Product Field Group' , 'sw-wapf'),
                    'edit_item'				=> __('Edit Product Field Group' , 'sw-wapf'),
                    'new_item'				=> __( 'New Field Group' , 'sw-wapf' ),
                    'view_item'				=> __( 'View Field Group', 'sw-wapf' ),
                    'search_items'			=> __( 'Search Field Groups', 'sw-wapf' ),
                    'not_found'				=> __( 'No Field Groups found', 'sw-wapf' ),
                    'not_found_in_trash'	=> __( 'No Field Groups found in Trash', 'sw-wapf' ),
                ],
                'public'			=> false,
                'show_ui'			=> true,
                '_builtin'			=> false,
                'capability_type'	=> 'post',
                'capabilities'		=> [
                    'edit_post'			=> $cap,
                    'delete_post'		=> $cap,
                    'edit_posts'		=> $cap,
                    'delete_posts'		=> $cap,
                ],
                'hierarchical'		=> true,
                'rewrite'			=> false,
                'query_var'			=> false,
                'supports' 			=> ['title'],
                'show_in_menu'		=> false,
                'show_in_rest'      => false
            ] );

        }
        
        public function load_text_domain() {
            load_plugin_textdomain(
                'sw-wapf',
                false,
                $this->settings[ 'slug' ] . '/languages/'
            );
        }

	    private function is_woocommerce_active()
	    {
		    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			    return true;
		    }
		    if (is_multisite()) {
			    $plugins = get_site_option('active_sitewide_plugins');
			    if (isset($plugins['woocommerce/woocommerce.php']))
				    return true;
		    }
		    return false;
	    }

    }
}