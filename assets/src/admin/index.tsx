import { createRoot } from 'react-dom/client';

import { App } from './app';
import { readBootstrapData } from './lib/data';

const PROPERTY_PATTERN = /@property\s+[^{]+\{[^}]*}/g;
const FONT_TIMEOUT = 2500;

let appStyle: HTMLStyleElement | null = null;
let propertiesStyle: HTMLStyleElement | null = null;

function applyDevCss(css: string, shadow: ShadowRoot | null): void {
	if (!appStyle && shadow) {
		appStyle = document.createElement('style');
		shadow.append(appStyle);
	}

	if (appStyle) {
		appStyle.textContent = css;
	}

	// Chromium ignores @property inside a shadow root, and Tailwind utilities
	// rely on the registrations.
	const properties = css.match(PROPERTY_PATTERN)?.join('\n') ?? '';

	if (!propertiesStyle && properties) {
		propertiesStyle = document.createElement('style');
		document.head.append(propertiesStyle);
	}

	if (propertiesStyle) {
		propertiesStyle.textContent = properties;
	}
}

async function injectDevStyles(shadow: ShadowRoot): Promise<void> {
	if (!import.meta.env.DEV) {
		return;
	}

	const [{ default: fonts }, { default: css }] = await Promise.all([
		import('@fontsource-variable/geist/wght.css?inline'),
		import('./app.css?inline'),
	]);

	const fontStyle = document.createElement('style');
	fontStyle.textContent = fonts;
	document.head.append(fontStyle);

	applyDevCss(css, shadow);
}

function waitForFonts(): Promise<unknown> {
	if (!document.fonts) {
		return Promise.resolve();
	}

	return Promise.race([
		document.fonts.ready,
		new Promise((resolve) => window.setTimeout(resolve, FONT_TIMEOUT)),
	]);
}

function nextPaint(): Promise<void> {
	return new Promise((resolve) => {
		requestAnimationFrame(() => requestAnimationFrame(() => resolve()));
		window.setTimeout(resolve, 100);
	});
}

function setLoaderMessage(message: string): void {
	const loader = document
		.getElementById('re-events-host')
		?.shadowRoot?.querySelector<HTMLElement>('#re-events-loading');

	if (loader) {
		loader.textContent = message;
	}
}

async function mount(): Promise<void> {
	const host = document.getElementById('re-events-host');
	const data = readBootstrapData();

	if (!host || !data) {
		setLoaderMessage('ReEvents failed to load. Check the browser console.');
		return;
	}

	let shadow = host.shadowRoot;

	if (!shadow) {
		const template = host.querySelector<HTMLTemplateElement>('#re-events-template');

		if (!template) {
			setLoaderMessage('ReEvents failed to load. Check the browser console.');
			return;
		}

		shadow = host.attachShadow({ mode: 'open' });
		shadow.append(template.content.cloneNode(true));
		template.remove();
	}

	const rootElement = shadow.querySelector<HTMLElement>('#re-events-root');
	const loader = shadow.querySelector<HTMLElement>('#re-events-loading');

	if (!rootElement) {
		setLoaderMessage('ReEvents failed to load. Check the browser console.');
		return;
	}

	try {
		await injectDevStyles(shadow);

		createRoot(rootElement).render(<App data={data} />);

		await waitForFonts();
		await nextPaint();

		loader?.remove();
		rootElement.hidden = false;
		rootElement.removeAttribute('aria-busy');
	} catch (error) {
		console.error('[ReEvents] admin mount failed', error);
		setLoaderMessage('ReEvents failed to load. Check the browser console.');
	}
}

// ?inline CSS bypasses Vite's CSS HMR, so swap the shadow copies in place.
if (import.meta.hot) {
	import.meta.hot.accept('./app.css?inline', (module) => {
		const shadow = document.getElementById('re-events-host')?.shadowRoot ?? null;
		applyDevCss(module?.default ?? '', shadow);
	});
}

mount();
