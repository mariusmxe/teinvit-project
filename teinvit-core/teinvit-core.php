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

/**
 * Constante plugin
 */
define( 'TEINVIT_CORE_VERSION', '1.0.0' );
define( 'TEINVIT_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'TEINVIT_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once TEINVIT_CORE_PATH . 'core/client-admin.php';
register_activation_hook( __FILE__, 'teinvit_install_client_admin_tables' );

/**
 * NOTICE ADMIN – confirmare încărcare plugin
 * (nu conține logică de business)
 */
add_action( 'admin_notices', function () {
    echo '<div class="notice notice-success"><p><strong>Te Invit – Core</strong> activ.</p></div>';
});

/**
 * =====================================================
 * ÎNCĂRCARE MODULE – DOAR DUPĂ WOOCOMMERCE
 * =====================================================
 */
add_action( 'plugins_loaded', function () {

    // WooCommerce NU este activ → ieșim fără erori
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    /**
     * BOOTSTRAP (obligatoriu)
     */
    require_once TEINVIT_CORE_PATH . 'core/bootstrap.php';

    /**
     * CORE
     */
    require_once TEINVIT_CORE_PATH . 'core/tokens.php';
    require_once TEINVIT_CORE_PATH . 'core/endpoints.php';

    /**
     * INVITAȚIE NUNTĂ – PREVIEW UNIC
     */
    require_once TEINVIT_CORE_PATH . 'invitations/wedding/preview/renderer.php';

    /**
     * HOOKS DE AFIȘARE
     */
    require_once TEINVIT_CORE_PATH . 'hooks/product-preview.php';
    require_once TEINVIT_CORE_PATH . 'hooks/guest-preview.php';
    require_once TEINVIT_CORE_PATH . 'admin/order-meta-box.php';

    /**
     * PDF GENERATION (WP → VPS)
     */
    require_once TEINVIT_CORE_PATH . 'hooks/pdf-generate.php';

});
