<?php
/**
 * IndexNow: tells Bing (and the other IndexNow engines, which share submissions)
 * about new or changed URLs right away. Google does not use IndexNow.
 *
 * - The key is generated once and served at /{key}.txt.
 * - After an import or a page-set change, URLs not sent before are submitted
 *   in the background (WP-Cron), up to 10,000 per request.
 * - Published posts and pages are submitted when they are published or updated.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

class IndexNow {

	const ENDPOINT = 'https://api.indexnow.org/indexnow';
	const SENT     = 'wordmivo_indexnow_sent';
	const HOOK     = 'wordmivo_indexnow_sync';

	public static function init(): void {
		add_action( 'init', static fn() => add_rewrite_rule( '^([a-f0-9]{32})\.txt$', 'index.php?wm_indexnow=$matches[1]', 'top' ) );
		add_filter( 'query_vars', static fn( $v ) => array_merge( $v, array( 'wm_indexnow' ) ) );
		add_action( 'template_redirect', array( __CLASS__, 'key_file' ), 0 );
		add_action( 'wordmivo_import_done', array( __CLASS__, 'schedule' ) );
		add_action( 'update_option_wordmivo_page_sets', array( __CLASS__, 'schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'sync' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'on_publish' ), 10, 3 );
	}

	public static function key(): string {
		$key = (string) get_option( 'wordmivo_indexnow_key', '' );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
			$key = md5( wp_generate_password( 32, true, true ) );
			update_option( 'wordmivo_indexnow_key', $key );
		}
		return $key;
	}

	/** Only a public site on a real domain should submit. */
	public static function active(): bool {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return get_option( 'blog_public' ) && $host && ! preg_match( '/^(localhost|127\.|10\.|192\.168\.)|\.(local|test)$/', $host );
	}

	public static function key_file(): void {
		$key = get_query_var( 'wm_indexnow' );
		if ( ! $key ) {
			return;
		}
		if ( self::key() !== $key ) {
			return; // Let WordPress serve its normal 404.
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( $key );
		exit;
	}

	public static function schedule(): void {
		if ( self::active() && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::HOOK );
		}
	}

	/** Every indexable URL the site has: virtual pages, word pages, published pages and posts. */
	public static function all_urls(): array {
		$urls = array( home_url( '/' ) );
		foreach ( Sitemap::urls() as $u ) {
			$urls[] = $u['loc'];
		}
		if ( in_array( 'words', Pages::enabled_sets(), true ) ) {
			$provider = new Sitemap_Words_Provider();
			for ( $page = 1, $max = $provider->get_max_num_pages(); $page <= $max; $page++ ) {
				foreach ( $provider->get_url_list( $page ) as $u ) {
					$urls[] = $u['loc'];
				}
			}
		}
		foreach ( get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
			$urls[] = get_permalink( $id );
		}
		return array_values( array_unique( $urls ) );
	}

	/**
	 * Submit URLs not sent before. Sent URLs are remembered as crc32 numbers.
	 */
	public static function sync(): array {
		if ( ! self::active() ) {
			return array( 'skipped' => 'not a public site on a real domain' );
		}
		$sent = get_option( self::SENT, array() );
		$sent = is_array( $sent ) ? array_flip( $sent ) : array();
		$new  = array_values( array_filter( self::all_urls(), static fn( $u ) => ! isset( $sent[ crc32( $u ) ] ) ) );
		$ok   = 0;
		foreach ( array_chunk( $new, 10000 ) as $chunk ) {
			if ( self::submit( $chunk ) ) {
				foreach ( $chunk as $u ) {
					$sent[ crc32( $u ) ] = true;
				}
				$ok += count( $chunk );
			}
		}
		update_option( self::SENT, array_keys( $sent ), false );
		update_option( 'wordmivo_indexnow_last', array( 'time' => time(), 'new' => count( $new ), 'sent' => $ok ), false );
		return array(
			'new'  => count( $new ),
			'sent' => $ok,
		);
	}

	public static function submit( array $urls, bool $blocking = true ): bool {
		if ( ! $urls ) {
			return true;
		}
		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout'  => $blocking ? 20 : 1,
				'blocking' => $blocking,
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => wp_json_encode(
					array(
						'host'        => wp_parse_url( home_url(), PHP_URL_HOST ),
						'key'         => self::key(),
						'keyLocation' => home_url( '/' . self::key() . '.txt' ),
						'urlList'     => array_values( $urls ),
					)
				),
			)
		);
		if ( ! $blocking ) {
			return true;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		return 200 === $code || 202 === $code;
	}

	public static function on_publish( string $new, string $old, \WP_Post $post ): void {
		if ( 'publish' !== $new || ! in_array( $post->post_type, array( 'post', 'page' ), true ) || wp_is_post_revision( $post ) || ! self::active() ) {
			return;
		}
		self::submit( array( get_permalink( $post ) ), false ); // Do not slow down the editor.
	}
}
