import { AppLayout } from '@/components/app-layout';
import { EventsPage } from '@/pages/events-page';
import { PlaceholderPage } from '@/pages/placeholder-page';
import { BootstrapProvider, useBootstrap } from '@/lib/bootstrap-context';
import type { BootstrapData } from '@/lib/data';

function CurrentPage() {
	const data = useBootstrap();

	if (data.currentView === 'events') {
		return <EventsPage />;
	}

	const label = data.nav.find((item) => item.key === data.currentView)?.label ?? 'ReEvents';

	return <PlaceholderPage title={label} />;
}

export function App({ data }: { data: BootstrapData }) {
	return (
		<BootstrapProvider data={data}>
			<AppLayout>
				<CurrentPage />
			</AppLayout>
		</BootstrapProvider>
	);
}
