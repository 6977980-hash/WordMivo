<?php
/**
 * WordMivo theme setup. Functionality lives in the WordMivo Core plugin.
 *
 * @package WordMivo
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'responsive-embeds' );
		register_nav_menus( array( 'primary' => 'Primary', 'footer' => 'Footer' ) );
	}
);

// Lean front end: no emoji script, no oEmbed discovery, no block CSS unless content uses blocks.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );

add_action(
	'wp_enqueue_scripts',
	static function () {
		$post       = get_post();
		$has_blocks = is_singular() && $post && has_blocks( $post );
		if ( ! $has_blocks ) {
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'wp-block-library-theme' );
			wp_dequeue_style( 'global-styles' );
			wp_dequeue_style( 'classic-theme-styles' );
		}
		if ( ! is_singular() || ! comments_open() ) {
			wp_dequeue_script( 'comment-reply' );
		}
	},
	100
);

// All theme CSS is small, so inline it: no render-blocking request.
add_action(
	'wp_head',
	static function () {
		$css = file_get_contents( get_theme_file_path( 'assets/css/main.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		echo '<style id="wm-main-css">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- static theme file.
		// Apply saved theme before paint to avoid a flash.
		echo "<script>try{var t=localStorage.getItem('wm_theme');if(t)document.documentElement.dataset.theme=t}catch(e){}</script>\n";
	},
	1
);

/**
 * Fallback navigation when no menu is assigned.
 */
function wordmivo_default_nav(): void {
	$links = array(
		'5 Letters'     => home_url( '/' ),
		'4 Letters'     => home_url( '/4-letter-words/' ),
		'6 Letters'     => home_url( '/6-letter-words/' ),
		'Wordle Solver' => home_url( '/wordle-solver/' ),
		'Unscrambler'   => home_url( '/word-unscrambler/' ),
	);
	echo '<ul>';
	foreach ( $links as $label => $url ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $label ) );
	}
	echo '</ul>';
}

/**
 * Footer links to standard pages that exist.
 */
function wordmivo_footer_pages(): void {
	echo '<ul>';
	foreach ( array( 'about', 'methodology', 'contact', 'privacy-policy', 'terms-of-service' ) as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page && 'publish' === $page->post_status ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( get_permalink( $page ) ), esc_html( get_the_title( $page ) ) );
		}
	}
	echo '</ul>';
}

/**
 * Site-wide internal links: word finders, tools and 5-letter A-Z lists.
 */
function wordmivo_footer_links(): void {
	if ( ! class_exists( 'WordMivo\\Pages' ) ) {
		return;
	}
	$groups = array();
	for ( $n = 3; $n <= 8; $n++ ) {
		$groups['Word finders'][ "{$n} letter words" ] = WordMivo\Pages::url( array( 'type' => 'hub', 'len' => $n ) );
	}
	foreach ( WordMivo\Pages::TOOLS as $slug => $name ) {
		$groups['Tools'][ $name ] = WordMivo\Pages::url( array( 'type' => 'tool', 'x' => $slug ) );
	}
	foreach ( range( 'a', 'z' ) as $l ) {
		$spec = WordMivo\Pages::normalize( array( 'type' => 'starts', 'len' => 5, 'x' => $l ) );
		if ( WordMivo\Pages::is_served( $spec ) && WordMivo\Pages::count( $spec ) > 0 ) {
			$groups['5 letter words starting with'][ strtoupper( $l ) ] = WordMivo\Pages::url( $spec );
		}
	}
	foreach ( $groups as $label => $links ) {
		printf( '<nav class="wm-footer-group" aria-label="%1$s"><p class="wm-footer-title">%1$s</p><ul>', esc_attr( $label ) );
		foreach ( $links as $text => $url ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $text ) );
		}
		echo '</ul></nav>';
	}
}

/**
 * Footer links to WordMivo's social profiles, when set in Tools > WordMivo.
 */
function wordmivo_footer_social(): void {
	if ( ! class_exists( 'WordMivo\\Seo' ) ) {
		return;
	}
	$links = WordMivo\Seo::social_links();
	if ( ! $links ) {
		return;
	}
	echo '<ul class="wm-social">';
	foreach ( $links as $key => $url ) {
		printf( '<li><a href="%s" rel="me noopener" target="_blank">%s</a></li>', esc_url( $url ), esc_html( WordMivo\Seo::SOCIAL[ $key ] ) );
	}
	echo '</ul>';
}
