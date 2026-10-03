<?php
/**
 * Search results.
 *
 * @package WordMivo
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main" class="wm-content">
	<h1><?php printf( 'Search results for "%s"', esc_html( get_search_query() ) ); ?></h1>
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article class="wm-entry"><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><?php the_excerpt(); ?></article>
		<?php endwhile; ?>
	<?php else : ?>
		<p>No results. Looking for words from letters? Try the <a href="<?php echo esc_url( home_url( '/word-unscrambler/' ) ); ?>">word unscrambler</a>.</p>
	<?php endif; ?>
</main>
<?php
get_footer();
