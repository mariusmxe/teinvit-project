<?php
/**
 * Backward-compatible loader for the shared TeInvit order panel.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( defined( 'TEINVIT_CORE_PATH' ) ) {
    require_once TEINVIT_CORE_PATH . 'infrastructure/order-meta-box.php';
}
