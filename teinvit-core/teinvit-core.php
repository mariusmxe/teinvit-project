<?php
/**
 * Plugin Name: Te Invit – Core
 * Plugin URI: https://teinvit.com
 * Description: Core logic for Te Invit invitations.
 * Version: 1.0.0
 * Author: Te Invit
 * Text Domain: teinvit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TEINVIT_CORE_VERSION', '1.0.0' );
define( 'TEINVIT_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'TEINVIT_CORE_URL', plugin_dir_url( __FILE__ ) );

define( 'TEINVIT_CLIENT_ADMIN_SCHEMA_VERSION', 3 );
define( 'TEINVIT_CLIENT_ADMIN_SCHEMA_OPTION', 'teinvit_client_admin_schema_version' );

require_once TEINVIT_CORE_PATH . 'infrastructure/security.php';
require_once TEINVIT_CORE_PATH . 'infrastructure/helpers.php';
require_once TEINVIT_CORE_PATH . 'infrastructure/database.php';
require_once TEINVIT_CORE_PATH . 'infrastructure/tokens.php';
require_once TEINVIT_CORE_PATH . 'infrastructure/routing.php';
require_once TEINVIT_CORE_PATH . 'infrastructure/pdf/generate.php';

require_once TEINVIT_CORE_PATH . 'modules/wedding/module.php';

function teinvit_maybe_run_client_admin_schema_migrations() {
    $stored_version = (int) get_option( TEINVIT_CLIENT_ADMIN_SCHEMA_OPTION, 0 );
    if ( $stored_version >= TEINVIT_CLIENT_ADMIN_SCHEMA_VERSION ) {
        return;
    }

    if ( function_exists( 'teinvit_run_schema_migrations' ) ) {
        teinvit_run_schema_migrations();
        teinvit_install_modular_tables();
        flush_rewrite_rules();
        update_option( TEINVIT_CLIENT_ADMIN_SCHEMA_OPTION, TEINVIT_CLIENT_ADMIN_SCHEMA_VERSION, false );
    }
}

register_activation_hook( __FILE__, 'teinvit_install_client_admin_tables' );
register_activation_hook( __FILE__, 'teinvit_install_modular_tables' );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    teinvit_maybe_run_client_admin_schema_migrations();
} );
