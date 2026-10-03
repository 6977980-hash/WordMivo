<?php
/**
 * Embeddable widgets for other websites: /embed/finder/5/ and /embed/wordle/.
 *
 * The embed code puts an iframe plus a normal text link to WordMivo on the host
 * page; the link in the host page is what earns the backlink. Embed pages are
 * noindex and may be framed by any site.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

class Embed {

	const WIDGETS = array(
		'finder' => 'Word finder',
		'wordle' => 'Wordle solver',
	);

	public static function init(): void {
		add_action( 'init', static fn() => add_rewrite_rule( '^embed/(finder|wordle)(?:/([3-8]))?/?$', 'index.php?wm_embed=$matches[1]&wm_len=$matches[2]', 'top' ) );
		add_filter( 'query_vars', static fn( $v ) => array_merge( $v, array( 'wm_embed' ) ) );
		add_action( 'template_redirect', array( __CLASS__, 'render' ), 0 );
	}

	public static function url( string $widget, int $len = 5 ): string {
		return home_url( '/embed/' . $widget . '/' . ( 'finder' === $widget ? $len . '/' : '' ) );
	}

	/** Copy-paste code for a host page: iframe + visible credit link. */
	public static function code( string $widget, int $len = 5 ): string {
		$target = 'finder' === $widget ? Pages::url( array( 'type' => 'hub', 'len' => $len ) ) : Pages::url( array( 'type' => 'tool', 'x' => 'wordle-solver' ) );
		$label  = 'finder' === $widget ? "{$len} letter word finder" : 'Wordle solver';
		$height = 'finder' === $widget ? 640 : 760;
		return sprintf(
			'<iframe src="%1$s" title="%2$s by WordMivo" width="100%%" height="%3$d" style="border:0;max-width:640px" loading="lazy"></iframe>' . "\n" . '<p style="font-size:14px">%4$s by <a href="%5$s">WordMivo</a></p>',
			esc_url( self::url( $widget, $len ) ),
			esc_attr( ucfirst( $label ) ),
			$height,
			esc_html( ucfirst( $label ) ),
			esc_url( $target )
		);
	}

	public static function render(): void {
		$widget = get_query_var( 'wm_embed' );
		if ( ! isset( self::WIDGETS[ $widget ] ) ) {
			return;
		}
		$len = (int) get_query_var( 'wm_len' );
		$len = $len >= MIN_LEN && $len <= MAX_LEN ? $len : 5;
		header_remove( 'X-Frame-Options' );
		header( 'Content-Security-Policy: frame-ancestors *' );
		header( 'X-Robots-Tag: noindex, follow' );
		status_header( 200 );
		$tool   = 'finder' === $widget ? Shortcodes::finder( array( 'length' => $len ) ) : Shortcodes::wordle();
		$home   = 'finder' === $widget ? Pages::url( array( 'type' => 'hub', 'len' => $len ) ) : Pages::url( array( 'type' => 'tool', 'x' => 'wordle-solver' ) );
		$css    = (string) file_get_contents( WORDMIVO_DIR . 'assets/css/tools.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, follow">
<title><?php echo esc_html( self::WIDGETS[ $widget ] . ' | WordMivo' ); ?></title>
<link rel="canonical" href="<?php echo esc_url( $home ); ?>">
<style>
:root{--bg:#fff;--surface:#f6f7fb;--text:#0f172a;--muted:#5b6475;--line:#e2e6ef;--primary:#4338ca;--on-primary:#fff;color-scheme:light}
@media (prefers-color-scheme:dark){:root{--bg:#0d1117;--surface:#151b24;--text:#e6e9ef;--muted:#9aa4b2;--line:#273041;--primary:#8b93ff;--on-primary:#0d1117;color-scheme:dark}}
body{margin:0;padding:8px;background:var(--bg);color:var(--text);font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
.wm-tool{margin:0}[data-action=copy]{display:none}.wm-credit{font-size:13px;color:var(--muted);text-align:right;margin:6px 4px}.wm-credit a{color:var(--primary)}
<?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput -- static file shipped with the plugin. ?>
</style>
</head>
<body>
<?php echo $tool; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the shortcode. ?>
<p class="wm-credit">Powered by <a href="<?php echo esc_url( $home ); ?>" target="_blank" rel="noopener">WordMivo</a></p>
<?php Shortcodes::print_js(); ?>
</body>
</html>
		<?php
		exit;
	}

	/** [wordmivo_embed_code]: the widget page with ready-to-copy code and a preview. */
	public static function shortcode(): string {
		$out = '';
		foreach ( array( array( 'finder', 5 ), array( 'finder', 4 ), array( 'finder', 6 ), array( 'wordle', 5 ) ) as $i => $w ) {
			$title = 'finder' === $w[0] ? "{$w[1]} letter word finder" : 'Wordle solver';
			$id    = 'wm-embed-' . $i;
			$out  .= '<section class="wm-section"><h2>' . esc_html( ucfirst( $title ) ) . '</h2>'
				. '<label for="' . esc_attr( $id ) . '">Copy this code into your page (HTML or Custom HTML block):</label>'
				. '<textarea id="' . esc_attr( $id ) . '" class="wm-code" rows="4" readonly onclick="this.select()">' . esc_textarea( self::code( $w[0], $w[1] ) ) . '</textarea>'
				. ( 0 === $i ? '<p class="wm-note">Preview:</p><iframe src="' . esc_url( self::url( $w[0], $w[1] ) ) . '" title="Word finder preview" width="100%" height="640" style="border:1px solid var(--line);border-radius:12px;max-width:640px" loading="lazy"></iframe>' : '' )
				. '</section>';
		}
		return $out;
	}
}
