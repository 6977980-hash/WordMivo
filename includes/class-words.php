<?php
/**
 * Single-word pages: /word/crane/ with meaning (WordNet), Scrabble score,
 * anagrams and the words hidden inside it.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

class Words {

	const POS = array(
		'n' => 'noun',
		'v' => 'verb',
		'a' => 'adjective',
		'r' => 'adverb',
	);

	/** Words in the sitemap: defined dictionary words up to this frequency rank. */
	const SITEMAP_RANK = 60000;

	private static $cache = array();

	public static function init(): void {
		add_action( 'init', static fn() => add_rewrite_rule( '^word/([a-z]{2,15})/?$', 'index.php?wm_type=word&wm_x=$matches[1]', 'top' ) );
	}

	public static function url( string $word ): string {
		return home_url( '/word/' . $word . '/' );
	}

	/**
	 * Everything the page needs about one word, or null when it is not in the database.
	 */
	public static function get( string $word ): ?object {
		global $wpdb;
		if ( array_key_exists( $word, self::$cache ) ) {
			return self::$cache[ $word ];
		}
		$table = words_table();
		$defs  = defs_table();
		$row   = is_valid_word( $word ) ? $wpdb->get_row( $wpdb->prepare( "SELECT w.word, w.len, w.scrabble_score AS score, w.freq_rank, w.is_valid, w.is_likely, w.vowels, w.signature, d.base, d.defs FROM {$table} w LEFT JOIN {$defs} d ON d.word = w.word WHERE w.word = %s", $word ) ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $row ) {
			$row->len       = (int) $row->len;
			$row->score     = (int) $row->score;
			$row->is_valid  = (int) $row->is_valid;
			$row->is_likely = (int) $row->is_likely;
			$row->base      = (string) $row->base;
			$row->meanings  = self::parse( (string) $row->defs );
			if ( $row->base && ! $row->meanings ) {
				$row->meanings = self::parse( (string) $wpdb->get_var( $wpdb->prepare( "SELECT defs FROM {$defs} WHERE word = %s", $row->base ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		self::$cache[ $word ] = $row;
		return $row;
	}

	/** "n:def~example|def\tv:def" -> [ 'noun' => [ [def, example], ... ], ... ] */
	public static function parse( string $defs ): array {
		$out = array();
		foreach ( array_filter( explode( "\t", $defs ) ) as $group ) {
			$pos = self::POS[ $group[0] ] ?? '';
			if ( ! $pos || ':' !== ( $group[1] ?? '' ) ) {
				continue;
			}
			foreach ( explode( '|', substr( $group, 2 ) ) as $sense ) {
				$parts         = explode( '~', $sense, 2 );
				$out[ $pos ][] = array( trim( $parts[0] ), trim( $parts[1] ?? '' ) );
			}
		}
		return $out;
	}

	public static function first_definition( object $w ): string {
		foreach ( $w->meanings as $senses ) {
			return $senses[0][0];
		}
		return '';
	}

	/** Pages worth indexing: dictionary words with their own meaning. */
	public static function is_indexable( object $w ): bool {
		return $w->is_valid && ! $w->base && $w->meanings;
	}

	/** Other dictionary words with exactly the same letters. */
	public static function anagrams( object $w ): array {
		global $wpdb;
		$table = words_table();
		return $wpdb->get_col( $wpdb->prepare( "SELECT word FROM {$table} WHERE signature = %s AND word <> %s AND is_valid = 1 ORDER BY freq_rank IS NULL, freq_rank, word LIMIT 30", $w->signature, $w->word ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Shorter dictionary words that can be made from the word's letters. */
	public static function words_inside( object $w, int $limit = 40 ): array {
		global $wpdb;
		$table   = words_table();
		$outside = ~letter_mask( $w->word ) & 0x3FFFFFF;
		$rows    = $wpdb->get_col( $wpdb->prepare( "SELECT word FROM {$table} WHERE len BETWEEN 3 AND %d AND is_valid = 1 AND (letter_mask & %d) = 0 ORDER BY freq_rank IS NULL, freq_rank LIMIT 3000", $w->len - 1, $outside ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out     = array();
		foreach ( $rows as $word ) {
			if ( null !== rack_score( $word, $w->word ) ) {
				$out[] = $word;
				if ( count( $out ) >= $limit ) {
					break;
				}
			}
		}
		return $out;
	}

	/** Neighbouring defined words in A-Z order, for crawl paths between word pages. */
	public static function neighbours( object $w ): array {
		global $wpdb;
		$defs = defs_table();
		return array(
			'prev' => array_reverse( $wpdb->get_col( $wpdb->prepare( "SELECT word FROM {$defs} WHERE base = '' AND word < %s ORDER BY word DESC LIMIT 3", $w->word ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'next' => $wpdb->get_col( $wpdb->prepare( "SELECT word FROM {$defs} WHERE base = '' AND word > %s ORDER BY word LIMIT 3", $w->word ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Which of these words have their own indexable page (for linking from lists).
	 */
	public static function linkable( array $words ): array {
		global $wpdb;
		if ( ! $words || ! in_array( 'words', Pages::enabled_sets(), true ) ) {
			return array();
		}
		$in = implode( ',', array_map( static fn( $w ) => $wpdb->prepare( '%s', $w ), $words ) );
		return array_flip( $wpdb->get_col( 'SELECT word FROM ' . defs_table() . " WHERE base = '' AND word IN ({$in})" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function title( object $w ): string {
		$word = ucfirst( $w->word );
		return $w->meanings
			? "{$word}: Meaning, Scrabble Score & Anagrams | WordMivo"
			: "Is {$word} a Word? Scrabble Score & Anagrams | WordMivo";
	}

	public static function description( object $w ): string {
		$def  = self::first_definition( $w );
		$text = $w->is_valid
			? sprintf( '%s is a valid Scrabble word worth %d points.', ucfirst( $w->word ), $w->score )
			: sprintf( '%s is not in the Scrabble dictionary we use.', ucfirst( $w->word ) );
		if ( $def ) {
			$text = sprintf( '%s means "%s". ', ucfirst( $w->word ), wp_html_excerpt( $def, 90, '...' ) ) . $text;
		}
		return $text . ' See anagrams and words you can make from its letters.';
	}

	/** Question/answer pairs: visible FAQ + FAQPage schema. */
	public static function faq( object $w ): array {
		$word = $w->word;
		$cap  = ucfirst( $word );
		$faq  = array();
		$faq[] = array(
			"Is {$word} a valid Scrabble word?",
			$w->is_valid
				? "Yes. {$cap} is in the ENABLE word list used by many word games, so it is generally playable in Scrabble and Words With Friends."
				: "No. {$cap} is not in the ENABLE word list, so most word games will not accept it.",
		);
		$faq[] = array( "How many points is {$word} worth in Scrabble?", sprintf( '%s is worth %d points in Scrabble, before any premium squares.', $cap, $w->score ) );
		$def   = self::first_definition( $w );
		if ( $def ) {
			$faq[] = array( "What does {$word} mean?", ( $w->base ? "{$cap} is a form of {$w->base}, which means: " : "{$cap} means: " ) . $def . '.' );
		}
		if ( 5 === $w->len ) {
			$faq[] = array(
				"Could {$word} be a Wordle answer?",
				$w->is_likely
					? "{$cap} is on our list of likely Wordle answers: a common five-letter dictionary word that is not a plural or past tense."
					: "{$cap} is not on our list of likely Wordle answers, but it may still be accepted as a guess.",
			);
		}
		return $faq;
	}
}
