<?php
/**
 * Resumable, batched import of the word list and frequency list.
 *
 * Stages: words -> ranks -> json -> counts -> done. Each call to step()
 * does a bounded amount of work so it fits inside shared-hosting timeouts.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

class Importer {

	const WORDS_FILE = 'words_alpha.txt';
	const FREQ_FILE  = 'count_1w.txt';
	const STATE      = 'wordmivo_import_state';

	public static function words_path(): string {
		return trailingslashit( WORDMIVO_DATA_DIR ) . self::WORDS_FILE;
	}

	public static function freq_path(): string {
		return trailingslashit( WORDMIVO_DATA_DIR ) . self::FREQ_FILE;
	}

	/**
	 * Read-only check of the data files (Phase 0).
	 */
	public static function verify(): array {
		$report = array(
			'data_dir' => WORDMIVO_DATA_DIR,
			'files'    => array(),
			'lengths'  => array(),
			'errors'   => array(),
		);
		foreach ( array( self::words_path(), self::freq_path() ) as $path ) {
			if ( ! is_readable( $path ) ) {
				$report['errors'][] = sprintf( 'Missing or unreadable: %s', $path );
				continue;
			}
			$lines = 0;
			$fh    = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			while ( false !== fgets( $fh ) ) {
				++$lines;
			}
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$report['files'][ basename( $path ) ] = array(
				'lines'  => $lines,
				'bytes'  => filesize( $path ),
				'sha256' => hash_file( 'sha256', $path ),
			);
		}
		if ( is_readable( self::words_path() ) ) {
			$fh = fopen( self::words_path(), 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			while ( false !== ( $line = fgets( $fh ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
				$word = strtolower( trim( $line ) );
				if ( is_valid_word( $word ) ) {
					$len                       = strlen( $word );
					$report['lengths'][ $len ] = ( $report['lengths'][ $len ] ?? 0 ) + 1;
				}
			}
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			ksort( $report['lengths'] );
		}
		return $report;
	}

	public static function state(): array {
		return wp_parse_args(
			get_option( self::STATE, array() ),
			array(
				'stage'  => 'idle',
				'offset' => 0,
				'done'   => 0,
				'ranked' => 0,
				'rank'   => 0,
			)
		);
	}

	public static function start(): array {
		// The frequency list is optional: without it words are imported unranked
		// and a later re-import adds the ranks.
		if ( ! is_readable( self::words_path() ) ) {
			return array(
				'stage' => 'error',
				'error' => sprintf( 'Missing or unreadable: %s', self::words_path() ),
			);
		}
		$state = array(
			'stage'  => 'words',
			'offset' => 0,
			'done'   => 0,
			'ranked' => 0,
			'rank'   => 0,
		);
		update_option( self::STATE, $state, false );
		return $state;
	}

	/**
	 * Run one bounded batch of the current stage.
	 */
	public static function step( int $batch = 2000 ): array {
		$state = self::state();
		$batch = max( 100, min( 20000, $batch ) );

		switch ( $state['stage'] ) {
			case 'words':
				$state = self::step_words( $state, $batch );
				break;
			case 'ranks':
				$state = self::step_ranks( $state, $batch );
				break;
			case 'json':
				self::write_json();
				$state['stage'] = 'counts';
				break;
			case 'counts':
				Pages::rebuild_counts();
				self::record_sources();
				$state['stage'] = 'done';
				do_action( 'wordmivo_import_done' );
				break;
		}
		update_option( self::STATE, $state, false );
		return $state;
	}

	private static function step_words( array $state, int $batch ): array {
		global $wpdb;
		$fh = fopen( self::words_path(), 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fseek( $fh, (int) $state['offset'] );

		$rows = array();
		$read = 0;
		while ( $read < $batch && false !== ( $line = fgets( $fh ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			++$read;
			$word = strtolower( trim( $line ) );
			if ( ! is_valid_word( $word ) ) {
				continue;
			}
			$rows[] = $wpdb->prepare(
				'(%s,%d,%s,%s,%s,%d,%d,%d,%d)',
				$word,
				strlen( $word ),
				$word[0],
				substr( $word, -1 ),
				signature( $word ),
				letter_mask( $word ),
				vowel_count( $word ),
				has_repeat( $word ) ? 1 : 0,
				scrabble_score( $word )
			);
		}
		$state['offset'] = ftell( $fh );
		$eof             = feof( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( $rows ) {
			$table = words_table();
			// Values are prepared above; INSERT IGNORE keeps re-imports idempotent.
			$wpdb->query( "INSERT IGNORE INTO {$table} (word,len,first_letter,last_letter,signature,letter_mask,vowels,has_double,scrabble_score) VALUES " . implode( ',', $rows ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$state['done'] += count( $rows );
		}
		if ( $eof || 0 === $read ) {
			$state['stage']  = 'ranks';
			$state['offset'] = 0;
		}
		return $state;
	}

	/**
	 * Frequency file lines are "word<TAB>count" (or just "word"); rank = line number.
	 */
	private static function step_ranks( array $state, int $batch ): array {
		global $wpdb;
		if ( ! is_readable( self::freq_path() ) ) {
			$state['stage'] = 'json';
			return $state;
		}
		$fh = fopen( self::freq_path(), 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fseek( $fh, (int) $state['offset'] );

		$cases = array();
		$words = array();
		$read  = 0;
		while ( $read < $batch && false !== ( $line = fgets( $fh ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			++$read;
			++$state['rank'];
			$word = strtolower( trim( strtok( $line, "\t " ) ) );
			if ( ! is_valid_word( $word ) || isset( $words[ $word ] ) ) {
				continue;
			}
			$words[ $word ] = true;
			$cases[]        = $wpdb->prepare( 'WHEN %s THEN %d', $word, $state['rank'] );
		}
		$state['offset'] = ftell( $fh );
		$eof             = feof( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( $cases ) {
			$table = words_table();
			$in    = implode( ',', array_map( static fn( $w ) => $wpdb->prepare( '%s', $w ), array_keys( $words ) ) );
			// Only set a rank once, so the first (most frequent) occurrence wins.
			$state['ranked'] += (int) $wpdb->query( "UPDATE {$table} SET freq_rank = CASE word " . implode( ' ', $cases ) . " END WHERE word IN ({$in}) AND freq_rank IS NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( $eof || 0 === $read ) {
			$state['stage']  = 'json';
			$state['offset'] = 0;
		}
		return $state;
	}

	/**
	 * Write words-{len}.{hash}.json to uploads/wordmivo/ as [[word, score, rank], ...],
	 * common words first. Hash in the name makes browser caching safe.
	 */
	public static function write_json(): array {
		global $wpdb;
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'wordmivo';
		$url     = trailingslashit( $uploads['baseurl'] ) . 'wordmivo';
		wp_mkdir_p( $dir );

		$table = words_table();
		$files = array();
		foreach ( glob( $dir . '/words-*.json' ) ?: array() as $old ) {
			wp_delete_file( $old );
		}
		for ( $len = MIN_LEN; $len <= MAX_LEN; $len++ ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT word, scrabble_score, freq_rank FROM {$table} WHERE len = %d ORDER BY freq_rank IS NULL, freq_rank, word", $len ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_N
			);
			$data = array_map( static fn( $r ) => array( $r[0], (int) $r[1], (int) $r[2] ), $rows );
			$json = wp_json_encode( $data );
			$hash = substr( md5( $json ), 0, 10 );
			$name = "words-{$len}.{$hash}.json";
			file_put_contents( "{$dir}/{$name}", $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$files[ $len ] = set_url_scheme( "{$url}/{$name}", 'relative' );
		}
		update_option( 'wordmivo_json_files', $files );
		return $files;
	}

	private static function record_sources(): void {
		global $wpdb;
		$table = sources_table();
		$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sources = array(
			array( 'dwyl/english-words (words_alpha.txt)', 'https://github.com/dwyl/english-words', 'Unlicense', self::words_path() ),
			// License copied as unverified on purpose: check the source page before stating one.
			array( 'Peter Norvig word frequencies (count_1w.txt)', 'https://norvig.com/ngrams/', 'UNVERIFIED', self::freq_path() ),
		);
		foreach ( $sources as $s ) {
			$wpdb->insert(
				$table,
				array(
					'source_name' => $s[0],
					'url'         => $s[1],
					'license'     => $s[2],
					'sha256'      => is_readable( $s[3] ) ? hash_file( 'sha256', $s[3] ) : '',
					'acquired_at' => current_time( 'mysql', true ),
				)
			);
		}
	}

	public static function stats(): array {
		global $wpdb;
		$table = words_table();
		return array(
			'total'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'ranked'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE freq_rank IS NOT NULL" ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'by_length'  => wp_list_pluck( $wpdb->get_results( "SELECT len, COUNT(*) n FROM {$table} GROUP BY len ORDER BY len" ), 'n', 'len' ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'json_files' => get_option( 'wordmivo_json_files', array() ),
		);
	}
}
