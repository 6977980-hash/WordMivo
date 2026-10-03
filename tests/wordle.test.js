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
