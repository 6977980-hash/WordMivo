# Changelog

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
