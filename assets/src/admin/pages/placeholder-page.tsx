import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export function PlaceholderPage({ title }: { title: string }) {
	return (
		<Card>
			<CardHeader>
				<CardTitle>{title}</CardTitle>
				<CardDescription>This screen is not built yet.</CardDescription>
			</CardHeader>
			<CardContent />
		</Card>
	);
}
