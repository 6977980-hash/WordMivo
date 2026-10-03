# WordMivo — Claude Execution Prompt v6

Supersedes v5.5. Written 2026-10-03. Review of v5.5: `prompt-review-and-strategy.md`.

## 0. Mission and ground rules

Build **WordMivo**, a WordPress word-finder site that beats https://5-letter-words.com/ on coverage, usefulness and speed.

Competitor baseline (checked 2026-10-03): ~150 pages; finders for 3–7 letters; 26 "starts with" pages per length with the word list in static HTML (e.g. 736 five-letter words starting with A) and Scrabble scores; filter tool with known position / unknown position / exclude / repeat toggle / dark mode. It has **no** ends-with, contains, position, daily Wordle, FAQ, blog, schema or author pages.

Rules for the executing agent:
1. **Never fabricate a result.** If you cannot run or measure something (Lighthouse, live counts, server checks), write `NOT MEASURED` and say why. A report is only `READY` when every line is verified.
2. Stop after each phase with its report and ask: `Phase N complete. Proceed? (yes/no)`.
3. Never put credentials in code, commits or reports. Read them from environment variables only.
4. Never touch the live server's database or files outside the steps listed. No destructive SQL (`DROP`, `TRUNCATE`, `DELETE` without `WHERE`) except on the plugin's own `wm_` tables during re-import, after confirming.
5. All code: PHP 8.1+, WordPress 6.6+, WordPress Coding Standards, every input sanitized, every output escaped, nonces + capability checks on all admin actions.
6. Repo: `6977980-hash/WordMivo`. Work on a branch, open a PR per phase.

## 1. Architecture

Two packages, never mixed:

```
wordmivo-core/            (plugin: all functionality, survives theme changes)
  wordmivo-core.php
  includes/  class-installer.php  class-importer.php  class-cli.php
             class-rest.php  class-pages.php  class-sitemap.php
             class-schema.php  class-wordle-daily.php  helpers.php
  templates/ page-list.php  page-finder.php
  assets/js/ finder.js  wordle.js  solver.js       (vanilla, no jQuery)
  assets/data/ words-{3..8}.json  (generated, hashed filenames)
wordmivo-theme/           (theme: design only)
  style.css functions.php header.php footer.php index.php page.php
  single.php 404.php search.php
  assets/css/main.css  assets/js/theme.js
```

Tools are exposed as **shortcodes** (`[wordmivo_finder length="5"]`, `[wordmivo_wordle]`, `[wordmivo_scrabble]`, `[wordmivo_anagram]`, `[wordmivo_unscramble]`). No Gutenberg blocks in v1. If blocks are added later, names must start with a letter (`wordmivo/five-letter-finder`) and callbacks use valid PHP names via an explicit map, never string-built names with hyphens.

## 2. Phases

### Phase 0 — Data verification (read-only)

Input files (uploaded by the owner to Hostinger; exact path given by the owner, stored in `WORDMIVO_DATA_DIR`, default outside `public_html`):
- `words_alpha.txt` — dwyl/english-words, one word per line, ~370,105 words.
- `count_1w.txt` — Peter Norvig word frequency list, `word<TAB>count`.

Tasks: confirm both files exist and are readable; count lines; count words by length; sha256 of each; confirm the data dir is not web-accessible (or add an `.htaccess` deny).

Expected (from the public dwyl list; mismatch = report, not fail): length 3 = 2,130; 4 = 7,186; 5 = 15,921; 6 = 29,874; 7 = 41,998.

```
PHASE-0-REPORT
- Data dir: <path> (web-accessible: yes/no)
- words_alpha.txt: <lines> lines, sha256 <hash>
- count_1w.txt: <lines> lines, sha256 <hash>
- Counts by length 2..8: <...>
- Status: READY / BLOCKED (<reason>)
```

### Phase 1 — Plugin + theme skeleton

- Plugin header, activation hook creates tables (via `dbDelta`), deactivation keeps data, uninstall removes tables only if the admin option `wordmivo_delete_on_uninstall` is on.
- Theme: system font stack, design tokens as CSS custom properties (primary, accent, bg, text, radius 8/12/9999px, one subtle shadow), light + dark (`prefers-color-scheme` + toggle stored in localStorage with try/catch), `:focus-visible`, `prefers-reduced-motion`, skip link, mobile-first, no horizontal scroll at 360px.
- Front end loads **no jQuery, no web fonts, no icon fonts**; dequeue block-library CSS on pages that do not use blocks; remove emoji scripts and oEmbed.

### Phase 2 — Database and import

```sql
CREATE TABLE {prefix}wm_words (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  word VARCHAR(32) NOT NULL,
  len TINYINT UNSIGNED NOT NULL,
  first_letter CHAR(1) NOT NULL,
  last_letter CHAR(1) NOT NULL,
  signature VARCHAR(32) NOT NULL,         -- letters sorted a..z, for anagrams
  letter_mask INT UNSIGNED NOT NULL,      -- bit i = contains letter i, for contains/exclude
  vowels TINYINT UNSIGNED NOT NULL,
  has_double TINYINT(1) NOT NULL DEFAULT 0,
  scrabble_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  freq_rank INT UNSIGNED NULL,            -- rank in count_1w.txt, NULL if absent
  UNIQUE KEY uq_word (word),
  KEY idx_len_rank (len, freq_rank),
  KEY idx_len_first (len, first_letter),
  KEY idx_len_last (len, last_letter),
  KEY idx_signature (signature)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE {prefix}wm_sources (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_name VARCHAR(191) NOT NULL,
  url TEXT NOT NULL,
  license VARCHAR(191) NOT NULL,          -- copy exactly from the source page; "UNVERIFIED" if unknown
  sha256 CHAR(64) NOT NULL,
  acquired_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Import:
- Primary: WP-CLI `wp wordmivo import [--batch=5000]`. Fallback: admin page with chained AJAX batches of 2,000 lines (nonce + `manage_options`), resumable via an option storing the byte offset.
- Keep only `^[a-z]{2,15}$`. Load `count_1w.txt` into a rank map first, then insert words with `freq_rank`.
- Scrabble score with standard English tile values.
- After import, generate `assets/data/words-{len}.json` for lengths 3–8 as `[[word, score, rank_or_0], ...]` sorted by rank (common first), filenames include a content hash; store the current filename in an option.
- Record both sources in `wm_sources`.

Common words: do not use a boolean. Use `freq_rank`. A word is "common" for display if `freq_rank <= 20000` (expected only ~1,300 five-letter words fall in the top 10,000; that is fine).

```
PHASE-2-REPORT
- Rows imported: <n> (by length 2..8: <...>)
- Words with freq_rank: <n>; five-letter with rank<=20000: <n>
- JSON files: <name> <raw KB> / <gzip KB> for each length
- Sources recorded: <n>
- Re-import idempotent: tested yes/no
- Status: READY / BLOCKED
```

### Phase 3 — Tools

| Tool | Where it runs | Notes |
|---|---|---|
| Word finder (3–8) | Client, from JSON | Inputs: known positions (one box per letter), letters anywhere-but-not-here, exclude, starts/ends/contains; sort common / A–Z / score; result count; copy button |
| Wordle solver | Client, 5-letter JSON | Grid where each guess letter is clicked gray/yellow/green; correct duplicate-letter logic (two-pass, `===`); ranks remaining words by common + letter-frequency score; shows "best next guess" |
| Scrabble / word finder from rack | REST | Rack up to 15 letters, up to 2 blanks (`?`), results grouped by length, score shown |
| Anagram solver | REST | Exact match on `signature` index |
| Unscrambler | REST | All sub-words length 3+; filter candidates with `letter_mask` first, then count check in PHP |

REST: `GET /wp-json/wordmivo/v1/words` with `mode` (`anagram|unscramble|rack`), `letters` (`^[a-z?]{1,15}$`), max 2 blanks, max 1,000 results. Rate limit 60 requests/min per IP hash using transients; return `429` beyond. Responses cacheable (`Cache-Control: public, max-age=86400`).

Daily Wordle page: spoiler-safe (hints first: vowel count, first letter, then a reveal button). Answer entered by admin each day in a simple admin field, or fetched by a scheduled job only if the owner approves a source. Never use "Wordle" in the brand name; describe it as "hints for today's Wordle".

Definitions: v1 none. (Later: store WordNet definitions locally for common words only.)
PWA: not in v1.

Unit tests: PHP (PHPUnit or a WP-CLI test command) for signature/mask/score; JS test file for the Wordle feedback function with duplicate-letter cases (`speed` vs `abide`, `eerie` vs `there`, `allee` vs `lever`).

```
PHASE-3-REPORT
- Tools working: <list with yes/no each>
- Wordle duplicate-letter tests: <passed>/<total>
- REST validation + rate limit tested: yes/no
- Status: READY / BLOCKED
```

### Phase 4 — Programmatic pages (virtual, server-rendered)

Do not create thousands of WP posts. Register rewrite rules and render from `templates/page-list.php`. Every list page:
- H1 with the exact query ("5 Letter Words Ending in E").
- Word list **rendered in HTML by PHP** (not loaded by JS), common words first in a "Most common" section, then all others A–Z; Scrabble score next to each word; total count.
- A short computed intro unique to the page: count, % common, most frequent second letter, 3 example common words. No AI filler paragraphs.
- The finder tool pre-filled with the page's constraint.
- Related links block (same letter other rule, same rule neighbouring letters, other lengths).
- Pages with fewer than 5 words: `noindex, follow`.

Page sets (launch order):
1. Finder hubs: `/3-letter-words/` … `/8-letter-words/` (home = 5-letter).
2. Starts with: `/{n}-letter-words-starting-with-{x}/` for n=3..7.
3. Ends with: `/{n}-letter-words-ending-in-{x}/` for n=3..7.
4. Contains: `/{n}-letter-words-with-{x}/` for n=4..6.
5. Position (5 letters): `/5-letter-words-with-{x}-as-{second|third|fourth}-letter/`.
6. Special (5 letters): no vowels, double letters, three vowels, starting with common bigrams (st, ch, sh, tr, br, cr, gr, pl, sl, sp), ending in (er, ly, ch, sh, ed).
7. Tools: `/wordle-solver/`, `/scrabble-word-finder/`, `/anagram-solver/`, `/word-unscrambler/`, `/todays-wordle-hints/`.
8. Real pages (WP pages): About (who runs the site), Methodology (word sources, licences, how "common" is computed), Contact, Privacy, Terms.

Publish in batches (hubs + starts/ends first, others over following weeks) controlled by an admin option per page set.

```
PHASE-4-REPORT
- Page sets enabled: <list with page counts>
- Sample URLs checked (200, H1, list in HTML source): <5 URLs>
- noindex thin pages: <n>
- Status: READY / BLOCKED
```

### Phase 5 — SEO, schema, performance

- Titles/meta descriptions generated per page; self-referencing canonical; no duplicate titles.
- Schema (JSON-LD, plugin-owned, no SEO plugin needed): `WebSite`, `WebApplication` on tool pages, `BreadcrumbList` everywhere, `ItemList` (first 50 words) on list pages. FAQ content allowed but don't expect FAQ rich results.
- Sitemaps: register a WP core sitemap provider for virtual pages (only enabled, indexable sets).
- robots.txt: allow all incl. GPTBot, ClaudeBot, PerplexityBot, Google-Extended; sitemap line.
- OG/Twitter tags with one default 1200×630 WebP/JPG image.
- Security headers via `send_headers`: `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options: SAMEORIGIN`, `Permissions-Policy`.
- Analytics: optional GA4 ID in settings, loaded only after consent; Search Console verification meta field. If ads/GA4 will serve EEA users, use a Google-certified CMP (Consent Mode v2).
- **Performance budget (target Lighthouse 100 on mobile for home and a list page, before ads):** HTML < 60 KB gzipped for list pages (paginate/“show all” if larger), total JS < 30 KB gzipped, CSS < 15 KB inlined critical, zero render-blocking resources, no layout shift (reserve space for any future ad slot now), LCP element is text, JSON loaded on idle/interaction. Hostinger: LiteSpeed Cache plugin for page cache + browser cache.

```
PHASE-5-REPORT
- Schema validated (Rich Results Test or schema.org validator): yes/no/NOT MEASURED
- Sitemap URL count: <n>
- Lighthouse mobile (home / list page / wordle): Perf, A11y, BP, SEO — or NOT MEASURED
- Status: READY / BLOCKED
```

### Phase 6 — QA and delivery

Acceptance checklist (all must be verified, not assumed):
1. Plugin activates on clean WP with no PHP notices in `debug.log`.
2. Theme activates; switching to a default theme keeps tools working via shortcodes.
3. Import completes; counts match Phase 0.
4. Re-import does not duplicate rows.
5. JSON files exist, hashed names, served gzipped.
6. Finder: known + unknown position + exclude combination returns correct words (3 test cases).
7. Wordle duplicate-letter tests pass.
8. Anagram returns exact matches only.
9. Unscrambler respects letter counts (no word uses a letter more often than given).
10. Rack with 2 blanks works; 3 blanks rejected.
11. REST rejects invalid input with 400 and rate-limits with 429.
12. Every enabled programmatic URL returns 200 and has the list in HTML source.
13. Thin pages are noindex.
14. Unique title + canonical on every page sample.
15. Breadcrumb + WebApplication/ItemList JSON-LD valid.
16. Sitemap lists only indexable URLs.
17. robots.txt correct.
18. No horizontal scroll at 360px; dark mode correct.
19. Keyboard-only use of every tool; visible focus; skip link works.
20. Admin actions require nonce + capability.
21. No credentials or secrets in the repo.
22. Lighthouse mobile scores recorded (or NOT MEASURED with reason).

Deliverables: `wordmivo-core.zip`, `wordmivo-theme.zip`, `README.md`, `INSTALL-HOSTINGER.md` (upload, activate, data path, WP-CLI import via SSH or admin import, LiteSpeed settings), `CHANGELOG.md`, rollback steps (deactivate plugin; tables are `wm_*` only; restore from Hostinger backup).

```
PHASE-6-REPORT
- Checklist: <passed>/22 (list failures)
- debug.log: clean / <issues>
- Packages: <files + sizes>
- Status: DELIVERED / BLOCKED
```

## 3. Ranking plan (context for content decisions)

- Month 1: launch hubs, starts-with, ends-with, tools, About/Methodology; Search Console + Bing + IndexNow.
- Month 2: contains, position, special sets; daily Wordle hints page; internal link grid.
- Month 3+: 2–3 data articles (e.g. best starting words from frequency data), outreach to tool directories and word-game communities; prune pages with zero impressions after 3 months.
- Avoid: mass AI text, publishing thousands of pages at once, indexing every single word page.
