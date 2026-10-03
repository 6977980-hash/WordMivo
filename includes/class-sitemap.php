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
				}
			}
		);
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
