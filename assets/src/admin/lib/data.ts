export type NavItem = {
	key: string;
	label: string;
	url: string;
};

export type EventRow = {
	id: number;
	title: string;
	date: string;
	venue: string;
	status: string;
	tone: 'success' | 'warning' | 'neutral' | 'danger';
};

export type Pagination = {
	current: number;
	total: number;
	summary: string;
};

export type BootstrapData = {
	currentView: string;
	homeUrl: string;
	nav: NavItem[];
	events: EventRow[];
	pagination: Pagination;
};

const TONES = ['success', 'warning', 'neutral', 'danger'] as const;

function isString(value: unknown): value is string {
	return typeof value === 'string';
}

function isNumber(value: unknown): value is number {
	return typeof value === 'number';
}

function isNavItem(value: unknown): value is NavItem {
	if (!value || typeof value !== 'object') {
		return false;
	}

	const item = value as NavItem;
	return isString(item.key) && isString(item.label) && isString(item.url);
}

function isEventRow(value: unknown): value is EventRow {
	if (!value || typeof value !== 'object') {
		return false;
	}

	const event = value as EventRow;
	return (
		isNumber(event.id) &&
		isString(event.title) &&
		isString(event.date) &&
		isString(event.venue) &&
		isString(event.status) &&
		TONES.includes(event.tone)
	);
}

function isPagination(value: unknown): value is Pagination {
	if (!value || typeof value !== 'object') {
		return false;
	}

	const pagination = value as Pagination;
	return isNumber(pagination.current) && isNumber(pagination.total) && isString(pagination.summary);
}

export function parseBootstrapData(text: string): BootstrapData | null {
	try {
		const data = JSON.parse(text) as Partial<BootstrapData>;

		if (
			!data ||
			!isString(data.currentView) ||
			!isString(data.homeUrl) ||
			!Array.isArray(data.nav) ||
			!data.nav.every(isNavItem) ||
			!Array.isArray(data.events) ||
			!data.events.every(isEventRow) ||
			!isPagination(data.pagination)
		) {
			return null;
		}

		return data as BootstrapData;
	} catch {
		return null;
	}
}

export function readBootstrapData(): BootstrapData | null {
	const node = document.getElementById('re-events-data');

	if (!node?.textContent) {
		return null;
	}

	return parseBootstrapData(node.textContent);
}
