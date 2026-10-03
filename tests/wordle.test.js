// Run: node tests/wordle.test.js
const assert = require('assert');
const { checkGuess, finderFilter, wordleCandidates } = require('../assets/js/wordmivo.js');

const cases = [
	['speed', 'abide', ['gray', 'gray', 'yellow', 'gray', 'yellow']],
	['eerie', 'there', ['yellow', 'gray', 'yellow', 'gray', 'green']],
	['allee', 'lever', ['gray', 'yellow', 'gray', 'green', 'yellow']],
	['crane', 'crane', ['green', 'green', 'green', 'green', 'green']],
	['geese', 'eagle', ['yellow', 'yellow', 'gray', 'gray', 'green']],
	['loops', 'pools', ['yellow', 'green', 'green', 'yellow', 'green']],
];
let passed = 0;
for (const [guess, answer, want] of cases) {
	assert.deepStrictEqual(checkGuess(guess, answer), want, `${guess} vs ${answer}`);
	passed++;
}

// Finder: gray letter that is also green elsewhere must not exclude the word.
const f = finderFilter(['', '', '', '', 'e'], 'a', 'e');
assert.strictEqual(f('crate'), true);
assert.strictEqual(f('there'), false); // no 'a'
assert.strictEqual(finderFilter(['', '', '', '', ''], 'ee', '')('there'), true);
assert.strictEqual(finderFilter(['', '', '', '', ''], 'ee', '')('crane'), false);
passed += 4;

// Solver keeps only words consistent with the feedback.
const words = [['there', 1, 1], ['three', 1, 2], ['eerie', 1, 0], ['crane', 1, 3]];
const fb = checkGuess('eerie', 'there');
assert.deepStrictEqual(wordleCandidates(words, [['eerie', fb]]).map((w) => w[0]), ['there']);
passed++;

console.log(`wordle tests: ${passed} passed`);

// Yellow letters: present, but not at the given positions.
const y = require('../assets/js/wordmivo.js').finderFilter(['', '', '', '', ''], '', '', ['', 'r', '', '', '']);
assert.strictEqual(y('crane'), false); // r at position 2
assert.strictEqual(y('rates'), true);
assert.strictEqual(y('plate'), false); // no r

// Share-link encoding round trip.
const { encodeGuesses, decodeGuesses, bestGuesses } = require('../assets/js/wordmivo.js');
const rows = [['crane', ['gray', 'green', 'yellow', 'gray', 'green']]];
assert.strictEqual(encodeGuesses(rows), 'crane02102');
assert.deepStrictEqual(decodeGuesses('crane02102.bad'), rows);

// Best guess splits the answers; with 2 answers it returns them.
assert.deepStrictEqual(bestGuesses(['crane', 'crate'], ['crane', 'crate'], 5), ['crane', 'crate']);
// A word in the pool twice (likely answers + candidates) is suggested once.
{
	const answers = ['lotus', 'sloth', 'lousy', 'south', 'mouth', 'youth'];
	const picks = bestGuesses(answers, answers.concat(answers), 5);
	assert.strictEqual(new Set(picks).size, picks.length);
}
console.log('extra tests passed');

// Game analyzer: solving in one is perfect skill; steps carry before/after counts.
{
	const W = require('../assets/js/wordmivo.js');
	const words = [['crane', 1, 1, 3], ['crate', 1, 2, 3], ['trace', 1, 3, 3], ['slate', 1, 4, 3], ['pious', 1, 5, 3]];
	const steps = W.analyzeGame(words, ['crane', 'pious'], 'pious');
	assert.strictEqual(steps.length, 2);
	assert.strictEqual(steps[1].solved, true);
	assert.strictEqual(steps[0].after, 1); // only pious fits all-gray CRANE
	assert.ok(steps[0].skill > 0 && steps[0].skill <= 99);
	// Multi-board: a board with one answer left is finished first.
	assert.deepStrictEqual(W.bestMulti([['crane'], ['slate', 'crate']], ['trace'], 1), ['crane']);
	assert.strictEqual(W.expectedLeft('crane', ['crane']), 0);
	console.log('analyzer tests passed');
}
