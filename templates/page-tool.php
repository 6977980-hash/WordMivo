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
	<section class="wm-section wm-prose">
		<h2>How it works</h2>
		<ol>
			<?php foreach ( $c['how'] as $step ) : ?>
				<li><?php echo esc_html( $step ); ?></li>
			<?php endforeach; ?>
		</ol>
		<p><?php echo esc_html( $c['more'] ); ?></p>
	</section>
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
