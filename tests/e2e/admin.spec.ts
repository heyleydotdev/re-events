import { expect, test, type Page } from '@playwright/test';

const user = process.env.WP_ADMIN_USER || 'admin';
const password = process.env.WP_ADMIN_PASSWORD || 'password';

async function login( page: Page ): Promise<void> {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', user );
	await page.fill( '#user_pass', password );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
}

test( 'admin settings and bookings pages load', async ( { page } ) => {
	await login( page );

	await page.goto( '/wp-admin/admin.php?page=reevents-settings&tab=api' );
	await expect( page.getByRole( 'heading', { name: 'ReEvents Settings' } ) ).toBeVisible();
	await expect( page.getByRole( 'heading', { name: 'API keys' } ) ).toBeVisible();

	await page.goto( '/wp-admin/admin.php?page=reevents-bookings' );
	await expect( page.getByRole( 'heading', { name: 'Bookings' } ) ).toBeVisible();
} );

test( 'event ticket editor saves a ticket', async ( { page } ) => {
	const eventId = process.env.REEVENTS_EVENT_ID;

	test.skip( ! eventId, 'No E2E event seeded' );

	await login( page );
	await page.goto( `/wp-admin/post.php?post=${ eventId }&action=edit`, {
		waitUntil: 'domcontentloaded',
	} );

	// Meta boxes start hidden in the block editor.
	await page.evaluate( () => {
		let element = document.querySelector( '#reevents-tickets' );

		while ( element ) {
			element.style.display = 'block';
			element.style.visibility = 'visible';
			element = element.parentElement;
		}
	} );

	const name = `E2E ticket ${ Date.now() }`;
	const cards = page.locator( '#reevents-tickets-list .reevents-ticket' );
	const before = await cards.count();

	// The hidden block-editor meta area cannot be scrolled into view, so drive
	// the row script and inputs through the DOM.
	await page.evaluate( ( ticketName ) => {
		document.querySelector( '#reevents-add-ticket' )?.click();

		const cards = document.querySelectorAll(
			'#reevents-tickets-list .reevents-ticket'
		);
		const card = cards[ cards.length - 1 ];

		if ( ! card ) {
			return;
		}

		const set = ( key: string, value: string ) => {
			const input = card.querySelector(
				`input[name$="[${ key }]"]`
			) as HTMLInputElement | null;

			if ( input ) {
				input.value = value;
				input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			}
		};

		set( 'name', ticketName );
		set( 'price', '12.50' );
		set( 'currency', 'USD' );
		set( 'capacity', '3' );
	}, name );

	await expect( cards ).toHaveCount( before + 1 );

	const saved = page.waitForResponse( ( response ) => {
		if ( response.request().method() !== 'POST' ) {
			return false;
		}

		return (
			new URL( response.url() ).pathname ===
			`/wp-json/wp/v2/reevents-events/${ eventId }`
		);
	} );

	await page
		.locator(
			'.editor-post-publish-button, button.editor-post-publish-button__button'
		)
		.first()
		.click( { force: true } );
	await saved;

	await page.reload();
	await page.evaluate( () => {
		let element = document.querySelector( '#reevents-tickets' );

		while ( element ) {
			element.style.display = 'block';
			element.style.visibility = 'visible';
			element = element.parentElement;
		}
	} );

	await expect( page.locator( `input[value="${ name }"]` ) ).toHaveCount( 1 );
} );
