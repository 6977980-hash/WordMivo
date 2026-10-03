<?php
/**
 * Pure helpers for word metrics. No WordPress calls except table names.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

const VOWELS     = 'aeiou';
const MIN_LEN    = 3;
const MAX_LEN    = 8;
const MAX_RACK   = 15;
const MAX_BLANKS = 2;

// Site owner, shown on About/Methodology and in schema (E-E-A-T).
const OWNER_NAME     = 'Ali Ahmad';
const OWNER_LINKEDIN = 'https://www.linkedin.com/in/ali-ahmad-chaudhry-12777486/';

// Google Search Console verification for wordmivo.com (public by design; overridable in Tools > WordMivo).
const GSC_DEFAULT = '9cmloaft-ebcSjQTtJyk9sf1CjuJXuAEsMCD1W_u4p4';

// Bing Webmaster Tools verification (public by design; overridable in Tools > WordMivo).
const BING_DEFAULT = '3CA5D5A38222486BA11B6778E763417C';

/**
 * Standard English Scrabble tile values.
 */
function tile_values(): array {
	static $values = null;
	if ( null === $values ) {
		$values = array();
		$groups = array(
			1  => 'aeilnorstu',
			2  => 'dg',
			3  => 'bcmp',
			4  => 'fhvwy',
			5  => 'k',
			8  => 'jx',
			10 => 'qz',
		);
		foreach ( $groups as $points => $letters ) {
			foreach ( str_split( $letters ) as $letter ) {
				$values[ $letter ] = $points;
			}
		}
	}
	return $values;
}

function words_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'wm_words';
}

function defs_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'wm_defs';
}

function sources_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'wm_sources';
}

/** Letters sorted a..z, used for exact anagram lookups. */
function signature( string $word ): string {
	$letters = str_split( $word );
	sort( $letters );
	return implode( '', $letters );
}

/** Bit i set when the word contains letter i (a = bit 0). */
function letter_mask( string $word ): int {
	$mask = 0;
	foreach ( str_split( $word ) as $letter ) {
		$mask |= 1 << ( ord( $letter ) - 97 );
	}
	return $mask;
}

function letter_bit( string $letter ): int {
	return 1 << ( ord( $letter ) - 97 );
}

function vowel_mask(): int {
	return letter_mask( VOWELS );
}

function scrabble_score( string $word ): int {
	$values = tile_values();
	$score  = 0;
	foreach ( str_split( $word ) as $letter ) {
		$score += $values[ $letter ] ?? 0;
	}
	return $score;
}

function vowel_count( string $word ): int {
	return strlen( $word ) - strlen( str_replace( str_split( VOWELS ), '', $word ) );
}

/** True when any letter appears more than once. */
function has_repeat( string $word ): bool {
	return count( array_unique( str_split( $word ) ) ) < strlen( $word );
}

function is_valid_word( string $word ): bool {
	return (bool) preg_match( '/^[a-z]{2,15}$/', $word );
}

/**
 * Can $word be built from $rack (letters plus '?' blanks)?
 * Returns the score using only rack tiles (blanks score 0), or null.
 */
function rack_score( string $word, string $rack ): ?int {
	$counts = count_chars( str_replace( '?', '', $rack ), 1 );
	$blanks = substr_count( $rack, '?' );
	$values = tile_values();
	$score  = 0;
	foreach ( str_split( $word ) as $letter ) {
		$code = ord( $letter );
		if ( ! empty( $counts[ $code ] ) ) {
			--$counts[ $code ];
			$score += $values[ $letter ];
		} elseif ( $blanks > 0 ) {
			--$blanks;
		} else {
			return null;
		}
	}
	return $score;
}

function ordinal_positions(): array {
	return array(
		'first'  => 1,
		'second' => 2,
		'third'  => 3,
		'fourth' => 4,
		'fifth'  => 5,
	);
}

/** URL of the generated client-side word list for a length, or ''. */
function json_url( int $length ): string {
	$files = get_option( 'wordmivo_json_files', array() );
	return isset( $files[ $length ] ) ? (string) $files[ $length ] : '';
}

/** Shorten text to at most $max characters at a word boundary, adding "..." when cut. */
function short_text( string $text, int $max ): string {
	if ( mb_strlen( $text ) <= $max ) {
		return $text;
	}
	return rtrim( mb_substr( $text, 0, (int) mb_strrpos( mb_substr( $text, 0, $max - 2 ), ' ' ) ), ' ,;:' ) . '...';
}

/**
 * Meta description from whole sentences: adds sentences while they fit in $max
 * characters, so Google never shows one cut mid-word. The first one is shortened
 * at a word boundary only if it alone is too long.
 */
function fit_description( array $sentences, int $max = 158 ): string {
	$out = '';
	foreach ( array_filter( array_map( 'trim', $sentences ) ) as $s ) {
		$next = '' === $out ? $s : $out . ' ' . $s;
		if ( mb_strlen( $next ) > $max ) {
			if ( '' === $out ) {
				$out = short_text( $s, $max );
			}
			continue;
		}
		$out = $next;
	}
	return $out;
}
