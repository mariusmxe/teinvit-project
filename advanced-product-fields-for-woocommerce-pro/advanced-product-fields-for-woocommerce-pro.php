<?php

/*
 * Plugin Name: Advanced Product Fields Pro for WooCommerce
 * Plugin URI: https://www.studiowombat.com/plugin/advanced-product-fields-for-woocommerce/
 * Description: Customize WooCommerce product pages with powerful and intuitive fields ( = product add-ons).
 * Version: 3.1.6
 * Author: StudioWombat
 * Author URI: https://studiowombat.com/
 * Text Domain: sw-wapf
 * WC requires at least: 4.9
 * WC tested up to: 10.5
 * Requires at least: 6.0
 * Requires PHP: 7.1
 */


if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! function_exists( 'SW_WAPF_PRO_auto_loader ') ) {
    
	function SW_WAPF_PRO_auto_loader( $class_name ) {
        
		// Not loading a class from our plugin.
		if ( strpos( $class_name, 'SW_WAPF_PRO' ) === false ) {
			return;
		}
        
        // The substr removes SW_WAPF_PRO\ root namespace. Then we split by \
        $path_parts = explode( '\\', substr( $class_name, 12 ) );
        $file_name = array_pop( $path_parts );

        // Convert to lowercase and build path in one step
        $path = plugin_dir_path( __FILE__ ) . implode( DIRECTORY_SEPARATOR, array_map( 'strtolower', $path_parts ) );
        if( ! empty( $path_parts ) ) {
            $path .= DIRECTORY_SEPARATOR;
        }

        // Create the file name
        $file_path = $path . 'class-' . str_replace('_', '-', strtolower( $file_name ) ) . '.php';

        require_once( $file_path );
        
	}

	spl_autoload_register( 'SW_WAPF_PRO_auto_loader' );
    
}

if( ! function_exists( 'wapf_pro' ) ) {
	function wapf_pro() {

		$version = '3.1.6';

		// globals
		global $wapf;

		// initialize
		if ( ! isset( $wapf ) ) {
			$wapf = new \SW_WAPF_PRO\WAPF();
			$wapf->initialize( $version, __FILE__ );
		}
        
		return $wapf;

	}
}

// initialize
wapf_pro();

// Declare HPOS compatibility.
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );