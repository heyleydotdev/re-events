import { Plus, Search } from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
	Table,
	TableBody,
	TableCell,
	TableHead,
	TableHeader,
	TableRow,
} from '@/components/ui/table';
import { useBootstrap } from '@/lib/bootstrap-context';

const badgeVariant = {
	success: 'success',
	warning: 'warning',
	neutral: 'secondary',
	danger: 'destructive',
} as const;

export function EventsPage() {
	const { events, pagination } = useBootstrap();
	const [query, setQuery] = useState('');

	const needle = query.trim().toLowerCase();
	const filtered = needle
		? events.filter((event) =>
				[event.title, event.venue, event.status].some((value) =>
					value.toLowerCase().includes(needle),
				),
			)
		: events;

	const pageCount = Math.max(1, pagination.total);
	const pageStart = Math.max(1, Math.min(pagination.current - 2, pageCount - 4));
	const pages = Array.from({ length: Math.min(5, pageCount) }, (_, index) => pageStart + index);
	const summary = query
		? `Showing ${filtered.length} of ${events.length} events`
		: pagination.summary;

	return (
		<div className="flex flex-col gap-6">
			<div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
				<div className="relative w-full sm:max-w-xs">
					<Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
					<Input
						type="search"
						value={query}
						onChange={(event) => setQuery(event.target.value)}
						placeholder="Search events"
						aria-label="Search events"
						className="pl-9"
					/>
				</div>
				<Button type="button" disabled>
					<Plus />
					Create event
				</Button>
			</div>

			<div className="overflow-hidden rounded-xl border bg-card shadow-sm">
				<Table>
					<TableHeader>
						<TableRow>
							<TableHead>Event</TableHead>
							<TableHead>Date</TableHead>
							<TableHead>Venue</TableHead>
							<TableHead>Status</TableHead>
						</TableRow>
					</TableHeader>
					<TableBody>
						{filtered.map((event) => (
							<TableRow key={event.id}>
								<TableCell className="font-medium text-foreground">{event.title}</TableCell>
								<TableCell className="text-muted-foreground">{event.date}</TableCell>
								<TableCell className="text-muted-foreground">{event.venue}</TableCell>
								<TableCell>
									<Badge variant={badgeVariant[event.tone]}>{event.status}</Badge>
								</TableCell>
							</TableRow>
						))}
						{filtered.length === 0 && (
							<TableRow>
								<TableCell colSpan={4} className="h-24 text-center text-muted-foreground">
									No events match “{query}”.
								</TableCell>
							</TableRow>
						)}
					</TableBody>
				</Table>

				<div className="flex flex-col gap-3 border-t px-4 py-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
					<span>{summary}</span>
					<div className="flex items-center gap-1">
						<Button variant="ghost" size="sm" disabled>
							Previous
						</Button>
						{pages.map((page) => (
							<Button
								key={page}
								variant={page === pagination.current ? 'secondary' : 'ghost'}
								size="sm"
								disabled
								aria-current={page === pagination.current ? 'page' : undefined}
							>
								{page}
							</Button>
						))}
						<Button variant="ghost" size="sm" disabled>
							Next
						</Button>
					</div>
				</div>
			</div>
		</div>
	);
}
