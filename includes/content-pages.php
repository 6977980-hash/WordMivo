<?php
/**
 * Standard site pages created once by the plugin (About, Methodology, Contact,
 * Privacy Policy, Terms). Editable afterwards in WP Admin > Pages.
 *
 * @package WordMivo
 */

namespace WordMivo;

defined( 'ABSPATH' ) || exit;

function standard_pages(): array {
	$updated = 'Last updated: October 3, 2026.';
	$email   = 'contact@wordmivo.com';

	return array(
		'about'            => array(
			'title'   => 'About WordMivo',
			'content' => <<<HTML
<p>WordMivo is a free set of word tools for people who love word games. Whether you are stuck on today's Wordle, looking for a high-scoring Scrabble play, or just curious which five-letter words end in "y", WordMivo helps you find the right word in seconds.</p>
<h2>What you can do here</h2>
<ul>
<li><strong>Word finders</strong> for 3 to 8 letter words: enter the letters you know, the letters that must appear and the ones to leave out.</li>
<li><strong>Wordle solver</strong>: type your guesses and colours to see every answer that still fits, plus the best next guess.</li>
<li><strong>Anagram solver, word unscrambler and Scrabble word finder</strong>, including blank tiles.</li>
<li><strong>Word lists</strong>: words that start with, end in or contain any letter, with the most common words shown first.</li>
</ul>
<h2>How we are different</h2>
<p>Most word lists are sorted A to Z, so rare words crowd out the ones you actually need. WordMivo ranks words by how often they appear in real English text, so likely answers come first. Every list also shows Scrabble scores. You can read exactly where our words come from on our <a href="/methodology/">methodology page</a>.</p>
<h2>Free and fast</h2>
<p>WordMivo is free to use and needs no account. Pages are built to load quickly on any phone, and the tools work as you type.</p>
<p>Questions or ideas? <a href="/contact/">Get in touch</a>.</p>
<p><em>Wordle is a trademark of The New York Times Company. Scrabble is a trademark of Hasbro, Inc. in the US and Canada and of Mattel elsewhere. WordMivo is an independent site and is not affiliated with or endorsed by these companies.</em></p>
HTML,
		),
		'methodology'      => array(
			'title'   => 'How Our Word Lists Work',
			'content' => <<<HTML
<p>This page explains where WordMivo's words come from and how we sort them, so you know what you are looking at.</p>
<h2>Word source</h2>
<p>Our word list is based on the open-source <a href="https://github.com/dwyl/english-words" rel="nofollow">dwyl/english-words</a> list (released under the Unlicense). It contains about 370,000 English words. We keep only words made of the letters a to z, which leaves about 359,000 words.</p>
<p>Because this list is very broad, it includes some rare, old or specialised words. That is useful for word games, but it also means not every word is accepted by every game. Wordle, Scrabble and other games each use their own dictionaries.</p>
<h2>How "common words" are chosen</h2>
<p>We rank words using Peter Norvig's word frequency list (<a href="https://norvig.com/ngrams/" rel="nofollow">count_1w.txt</a>), which counts how often words appear across a very large sample of English web text. A word is marked common if it is among the 20,000 most frequent words. On every list, common words are shown first; the full list follows in A to Z order.</p>
<h2>Scrabble scores</h2>
<p>The number next to each word is its base Scrabble score using standard English tile values, before any board bonuses. In the Scrabble word finder, blank tiles score zero, as in the game.</p>
<h2>Wordle solver logic</h2>
<p>The Wordle solver checks every five-letter word against each guess and colour you enter, using the same rules as the game, including repeated letters. If you guess a letter twice and only one copy is coloured, the solver knows the answer has that letter exactly once.</p>
<h2>Corrections</h2>
<p>If you spot a word that should not be on a list, or one that is missing, please <a href="/contact/">tell us</a>.</p>
<p>{$updated}</p>
HTML,
		),
		'contact'          => array(
			'title'   => 'Contact',
			'content' => <<<HTML
<p>We would love to hear from you, whether you found a bug, want a word added or removed, or have an idea for a new tool.</p>
<p>Email: <a href="mailto:{$email}">{$email}</a></p>
<p>We read every message and usually reply within a few days.</p>
HTML,
		),
		'privacy-policy'   => array(
			'title'   => 'Privacy Policy',
			'content' => <<<HTML
<p>This policy explains what information WordMivo ("we") collects when you use wordmivo.com, and how it is used. We keep data collection to a minimum.</p>
<h2>No accounts</h2>
<p>You can use every tool without signing up. We do not ask for your name, email or any other personal details unless you choose to email us.</p>
<h2>What you type into the tools</h2>
<p>The word finder and Wordle solver run in your browser; the letters you type are not sent to us. The anagram solver, word unscrambler and Scrabble word finder send the letters you enter to our server to look up matching words. We do not store these searches with anything that identifies you.</p>
<h2>Server logs and abuse protection</h2>
<p>Like most websites, our hosting provider (Hostinger) keeps standard server logs, such as IP address, browser type and the pages requested, for security and troubleshooting. To protect our tools from abuse, we count requests per visitor for one minute using a one-way hash of the IP address, which is then discarded.</p>
<h2>Browser storage</h2>
<p>We store two small settings in your browser's local storage: your light or dark mode choice (<code>wm_theme</code>) and your answer to our cookie notice (<code>wm_consent</code>). They never leave your device, and you can clear them at any time in your browser settings.</p>
<h2>Analytics cookies</h2>
<p>If we use Google Analytics, it only loads after you click "Accept" on our cookie notice. It helps us understand which pages are useful, using cookies set by Google. If you decline, no analytics cookies are set. You can read <a href="https://policies.google.com/privacy" rel="nofollow">Google's privacy policy</a> for details.</p>
<h2>Advertising</h2>
<p>We may show ads in the future to keep WordMivo free. If we do, we will update this policy before ads appear and ask for consent where the law requires it.</p>
<h2>Children</h2>
<p>WordMivo is suitable for all ages and does not knowingly collect personal information from children.</p>
<h2>Your rights</h2>
<p>Depending on where you live, you may have the right to ask what data we hold about you and to have it deleted. Email us at <a href="mailto:{$email}">{$email}</a>.</p>
<h2>Changes</h2>
<p>We will post any changes to this policy on this page and update the date below.</p>
<p>{$updated}</p>
HTML,
		),
		'terms-of-service' => array(
			'title'   => 'Terms of Service',
			'content' => <<<HTML
<p>By using wordmivo.com you agree to these terms. If you do not agree, please do not use the site.</p>
<h2>Use of the site</h2>
<p>WordMivo is free for personal use. Please do not use automated tools to scrape the site or send large volumes of requests to our tools, and do not try to disrupt or harm the service.</p>
<h2>No guarantee</h2>
<p>We work hard to keep our word lists accurate, but they are provided "as is". A word appearing on WordMivo does not guarantee it will be accepted by any particular game or dictionary, and we are not responsible for game results.</p>
<h2>Fair play</h2>
<p>Our tools are meant to help you learn words and get unstuck. How you use them, and whether that fits the rules of the game or competition you are playing, is up to you.</p>
<h2>Trademarks</h2>
<p>Wordle is a trademark of The New York Times Company. Scrabble is a trademark of Hasbro, Inc. in the US and Canada and of Mattel elsewhere. WordMivo is not affiliated with or endorsed by these companies.</p>
<h2>Links</h2>
<p>We may link to other websites. We are not responsible for their content or practices.</p>
<h2>Liability</h2>
<p>To the extent allowed by law, WordMivo is not liable for any loss or damage arising from your use of the site.</p>
<h2>Changes</h2>
<p>We may update these terms from time to time. The date below shows the latest version.</p>
<p>{$updated}</p>
<p>Contact: <a href="mailto:{$email}">{$email}</a></p>
HTML,
		),
	);
}

/**
 * Create missing pages once. Never overwrites a page someone has published or edited,
 * except WordPress's own unpublished privacy-policy draft.
 */
function create_standard_pages(): array {
	$log = array();
	foreach ( standard_pages() as $slug => $page ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing && 'publish' === $existing->post_status ) {
			$log[] = "kept {$slug}";
			continue;
		}
		$data = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $page['title'],
			'post_content' => $page['content'],
		);
		if ( $existing && 'privacy-policy' === $slug ) {
			$data['ID'] = $existing->ID;
			wp_update_post( $data );
			$log[] = "replaced draft {$slug}";
		} elseif ( $existing ) {
			$log[] = "kept unpublished {$slug}";
			continue;
		} else {
			wp_insert_post( $data );
			$log[] = "created {$slug}";
		}
		if ( 'privacy-policy' === $slug ) {
			$id = get_page_by_path( $slug, OBJECT, 'page' )->ID ?? 0;
			if ( $id ) {
				update_option( 'wp_page_for_privacy_policy', $id );
			}
		}
	}
	update_option( 'wordmivo_pages_created', 1, false );
	return $log;
}
