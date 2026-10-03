<?php
/**
 * Tool pages: Wordle solver, anagram, unscrambler, Scrabble finder, daily hints.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

$spec = Pages::current();
$tool = $spec['x'];

$content = array(
	'wordle-solver'        => array(
		'tool'  => '[wordmivo_wordle]',
		'intro' => 'Enter each Wordle guess and set the colours you got. The solver lists every word that still fits, with the most common words first, and suggests the guess that rules out the most options.',
		'how'   => array(
			'Type your first guess into the top row.',
			'Tap each tile until it matches the colour Wordle showed: gray, yellow or green.',
			'Press "Show possible answers". Add your next guess and repeat.',
		),
		'more'  => 'Repeated letters are handled the way Wordle does it: if you guess a letter twice and only one is coloured, the word has that letter exactly once.',
	),
	'anagram-solver'       => array(
		'tool'  => '[wordmivo_anagram]',
		'intro' => 'Type a word or a set of letters to find every word that uses exactly the same letters, no more and no less.',
		'how'   => array( 'Type 2 to 15 letters.', 'Press "Find anagrams".', 'Common words are listed first.' ),
		'more'  => 'Need words that use only some of the letters? Use the word unscrambler.',
	),
	'word-unscrambler'     => array(
		'tool'  => '[wordmivo_unscramble]',
		'intro' => 'Unscramble any letters into every word you can make from them, from three letters up, grouped by length.',
		'how'   => array( 'Type up to 15 letters.', 'Press "Unscramble".', 'Longest and highest-scoring words come first.' ),
		'more'  => 'Each letter is used at most as many times as you typed it.',
	),
	'scrabble-word-finder' => array(
		'tool'  => '[wordmivo_scrabble]',
		'intro' => 'Enter your Scrabble rack to see every playable word with its score. Use ? for blank tiles (up to two).',
		'how'   => array( 'Type your tiles, using ? for a blank.', 'Press "Find words".', 'Blank tiles score zero, as in the game.' ),
		'more'  => 'Scores are base tile values, before board bonuses.',
	),
	'wordle-analyzer'      => array(
		'tool'  => '[wordmivo_analyzer]',
		'intro' => 'Find out how well you really played. Enter your Wordle guesses and the answer, and get a skill and luck score for every guess, plus the guess we would have played.',
		'how'   => array(
			'Type your guesses in the order you played them.',
			"Type the day's answer (or leave it empty if your last guess was right).",
			'Press "Analyze my game" to see how many answers each guess ruled out.',
		),
		'more'  => 'Skill is 99 when your guess was as good as the best one we found. Luck is high when the colours you got left fewer answers than most other answers would have. Wordle is a trademark of The New York Times Company; this analyzer is an independent tool.',
	),
	'spelling-bee-solver'  => array(
		'tool'  => '[wordmivo_bee]',
		'intro' => 'Stuck on the Spelling Bee? Enter the centre letter and the six outer letters to see every word, pangrams first, with points.',
		'how'   => array( 'Type the centre (yellow) letter.', 'Type the six outer letters.', 'Press "Find words". Pangrams use all seven letters and score 7 bonus points.' ),
		'more'  => 'Words are at least four letters long and always use the centre letter; letters can repeat. Spelling Bee is a trademark of The New York Times Company; this solver is an independent tool.',
	),
	'letter-boxed-solver'  => array(
		'tool'  => '[wordmivo_boxed]',
		'intro' => 'Enter the three letters on each side of the Letter Boxed square to find every playable word and the shortest solutions that use all 12 letters.',
		'how'   => array( 'Type the three letters on each side.', 'Press "Solve".', 'Solutions chain words: each word starts with the last letter of the one before.' ),
		'more'  => 'Consecutive letters in a word never come from the same side. Letter Boxed is a trademark of The New York Times Company; this solver is an independent tool.',
	),
	'quordle-solver'       => array(
		'tool'  => '[wordmivo_multi]',
		'intro' => 'Solve Quordle and Octordle faster: add each guess once, set its colours on every board, and see the possible answers for each board and the best next guess for all of them.',
		'how'   => array( 'Choose Quordle (4 boards) or Octordle (8 boards).', 'Add your guess, then tap tiles on each board to match the colours.', 'Follow "Best next guess" or pick from each board\'s list.' ),
		'more'  => 'When a board has one answer left, we suggest finishing it first. Quordle is owned by Merriam-Webster; this solver is an independent tool.',
	),
	'best-wordle-starting-words' => array(
		'tool'  => '[wordmivo_openers]',
		'intro' => 'Which word should you start Wordle with? Instead of opinions, we ran the numbers: every five-letter word was tested as a first guess against every likely answer.',
		'how'   => array(
			'"Answers left (avg)" is how many likely answers remain, on average, after the colours you get from that first guess. Lower is better.',
			'"Worst case" is the largest number of answers that could remain.',
			'"Green chance" is how often the word gets at least one green tile.',
		),
		'more'  => 'Results use our own list of likely answers (common five-letter words that are not plurals or past tenses), not the official answer list, and the same colour rules as the Wordle solver. Vowel-heavy words like ADIEU and AUDIO find vowels but leave far more answers than words with common consonants like R, S, T and L. Wordle is a trademark of The New York Times Company.',
	),
	'todays-wordle-hints'  => array(
		'tool'  => '[wordmivo_wordle_hints]',
		'intro' => "Stuck on today's Wordle? Open the hints one at a time. The answer stays hidden until you choose to reveal it.",
		'how'   => array( 'Read the vowel and repeat hints first.', 'Open the first or last letter only if you need it.', 'Reveal the answer as a last resort.' ),
		'more'  => 'Wordle is a trademark of The New York Times Company. WordMivo is not affiliated with it.',
	),
);
$c = $content[ $tool ];

get_header();
?>
<main id="main" class="wm-page">
	<nav class="wm-crumbs" aria-label="Breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a></nav>
	<h1><?php echo esc_html( Pages::heading( $spec ) ); ?></h1>
	<p class="wm-lead"><?php echo esc_html( $c['intro'] ); ?></p>
	<?php echo do_shortcode( $c['tool'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- shortcode output is escaped. ?>
	<?php echo Ads::slot( 'top' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in Ads::slot(). ?>
	<section class="wm-section wm-prose">
		<h2>How it works</h2>
		<ol>
			<?php foreach ( $c['how'] as $step ) : ?>
				<li><?php echo esc_html( $step ); ?></li>
			<?php endforeach; ?>
		</ol>
		<p><?php echo esc_html( $c['more'] ); ?></p>
	</section>
	<?php echo Ads::slot( 'bottom' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in Ads::slot(). ?>
	<?php foreach ( Pages::related( $spec ) as $label => $links ) : ?>
	<nav class="wm-section wm-related" aria-label="<?php echo esc_attr( $label ); ?>">
		<h2><?php echo esc_html( $label ); ?></h2>
		<ul>
			<?php foreach ( $links as $text => $url ) : ?>
				<li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $text ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php endforeach; ?>
</main>
<?php
get_footer();
