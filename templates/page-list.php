<?php
/**
 * Word-list and finder-hub page. Words are rendered in HTML for crawlers.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

$spec    = Pages::current();
$len     = (int) $spec['len'];
$is_hub  = 'hub' === $spec['type'];
$words   = Pages::words( $spec );
$total   = (int) $words['total'];
$h1      = ! empty( $spec['home'] ) ? '5 Letter Words Finder' : Pages::heading( $spec );
$nword   = Pages::number_word( $len );
$examples = array_slice( wp_list_pluck( $words['common'], 'word' ), 0, 3 );

get_header();
?>
<main id="main" class="wm-page">
	<?php if ( empty( $spec['home'] ) ) : ?>
	<nav class="wm-crumbs" aria-label="Breadcrumb">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
		<?php if ( ! $is_hub ) : ?>
			<span aria-hidden="true">/</span> <a href="<?php echo esc_url( Pages::url( array( 'type' => 'hub', 'len' => $len ) ) ); ?>"><?php echo esc_html( "{$len} Letter Words" ); ?></a>
		<?php endif; ?>
	</nav>
	<?php endif; ?>

	<h1><?php echo esc_html( $h1 ); ?></h1>

	<p class="wm-lead">
		<?php
		if ( $is_hub ) {
			printf(
				'Find any %1$s-letter word in seconds. Our list has %2$s %1$s-letter words; enter the letters you know, the letters it must contain and the ones to exclude, and common words show first.',
				esc_html( $nword ),
				esc_html( number_format_i18n( $total ) )
			);
		} else {
			$top2 = strtoupper( Pages::top_letter_at( $spec, 'starts' === $spec['type'] ? strlen( $spec['x'] ) + 1 : 1 ) );
			printf(
				'There are <strong>%1$s</strong> %2$s in our word list. %3$s of them are common English words%4$s. %5$s',
				esc_html( number_format_i18n( $total ) ),
				esc_html( Pages::phrase( $spec ) ),
				esc_html( number_format_i18n( $words['n_common'] ) ),
				$examples ? esc_html( ', such as ' . implode( ', ', $examples ) ) : '',
				$top2 ? esc_html( sprintf( 'The most frequent %s letter is %s.', 'starts' === $spec['type'] ? 'next' : 'first', $top2 ) ) : ''
			);
		}
		?>
	</p>

	<?php echo Shortcodes::finder( array( 'length' => $len ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the shortcode. ?>

	<?php if ( $words['common'] ) : ?>
	<section class="wm-section" aria-labelledby="wm-common">
		<h2 id="wm-common"><?php echo esc_html( $is_hub ? "Most common {$len} letter words" : 'Most common words' ); ?></h2>
		<ul class="wm-words wm-words-common">
			<?php foreach ( $words['common'] as $w ) : ?>
				<li><?php echo esc_html( $w->word ); ?><sub><?php echo esc_html( $w->score ); ?></sub></li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php endif; ?>

	<?php if ( $words['all'] ) : ?>
	<section class="wm-section" aria-labelledby="wm-all">
		<h2 id="wm-all"><?php echo esc_html( sprintf( 'All %s %s (A to Z)', number_format_i18n( $total ), Pages::phrase( $spec ) ) ); ?></h2>
		<p class="wm-note">Numbers show the Scrabble score.</p>
		<ul class="wm-words">
			<?php foreach ( $words['all'] as $w ) : ?>
				<li><?php echo esc_html( $w->word ); ?><sub><?php echo esc_html( $w->score ); ?></sub></li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $total > count( $words['all'] ) ) : ?>
			<p class="wm-note"><?php echo esc_html( sprintf( 'Showing the first %s words. Use the finder above to narrow the list.', number_format_i18n( count( $words['all'] ) ) ) ); ?></p>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<?php if ( 0 === $total ) : ?>
		<p class="wm-note">The word database is being prepared. Please check back soon.</p>
	<?php endif; ?>

	<?php if ( $is_hub ) : ?>
	<section class="wm-section wm-prose">
		<h2>How to use the <?php echo esc_html( $len ); ?> letter word finder</h2>
		<ol>
			<li><strong>Known letters:</strong> type letters you know into their boxes (green in Wordle).</li>
			<li><strong>Must contain:</strong> letters that are in the word but you don't know where (yellow).</li>
			<li><strong>Exclude:</strong> letters that are not in the word (gray).</li>
		</ol>
		<p>Results update as you type. "Common first" sorts by how often a word appears in real English text, so likely Wordle answers rise to the top. Switch to "Scrabble score" to find high-scoring plays.</p>
	</section>
	<?php endif; ?>

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
