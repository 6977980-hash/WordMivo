<?php
/**
 * Fallback list template (blog, archives).
 *
 * @package WordMivo
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main" class="wm-content">
	<?php if ( is_home() && ! is_front_page() ) : ?>
		<h1><?php single_post_title(); ?></h1>
	<?php elseif ( is_archive() ) : ?>
		<?php the_archive_title( '<h1>', '</h1>' ); ?>
	<?php endif; ?>
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article class="wm-entry">
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<?php the_excerpt(); ?>
			</article>
		<?php endwhile; ?>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p>Nothing here yet.</p>
	<?php endif; ?>
</main>
<?php
get_footer();
