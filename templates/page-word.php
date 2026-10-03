<?php
/**
 * Single word page: /word/crane/.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

$spec    = Pages::current();
$w       = Words::get( $spec['x'] );
$word    = $w->word;
$upper   = strtoupper( $word );
$hub     = Pages::normalize( array( 'type' => 'hub', 'len' => $w->len ) );
$in_hub  = $w->len >= MIN_LEN && $w->len <= MAX_LEN && Pages::is_served( $hub );
$anagram = Words::anagrams( $w );
$inside  = Words::words_inside( $w );
$near    = Words::neighbours( $w );
$links   = Words::linkable( array_merge( $anagram, $inside, $w->base ? array( $w->base ) : array() ) );
$tiles   = tile_values();

/** A word as a link when it has its own page, else plain text. */
$word_link = static function ( string $x ) use ( $links ): string {
	return isset( $links[ $x ] )
		? sprintf( '<a href="%s">%s</a>', esc_url( Words::url( $x ) ), esc_html( $x ) )
		: esc_html( $x );
};

get_header();
?>
<main id="main" class="wm-page wm-word-page">
	<nav class="wm-crumbs" aria-label="Breadcrumb">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
		<?php if ( $in_hub ) : ?>
			<span aria-hidden="true">/</span> <a href="<?php echo esc_url( Pages::url( $hub ) ); ?>"><?php echo esc_html( "{$w->len} Letter Words" ); ?></a>
		<?php endif; ?>
	</nav>

	<h1><?php echo esc_html( ucfirst( $word ) ); ?></h1>

	<div class="wm-tiles" role="img" aria-label="<?php echo esc_attr( sprintf( '%s: %d points in Scrabble', $upper, $w->score ) ); ?>">
		<?php foreach ( str_split( $word ) as $l ) : ?>
			<span class="wm-letter" aria-hidden="true"><?php echo esc_html( strtoupper( $l ) ); ?><sub><?php echo esc_html( $tiles[ $l ] ); ?></sub></span>
		<?php endforeach; ?>
	</div>

	<p class="wm-lead">
		<?php
		if ( $w->is_valid ) {
			printf(
				'<strong>Yes, %1$s is a valid Scrabble word.</strong> It has %2$d letters and is worth <strong>%3$d points</strong> in Scrabble.',
				esc_html( $word ),
				(int) $w->len,
				(int) $w->score
			);
		} else {
			printf(
				'<strong>%1$s is not in the Scrabble word list we use</strong> (ENABLE), so most word games will not accept it. Its letters would be worth %2$d points.',
				esc_html( ucfirst( $word ) ),
				(int) $w->score
			);
		}
		if ( 5 === $w->len && $w->is_likely ) {
			echo ' It is on our list of likely Wordle answers.';
		}
		?>
	</p>

	<section class="wm-section wm-prose" aria-labelledby="wm-meaning">
		<h2 id="wm-meaning">What does <?php echo esc_html( $word ); ?> mean?</h2>
		<?php if ( $w->base ) : ?>
			<p><?php echo esc_html( ucfirst( $word ) ); ?> is a form of <?php echo $word_link( $w->base ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $word_link. ?>.</p>
		<?php endif; ?>
		<?php if ( $w->meanings ) : ?>
			<?php foreach ( $w->meanings as $pos => $senses ) : ?>
				<h3 class="wm-pos"><?php echo esc_html( $pos ); ?></h3>
				<ol class="wm-defs">
					<?php foreach ( $senses as $sense ) : ?>
						<li><?php echo esc_html( $sense[0] ); ?><?php if ( $sense[1] ) : ?> <q><?php echo esc_html( $sense[1] ); ?></q><?php endif; ?></li>
					<?php endforeach; ?>
				</ol>
			<?php endforeach; ?>
			<p class="wm-note">Definitions from WordNet 3.0, Princeton University.</p>
		<?php else : ?>
			<p>We don't have a definition for <?php echo esc_html( $word ); ?> yet.</p>
		<?php endif; ?>
	</section>

	<?php $synonyms = Words::synonyms( (string) $w->defs ); ?>
	<?php if ( $synonyms ) : ?>
	<section class="wm-section" aria-labelledby="wm-syn">
		<h2 id="wm-syn">Synonyms for <?php echo esc_html( $word ); ?></h2>
		<?php $links = array_merge( $links, Words::linkable( $synonyms ) ); ?>
		<ul class="wm-words wm-linked">
			<?php foreach ( $synonyms as $x ) : ?>
				<li><?php echo isset( $links[ $x ] ) ? '<a href="' . esc_url( Words::url( $x ) ) . '">' . esc_html( $x ) . '</a>' : esc_html( $x ); ?></li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php endif; ?>

	<?php echo Ads::slot( 'top' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in Ads::slot(). ?>

	<section class="wm-section" aria-labelledby="wm-facts">
		<h2 id="wm-facts">Word facts</h2>
		<table class="wm-facts">
			<tbody>
				<tr><th scope="row">Letters</th><td><?php echo esc_html( $w->len ); ?></td></tr>
				<tr><th scope="row">Vowels / consonants</th><td><?php echo esc_html( $w->vowels . ' / ' . ( $w->len - $w->vowels ) ); ?></td></tr>
				<tr><th scope="row">Scrabble points</th><td><?php echo esc_html( $w->score ); ?></td></tr>
				<tr><th scope="row">In the ENABLE word list</th><td><?php echo $w->is_valid ? 'Yes' : 'No'; ?></td></tr>
				<tr><th scope="row">How common</th><td><?php echo esc_html( ! $w->freq_rank ? 'Rare' : ( $w->freq_rank <= 20000 ? 'Common' : ( $w->freq_rank <= 100000 ? 'Less common' : 'Rare' ) ) ); ?></td></tr>
				<?php if ( 5 === $w->len ) : ?>
					<tr><th scope="row">Likely Wordle answer</th><td><?php echo $w->is_likely ? 'Yes' : 'No'; ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</section>

	<section class="wm-section" aria-labelledby="wm-anagrams">
		<h2 id="wm-anagrams">Anagrams of <?php echo esc_html( $word ); ?></h2>
		<?php if ( $anagram ) : ?>
			<ul class="wm-words wm-linked">
				<?php foreach ( $anagram as $x ) : ?>
					<li><?php echo $word_link( $x ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $word_link. ?></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p>No other dictionary word uses exactly the letters <?php echo esc_html( $upper ); ?>.</p>
		<?php endif; ?>
	</section>

	<?php if ( $inside ) : ?>
	<section class="wm-section" aria-labelledby="wm-inside">
		<h2 id="wm-inside">Words you can make from <?php echo esc_html( $word ); ?></h2>
		<ul class="wm-words wm-linked">
			<?php foreach ( $inside as $x ) : ?>
				<li><?php echo $word_link( $x ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $word_link. ?></li>
			<?php endforeach; ?>
		</ul>
		<p><a href="<?php echo esc_url( add_query_arg( 'letters', $word, Pages::url( array( 'type' => 'tool', 'x' => 'word-unscrambler' ) ) ) ); ?>">Unscramble <?php echo esc_html( $upper ); ?> for every word</a></p>
	</section>
	<?php endif; ?>

	<?php echo Ads::slot( 'bottom' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in Ads::slot(). ?>

	<section class="wm-section wm-prose wm-faq" aria-labelledby="wm-faq">
		<h2 id="wm-faq">Frequently asked questions</h2>
		<?php foreach ( Words::faq( $w ) as $qa ) : ?>
			<h3><?php echo esc_html( $qa[0] ); ?></h3>
			<p><?php echo esc_html( $qa[1] ); ?></p>
		<?php endforeach; ?>
	</section>

	<?php
	$related = array();
	foreach ( array( 'starts' => array( $word[0], 'starting with' ), 'ends' => array( substr( $word, -1 ), 'ending in' ) ) as $type => $info ) {
		$s = Pages::normalize( array( 'type' => $type, 'len' => $w->len, 'x' => $info[0] ) );
		if ( Pages::is_served( $s ) && Pages::count( $s ) > 0 ) {
			$related[ sprintf( '%d letter words %s %s', $w->len, $info[1], strtoupper( $info[0] ) ) ] = Pages::url( $s );
		}
	}
	if ( $in_hub ) {
		$related[ "{$w->len} letter word finder" ] = Pages::url( $hub );
	}
	foreach ( array_merge( $near['prev'], $near['next'] ) as $x ) {
		$related[ $x ] = Words::url( $x );
	}
	?>
	<nav class="wm-section wm-related" aria-label="Related">
		<h2>Related</h2>
		<ul>
			<?php foreach ( $related as $text => $url ) : ?>
				<li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $text ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</nav>
</main>
<?php
get_footer();
