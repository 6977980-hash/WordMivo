# Changelog

## 0.6.1
- Family-friendly: data/blocklist.txt (profanity, sexual slang, slurs) is skipped at import and, after this deploy, removed from the word and definition tables in the background (DATA_REV 2), then likely answers, finder files and counts are rebuilt. Affects lists, finders, solvers, word pages and the sitemap.
- Dataset regenerated: 8,602 five-letter words, 2,130 likely answers, starting words re-ranked (RAISE still #1). Methodology page explains the filter (PAGES_VERSION 6).

## 0.6.0
- Word quality: lists, page counts, the finder, Wordle/Quordle solvers, anagram solver, unscrambler and Scrabble finder now use ENABLE dictionary words only (no names or junk such as "david", "aaron", "topsl"). Finder files and counts rebuild once in the background after deploy (no import needed); LiteSpeed cache is purged after rebuilds and imports.
- Removed Today's Wordle Hints (needed a daily manual answer); /todays-wordle-hints/ redirects to /wordle-solver/. Admin field removed.
- WordPress leftovers: sample "Hello world!" post and "Sample Page" are trashed once if unedited; author archives redirect to /about/ and the users sitemap is off.
- Favicon: PNG icons (48, 192), apple-touch-icon and /favicon.ico now show the WordMivo icon instead of the WordPress logo; Organization logo is a square 512px PNG.
- FAQ sections with FAQPage schema on the Wordle solver, anagram solver, word unscrambler and Scrabble word finder.
- Meta descriptions for word pages and Word of the Day are built from whole sentences and stay under 160 characters.
- Upper-case word URLs (/word/Crane/) redirect to lower case.
- Finder: "Find words" scrolls the results into view on phones.
- Fix: Wordle solver suggested the same best guess twice.

## 0.5.1
- Dataset page links to the Kaggle copy of the dataset (also in the Dataset schema sameAs and the README).

## 0.5.0
- Word of the Day (/word-of-the-day/): picked automatically each day from defined words with an example, never repeated, stored in option wordmivo_wotd; title changes daily; page cache expires at midnight.
- Open dataset: assets/dataset/ (five-letter-words.csv, likely-wordle-answers.txt, wordle-starting-words-ranked.csv, README, CC BY 4.0) and /wordle-word-list-download/ with schema.org Dataset markup.
- AI/GPT: /openapi.json describing the word API; new REST modes filter (Wordle-style pattern/include/exclude/notat) and define (meanings, synonyms, anagrams, score, page URL); higher rate limit for ChatGPT actions.

## 0.4.2
- /BingSiteAuth.xml served from the Bing code (Bing's XML-file verification method).

## 0.4.1
- Bing Webmaster Tools verification meta (msvalidate.01), default code set, editable in Tools > WordMivo.

## 0.4.0
- Crossword clue solver / reverse dictionary (/crossword-solver/): FULLTEXT search over WordNet meanings and synonyms, filtered by length or pattern (c???e); pattern-only search lists common words. DB_VERSION 4 (fulltext index).
- definitions.tsv now carries WordNet synonyms ("s:" group); word pages show a Synonyms section. Needs one Start import.
- Wordle analyzer: "Share my result" (emoji summary, no letters) and "Save image" (1080x1080 report card); share links hide the answer (ROT13 ?s=).
- Embeddable widgets: /embed/finder/{3-8}/ and /embed/wordle/ (noindex, frameable anywhere) and a /word-finder-widget/ page with copy-paste code that includes a credit link.
- Rack modes now check the blank limit in the handler so clue patterns may use many ? characters.

## 0.3.2
- IndexNow: key served at /{key}.txt; new URLs are submitted to Bing/IndexNow in the background after each import and page-set change, and posts/pages on publish. WP-CLI: wp wordmivo indexnow. Skipped on localhost.
- E-E-A-T: About page "Why I built WordMivo" by Ali Ahmad with LinkedIn; Methodology byline; Organization/Person/AboutPage schema with founder and sameAs.
- Social profile links setting (Tools > WordMivo): shown in the footer and as Organization sameAs.
- New data page /best-wordle-starting-words/ (data/starting-words.json, computed offline from our likely-answer list).

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
