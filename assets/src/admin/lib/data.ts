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
	restUrl: string;
	nonce: string;
	canManage: boolean;
};

export function readBootstrapData(): BootstrapData | null {
	const node = document.getElementById('re-events-data');

	if (!node?.textContent) {
		return null;
	}

	try {
		return JSON.parse(node.textContent) as BootstrapData;
	} catch {
		return null;
	}
}
