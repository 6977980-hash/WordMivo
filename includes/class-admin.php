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
		if ( $words && ! $flags ) {
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
		update_option( 'wordmivo_gsc_verification', sanitize_text_field( wp_unslash( $_POST['gsc'] ?? '' ) ) );

		$answer = strtolower( sanitize_text_field( wp_unslash( $_POST['wordle_answer'] ?? '' ) ) );
		if ( '' === $answer || preg_match( '/^[a-z]{5}$/', $answer ) ) {
			update_option( 'wordmivo_wordle_answer', $answer );
			update_option( 'wordmivo_wordle_date', $answer ? current_time( 'Y-m-d' ) : '' );
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
			<tr><th scope="row"><label for="wm-wordle">Today's Wordle answer</label></th><td><input id="wm-wordle" name="wordle_answer" type="text" maxlength="5" value="<?php echo esc_attr( get_option( 'wordmivo_wordle_answer', '' ) ); ?>"> <span class="description">Saved for today's date (<?php echo esc_html( current_time( 'Y-m-d' ) ); ?>). Hints page shows it hidden behind a reveal.</span></td></tr>
			<tr><th scope="row"><label for="wm-ga4">GA4 measurement ID</label></th><td><input id="wm-ga4" name="ga4" type="text" placeholder="G-XXXXXXX" value="<?php echo esc_attr( get_option( 'wordmivo_ga4_id', '' ) ); ?>"> <span class="description">Loads only after cookie consent.</span></td></tr>
			<tr><th scope="row"><label for="wm-gsc">Search Console verification code</label></th><td><input id="wm-gsc" name="gsc" type="text" class="regular-text" value="<?php echo esc_attr( get_option( 'wordmivo_gsc_verification', '' ) ); ?>"></td></tr>
		</table>
		<?php submit_button(); ?>
	</form>

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
