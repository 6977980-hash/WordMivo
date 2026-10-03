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

	/**
	 * Expected answers left after guess g, not counting the case where g is the answer
	 * (that leaves nothing). Lower is better.
	 */
	function expectedLeft(g, answers) {
		var groups = {};
		for (var i = 0; i < answers.length; i++) {
			var k = feedbackKey(g, answers[i]);
			groups[k] = (groups[k] || 0) + 1;
		}
		var sum = 0;
		for (var key in groups) { if (key !== '22222') { sum += groups[key] * groups[key]; } }
		return sum / answers.length;
	}

	/**
	 * Game analysis. words: JSON rows [word, score, rank, flags]; guesses: list of words;
	 * answer: the solution. Returns one step per guess with skill and luck out of 99.
	 */
	function analyzeGame(words, guesses, answer) {
		var likelyAll = words.filter(function (w) { return w[3] & LIKELY; }).map(function (w) { return w[0]; });
		var answerLikely = likelyAll.indexOf(answer) !== -1;
		var rows = [];
		var steps = [];
		guesses.forEach(function (g) {
			var cands = wordleCandidates(words, rows).filter(function (w) { return !answerLikely || (w[3] & LIKELY); }).map(function (w) { return w[0]; });
			if (cands.indexOf(answer) === -1) { cands.push(answer); }
			var fb = checkGuess(g, answer);
			var pool = likelyAll.concat(cands);
			var picks = bestGuesses(cands, pool, 3);
			var bestE = Infinity;
			picks.forEach(function (p) { bestE = Math.min(bestE, expectedLeft(p, cands)); });
			var userE = expectedLeft(g, cands);
			bestE = Math.min(bestE, userE);
			var key = fb.map(function (c) { return c === 'green' ? '2' : c === 'yellow' ? '1' : '0'; }).join('');
			var left = key === '22222' ? [] : cands.filter(function (w) { return feedbackKey(g, w) === key; });
			// Luck: share of possible answers that would have left more words than this one did.
			var worse = 0, same = 0;
			var sizes = {};
			cands.forEach(function (w) { var k = feedbackKey(g, w); sizes[k] = (sizes[k] || 0) + 1; });
			cands.forEach(function (w) {
				var k = feedbackKey(g, w);
				var n = k === '22222' ? 0 : sizes[k];
				if (n > left.length) { worse++; } else if (n === left.length) { same++; }
			});
			steps.push({
				guess: g,
				colours: fb,
				before: cands.length,
				after: left.length,
				solved: key === '22222',
				skill: Math.round(99 * (1 + bestE) / (1 + userE)),
				luck: cands.length > 1 ? Math.round(99 * (worse + same / 2) / cands.length) : 50,
				best: picks[0] || g
			});
			rows.push([g, fb]);
		});
		return steps;
	}

	/**
	 * Best next guess for several boards at once (Quordle/Octordle): a board with one
	 * answer left is finished first; otherwise the guess with the fewest expected
	 * answers left summed over the unsolved boards.
	 */
	function bestMulti(answerSets, pool, n) {
		var open = answerSets.filter(function (a) { return a.length; });
		var single = open.filter(function (a) { return a.length === 1; }).map(function (a) { return a[0]; });
		if (single.length) { return single.slice(0, n); }
		if (!open.length) { return []; }
		var union = [];
		open.forEach(function (a) { union = union.concat(a); });
		var score = letterScore(union);
		var size = Math.max(40, Math.min(pool.length, Math.floor(300000 / union.length)));
		var seen = {};
		var trimmed = pool.concat(union).filter(function (w) { if (seen[w]) { return false; } seen[w] = 1; return true; })
			.sort(function (a, b) { return score(b) - score(a); }).slice(0, size);
		return trimmed.map(function (g) {
			var t = 0;
			open.forEach(function (a) { t += expectedLeft(g, a); });
			return [g, t];
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
		expectedLeft: expectedLeft,
		analyzeGame: analyzeGame,
		bestMulti: bestMulti,
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

	function tileRow(word, colours, cls) {
		var row = el('div', 'wm-guess ' + (cls || ''));
		for (var i = 0; i < 5; i++) {
			var t = el('span', 'wm-tile', word[i].toUpperCase());
			t.dataset.state = colours[i];
			row.appendChild(t);
		}
		return row;
	}

	function wordsIn(text) {
		return (text || '').toLowerCase().split(/[^a-z]+/).filter(function (w) { return w.length === 5; }).slice(0, 6);
	}

	/** ROT13, so a shared analyzer link does not show the answer in plain text. */
	function rot13(t) {
		return t.replace(/[a-z]/g, function (c) { return String.fromCharCode((c.charCodeAt(0) - 84) % 26 + 97); });
	}

	var EMOJI = { green: '\uD83D\uDFE9', yellow: '\uD83D\uDFE8', gray: '\u2B1B' };

	function shareText(steps, skill, luck) {
		var solved = steps[steps.length - 1].solved;
		var lines = ['WordMivo Wordle analysis: ' + (solved ? steps.length : 'X') + '/6', 'Skill ' + skill + '/99 \u00B7 Luck ' + luck + '/99'];
		steps.forEach(function (st) { lines.push(st.colours.map(function (c) { return EMOJI[c]; }).join('') + ' S' + st.skill + ' L' + st.luck); });
		return lines.join('\n');
	}

	/** A 1080x1080 result card drawn on a canvas (no letters, so no spoilers). */
	function resultImage(steps, skill, luck) {
		var c = document.createElement('canvas');
		c.width = 1080; c.height = 1080;
		var x = c.getContext('2d');
		x.fillStyle = '#0f172a'; x.fillRect(0, 0, 1080, 1080);
		x.fillStyle = '#ffffff'; x.font = 'bold 64px system-ui, sans-serif'; x.textAlign = 'center';
		x.fillText('My Wordle report card', 540, 130);
		x.font = 'bold 96px system-ui, sans-serif';
		x.fillStyle = '#22c55e'; x.fillText('Skill ' + skill, 300, 270);
		x.fillStyle = '#facc15'; x.fillText('Luck ' + luck, 780, 270);
		var colours = { green: '#16a34a', yellow: '#ca8a04', gray: '#475569' };
		var size = 92, gap = 14, left = (1080 - (5 * size + 4 * gap)) / 2 - 90;
		steps.forEach(function (st, r) {
			var y = 340 + r * (size + gap);
			st.colours.forEach(function (col, i) { x.fillStyle = colours[col]; x.fillRect(left + i * (size + gap), y, size, size); });
			x.fillStyle = '#e2e8f0'; x.font = 'bold 40px system-ui, sans-serif'; x.textAlign = 'left';
			x.fillText(st.skill + ' / ' + st.luck, left + 5 * (size + gap) + 20, y + 62);
		});
		var solved = steps[steps.length - 1].solved;
		x.textAlign = 'center'; x.fillStyle = '#ffffff'; x.font = 'bold 56px system-ui, sans-serif';
		x.fillText(solved ? 'Solved in ' + steps.length + '/6' : 'Not solved', 540, 340 + steps.length * (size + gap) + 90);
		x.fillStyle = '#94a3b8'; x.font = '32px system-ui, sans-serif';
		x.fillText('Skill / luck for each guess', 540, 340 + steps.length * (size + gap) + 145);
		x.textAlign = 'center'; x.fillStyle = '#a5b4fc'; x.font = 'bold 48px system-ui, sans-serif';
		x.fillText('wordmivo.com/wordle-analyzer', 540, 1030);
		return c;
	}

	function initAnalyzer(form) {
		var out = form.querySelector('.wm-results');
		function run() {
			var guesses = wordsIn(form.guesses.value);
			var answer = lettersOnly(form.answer.value);
			if (!answer && guesses.length) { answer = guesses[guesses.length - 1]; }
			if (!guesses.length || answer.length !== 5) {
				message(out, 'Type at least one five-letter guess and the answer.');
				return;
			}
			setParams({ s: rot13(guesses.join('.') + '-' + answer) });
			message(out, 'Analyzing...');
			loadWords(form.dataset.src).then(function (words) {
				return new Promise(function (resolve) { setTimeout(function () { resolve(words); }, 30); });
			}).then(function (words) {
				var steps = analyzeGame(words, guesses, answer);
				out.textContent = '';
				var skill = 0, luck = 0;
				steps.forEach(function (st, i) {
					var card = el('div', 'wm-step');
					card.appendChild(tileRow(st.guess, st.colours));
					var p = el('p', 'wm-step-text');
					p.appendChild(el('strong', '', 'Guess ' + (i + 1) + ': '));
					p.appendChild(document.createTextNode(st.solved ? 'Solved! ' : st.before + ' possible answers before, ' + st.after + ' after. '));
					card.appendChild(p);
					var m = el('p', 'wm-scores');
					m.appendChild(el('span', 'wm-score', 'Skill ' + st.skill + '/99'));
					m.appendChild(el('span', 'wm-score', 'Luck ' + st.luck + '/99'));
					card.appendChild(m);
					if (!st.solved && st.best !== st.guess) {
						card.appendChild(el('p', 'wm-note', 'Our pick here: ' + st.best.toUpperCase()));
					}
					out.appendChild(card);
					skill += st.skill;
					luck += st.luck;
				});
				var sum = el('p', 'wm-count', 'Overall: skill ' + Math.round(skill / steps.length) + '/99, luck ' + Math.round(luck / steps.length) + '/99' + (steps[steps.length - 1].solved ? ', solved in ' + steps.length + '.' : '.'));
				out.insertBefore(sum, out.firstChild);
				var avgSkill = Math.round(skill / steps.length), avgLuck = Math.round(luck / steps.length);
				var bar = el('div', 'wm-actions');
				var shareBtn = el('button', 'wm-btn', 'Share my result');
				shareBtn.type = 'button';
				var imgBtn = el('button', 'wm-btn wm-btn-ghost', 'Save image');
				imgBtn.type = 'button';
				var note = el('span', 'wm-copied');
				bar.appendChild(shareBtn); bar.appendChild(imgBtn); bar.appendChild(note);
				out.insertBefore(bar, out.children[1]);
				var text = shareText(steps, avgSkill, avgLuck);
				shareBtn.addEventListener('click', function () {
					var data = { title: 'My Wordle analysis', text: text, url: location.href };
					if (navigator.share) {
						navigator.share(data).catch(function () {});
					} else if (navigator.clipboard) {
						navigator.clipboard.writeText(text + '\n' + location.href).then(function () { note.textContent = 'Copied, paste it anywhere'; });
					}
				});
				imgBtn.addEventListener('click', function () {
					resultImage(steps, avgSkill, avgLuck).toBlob(function (blob) {
						var file = new File([blob], 'wordle-report-card.png', { type: 'image/png' });
						if (navigator.canShare && navigator.canShare({ files: [file] })) {
							navigator.share({ files: [file], text: text, url: location.href }).catch(function () {});
							return;
						}
						var a = document.createElement('a');
						a.href = URL.createObjectURL(blob);
						a.download = 'wordle-report-card.png';
						a.click();
						setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
					});
				});
				out.appendChild(el('p', 'wm-note', 'Skill compares how many answers your guess would leave on average with the best guess we found. Luck shows how your result compares with the other possible answers.'));
			}).catch(function () {
				message(out, 'The word list is not available yet. Please try again later.');
			});
		}
		form.addEventListener('submit', function (e) { e.preventDefault(); run(); });
		form.addEventListener('focusin', function () { loadWords(form.dataset.src).catch(function () {}); }, { once: true });
		copyLinkButton(form);
		var p = params();
		var shared = p && p.get('s') ? rot13((p.get('s') || '').toLowerCase()).split('-') : null;
		if (shared || (p && p.get('g'))) {
			var g = shared ? shared[0] : p.get('g');
			form.guesses.value = (g || '').split('.').filter(function (w) { return /^[a-z]{5}$/.test(w); }).join(' ');
			form.answer.value = lettersOnly(shared ? shared[1] : p.get('a')).slice(0, 5);
			run();
		}
	}

	function initPuzzle(form) {
		var out = form.querySelector('.wm-results');
		var mode = form.dataset.mode;
		function value() {
			if (mode === 'bee') { return lettersOnly(form.center.value).slice(0, 1) + lettersOnly(form.outer.value).slice(0, 6); }
			return [0, 1, 2, 3].map(function (i) { return lettersOnly(form['s' + i].value).slice(0, 3); }).join(',');
		}
		function fill(v) {
			if (mode === 'bee') {
				form.center.value = v.slice(0, 1);
				form.outer.value = v.slice(1, 7);
			} else {
				v.split(',').forEach(function (side, i) { if (form['s' + i]) { form['s' + i].value = side; } });
			}
		}
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var letters = value();
			var plain = letters.replace(/,/g, '');
			var need = mode === 'bee' ? 7 : 12;
			var unique = plain.split('').filter(function (l, i) { return plain.indexOf(l) === i; });
			if (plain.length !== need || unique.length !== need) {
				message(out, 'Enter ' + need + ' different letters.');
				return;
			}
			setParams({ letters: letters });
			message(out, 'Searching...');
			fetch(window.wmConfig.rest + '?mode=' + mode + '&letters=' + encodeURIComponent(letters)).then(function (r) {
				return r.json().then(function (j) { return [r.ok, j]; });
			}).then(function (res) {
				if (!res[0]) { message(out, res[1].message || 'Something went wrong.'); return; }
				var d = res[1];
				out.textContent = '';
				if (mode === 'bee') {
					var pangrams = d.words.filter(function (w) { return w[2]; });
					var total = d.words.reduce(function (t, w) { return t + w[1]; }, 0);
					out.appendChild(el('p', 'wm-count', d.count + ' words, ' + pangrams.length + ' pangram' + (pangrams.length === 1 ? '' : 's') + ', ' + total + ' points in all'));
					if (pangrams.length) {
						out.appendChild(el('h3', '', 'Pangrams'));
						renderList(out, pangrams, 50);
					}
					var groups = {};
					d.words.filter(function (w) { return !w[2]; }).forEach(function (w) { (groups[w[0].length] = groups[w[0].length] || []).push(w); });
					Object.keys(groups).sort(function (a, b) { return b - a; }).forEach(function (len) {
						out.appendChild(el('h3', '', len + ' letters'));
						renderList(out, groups[len], 200);
					});
					out.appendChild(el('p', 'wm-note', 'Numbers show Spelling Bee points. The puzzle uses its own word list, so a few words here may not be accepted and a few accepted words may be missing.'));
				} else {
					if (d.solutions.length) {
						out.appendChild(el('h3', '', 'Solutions'));
						var ol = el('ol', 'wm-solutions');
						d.solutions.forEach(function (s) { ol.appendChild(el('li', '', s.join(' → ').toUpperCase())); });
						out.appendChild(ol);
					} else {
						out.appendChild(el('p', 'wm-note', 'No one- or two-word solution found with our word list.'));
					}
					out.appendChild(el('h3', '', d.count + ' playable words'));
					renderList(out, d.words, 200);
					out.appendChild(el('p', 'wm-note', 'Numbers show how many of the 12 letters each word uses.'));
				}
			}).catch(function () { message(out, 'Network error. Please try again.'); });
		});
		copyLinkButton(form);
		var p = params();
		var saved = p ? (p.get('letters') || '').toLowerCase().replace(/[^a-z,]/g, '') : '';
		if (saved) {
			fill(saved);
			form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
		}
	}

	function initMulti(wrap) {
		var boardsBox = wrap.querySelector('.wm-boards');
		var best = wrap.querySelector('.wm-best-multi');
		var select = wrap.querySelector('select[name="boards"]');
		var input = wrap.querySelector('input[name="guess"]');
		var guesses = [];
		var colours = []; // colours[board][guess] = ['gray', ...]

		function nBoards() { return +select.value; }
		function solvedAt(b) {
			for (var g = 0; g < guesses.length; g++) {
				if (colours[b][g].every(function (c) { return c === 'green'; })) { return g; }
			}
			return -1;
		}
		function share() {
			var codes = { gray: '0', yellow: '1', green: '2' };
			setParams(guesses.length ? {
				b: String(nBoards()),
				g: guesses.join('.'),
				c: colours.slice(0, nBoards()).map(function (bc) { return bc.map(function (r) { return r.map(function (c) { return codes[c]; }).join(''); }).join(''); }).join('-')
			} : {});
		}
		function render() {
			while (colours.length < 8) { colours.push([]); }
			colours.forEach(function (bc) { while (bc.length < guesses.length) { bc.push(['gray', 'gray', 'gray', 'gray', 'gray']); } bc.length = guesses.length; });
			boardsBox.textContent = '';
			boardsBox.className = 'wm-boards wm-boards-' + nBoards();
			for (var b = 0; b < nBoards(); b++) {
				var board = el('div', 'wm-board');
				var done = solvedAt(b);
				board.appendChild(el('p', 'wm-board-title', 'Board ' + (b + 1) + (done !== -1 ? ': solved' : '')));
				if (done !== -1) { board.classList.add('wm-board-done'); }
				guesses.forEach(function (g, gi) {
					if (done !== -1 && gi > done) { return; }
					var row = el('div', 'wm-guess');
					for (var i = 0; i < 5; i++) {
						var t = el('button', 'wm-tile', g[i].toUpperCase());
						t.type = 'button';
						t.dataset.state = colours[b][gi][i];
						t.setAttribute('aria-label', 'Board ' + (b + 1) + ', guess ' + (gi + 1) + ', letter ' + (i + 1) + ', ' + colours[b][gi][i]);
						(function (bb, gg, ii) {
							t.addEventListener('click', function () {
								colours[bb][gg][ii] = STATES[(STATES.indexOf(colours[bb][gg][ii]) + 1) % 3];
								render();
								var again = boardsBox.querySelectorAll('.wm-board')[bb];
								var tile = again && again.querySelectorAll('.wm-guess')[gg];
								if (tile) { tile.children[ii].focus(); }
							});
						})(b, gi, i);
						row.appendChild(t);
					}
					board.appendChild(row);
				});
				board.appendChild(el('div', 'wm-board-words'));
				boardsBox.appendChild(board);
			}
			share();
			solve();
		}
		function solve() {
			if (!guesses.length) { best.textContent = ''; return; }
			loadWords(wrap.dataset.src).then(function (words) {
				var likelyAll = words.filter(function (w) { return w[3] & LIKELY; }).map(function (w) { return w[0]; });
				var sets = [];
				var boards = boardsBox.querySelectorAll('.wm-board');
				for (var b = 0; b < nBoards(); b++) {
					var box = boards[b].querySelector('.wm-board-words');
					box.textContent = '';
					if (solvedAt(b) !== -1) { sets.push([]); continue; }
					var rows = guesses.map(function (g, gi) { return [g, colours[b][gi]]; });
					var cands = wordleCandidates(words, rows);
					var likely = cands.filter(function (w) { return w[3] & LIKELY; });
					var show = likely.length ? likely : cands;
					sets.push(show.map(function (w) { return w[0]; }));
					box.appendChild(el('p', 'wm-count', show.length + ' possible'));
					renderList(box, show, 12);
				}
				var picks = bestMulti(sets, likelyAll, 3);
				best.textContent = '';
				if (picks.length) {
					best.appendChild(el('p', 'wm-best', 'Best next guess: ' + picks.map(function (w) { return w.toUpperCase(); }).join(', ')));
				}
			}).catch(function () { best.textContent = 'The word list is not available yet.'; });
		}
		function add() {
			var g = lettersOnly(input.value);
			if (g.length !== 5 || guesses.length >= (nBoards() === 8 ? 13 : 9)) { input.focus(); return; }
			guesses.push(g);
			input.value = '';
			render();
			input.focus();
		}
		wrap.querySelector('[data-action="add"]').addEventListener('click', add);
		input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); add(); } });
		wrap.querySelector('[data-action="undo"]').addEventListener('click', function () { guesses.pop(); render(); });
		select.addEventListener('change', render);
		wrap.addEventListener('focusin', function () { loadWords(wrap.dataset.src).catch(function () {}); }, { once: true });
		copyLinkButton(wrap);

		var p = params();
		if (p && p.get('g')) {
			var names = ['gray', 'yellow', 'green'];
			if (p.get('b') === '8') { select.value = '8'; }
			guesses = (p.get('g') || '').split('.').filter(function (w) { return /^[a-z]{5}$/.test(w); }).slice(0, 13);
			(p.get('c') || '').split('-').slice(0, 8).forEach(function (s, b) {
				colours[b] = guesses.map(function (g, gi) {
					var part = s.slice(gi * 5, gi * 5 + 5);
					return /^[012]{5}$/.test(part) ? part.split('').map(function (d) { return names[+d]; }) : ['gray', 'gray', 'gray', 'gray', 'gray'];
				});
			});
		}
		render();
	}

	function initClue(form) {
		var out = form.querySelector('.wm-results');
		function pattern() {
			var v = (form.pattern.value || '').toLowerCase().trim();
			if (/^\d{1,2}$/.test(v)) { return new Array(Math.min(15, +v) + 1).join('?'); }
			return v.replace(/[_.\s-]/g, '?').replace(/[^a-z?]/g, '');
		}
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var p = pattern();
			var clue = (form.clue.value || '').trim().slice(0, 120);
			if (p.length < 2) { message(out, 'Type the answer length (e.g. 5) or a pattern like c???e.'); return; }
			setParams({ clue: clue, p: p });
			message(out, 'Searching...');
			fetch(window.wmConfig.rest + '?mode=clue&letters=' + encodeURIComponent(p) + '&clue=' + encodeURIComponent(clue)).then(function (r) {
				return r.json().then(function (j) { return [r.ok, j]; });
			}).then(function (res) {
				if (!res[0]) { message(out, res[1].message || 'Something went wrong.'); return; }
				var words = res[1].words;
				out.textContent = '';
				out.appendChild(el('p', 'wm-count', words.length ? words.length + ' possible answer' + (words.length === 1 ? '' : 's') : 'No match. Try fewer clue words or check the length.'));
				var ol = el('ol', 'wm-answers');
				words.forEach(function (w) {
					var li = el('li');
					var a = el('a', '', w[0].toUpperCase());
					a.href = form.dataset.words + w[0] + '/';
					li.appendChild(a);
					if (w[1]) { li.appendChild(el('span', 'wm-note', ' ' + w[1])); }
					ol.appendChild(li);
				});
				out.appendChild(ol);
			}).catch(function () { message(out, 'Network error. Please try again.'); });
		});
		copyLinkButton(form);
		var p = params();
		if (p && p.get('p')) {
			form.clue.value = (p.get('clue') || '').slice(0, 120);
			form.pattern.value = (p.get('p') || '').toLowerCase().replace(/[^a-z?]/g, '').slice(0, 15);
			form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
		}
	}

	document.querySelectorAll('[data-wm="analyzer"]').forEach(initAnalyzer);
	document.querySelectorAll('[data-wm="clue"]').forEach(initClue);
	document.querySelectorAll('[data-wm="puzzle"]').forEach(initPuzzle);
	document.querySelectorAll('[data-wm="multi"]').forEach(initMulti);
	document.querySelectorAll('[data-wm="finder"]').forEach(initFinder);
	document.querySelectorAll('[data-wm="wordle"]').forEach(initWordle);
	document.querySelectorAll('[data-wm="rack"]').forEach(initRack);
})(this);
