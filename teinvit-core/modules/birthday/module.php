<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TEINVIT_BIRTHDAY_MODULE_PATH', TEINVIT_CORE_PATH . 'modules/birthday/' );
define( 'TEINVIT_BIRTHDAY_MODULE_URL', TEINVIT_CORE_URL . 'modules/birthday/' );

require_once TEINVIT_BIRTHDAY_MODULE_PATH . 'services/placeholders.php';
require_once TEINVIT_BIRTHDAY_MODULE_PATH . 'services/runtime.php';
require_once TEINVIT_BIRTHDAY_MODULE_PATH . 'contracts/boundaries.php';
require_once TEINVIT_BIRTHDAY_MODULE_PATH . 'hooks/product-preview.php';
require_once TEINVIT_BIRTHDAY_MODULE_PATH . 'admin/client-admin.php';
require_once TEINVIT_BIRTHDAY_MODULE_PATH . 'rsvp/rsvp.php';

function teinvit_birthday_module_bootstrap_contract() {
    return [
        'vertical_key' => 'birthday',
        'status' => 'phase4_preview_pdf_ready',
        'public_runtime_enabled' => true,
        'storage_tables' => function_exists( 'teinvit_storage_tables_for_vertical' ) ? teinvit_storage_tables_for_vertical( 'birthday' ) : [],
        'boundaries' => teinvit_birthday_module_boundaries_contract(),
        'notes' => 'Phase 4 enabled payload + renderer + preview/pdf for public token routes only.',
    ];
}

function teinvit_birthday_module_register_runtime() {
    if ( ! function_exists( 'teinvit_register_vertical_module_runtime' ) ) {
        return;
    }

    teinvit_register_vertical_module_runtime( 'birthday', teinvit_birthday_module_bootstrap_contract() );
}

add_action( 'plugins_loaded', 'teinvit_birthday_module_register_runtime', 18 );
