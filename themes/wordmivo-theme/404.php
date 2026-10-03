<?php
/**
 * Not found.
 *
 * @package WordMivo
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main" class="wm-content">
	<h1>Page not found</h1>
	<p>That page doesn't exist. Try the <a href="<?php echo esc_url( home_url( '/' ) ); ?>">5 letter word finder</a> or search below.</p>
	<?php get_search_form(); ?>
</main>
<?php
get_footer();
