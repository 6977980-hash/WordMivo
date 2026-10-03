<?php
/**
 * Ad slots (Google AdSense).
 *
 * While ads are off, slots print nothing at all: no markup, no space, no script.
 * Once switched on in Tools > WordMivo, each slot reserves its height up front so
 * the page does not jump when the ad arrives, and the AdSense script loads only
 * when a slot comes near the screen (or on first interaction), keeping first
 * paint fast. Unfilled slots collapse.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

class Ads {

	const POSITIONS = array(
		'top'    => 'Below the tool',
		'middle' => 'Between word lists',
		'bottom' => 'Before FAQ / related links',
	);

	private static $printed = false;

	public static function init(): void {
		add_action( 'wp_footer', array( __CLASS__, 'loader' ), 20 );
		add_action( 'init', static fn() => add_rewrite_rule( '^ads\.txt$', 'index.php?wm_ads_txt=1', 'top' ) );
		add_filter( 'query_vars', static fn( $v ) => array_merge( $v, array( 'wm_ads_txt' ) ) );
		add_action( 'template_redirect', array( __CLASS__, 'ads_txt' ), 0 );
		add_filter( 'the_content', array( __CLASS__, 'after_content' ), 20 );
	}

	public static function client(): string {
		$id = (string) get_option( 'wordmivo_adsense_client', '' );
		return preg_match( '/^ca-pub-\d{10,20}$/', $id ) ? $id : '';
	}

	public static function enabled(): bool {
		return (bool) get_option( 'wordmivo_ads_enabled', 0 ) && self::client();
	}

	/**
	 * Markup for one slot, or '' while ads are off or the slot has no ID.
	 */
	public static function slot( string $position ): string {
		$slot = (string) get_option( 'wordmivo_ad_slot_' . $position, '' );
		if ( ! self::enabled() || ! preg_match( '/^\d{6,20}$/', $slot ) || is_admin() ) {
			return '';
		}
		self::$printed = true;
		return sprintf(
			'<div class="wm-ad wm-ad-%1$s" role="complementary" aria-label="Advertisement"><span class="wm-ad-label">Advertisement</span><ins class="adsbygoogle" style="display:block" data-ad-client="%2$s" data-ad-slot="%3$s" data-ad-format="auto" data-full-width-responsive="true"></ins></div>',
			esc_attr( $position ),
			esc_attr( self::client() ),
			esc_attr( $slot )
		);
	}

	/** One slot after normal page/post content (not on our virtual pages). */
	public static function after_content( $content ) {
		if ( is_singular() && in_the_loop() && is_main_query() && ! Pages::current() ) {
			$content .= self::slot( 'bottom' );
		}
		return $content;
	}

	/**
	 * Lazy loader: fetch AdSense only when a slot is within ~600px of the viewport,
	 * or after the first scroll/tap, then fill each slot as it approaches.
	 */
	public static function loader(): void {
		if ( ! self::$printed ) {
			return;
		}
		$src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . rawurlencode( self::client() );
		?>
<style>.wm-ad{min-height:300px;margin:24px 0;text-align:center}.wm-ad-label{display:block;font-size:.7rem;letter-spacing:.06em;text-transform:uppercase;color:var(--muted,#64748b);margin-bottom:4px}@media (min-width:760px){.wm-ad{min-height:280px}}.wm-ad:has(ins[data-ad-status=unfilled]){display:none}</style>
<script>
(function(){var loaded=false,slots=[].slice.call(document.querySelectorAll('.wm-ad ins.adsbygoogle'));
function load(){if(loaded)return;loaded=true;var s=document.createElement('script');s.async=true;s.crossOrigin='anonymous';s.src=<?php echo wp_json_encode( $src ); ?>;document.head.appendChild(s);}
function fill(ins){if(ins.dataset.wmFilled)return;ins.dataset.wmFilled='1';load();(window.adsbygoogle=window.adsbygoogle||[]).push({});}
if('IntersectionObserver' in window){var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){io.unobserve(e.target);fill(e.target);}});},{rootMargin:'600px 0px'});slots.forEach(function(s){io.observe(s);});}
else{slots.forEach(fill);}
['scroll','pointerdown','keydown'].forEach(function(ev){window.addEventListener(ev,load,{once:true,passive:true});});})();
</script>
		<?php
	}

	/** /ads.txt, required by AdSense, built from the publisher ID. */
	public static function ads_txt(): void {
		if ( ! get_query_var( 'wm_ads_txt' ) ) {
			return;
		}
		$client = self::client();
		status_header( $client ? 200 : 404 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		if ( $client ) {
			echo 'google.com, ' . esc_html( str_replace( 'ca-', '', $client ) ) . ", DIRECT, f08c47fec0942fa0\n";
		}
		exit;
	}
}
