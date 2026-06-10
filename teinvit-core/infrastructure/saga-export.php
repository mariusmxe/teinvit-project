<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/saga-export/settings.php';
require_once __DIR__ . '/saga-export/diagnostics.php';
require_once __DIR__ . '/saga-export/date-range.php';
require_once __DIR__ . '/saga-export/webtoffee-invoice-adapter.php';
require_once __DIR__ . '/saga-export/product-eligibility.php';
require_once __DIR__ . '/saga-export/order-source.php';
require_once __DIR__ . '/saga-export/document-builder.php';
require_once __DIR__ . '/saga-export/xml-writer.php';
require_once __DIR__ . '/saga-export/csv-writer.php';
require_once __DIR__ . '/saga-export/download-handler.php';
require_once __DIR__ . '/saga-export/admin-page.php';

TeInvit_Saga_Download_Handler::register();
TeInvit_Saga_Admin_Page::register();
