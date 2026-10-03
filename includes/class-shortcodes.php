<?php
/**
 * Tool shortcodes. Markup works without JS for labels; JS adds behaviour.
 *
 * [wordmivo_finder length="5"] [wordmivo_wordle] [wordmivo_anagram]
 * [wordmivo_unscramble] [wordmivo_scrabble]
 * [wordmivo_analyzer] [wordmivo_bee] [wordmivo_boxed] [wordmivo_multi]
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
		add_shortcode( 'wordmivo_analyzer', array( __CLASS__, 'analyzer' ) );
		add_shortcode( 'wordmivo_bee', array( __CLASS__, 'bee' ) );
		add_shortcode( 'wordmivo_boxed', array( __CLASS__, 'boxed' ) );
		add_shortcode( 'wordmivo_multi', array( __CLASS__, 'multi' ) );
		add_shortcode( 'wordmivo_openers', array( __CLASS__, 'openers' ) );
		add_shortcode( 'wordmivo_clue', array( __CLASS__, 'clue' ) );
		add_shortcode( 'wordmivo_wotd', array( __CLASS__, 'wotd' ) );
		add_shortcode( 'wordmivo_dataset', array( __CLASS__, 'dataset' ) );
		add_shortcode( 'wordmivo_embed_code', array( 'WordMivo\\Embed', 'shortcode' ) );
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

	private static function text_input( string $name, string $label, int $max, string $placeholder = '', string $extra = '' ): string {
		return sprintf(
			'<label>%1$s<input type="text" name="%2$s" maxlength="%3$d" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="%4$s"%5$s></label>',
			esc_html( $label ),
			esc_attr( $name ),
			$max,
			esc_attr( $placeholder ),
			$extra // Static attributes from this class.
		);
	}

	/** Wordle game analyzer: skill and luck per guess. */
	public static function analyzer(): string {
		self::$needs_assets = true;
		return '<form class="wm-tool wm-analyzer" data-wm="analyzer" data-src="' . esc_attr( json_url( 5 ) ) . '">'
			. '<label>Your guesses, in order<textarea name="guesses" rows="3" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="crane slate pious"></textarea></label>'
			. '<div class="wm-row">' . self::text_input( 'answer', "The day's answer", 5, 'e.g. pious' ) . '</div>'
			. '<p class="wm-note">Tip: leave the answer empty if you solved it; we use your last guess.</p>'
			. '<div class="wm-actions"><button type="submit" class="wm-btn">Analyze my game</button><button type="button" class="wm-btn wm-btn-ghost" data-action="copy">Copy link</button></div>'
			. '<div class="wm-results" aria-live="polite"></div></form>';
	}

	const DATASET = array(
		'five-letter-words.csv'            => array( 'All five-letter words (CSV)', 'Word, Scrabble score, frequency rank, likely Wordle answer.', 'text/csv' ),
		'likely-wordle-answers.txt'        => array( 'Likely Wordle answers (TXT)', 'Our estimate of likely answers, one word per line.', 'text/plain' ),
		'wordle-starting-words-ranked.csv' => array( 'Wordle starting words ranked (CSV)', 'Every five-letter word scored as an opening guess.', 'text/csv' ),
		'README.md'                        => array( 'README and licence', 'Column descriptions, method and credit line.', 'text/markdown' ),
	);

	/** The same dataset published on Kaggle. */
	const KAGGLE = 'https://www.kaggle.com/datasets/wordpresswordmivo/wordle-word-list-and-five-letter-words-dataset';

	/** Free dataset downloads, with schema.org Dataset markup for Google Dataset Search. */
	public static function dataset(): string {
		$base = WORDMIVO_URL . 'assets/dataset/';
		$html = '<ul class="wm-downloads">';
		$dist = array();
		foreach ( self::DATASET as $file => $info ) {
			$path  = WORDMIVO_DIR . 'assets/dataset/' . $file;
			$size  = is_readable( $path ) ? size_format( filesize( $path ), 0 ) : '';
			$html .= sprintf( '<li><a href="%s" download>%s</a> <span class="wm-note">%s %s</span></li>', esc_url( $base . $file ), esc_html( $info[0] ), esc_html( $info[1] ), esc_html( $size ? "({$size})" : '' ) );
			$dist[] = array( '@type' => 'DataDownload', 'encodingFormat' => $info[2], 'contentUrl' => $base . $file, 'name' => $info[0] );
		}
		$html  .= '</ul>';
		$html  .= '<p>Also on <a href="' . esc_url( self::KAGGLE ) . '" rel="noopener">Kaggle</a>, where you can preview the files and open them in a notebook.</p>';
		$html  .= '<h2>How to credit</h2><p>The data is free under <a href="https://creativecommons.org/licenses/by/4.0/" rel="nofollow">CC BY 4.0</a>. Use it in apps, research, articles or games; just add this credit with a link:</p>'
			. '<textarea class="wm-code" rows="2" readonly aria-label="Credit line to copy" onclick="this.select()">Data: &lt;a href="' . esc_url( home_url( '/' ) ) . '"&gt;WordMivo&lt;/a&gt; (CC BY 4.0)</textarea>';
		$schema = array(
			'@context'            => 'https://schema.org',
			'@type'               => 'Dataset',
			'name'                => 'WordMivo Wordle and Five-Letter Word Dataset',
			'description'         => 'Five-letter English words with Scrabble scores and frequency ranks, an estimated list of 2,141 likely Wordle answers, and every five-letter word ranked as a Wordle starting word.',
			'url'                 => Pages::url( array( 'type' => 'tool', 'x' => 'wordle-word-list-download' ) ),
			'license'             => 'https://creativecommons.org/licenses/by/4.0/',
			'isAccessibleForFree' => true,
			'creator'             => Seo::organization(),
			'keywords'            => array( 'Wordle', 'five-letter words', 'word list', 'word frequency', 'Scrabble' ),
			'distribution'        => $dist,
			'sameAs'              => array( self::KAGGLE ),
		);
		return $html . '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ) . '</script>';
	}

	/** Word of the day card plus the archive of earlier days. */
	public static function wotd(): string {
		$days = Words::word_of_the_day();
		$word = reset( $days );
		$w    = $word ? Words::get( $word ) : null;
		if ( ! $w ) {
			return '<p class="wm-note">The word of the day appears once the word database is ready.</p>';
		}
		$html = '<article class="wm-tool wm-wotd"><p class="wm-date">' . esc_html( wp_date( 'l, F j, Y', strtotime( (string) key( $days ) ) ) ) . '</p>'
			. '<p class="wm-wotd-word"><a href="' . esc_url( Words::url( $word ) ) . '">' . esc_html( $word ) . '</a></p>';
		foreach ( $w->meanings as $pos => $senses ) {
			$html .= '<p><em>' . esc_html( $pos ) . '</em>: ' . esc_html( $senses[0][0] ) . '</p>';
			if ( $senses[0][1] ) {
				$html .= '<p class="wm-note">Example: <q>' . esc_html( $senses[0][1] ) . '</q></p>';
			}
			break;
		}
		$html .= '<p>' . esc_html( sprintf( '%d letters, %d points in Scrabble.', $w->len, $w->score ) ) . ' <a href="' . esc_url( Words::url( $word ) ) . '">' . esc_html( 'More about ' . $word ) . '</a></p></article>';
		$past  = array_slice( $days, 1, 60, true );
		if ( $past ) {
			$html .= '<h2>Previous words of the day</h2><ul class="wm-wotd-list">';
			foreach ( $past as $date => $x ) {
				$html .= '<li><span>' . esc_html( wp_date( 'M j, Y', strtotime( $date ) ) ) . '</span> <a href="' . esc_url( Words::url( $x ) ) . '">' . esc_html( $x ) . '</a></li>';
			}
			$html .= '</ul>';
		}
		return $html;
	}

	/** Crossword clue solver / reverse dictionary. */
	public static function clue(): string {
		self::$needs_assets = true;
		return '<form class="wm-tool wm-clue" data-wm="clue" data-words="' . esc_attr( home_url( '/word/' ) ) . '" role="search">'
			. '<label>Clue or meaning<input type="text" name="clue" maxlength="120" autocomplete="off" placeholder="e.g. large wading bird"></label>'
			. '<div class="wm-row">' . self::text_input( 'pattern', 'Answer length or pattern', 15, 'e.g. 5 or c???e', ' required' ) . '</div>'
			. '<p class="wm-note">Use ? for unknown letters. Type just a number if you only know the length.</p>'
			. '<div class="wm-actions"><button type="submit" class="wm-btn">Find answers</button><button type="button" class="wm-btn wm-btn-ghost" data-action="copy">Copy link</button></div>'
			. '<div class="wm-results" aria-live="polite"></div></form>';
	}

	/** Spelling Bee solver. */
	public static function bee(): string {
		self::$needs_assets = true;
		return '<form class="wm-tool wm-puzzle" data-wm="puzzle" data-mode="bee" role="search">'
			. '<div class="wm-row">' . self::text_input( 'center', 'Centre letter', 1, 'e.g. a', ' required pattern="[A-Za-z]"' ) . self::text_input( 'outer', 'The other 6 letters', 6, 'e.g. lpnetc', ' required pattern="[A-Za-z]{6}"' ) . '</div>'
			. '<div class="wm-actions"><button type="submit" class="wm-btn">Find words</button><button type="button" class="wm-btn wm-btn-ghost" data-action="copy">Copy link</button></div>'
			. '<div class="wm-results" aria-live="polite"></div></form>';
	}

	/** Letter Boxed solver. */
	public static function boxed(): string {
		self::$needs_assets = true;
		$sides = '';
		foreach ( array( 'Top', 'Right', 'Bottom', 'Left' ) as $i => $name ) {
			$sides .= self::text_input( 's' . $i, $name . ' side', 3, array( 'abc', 'def', 'ghi', 'jkl' )[ $i ], ' required pattern="[A-Za-z]{3}"' );
		}
		return '<form class="wm-tool wm-puzzle" data-wm="puzzle" data-mode="boxed" role="search">'
			. '<div class="wm-row wm-sides">' . $sides . '</div>'
			. '<div class="wm-actions"><button type="submit" class="wm-btn">Solve</button><button type="button" class="wm-btn wm-btn-ghost" data-action="copy">Copy link</button></div>'
			. '<div class="wm-results" aria-live="polite"></div></form>';
	}

	/** Quordle / Octordle: shared guesses, colours per board. */
	public static function multi(): string {
		self::$needs_assets = true;
		return '<div class="wm-tool wm-multi" data-wm="multi" data-src="' . esc_attr( json_url( 5 ) ) . '">'
			. '<div class="wm-row"><label>Game<select name="boards"><option value="4">Quordle (4 boards)</option><option value="8">Octordle (8 boards)</option></select></label>'
			. self::text_input( 'guess', 'Add a guess', 5, 'e.g. crane', ' pattern="[A-Za-z]{5}"' ) . '</div>'
			. '<p class="wm-help">Add each guess, then tap its tiles on every board to match the colours you got. Boards you solved turn green.</p>'
			. '<div class="wm-actions"><button type="button" class="wm-btn" data-action="add">Add guess</button><button type="button" class="wm-btn wm-btn-ghost" data-action="undo">Remove last</button><button type="button" class="wm-btn wm-btn-ghost" data-action="copy">Copy link</button></div>'
			. '<div class="wm-best-multi" aria-live="polite"></div>'
			. '<div class="wm-boards"></div></div>';
	}

	/**
	 * Best starting words, from data/starting-words.json (computed offline from our
	 * likely-answer list with the same feedback rules as the solver).
	 */
	public static function openers(): string {
		$file = WORDMIVO_DIR . 'data/starting-words.json';
		$data = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $data ) {
			return '';
		}
		$table = static function ( array $rows, bool $show_rank ) {
			$html = '<div class="wm-table-wrap"><table class="wm-facts wm-openers"><thead><tr>' . ( $show_rank ? '<th scope="col">Rank</th>' : '<th scope="col">#</th>' ) . '<th scope="col">Word</th><th scope="col">Answers left (avg)</th><th scope="col">Worst case</th><th scope="col">Green chance</th></tr></thead><tbody>';
			foreach ( $rows as $i => $r ) {
				$word  = esc_html( strtoupper( $r['w'] ) );
				$link  = Words::linkable( array( $r['w'] ) ) ? '<a href="' . esc_url( Words::url( $r['w'] ) ) . '">' . $word . '</a>' : $word;
				$html .= sprintf( '<tr><td>%d</td><td><strong>%s</strong></td><td>%s</td><td>%d</td><td>%d%%</td></tr>', $show_rank ? (int) $r['rank'] : $i + 1, $link, esc_html( number_format_i18n( $r['e'], 1 ) ), (int) $r['worst'], (int) $r['green'] );
			}
			return $html . '</tbody></table></div>';
		};
		$top = $data['top'][0];
		$out = '<p class="wm-lead"><strong>' . esc_html( strtoupper( $top['w'] ) ) . ' is the best Wordle starting word in our analysis.</strong> '
			. sprintf( 'After it, on average only %s of %s likely answers are left, and at worst %d.', esc_html( number_format_i18n( $top['e'], 1 ) ), esc_html( number_format_i18n( $data['answers'] ) ), (int) $top['worst'] ) . '</p>'
			. '<h2>Top 25 starting words</h2>'
			. '<p>We tested all ' . esc_html( number_format_i18n( $data['guesses'] ) ) . ' five-letter dictionary words as openers against every likely answer. Lower "answers left" is better.</p>'
			. $table( array_slice( $data['top'], 0, 25 ), false )
			. '<h2>Best starting words that can also be the answer</h2>'
			. '<p>These are common words, so they also give you a small chance of winning in one.</p>'
			. $table( array_slice( $data['top_answers'], 0, 15 ), false )
			. '<h2>How popular openers compare</h2>'
			. '<p>Where the starting words people talk about most land among all ' . esc_html( number_format_i18n( $data['guesses'] ) ) . ' words.</p>'
			. $table( $data['popular'], true );
		return $out;
	}
}
