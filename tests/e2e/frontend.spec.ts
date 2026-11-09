import { expect, test } from '@playwright/test';

test( 'events archive responds without a server error', async ( { request } ) => {
	const response = await request.get( '/events/' );

	expect( response.status() ).toBeLessThan( 500 );
} );
