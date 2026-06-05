<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function teinvit_product_gallery_admin_register_page() {
    $parent = function_exists( 'teinvit_admin_root_slug' ) ? teinvit_admin_root_slug() : 'teinvit-admin-hub';

    add_submenu_page(
        $parent,
        'Product Gallery Images',
        'Product Gallery Images',
        teinvit_product_gallery_admin_capability(),
        'teinvit-product-gallery-images',
        'teinvit_product_gallery_render_admin_page'
    );
}
add_action( 'admin_menu', 'teinvit_product_gallery_admin_register_page', 70 );

function teinvit_product_gallery_admin_theme_rows( array $product_context ) {
    $logs = teinvit_product_gallery_logs_for_product( $product_context['product_id'] );
    $logs_by_theme = [];
    foreach ( $logs as $log ) {
        $logs_by_theme[ (string) ( $log['theme_key_internal'] ?? '' ) ] = $log;
    }

    $rows = [];
    foreach ( teinvit_product_gallery_themes_for_vertical( $product_context['vertical'] ) as $theme ) {
        $existing = teinvit_product_gallery_existing_pair_status( $product_context, $theme );
        $log = $logs_by_theme[ $theme['theme_key_internal'] ] ?? [];
        $status = (string) ( $log['status'] ?? '' );
        if ( $status === '' ) {
            $status = $existing['status'] === 'complete' ? 'success' : 'pending';
        }
        $rows[] = [
            'theme_key_internal' => $theme['theme_key_internal'],
            'theme_label'        => $theme['label'],
            'theme_file_key'     => $theme['file_key'],
            'css_class'          => $theme['css_class'],
            'status'             => $status,
            'existing_pair'      => $existing['status'],
            'error_code'         => (string) ( $log['error_code'] ?? '' ),
            'error_message'      => (string) ( $log['error_message'] ?? '' ),
            'png_path'           => (string) ( $log['png_path'] ?? ( $existing['paths']['png'] ?? '' ) ),
            'webp_path'          => (string) ( $log['webp_path'] ?? ( $existing['paths']['webp'] ?? '' ) ),
            'attempt_count'      => (int) ( $log['attempt_count'] ?? 0 ),
            'updated_at'         => (string) ( $log['updated_at'] ?? '' ),
        ];
    }

    return $rows;
}

function teinvit_product_gallery_ajax_prepare_product() {
    check_ajax_referer( 'teinvit_product_gallery_admin', 'nonce' );
    if ( ! current_user_can( teinvit_product_gallery_admin_capability() ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
    }

    $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
    $product_context = teinvit_product_gallery_detect_product_context( $product_id );
    if ( is_wp_error( $product_context ) ) {
        wp_send_json_error( [ 'message' => $product_context->get_error_message(), 'code' => $product_context->get_error_code() ], 400 );
    }

    $background = teinvit_product_gallery_background_details_for_product( $product_context['product_id'] );
    wp_send_json_success( [
        'product' => [
            'product_id'               => $product_context['product_id'],
            'product_name'             => $product_context['product_name'],
            'product_slug'             => $product_context['product_slug'],
            'gallery_file_base_slug'   => $product_context['gallery_file_base_slug'],
            'vertical'                 => $product_context['vertical'],
            'package_type'             => $product_context['package_type'],
            'background_attachment_id' => $background['background_attachment_id'],
            'background_url'           => $background['expected_background_url'],
            'background_valid'         => ! empty( $background['background_attachment_id'] ) && ! empty( $background['expected_background_url'] ),
        ],
        'themes' => teinvit_product_gallery_admin_theme_rows( $product_context ),
    ] );
}
add_action( 'wp_ajax_teinvit_product_gallery_prepare_product', 'teinvit_product_gallery_ajax_prepare_product' );

function teinvit_product_gallery_ajax_generate_theme() {
    check_ajax_referer( 'teinvit_product_gallery_admin', 'nonce' );
    if ( ! current_user_can( teinvit_product_gallery_admin_capability() ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
    }

    $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
    $theme_key = isset( $_POST['theme_key'] ) ? sanitize_key( wp_unslash( $_POST['theme_key'] ) ) : '';
    $mode = isset( $_POST['mode'] ) && sanitize_key( wp_unslash( $_POST['mode'] ) ) === 'regenerate' ? 'regenerate' : 'missing';

    $result = teinvit_product_gallery_generate_theme( $product_id, $theme_key, $mode );
    $product_context = teinvit_product_gallery_detect_product_context( $product_id );
    if ( is_wp_error( $product_context ) ) {
        wp_send_json_error( [ 'message' => $product_context->get_error_message(), 'code' => $product_context->get_error_code() ], 400 );
    }

    $rows = teinvit_product_gallery_admin_theme_rows( $product_context );
    $row = null;
    foreach ( $rows as $candidate ) {
        if ( (string) $candidate['theme_key_internal'] === $theme_key ) {
            $row = $candidate;
            break;
        }
    }

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [
            'message' => $result->get_error_message(),
            'code'    => $result->get_error_code(),
            'row'     => $row,
        ], 200 );
    }

    wp_send_json_success( [
        'result' => $result,
        'row'    => $row,
    ] );
}
add_action( 'wp_ajax_teinvit_product_gallery_generate_theme', 'teinvit_product_gallery_ajax_generate_theme' );

function teinvit_product_gallery_render_admin_page() {
    if ( ! current_user_can( teinvit_product_gallery_admin_capability() ) ) {
        wp_die( 'Unauthorized' );
    }

    $nonce = wp_create_nonce( 'teinvit_product_gallery_admin' );

    echo '<div class="wrap teinvit-product-gallery-admin">';
    echo '<h1>Product Gallery Images</h1>';
    echo '<p>Genereaza imagini demonstrative PNG si WebP pentru galeria produsului selectat. Faza 1 lucreaza doar pe produs individual.</p>';
    echo '<div class="notice notice-warning inline"><p>Nu se creeaza attachment-uri Media Library si nu se modifica galeria WooCommerce.</p></div>';

    echo '<div style="display:flex;gap:12px;align-items:flex-end;margin:20px 0;">';
    echo '<label><strong>Product ID</strong><br><input type="number" min="1" id="teinvit-gallery-product-id" class="regular-text" style="width:180px;"></label>';
    echo '<button type="button" class="button button-primary" id="teinvit-gallery-load-product">Load product</button>';
    echo '</div>';

    echo '<div id="teinvit-gallery-product-summary" style="margin:16px 0;"></div>';
    echo '<div id="teinvit-gallery-actions" style="display:none;margin:16px 0;">';
    echo '<label><strong>Theme</strong><br><select id="teinvit-gallery-theme-select"></select></label> ';
    echo '<button type="button" class="button button-primary" id="teinvit-gallery-generate-theme">Generate missing/incomplete selected theme</button> ';
    echo '<button type="button" class="button" id="teinvit-gallery-regenerate-theme">Regenerate selected theme</button> ';
    echo '<button type="button" class="button" id="teinvit-gallery-generate-all">Generate missing/incomplete all themes</button> ';
    echo '<button type="button" class="button" id="teinvit-gallery-retry-failed">Retry failed</button> ';
    echo '<button type="button" class="button button-secondary" id="teinvit-gallery-regenerate-product">Regenerate selected product</button>';
    echo '<div id="teinvit-gallery-progress" style="margin-top:10px;"></div>';
    echo '</div>';

    echo '<table class="widefat striped" id="teinvit-gallery-themes-table" style="display:none;max-width:1200px;">';
    echo '<thead><tr><th>Theme</th><th>File key</th><th>Status</th><th>Existing pair</th><th>Attempts</th><th>Error</th><th>PNG</th><th>WebP</th><th>Updated</th></tr></thead><tbody></tbody>';
    echo '</table>';

    echo '<script>';
    echo 'window.TEINVIT_PRODUCT_GALLERY_ADMIN=' . wp_json_encode( [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => $nonce,
    ] ) . ';';
    echo <<<'JS'
(function(){
  var cfg = window.TEINVIT_PRODUCT_GALLERY_ADMIN || {};
  var state = { product: null, themes: [] };
  function qs(sel){ return document.querySelector(sel); }
  function setProgress(msg){ var el = qs('#teinvit-gallery-progress'); if (el) el.textContent = msg || ''; }
  function statusClass(status){
    if (status === 'success' || status === 'skipped_existing') return 'color:#137333;font-weight:600;';
    if (String(status || '').indexOf('failed') === 0 || status === 'failed') return 'color:#b3261e;font-weight:600;';
    if (status === 'processing') return 'color:#8a5a00;font-weight:600;';
    return '';
  }
  function request(action, data){
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', cfg.nonce || '');
    Object.keys(data || {}).forEach(function(k){ body.set(k, data[k]); });
    return fetch(cfg.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function(r){ return r.json(); });
  }
  function renderSummary(){
    var box = qs('#teinvit-gallery-product-summary');
    if (!box || !state.product) { if (box) box.innerHTML = ''; return; }
    var p = state.product;
    box.innerHTML =
      '<table class="widefat striped" style="max-width:720px;"><tbody>' +
      '<tr><th>Product</th><td>' + esc(p.product_name) + ' (#' + esc(p.product_id) + ')</td></tr>' +
      '<tr><th>Vertical</th><td><code>' + esc(p.vertical) + '</code></td></tr>' +
      '<tr><th>Product slug</th><td><code>' + esc(p.product_slug) + '</code></td></tr>' +
      '<tr><th>Gallery base slug</th><td><code>' + esc(p.gallery_file_base_slug) + '</code></td></tr>' +
      '<tr><th>Background</th><td>' + (p.background_valid ? 'OK attachment #' + esc(p.background_attachment_id) : '<strong style="color:#b3261e;">missing</strong>') + '</td></tr>' +
      '</tbody></table>';
  }
  function esc(v){
    return String(v == null ? '' : v).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]; });
  }
  function renderThemes(){
    var table = qs('#teinvit-gallery-themes-table');
    var tbody = table ? table.querySelector('tbody') : null;
    var select = qs('#teinvit-gallery-theme-select');
    if (!table || !tbody || !select) return;
    tbody.innerHTML = '';
    select.innerHTML = '';
    state.themes.forEach(function(t){
      var opt = document.createElement('option');
      opt.value = t.theme_key_internal;
      opt.textContent = t.theme_label + ' (' + t.theme_file_key + ')';
      select.appendChild(opt);
      var tr = document.createElement('tr');
      tr.setAttribute('data-theme', t.theme_key_internal);
      tr.innerHTML =
        '<td>' + esc(t.theme_label) + '<br><code>' + esc(t.theme_key_internal) + '</code></td>' +
        '<td><code>' + esc(t.theme_file_key) + '</code></td>' +
        '<td style="' + statusClass(t.status) + '">' + esc(t.status) + '</td>' +
        '<td>' + esc(t.existing_pair) + '</td>' +
        '<td>' + esc(t.attempt_count) + '</td>' +
        '<td><code>' + esc(t.error_code) + '</code><br>' + esc(t.error_message) + '</td>' +
        '<td><small>' + esc(t.png_path) + '</small></td>' +
        '<td><small>' + esc(t.webp_path) + '</small></td>' +
        '<td>' + esc(t.updated_at) + '</td>';
      tbody.appendChild(tr);
    });
    table.style.display = state.themes.length ? '' : 'none';
    var actions = qs('#teinvit-gallery-actions');
    if (actions) actions.style.display = state.themes.length ? '' : 'none';
  }
  function updateRow(row){
    if (!row) return;
    state.themes = state.themes.map(function(t){ return t.theme_key_internal === row.theme_key_internal ? row : t; });
    renderThemes();
  }
  function loadProduct(){
    var id = qs('#teinvit-gallery-product-id').value;
    setProgress('Loading product...');
    request('teinvit_product_gallery_prepare_product', { product_id: id }).then(function(json){
      if (!json || !json.success) {
        state = { product: null, themes: [] };
        renderSummary(); renderThemes();
        setProgress((json && json.data && json.data.message) || 'Product load failed.');
        return;
      }
      state.product = json.data.product;
      state.themes = json.data.themes || [];
      renderSummary(); renderThemes();
      setProgress('Product loaded.');
    }).catch(function(err){ setProgress(err.message || 'Request failed.'); });
  }
  function generateTheme(themeKey, mode){
    if (!state.product || !themeKey) return Promise.resolve(false);
    updateRow(Object.assign({}, state.themes.find(function(t){ return t.theme_key_internal === themeKey; }) || {}, { status: 'processing' }));
    setProgress('Processing ' + themeKey + '...');
    return request('teinvit_product_gallery_generate_theme', {
      product_id: state.product.product_id,
      theme_key: themeKey,
      mode: mode || 'missing'
    }).then(function(json){
      if (json && json.data && json.data.row) updateRow(json.data.row);
      if (!json || !json.success) {
        setProgress(((json && json.data && json.data.message) || 'Theme failed') + ' (' + themeKey + ')');
        return false;
      }
      setProgress('Done ' + themeKey + '.');
      return true;
    }).catch(function(err){ setProgress(err.message || 'Request failed.'); return false; });
  }
  function runQueue(themes, mode){
    var list = themes.slice();
    var total = list.length;
    var index = 0;
    function next(){
      if (!list.length) { setProgress('Finished ' + index + '/' + total + '.'); return Promise.resolve(); }
      var key = list.shift();
      index += 1;
      setProgress('Processing ' + index + '/' + total + ': ' + key);
      return generateTheme(key, mode).then(next);
    }
    return next();
  }
  document.addEventListener('click', function(e){
    if (e.target && e.target.id === 'teinvit-gallery-load-product') loadProduct();
    if (e.target && e.target.id === 'teinvit-gallery-generate-theme') generateTheme(qs('#teinvit-gallery-theme-select').value, 'missing');
    if (e.target && e.target.id === 'teinvit-gallery-regenerate-theme') {
      if (confirm('Regenerate selected theme and overwrite valid files only after a new valid pair is ready?')) generateTheme(qs('#teinvit-gallery-theme-select').value, 'regenerate');
    }
    if (e.target && e.target.id === 'teinvit-gallery-generate-all') {
      runQueue(state.themes.map(function(t){ return t.theme_key_internal; }), 'missing');
    }
    if (e.target && e.target.id === 'teinvit-gallery-retry-failed') {
      runQueue(state.themes.filter(function(t){ return String(t.status || '').indexOf('failed') === 0 || t.status === 'failed'; }).map(function(t){ return t.theme_key_internal; }), 'missing');
    }
    if (e.target && e.target.id === 'teinvit-gallery-regenerate-product') {
      if (confirm('Regenerate all themes for this product? Existing files are overwritten atomically only after each new pair is valid.')) {
        runQueue(state.themes.map(function(t){ return t.theme_key_internal; }), 'regenerate');
      }
    }
  });
})();
JS;
    echo '</script>';
    echo '</div>';
}
