<?php
/**
 * Titles, meta, canonical, Open Graph, JSON-LD, robots.txt, headers, analytics.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

class Seo {

	public static function init(): void {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 20 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 2 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 10, 2 );
		add_action( 'send_headers', array( __CLASS__, 'security_headers' ) );
		add_action( 'wp_footer', array( __CLASS__, 'analytics' ), 99 );

		// Our own canonical is printed for virtual pages.
		add_action(
			'template_redirect',
			static function () {
				if ( Pages::current() ) {
					remove_action( 'wp_head', 'rel_canonical' );
				}
			},
			5
		);
	}

	private static function seo_plugin_active(): bool {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' );
	}

	public static function document_title( $title ) {
		$spec = Pages::current();
		if ( ! $spec ) {
			return $title;
		}
		if ( ! empty( $spec['home'] ) ) {
			return '5 Letter Words: Word Finder for Wordle & Word Games | WordMivo';
		}
		return Pages::title( $spec );
	}

	public static function robots( array $robots ): array {
		$spec = Pages::current();
		if ( $spec && ! Pages::is_indexable( $spec ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		if ( is_search() ) {
			$robots['noindex'] = true;
		}
		$robots['max-image-preview'] = 'large';
		return $robots;
	}

	private static function canonical(): string {
		$spec = Pages::current();
		if ( $spec ) {
			return ! empty( $spec['home'] ) ? home_url( '/' ) : Pages::url( $spec );
		}
		if ( is_singular() ) {
			return (string) wp_get_canonical_url();
		}
		return is_front_page() ? home_url( '/' ) : '';
	}

	public static function head(): void {
		$spec = Pages::current();
		if ( ! $spec && self::seo_plugin_active() ) {
			self::verification();
			return;
		}
		$title       = $spec ? self::document_title( '' ) : wp_get_document_title();
		$description = '';
		if ( $spec ) {
			$description = Pages::description( $spec );
		} elseif ( is_singular() ) {
			$description = wp_strip_all_tags( get_the_excerpt() );
		}
		$canonical = self::canonical();
		$image     = file_exists( get_theme_file_path( 'assets/og.png' ) ) ? get_theme_file_uri( 'assets/og.png' ) : '';

		if ( $canonical && $spec ) {
			printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canonical ) );
		}
		if ( $description ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
		}
		$og = array(
			'og:type'        => 'website',
			'og:site_name'   => 'WordMivo',
			'og:title'       => $title,
			'og:description' => $description,
			'og:url'         => $canonical,
			'og:image'       => $image,
		);
		foreach ( array_filter( $og ) as $property => $content ) {
			printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $property ), esc_attr( $content ) );
		}
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		self::verification();

		if ( $spec ) {
			self::json_ld( $spec, $canonical, $description );
		}
	}

	private static function verification(): void {
		$gsc = get_option( 'wordmivo_gsc_verification', '' );
		if ( $gsc ) {
			printf( '<meta name="google-site-verification" content="%s">' . "\n", esc_attr( $gsc ) );
		}
	}

	private static function json_ld( array $spec, string $url, string $description ): void {
		$graph   = array();
		$home    = home_url( '/' );
		$graph[] = array(
			'@type' => 'WebSite',
			'@id'   => $home . '#website',
			'name'  => 'WordMivo',
			'url'   => $home,
		);

		$crumbs = array(
			array(
				'name' => 'Home',
				'item' => $home,
			),
		);
		if ( 'tool' !== $spec['type'] && 'hub' !== $spec['type'] ) {
			$hub      = Pages::normalize( array( 'type' => 'hub', 'len' => $spec['len'] ) );
			$crumbs[] = array(
				'name' => "{$spec['len']} Letter Words",
				'item' => Pages::url( $hub ),
			);
		}
		if ( empty( $spec['home'] ) ) {
			$crumbs[] = array(
				'name' => Pages::heading( $spec ),
				'item' => $url,
			);
		}
		$items = array();
		foreach ( array_values( array_unique( $crumbs, SORT_REGULAR ) ) as $i => $c ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $c['name'],
				'item'     => $c['item'],
			);
		}
		if ( count( $items ) > 1 ) {
			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $items,
			);
		}

		if ( 'tool' === $spec['type'] || 'hub' === $spec['type'] ) {
			$graph[] = array(
				'@type'               => 'WebApplication',
				'name'                => ! empty( $spec['home'] ) ? '5 Letter Word Finder' : Pages::heading( $spec ),
				'url'                 => $url,
				'description'         => $description,
				'applicationCategory' => 'GameApplication',
				'operatingSystem'     => 'Any',
				'browserRequirements' => 'Requires JavaScript',
				'offers'              => array(
					'@type'         => 'Offer',
					'price'         => '0',
					'priceCurrency' => 'USD',
				),
			);
		} else {
			$words   = Pages::words( $spec );
			$list    = $words['common'] ? $words['common'] : $words['all'];
			$graph[] = array(
				'@type'           => 'ItemList',
				'name'            => Pages::heading( $spec ),
				'numberOfItems'   => $words['total'],
				'itemListElement' => array_map(
					static fn( $i, $w ) => array(
						'@type'    => 'ListItem',
						'position' => $i + 1,
						'name'     => $w->word,
					),
					array_keys( array_slice( $list, 0, 50 ) ),
					array_slice( $list, 0, 50 )
				),
			);
		}
		echo '<script type="application/ld+json">' . wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		) . "</script>\n";
	}

	public static function robots_txt( string $output, $is_public ): string {
		if ( ! $is_public ) {
			return $output;
		}
		$bots = array( 'GPTBot', 'ClaudeBot', 'PerplexityBot', 'Google-Extended' );
		foreach ( $bots as $bot ) {
			$output .= "\nUser-agent: {$bot}\nAllow: /\n";
		}
		return $output;
	}

	public static function security_headers(): void {
		if ( is_admin() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
	}

	/**
	 * GA4 loads only after the visitor accepts the consent banner (theme sets localStorage 'wm_consent').
	 */
	public static function analytics(): void {
		$id = get_option( 'wordmivo_ga4_id', '' );
		if ( ! $id || ! preg_match( '/^G-[A-Z0-9]+$/', $id ) ) {
			return;
		}
		?>
<script>
(function(){function load(){var s=document.createElement('script');s.async=1;s.src='https://www.googletagmanager.com/gtag/js?id=<?php echo esc_js( $id ); ?>';document.head.appendChild(s);window.dataLayer=window.dataLayer||[];function g(){dataLayer.push(arguments)}g('js',new Date());g('config','<?php echo esc_js( $id ); ?>');}
var ok=false;try{ok=localStorage.getItem('wm_consent')==='yes'}catch(e){}
if(ok){window.addEventListener('load',function(){setTimeout(load,1500)})}else{document.addEventListener('wm:consent',load,{once:true})}})();
</script>
		<?php
	}
}
