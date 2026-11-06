import { HashRouter, Navigate, Route, Routes } from 'react-router-dom';

import { AppLayout } from '@/components/app-layout';
import { EventsPage } from '@/pages/events-page';
import { PlaceholderPage } from '@/pages/placeholder-page';
import { BootstrapProvider, useBootstrap } from '@/lib/bootstrap-context';
import type { BootstrapData } from '@/lib/data';

function RoutedPages() {
	const data = useBootstrap();

	return (
		<Routes>
			<Route element={<AppLayout />}>
				<Route index element={<Navigate to={`/${data.currentView}`} replace />} />
				<Route path="events" element={<EventsPage />} />
				{data.nav
					.filter((item) => item.key !== 'events')
					.map((item) => (
						<Route
							key={item.key}
							path={item.key}
							element={<PlaceholderPage title={item.label} />}
						/>
					))}
				<Route path="*" element={<Navigate to={`/${data.currentView}`} replace />} />
			</Route>
		</Routes>
	);
}

export function App({ data }: { data: BootstrapData }) {
	return (
		<BootstrapProvider data={data}>
			<HashRouter>
				<RoutedPages />
			</HashRouter>
		</BootstrapProvider>
	);
}
