<?php
/**
 * Virtual, server-rendered word-list and tool pages.
 *
 * A page is described by a "spec": type + length + letter(s). Specs map to
 * URLs, SQL filters, titles and counts. No WordPress posts are created.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

class Pages {

	const MIN_INDEXABLE = 5;
	const LIST_CAP      = 3000;
	const COMMON_RANK   = 20000;

	const SETS = array(
		'hubs'     => 'Finder hubs (3-8 letters)',
		'starts'   => 'Starting with A-Z (3-7 letters)',
		'ends'     => 'Ending in A-Z (3-7 letters)',
		'tools'    => 'Tool pages (Wordle solver, anagram, unscrambler, Scrabble, daily hints)',
		'contains' => 'Containing a letter (4-6 letters)',
		'position' => 'Letter in 2nd/3rd/4th position (5 letters)',
		'special'  => 'No vowels / double letters / three vowels (4-6 letters)',
		'bigram'   => 'Starting/ending with letter pairs (5 letters)',
	);

	const DEFAULT_SETS = array( 'hubs', 'starts', 'ends', 'tools' );

	const SPECIALS = array(
		'with-no-vowels'      => 'with No Vowels',
		'with-double-letters' => 'with Double Letters',
		'with-three-vowels'   => 'with Three Vowels',
	);

	const START_BIGRAMS = array( 'st', 'ch', 'sh', 'tr', 'br', 'cr', 'gr', 'pl', 'sl', 'sp' );
	const END_BIGRAMS   = array( 'er', 'ly', 'ch', 'sh', 'ed' );

	const TOOLS = array(
		'wordle-solver'        => 'Wordle Solver',
		'anagram-solver'       => 'Anagram Solver',
		'word-unscrambler'     => 'Word Unscrambler',
		'scrabble-word-finder' => 'Scrabble Word Finder',
		'todays-wordle-hints'  => "Today's Wordle Hints",
	);

	/** @var array|null Spec for the current request. */
	private static $current = null;

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ), 1 );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 99 );
	}

	public static function enabled_sets(): array {
		$sets = get_option( 'wordmivo_page_sets', self::DEFAULT_SETS );
		return is_array( $sets ) ? $sets : self::DEFAULT_SETS;
	}

	public static function add_rewrite_rules(): void {
		$n   = '([3-8])';
		$top = 'top';
		add_rewrite_rule( "^{$n}-letter-words/?$", 'index.php?wm_type=hub&wm_len=$matches[1]', $top );
		add_rewrite_rule( "^{$n}-letter-words-starting-with-([a-z]{1,2})/?$", 'index.php?wm_type=starts&wm_len=$matches[1]&wm_x=$matches[2]', $top );
		add_rewrite_rule( "^{$n}-letter-words-ending-in-([a-z]{1,2})/?$", 'index.php?wm_type=ends&wm_len=$matches[1]&wm_x=$matches[2]', $top );
		add_rewrite_rule( "^{$n}-letter-words-with-([a-z])-as-(second|third|fourth)-letter/?$", 'index.php?wm_type=position&wm_len=$matches[1]&wm_x=$matches[2]&wm_pos=$matches[3]', $top );
		add_rewrite_rule( "^{$n}-letter-words-(with-no-vowels|with-double-letters|with-three-vowels)/?$", 'index.php?wm_type=special&wm_len=$matches[1]&wm_x=$matches[2]', $top );
		add_rewrite_rule( "^{$n}-letter-words-with-([a-z])/?$", 'index.php?wm_type=contains&wm_len=$matches[1]&wm_x=$matches[2]', $top );
		add_rewrite_rule( '^(' . implode( '|', array_keys( self::TOOLS ) ) . ')/?$', 'index.php?wm_type=tool&wm_x=$matches[1]', $top );
	}

	public static function query_vars( array $vars ): array {
		return array_merge( $vars, array( 'wm_type', 'wm_len', 'wm_x', 'wm_pos' ) );
	}

	/**
	 * Every page the site can serve, regardless of enabled sets.
	 */
	public static function all_specs(): array {
		$specs   = array();
		$letters = range( 'a', 'z' );
		for ( $len = MIN_LEN; $len <= MAX_LEN; $len++ ) {
			$specs[] = array( 'type' => 'hub', 'len' => $len );
		}
		for ( $len = 3; $len <= 7; $len++ ) {
			foreach ( $letters as $x ) {
				$specs[] = array( 'type' => 'starts', 'len' => $len, 'x' => $x );
				$specs[] = array( 'type' => 'ends', 'len' => $len, 'x' => $x );
			}
		}
		for ( $len = 4; $len <= 6; $len++ ) {
			foreach ( $letters as $x ) {
				$specs[] = array( 'type' => 'contains', 'len' => $len, 'x' => $x );
			}
			foreach ( array_keys( self::SPECIALS ) as $x ) {
				$specs[] = array( 'type' => 'special', 'len' => $len, 'x' => $x );
			}
		}
		foreach ( $letters as $x ) {
			foreach ( array( 'second', 'third', 'fourth' ) as $pos ) {
				$specs[] = array( 'type' => 'position', 'len' => 5, 'x' => $x, 'pos' => $pos );
			}
		}
		foreach ( self::START_BIGRAMS as $x ) {
			$specs[] = array( 'type' => 'starts', 'len' => 5, 'x' => $x );
		}
		foreach ( self::END_BIGRAMS as $x ) {
			$specs[] = array( 'type' => 'ends', 'len' => 5, 'x' => $x );
		}
		foreach ( array_keys( self::TOOLS ) as $x ) {
			$specs[] = array( 'type' => 'tool', 'x' => $x );
		}
		return array_map( array( __CLASS__, 'normalize' ), $specs );
	}

	public static function normalize( array $spec ): array {
		return wp_parse_args(
			$spec,
			array(
				'type' => '',
				'len'  => 0,
				'x'    => '',
				'pos'  => '',
			)
		);
	}

	public static function key( array $spec ): string {
		return implode( ':', array( $spec['type'], $spec['len'], $spec['x'], $spec['pos'] ) );
	}

	/** Which admin page set a spec belongs to. */
	public static function set_of( array $spec ): string {
		switch ( $spec['type'] ) {
			case 'hub':
				return 'hubs';
			case 'tool':
				return 'tools';
			case 'starts':
			case 'ends':
				return strlen( $spec['x'] ) === 2 ? 'bigram' : $spec['type'];
			default:
				return $spec['type'];
		}
	}

	/** Known spec (part of all_specs) in an enabled set? */
	public static function is_served( array $spec ): bool {
		static $known = null;
		if ( null === $known ) {
			$known = array_flip( array_map( array( __CLASS__, 'key' ), self::all_specs() ) );
		}
		return isset( $known[ self::key( $spec ) ] ) && in_array( self::set_of( $spec ), self::enabled_sets(), true );
	}

	public static function current(): ?array {
		return self::$current;
	}

	public static function template_redirect(): void {
		$type = get_query_var( 'wm_type' );

		if ( ! $type && is_front_page() && get_option( 'wordmivo_home_finder', 1 ) && ! is_paged() ) {
			self::$current = self::normalize( array( 'type' => 'hub', 'len' => 5, 'home' => true ) );
			self::$current['home'] = true;
			return;
		}
		if ( ! $type ) {
			return;
		}
		$spec = self::normalize(
			array(
				'type' => sanitize_key( $type ),
				'len'  => (int) get_query_var( 'wm_len' ),
				'x'    => sanitize_key( get_query_var( 'wm_x' ) ),
				'pos'  => sanitize_key( get_query_var( 'wm_pos' ) ),
			)
		);

		// The 5-letter hub is the home page.
		if ( 'hub' === $spec['type'] && 5 === $spec['len'] && get_option( 'wordmivo_home_finder', 1 ) ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
		if ( ! self::is_served( $spec ) || ( 'tool' !== $spec['type'] && 'hub' !== $spec['type'] && 0 === self::count( $spec ) ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}
		self::$current = $spec;
		if ( 'tool' === $spec['type'] && 'todays-wordle-hints' === $spec['x'] ) {
			// Daily content: keep LiteSpeed's page cache short.
			do_action( 'litespeed_control_set_ttl', 900 );
		}
		global $wp_query;
		$wp_query->is_404  = false;
		$wp_query->is_home = false;
		status_header( 200 );
	}

	public static function template_include( string $template ): string {
		if ( ! self::$current ) {
			return $template;
		}
		return 'tool' === self::$current['type']
			? WORDMIVO_DIR . 'templates/page-tool.php'
			: WORDMIVO_DIR . 'templates/page-list.php';
	}

	public static function url( array $spec ): string {
		$spec = self::normalize( $spec );
		$n    = $spec['len'];
		switch ( $spec['type'] ) {
			case 'hub':
				$path = 5 === $n && get_option( 'wordmivo_home_finder', 1 ) ? '' : "{$n}-letter-words/";
				break;
			case 'starts':
				$path = "{$n}-letter-words-starting-with-{$spec['x']}/";
				break;
			case 'ends':
				$path = "{$n}-letter-words-ending-in-{$spec['x']}/";
				break;
			case 'contains':
				$path = "{$n}-letter-words-with-{$spec['x']}/";
				break;
			case 'position':
				$path = "{$n}-letter-words-with-{$spec['x']}-as-{$spec['pos']}-letter/";
				break;
			case 'special':
				$path = "{$n}-letter-words-{$spec['x']}/";
				break;
			case 'tool':
				$path = "{$spec['x']}/";
				break;
			default:
				$path = '';
		}
		return home_url( '/' . $path );
	}

	/**
	 * SQL WHERE clause (already prepared) for a list spec.
	 */
	public static function where( array $spec ): string {
		global $wpdb;
		$len   = (int) $spec['len'];
		$x     = $spec['x'];
		$where = $wpdb->prepare( 'len = %d', $len );
		switch ( $spec['type'] ) {
			case 'starts':
				$where .= strlen( $x ) === 1
					? $wpdb->prepare( ' AND first_letter = %s', $x )
					: $wpdb->prepare( ' AND word LIKE %s', $wpdb->esc_like( $x ) . '%' );
				break;
			case 'ends':
				$where .= strlen( $x ) === 1
					? $wpdb->prepare( ' AND last_letter = %s', $x )
					: $wpdb->prepare( ' AND word LIKE %s', '%' . $wpdb->esc_like( $x ) );
				break;
			case 'contains':
				$where .= $wpdb->prepare( ' AND (letter_mask & %d) <> 0', letter_bit( $x ) );
				break;
			case 'position':
				$where .= $wpdb->prepare( ' AND SUBSTRING(word, %d, 1) = %s', ordinal_positions()[ $spec['pos'] ] ?? 1, $x );
				break;
			case 'special':
				if ( 'with-no-vowels' === $x ) {
					$where .= $wpdb->prepare( ' AND (letter_mask & %d) = 0', vowel_mask() );
				} elseif ( 'with-double-letters' === $x ) {
					$where .= ' AND has_double = 1';
				} elseif ( 'with-three-vowels' === $x ) {
					$where .= ' AND vowels = 3';
				}
				break;
		}
		return $where;
	}

	public static function count( array $spec ): int {
		$counts = get_option( 'wordmivo_page_counts', array() );
		$key    = self::key( $spec );
		if ( isset( $counts[ $key ] ) ) {
			return (int) $counts[ $key ];
		}
		return self::count_live( $spec );
	}

	private static function count_live( array $spec ): int {
		global $wpdb;
		if ( 'tool' === $spec['type'] ) {
			return 0;
		}
		$table = words_table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE " . self::where( $spec ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function rebuild_counts(): array {
		$counts = array();
		foreach ( self::all_specs() as $spec ) {
			if ( 'tool' !== $spec['type'] ) {
				$counts[ self::key( $spec ) ] = self::count_live( $spec );
			}
		}
		update_option( 'wordmivo_page_counts', $counts, false );
		return $counts;
	}

	public static function is_indexable( array $spec ): bool {
		return 'tool' === $spec['type'] || 'hub' === $spec['type'] || self::count( $spec ) >= self::MIN_INDEXABLE;
	}

	/**
	 * Words for a spec: [ 'common' => rows by rank, 'all' => rows A-Z (capped) ].
	 */
	public static function words( array $spec ): array {
		global $wpdb;
		$table   = words_table();
		$where   = self::where( $spec );
		$common  = self::common_sql( $spec );
		$top     = $wpdb->get_results( "SELECT word, scrabble_score AS score, is_likely FROM {$table} WHERE {$where} AND {$common} ORDER BY freq_rank LIMIT 60" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$all     = 'hub' === $spec['type'] ? array() : $wpdb->get_results( $wpdb->prepare( "SELECT word, scrabble_score AS score, is_likely FROM {$table} WHERE {$where} ORDER BY word LIMIT %d", self::LIST_CAP ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total   = 'hub' === $spec['type'] ? self::count_live( $spec ) : self::count( $spec );
		$ncommon = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where} AND {$common}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array(
			'common'   => $top,
			'all'      => $all,
			'total'    => $total,
			'n_common' => $ncommon,
			'likely'   => 5 === (int) $spec['len'],
		);
	}

	/**
	 * "Common" means likely Wordle answers for 5 letters, otherwise frequent dictionary words.
	 * Falls back to frequency alone when the dictionary has not been imported.
	 */
	private static function common_sql( array $spec ): string {
		global $wpdb;
		static $has_valid = null;
		if ( null === $has_valid ) {
			$has_valid = (bool) $wpdb->get_var( 'SELECT 1 FROM ' . words_table() . ' WHERE is_valid = 1 LIMIT 1' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		if ( ! $has_valid ) {
			return $wpdb->prepare( 'freq_rank <= %d', self::COMMON_RANK );
		}
		return 5 === (int) $spec['len'] ? 'is_likely = 1' : $wpdb->prepare( 'is_valid = 1 AND freq_rank <= %d', self::COMMON_RANK );
	}

	/** Most frequent letter at a 1-based position among the spec's words. */
	public static function top_letter_at( array $spec, int $pos ): string {
		global $wpdb;
		$table = words_table();
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT SUBSTRING(word, %d, 1) l FROM {$table} WHERE " . self::where( $spec ) . ' GROUP BY l ORDER BY COUNT(*) DESC LIMIT 1', $pos ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function number_word( int $n ): string {
		$words = array( 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight' );
		return $words[ $n ] ?? (string) $n;
	}

	/** Short, human title used for the H1. */
	public static function heading( array $spec ): string {
		$n = $spec['len'];
		$x = strtoupper( $spec['x'] );
		switch ( $spec['type'] ) {
			case 'hub':
				return "{$n} Letter Word Finder";
			case 'starts':
				return "{$n} Letter Words Starting with {$x}";
			case 'ends':
				return "{$n} Letter Words Ending in {$x}";
			case 'contains':
				return "{$n} Letter Words with {$x}";
			case 'position':
				return "{$n} Letter Words with {$x} as the " . ucfirst( $spec['pos'] ) . ' Letter';
			case 'special':
				return "{$n} Letter Words " . self::SPECIALS[ $spec['x'] ];
			case 'tool':
				return self::TOOLS[ $spec['x'] ];
		}
		return '';
	}

	/** Heading as a lowercase phrase, keeping the letters uppercase: "5 letter words starting with A". */
	public static function phrase( array $spec ): string {
		$text = strtolower( self::heading( $spec ) );
		if ( $spec['x'] && 'special' !== $spec['type'] && 'tool' !== $spec['type'] ) {
			$text = preg_replace( '/\b' . preg_quote( $spec['x'], '/' ) . '\b/', strtoupper( $spec['x'] ), $text, 1 );
		}
		return $text;
	}

	public static function title( array $spec ): string {
		if ( 'tool' === $spec['type'] ) {
			return self::heading( $spec ) . ' | WordMivo';
		}
		if ( 'hub' === $spec['type'] ) {
			return "{$spec['len']} Letter Words: Finder & Full List | WordMivo";
		}
		return self::heading( $spec ) . ' (' . number_format_i18n( self::count( $spec ) ) . ' Words) | WordMivo';
	}

	public static function description( array $spec ): string {
		$n = self::number_word( (int) $spec['len'] );
		switch ( $spec['type'] ) {
			case 'hub':
				return "Find {$n}-letter words fast. Filter by known letters, positions and excluded letters, with common words first. Great for Wordle, Scrabble and crosswords.";
			case 'tool':
				return self::tool_description( $spec['x'] );
		}
		$count = number_format_i18n( self::count( $spec ) );
		return "All {$count} " . self::phrase( $spec ) . ', common words first, with Scrabble scores. Filter the list by letter and position for Wordle and word games.';
	}

	public static function tool_description( string $tool ): string {
		$map = array(
			'wordle-solver'        => 'Enter your guesses and colours to see every possible answer and the best next guess. Free Wordle solver with correct handling of repeated letters.',
			'anagram-solver'       => 'Type any letters to find every word that uses exactly those letters. Fast anagram solver with Scrabble scores.',
			'word-unscrambler'     => 'Unscramble letters into every word you can make, grouped by length and sorted by score.',
			'scrabble-word-finder' => 'Find the best Scrabble words from your rack, including up to two blank tiles, with scores.',
			'todays-wordle-hints'  => "Spoiler-free hints for today's Wordle, with the answer hidden until you choose to reveal it.",
		);
		return $map[ $tool ] ?? '';
	}

	/**
	 * Short question/answer pairs for the page: visible FAQ + FAQPage schema.
	 * Answers lead with the fact so search and AI answers can quote them.
	 */
	public static function faq( array $spec ): array {
		$n     = (int) $spec['len'];
		$words = self::words( $spec );
		$total = number_format_i18n( $words['total'] );
		$ex    = implode( ', ', array_slice( wp_list_pluck( $words['common'], 'word' ), 0, 5 ) );
		$faq   = array();
		if ( 'hub' === $spec['type'] ) {
			$faq[] = array( "How many {$n} letter words are there?", "WordMivo's list has {$total} {$n}-letter English words. {$words['n_common']} of them are " . ( 5 === $n ? 'likely Wordle answers (common dictionary words that are not plurals or past tenses).' : 'common everyday words.' ) );
			if ( $ex ) {
				$faq[] = array( "What are the most common {$n} letter words?", "The most common {$n}-letter words in English include {$ex}." );
			}
			if ( 5 === $n ) {
				$faq[] = array( 'What is a good 5 letter word to start Wordle?', 'Start with a common word that uses five different, frequent letters, such as CRANE, SLATE, TRACE or CRATE. They test the vowels A and E plus the most common consonants R, S, T, L and N.' );
			}
			$faq[] = array( "How do I find {$n} letter words with certain letters?", 'Type the letters you know into their boxes, add letters that must appear anywhere in "Must contain", and letters to skip in "Exclude". The list updates as you type, with common words first.' );
			$faq[] = array( 'Are all these words valid in Wordle and Scrabble?', 'Not always. Our list is broad and includes rare words. Each game uses its own dictionary, so common words shown first are the safest picks.' );
		} elseif ( 'tool' !== $spec['type'] ) {
			$phrase = self::phrase( $spec );
			$faq[]  = array( 'How many ' . $phrase . ' are there?', "There are {$total} {$phrase} in WordMivo's list, of which {$words['n_common']} are " . ( 5 === $n ? 'likely Wordle answers.' : 'common words.' ) );
			if ( $ex ) {
				$faq[] = array( 'What are common ' . $phrase . '?', "Common {$phrase} include {$ex}." );
			}
		}
		return $faq;
	}

	/**
	 * Related pages for internal linking.
	 */
	public static function related( array $spec ): array {
		$groups = array();
		$len    = (int) $spec['len'];
		$x      = $spec['x'];
		$one    = 1 === strlen( $x ) && 'special' !== $spec['type'];

		if ( in_array( $spec['type'], array( 'starts', 'ends', 'contains' ), true ) && $one ) {
			$links = array();
			foreach ( range( 'a', 'z' ) as $l ) {
				$s = self::normalize( array( 'type' => $spec['type'], 'len' => $len, 'x' => $l ) );
				if ( $l !== $x && self::is_served( $s ) && self::count( $s ) > 0 ) {
					$links[ strtoupper( $l ) ] = self::url( $s );
				}
			}
			$groups[ 'Other letters' ] = $links;
		}
		if ( $one ) {
			$links = array();
			foreach ( array( 'starts' => 'Starting with', 'ends' => 'Ending in', 'contains' => 'With' ) as $type => $label ) {
				$s = self::normalize( array( 'type' => $type, 'len' => $len, 'x' => $x ) );
				if ( $type !== $spec['type'] && self::is_served( $s ) && self::count( $s ) > 0 ) {
					$links[ "{$label} " . strtoupper( $x ) ] = self::url( $s );
				}
			}
			for ( $n = 3; $n <= 7; $n++ ) {
				$s = self::normalize( array( 'type' => $spec['type'], 'len' => $n, 'x' => $x, 'pos' => $spec['pos'] ) );
				if ( $n !== $len && self::is_served( $s ) && self::count( $s ) > 0 ) {
					$links[ "{$n} letters" ] = self::url( $s );
				}
			}
			if ( $links ) {
				$groups[ 'Related lists' ] = $links;
			}
		}
		if ( 'hub' === $spec['type'] ) {
			foreach ( array( 'starts' => 'Starting with', 'ends' => 'Ending in' ) as $type => $label ) {
				$links = array();
				foreach ( range( 'a', 'z' ) as $l ) {
					$s = self::normalize( array( 'type' => $type, 'len' => $len, 'x' => $l ) );
					if ( self::is_served( $s ) && self::count( $s ) > 0 ) {
						$links[ strtoupper( $l ) ] = self::url( $s );
					}
				}
				if ( $links ) {
					$groups[ "{$len} letter words {$label}" ] = $links;
				}
			}
		}
		$hubs = array();
		for ( $n = MIN_LEN; $n <= MAX_LEN; $n++ ) {
			$s = self::normalize( array( 'type' => 'hub', 'len' => $n ) );
			if ( self::is_served( $s ) && ! ( 'hub' === $spec['type'] && $n === $len ) ) {
				$hubs[ "{$n} letter words" ] = self::url( $s );
			}
		}
		$groups['Word finders'] = $hubs;
		return array_filter( $groups );
	}

	/**
	 * Values to pre-fill the finder with: [ known => [pos => letter], include => '' ].
	 */
	public static function prefill( array $spec ): array {
		$known   = array();
		$include = '';
		$x       = $spec['x'];
		switch ( $spec['type'] ) {
			case 'starts':
				foreach ( str_split( $x ) as $i => $l ) {
					$known[ $i ] = $l;
				}
				break;
			case 'ends':
				$start = $spec['len'] - strlen( $x );
				foreach ( str_split( $x ) as $i => $l ) {
					$known[ $start + $i ] = $l;
				}
				break;
			case 'position':
				$known[ ( ordinal_positions()[ $spec['pos'] ] ?? 1 ) - 1 ] = $x;
				break;
			case 'contains':
				$include = $x;
				break;
		}
		return array(
			'known'   => $known,
			'include' => $include,
		);
	}
}
