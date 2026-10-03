<?php
/**
 * Footer.
 *
 * @package WordMivo
 */

defined( 'ABSPATH' ) || exit;
?>
</div>
<footer class="wm-footer">
	<div class="wm-wrap">
		<?php
		if ( has_nav_menu( 'footer' ) ) {
			wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'depth' => 1 ) );
		}
		wordmivo_footer_links();
		wordmivo_footer_pages();
		?>
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> WordMivo. Wordle is a trademark of The New York Times Company. Scrabble is a trademark of Hasbro, Inc. and Mattel. WordMivo is an independent word helper and is not affiliated with them.</p>
	</div>
</footer>
<?php if ( get_option( 'wordmivo_ga4_id' ) ) : ?>
<div class="wm-consent" id="wm-consent" hidden>
	<p>We use analytics cookies to improve WordMivo. You can say no.</p>
	<button type="button" class="wm-btn" data-consent="yes">Accept</button>
	<button type="button" class="wm-btn wm-btn-ghost" data-consent="no">Decline</button>
</div>
<?php endif; ?>
<script>
(function(){var d=document.documentElement,b=document.getElementById('wm-theme-toggle');
if(b){b.addEventListener('click',function(){var dark=d.dataset.theme?d.dataset.theme==='dark':matchMedia('(prefers-color-scheme: dark)').matches;d.dataset.theme=dark?'light':'dark';try{localStorage.setItem('wm_theme',d.dataset.theme)}catch(e){}});}
var c=document.getElementById('wm-consent');if(!c)return;var v=null;try{v=localStorage.getItem('wm_consent')}catch(e){}
if(!v){c.hidden=false;}
c.addEventListener('click',function(e){var a=e.target.getAttribute('data-consent');if(!a)return;try{localStorage.setItem('wm_consent',a)}catch(e){}c.hidden=true;if(a==='yes')document.dispatchEvent(new Event('wm:consent'));});})();
</script>
<?php wp_footer(); ?>
</body>
</html>
