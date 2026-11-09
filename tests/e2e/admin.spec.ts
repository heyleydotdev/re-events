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
