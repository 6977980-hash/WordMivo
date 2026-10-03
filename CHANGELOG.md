# Changelog

## 0.3.1
- SEO titles: keyword-rich titles for all tool pages; standard pages use "Title | WordMivo" without repeating the brand.
- Hand-written meta descriptions for About, Methodology, Contact, Privacy Policy and Terms (a page excerpt still overrides).
- Site name and tagline are filled once if empty or still the WordPress default.

## 0.3.0
- Word pages at /word/{word}/: meaning (WordNet 3.0), Scrabble score, letter tiles, word facts, anagrams, words you can make, FAQ + DefinedTerm schema. Defined dictionary words are indexable; inflected forms and non-dictionary words are noindex. New sitemap (wp-sitemap-wordmivowords-N.xml), ~23k most common words. Common words on list pages link to their word page.
- New data file data/definitions.tsv (built from WordNet 3.0; licence in data/WORDNET-LICENSE.txt) and a "defs" import stage. Needs one Start import.
- Wordle Game Analyzer (/wordle-analyzer/): skill and luck per guess, best guess at each step, shareable link.
- Spelling Bee solver, Letter Boxed solver (with one/two-word solutions) and Quordle/Octordle solver.
- Word pages set is switched on once on upgrade.

## 0.2.2
- Legal: source licences recorded correctly (Norvig list is MIT via norvig/pytudes; ENABLE public domain).
- Methodology page states we do not copy any game's official word or answer lists.
- Footer disclaimer now names Scrabble (Hasbro / Mattel) as well as Wordle (NYT).
- When ads are on, the privacy policy automatically adds the Google AdSense cookie disclosure and opt-out links.

## 0.1.0 — 2026-10-03
- First build from Prompt v6: plugin + bundled theme, word tables, batched importer (admin + WP-CLI), JSON word lists, finder, Wordle solver, anagram/unscramble/rack REST, daily Wordle hints, programmatic list pages, schema, sitemap provider, robots.txt, security headers, consent-gated GA4.
- One-time cleanup of `docs/` and `.git/` left in `public_html` by the first deploy (only when they match this repo).

## 0.1.1 — 2026-10-03
- Creates About, Methodology, Contact, Privacy Policy and Terms of Service pages once (never overwrites published pages).

## 0.1.2 — 2026-10-03
- FAQ section + FAQPage schema on finder hubs and list pages (direct answers for search and AI assistants).
- Organization schema, OG image (1200x630), favicon, meta description fallback for normal pages.
- /llms.txt for AI assistants.
- Site-wide footer links: finders, tools, 5-letter A-Z lists.

## 0.2.0 — 2026-10-03
- ENABLE dictionary (public domain) flags dictionary words and adds ~21k missing valid words.
- "Likely Wordle answer" estimate (~2,100 five-letter words: dictionary + common + not plural/past tense), shown first and marked with a green bar; "Only likely answers" filter.
- Finder: yellow "not in this spot" letters per position.
- Wordle solver: best starting words, best next guesses by expected remaining answers, hard mode.
- Shareable links for finder, solver and rack tools (URL updates as you search) + Copy link button.
- Standard pages refresh automatically when unedited. Admin notice asks for one re-import after this update.

## 0.2.1 — 2026-10-03
- Search Console verification meta for wordmivo.com set by default.
- Ad slots (AdSense): invisible and zero-cost while ads are off; when on, reserved height (no layout shift), lazy-loaded script, unfilled slots collapse, automatic /ads.txt. Settings in Tools > WordMivo.
