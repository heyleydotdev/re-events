import { type ReactNode, useEffect } from 'react';

import { cn } from '@/lib/utils';
import { useBootstrap } from '@/lib/bootstrap-context';

export function AppLayout({ children }: { children: ReactNode }) {
	const data = useBootstrap();
	const current = data.nav.find((item) => item.key === data.currentView) ?? data.nav[0];

	useEffect(() => {
		document.title = `${current.label} ‹ ReEvents`;
	}, [current.label]);

	return (
		<>
			<header className="sticky top-(--wp-admin--admin-bar--height) z-50 border-b bg-background backdrop-blur supports-backdrop-filter:bg-background/50">
				<div className="container flex h-12 items-center gap-8">
					<a href={data.homeUrl} className="text-base font-semibold">
						ReEvents
					</a>
					<nav className="flex flex-1 items-center justify-end gap-1 text-sm">
						{data.nav.map((item) => {
							const isActive = item.key === data.currentView;

							return (
								<a
									key={item.key}
									href={item.url}
									aria-current={isActive ? 'page' : undefined}
									className={cn(
										'rounded-md px-3 py-1.5 font-medium transition-colors',
										isActive
											? 'text-secondary-foreground'
											: 'text-muted-foreground hover:text-accent-foreground',
									)}
								>
									{item.label}
								</a>
							);
						})}
					</nav>
				</div>
			</header>

			<main className="container pt-6 pb-20">{children}</main>
		</>
	);
}
