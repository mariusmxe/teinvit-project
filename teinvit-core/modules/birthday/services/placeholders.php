<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_birthday_placeholder_not_ready( $component, array $context = [] ) {
    return new WP_Error(
        'teinvit_birthday_not_ready',
        sprintf( 'Birthday module component "%s" is not enabled yet (Phase 3 bootstrap only).', sanitize_key( (string) $component ) ),
        [
            'vertical' => 'birthday',
            'phase' => 'phase3_bootstrap',
            'context' => $context,
        ]
    );
}

function teinvit_birthday_payload_builder_placeholder( array $context = [] ) {
    return teinvit_birthday_placeholder_not_ready( 'payload_builder', $context );
}

function teinvit_birthday_renderer_placeholder( array $context = [] ) {
    return teinvit_birthday_placeholder_not_ready( 'renderer', $context );
}

function teinvit_birthday_preview_pdf_placeholder( array $context = [] ) {
    return teinvit_birthday_placeholder_not_ready( 'preview_pdf', $context );
}

function teinvit_birthday_admin_client_placeholder( array $context = [] ) {
    return teinvit_birthday_placeholder_not_ready( 'admin_client', $context );
}

function teinvit_birthday_invitati_placeholder( array $context = [] ) {
    return teinvit_birthday_placeholder_not_ready( 'invitati', $context );
}

function teinvit_birthday_rsvp_placeholder( array $context = [] ) {
    return teinvit_birthday_placeholder_not_ready( 'rsvp', $context );
}

function teinvit_birthday_reports_placeholder( array $context = [] ) {
    return teinvit_birthday_placeholder_not_ready( 'reports', $context );
}

function teinvit_birthday_gifts_placeholder( array $context = [] ) {
    return teinvit_birthday_placeholder_not_ready( 'gifts', $context );
}

function teinvit_birthday_email_semantics_placeholder( array $context = [] ) {
    return teinvit_birthday_placeholder_not_ready( 'email_semantics', $context );
}
