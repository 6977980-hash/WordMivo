/* WordMivo tools. Vanilla JS, no dependencies. */
(function (root) {
	'use strict';

	/** Wordle feedback for guess vs answer, two passes so repeated letters match the game. */
	function checkGuess(guess, answer) {
		var result = ['gray', 'gray', 'gray', 'gray', 'gray'].slice(0, guess.length);
		var left = answer.split('');
		var i, idx;
		for (i = 0; i < guess.length; i++) {
			if (guess[i] === answer[i]) {
				result[i] = 'green';
				left[i] = null;
			}
		}
		for (i = 0; i < guess.length; i++) {
			if (result[i] === 'gray') {
				idx = left.indexOf(guess[i]);
				if (idx !== -1) {
					result[i] = 'yellow';
					left[idx] = null;
				}
			}
		}
		return result;
	}

	function countLetters(s) {
		var c = {};
		for (var i = 0; i < s.length; i++) {
			c[s[i]] = (c[s[i]] || 0) + 1;
		}
		return c;
	}

	/**
	 * Build a predicate for the finder.
	 * known: array of letter or '' per position; include/exclude: strings.
	 */
	function finderFilter(known, include, exclude) {
		var need = countLetters(include);
		var keep = {};
		known.forEach(function (l) { if (l) { keep[l] = 1; } });
		Object.keys(need).forEach(function (l) { keep[l] = 1; });
		var banned = exclude.split('').filter(function (l) { return !keep[l]; });
		return function (word) {
			var i;
			for (i = 0; i < known.length; i++) {
				if (known[i] && word[i] !== known[i]) { return false; }
			}
			for (i = 0; i < banned.length; i++) {
				if (word.indexOf(banned[i]) !== -1) { return false; }
			}
			var have = countLetters(word);
			for (var l in need) {
				if ((have[l] || 0) < need[l]) { return false; }
			}
			return true;
		};
	}

	/** Words consistent with every [guess, colours] pair. */
	function wordleCandidates(words, rows) {
		return words.filter(function (w) {
			for (var r = 0; r < rows.length; r++) {
				var fb = checkGuess(rows[r][0], w[0]);
				for (var i = 0; i < 5; i++) {
					if (fb[i] !== rows[r][1][i]) { return false; }
				}
			}
			return true;
		});
	}

	/** Rank guesses by how many candidates share their (distinct) letters. */
	function bestGuesses(candidates, n) {
		var freq = {};
		candidates.forEach(function (w) {
			Object.keys(countLetters(w[0])).forEach(function (l) { freq[l] = (freq[l] || 0) + 1; });
		});
		return candidates.map(function (w) {
			var s = 0;
			Object.keys(countLetters(w[0])).forEach(function (l) { s += freq[l]; });
			return [w[0], s + (w[2] ? 1 / w[2] : 0)];
		}).sort(function (a, b) { return b[1] - a[1]; }).slice(0, n).map(function (x) { return x[0]; });
	}

	var api = { checkGuess: checkGuess, finderFilter: finderFilter, wordleCandidates: wordleCandidates, bestGuesses: bestGuesses };
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}
	if (typeof document === 'undefined') {
		return;
	}
	root.WordMivo = api;

	/* ---------- DOM ---------- */

	var cache = {};
	function loadWords(src) {
		if (!src) { return Promise.reject(new Error('no-data')); }
		if (!cache[src]) {
			cache[src] = fetch(src, { credentials: 'omit' }).then(function (r) {
				if (!r.ok) { throw new Error('HTTP ' + r.status); }
				return r.json();
			});
		}
		return cache[src];
	}

	function el(tag, cls, text) {
		var e = document.createElement(tag);
		if (cls) { e.className = cls; }
		if (text !== undefined) { e.textContent = text; }
		return e;
	}

	function renderList(box, words, limit) {
		var ul = el('ul', 'wm-words');
		words.slice(0, limit).forEach(function (w) {
			var li = el('li', '', w[0]);
			li.appendChild(el('sub', '', String(w[1])));
			ul.appendChild(li);
		});
		box.appendChild(ul);
		if (words.length > limit) {
			var more = el('button', 'wm-btn wm-btn-ghost', 'Show more');
			more.type = 'button';
			more.addEventListener('click', function () {
				box.removeChild(ul);
				box.removeChild(more);
				renderList(box, words, limit + 500);
			});
			box.appendChild(more);
		}
	}

	function sorted(words, mode) {
		var out = words.slice();
		if (mode === 'az') {
			out.sort(function (a, b) { return a[0] < b[0] ? -1 : 1; });
		} else if (mode === 'score') {
			out.sort(function (a, b) { return b[1] - a[1] || (a[0] < b[0] ? -1 : 1); });
		}
		return out; // JSON is already common-first.
	}

	function lettersOnly(v) {
		return (v || '').toLowerCase().replace(/[^a-z]/g, '');
	}

	function message(box, text) {
		box.textContent = '';
		box.appendChild(el('p', 'wm-note', text));
	}

	function moveFocus(inputs, i) {
		if (inputs[i]) { inputs[i].focus(); inputs[i].select(); }
	}

	function bindBoxes(inputs, onChange) {
		inputs.forEach(function (input, i) {
			input.addEventListener('input', function () {
				input.value = lettersOnly(input.value).slice(-1);
				if (input.value) { moveFocus(inputs, i + 1); }
				onChange();
			});
			input.addEventListener('keydown', function (e) {
				if (e.key === 'Backspace' && !input.value) { moveFocus(inputs, i - 1); }
				if (e.key === 'ArrowLeft') { moveFocus(inputs, i - 1); }
				if (e.key === 'ArrowRight') { moveFocus(inputs, i + 1); }
			});
		});
	}

	function initFinder(form) {
		var boxes = Array.prototype.slice.call(form.querySelectorAll('.wm-box'));
		var out = form.querySelector('.wm-results');
		var timer;
		function run() {
			loadWords(form.dataset.src).then(function (words) {
				var known = boxes.map(function (b) { return lettersOnly(b.value); });
				var test = finderFilter(known, lettersOnly(form.include.value), lettersOnly(form.exclude.value));
				var found = sorted(words.filter(function (w) { return test(w[0]); }), form.sort.value);
				out.textContent = '';
				out.appendChild(el('p', 'wm-count', found.length + ' word' + (found.length === 1 ? '' : 's') + ' found'));
				renderList(out, found, 300);
			}).catch(function () {
				message(out, 'The word list is not available yet. Please try again later.');
			});
		}
		function later() {
			clearTimeout(timer);
			timer = setTimeout(run, 150);
		}
		bindBoxes(boxes, later);
		form.addEventListener('input', function (e) { if (!e.target.classList.contains('wm-box')) { later(); } });
		form.addEventListener('change', later);
		form.addEventListener('submit', function (e) { e.preventDefault(); run(); });
		form.addEventListener('reset', function () { setTimeout(function () { out.textContent = ''; }, 0); });
		form.addEventListener('focusin', function () { loadWords(form.dataset.src).catch(function () {}); }, { once: true });
	}

	var STATES = ['gray', 'yellow', 'green'];

	function initWordle(wrap) {
		var out = wrap.querySelector('.wm-results');
		var tiles = Array.prototype.slice.call(wrap.querySelectorAll('.wm-tile'));
		bindBoxes(tiles, function () {});
		tiles.forEach(function (t, i) {
			function cycle() {
				var next = STATES[(STATES.indexOf(t.dataset.state) + 1) % 3];
				t.dataset.state = next;
				t.setAttribute('aria-label', 'Guess ' + (Math.floor(i / 5) + 1) + ' letter ' + (i % 5 + 1) + ', ' + next);
			}
			t.addEventListener('click', function () { if (t.value) { cycle(); } });
			t.addEventListener('keydown', function (e) {
				if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); cycle(); }
			});
		});
		wrap.addEventListener('focusin', function () { loadWords(wrap.dataset.src).catch(function () {}); }, { once: true });

		wrap.querySelector('[data-action="clear"]').addEventListener('click', function () {
			tiles.forEach(function (t) { t.value = ''; t.dataset.state = 'gray'; });
			out.textContent = '';
			moveFocus(tiles, 0);
		});
		wrap.querySelector('[data-action="solve"]').addEventListener('click', function () {
			var rows = [];
			wrap.querySelectorAll('.wm-guess').forEach(function (row) {
				var ts = row.querySelectorAll('.wm-tile');
				var word = '';
				var fb = [];
				ts.forEach(function (t) { word += lettersOnly(t.value); fb.push(t.dataset.state); });
				if (word.length === 5) { rows.push([word, fb]); }
			});
			if (!rows.length) {
				message(out, 'Enter at least one full 5-letter guess.');
				return;
			}
			loadWords(wrap.dataset.src).then(function (words) {
				var cands = wordleCandidates(words, rows);
				out.textContent = '';
				out.appendChild(el('p', 'wm-count', cands.length + ' possible answer' + (cands.length === 1 ? '' : 's')));
				if (cands.length > 1) {
					var best = bestGuesses(cands.length > 3000 ? cands.slice(0, 3000) : cands, 5);
					out.appendChild(el('p', 'wm-best', 'Best next guesses: ' + best.join(', ')));
				}
				renderList(out, cands, 300);
			}).catch(function () {
				message(out, 'The word list is not available yet. Please try again later.');
			});
		});
	}

	function initRack(form) {
		var out = form.querySelector('.wm-results');
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var letters = (form.letters.value || '').toLowerCase().replace(/[^a-z?]/g, '');
			if (letters.length < 2) {
				message(out, 'Type at least 2 letters.');
				return;
			}
			message(out, 'Searching...');
			var url = window.wmConfig.rest + '?mode=' + encodeURIComponent(form.dataset.mode) + '&letters=' + encodeURIComponent(letters);
			fetch(url).then(function (r) { return r.json().then(function (j) { return [r.ok, j]; }); }).then(function (res) {
				if (!res[0]) {
					message(out, res[1].message || 'Something went wrong.');
					return;
				}
				var words = res[1].words;
				out.textContent = '';
				out.appendChild(el('p', 'wm-count', words.length + ' word' + (words.length === 1 ? '' : 's') + ' found'));
				var groups = {};
				words.forEach(function (w) { (groups[w[0].length] = groups[w[0].length] || []).push(w); });
				Object.keys(groups).sort(function (a, b) { return b - a; }).forEach(function (len) {
					out.appendChild(el('h3', '', len + ' letters'));
					renderList(out, groups[len], 200);
				});
			}).catch(function () {
				message(out, 'Network error. Please try again.');
			});
		});
	}

	document.querySelectorAll('[data-wm="finder"]').forEach(initFinder);
	document.querySelectorAll('[data-wm="wordle"]').forEach(initWordle);
	document.querySelectorAll('[data-wm="rack"]').forEach(initRack);
})(this);
