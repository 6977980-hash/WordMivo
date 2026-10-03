# WordMivo Core

WordPress plugin for [wordmivo.com](https://wordmivo.com): word finders (3–8 letters), Wordle solver, anagram solver, word unscrambler, Scrabble rack finder, daily Wordle hints, and server-rendered word-list pages (starts with, ends in, contains, letter position, special lists), with SEO, schema and sitemaps built in.

The WordMivo theme ships inside this plugin (`themes/wordmivo-theme`) and is registered automatically, so one Git deploy updates both.

Build spec: [`docs/WordMivo_Prompt_v6.md`](docs/WordMivo_Prompt_v6.md).

## Install on Hostinger

1. **Deploy:** hPanel → Websites → wordmivo.com → Advanced → GIT. Repository `6977980-hash/WordMivo`, branch `claude/project-thread-cauwph`, directory `public_html/wp-content/plugins/wordmivo-core`, auto-deployment on.
2. **Word lists:** `data/` in this repo already holds `words_alpha.txt`, `count_1w.txt` and `enable1.txt` (web access blocked by `.htaccess`), so Git deploy brings them. To use another folder, add `define( 'WORDMIVO_DATA_DIR', '/full/path' );` to `wp-config.php`.
3. **Activate:** WP Admin → Plugins → activate **WordMivo Core**. Then Appearance → Themes → activate **WordMivo**.
4. **Permalinks:** Settings → Permalinks → "Post name" → Save.
5. **Import:** Tools → WordMivo → **Start import** (runs in small batches; keep the tab open). Over SSH instead: `wp wordmivo verify` then `wp wordmivo import`.
6. **Cache:** install LiteSpeed Cache, enable page cache and browser cache. Purge cache after each import.
7. **Search Console:** paste the verification code in Tools → WordMivo, then submit `https://wordmivo.com/wp-sitemap.xml`.

## Page sets

Tools → WordMivo → Page sets. Defaults on: hubs, starts-with, ends-in, tools. Turn on contains, position, special and letter-pair pages in later batches. Pages with fewer than 5 words are `noindex`; empty pages return 404.

| URL pattern | Example |
|---|---|
| `/{n}-letter-words/` (5 = home page) | `/4-letter-words/` |
| `/{n}-letter-words-starting-with-{x}/` | `/5-letter-words-starting-with-st/` |
| `/{n}-letter-words-ending-in-{x}/` | `/5-letter-words-ending-in-e/` |
| `/{n}-letter-words-with-{x}/` | `/5-letter-words-with-z/` |
| `/5-letter-words-with-{x}-as-{second,third,fourth}-letter/` | `/5-letter-words-with-a-as-second-letter/` |
| `/{n}-letter-words-{with-no-vowels,with-double-letters,with-three-vowels}/` | `/5-letter-words-with-no-vowels/` |
| Tools | `/wordle-solver/` `/anagram-solver/` `/word-unscrambler/` `/scrabble-word-finder/` `/todays-wordle-hints/` |

## Shortcodes

`[wordmivo_finder length="5"]`, `[wordmivo_wordle]`, `[wordmivo_anagram]`, `[wordmivo_unscramble]`, `[wordmivo_scrabble]`, `[wordmivo_wordle_hints]`

## REST

`GET /wp-json/wordmivo/v1/words?mode=anagram|unscramble|rack&letters=abc?` — 2–15 letters, up to 2 `?` blanks, max 1,000 results, 60 requests/min per IP.

## Rollback

Deactivate the plugin (data is kept). Its data lives only in `wp_wm_words`, `wp_wm_sources`, `wordmivo_*` options and `wp-content/uploads/wordmivo/`. Restore older code with Hostinger GIT → All deployments, or a Hostinger backup.

## Tests

`node tests/wordle.test.js` — Wordle feedback with repeated letters, finder and solver filters.
