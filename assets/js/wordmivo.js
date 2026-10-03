/* WordMivo tools. Vanilla JS, no dependencies. */
(function (root) {
	'use strict';

	var LIKELY = 2; // Flag bit in the JSON word lists: likely Wordle answer.

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

	/** Same as checkGuess but returns a compact key like "02100". */
	function feedbackKey(guess, answer) {
		var codes = { gray: '0', yellow: '1', green: '2' };
		return checkGuess(guess, answer).map(function (s) { return codes[s]; }).join('');
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
	 * known: letter or '' per position (green); notAt: letters per position that are
	 * in the word but not there (yellow); include/exclude: strings.
	 */
	function finderFilter(known, include, exclude, notAt) {
		notAt = notAt || [];
		var need = countLetters(include);
		notAt.join('').split('').forEach(function (l) { if (!need[l]) { need[l] = 1; } });
		var keep = {};
		known.forEach(function (l) { if (l) { keep[l] = 1; } });
		Object.keys(need).forEach(function (l) { keep[l] = 1; });
		var banned = exclude.split('').filter(function (l) { return !keep[l]; });
		return function (word) {
			var i;
			for (i = 0; i < known.length; i++) {
				if (known[i] && word[i] !== known[i]) { return false; }
			}
			for (i = 0; i < notAt.length; i++) {
				if (notAt[i] && notAt[i].indexOf(word[i]) !== -1) { return false; }
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

	function letterScore(words) {
		var freq = {};
		words.forEach(function (w) {
			Object.keys(countLetters(w)).forEach(function (l) { freq[l] = (freq[l] || 0) + 1; });
		});
		return function (w) {
			var s = 0;
			Object.keys(countLetters(w)).forEach(function (l) { s += freq[l] || 0; });
			return s;
		};
	}

	/**
	 * Best guesses: the words that leave the fewest answers on average
	 * (sum of squared feedback-group sizes / answers). The pool is trimmed by
	 * letter frequency first so the work stays small on phones.
	 */
	function bestGuesses(answers, pool, n) {
		if (answers.length <= 2) { return answers.slice(0, n); }
		var score = letterScore(answers);
		var size = Math.max(40, Math.min(pool.length, Math.floor(300000 / answers.length)));
		var trimmed = pool.slice().sort(function (a, b) { return score(b) - score(a); }).slice(0, size);
		var isAnswer = {};
		answers.forEach(function (a) { isAnswer[a] = 1; });
		return trimmed.map(function (g) {
			var groups = {};
			for (var i = 0; i < answers.length; i++) {
				var k = feedbackKey(g, answers[i]);
				groups[k] = (groups[k] || 0) + 1;
			}
			var sum = 0;
			for (var key in groups) { sum += groups[key] * groups[key]; }
			// Small bonus for guesses that could themselves be the answer.
			return [g, sum / answers.length - (isAnswer[g] ? 0.5 : 0)];
		}).sort(function (a, b) { return a[1] - b[1]; }).slice(0, n).map(function (x) { return x[0]; });
	}

	/* Share links: pattern helpers kept pure for tests. */
	function encodeGuesses(rows) {
		var codes = { gray: '0', yellow: '1', green: '2' };
		return rows.map(function (r) { return r[0] + r[1].map(function (s) { return codes[s]; }).join(''); }).join('.');
	}

	function decodeGuesses(s) {
		var names = ['gray', 'yellow', 'green'];
		return (s || '').split('.').filter(function (p) { return /^[a-z]{5}[012]{5}$/.test(p); }).slice(0, 6).map(function (p) {
			return [p.slice(0, 5), p.slice(5).split('').map(function (d) { return names[+d]; })];
		});
	}

	var api = {
		checkGuess: checkGuess,
		finderFilter: finderFilter,
		wordleCandidates: wordleCandidates,
		bestGuesses: bestGuesses,
		encodeGuesses: encodeGuesses,
		decodeGuesses: decodeGuesses
	};
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
			var li = el('li', (w[3] & LIKELY) ? 'wm-likely' : '', w[0]);
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
		return out; // JSON is already likely/common first.
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

	function params() {
		try { return new URLSearchParams(location.search); } catch (e) { return null; }
	}

	function setParams(map) {
		try {
			var p = new URLSearchParams();
			Object.keys(map).forEach(function (k) { if (map[k]) { p.set(k, map[k]); } });
			var qs = p.toString();
			history.replaceState(null, '', location.pathname + (qs ? '?' + qs : '') + location.hash);
		} catch (e) { /* history not available */ }
	}

	function copyLinkButton(container) {
		var btn = container.querySelector('[data-action="copy"]');
		if (!btn) { return; }
		var note = el('span', 'wm-copied');
		note.setAttribute('aria-live', 'polite');
		btn.parentNode.appendChild(note);
		btn.addEventListener('click', function () {
			var done = function () { note.textContent = 'Link copied'; setTimeout(function () { note.textContent = ''; }, 2500); };
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(location.href).then(done, function () { note.textContent = location.href; });
			} else {
				note.textContent = location.href;
			}
		});
	}

	function initFinder(form) {
		var boxes = Array.prototype.slice.call(form.querySelectorAll('.wm-box:not(.wm-notat)'));
		var notAt = Array.prototype.slice.call(form.querySelectorAll('.wm-notat'));
		var out = form.querySelector('.wm-results');
		var timer;

		function state() {
			return {
				known: boxes.map(function (b) { return lettersOnly(b.value); }),
				notAt: notAt.map(function (b) { return lettersOnly(b.value); }),
				include: lettersOnly(form.include.value),
				exclude: lettersOnly(form.exclude.value),
				sort: form.sort.value,
				likely: form.likely ? form.likely.checked : false
			};
		}
		function empty(s) {
			return !s.known.join('') && !s.notAt.join('') && !s.include && !s.exclude;
		}
		function share(s) {
			setParams(empty(s) && !s.likely ? {} : {
				k: s.known.join('') ? s.known.map(function (l) { return l || '_'; }).join('') : '',
				y: s.notAt.join('') ? s.notAt.join(',') : '',
				in: s.include,
				ex: s.exclude,
				s: s.sort !== 'common' ? s.sort : '',
				l: s.likely ? '1' : ''
			});
		}
		function run() {
			var s = state();
			share(s);
			loadWords(form.dataset.src).then(function (words) {
				var test = finderFilter(s.known, s.include, s.exclude, s.notAt);
				var found = words.filter(function (w) { return test(w[0]) && (!s.likely || (w[3] & LIKELY)); });
				found = sorted(found, s.sort);
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

		// Restore a shared search from the URL.
		var p = params();
		if (p && (p.get('k') || p.get('y') || p.get('in') || p.get('ex') || p.get('l'))) {
			var k = (p.get('k') || '').toLowerCase();
			boxes.forEach(function (b, i) { b.value = /[a-z]/.test(k[i] || '') ? k[i] : ''; });
			var y = (p.get('y') || '').split(',');
			notAt.forEach(function (b, i) { b.value = lettersOnly(y[i] || ''); });
			form.include.value = lettersOnly(p.get('in'));
			form.exclude.value = lettersOnly(p.get('ex'));
			if (/^(common|az|score)$/.test(p.get('s') || '')) { form.sort.value = p.get('s'); }
			if (form.likely) { form.likely.checked = p.get('l') === '1'; }
			run();
		}

		bindBoxes(boxes, later);
		form.addEventListener('input', function (e) { if (!e.target.classList.contains('wm-box') || e.target.classList.contains('wm-notat')) { later(); } });
		form.addEventListener('change', later);
		form.addEventListener('submit', function (e) { e.preventDefault(); run(); });
		form.addEventListener('reset', function () { setTimeout(function () { out.textContent = ''; setParams({}); }, 0); });
		form.addEventListener('focusin', function () { loadWords(form.dataset.src).catch(function () {}); }, { once: true });
		copyLinkButton(form);
	}

	var STATES = ['gray', 'yellow', 'green'];

	function initWordle(wrap) {
		var out = wrap.querySelector('.wm-results');
		var tiles = Array.prototype.slice.call(wrap.querySelectorAll('.wm-tile'));
		var hard = wrap.querySelector('input[name="hard"]');

		function setTile(t, i, s) {
			t.dataset.state = s;
			t.setAttribute('aria-label', 'Guess ' + (Math.floor(i / 5) + 1) + ' letter ' + (i % 5 + 1) + ', ' + s);
		}
		bindBoxes(tiles, function () {});
		tiles.forEach(function (t, i) {
			function cycle() { setTile(t, i, STATES[(STATES.indexOf(t.dataset.state) + 1) % 3]); }
			t.addEventListener('click', function () { if (t.value) { cycle(); } });
			t.addEventListener('keydown', function (e) {
				if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); cycle(); }
			});
		});
		wrap.addEventListener('focusin', function () { loadWords(wrap.dataset.src).catch(function () {}); }, { once: true });

		function rows() {
			var list = [];
			wrap.querySelectorAll('.wm-guess').forEach(function (row) {
				var word = '';
				var fb = [];
				row.querySelectorAll('.wm-tile').forEach(function (t) { word += lettersOnly(t.value); fb.push(t.dataset.state); });
				if (word.length === 5) { list.push([word, fb]); }
			});
			return list;
		}

		function solve() {
			var rs = rows();
			setParams(rs.length ? { g: encodeGuesses(rs), hard: hard && hard.checked ? '1' : '' } : {});
			message(out, 'Thinking...');
			loadWords(wrap.dataset.src).then(function (words) {
				// Let "Thinking..." paint before the heavier work.
				return new Promise(function (resolve) { setTimeout(function () { resolve(words); }, 30); });
			}).then(function (words) {
				var likelyAll = words.filter(function (w) { return w[3] & LIKELY; }).map(function (w) { return w[0]; });
				out.textContent = '';
				if (!rs.length) {
					var openers = bestGuesses(likelyAll, likelyAll, 10);
					out.appendChild(el('p', 'wm-best', 'Best starting words: ' + openers.join(', ')));
					out.appendChild(el('p', 'wm-note', 'Ranked by how many likely answers each word rules out on average.'));
					return;
				}
				var cands = wordleCandidates(words, rs);
				var likely = cands.filter(function (w) { return w[3] & LIKELY; });
				var answers = (likely.length ? likely : cands).map(function (w) { return w[0]; });
				var head = cands.length + ' possible word' + (cands.length === 1 ? '' : 's');
				if (likely.length) { head += ', ' + likely.length + ' likely answer' + (likely.length === 1 ? '' : 's'); }
				out.appendChild(el('p', 'wm-count', head));
				if (answers.length > 1) {
					var pool = hard && hard.checked ? answers : likelyAll.concat(answers);
					out.appendChild(el('p', 'wm-best', 'Best next guesses: ' + bestGuesses(answers, pool, 5).join(', ')));
				}
				renderList(out, cands, 300);
			}).catch(function () {
				message(out, 'The word list is not available yet. Please try again later.');
			});
		}

		wrap.querySelector('[data-action="clear"]').addEventListener('click', function () {
			tiles.forEach(function (t, i) { t.value = ''; setTile(t, i, 'gray'); });
			out.textContent = '';
			setParams({});
			moveFocus(tiles, 0);
		});
		wrap.querySelector('[data-action="solve"]').addEventListener('click', solve);
		if (hard) { hard.addEventListener('change', function () { if (out.textContent) { solve(); } }); }
		copyLinkButton(wrap);

		// Restore shared guesses from the URL.
		var p = params();
		var saved = p ? decodeGuesses(p.get('g')) : [];
		if (saved.length) {
			if (hard) { hard.checked = p.get('hard') === '1'; }
			saved.forEach(function (r, ri) {
				for (var c = 0; c < 5; c++) {
					var i = ri * 5 + c;
					tiles[i].value = r[0][c];
					setTile(tiles[i], i, r[1][c]);
				}
			});
			solve();
		}
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
			setParams({ letters: letters });
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
		copyLinkButton(form);
		var p = params();
		var saved = p ? (p.get('letters') || '').toLowerCase().replace(/[^a-z?]/g, '').slice(0, 15) : '';
		if (saved.length >= 2) {
			form.letters.value = saved;
			form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
		}
	}

	document.querySelectorAll('[data-wm="finder"]').forEach(initFinder);
	document.querySelectorAll('[data-wm="wordle"]').forEach(initWordle);
	document.querySelectorAll('[data-wm="rack"]').forEach(initRack);
})(this);
