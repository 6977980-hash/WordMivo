<?php
/**
 * REST endpoint for rack-based solvers: anagram, unscramble, Scrabble rack.
 *
 * GET /wp-json/wordmivo/v1/words?mode=anagram|unscramble|rack|bee|boxed&letters=abc?
 *
 * bee: 7 letters, the first is the centre letter. boxed: 12 letters, three per side.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

class Rest {

	const MAX_RESULTS = 1000;
	const RATE_LIMIT  = 60;

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	public static function register(): void {
		register_rest_route(
			'wordmivo/v1',
			'/words',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'words' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'mode'    => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'anagram', 'unscramble', 'rack', 'bee', 'boxed', 'clue', 'filter', 'define' ),
					),
					'clue'    => array(
						'type'              => 'string',
						'sanitize_callback' => static fn( $v ) => mb_substr( sanitize_text_field( (string) $v ), 0, 120 ),
					),
					'include' => array(
						'type'              => 'string',
						'sanitize_callback' => static fn( $v ) => substr( preg_replace( '/[^a-z]/', '', strtolower( (string) $v ) ), 0, 15 ),
					),
					'exclude' => array(
						'type'              => 'string',
						'sanitize_callback' => static fn( $v ) => substr( preg_replace( '/[^a-z]/', '', strtolower( (string) $v ) ), 0, 26 ),
					),
					'notat'   => array(
						'type'              => 'string',
						'sanitize_callback' => static fn( $v ) => substr( preg_replace( '/[^a-z,]/', '', strtolower( (string) $v ) ), 0, 100 ),
					),
					'letters' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => static fn( $v ) => strtolower( (string) $v ),
						'validate_callback' => array( __CLASS__, 'validate_letters' ),
					),
				),
			)
		);
	}

	public static function validate_letters( $value ) {
		$value = strtolower( (string) $value );
		if ( preg_match( '/^[a-z]{3}(,[a-z]{3}){3}$|^[a-z]{7}$/', $value ) ) {
			return true; // Letter Boxed sides or Spelling Bee letters; checked per mode later.
		}
		if ( ! preg_match( '/^[a-z?]{2,' . MAX_RACK . '}$/', $value ) ) {
			return new \WP_Error( 'wordmivo_letters', 'Use 2-15 letters a-z, with ? for a blank.', array( 'status' => 400 ) );
		}
		return true; // Blank limits depend on the mode; checked in words().
	}

	private static function rate_limited(): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'wm_rl_' . md5( $ip . wp_salt( 'nonce' ) . gmdate( 'YmdHi' ) );
		$n   = (int) get_transient( $key );
		set_transient( $key, $n + 1, 70 );
		// ChatGPT actions arrive from a few shared OpenAI addresses, so they get more room.
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return $n >= ( str_contains( $ua, 'ChatGPT' ) || str_contains( $ua, 'OpenAI' ) ? 20 * self::RATE_LIMIT : self::RATE_LIMIT );
	}

	public static function words( \WP_REST_Request $request ) {
		if ( self::rate_limited() ) {
			return new \WP_Error( 'wordmivo_rate_limit', 'Too many requests. Please wait a minute.', array( 'status' => 429 ) );
		}
		$mode    = $request['mode'];
		$letters = $request['letters'];
		if ( 'clue' === $mode ) {
			return self::clue( (string) $request['clue'], $letters );
		}
		if ( 'filter' === $mode ) {
			return self::filter( $letters, (string) $request['include'], (string) $request['exclude'], (string) $request['notat'] );
		}
		if ( 'define' === $mode ) {
			return self::define( $letters );
		}
		if ( substr_count( $letters, '?' ) > MAX_BLANKS ) {
			return new \WP_Error( 'wordmivo_blanks', 'At most 2 blanks are allowed.', array( 'status' => 400 ) );
		}
		if ( 'bee' === $mode || 'boxed' === $mode ) {
			return self::puzzle( $mode, $letters );
		}
		if ( 'anagram' === $mode && str_contains( $letters, '?' ) ) {
			$mode = 'rack';
		}
		$words    = 'anagram' === $mode ? self::anagrams( $letters ) : self::from_rack( $letters, 'unscramble' === $mode ? 3 : 2 );
		$response = rest_ensure_response(
			array(
				'mode'    => $mode,
				'letters' => $letters,
				'count'   => count( $words ),
				'words'   => $words,
			)
		);
		$response->header( 'Cache-Control', 'public, max-age=86400' );
		return $response;
	}

	private static function puzzle( string $mode, string $letters ) {
		$letters = preg_replace( '/[^a-z]/', '', $letters );
		$need    = 'bee' === $mode ? 7 : 12;
		if ( strlen( $letters ) !== $need || count( array_unique( str_split( $letters ) ) ) !== $need ) {
			return new \WP_Error( 'wordmivo_letters', sprintf( 'Enter %d different letters.', $need ), array( 'status' => 400 ) );
		}
		$data     = 'bee' === $mode ? self::spelling_bee( $letters ) : self::letter_boxed( $letters );
		$response = rest_ensure_response( array_merge( array( 'mode' => $mode, 'letters' => $letters ), $data ) );
		$response->header( 'Cache-Control', 'public, max-age=86400' );
		return $response;
	}

	/**
	 * Spelling Bee: words of 4+ letters using only the 7 letters and always the centre
	 * (first) letter. Points: 1 for 4 letters, else one per letter, +7 for a pangram.
	 */
	public static function spelling_bee( string $letters ): array {
		global $wpdb;
		$table   = words_table();
		$all     = letter_mask( $letters );
		$outside = ~$all & 0x3FFFFFF;
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT word, letter_mask FROM {$table} WHERE len >= 4 AND is_valid = 1 AND (letter_mask & %d) = 0 AND (letter_mask & %d) <> 0 ORDER BY freq_rank IS NULL, freq_rank LIMIT %d", $outside, letter_bit( $letters[0] ), self::MAX_RESULTS ), ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out     = array();
		foreach ( $rows as $r ) {
			$pangram = (int) $r[1] === $all;
			$points  = 4 === strlen( $r[0] ) ? 1 : strlen( $r[0] );
			$out[]   = array( $r[0], $points + ( $pangram ? 7 : 0 ), $pangram ? 1 : 0 );
		}
		usort( $out, static fn( $a, $b ) => array( $b[2], strlen( $b[0] ) ) <=> array( $a[2], strlen( $a[0] ) ) );
		return array(
			'count' => count( $out ),
			'words' => $out,
		);
	}

	/**
	 * Letter Boxed: words of 3+ letters from the 12 letters where consecutive letters
	 * never come from the same side; plus one- and two-word solutions that use all 12.
	 */
	public static function letter_boxed( string $letters ): array {
		global $wpdb;
		$table = words_table();
		$side  = array();
		foreach ( str_split( $letters ) as $i => $l ) {
			$side[ $l ] = intdiv( $i, 3 );
		}
		$all   = letter_mask( $letters );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT word, letter_mask, freq_rank FROM {$table} WHERE len >= 3 AND is_valid = 1 AND (letter_mask & %d) = 0", ~$all & 0x3FFFFFF ), ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$words = array();
		foreach ( $rows as $r ) {
			$w  = $r[0];
			$ok = true;
			for ( $i = 1, $n = strlen( $w ); $i < $n; $i++ ) {
				if ( $side[ $w[ $i ] ] === $side[ $w[ $i - 1 ] ] ) {
					$ok = false;
					break;
				}
			}
			if ( $ok ) {
				$words[] = array( $w, (int) $r[1], $r[2] ? (int) $r[2] : 1000000 );
			}
		}
		// Solutions: chains where each word starts with the previous word's last letter.
		$by_first = array();
		foreach ( $words as $i => $w ) {
			$by_first[ $w[0][0] ][] = $i;
		}
		$solutions = array();
		foreach ( $words as $a ) {
			if ( $a[1] === $all ) {
				$solutions[] = array( array( $a[0] ), strlen( $a[0] ), $a[2] );
				continue;
			}
			foreach ( $by_first[ substr( $a[0], -1 ) ] ?? array() as $j ) {
				$b = $words[ $j ];
				if ( ( $a[1] | $b[1] ) === $all ) {
					$solutions[] = array( array( $a[0], $b[0] ), strlen( $a[0] ) + strlen( $b[0] ), max( $a[2], $b[2] ) );
				}
			}
		}
		// Fewest words, then the most common words, then the shortest.
		usort( $solutions, static fn( $x, $y ) => array( count( $x[0] ), $x[2], $x[1] ) <=> array( count( $y[0] ), $y[2], $y[1] ) );
		usort( $words, static fn( $x, $y ) => array( self::bits( $y[1] ), $x[2] ) <=> array( self::bits( $x[1] ), $y[2] ) );
		return array(
			'count'     => count( $words ),
			'words'     => array_map( static fn( $w ) => array( $w[0], self::bits( $w[1] ) ), array_slice( $words, 0, 500 ) ),
			'solutions' => array_map( static fn( $s ) => $s[0], array_slice( $solutions, 0, 30 ) ),
		);
	}

	private static function bits( int $mask ): int {
		$n = 0;
		for ( ; $mask; $mask &= $mask - 1 ) {
			++$n;
		}
		return $n;
	}

	/**
	 * Crossword clue / reverse dictionary. $pattern uses ? for unknown letters
	 * (c???e); its length is the answer length. An empty clue lists pattern matches.
	 */
	public static function clue( string $clue, string $pattern ) {
		global $wpdb;
		$pattern = preg_replace( '/[^a-z?]/', '', strtolower( $pattern ) );
		$len     = strlen( $pattern );
		if ( $len < 2 ) {
			return new \WP_Error( 'wordmivo_letters', 'Enter the answer length or pattern, e.g. ????? or c???e.', array( 'status' => 400 ) );
		}
		$like  = str_replace( '?', '_', $pattern );
		$table = words_table();
		$defs  = defs_table();
		$terms = trim( preg_replace( '/[^a-z0-9\s-]/', ' ', strtolower( $clue ) ) );
		if ( '' === $terms ) {
			if ( ! str_contains( $pattern, '?' ) || strlen( str_replace( '?', '', $pattern ) ) === 0 ) {
				return new \WP_Error( 'wordmivo_clue', 'Type a clue, or some known letters in the pattern.', array( 'status' => 400 ) );
			}
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT w.word, d.defs FROM {$table} w LEFT JOIN {$defs} d ON d.word = w.word WHERE w.len = %d AND w.word LIKE %s AND w.is_valid = 1 ORDER BY w.freq_rank IS NULL, w.freq_rank LIMIT 100", $len, $like ), ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			// Words whose meaning or synonyms match the clue, best match first, common words breaking ties.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT d.word, d.defs FROM {$defs} d JOIN {$table} w ON w.word = d.word WHERE w.len = %d AND d.word LIKE %s AND MATCH(d.defs) AGAINST (%s IN NATURAL LANGUAGE MODE) ORDER BY MATCH(d.defs) AGAINST (%s IN NATURAL LANGUAGE MODE) DESC, w.freq_rank IS NULL, w.freq_rank LIMIT 50", $len, $like, $terms, $terms ), ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$out  = array();
		$skip = array_flip( preg_split( '/\s+/', $terms ) ); // The answer is never a word of the clue.
		foreach ( (array) $rows as $r ) {
			if ( isset( $skip[ $r[0] ] ) ) {
				continue;
			}
			// Show the sense that shares the most words with the clue.
			$best  = '';
			$score = -1;
			foreach ( Words::parse( (string) $r[1] ) as $senses ) {
				foreach ( $senses as $sense ) {
					$n = count( array_intersect_key( array_flip( preg_split( '/[^a-z]+/', strtolower( $sense[0] ) ) ), $skip ) );
					if ( $n > $score ) {
						$best  = $sense[0];
						$score = $n;
					}
				}
			}
			$out[] = array( $r[0], $best );
		}
		$response = rest_ensure_response(
			array(
				'mode'    => 'clue',
				'clue'    => $clue,
				'letters' => $pattern,
				'count'   => count( $out ),
				'words'   => $out,
			)
		);
		$response->header( 'Cache-Control', 'public, max-age=86400' );
		return $response;
	}

	/**
	 * Wordle-style filter. $pattern: known letters with ? for unknown (?r??e);
	 * include: letters that must appear; exclude: letters that must not;
	 * notat: comma list per position of letters that are in the word but not there.
	 */
	public static function filter( string $pattern, string $include, string $exclude, string $notat ) {
		global $wpdb;
		$pattern = preg_replace( '/[^a-z?]/', '', $pattern );
		$len     = strlen( $pattern );
		if ( $len < 2 ) {
			return new \WP_Error( 'wordmivo_letters', 'Give the pattern, e.g. ????? or ?r??e.', array( 'status' => 400 ) );
		}
		$not  = array_slice( explode( ',', $notat ), 0, $len );
		// Each include letter as many times as given; each yellow letter at least once.
		$need = count_chars( $include, 1 );
		foreach ( count_chars( implode( '', $not ), 1 ) as $code => $n ) {
			$need[ $code ] = max( $need[ $code ] ?? 0, 1 );
		}
		$keep = $include . implode( '', $not ) . str_replace( '?', '', $pattern );
		$ban  = array_diff( array_unique( str_split( $exclude ) ), str_split( $keep ), array( '' ) );
		$table = words_table();
		$sql   = $wpdb->prepare( "SELECT word, scrabble_score, is_likely FROM {$table} WHERE len = %d AND is_valid = 1 AND word LIKE %s", $len, str_replace( '?', '_', $pattern ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $ban ) {
			$sql .= $wpdb->prepare( ' AND (letter_mask & %d) = 0', letter_mask( implode( '', $ban ) ) );
		}
		if ( $need ) {
			$mask = letter_mask( implode( '', array_map( 'chr', array_keys( $need ) ) ) );
			$sql .= $wpdb->prepare( ' AND (letter_mask & %d) = %d', $mask, $mask );
		}
		$rows = $wpdb->get_results( $sql . ' ORDER BY is_likely DESC, freq_rank IS NULL, freq_rank LIMIT 2000', ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out  = array();
		foreach ( $rows as $r ) {
			$ok   = true;
			$have = count_chars( $r[0], 1 );
			foreach ( $need as $code => $n ) {
				if ( ( $have[ $code ] ?? 0 ) < $n ) {
					$ok = false;
				}
			}
			foreach ( $not as $i => $letters ) {
				if ( '' !== $letters && false !== strpos( $letters, $r[0][ $i ] ) ) {
					$ok = false;
				}
			}
			if ( $ok ) {
				$out[] = array( $r[0], (int) $r[1], (int) $r[2] );
			}
			if ( count( $out ) >= 300 ) {
				break;
			}
		}
		return rest_ensure_response(
			array(
				'mode'  => 'filter',
				'count' => count( $out ),
				'words' => $out,
				'note'  => '[word, scrabble score, likely Wordle answer 1/0], likely and common words first.',
			)
		);
	}

	/** Everything about one word: meaning, synonyms, score, validity, page URL. */
	public static function define( string $word ) {
		$word = preg_replace( '/[^a-z]/', '', $word );
		$w    = Words::get( $word );
		if ( ! $w ) {
			return rest_ensure_response( array( 'word' => $word, 'valid' => false, 'note' => 'Not in our word list.' ) );
		}
		global $wpdb;
		$defs = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT defs FROM ' . defs_table() . ' WHERE word = %s', $word ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return rest_ensure_response(
			array(
				'word'           => $word,
				'valid'          => (bool) $w->is_valid,
				'scrabble_score' => $w->score,
				'likely_wordle'  => (bool) $w->is_likely,
				'base_form'      => $w->base ? $w->base : null,
				'meanings'       => $w->meanings,
				'synonyms'       => Words::synonyms( $defs ),
				'anagrams'       => Words::anagrams( $w ),
				'url'            => Words::url( $word ),
			)
		);
	}

	/** Exact anagrams: same letters, same counts. */
	public static function anagrams( string $letters ): array {
		global $wpdb;
		$table = words_table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT word, scrabble_score FROM {$table} WHERE signature = %s ORDER BY freq_rank IS NULL, freq_rank, word LIMIT %d", signature( $letters ), self::MAX_RESULTS ), ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( static fn( $r ) => array( $r[0], (int) $r[1] ), $rows );
	}

	/**
	 * Every word of length >= $min buildable from the rack (blanks = '?').
	 * SQL narrows by letters outside the rack (at most one per blank); PHP checks counts.
	 */
	public static function from_rack( string $rack, int $min ): array {
		global $wpdb;
		$table   = words_table();
		$blanks  = substr_count( $rack, '?' );
		$letters = str_replace( '?', '', $rack );
		$outside = ~letter_mask( $letters ) & 0x3FFFFFF;
		$rows    = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT word FROM {$table} WHERE len BETWEEN %d AND %d AND BIT_COUNT(letter_mask & %d) <= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$min,
				strlen( $rack ),
				$outside,
				$blanks
			)
		);
		$out = array();
		foreach ( $rows as $word ) {
			$score = rack_score( $word, $rack );
			if ( null !== $score ) {
				$out[] = array( $word, $score );
			}
		}
		usort( $out, static fn( $a, $b ) => array( strlen( $b[0] ), $b[1], $a[0] ) <=> array( strlen( $a[0] ), $a[1], $b[0] ) );
		return array_slice( $out, 0, self::MAX_RESULTS );
	}
}
