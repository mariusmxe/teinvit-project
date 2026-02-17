<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TEINVIT_WEDDING_MODULE_PATH', TEINVIT_CORE_PATH . 'modules/wedding/' );
define( 'TEINVIT_WEDDING_MODULE_URL', TEINVIT_CORE_URL . 'modules/wedding/' );

require_once TEINVIT_WEDDING_MODULE_PATH . 'preview/renderer.php';
require_once TEINVIT_WEDDING_MODULE_PATH . 'rsvp/rsvp.php';
require_once TEINVIT_WEDDING_MODULE_PATH . 'gifts/gifts.php';
require_once TEINVIT_WEDDING_MODULE_PATH . 'admin/client-admin.php';

require_once TEINVIT_WEDDING_MODULE_PATH . 'hooks/product-preview.php';
require_once TEINVIT_WEDDING_MODULE_PATH . 'hooks/guest-preview.php';
require_once TEINVIT_WEDDING_MODULE_PATH . 'admin/order-meta-box.php';
