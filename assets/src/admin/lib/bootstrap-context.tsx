import { createContext, useContext, type ReactNode } from 'react';

import type { BootstrapData } from '@/lib/data';

const BootstrapContext = createContext<BootstrapData | null>(null);

export function BootstrapProvider({
	data,
	children,
}: {
	data: BootstrapData;
	children: ReactNode;
}) {
	return <BootstrapContext.Provider value={data}>{children}</BootstrapContext.Provider>;
}

export function useBootstrap(): BootstrapData {
	const data = useContext(BootstrapContext);

	if (!data) {
		throw new Error('ReEvents bootstrap data is missing.');
	}

	return data;
}
