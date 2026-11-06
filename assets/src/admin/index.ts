const FONT_FACE_PATTERN = /@font-face\s*{[^}]*}/g;
const PROPERTY_PATTERN = /@property\s+[^{]+\{[^}]*}/g;

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

	// Chromium ignores @property inside a shadow root; Tailwind utilities need
	// the registrations at the document level.
	const properties = css.match(PROPERTY_PATTERN)?.join('\n') ?? '';

	if (properties && !document.getElementById('re-events-properties')) {
		const head = document.createElement('style');
		head.id = 're-events-properties';
		head.textContent = properties;
		document.head.append(head);
	}
}

type OpenMenu = {
	menu: HTMLElement;
	toggle: HTMLElement;
	parent: Node;
};

let openMenu: OpenMenu | null = null;

function closeMenu(): void {
	if (!openMenu) {
		return;
	}

	const { menu, toggle, parent } = openMenu;

	menu.classList.add('hidden');
	menu.style.position = '';
	menu.style.top = '';
	menu.style.left = '';
	parent.appendChild(menu);
	toggle.setAttribute('aria-expanded', 'false');
	openMenu = null;
}

// The table scrolls, so the menu is moved to the shadow root and pinned to
// the toggle with fixed coordinates to escape the overflow clip.
function showMenu(toggle: HTMLElement, menu: HTMLElement, root: ShadowRoot): void {
	closeMenu();

	const rect = toggle.getBoundingClientRect();
	const parent = menu.parentNode ?? root;

	root.append(menu);
	menu.classList.remove('hidden');
	menu.style.position = 'fixed';
	menu.style.top = `${Math.round(rect.bottom + 4)}px`;
	menu.style.left = `${Math.round(rect.right - menu.offsetWidth)}px`;
	toggle.setAttribute('aria-expanded', 'true');
	openMenu = { menu, toggle, parent };
}

function initDrawer(dialog: HTMLDialogElement, root: ShadowRoot): void {
	dialog.addEventListener('click', (event) => {
		if (event.target === dialog) {
			dialog.close();
		}
	});

	dialog.addEventListener('close', () => {
		for (const toggle of root.querySelectorAll<HTMLElement>(
			`[data-reevents-target="#${dialog.id}"]`,
		)) {
			toggle.setAttribute('aria-expanded', 'false');
		}
	});
}

function initComponents(root: ShadowRoot): void {
	for (const dialog of root.querySelectorAll<HTMLDialogElement>('dialog[data-reevents-drawer]')) {
		initDrawer(dialog, root);
	}

	root.addEventListener('click', (event) => {
		if (!(event.target instanceof Element)) {
			return;
		}

		const path = event.composedPath();

		const dismiss = path.find(
			(node): node is Element =>
				node instanceof Element && node.matches('[data-reevents-dismiss="drawer"]'),
		);

		if (dismiss) {
			dismiss.closest('dialog')?.close();
			return;
		}

		const toggle = path.find(
			(node): node is Element => node instanceof Element && node.matches('[data-reevents-toggle]'),
		);

		if (toggle instanceof HTMLElement) {
			const selector = toggle.getAttribute('data-reevents-target');
			const target = selector ? root.querySelector<HTMLElement>(selector) : null;

			if (!target) {
				return;
			}

			if (
				toggle.getAttribute('data-reevents-toggle') === 'drawer' &&
				target instanceof HTMLDialogElement
			) {
				if (target.open) {
					target.close();
				} else {
					target.showModal();
					toggle.setAttribute('aria-expanded', 'true');
				}

				return;
			}

			if (toggle.getAttribute('data-reevents-toggle') === 'menu') {
				if (openMenu?.toggle === toggle) {
					closeMenu();
				} else {
					showMenu(toggle, target, root);
				}

				return;
			}
		}

		if (openMenu) {
			if (path.includes(openMenu.menu) && !event.target.closest('a, button')) {
				return;
			}

			closeMenu();
		}
	});

	root.addEventListener('keydown', (event) => {
		if (event instanceof KeyboardEvent && event.key === 'Escape') {
			closeMenu();
		}
	});
}

async function mount(): Promise<void> {
	const host = document.getElementById('re-events-host');

	if (!host) {
		return;
	}

	await injectDevFonts().catch((error: unknown) =>
		console.warn('[ReEvents] dev font injection failed', error),
	);

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

mount().catch((error: unknown) => {
	console.error('[ReEvents] admin mount failed', error);

	const loader = document.getElementById('re-events-loading');

	if (loader) {
		loader.textContent = 'ReEvents failed to load. Check the browser console.';
	}
});
