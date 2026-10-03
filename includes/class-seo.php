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
		add_action( 'wp_head', array( __CLASS__, 'icons' ), 3 );
		add_action( 'init', static fn() => add_rewrite_rule( '^llms\\.txt$', 'index.php?wm_llms=1', 'top' ) );
		add_filter( 'query_vars', static fn( $v ) => array_merge( $v, array( 'wm_llms' ) ) );
		add_action( 'template_redirect', array( __CLASS__, 'llms_txt' ), 0 );
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
			$post        = get_post();
			$source      = has_excerpt( $post ) ? $post->post_excerpt : strip_shortcodes( $post->post_content );
			$description = wp_html_excerpt( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $source ) ) ), 155, '...' );
		}
		$canonical = self::canonical();
		$image     = self::asset_url( 'og.png' );

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
		if ( $image ) {
			echo '<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">' . "\n";
			printf( '<meta property="og:image:alt" content="%s">' . "\n", esc_attr( 'WordMivo 5 letter word finder and Wordle solver' ) );
		}
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		self::verification();

		if ( $spec ) {
			self::json_ld( $spec, $canonical, $description );
		}
	}

	private static function verification(): void {
		$gsc = get_option( 'wordmivo_gsc_verification', GSC_DEFAULT );
		if ( $gsc ) {
			printf( '<meta name="google-site-verification" content="%s">' . "\n", esc_attr( $gsc ) );
		}
	}

	private static function json_ld( array $spec, string $url, string $description ): void {
		$graph   = array();
		$home    = home_url( '/' );
		$graph[] = array(
			'@type'     => 'WebSite',
			'@id'       => $home . '#website',
			'name'      => 'WordMivo',
			'url'       => $home,
			'publisher' => array( '@id' => $home . '#org' ),
		);
		$graph[] = array(
			'@type' => 'Organization',
			'@id'   => $home . '#org',
			'name'  => 'WordMivo',
			'url'   => $home,
			'logo'  => self::asset_url( 'og.png' ),
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
		$faq = Pages::faq( $spec );
		if ( $faq ) {
			$graph[] = array(
				'@type'      => 'FAQPage',
				'mainEntity' => array_map(
					static fn( $qa ) => array(
						'@type'          => 'Question',
						'name'           => $qa[0],
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => $qa[1],
						),
					),
					$faq
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

	/** Theme assets via the plugin URL, which works whichever theme is active. */
	private static function asset_url( string $file ): string {
		return WORDMIVO_URL . 'themes/wordmivo-theme/assets/' . $file;
	}

	public static function icons(): void {
		printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( self::asset_url( 'favicon.svg' ) ) );
	}

	/**
	 * /llms.txt: a plain summary of the site for AI assistants (llmstxt.org format).
	 */
	public static function llms_txt(): void {
		if ( ! get_query_var( 'wm_llms' ) ) {
			return;
		}
		$lines   = array( '# WordMivo', '', '> Free word finder tools for Wordle, Scrabble and word games: find words by length, letters, position and pattern, with common English words listed first and Scrabble scores shown.', '' );
		$lines[] = '## Tools';
		foreach ( Pages::TOOLS as $slug => $name ) {
			$spec    = Pages::normalize( array( 'type' => 'tool', 'x' => $slug ) );
			$lines[] = sprintf( '- [%s](%s): %s', $name, Pages::url( $spec ), Pages::tool_description( $slug ) );
		}
		$lines[] = '';
		$lines[] = '## Word finders by length';
		for ( $n = MIN_LEN; $n <= MAX_LEN; $n++ ) {
			$spec    = Pages::normalize( array( 'type' => 'hub', 'len' => $n ) );
			$lines[] = sprintf( '- [%d letter words](%s): finder and list of %s %d-letter words', $n, Pages::url( $spec ), number_format_i18n( Pages::count( $spec ) ), $n );
		}
		$lines[] = '';
		$lines[] = '## Word lists';
		$lines[] = sprintf( '- 5 letter words starting with a letter, e.g. [starting with A](%s)', Pages::url( array( 'type' => 'starts', 'len' => 5, 'x' => 'a' ) ) );
		$lines[] = sprintf( '- 5 letter words ending in a letter, e.g. [ending in E](%s)', Pages::url( array( 'type' => 'ends', 'len' => 5, 'x' => 'e' ) ) );
		$lines[] = sprintf( '- Full index: [sitemap](%s)', home_url( '/wp-sitemap.xml' ) );
		$lines[] = '';
		$lines[] = '## About';
		foreach ( array( 'about', 'methodology' ) as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && 'publish' === $page->post_status ) {
				$lines[] = sprintf( '- [%s](%s)', get_the_title( $page ), get_permalink( $page ) );
			}
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo implode( "\n", $lines ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- plain text.
		exit;
	}
}
