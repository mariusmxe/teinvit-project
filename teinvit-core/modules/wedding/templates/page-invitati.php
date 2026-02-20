<?php
$token = get_query_var( 'teinvit_invitati_token' );
$inv = teinvit_get_invitation( $token );
$active = teinvit_get_active_snapshot( $token );
$snapshot = $active ? json_decode( (string) $active['snapshot'], true ) : [];
$invitation_data = (array) ( $snapshot['invitation'] ?? [] );
$config = wp_parse_args( (array) ( $inv['config'] ?? [] ), teinvit_default_rsvp_config() );

$deadline_active = ! empty( $config['show_rsvp_deadline'] );
$deadline_raw = (string) ( $config['rsvp_deadline_date'] ?? '' );
$deadline_ts = 0;
if ( $deadline_raw !== '' && preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $deadline_raw, $m ) ) {
    $deadline_ts = strtotime( $m[3] . '-' . $m[2] . '-' . $m[1] . ' 23:59:59' );
}
$deadline_expired = $deadline_active && $deadline_ts > 0 && time() > $deadline_ts;

$bg = teinvit_model_background_url( $inv['model_key'] ?? 'invn01' );

global $wpdb;
$t = teinvit_db_tables();
$gifts = $wpdb->get_results( $wpdb->prepare( "SELECT gift_id,gift_name,gift_link,gift_delivery_address,status FROM {$t['gifts']} WHERE token=%s ORDER BY id ASC", $token ), ARRAY_A );
$in_cpt_template = ! empty( $GLOBALS['TEINVIT_IN_CPT_TEMPLATE'] );
?>
<div class="teinvit-invitati-page" style="max-width:980px;margin:0 auto;">
  <?php if ( ! $in_cpt_template ) : ?>
  <div class="preview" style="position:relative;">
    <img src="<?php echo esc_url( $bg ); ?>" alt="background" style="width:100%;height:auto;display:block;">
    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:28px;"><?php echo esc_html( $invitation_data['names'] ?? '' ); ?></div>
  </div>
  <?php endif; ?>

  <?php if ( $deadline_active && $deadline_raw ) : ?>
    <p style="padding:10px;border:1px solid #ddd;margin:10px 0;">Data limită pentru confirmări: <strong><?php echo esc_html( $deadline_raw ); ?></strong>.</p>
  <?php endif; ?>

  <?php if ( $deadline_expired ) : ?>
    <p style="padding:10px;border:1px solid #cc0000;background:#fff3f3;color:#900;">Perioada de confirmare a expirat. Formularul RSVP este dezactivat.</p>
  <?php endif; ?>

  <form id="teinvit-rsvp-form">
    <fieldset <?php disabled( $deadline_expired ); ?>>
      <h3>RSVP</h3>
      <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
      <input name="guest_last_name" placeholder="Nume" required>
      <input name="guest_first_name" placeholder="Prenume" required>
      <input name="guest_phone" placeholder="07xxxxxxxx" required pattern="^07\d{8}$">
      <input name="attending_people_count" type="number" min="1" value="1">

      <?php if ( ! empty( $config['show_attending_civil'] ) ) : ?><label><input type="checkbox" name="attending_civil"> Particip la cununia civilă</label><?php endif; ?>
      <?php if ( ! empty( $config['show_attending_religious'] ) ) : ?><label><input type="checkbox" name="attending_religious"> Particip la ceremonia religioasă</label><?php endif; ?>
      <?php if ( ! empty( $config['show_attending_party'] ) ) : ?><label><input type="checkbox" name="attending_party"> Particip la petrecere</label><?php endif; ?>
      <?php if ( ! empty( $config['show_kids'] ) ) : ?><label><input type="checkbox" name="bringing_kids"> Vin cu copii</label><input name="kids_count" type="number" min="0" value="0"><?php endif; ?>
      <?php if ( ! empty( $config['show_accommodation'] ) ) : ?><label><input type="checkbox" name="needs_accommodation"> Am nevoie de cazare</label><input name="accommodation_people_count" type="number" min="0" value="0"><?php endif; ?>
      <?php if ( ! empty( $config['show_vegetarian'] ) ) : ?><label><input type="checkbox" name="vegetarian_requested"> Meniu vegetarian</label><?php endif; ?>
      <?php if ( ! empty( $config['show_allergies'] ) ) : ?><label><input type="checkbox" name="has_allergies"> Alergii</label><input name="allergy_details" placeholder="Detalii alergii"><?php endif; ?>

      <textarea name="message_to_couple" placeholder="Mesaj"></textarea>

      <table>
        <thead><tr><th>Select</th><th>Cadou</th><th>Link</th><th>Adresă</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ( $gifts as $gift ) : ?>
          <tr>
            <td><input type="checkbox" name="gift_ids[]" value="<?php echo esc_attr( $gift['gift_id'] ); ?>" <?php disabled( 'reserved' === $gift['status'] ); ?>></td>
            <td><?php echo esc_html( $gift['gift_name'] ); ?></td>
            <td><?php if ( $gift['gift_link'] ) : ?><a href="<?php echo esc_url( $gift['gift_link'] ); ?>" target="_blank">link</a><?php endif; ?></td>
            <td><?php echo esc_html( $gift['gift_delivery_address'] ); ?></td>
            <td><?php echo esc_html( $gift['status'] ); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <label><input type="checkbox" name="gdpr_accepted" required> Accept GDPR</label>
      <button type="submit">Trimite RSVP</button>
    </fieldset>
  </form>
  <div id="teinvit-rsvp-msg"></div>
</div>
<script>
document.getElementById('teinvit-rsvp-form').addEventListener('submit', async function(e){
  e.preventDefault();
  const form=e.target; if(form.querySelector('fieldset[disabled]')) return;
  const fd=new FormData(form); const payload={};
  for(const [k,v] of fd.entries()){ if(k==='gift_ids[]'){ payload.gift_ids=(payload.gift_ids||[]); payload.gift_ids.push(v); } else if(form.elements[k] && form.elements[k].type==='checkbox'){ payload[k]=1; } else { payload[k]=v; } }
  ['attending_civil','attending_religious','attending_party','bringing_kids','needs_accommodation','vegetarian_requested','has_allergies','gdpr_accepted'].forEach(k=>{if(!payload[k]) payload[k]=0;});
  const res=await fetch('<?php echo esc_url_raw( rest_url( 'teinvit/v2/invitati/' . rawurlencode( $token ) . '/rsvp' ) ); ?>',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
  const data=await res.json();
  const msg=document.getElementById('teinvit-rsvp-msg');
  if(!res.ok){ msg.textContent=(data.message||'Eroare'); return; }
  msg.textContent='Mulțumim! RSVP salvat.';
  form.querySelectorAll('input,textarea,button').forEach(el=>el.disabled=true);
});
</script>
