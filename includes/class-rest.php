<?php
/**
 * REST endpoint for rack-based solvers: anagram, unscramble, Scrabble rack.
 *
 * GET /wp-json/wordmivo/v1/words?mode=anagram|unscramble|rack&letters=abc?
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
						'enum'     => array( 'anagram', 'unscramble', 'rack' ),
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
		if ( ! preg_match( '/^[a-z?]{2,' . MAX_RACK . '}$/', $value ) ) {
			return new \WP_Error( 'wordmivo_letters', 'Use 2-15 letters a-z, with ? for a blank.', array( 'status' => 400 ) );
		}
		if ( substr_count( $value, '?' ) > MAX_BLANKS ) {
			return new \WP_Error( 'wordmivo_blanks', 'At most 2 blanks are allowed.', array( 'status' => 400 ) );
		}
		return true;
	}

	private static function rate_limited(): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'wm_rl_' . md5( $ip . wp_salt( 'nonce' ) . gmdate( 'YmdHi' ) );
		$n   = (int) get_transient( $key );
		set_transient( $key, $n + 1, 70 );
		return $n >= self::RATE_LIMIT;
	}

	public static function words( \WP_REST_Request $request ) {
		if ( self::rate_limited() ) {
			return new \WP_Error( 'wordmivo_rate_limit', 'Too many requests. Please wait a minute.', array( 'status' => 429 ) );
		}
		$mode    = $request['mode'];
		$letters = $request['letters'];
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
