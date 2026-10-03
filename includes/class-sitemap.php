<?php
/**
 * Adds indexable virtual pages to the WordPress core sitemap (wp-sitemap.xml).
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

class Sitemap {

	public static function init(): void {
		add_action(
			'init',
			static function () {
				if ( function_exists( 'wp_register_sitemap_provider' ) ) {
					wp_register_sitemap_provider( 'wordmivo', new Sitemap_Provider() );
					if ( in_array( 'words', Pages::enabled_sets(), true ) ) {
						wp_register_sitemap_provider( 'wordmivowords', new Sitemap_Words_Provider() );
					}
				}
			}
		);
		// One-author site: author archives redirect to /about/, so keep them out of the sitemap.
		add_filter( 'wp_sitemaps_add_provider', static fn( $provider, $name ) => 'users' === $name ? false : $provider, 10, 2 );
	}

	public static function urls(): array {
		$urls = array();
		foreach ( Pages::all_specs() as $spec ) {
			if ( ! Pages::is_served( $spec ) || ! Pages::is_indexable( $spec ) ) {
				continue;
			}
			if ( 'hub' === $spec['type'] && 5 === $spec['len'] && get_option( 'wordmivo_home_finder', 1 ) ) {
				continue; // Home page, already in the core sitemap.
			}
			$urls[] = array( 'loc' => Pages::url( $spec ) );
		}
		return $urls;
	}
}

/**
 * Word pages, most frequent first: defined dictionary words up to Words::SITEMAP_RANK.
 */
class Sitemap_Words_Provider extends \WP_Sitemaps_Provider {

	const PER_PAGE = 2000;

	public function __construct() {
		$this->name        = 'wordmivowords';
		$this->object_type = 'wordmivowords';
	}

	private static function from(): string {
		global $wpdb;
		return 'FROM ' . defs_table() . ' d JOIN ' . words_table() . " w ON w.word = d.word WHERE d.base = '' AND w.is_valid = 1 AND " . $wpdb->prepare( 'w.freq_rank <= %d', Words::SITEMAP_RANK );
	}

	public function get_url_list( $page_num, $object_subtype = '' ) {
		global $wpdb;
		$words = $wpdb->get_col( 'SELECT d.word ' . self::from() . $wpdb->prepare( ' ORDER BY w.freq_rank LIMIT %d OFFSET %d', self::PER_PAGE, ( max( 1, (int) $page_num ) - 1 ) * self::PER_PAGE ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array_map( static fn( $w ) => array( 'loc' => Words::url( $w ) ), $words );
	}

	public function get_max_num_pages( $object_subtype = '' ) {
		global $wpdb;
		$n = (int) $wpdb->get_var( 'SELECT COUNT(*) ' . self::from() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return max( 1, (int) ceil( $n / self::PER_PAGE ) );
	}
}

class Sitemap_Provider extends \WP_Sitemaps_Provider {

	const PER_PAGE = 2000;

	public function __construct() {
		$this->name        = 'wordmivo';
		$this->object_type = 'wordmivo';
	}

	public function get_url_list( $page_num, $object_subtype = '' ) {
		return array_slice( Sitemap::urls(), ( $page_num - 1 ) * self::PER_PAGE, self::PER_PAGE );
	}

	public function get_max_num_pages( $object_subtype = '' ) {
		return max( 1, (int) ceil( count( Sitemap::urls() ) / self::PER_PAGE ) );
	}
}
