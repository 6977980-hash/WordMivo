<?php
/**
 * Database schema and activation.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

class Installer {

	const DB_VERSION = '4';

	/** Bump to rebuild the finder files and page counts in the background after a deploy. */
	const DATA_REV = '2';

	const REFRESH_HOOK = 'wordmivo_refresh_lists';

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
		// Site name and tagline (feeds, browser tabs): fill only when empty or the WordPress default.
		if ( ! get_option( 'wordmivo_identity_set' ) ) {
			if ( '' === trim( (string) get_option( 'blogname' ) ) ) {
				update_option( 'blogname', 'WordMivo' );
			}
			if ( in_array( trim( (string) get_option( 'blogdescription' ) ), array( '', 'Just another WordPress site' ), true ) ) {
				update_option( 'blogdescription', 'Free word finder, Wordle solver and word game tools' );
			}
			update_option( 'wordmivo_identity_set', 1 );
		}
		// Word pages arrived in 0.3.0: switch them on once; the admin can turn them off.
		if ( ! get_option( 'wordmivo_words_set_added' ) ) {
			$sets = Pages::enabled_sets();
			if ( ! in_array( 'words', $sets, true ) ) {
				$sets[] = 'words';
				update_option( 'wordmivo_page_sets', $sets );
			}
			update_option( 'wordmivo_words_set_added', 1 );
		}
		// 0.6.0: dictionary words only; 0.6.1: offensive words removed. Rebuild once, no import needed.
		if ( self::DATA_REV !== (string) get_option( 'wordmivo_data_rev' ) && get_option( 'wordmivo_json_files' ) && ! wp_next_scheduled( self::REFRESH_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::REFRESH_HOOK );
		}
		// WordPress's sample post and page are thin, duplicate-looking content: trash them once
		// if they still hold the default text (they stay restorable from the Trash).
		if ( ! get_option( 'wordmivo_samples_removed' ) ) {
			add_action( 'init', array( __CLASS__, 'trash_wp_samples' ), 30 );
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

	public static function trash_wp_samples(): void {
		$samples = array(
			'hello-world' => array( 'post', 'Welcome to WordPress. This is your first post.' ),
			'sample-page' => array( 'page', 'This is an example page.' ),
		);
		foreach ( $samples as $slug => $info ) {
			$post = get_page_by_path( $slug, OBJECT, $info[0] );
			if ( $post && 'publish' === $post->post_status && str_contains( $post->post_content, $info[1] ) ) {
				wp_trash_post( $post->ID );
			}
		}
		update_option( 'wordmivo_samples_removed', 1 );
	}

	/** Remove blocked words, re-mark likely answers, rebuild finder JSON and page counts, purge the page cache. */
	public static function refresh_lists(): void {
		Importer::remove_blocked();
		Importer::mark_likely();
		Importer::write_json();
		Pages::rebuild_counts();
		update_option( 'wordmivo_data_rev', self::DATA_REV );
		do_action( 'litespeed_purge_all' );
	}

	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$words   = words_table();
		$sources = sources_table();
		$defs    = defs_table();

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

		// WordNet definitions; base is set instead for inflected forms (cranes -> crane).
		dbDelta(
			"CREATE TABLE {$defs} (
  word varchar(32) NOT NULL,
  base varchar(32) NOT NULL DEFAULT '',
  defs text NOT NULL,
  PRIMARY KEY  (word),
  FULLTEXT KEY ft_defs (defs)
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
