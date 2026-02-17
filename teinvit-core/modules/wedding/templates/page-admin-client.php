<?php
$token = get_query_var( 'teinvit_admin_client_token' );
$inv = teinvit_get_invitation( $token );
$versions = teinvit_get_versions_for_token( $token );
$active = teinvit_get_active_snapshot( $token );
$snapshot = $active ? json_decode( (string) $active['snapshot'], true ) : [];
$invitation_data = (array) ( $snapshot['invitation'] ?? [] );
$config = wp_parse_args( (array) ( $inv['config'] ?? [] ), teinvit_default_rsvp_config() );

$toggle_labels = [
    'show_attending_civil' => 'Permite confirmarea pentru cununia civilă',
    'show_attending_religious' => 'Permite confirmarea pentru ceremonia religioasă',
    'show_attending_party' => 'Permite confirmarea pentru petrecere',
    'show_kids' => 'Permite confirmarea copiilor',
    'show_accommodation' => 'Permite solicitarea de cazare',
    'show_vegetarian' => 'Permite selectarea meniului vegetarian',
    'show_allergies' => 'Permite menționarea alergiilor',
    'show_rsvp_deadline' => 'Activează data limită de confirmare',
];

global $wpdb;
$t = teinvit_db_tables();
$gifts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['gifts']} WHERE token=%s ORDER BY id ASC", $token ), ARRAY_A );
?>
<div class="teinvit-admin-client">
  <h2>Admin client: <?php echo esc_html( $token ); ?></h2>

  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
    <input type="hidden" name="action" value="teinvit_save_invitation_info">
    <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
    <h3>Date invitație</h3>
    <input name="names" placeholder="Nume miri" value="<?php echo esc_attr( $invitation_data['names'] ?? '' ); ?>">
    <textarea name="message" placeholder="Mesaj"><?php echo esc_textarea( $invitation_data['message'] ?? '' ); ?></textarea>
    <input name="event_date" placeholder="YYYY-mm-dd HH:ii:ss" value="<?php echo esc_attr( $inv['event_date'] ?? '' ); ?>">
    <input name="model_key" placeholder="invn01" value="<?php echo esc_attr( $inv['model_key'] ?? 'invn01' ); ?>">
    <label><input type="checkbox" name="show_rsvp_deadline" <?php checked( ! empty( $config['show_rsvp_deadline'] ) ); ?>> Show RSVP deadline</label>
    <input name="rsvp_deadline_text" placeholder="Text deadline" value="<?php echo esc_attr( $config['rsvp_deadline_text'] ?? '' ); ?>">
    <button type="submit">Salvează informații</button>
  </form>

  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
    <input type="hidden" name="action" value="teinvit_save_rsvp_config">
    <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
    <h3>Toggle RSVP</h3>
    <?php foreach ( teinvit_default_rsvp_config() as $key => $v ) : if ( 'rsvp_deadline_text' === $key ) { continue; } ?>
      <label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $config[ $key ] ) ); ?>><?php echo esc_html( $toggle_labels[ $key ] ?? $key ); ?></label>
    <?php endforeach; ?>
    <button type="submit">Salvează modificări</button>
  </form>

  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
    <input type="hidden" name="action" value="teinvit_set_active_version">
    <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
    <h3>Versiuni</h3>
    <ul><?php foreach ( $versions as $v ) : ?><li>#<?php echo (int) $v['id']; ?> - <?php echo esc_html( $v['created_at'] ); ?></li><?php endforeach; ?></ul>
    <select name="active_version_id"><?php foreach ( $versions as $v ) : ?><option value="<?php echo (int) $v['id']; ?>" <?php selected( (int) $v['id'], (int) ( $inv['active_version_id'] ?? 0 ) ); ?>>#<?php echo (int) $v['id']; ?></option><?php endforeach; ?></select>
    <button type="submit">Setează ca activă</button>
  </form>

  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
    <input type="hidden" name="action" value="teinvit_save_version_snapshot">
    <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
    <input name="names" placeholder="Nume miri" value="<?php echo esc_attr( $invitation_data['names'] ?? '' ); ?>">
    <textarea name="message" placeholder="Mesaj"><?php echo esc_textarea( $invitation_data['message'] ?? '' ); ?></textarea>
    <button type="submit">Salvează modificări</button>
  </form>

  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'teinvit_admin_' . $token ); ?>
    <input type="hidden" name="action" value="teinvit_save_gifts">
    <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
    <h3>Cadouri</h3>
    <?php if ( ! empty( $inv['gifts_locked'] ) ) : ?>
      <p>Cadourile sunt blocate.</p>
    <?php endif; ?>
    <?php for ( $i = 0; $i < max( 5, count( $gifts ) ); $i++ ) : $gift = $gifts[ $i ] ?? []; ?>
      <div>
        <input name="gifts[<?php echo $i; ?>][gift_id]" placeholder="gift id" value="<?php echo esc_attr( $gift['gift_id'] ?? 'gift-' . $i ); ?>" <?php disabled( ! empty( $inv['gifts_locked'] ) ); ?>>
        <input name="gifts[<?php echo $i; ?>][gift_name]" placeholder="nume" value="<?php echo esc_attr( $gift['gift_name'] ?? '' ); ?>" <?php disabled( ! empty( $inv['gifts_locked'] ) ); ?>>
        <input name="gifts[<?php echo $i; ?>][gift_link]" placeholder="link" value="<?php echo esc_attr( $gift['gift_link'] ?? '' ); ?>" <?php disabled( ! empty( $inv['gifts_locked'] ) ); ?>>
        <input name="gifts[<?php echo $i; ?>][gift_delivery_address]" placeholder="adresă" value="<?php echo esc_attr( $gift['gift_delivery_address'] ?? '' ); ?>" <?php disabled( ! empty( $inv['gifts_locked'] ) ); ?>>
      </div>
    <?php endfor; ?>
    <button type="submit" <?php disabled( ! empty( $inv['gifts_locked'] ) ); ?>>Salvează cadouri</button>
  </form>
</div>
