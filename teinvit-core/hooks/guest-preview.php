<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// păstrăm hook-ul pentru extensii (RSVP/cadouri), preview-ul este randat direct din endpoint.
add_action( 'teinvit_guest_page_preview', function () {
    // no-op
}, 1 );
