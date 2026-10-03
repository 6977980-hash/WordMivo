# WordMivo: Prompt v5.5 Review + Ranking Strategy

Competitor: https://5-letter-words.com/ (3 Oct 2026 ko check kiya)

---

## 1. Sab se pehle: Database / Hostinger link

- Prompt mein **koi Hostinger link ya location mojud nahi**. Sirf ek *farz kiya hua* path hai: `wp-content/wordmivo-data/words_alpha.txt` aur `count_1w.txt`. Docx mein koi hyperlink bhi nahi.
- Yani jo upload kiya hai woh "database" nahi balki do **text files** hain (word list + frequency list). Prompt inko DB mein import karwata hai.
- Phase 0 kehta hai "file khud na parho, bas READY likh do". Yeh ghalat hai: agar path ghalat hua to Phase 2 ka import fail hoga aur pata bhi nahi chalega.
- `wp-content/` ke andar rakhi files public URL se download ho sakti hain. Behtar: `public_html` se bahar (e.g. `/home/USER/wordmivo-data/`) ya kam az kam `.htaccess` se deny.

**Aap se chahiye:** Hostinger par file ka asal path (File Manager mein jo dikhta hai). Password/credentials share na karein.

---

## 2. Prompt mein ghaltiyan (critical pehle)

| # | Ghalti | Asar | Fix |
|---|---|---|---|
| 1 | `render_callback => "render_wordmivo_$tool"` se function name banta hai `render_wordmivo_5-letter-finder` | PHP function name mein `-` allowed nahi. **Fatal/never callable**, tools render hi nahi honge | Mapping array use karein: `'5-letter-finder' => 'render_wordmivo_five_letter_finder'` |
| 2 | Block name `wordmivo/5-letter-finder` | WordPress block name **number se start nahi ho sakta**, `register_block_type` reject karta hai | `wordmivo/five-letter-finder` |
| 3 | `'supports' => ['autoRegister' => true]` | Aisi koi block support nahi hai | Hata dein. Bina editor JS ke block inserter mein nahi aayega; shortcode hi asal raasta hai |
| 4 | Block list mein 6 tools, Phase 3 mein "7 tools" | Mismatch, Claude confuse hoga | Daily Wordle ko alag page likhein ya list mein add karein |
| 5 | Phase 2.5: "5-letter words ~13,000" | `words_alpha.txt` mein asal mein **15,921** five-letter aur **7,186** four-letter words hain (maine public list gin kar check kiya) | Hardcoded expectation hata dein, actual count + sha256 report karwayein |
| 6 | "is_common ~10,000" | Top-10k frequency list ka sirf **~1,300** five-letter aur **~1,000** four-letter words se match hota hai (similar Norvig-derived list se estimate) | Boolean ki jagah `freq_rank INT` column rakhein; sorting is se karein |
| 7 | Schema mein anagram/unscramble ke liye column nahi | Anagram/Unscrambler/Scrabble 370k words par **full table scan**, shared hosting par slow/timeout | `signature` (sorted letters, indexed) + `letter_mask INT` columns add karein |
| 8 | `words_alpha.txt` ko Scrabble/Wordle ke liye use karna | Is list mein bohat se ajeeb/invalid words hain; Scrabble-valid nahi, Wordle-valid nahi | Scrabble: ENABLE (public domain). 5-letter: Wordle-accepted guesses jaisi curated list; answers-type common list alag flag |
| 9 | Norvig `count_1w.txt` ko "MIT" likha | Yeh data Google Web Trillion Word Corpus se derived hai; license khud verify karein | Source page ka license as-is record karein, assume na karein |
| 10 | Phase 3.5 Lighthouse scores | Claude ke paas live Hostinger site nahi; woh scores **ghar lega (fabricate)** | Likhein: "Agar run nahi kar sakte to `NOT MEASURED` likho" |
| 11 | Phase 4 "Dynamic content" | Agar word list JS se load hui to Google ko list kam dikhegi. Competitor 736 words **static HTML** mein dikhata hai | Programmatic pages par list **server-side PHP se render** ho, JS sirf filter ke liye |
| 12 | Phase 5 ka koi report/stop gate nahi | Baqi phases se inconsistent | PHASE-5-REPORT add karein |
| 13 | Phase 6 "22/22 checklist" kahin define nahi | Claude khud bana kar 22/22 PASS likh dega | 22 items explicitly likhein |
| 14 | "Rollback ... 4G DB import" | Typo/unclear | "Agar DB import fail ho" |
| 15 | `wordmivo-db.sql` + `words-import.csv` + AJAX import, teeno | Teen import raaste, confusion | Ek primary (WP-CLI ya AJAX), ek fallback (SQL) |
| 16 | Theme ke andar DB tables, REST API, import | Theme badalte hi saare tools/pages khatam. WordPress best practice ke khilaaf | **Do hisse:** `wordmivo-core` plugin (data, REST, shortcodes, pages) + `wordmivo-theme` (sirf design) |
| 17 | Urdu + English mix, kuch jumle tootay hue ("بو تو mismatch") | Claude ko ambiguity | Execution prompt poora English mein; aapko report Roman Urdu mein mangwa sakte hain |
| 18 | `==` JS comparisons, aur classic theme mein `page.php`, `404.php`, `search.php` missing | Minor, lekin 404/page templates zaroori | `===` aur missing templates add |
| 19 | REST rate limit "60/min" ka tareeqa nahi | Implement nahi hoga ya galat | Transient per-IP + input whitelist `[a-z?*]`, max 15 chars, max 2 blanks |
| 20 | PWA cache versioning nahi | Word list update ho to users purani list dekhte rahenge | `CACHE_VERSION` + JSON filename mein hash |

---

## 3. Kya NIKAAL dein (ya baad mein karein)

- **Gutenberg blocks** (abhi): shortcode/page templates kaafi hain. Ranking par block ka koi asar nahi.
- **PWA**: SEO par zero asar, bugs ka risk. Launch ke baad.
- **Definition modal via dictionaryapi.dev**: third-party, rate-limited, aur client-side hone ki wajah se SEO value zero. Ya to definitions DB mein (WordNet) ya hata dein.
- **FAQPage schema se rich result ki umeed**: Google 2023 se FAQ rich results sirf gov/health sites ko deta hai. FAQ content rakhein, schema optional.
- **llms.txt**: rakh sakte hain, lekin ranking factor nahi. Time na lagayein.
- **Yoast/RankMath conditional schema**: ek decide karein (RankMath ya koi nahi). Conditional code = do raaste test karne parenge.
- **Cookie banner localStorage only**: agar GA4/AdSense lagana hai to Google Consent Mode v2 wala CMP chahiye (EU traffic). Simple custom banner kaafi nahi.

---

## 4. Kya ADD karein

**Competitor ke barabar aane ke liye (must):**
- 3, 6, 7 letter word finders + unke A-Z pages (competitor ke paas 3-7 letter, 130 starts-with pages hain). Prompt sirf 4 aur 5 karta hai.

**Competitor se aage nikalne ke liye (unke paas yeh NAHI hai):**
- Ends-with pages (sab lengths) — competitor ke paas zero.
- Contains-letter pages: "5 letter words with A", "with A and E", "with no vowels", "with double letters", "with 3 vowels".
- Position pages: 2nd, 3rd, 4th letter (prompt mein 2nd aur middle hain; 4th add karein).
- Common bigrams: "starting with ST/CH/SH/TR", "ending in ER/LY/CH".
- Daily Wordle hint + answer page (spoiler-safe: pehle hints, phir reveal). Competitor ke paas nahi. "Wordle" NYT trademark hai: brand name mein use na karein, sirf descriptive.
- Har list page par: **common words pehle** (freq_rank se), "Wordle-likely" aur "rare/Scrabble-only" alag sections, count, stats (top letters), pre-filled finder tool.
- Methodology/About page: word list sources, kaun maintain karta hai (E-E-A-T). Competitor par author info zero.
- Schema: WebApplication (tools), BreadcrumbList, ItemList (word lists).
- Hostinger par **LiteSpeed Cache** plugin + page cache; Search Console + Bing Webmaster + IndexNow.
- Monetization plan (AdSense/Ezoic) abhi se layout mein jagah, warna baad mein CLS kharab hoga.

---

## 5. Competitor analysis: 5-letter-words.com

| Cheez | Unke paas | Hamara mauqa |
|---|---|---|
| Pages | ~145-150 (5 finders + 130 starts-with + legal) | 400-600 quality pages |
| Lengths | 3-7 | 2-8 |
| Ends-with / contains / position pages | Nahi | Haan (bara long-tail) |
| Word list | Static HTML, alphabetical, scrabble score ke saath (e.g. A se 736 words) | Common pehle, sections, stats |
| Tool | Position known / unknown / exclude, repeat toggle, dark mode | Yahi + Wordle grid (green/yellow/gray) input |
| Blog / FAQ / schema / author | Nahi | Haan |
| Extra | Subway Rush game cross-promo, GTM | Daily Wordle hints |
| Domain | Exact-match domain "5-letter-words", purana lagta hai | Hum yeh match nahi kar sakte; long-tail se jeetna hai |

**Seedhi baat:** "5 letter words" head keyword par competitor ka exact-match domain aur umar ka faida hai, aur upar bade sites (WordHippo, Merriam-Webster, YourDictionary, word.tips) bhi hain. Pehle din head term par harana realistic nahi. Raasta: **long-tail pages jo unke paas hain hi nahi** se traffic, phir internal linking se homepage ko authority.

---

## 6. Ranking strategy (step by step)

**Month 1: Bunyaad**
1. Prompt v6 (upar ke fixes) ke saath plugin + theme banwayein.
2. Data clean karein: curated 5-letter list, freq_rank, Scrabble-valid flag.
3. Launch: home (5-letter tool), 3/4/6/7 finders, 26 starts-with × lengths, 26 ends-with × lengths, About/Methodology, legal.
4. Search Console, sitemap submit, Core Web Vitals green (LCP < 2s mobile).

**Month 2: Long-tail pheilao**
5. Contains-letter, position, two-letter combo, no-vowel, double-letter pages. Har page par unique stats aur pehle 3 lines asal faide wali, template copy-paste text nahi.
6. Daily Wordle hints page (roz update; yahi "freshness" signal aur repeat visitors dega).
7. Internal linking grid: har page se related pages (A se start ↔ A par end ↔ A contain).

**Month 3+: Authority**
8. 2-3 data articles (e.g. "Best Wordle starting words, 15,000 words par analysis") — links attract karte hain.
9. Tool directories, Reddit/Facebook word-game groups mein genuinely useful share, embeddable widget.
10. Search Console se dekhein kaun se pages impressions le rahe hain; unhe improve karein, jo zero hain unhe merge/noindex.

**Bachne wali cheezein:**
- Hazaron AI-generated thin pages ek saath publish karna (Google "scaled content abuse" policy). 30-50 pages per week, quality ke saath.
- Har single word ka page (`/word/xyz/`) sab 370k ke liye. Agar banayein to sirf top ~3,000 common words, baqi noindex.

---

## 7. Agla qadam

Main corrected **Prompt v6** (English, plugin + theme split, sahi data numbers, defined 22-point checklist) likh sakta hoon, ya seedha repo (`6977980-hash/WordMivo`, abhi khaali hai) mein code banana shuru kar sakta hoon.
