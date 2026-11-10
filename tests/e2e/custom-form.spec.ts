import { expect, test } from '@playwright/test';

const eventId = process.env.REEVENTS_EVENT_ID;
const ticketId = process.env.REEVENTS_TICKET_ID;

test( 'custom form schema and booking', async ( { request } ) => {
	test.skip( ! eventId || ! ticketId, 'No E2E event seeded' );

	const schemaResponse = await request.get( `/wp-json/re-events/v1/events/${ eventId }/booking-form` );

	expect( schemaResponse.ok() ).toBeTruthy();

	const schema = await schemaResponse.json();

	expect( schema.event_id ).toBe( Number( eventId ) );
	expect( schema.tickets.length ).toBeGreaterThan( 0 );

	const fields: Record<string, string> = {};

	for ( const field of schema.fields ) {
		fields[ field.key ] = field.type === 'email' ? 'e2e@example.com' : 'E2E';
	}

	const payload = {
		ticket_id: Number( ticketId ),
		quantity: 1,
		booking_token: `e2e-${ Date.now() }-${ Math.random() }`,
		website: '',
		attendees: [
			{
				name: 'E2E Attendee',
				email: 'e2e@example.com',
				fields,
			},
		],
	};

	const first = await request.post( schema.submit_url, { data: payload } );

	expect( first.status() ).toBe( 201 );

	const booking = await first.json();

	expect( booking.reference ).toBeTruthy();

	const second = await request.post( schema.submit_url, { data: payload } );

	expect( second.status() ).toBe( 200 );

	const duplicate = await second.json();

	expect( duplicate.id ).toBe( booking.id );
} );
