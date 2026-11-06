import Drawer from 'bootstrap/js/dist/drawer.js';
import Menu from 'bootstrap/js/dist/menu.js';

const FONT_FACE_PATTERN = /@font-face\s*{[^}]*}/g;

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
	style.textContent = css;
	shadow.append(style);
}

function initComponents(root: ShadowRoot): void {
	root.addEventListener('click', (event) => {
		if (!(event.target instanceof Element)) {
			return;
		}

		Menu.clearMenus(event);

		const path = event.composedPath();
		const dismiss = path.find(
			(node): node is Element =>
				node instanceof Element && node.matches('[data-bs-dismiss="drawer"]'),
		);

		if (dismiss) {
			const dialog = dismiss.closest('dialog');

			if (dialog) {
				Drawer.getOrCreateInstance(dialog).hide();
			}

			return;
		}

		const toggle = path.find(
			(node): node is Element =>
				node instanceof Element &&
				node.matches('[data-bs-toggle="drawer"], [data-bs-toggle="menu"]'),
		);

		if (!toggle) {
			return;
		}

		if (toggle.matches('[data-bs-toggle="drawer"]')) {
			const selector = toggle.getAttribute('data-bs-target');
			const target = selector ? root.querySelector(selector) : null;

			if (target) {
				Drawer.getOrCreateInstance(target).toggle();
			}

			return;
		}

		event.preventDefault();
		Menu.getOrCreateInstance(toggle).toggle();
	});
}

async function mount(): Promise<void> {
	const host = document.getElementById('re-events-host');

	if (!host) {
		return;
	}

	await injectDevFonts();

	let shadow = host.shadowRoot;

	if (!shadow) {
		const template = host.querySelector('template');

		if (!template) {
			return;
		}

		shadow = host.attachShadow({ mode: 'open' });

		if (!template.content.querySelector('style, link[rel="stylesheet"]')) {
			await injectDevCss(shadow);
		}

		shadow.append(template.content.cloneNode(true));
		template.remove();
	}

	host.querySelector('#re-events-loading')?.remove();

	initComponents(shadow);
}

mount();
