<?php
/**
 * Te Invit – Core Bootstrap
 *
 * Rol:
 * - încărcare module core
 * - inițializări globale
 * - hook-uri infrastructură (non-UI)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * =====================================================
 * LOAD CORE MODULES
 * (order matters!)
 * =====================================================
 */
require_once TEINVIT_CORE_PATH . 'core/security.php';
require_once TEINVIT_CORE_PATH . 'core/tokens.php';
require_once TEINVIT_CORE_PATH . 'core/endpoints.php';
require_once TEINVIT_CORE_PATH . 'core/rsvp.php';
require_once TEINVIT_CORE_PATH . 'core/emails.php';

/**
 * =====================================================
 * BOOTSTRAP READY
 *
 * În acest moment:
 * - WordPress este încărcat
 * - WooCommerce este activ
 * - modulele core sunt disponibile
 *
 * Integrarea reală cu Node / PDF
 * va fi adăugată controlat, în fișier dedicat.
 * =====================================================
 */
