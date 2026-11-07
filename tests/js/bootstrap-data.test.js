import { describe, expect, test } from 'bun:test';

import { parseBootstrapData } from '../../assets/src/admin/lib/data.ts';

const valid = {
	currentView: 'events',
	homeUrl: 'https://example.test/wp-admin/admin.php?page=re-events',
	nav: [
		{
			key: 'events',
			label: 'Events',
			url: 'https://example.test/wp-admin/admin.php?page=re-events',
		},
	],
	events: [
		{
			id: 1,
			title: 'WordPress Meetup',
			date: 'Nov 12, 2026',
			venue: 'Berlin',
			status: 'Published',
			tone: 'success',
		},
	],
	pagination: {
		current: 1,
		total: 1,
		summary: 'Showing 1 of 1 events',
	},
};

describe('parseBootstrapData', () => {
	test('accepts a complete payload', () => {
		expect(parseBootstrapData(JSON.stringify(valid))).toEqual(valid);
	});

	test('rejects missing sections', () => {
		expect(parseBootstrapData(JSON.stringify({ ...valid, nav: undefined }))).toBeNull();
		expect(parseBootstrapData(JSON.stringify({ ...valid, events: undefined }))).toBeNull();
		expect(parseBootstrapData(JSON.stringify({ ...valid, pagination: undefined }))).toBeNull();
	});

	test('rejects malformed nav items', () => {
		expect(parseBootstrapData(JSON.stringify({ ...valid, nav: [{ key: 'events' }] }))).toBeNull();
	});

	test('rejects event fields the UI dereferences', () => {
		expect(
			parseBootstrapData(
				JSON.stringify({ ...valid, events: [{ ...valid.events[0], title: null }] }),
			),
		).toBeNull();
		expect(
			parseBootstrapData(
				JSON.stringify({ ...valid, events: [{ ...valid.events[0], tone: 'unknown' }] }),
			),
		).toBeNull();
	});

	test('rejects non-numeric pagination', () => {
		expect(
			parseBootstrapData(
				JSON.stringify({ ...valid, pagination: { ...valid.pagination, total: '1' } }),
			),
		).toBeNull();
	});

	test('rejects invalid JSON', () => {
		expect(parseBootstrapData('{')).toBeNull();
	});
});
