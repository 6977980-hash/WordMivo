<?php
/**
 * Header.
 *
 * @package WordMivo
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#4338ca">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="wm-skip" href="#main">Skip to content</a>
<header class="wm-header">
	<div class="wm-wrap">
		<a class="wm-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="WordMivo home">Word<span>Mivo</span></a>
		<nav class="wm-nav" aria-label="Main">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'depth' => 1 ) );
			} else {
				wordmivo_default_nav();
			}
			?>
		</nav>
		<button type="button" class="wm-theme-toggle" id="wm-theme-toggle" aria-label="Toggle dark mode">&#9680;</button>
	</div>
</header>
<div class="wm-wrap">
