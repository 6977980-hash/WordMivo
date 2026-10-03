<?php
/**
 * Admin page: Tools > WordMivo. Data status, batched import, settings.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

class Admin {

	const SLUG = 'wordmivo';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_wordmivo_save', array( __CLASS__, 'save' ) );
		add_action( 'wp_ajax_wordmivo_import_batch', array( __CLASS__, 'ajax_import' ) );
		add_action( 'admin_notices', array( __CLASS__, 'reimport_notice' ) );
	}

	public static function menu(): void {
		add_management_page( 'WordMivo', 'WordMivo', 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
	}

	/**
	 * After an update that adds new data (dictionary flags), ask for one re-import.
	 */
	public static function reimport_notice(): void {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) || ! is_readable( Importer::dict_path() ) ) {
			return;
		}
		$table = words_table();
		$words = (int) $wpdb->get_var( "SELECT COUNT(*) FROM (SELECT 1 FROM {$table} LIMIT 1) t" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$flags = (int) $wpdb->get_var( "SELECT COUNT(*) FROM (SELECT 1 FROM {$table} WHERE is_valid = 1 LIMIT 1) t" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$defs  = ! is_readable( Importer::defs_path() ) || (int) $wpdb->get_var( 'SELECT COUNT(*) FROM (SELECT 1 FROM ' . defs_table() . ' LIMIT 1) t' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $words && ( ! $flags || ! $defs ) ) {
			printf(
				'<div class="notice notice-warning"><p><strong>WordMivo:</strong> new word data is available. Go to <a href="%s">Tools &gt; WordMivo</a> and click <strong>Start import</strong> once.</p></div>',
				esc_url( admin_url( 'tools.php?page=' . self::SLUG ) )
			);
		}
	}

	public static function ajax_import(): void {
		check_ajax_referer( 'wordmivo_import' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}
		$state = ! empty( $_POST['start'] ) ? Importer::start() : Importer::step( 2000 );
		if ( 'error' === $state['stage'] ) {
			wp_send_json_error( $state['error'] );
		}
		wp_send_json_success( $state );
	}

	public static function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( 'wordmivo_save' );
		// phpcs:disable WordPress.Security.NonceVerification -- verified above.
		$sets = isset( $_POST['sets'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sets'] ) ) : array();
		update_option( 'wordmivo_page_sets', array_values( array_intersect( $sets, array_keys( Pages::SETS ) ) ) );
		update_option( 'wordmivo_home_finder', empty( $_POST['home_finder'] ) ? 0 : 1 );

		$ga4 = strtoupper( sanitize_text_field( wp_unslash( $_POST['ga4'] ?? '' ) ) );
		update_option( 'wordmivo_ga4_id', preg_match( '/^G-[A-Z0-9]+$/', $ga4 ) ? $ga4 : '' );
		// Accept either the bare code or the whole <meta ... content="..."> tag.
		$gsc = wp_unslash( $_POST['gsc'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		if ( preg_match( '/content=["\']([^"\']+)/', $gsc, $m ) ) {
			$gsc = $m[1];
		}
		update_option( 'wordmivo_gsc_verification', sanitize_text_field( $gsc ) );
		$bing = wp_unslash( $_POST['bing'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		if ( preg_match( '/content=["\']([^"\']+)/', $bing, $m ) ) {
			$bing = $m[1];
		}
		update_option( 'wordmivo_bing_verification', sanitize_text_field( $bing ) );

		$social = array();
		foreach ( array_keys( Seo::SOCIAL ) as $key ) {
			$url = esc_url_raw( trim( wp_unslash( $_POST[ 'social_' . $key ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw.
			if ( $url && 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) ) {
				$social[ $key ] = strtok( $url, '?' ); // Drop tracking parameters.
			}
		}
		update_option( 'wordmivo_social', $social );

		update_option( 'wordmivo_ads_enabled', empty( $_POST['ads_enabled'] ) ? 0 : 1 );
		$client = sanitize_text_field( wp_unslash( $_POST['adsense_client'] ?? '' ) );
		update_option( 'wordmivo_adsense_client', preg_match( '/^ca-pub-\d{10,20}$/', $client ) ? $client : '' );
		foreach ( array_keys( Ads::POSITIONS ) as $pos ) {
			$slot = sanitize_text_field( wp_unslash( $_POST[ 'ad_slot_' . $pos ] ?? '' ) );
			update_option( 'wordmivo_ad_slot_' . $pos, preg_match( '/^\d{6,20}$/', $slot ) ? $slot : '' );
		}

		// phpcs:enable
		flush_rewrite_rules();
		wp_safe_redirect( add_query_arg( 'updated', 1, admin_url( 'tools.php?page=' . self::SLUG ) ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$stats   = Importer::stats();
		$state   = Importer::state();
		$enabled = Pages::enabled_sets();
		$files   = array(
			Importer::words_path() => is_readable( Importer::words_path() ),
			Importer::freq_path()  => is_readable( Importer::freq_path() ),
		);
		?>
<div class="wrap">
	<h1>WordMivo</h1>
	<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success"><p>Settings saved.</p></div>
	<?php endif; ?>

	<h2>Data</h2>
	<table class="widefat striped" style="max-width:820px">
		<tbody>
		<?php foreach ( $files as $path => $ok ) : ?>
			<tr><td><code><?php echo esc_html( $path ); ?></code></td><td><?php echo $ok ? '&#10003; found' : '<strong style="color:#b32d2e">missing</strong>'; ?></td></tr>
		<?php endforeach; ?>
			<tr><td>Words in database</td><td><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?> (with frequency rank: <?php echo esc_html( number_format_i18n( $stats['ranked'] ) ); ?>)</td></tr>
			<tr><td>By length</td><td><?php foreach ( $stats['by_length'] as $len => $n ) { echo esc_html( "{$len}: " . number_format_i18n( $n ) . '  ' ); } ?></td></tr>
			<tr><td>Import state</td><td id="wm-import-state"><?php echo esc_html( $state['stage'] ); ?></td></tr>
		</tbody>
	</table>
	<p class="description">Upload <code>words_alpha.txt</code>, <code>count_1w.txt</code> and <code>enable1.txt</code> to a <code>wordmivo-data</code> folder next to public_html (found automatically), or set <code>WORDMIVO_DATA_DIR</code> in wp-config.php.</p>
	<p><button type="button" class="button button-primary" id="wm-import">Start import</button> <span id="wm-import-log"></span></p>
	<p class="description">Or over SSH: <code>wp wordmivo import</code></p>

	<h2>Settings</h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wordmivo_save">
		<?php wp_nonce_field( 'wordmivo_save' ); ?>
		<table class="form-table" role="presentation">
			<tr><th scope="row">Page sets</th><td>
				<?php foreach ( Pages::SETS as $key => $label ) : ?>
					<label style="display:block"><input type="checkbox" name="sets[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $enabled, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
				<p class="description">Turn sets on in batches so Google indexes them gradually.</p>
			</td></tr>
			<tr><th scope="row">Home page</th><td><label><input type="checkbox" name="home_finder" value="1" <?php checked( get_option( 'wordmivo_home_finder', 1 ) ); ?>> Show the 5-letter word finder as the home page</label></td></tr>
			<tr><th scope="row"><label for="wm-ga4">GA4 measurement ID</label></th><td><input id="wm-ga4" name="ga4" type="text" placeholder="G-XXXXXXX" value="<?php echo esc_attr( get_option( 'wordmivo_ga4_id', '' ) ); ?>"> <span class="description">Loads only after cookie consent.</span></td></tr>
			<tr><th scope="row">Ads (AdSense)</th><td>
				<label style="display:block"><input type="checkbox" name="ads_enabled" value="1" <?php checked( get_option( 'wordmivo_ads_enabled', 0 ) ); ?>> Show ads</label>
				<p><label>Publisher ID <input name="adsense_client" type="text" placeholder="ca-pub-1234567890123456" value="<?php echo esc_attr( get_option( 'wordmivo_adsense_client', '' ) ); ?>"></label></p>
				<?php foreach ( Ads::POSITIONS as $pos => $label ) : ?>
					<p><label><?php echo esc_html( $label ); ?> slot ID <input name="ad_slot_<?php echo esc_attr( $pos ); ?>" type="text" inputmode="numeric" placeholder="1234567890" value="<?php echo esc_attr( get_option( 'wordmivo_ad_slot_' . $pos, '' ) ); ?>"></label></p>
				<?php endforeach; ?>
				<p class="description">While "Show ads" is off, nothing is added to pages. When on, each slot keeps its space reserved so the page does not jump, and AdSense loads only when a slot is near the screen. ads.txt is served automatically at /ads.txt.</p>
			</td></tr>
			<tr><th scope="row">WordMivo social profiles</th><td>
				<?php $social = Seo::social_links(); ?>
				<?php foreach ( Seo::SOCIAL as $key => $label ) : ?>
					<p><label><?php echo esc_html( $label ); ?> <input name="social_<?php echo esc_attr( $key ); ?>" type="url" class="regular-text" placeholder="https://" value="<?php echo esc_attr( $social[ $key ] ?? '' ); ?>"></label></p>
				<?php endforeach; ?>
				<p class="description">Shown in the footer and added to Google's structured data (sameAs) so the site is recognised as a brand.</p>
			</td></tr>
			<tr><th scope="row"><label for="wm-bing">Bing verification code</label></th><td><input id="wm-bing" name="bing" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'wordmivo_bing_verification', BING_DEFAULT ) ); ?>"></td></tr>
			<tr><th scope="row"><label for="wm-gsc">Search Console verification code</label></th><td><input id="wm-gsc" name="gsc" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'wordmivo_gsc_verification', GSC_DEFAULT ) ); ?>"></td></tr>
		</table>
		<?php submit_button(); ?>
	</form>

	<h2>IndexNow (Bing)</h2>
	<?php $last = get_option( 'wordmivo_indexnow_last' ); ?>
	<p>Key file: <a href="<?php echo esc_url( home_url( '/' . IndexNow::key() . '.txt' ) ); ?>"><?php echo esc_html( IndexNow::key() ); ?>.txt</a>.
	<?php if ( $last ) : ?>
		Last run <?php echo esc_html( human_time_diff( $last['time'] ) ); ?> ago: <?php echo esc_html( $last['sent'] . ' of ' . $last['new'] . ' new URLs submitted.' ); ?>
	<?php else : ?>
		New URLs are submitted automatically after each import and when page sets change.
	<?php endif; ?></p>

	<?php $log = get_option( 'wordmivo_cleanup_log' ); ?>
	<?php if ( $log ) : ?>
		<h2>First-deploy cleanup</h2>
		<p><?php echo esc_html( implode( '; ', (array) $log ) ); ?></p>
	<?php endif; ?>
</div>
<script>
(function(){
	var btn=document.getElementById('wm-import'),log=document.getElementById('wm-import-log'),st=document.getElementById('wm-import-state');
	var nonce=<?php echo wp_json_encode( wp_create_nonce( 'wordmivo_import' ) ); ?>;
	function call(start){
		var body=new URLSearchParams({action:'wordmivo_import_batch',_ajax_nonce:nonce});
		if(start){body.append('start','1');}
		return fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:body}).then(function(r){return r.json();});
	}
	function loop(start){
		call(start).then(function(res){
			if(!res.success){log.textContent='Error: '+res.data;btn.disabled=false;return;}
			var s=res.data;st.textContent=s.stage;
			log.textContent='Stage '+s.stage+' | words '+s.done+' | ranked '+s.ranked;
			if(s.stage==='done'){log.textContent+=' | Finished. Reload to see counts.';btn.disabled=false;return;}
			loop(false);
		}).catch(function(e){log.textContent='Network error, click again to resume: '+e;btn.disabled=false;});
	}
	btn.addEventListener('click',function(){btn.disabled=true;var s=st.textContent.trim();loop(s==='idle'||s==='done'||s==='error');});
})();
</script>
		<?php
	}
}
