import { createRoot } from 'react-dom/client';

import { App } from './app';
import { readBootstrapData } from './lib/data';

const FONT_FACE_PATTERN = /@font-face\s*{[^}]*}/g;
const PROPERTY_PATTERN = /@property\s+[^{]+\{[^}]*}/g;
const FONT_TIMEOUT = 2500;

async function injectDevFonts(): Promise<void> {
	if (!import.meta.env.DEV || document.getElementById('re-events-fonts')) {
		return;
	}

	const { default: css } = await import('@fontsource-variable/geist/wght.css?inline');
	const faces = css.match(FONT_FACE_PATTERN)?.join('\n') ?? '';

	if (!faces) {
		return;
	}

	const style = document.createElement('style');
	style.id = 're-events-fonts';
	style.textContent = faces;
	document.head.append(style);
}

async function injectDevCss(shadow: ShadowRoot): Promise<void> {
	if (!import.meta.env.DEV) {
		return;
	}

	const { default: css } = await import('./app.css?inline');
	const style = document.createElement('style');
	style.dataset.reeventsApp = 'true';
	style.textContent = css;
	shadow.prepend(style);

	// Chromium ignores @property inside a shadow root, and Tailwind utilities
	// rely on the registrations.
	const properties = css.match(PROPERTY_PATTERN)?.join('\n') ?? '';

	if (properties && !document.getElementById('re-events-properties')) {
		const head = document.createElement('style');
		head.id = 're-events-properties';
		head.textContent = properties;
		document.head.append(head);
	}
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
	});
}

async function mount(): Promise<void> {
	const host = document.getElementById('re-events-host');
	const data = readBootstrapData();

	if (!host || !data) {
		return;
	}

	let shadow = host.shadowRoot;

	if (!shadow) {
		const template = host.querySelector<HTMLTemplateElement>('#re-events-template');

		if (!template) {
			return;
		}

		shadow = host.attachShadow({ mode: 'open' });
		shadow.append(template.content.cloneNode(true));
		template.remove();
	}

	const rootElement = shadow.querySelector<HTMLElement>('#re-events-root');
	const loader = shadow.querySelector<HTMLElement>('#re-events-loading');

	if (!rootElement) {
		return;
	}

	try {
		await injectDevFonts();

		if (!shadow.querySelector('style[data-reevents-app], link[rel="stylesheet"]')) {
			await injectDevCss(shadow);
		}

		createRoot(rootElement).render(<App data={data} />);

		await waitForFonts();
		await nextPaint();

		loader?.remove();
		rootElement.style.opacity = '1';
		rootElement.removeAttribute('aria-busy');
	} catch (error) {
		console.error('[ReEvents] admin mount failed', error);

		if (loader) {
			loader.textContent = 'ReEvents failed to load. Check the browser console.';
		}
	}
}

mount();
