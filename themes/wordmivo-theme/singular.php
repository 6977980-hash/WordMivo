<?php
/**
 * Pages and posts.
 *
 * @package WordMivo
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main" class="wm-content">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class( 'wm-prose' ); ?>>
			<h1><?php the_title(); ?></h1>
			<?php if ( is_single() ) : ?>
				<p class="wm-note"><?php echo esc_html( get_the_date() ); ?> &middot; <?php the_author(); ?></p>
			<?php endif; ?>
			<?php the_content(); ?>
		</article>
	<?php endwhile; ?>
</main>
<?php
get_footer();
