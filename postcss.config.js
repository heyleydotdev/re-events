/**
 * Shadow DOM CSS transform.
 *
 * The CSS is injected into a shadow root, so document-level selectors become
 * :host. Bare [data-bs-theme] selectors are emitted for both the host and
 * nested elements so component-level theming keeps working.
 */

/**
 * Rewrites one selector for use inside a shadow root.
 *
 * @param {string} selector CSS selector.
 * @return {string[]} Rewritten selectors.
 */
export const rewriteSelector = (selector) => {
	const next = selector.trim();

	if (!next) {
		return [next];
	}

	const leadingTheme = next.match(/^(html|body|:root)(\[data-bs-theme[^\]]*\])(.*)$/);

	if (leadingTheme) {
		return [`:host(${leadingTheme[2]})${leadingTheme[3]}`];
	}

	const themeMatch = next.match(/^\[data-bs-theme([^\]]*)\](.*)$/);

	if (themeMatch) {
		const attribute = `[data-bs-theme${themeMatch[1]}]`;

		return [`:host(${attribute})${themeMatch[2]}`, `${attribute}${themeMatch[2]}`];
	}

	for (const globalSelector of [
		'html body',
		':root body',
		':host body',
		':root',
		':host',
		'html',
		'body',
	]) {
		if (next === globalSelector) {
			return [':host'];
		}

		if (next.startsWith(`${globalSelector} `)) {
			return [`:host${next.slice(globalSelector.length)}`];
		}
	}

	return [next];
};

const shadowScope = () => ({
	postcssPlugin: 'shadow-scope',
	OnceExit(root) {
		root.walkRules((rule) => {
			if (rule.parent && rule.parent.type === 'atrule' && /keyframes$/i.test(rule.parent.name)) {
				return;
			}

			rule.selectors = [...new Set(rule.selectors.flatMap(rewriteSelector))];
		});
	},
});

export default {
	plugins: [shadowScope()],
};
