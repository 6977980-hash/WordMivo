<?php
/**
 * Database schema and activation.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

class Installer {

	const DB_VERSION = '2';

	public static function activate(): void {
		self::create_tables();
		self::cleanup_first_deploy();
		Pages::add_rewrite_rules();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function maybe_upgrade(): void {
		if ( ! get_option( 'wordmivo_cleanup_done' ) ) {
			self::cleanup_first_deploy();
		}
		if ( PAGES_VERSION !== (string) get_option( 'wordmivo_pages_created' ) ) {
			add_action( 'init', __NAMESPACE__ . '\\create_standard_pages', 20 );
		}
		if ( get_option( 'wordmivo_db_version' ) !== self::DB_VERSION ) {
			self::create_tables();
		}
		// A Git deploy updates files without re-running activation.
		if ( get_option( 'wordmivo_rewrite_version' ) !== WORDMIVO_VERSION ) {
			add_action(
				'init',
				static function () {
					flush_rewrite_rules();
					update_option( 'wordmivo_rewrite_version', WORDMIVO_VERSION );
				},
				99
			);
		}
	}

	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$words   = words_table();
		$sources = sources_table();

		// dbDelta needs two spaces after PRIMARY KEY and one field per line.
		dbDelta(
			"CREATE TABLE {$words} (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  word varchar(32) NOT NULL,
  len tinyint(3) unsigned NOT NULL,
  first_letter char(1) NOT NULL,
  last_letter char(1) NOT NULL,
  signature varchar(32) NOT NULL,
  letter_mask int(10) unsigned NOT NULL,
  vowels tinyint(3) unsigned NOT NULL,
  has_double tinyint(1) NOT NULL DEFAULT 0,
  scrabble_score smallint(5) unsigned NOT NULL DEFAULT 0,
  freq_rank int(10) unsigned DEFAULT NULL,
  is_valid tinyint(1) NOT NULL DEFAULT 0,
  is_likely tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_word (word),
  KEY idx_len_rank (len,freq_rank),
  KEY idx_len_first (len,first_letter),
  KEY idx_len_last (len,last_letter),
  KEY idx_signature (signature)
) ENGINE=InnoDB {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$sources} (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  source_name varchar(191) NOT NULL,
  url text NOT NULL,
  license varchar(191) NOT NULL,
  sha256 char(64) NOT NULL,
  acquired_at datetime NOT NULL,
  PRIMARY KEY  (id)
) ENGINE=InnoDB {$charset};"
		);

		update_option( 'wordmivo_db_version', self::DB_VERSION );
	}

	/**
	 * The very first Git deploy went to public_html by mistake and left docs/ and .git/
	 * in the site root. Remove them once, and only when they are provably ours.
	 */
	public static function cleanup_first_deploy(): void {
		$root = untrailingslashit( ABSPATH );
		$log  = array();

		$docs = $root . '/docs';
		if ( is_dir( $docs ) ) {
			$ours  = array( 'prompt-review-and-strategy.md', 'WordMivo_Prompt_v6.md', '.htaccess' );
			$files = array_values( array_diff( scandir( $docs ), array( '.', '..' ) ) );
			if ( $files && ! array_diff( $files, $ours ) ) {
				$log[] = self::rmdir_recursive( $docs ) ? 'Removed docs/' : 'Could not remove docs/';
			} else {
				$log[] = 'Kept docs/ (contains files that are not ours)';
			}
		}

		$git    = $root . '/.git';
		$config = $git . '/config';
		if ( is_dir( $git ) && is_readable( $config ) ) {
			$conf = (string) file_get_contents( $config ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( preg_match( '#github\.com[:/]6977980-hash/WordMivo(\.git)?\s#i', $conf ) ) {
				$log[] = self::rmdir_recursive( $git ) ? 'Removed .git/' : 'Could not remove .git/';
			} else {
				$log[] = 'Kept .git/ (not the WordMivo repo)';
			}
		}

		update_option( 'wordmivo_cleanup_done', 1, false );
		update_option( 'wordmivo_cleanup_log', $log ? $log : array( 'Nothing to clean' ), false );
	}

	private static function rmdir_recursive( string $dir ): bool {
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			if ( $item->isDir() && ! $item->isLink() ) {
				@rmdir( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			} else {
				@unlink( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			}
		}
		return @rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	}
}
