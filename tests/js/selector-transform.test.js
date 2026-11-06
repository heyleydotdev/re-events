import { describe, expect, test } from 'bun:test';
import { rewriteSelector } from '../../postcss.config.js';

describe('rewriteSelector', () => {
	test('maps global selectors to :host', () => {
		expect(rewriteSelector(':root')).toEqual([':host']);
		expect(rewriteSelector(':host')).toEqual([':host']);
		expect(rewriteSelector('html')).toEqual([':host']);
		expect(rewriteSelector('body')).toEqual([':host']);
		expect(rewriteSelector('html body')).toEqual([':host']);
		expect(rewriteSelector(':root body')).toEqual([':host']);
	});

	test('maps global selector prefixes to :host', () => {
		expect(rewriteSelector('body .card')).toEqual([':host .card']);
		expect(rewriteSelector(':root body .card')).toEqual([':host .card']);
	});

	test('emits host and nested forms for theme attributes', () => {
		expect(rewriteSelector('[data-bs-theme=dark]')).toEqual([
			':host([data-bs-theme=dark])',
			'[data-bs-theme=dark]',
		]);

		expect(rewriteSelector('[data-bs-theme=light] .card')).toEqual([
			':host([data-bs-theme=light]) .card',
			'[data-bs-theme=light] .card',
		]);
	});

	test('maps themed html/body selectors only to host', () => {
		expect(rewriteSelector('html[data-bs-theme=dark] .card')).toEqual([
			':host([data-bs-theme=dark]) .card',
		]);
	});

	test('leaves normal selectors alone', () => {
		expect(rewriteSelector('.btn')).toEqual(['.btn']);
	});
});
