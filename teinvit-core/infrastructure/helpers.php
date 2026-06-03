<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_xlsx_column_name( $zero_based_index ) {
    $index = max( 0, (int) $zero_based_index );
    $name = '';

    do {
        $name = chr( 65 + ( $index % 26 ) ) . $name;
        $index = intdiv( $index, 26 ) - 1;
    } while ( $index >= 0 );

    return $name;
}

function teinvit_xlsx_clean_text( $value ) {
    $encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $value ) : json_encode( $value );
    $text = is_scalar( $value ) || $value === null ? (string) $value : $encoded;
    if ( ! is_string( $text ) ) {
        $text = '';
    }

    if ( function_exists( 'mb_convert_encoding' ) ) {
        $text = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );
    } elseif ( function_exists( 'iconv' ) ) {
        $converted = @iconv( 'UTF-8', 'UTF-8//IGNORE', $text );
        if ( $converted !== false ) {
            $text = $converted;
        }
    }

    $clean = preg_replace( '/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text );
    return $clean === null ? '' : $clean;
}

function teinvit_xlsx_xml_text( $value ) {
    return htmlspecialchars( teinvit_xlsx_clean_text( $value ), ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8' );
}

function teinvit_xlsx_sheet_bounds( array $rows ) {
    $row_count = max( 1, count( $rows ) );
    $column_count = 1;

    foreach ( $rows as $row ) {
        $column_count = max( $column_count, count( (array) $row ) );
    }

    return [ $row_count, $column_count ];
}

function teinvit_xlsx_column_widths( array $rows, $column_count ) {
    $column_count = max( 1, (int) $column_count );
    $widths = array_fill( 0, $column_count, 10 );

    foreach ( $rows as $row ) {
        $cells = array_values( (array) $row );
        for ( $i = 0; $i < $column_count; $i++ ) {
            $text = isset( $cells[ $i ] ) ? teinvit_xlsx_clean_text( $cells[ $i ] ) : '';
            $text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $text ) : strip_tags( $text );
            $length = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
            $widths[ $i ] = max( $widths[ $i ], min( 45, max( 10, (int) ceil( $length * 0.9 ) + 2 ) ) );
        }
    }

    return $widths;
}

function teinvit_xlsx_sheet_xml( array $rows ) {
    list( $row_count, $column_count ) = teinvit_xlsx_sheet_bounds( $rows );
    $last_cell = teinvit_xlsx_column_name( $column_count - 1 ) . $row_count;
    $widths = teinvit_xlsx_column_widths( $rows, $column_count );

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
    $xml .= '<dimension ref="A1:' . $last_cell . '"/>';
    $xml .= '<sheetViews><sheetView workbookViewId="0"><selection activeCell="A1" sqref="A1"/></sheetView></sheetViews>';
    $xml .= '<sheetFormatPr baseColWidth="10" defaultRowHeight="15"/>';
    $xml .= '<cols>';
    foreach ( $widths as $index => $width ) {
        $col = $index + 1;
        $xml .= '<col min="' . $col . '" max="' . $col . '" width="' . max( 8, (int) $width ) . '" customWidth="1"/>';
    }
    $xml .= '</cols>';
    $xml .= '<sheetData>';

    foreach ( $rows as $ri => $cells ) {
        $row_num = $ri + 1;
        $xml .= '<row r="' . $row_num . '" spans="1:' . $column_count . '">';
        foreach ( array_values( (array) $cells ) as $ci => $value ) {
            $ref = teinvit_xlsx_column_name( $ci ) . $row_num;
            $safe = teinvit_xlsx_clean_text( $value );
            $space = preg_match( '/^\s|\s$/u', $safe ) ? ' xml:space="preserve"' : '';
            $style = $row_num === 1 ? ' s="1"' : '';
            $xml .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t' . $space . '>' . teinvit_xlsx_xml_text( $safe ) . '</t></is></c>';
        }
        $xml .= '</row>';
    }

    $xml .= '</sheetData>';
    $xml .= '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>';
    $xml .= '</worksheet>';

    return $xml;
}

function teinvit_xlsx_content_types_xml() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/xl/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>';
}

function teinvit_xlsx_root_rels_xml() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>';
}

function teinvit_xlsx_workbook_xml() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<fileVersion appName="xl" lastEdited="7" lowestEdited="7" rupBuild="0"/>'
        . '<workbookPr defaultThemeVersion="164011"/>'
        . '<bookViews><workbookView activeTab="0"/></bookViews>'
        . '<sheets><sheet name="Rezumat" sheetId="1" r:id="rId1"/><sheet name="Unic" sheetId="2" r:id="rId2"/><sheet name="Istoric" sheetId="3" r:id="rId3"/></sheets>'
        . '<calcPr calcId="0"/>'
        . '</workbook>';
}

function teinvit_xlsx_workbook_rels_xml() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>'
        . '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '<Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>'
        . '</Relationships>';
}

function teinvit_xlsx_styles_xml() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '<dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleMedium9"/>'
        . '</styleSheet>';
}

function teinvit_xlsx_theme_xml() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Office Theme">'
        . '<a:themeElements><a:clrScheme name="Office"><a:dk1><a:sysClr val="windowText" lastClr="000000"/></a:dk1><a:lt1><a:sysClr val="window" lastClr="FFFFFF"/></a:lt1><a:dk2><a:srgbClr val="1F2937"/></a:dk2><a:lt2><a:srgbClr val="F9FAFB"/></a:lt2><a:accent1><a:srgbClr val="2563EB"/></a:accent1><a:accent2><a:srgbClr val="16A34A"/></a:accent2><a:accent3><a:srgbClr val="DC2626"/></a:accent3><a:accent4><a:srgbClr val="9333EA"/></a:accent4><a:accent5><a:srgbClr val="EA580C"/></a:accent5><a:accent6><a:srgbClr val="0891B2"/></a:accent6><a:hlink><a:srgbClr val="0000FF"/></a:hlink><a:folHlink><a:srgbClr val="800080"/></a:folHlink></a:clrScheme><a:fontScheme name="Office"><a:majorFont><a:latin typeface="Calibri Light"/><a:ea typeface=""/><a:cs typeface=""/></a:majorFont><a:minorFont><a:latin typeface="Calibri"/><a:ea typeface=""/><a:cs typeface=""/></a:minorFont></a:fontScheme><a:fmtScheme name="Office"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:gradFill rotWithShape="1"><a:gsLst><a:gs pos="0"><a:schemeClr val="phClr"><a:tint val="50000"/></a:schemeClr></a:gs><a:gs pos="100000"><a:schemeClr val="phClr"><a:shade val="50000"/></a:schemeClr></a:gs></a:gsLst><a:lin ang="5400000" scaled="0"/></a:gradFill><a:gradFill rotWithShape="1"><a:gsLst><a:gs pos="0"><a:schemeClr val="phClr"><a:tint val="80000"/></a:schemeClr></a:gs><a:gs pos="100000"><a:schemeClr val="phClr"><a:shade val="30000"/></a:schemeClr></a:gs></a:gsLst><a:lin ang="5400000" scaled="0"/></a:gradFill></a:fillStyleLst><a:lnStyleLst><a:ln w="63500" cap="flat" cmpd="sng" algn="ctr"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:prstDash val="solid"/></a:ln><a:ln w="127000" cap="flat" cmpd="sng" algn="ctr"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:prstDash val="solid"/></a:ln><a:ln w="190500" cap="flat" cmpd="sng" algn="ctr"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:prstDash val="solid"/></a:ln></a:lnStyleLst><a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst><a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"><a:tint val="95000"/></a:schemeClr></a:solidFill><a:gradFill rotWithShape="1"><a:gsLst><a:gs pos="0"><a:schemeClr val="phClr"><a:tint val="40000"/></a:schemeClr></a:gs><a:gs pos="100000"><a:schemeClr val="phClr"><a:shade val="40000"/></a:schemeClr></a:gs></a:gsLst><a:lin ang="5400000" scaled="0"/></a:gradFill></a:bgFillStyleLst></a:fmtScheme></a:themeElements>'
        . '</a:theme>';
}

function teinvit_xlsx_core_props_xml() {
    $now = gmdate( 'Y-m-d\TH:i:s\Z' );
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:creator>TeInvit</dc:creator><cp:lastModifiedBy>TeInvit</cp:lastModifiedBy>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
        . '</cp:coreProperties>';
}

function teinvit_xlsx_app_props_xml() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
        . '<Application>TeInvit</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop>'
        . '<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>3</vt:i4></vt:variant></vt:vector></HeadingPairs>'
        . '<TitlesOfParts><vt:vector size="3" baseType="lpstr"><vt:lpstr>Rezumat</vt:lpstr><vt:lpstr>Unic</vt:lpstr><vt:lpstr>Istoric</vt:lpstr></vt:vector></TitlesOfParts>'
        . '</Properties>';
}

function teinvit_xlsx_write_report_workbook( $zip, array $sheets ) {
    $defaults = [
        'Rezumat' => [],
        'Unic' => [],
        'Istoric' => [],
    ];
    $sheets = array_merge( $defaults, $sheets );

    $zip->addFromString( '[Content_Types].xml', teinvit_xlsx_content_types_xml() );
    $zip->addFromString( '_rels/.rels', teinvit_xlsx_root_rels_xml() );
    $zip->addFromString( 'docProps/core.xml', teinvit_xlsx_core_props_xml() );
    $zip->addFromString( 'docProps/app.xml', teinvit_xlsx_app_props_xml() );
    $zip->addFromString( 'xl/workbook.xml', teinvit_xlsx_workbook_xml() );
    $zip->addFromString( 'xl/_rels/workbook.xml.rels', teinvit_xlsx_workbook_rels_xml() );
    $zip->addFromString( 'xl/styles.xml', teinvit_xlsx_styles_xml() );
    $zip->addFromString( 'xl/theme/theme1.xml', teinvit_xlsx_theme_xml() );
    $zip->addFromString( 'xl/worksheets/sheet1.xml', teinvit_xlsx_sheet_xml( (array) $sheets['Rezumat'] ) );
    $zip->addFromString( 'xl/worksheets/sheet2.xml', teinvit_xlsx_sheet_xml( (array) $sheets['Unic'] ) );
    $zip->addFromString( 'xl/worksheets/sheet3.xml', teinvit_xlsx_sheet_xml( (array) $sheets['Istoric'] ) );
}
