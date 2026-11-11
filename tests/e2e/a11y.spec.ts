import { expect, test, type Page } from '@playwright/test';

const user = process.env.WP_ADMIN_USER || 'admin';
const password = process.env.WP_ADMIN_PASSWORD || 'password';
const axePath = `${ process.cwd() }/node_modules/axe-core/axe.min.js`;

async function login( page: Page ): Promise<void> {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', user );
	await page.fill( '#user_pass', password );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
}

test( 'ticket editor passes WCAG A and AA checks', async ( { page } ) => {
	const eventId = process.env.REEVENTS_EVENT_ID;

	test.skip( ! eventId, 'No E2E event seeded' );

	await login( page );
	await page.goto( `/wp-admin/post.php?post=${ eventId }&action=edit`, {
		waitUntil: 'domcontentloaded',
	} );

	await expect( page.locator( '#reevents-tickets' ) ).toHaveCount( 1 );

	// Meta boxes start hidden in the block editor.
	await page.evaluate( () => {
		let element = document.querySelector( '#reevents-tickets' );

		while ( element ) {
			element.style.display = 'block';
			element.style.visibility = 'visible';
			element = element.parentElement;
		}
	} );

	await page.addScriptTag( { path: axePath } );

	const violations = await page.evaluate( async () => {
		const results = await ( window as unknown as {
			axe: {
				run: (
					context: Element | null,
					options: Record<string, unknown>
				) => Promise< { violations: unknown[] } >;
			};
		} ).axe.run( document.querySelector( '#reevents-tickets' ), {
			runOnly: {
				type: 'tag',
				values: [ 'wcag2a', 'wcag2aa', 'wcag22aa' ],
			},
		} );

		return results.violations;
	} );

	expect( violations ).toEqual( [] );
} );
