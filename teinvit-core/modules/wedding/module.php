<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TEINVIT_WEDDING_MODULE_PATH', TEINVIT_CORE_PATH . 'modules/wedding/' );
define( 'TEINVIT_WEDDING_MODULE_URL', TEINVIT_CORE_URL . 'modules/wedding/' );


function teinvit_wedding_invitation_only_wapf_field_ids() {
    return [
        '6963a95e66425',
        '6963aa37412e4',
        '6963aa782092d',
        '696445d6a9ce9',
        '6964461d67da5',
        '6964466afe4d1',
        '69644689ee7e1',
        '696446dfabb7b',
        '696448f2ae763',
        '69644a3415fb9',
        '69644a5822ddc',
        '69644d9e814ef',
        '69644f2b40023',
        '69644f85d865e',
        '8dec5e7',
        '69644fd5c832b',
        '69645088f4b73',
        '696450ee17f9e',
        '696450ffe7db4',
        '32f74cc',
        '69645104b39f4',
        '696451a951467',
        '696451d204a8a',
        '696452023cdcd',
        'a4a0fca',
        '696452478586d',
        '6967752ab511b',
    ];
}

require_once TEINVIT_WEDDING_MODULE_PATH . 'preview/renderer.php';
require_once TEINVIT_WEDDING_MODULE_PATH . 'rsvp/rsvp.php';
require_once TEINVIT_WEDDING_MODULE_PATH . 'gifts/gifts.php';
require_once TEINVIT_WEDDING_MODULE_PATH . 'admin/client-admin.php';
require_once TEINVIT_WEDDING_MODULE_PATH . 'custom-pages.php';

require_once TEINVIT_WEDDING_MODULE_PATH . 'hooks/product-preview.php';
require_once TEINVIT_WEDDING_MODULE_PATH . 'hooks/guest-preview.php';
require_once TEINVIT_WEDDING_MODULE_PATH . 'admin/order-meta-box.php';
