<?php
/**
 * Tool shortcodes. Markup works without JS for labels; JS adds behaviour.
 *
 * [wordmivo_finder length="5"] [wordmivo_wordle] [wordmivo_anagram]
 * [wordmivo_unscramble] [wordmivo_scrabble] [wordmivo_wordle_hints]
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

class Shortcodes {

	private static $needs_assets = false;

	public static function init(): void {
		add_shortcode( 'wordmivo_finder', array( __CLASS__, 'finder' ) );
		add_shortcode( 'wordmivo_wordle', array( __CLASS__, 'wordle' ) );
		add_shortcode( 'wordmivo_anagram', static fn() => self::rack_tool( 'anagram' ) );
		add_shortcode( 'wordmivo_unscramble', static fn() => self::rack_tool( 'unscramble' ) );
		add_shortcode( 'wordmivo_scrabble', static fn() => self::rack_tool( 'rack' ) );
		add_shortcode( 'wordmivo_wordle_hints', array( __CLASS__, 'wordle_hints' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_css' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'print_js' ), 5 );
	}

	/**
	 * Tool CSS is tiny, so it is inlined in the head (no layout shift); JS is deferred.
	 */
	public static function print_css(): void {
		$post = get_post();
		$uses = Pages::current() || ( is_singular() && $post && str_contains( $post->post_content, '[wordmivo_' ) );
		if ( ! $uses ) {
			return;
		}
		$css = file_get_contents( WORDMIVO_DIR . 'assets/css/tools.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		echo '<style id="wm-tools-css">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- static file shipped with the plugin.
	}

	public static function print_js(): void {
		if ( ! self::$needs_assets ) {
			return;
		}
		printf(
			'<script id="wm-config">window.wmConfig=%s;</script><script src="%s" defer></script>',
			wp_json_encode(
				array(
					'rest' => esc_url_raw( rest_url( 'wordmivo/v1/words' ) ),
				)
			),
			esc_url( WORDMIVO_URL . 'assets/js/wordmivo.js?v=' . WORDMIVO_VERSION )
		);
	}

	public static function finder( $atts ): string {
		$atts = shortcode_atts(
			array(
				'length'  => 5,
				'known'   => '',
				'include' => '',
			),
			$atts
		);
		$len  = max( MIN_LEN, min( MAX_LEN, (int) $atts['length'] ) );
		self::$needs_assets = true;

		$prefill = array(
			'known'   => array(),
			'include' => sanitize_key( $atts['include'] ),
		);
		$spec    = Pages::current();
		if ( $spec && (int) $spec['len'] === $len && 'tool' !== $spec['type'] ) {
			$prefill = Pages::prefill( $spec );
		}

		ob_start();
		?>
<form class="wm-tool wm-finder" data-wm="finder" data-len="<?php echo esc_attr( $len ); ?>" data-src="<?php echo esc_attr( json_url( $len ) ); ?>" role="search" aria-label="<?php echo esc_attr( "{$len} letter word finder" ); ?>">
	<fieldset class="wm-known">
		<legend>Known letters (green)</legend>
		<div class="wm-boxes">
			<?php for ( $i = 0; $i < $len; $i++ ) : ?>
				<input type="text" inputmode="text" maxlength="1" autocomplete="off" autocapitalize="off" spellcheck="false" class="wm-box" name="k<?php echo esc_attr( $i ); ?>" aria-label="<?php echo esc_attr( 'Letter ' . ( $i + 1 ) ); ?>" value="<?php echo esc_attr( $prefill['known'][ $i ] ?? '' ); ?>">
			<?php endfor; ?>
		</div>
	</fieldset>
	<fieldset class="wm-known">
		<legend>In the word, but not in this spot (yellow)</legend>
		<div class="wm-boxes">
			<?php for ( $i = 0; $i < $len; $i++ ) : ?>
				<input type="text" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="5" class="wm-box wm-notat" name="y<?php echo esc_attr( $i ); ?>" aria-label="<?php echo esc_attr( 'Letters not in position ' . ( $i + 1 ) ); ?>">
			<?php endfor; ?>
		</div>
	</fieldset>
	<div class="wm-row">
		<label>Must contain (anywhere)
			<input type="text" name="include" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="<?php echo esc_attr( $len ); ?>" value="<?php echo esc_attr( $prefill['include'] ); ?>" placeholder="e.g. ae">
		</label>
		<label>Exclude (gray)
			<input type="text" name="exclude" autocomplete="off" autocapitalize="off" spellcheck="false" maxlength="26" placeholder="e.g. rst">
		</label>
		<label>Sort
			<select name="sort">
				<option value="common">Common first</option>
				<option value="az">A to Z</option>
				<option value="score">Scrabble score</option>
			</select>
		</label>
	</div>
	<?php if ( 5 === $len ) : ?>
		<label class="wm-check"><input type="checkbox" name="likely" value="1"> Only likely Wordle answers</label>
	<?php endif; ?>
	<div class="wm-actions">
		<button type="submit" class="wm-btn">Find words</button>
		<button type="reset" class="wm-btn wm-btn-ghost">Reset</button>
		<button type="button" class="wm-btn wm-btn-ghost" data-action="copy">Copy link</button>
	</div>
	<div class="wm-results" aria-live="polite"></div>
</form>
		<?php
		return (string) ob_get_clean();
	}

	public static function wordle(): string {
		self::$needs_assets = true;
		ob_start();
		?>
<div class="wm-tool wm-wordle" data-wm="wordle" data-src="<?php echo esc_attr( json_url( 5 ) ); ?>">
	<p class="wm-help">Type each guess, then tap a tile to set its colour: gray, yellow, green. Press the button with no guesses to see the best starting words.</p>
	<div class="wm-grid" role="group" aria-label="Your guesses">
		<?php for ( $r = 0; $r < 6; $r++ ) : ?>
			<div class="wm-guess" data-row="<?php echo esc_attr( $r ); ?>">
				<?php for ( $c = 0; $c < 5; $c++ ) : ?>
					<input type="text" maxlength="1" autocomplete="off" autocapitalize="off" spellcheck="false" class="wm-tile" placeholder=" " data-state="gray" aria-label="<?php echo esc_attr( sprintf( 'Guess %d letter %d, gray', $r + 1, $c + 1 ) ); ?>">
				<?php endfor; ?>
			</div>
		<?php endfor; ?>
	</div>
	<div class="wm-actions">
		<button type="button" class="wm-btn" data-action="solve">Show possible answers</button>
		<button type="button" class="wm-btn wm-btn-ghost" data-action="clear">Clear</button>
		<button type="button" class="wm-btn wm-btn-ghost" data-action="copy">Copy link</button>
	</div>
	<label class="wm-check"><input type="checkbox" name="hard" value="1"> Hard mode (only suggest words that could be the answer)</label>
	<div class="wm-results" aria-live="polite"></div>
</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function rack_tool( string $mode ): string {
		self::$needs_assets = true;
		$labels = array(
			'anagram'    => array( 'Letters', 'Find anagrams' ),
			'unscramble' => array( 'Letters to unscramble', 'Unscramble' ),
			'rack'       => array( 'Your rack (use ? for blank, max 2)', 'Find words' ),
		);
		ob_start();
		?>
<form class="wm-tool wm-rack" data-wm="rack" data-mode="<?php echo esc_attr( $mode ); ?>" role="search">
	<label><?php echo esc_html( $labels[ $mode ][0] ); ?>
		<input type="text" name="letters" required minlength="2" maxlength="<?php echo esc_attr( MAX_RACK ); ?>" pattern="[A-Za-z?]{2,15}" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="<?php echo 'rack' === $mode ? 'e.g. retains?' : 'e.g. listen'; ?>">
	</label>
	<div class="wm-actions"><button type="submit" class="wm-btn"><?php echo esc_html( $labels[ $mode ][1] ); ?></button><button type="button" class="wm-btn wm-btn-ghost" data-action="copy">Copy link</button></div>
	<div class="wm-results" aria-live="polite"></div>
</form>
		<?php
		return (string) ob_get_clean();
	}

	public static function wordle_hints(): string {
		$answer = strtolower( (string) get_option( 'wordmivo_wordle_answer', '' ) );
		$date   = (string) get_option( 'wordmivo_wordle_date', '' );
		$today  = current_time( 'Y-m-d' );
		if ( ! preg_match( '/^[a-z]{5}$/', $answer ) || $date !== $today ) {
			return '<p class="wm-note">Today\'s hints are not posted yet. Use the <a href="' . esc_url( home_url( '/wordle-solver/' ) ) . '">Wordle solver</a> with your guesses meanwhile.</p>';
		}
		$vowels  = vowel_count( $answer );
		$repeats = has_repeat( $answer );
		ob_start();
		?>
<div class="wm-hints">
	<p class="wm-date"><?php echo esc_html( wp_date( 'l, F j, Y', strtotime( $today ) ) ); ?></p>
	<ol>
		<li>It has <strong><?php echo esc_html( $vowels ); ?></strong> vowel<?php echo 1 === $vowels ? '' : 's'; ?>.</li>
		<li><?php echo $repeats ? 'At least one letter <strong>repeats</strong>.' : 'No letter repeats.'; ?></li>
		<li><details><summary>Show the first letter</summary><p><strong><?php echo esc_html( strtoupper( $answer[0] ) ); ?></strong></p></details></li>
		<li><details><summary>Show the last letter</summary><p><strong><?php echo esc_html( strtoupper( substr( $answer, -1 ) ) ); ?></strong></p></details></li>
	</ol>
	<details class="wm-reveal"><summary>Reveal today's answer</summary><p class="wm-answer"><?php echo esc_html( strtoupper( $answer ) ); ?></p></details>
</div>
		<?php
		return (string) ob_get_clean();
	}
}
