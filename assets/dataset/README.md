# WordMivo Wordle & Five-Letter Word Dataset

Free, ready-to-use data about five-letter English words, from [WordMivo](https://wordmivo.com/).

| File | Rows | What it is |
|---|---|---|
| `five-letter-words.csv` | 8,636 | Every five-letter word in the ENABLE word list, with Scrabble score, English frequency rank and whether it is a likely Wordle answer |
| `likely-wordle-answers.txt` | 2,141 | Our estimate of likely Wordle answers, one per line, A to Z |
| `wordle-starting-words-ranked.csv` | 8,636 | Every five-letter word ranked as a Wordle opening guess |

## Columns

**five-letter-words.csv**
- `word`: lowercase word
- `scrabble_score`: base Scrabble tile value, no premium squares
- `frequency_rank`: rank in English web text (1 = most frequent); empty when not ranked
- `is_likely_wordle_answer`: 1 if the word is in `likely-wordle-answers.txt`

**wordle-starting-words-ranked.csv**
- `rank`: 1 = best opener
- `avg_answers_left`: expected number of likely answers left after this first guess (lower is better)
- `worst_case`: largest number of answers that can be left
- `feedback_patterns`: how many different colour patterns the guess can produce
- `green_chance_pct`: chance of at least one green tile
- `is_likely_answer`: 1 if the opener is itself a likely answer

## How it was made

- Words: the ENABLE word list (public domain).
- Frequency: Peter Norvig's `count_1w.txt` from [norvig/pytudes](https://github.com/norvig/pytudes) (MIT License, Copyright (c) 2010-2017 Peter Norvig).
- Likely answers: our own estimate, not the official answer list. A word is included when it is in ENABLE, is among the 40,000 most frequent English words, and is not a simple plural or past tense.
- Openers: each word was played as a first guess against all 2,141 likely answers using Wordle's colour rules (repeated letters handled as in the game).

Wordle is a trademark of The New York Times Company. This dataset is independent and not affiliated with it.

## License and credit

The dataset is released under [Creative Commons Attribution 4.0 (CC BY 4.0)](https://creativecommons.org/licenses/by/4.0/). You may use it for anything, including commercially, as long as you credit WordMivo with a link:

> Data: [WordMivo](https://wordmivo.com/) (CC BY 4.0)

Frequency ranks are derived from MIT-licensed data; keep the MIT notice above when you redistribute them.

Also on Kaggle: https://www.kaggle.com/datasets/wordpresswordmivo/wordle-word-list-and-five-letter-words-dataset

Updates and more tools: https://wordmivo.com/ · Contact: contact@wordmivo.com
