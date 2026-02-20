<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$token = get_query_var( 'teinvit_admin_client_token' );
$inv = teinvit_get_invitation( $token );
if ( ! $inv ) {
    echo '<p>Invitația nu a fost găsită.</p>';
    return;
}

$order = wc_get_order( (int) $inv['order_id'] );
if ( ! $order ) {
    echo '<p>Comanda nu a fost găsită.</p>';
    return;
}

$versions = teinvit_get_versions_for_token( $token );
usort( $versions, static function( $a, $b ) {
    return (int) $a['id'] <=> (int) $b['id'];
} );

$order_wapf = TeInvit_Wedding_Preview_Renderer::get_order_wapf_field_map( $order );
$order_invitation = TeInvit_Wedding_Preview_Renderer::get_order_invitation_data( $order );

$variants = [];
foreach ( $versions as $index => $row ) {
    $snap = json_decode( (string) $row['snapshot'], true );
    $snap_inv = ( isset( $snap['invitation'] ) && is_array( $snap['invitation'] ) ) ? $snap['invitation'] : [];
    $snap_wapf = ( isset( $snap['wapf_fields'] ) && is_array( $snap['wapf_fields'] ) ) ? $snap['wapf_fields'] : [];

    if ( empty( $snap_wapf ) && 0 === $index ) {
        $snap_wapf = $order_wapf;
    }
    if ( empty( $snap_inv ) && 0 === $index ) {
        $snap_inv = $order_invitation;
    }

    $variants[] = [
        'id' => (int) $row['id'],
        'label' => 'Varianta ' . $index,
        'invitation' => $snap_inv,
        'wapf_fields' => $snap_wapf,
        'created_at' => (string) $row['created_at'],
    ];
}

$current = null;
$active_id = (int) ( $inv['active_version_id'] ?? 0 );
foreach ( $variants as $variant ) {
    if ( (int) $variant['id'] === $active_id ) {
        $current = $variant;
        break;
    }
}
if ( ! $current && ! empty( $variants ) ) {
    $current = $variants[0];
}

$current_invitation = $current['invitation'] ?? $order_invitation;
$current_wapf = $current['wapf_fields'] ?? $order_wapf;
$subtitle = trim( (string) ( $current_invitation['names'] ?? '' ) );
if ( $subtitle === '' ) {
    $subtitle = 'Nume Mireasă & Nume Mire';
}

$config = is_array( $inv['config'] ?? null ) ? $inv['config'] : [];
$edits_remaining = isset( $config['edits_free_remaining'] ) ? (int) $config['edits_free_remaining'] : 2;
$show_deadline = ! empty( $config['show_rsvp_deadline'] );
$deadline_date = (string) ( $config['rsvp_deadline_date'] ?? '' );

$preview_html = TeInvit_Wedding_Preview_Renderer::render_from_invitation_data( $current_invitation, $order );
$product_id = teinvit_get_order_primary_product_id( $order );
$product = $product_id ? wc_get_product( $product_id ) : null;
$apf_html = ( $product && function_exists( 'wapf_display_field_groups_for_product' ) ) ? wapf_display_field_groups_for_product( $product ) : '';
$buy_edits_url = add_query_arg( [ 'add-to-cart' => 301, 'quantity' => 1 ], wc_get_cart_url() );
?>
<style>
.teinvit-admin-page{max-width:1200px;margin:20px auto;padding:16px}.teinvit-admin-page h1,.teinvit-admin-page .sub{text-align:center}
.teinvit-admin-intro{border:1px solid #ddd;padding:14px;border-radius:8px;background:#fff;margin:16px 0}
.teinvit-zone{border:1px solid #e5e5e5;padding:14px;border-radius:8px;background:#fff;margin:16px 0}
.teinvit-two-col{display:grid;grid-template-columns:1.2fr 1fr;gap:20px}
@media (max-width: 1024px){.teinvit-two-col{grid-template-columns:1fr}}
.teinvit-apf-col .wapf-wrapper,.teinvit-apf-col .wapf{max-width:100%}
</style>
<div class="teinvit-admin-page">
  <h1>Administrare invitație</h1>
  <p class="sub"><?php echo esc_html( $subtitle ); ?></p>

  <div class="teinvit-admin-intro">
    <p>Aici poți modifica invitația rapid, vedea preview-ul în timp real și publica varianta dorită pentru invitați.</p>
  </div>

  <div class="teinvit-zone">
    <h3>Data limită RSVP</h3>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
      <input type="hidden" name="action" value="teinvit_save_invitation_info">
      <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
      <label><input type="checkbox" id="show_rsvp_deadline" name="show_rsvp_deadline" <?php checked( $show_deadline ); ?>> Doresc afișarea datei limită pentru confirmări</label>
      <div id="deadline-wrap" style="margin-top:10px;<?php echo $show_deadline ? '' : 'display:none;'; ?>">
        <label>Completează data maximă a confirmării</label>
        <input type="text" name="rsvp_deadline_date" placeholder="zz/ll/aaaa" value="<?php echo esc_attr( $deadline_date ); ?>">
      </div>
      <p><button type="submit" class="button">Salvează data limită</button></p>
    </form>
  </div>

  <div class="teinvit-zone teinvit-two-col">
    <div>
      <?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      <h3>Alege varianta afișată invitaților:</h3>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-publish-form">
        <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
        <input type="hidden" name="action" value="teinvit_set_active_version">
        <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
        <?php foreach ( $variants as $variant ) : ?>
          <label style="display:block;margin-bottom:6px;">
            <input type="radio" name="active_version_id" value="<?php echo (int) $variant['id']; ?>" <?php checked( (int) $variant['id'], $active_id ); ?> class="teinvit-variant-radio">
            <?php echo esc_html( $variant['label'] ); ?>
          </label>
        <?php endforeach; ?>
        <button type="submit" class="button button-primary">Publică</button>
      </form>
    </div>

    <div class="teinvit-apf-col">
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="teinvit-save-form" class="cart">
        <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
        <input type="hidden" name="action" value="teinvit_save_version_snapshot">
        <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">

        <?php if ( $apf_html !== '' ) : ?>
          <?php echo $apf_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php else : ?>
          <p>Câmpurile APF nu sunt disponibile (plugin/APF hooks).</p>
        <?php endif; ?>

        <p id="teinvit-edits-counter"><?php echo (int) $edits_remaining; ?> modificări gratuite disponibile</p>
        <?php if ( $edits_remaining > 0 ) : ?>
          <button type="submit" class="button button-primary" id="teinvit-save-btn">Salvează modificările</button>
        <?php else : ?>
          <a href="<?php echo esc_url( $buy_edits_url ); ?>" class="button">Cumpără modificări suplimentare</a>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>
<script>
(function(){
  const variants = <?php echo wp_json_encode( $variants ); ?>;
  const initialWapf = <?php echo wp_json_encode( $current_wapf ); ?>;
  const editsRemaining = <?php echo (int) $edits_remaining; ?>;

  const deadlineCb = document.getElementById('show_rsvp_deadline');
  const deadlineWrap = document.getElementById('deadline-wrap');
  if(deadlineCb && deadlineWrap){ deadlineCb.addEventListener('change',()=>{ deadlineWrap.style.display = deadlineCb.checked ? '' : 'none'; }); }

  function parseWapfForm(){
    const out = {};
    document.querySelectorAll('#teinvit-save-form [name^="wapf[field_"]').forEach(el=>{
      const m = el.name.match(/^wapf\[field_([^\]]+)\]/); if(!m) return;
      const id = m[1];
      if(el.type==='checkbox'){
        if(!out[id]) out[id] = [];
        if(el.checked) out[id].push(el.value || '1');
        return;
      }
      if(el.type==='radio'){ if(el.checked){ out[id]=el.value || ''; } else if(typeof out[id] === 'undefined'){ out[id]=''; } return; }
      out[id]=el.value || '';
    });
    Object.keys(out).forEach(k=>{ if(Array.isArray(out[k])) out[k]=out[k].join(', '); });
    return out;
  }

  function setWapfValues(map){
    document.querySelectorAll('#teinvit-save-form [name^="wapf[field_"]').forEach(el=>{
      const m = el.name.match(/^wapf\[field_([^\]]+)\]/); if(!m) return;
      const id = m[1];
      const raw = String(map[id] ?? '');
      if(el.type==='checkbox'){
        const selected = raw.split(',').map(s=>s.trim()).filter(Boolean);
        el.checked = selected.includes(el.value) || (selected.includes('1') && (el.value === '1' || el.value === 'on'));
        el.dispatchEvent(new Event('change', {bubbles:true}));
        return;
      }
      if(el.type==='radio'){
        el.checked = String(el.value) === raw;
        if(el.checked) el.dispatchEvent(new Event('change', {bubbles:true}));
        return;
      }
      el.value = raw;
      el.dispatchEvent(new Event('input', {bubbles:true}));
      el.dispatchEvent(new Event('change', {bubbles:true}));
    });
  }

  function buildInvitation(w){
    const get=(k)=> (w[k]||'').trim();
    const has=(k)=> get(k)!=='';
    const inv={theme:'editorial',names:[get('6963a95e66425'),get('6963aa37412e4')].filter(Boolean).join(' & '),message:get('6963aa782092d'),show_parents:false,parents:{},show_nasi:false,nasi:'',events:[]};
    const theme=(get('6967752ab511b')||'').toLowerCase(); if(theme.includes('romantic'))inv.theme='romantic'; else if(theme.includes('modern'))inv.theme='modern'; else if(theme.includes('classic'))inv.theme='classic';
    if(has('696445d6a9ce9')){ inv.show_parents=true; inv.parents={mireasa:[get('6964461d67da5'),get('6964466afe4d1')].filter(Boolean).join(' & '),mire:[get('69644689ee7e1'),get('696446dfabb7b')].filter(Boolean).join(' & ')}; }
    if(has('696448f2ae763')){ inv.show_nasi=true; inv.nasi=[get('69644a3415fb9'),get('69644a5822ddc')].filter(Boolean).join(' & '); }
    if(has('69644d9e814ef')) inv.events.push({title:'Cununie civilă',loc:get('69644f2b40023'),date:[get('69644f85d865e'),get('8dec5e7')].filter(Boolean).join(' ora '),waze:get('69644fd5c832b')});
    if(has('69645088f4b73')) inv.events.push({title:'Ceremonie religioasă',loc:get('696450ee17f9e'),date:[get('696450ffe7db4'),get('32f74cc')].filter(Boolean).join(' ora '),waze:get('69645104b39f4')});
    if(has('696451a951467')) inv.events.push({title:'Petrecerea',loc:get('696451d204a8a'),date:[get('696452023cdcd'),get('a4a0fca')].filter(Boolean).join(' ora '),waze:get('696452478586d')});
    return inv;
  }

  function refreshPreview(){
    const inv = buildInvitation(parseWapfForm());
    window.TEINVIT_INVITATION_DATA = inv;
    document.dispatchEvent(new Event('input', {bubbles:true}));
  }

  function applyVariant(id){
    const variant = variants.find(v => String(v.id)===String(id)); if(!variant) return;
    setWapfValues(variant.wapf_fields || {});
    refreshPreview();
  }

  setWapfValues(initialWapf);
  refreshPreview();

  document.querySelectorAll('.teinvit-variant-radio').forEach(r=>r.addEventListener('change',()=>applyVariant(r.value)));
  document.querySelectorAll('#teinvit-save-form input, #teinvit-save-form select, #teinvit-save-form textarea').forEach(el=>{
    el.addEventListener('input', refreshPreview); el.addEventListener('change', refreshPreview);
  });

  const saveForm = document.getElementById('teinvit-save-form');
  if(saveForm){
    saveForm.addEventListener('submit', function(e){
      if(editsRemaining <= 0){ e.preventDefault(); return; }
      const msg = editsRemaining === 1
        ? 'Aceasta este ultima modificare disponibilă. Poți achiziționa altele oricând. Salvezi modificările?'
        : 'Această acțiune consumă o modificare disponibilă din ' + editsRemaining + '. Salvezi modificările?';
      if(!window.confirm(msg)){ e.preventDefault(); }
    });
  }
})();
</script>
