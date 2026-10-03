<?php
/**
 * Resumable, batched import of the word list and frequency list.
 *
 * Stages: words -> valid -> ranks -> likely -> defs -> json -> counts -> done. Each call to step()
 * does a bounded amount of work so it fits inside shared-hosting timeouts.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

class Importer {

	const WORDS_FILE = 'words_alpha.txt';
	const FREQ_FILE  = 'count_1w.txt';
	const DICT_FILE  = 'enable1.txt';
	const DEFS_FILE  = 'definitions.tsv';
	const LIKELY_MAX_RANK = 40000;
	const STATE      = 'wordmivo_import_state';

	public static function words_path(): string {
		return trailingslashit( WORDMIVO_DATA_DIR ) . self::WORDS_FILE;
	}

	public static function dict_path(): string {
		return trailingslashit( WORDMIVO_DATA_DIR ) . self::DICT_FILE;
	}

	public static function defs_path(): string {
		return trailingslashit( WORDMIVO_DATA_DIR ) . self::DEFS_FILE;
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
			case 'valid':
				$state = self::step_valid( $state, $batch );
				break;
			case 'ranks':
				$state = self::step_ranks( $state, $batch );
				break;
			case 'likely':
				$state['likely'] = self::mark_likely();
				$state['stage']  = 'defs';
				$state['offset'] = 0;
				break;
			case 'defs':
				$state = self::step_defs( $state, $batch );
				break;
			case 'json':
				self::write_json();
				$state['stage'] = 'counts';
				break;
			case 'counts':
				Pages::rebuild_counts();
				self::record_sources();
				$state['stage'] = 'done';
				update_option( 'wordmivo_data_rev', Installer::DATA_REV );
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
			$rows[] = self::row_sql( $word, 0 );
		}
		$state['offset'] = ftell( $fh );
		$eof             = feof( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( $rows ) {
			$table = words_table();
			// Values are prepared above; INSERT IGNORE keeps re-imports idempotent.
			$wpdb->query( 'INSERT IGNORE INTO ' . $table . ' ' . self::COLUMNS . ' VALUES ' . implode( ',', $rows ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$state['done'] += count( $rows );
		}
		if ( $eof || 0 === $read ) {
			$state['stage']  = 'valid';
			$state['offset'] = 0;
		}
		return $state;
	}

	const COLUMNS = '(word,len,first_letter,last_letter,signature,letter_mask,vowels,has_double,scrabble_score,is_valid)';

	private static function row_sql( string $word, int $valid ): string {
		global $wpdb;
		return $wpdb->prepare(
			'(%s,%d,%s,%s,%s,%d,%d,%d,%d,%d)',
			$word,
			strlen( $word ),
			$word[0],
			substr( $word, -1 ),
			signature( $word ),
			letter_mask( $word ),
			vowel_count( $word ),
			has_repeat( $word ) ? 1 : 0,
			scrabble_score( $word ),
			$valid
		);
	}

	/**
	 * ENABLE (public domain Scrabble-style dictionary): flags words as valid and adds
	 * valid words missing from the main list.
	 */
	private static function step_valid( array $state, int $batch ): array {
		global $wpdb;
		if ( ! is_readable( self::dict_path() ) ) {
			$state['stage'] = 'ranks';
			return $state;
		}
		$fh = fopen( self::dict_path(), 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fseek( $fh, (int) $state['offset'] );
		$rows = array();
		$read = 0;
		while ( $read < $batch && false !== ( $line = fgets( $fh ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			++$read;
			$word = strtolower( trim( $line ) );
			if ( is_valid_word( $word ) ) {
				$rows[] = self::row_sql( $word, 1 );
			}
		}
		$state['offset'] = ftell( $fh );
		$eof             = feof( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( $rows ) {
			$table = words_table();
			$wpdb->query( 'INSERT INTO ' . $table . ' ' . self::COLUMNS . ' VALUES ' . implode( ',', $rows ) . ' ON DUPLICATE KEY UPDATE is_valid = 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		if ( $eof || 0 === $read ) {
			$state['stage']  = 'ranks';
			$state['offset'] = 0;
		}
		return $state;
	}

	/**
	 * Our own estimate of likely Wordle-style answers: five-letter dictionary words
	 * that are common in English and are not a simple plural or past tense.
	 */
	public static function mark_likely(): int {
		global $wpdb;
		$table = words_table();
		$valid = array_flip( $wpdb->get_col( "SELECT word FROM {$table} WHERE is_valid = 1 AND len BETWEEN 3 AND 5" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cands = $wpdb->get_col( $wpdb->prepare( "SELECT word FROM {$table} WHERE len = 5 AND is_valid = 1 AND freq_rank <= %d", self::LIKELY_MAX_RANK ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$likely = array();
		foreach ( $cands as $w ) {
			$plural = str_ends_with( $w, 's' ) && ! str_ends_with( $w, 'ss' ) && ( isset( $valid[ substr( $w, 0, -1 ) ] ) || ( str_ends_with( $w, 'es' ) && isset( $valid[ substr( $w, 0, -2 ) ] ) ) );
			$past   = str_ends_with( $w, 'ed' ) && ( isset( $valid[ substr( $w, 0, -2 ) ] ) || isset( $valid[ substr( $w, 0, -1 ) ] ) );
			if ( ! $plural && ! $past ) {
				$likely[] = $w;
			}
		}
		$wpdb->query( "UPDATE {$table} SET is_likely = 0 WHERE is_likely = 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( array_chunk( $likely, 1000 ) as $chunk ) {
			$in = implode( ',', array_map( static fn( $w ) => $wpdb->prepare( '%s', $w ), $chunk ) );
			$wpdb->query( "UPDATE {$table} SET is_likely = 1 WHERE word IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return count( $likely );
	}

	/**
	 * definitions.tsv (built from WordNet 3.0): "word<TAB>n:def~example|def<TAB>v:..."
	 * or "word<TAB>=base" for inflected forms.
	 */
	private static function step_defs( array $state, int $batch ): array {
		global $wpdb;
		if ( ! is_readable( self::defs_path() ) ) {
			$state['stage'] = 'json';
			return $state;
		}
		$fh = fopen( self::defs_path(), 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fseek( $fh, (int) $state['offset'] );
		$rows = array();
		$read = 0;
		while ( $read < $batch && false !== ( $line = fgets( $fh ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			++$read;
			$parts = explode( "\t", rtrim( $line, "\r\n" ), 2 );
			if ( 2 !== count( $parts ) || ! is_valid_word( $parts[0] ) ) {
				continue;
			}
			$base   = str_starts_with( $parts[1], '=' ) ? substr( $parts[1], 1 ) : '';
			$rows[] = $wpdb->prepare( '(%s,%s,%s)', $parts[0], is_valid_word( $base ) ? $base : '', $base ? '' : $parts[1] );
		}
		$state['offset'] = ftell( $fh );
		$eof             = feof( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( $rows ) {
			$wpdb->query( 'REPLACE INTO ' . defs_table() . ' (word,base,defs) VALUES ' . implode( ',', $rows ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		if ( $eof || 0 === $read ) {
			$state['stage']  = 'json';
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
			$state['stage'] = 'likely';
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
			$state['stage']  = 'likely';
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
		$dict  = Pages::has_dictionary() ? ' AND is_valid = 1' : '';
		for ( $len = MIN_LEN; $len <= MAX_LEN; $len++ ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT word, scrabble_score, freq_rank, is_valid + 2 * is_likely FROM {$table} WHERE len = %d{$dict} ORDER BY is_likely DESC, is_valid DESC, freq_rank IS NULL, freq_rank, word", $len ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_N
			);
			// [word, score, rank (0 = unranked), flags: 1 = dictionary word, 2 = likely answer].
			$data = array_map( static fn( $r ) => array( $r[0], (int) $r[1], (int) $r[2], (int) $r[3] ), $rows );
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
			array( 'ENABLE word list (enable1.txt)', 'https://github.com/dolph/dictionary', 'Public domain (ENABLE2K by its authors)', self::dict_path() ),
			array( 'Peter Norvig word frequencies (count_1w.txt)', 'https://github.com/norvig/pytudes', 'MIT (norvig/pytudes)', self::freq_path() ),
			array( 'WordNet 3.0 definitions (definitions.tsv)', 'https://wordnet.princeton.edu/', 'WordNet 3.0 licence (Princeton University), see data/WORDNET-LICENSE.txt', self::defs_path() ),
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
