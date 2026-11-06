import { useEffect } from 'react';
import { NavLink, Outlet, useLocation } from 'react-router-dom';

import { cn } from '@/lib/utils';
import { useBootstrap } from '@/lib/bootstrap-context';

export function AppLayout() {
	const data = useBootstrap();
	const location = useLocation();
	const current = data.nav.find((item) => location.pathname === `/${item.key}`) ?? data.nav[0];

	useEffect(() => {
		document.title = `${current.label} ‹ ReEvents`;
	}, [current.label]);

	return (
		<div className="min-h-[calc(100svh-var(--wp-admin--admin-bar--height))] bg-background">
			<header className="sticky top-0 z-10 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
				<div className="mx-auto flex h-14 max-w-6xl items-center gap-8 px-6">
					<NavLink to={`/${data.currentView}`} className="text-sm font-semibold tracking-tight">
						ReEvents
					</NavLink>
					<nav className="flex items-center gap-1 text-sm">
						{data.nav.map((item) => (
							<NavLink
								key={item.key}
								to={`/${item.key}`}
								className={({ isActive }) =>
									cn(
										'rounded-md px-3 py-1.5 font-medium transition-colors',
										isActive
											? 'bg-secondary text-secondary-foreground'
											: 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
									)
								}
							>
								{item.label}
							</NavLink>
						))}
					</nav>
				</div>
			</header>

			<main className="mx-auto w-full max-w-6xl px-6 py-8">
				<Outlet />
			</main>
		</div>
	);
}
