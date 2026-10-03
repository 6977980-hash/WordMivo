<?php
/**
 * WP-CLI: wp wordmivo verify | import | stats | json | counts
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

class CLI {

	/**
	 * Read-only check of the data files: line counts, sha256, words by length.
	 */
	public function verify(): void {
		$r = Importer::verify();
		\WP_CLI::log( 'Data dir: ' . $r['data_dir'] );
		foreach ( $r['files'] as $name => $f ) {
			\WP_CLI::log( sprintf( '%s: %d lines, %d bytes, sha256 %s', $name, $f['lines'], $f['bytes'], $f['sha256'] ) );
		}
		foreach ( $r['lengths'] as $len => $n ) {
			if ( $len <= 8 ) {
				\WP_CLI::log( sprintf( '  length %d: %d', $len, $n ) );
			}
		}
		foreach ( $r['errors'] as $e ) {
			\WP_CLI::warning( $e );
		}
		$r['errors'] ? \WP_CLI::error( 'BLOCKED' ) : \WP_CLI::success( 'READY' );
	}

	/**
	 * Import words and frequency ranks, then build JSON and page counts.
	 *
	 * [--batch=<n>]
	 * : Lines per batch. Default 5000.
	 */
	public function import( $args, $assoc ): void {
		$batch = (int) ( $assoc['batch'] ?? 5000 );
		$state = Importer::start();
		if ( 'error' === $state['stage'] ) {
			\WP_CLI::error( $state['error'] );
		}
		$last = '';
		while ( 'done' !== $state['stage'] ) {
			$state = Importer::step( $batch );
			if ( $state['stage'] !== $last ) {
				\WP_CLI::log( 'Stage: ' . $state['stage'] );
				$last = $state['stage'];
			}
		}
		\WP_CLI::success( sprintf( 'Imported %d words, %d ranked.', $state['done'], $state['ranked'] ) );
		$this->stats();
	}

	/**
	 * Print database counts.
	 */
	public function stats(): void {
		$s = Importer::stats();
		\WP_CLI::log( sprintf( 'Total: %d, with rank: %d', $s['total'], $s['ranked'] ) );
		foreach ( $s['by_length'] as $len => $n ) {
			\WP_CLI::log( sprintf( '  length %d: %d', $len, $n ) );
		}
		foreach ( $s['json_files'] as $len => $url ) {
			\WP_CLI::log( sprintf( '  json %d: %s', $len, $url ) );
		}
	}

	/**
	 * Rebuild the client-side JSON word lists.
	 */
	public function json(): void {
		$files = Importer::write_json();
		\WP_CLI::success( count( $files ) . ' JSON files written.' );
	}

	/**
	 * Rebuild cached page counts (used by sitemap and noindex rules).
	 */
	public function counts(): void {
		$c = Pages::rebuild_counts();
		\WP_CLI::success( count( $c ) . ' page counts stored.' );
	}
}
