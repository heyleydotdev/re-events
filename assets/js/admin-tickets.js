( function () {
	const list = document.getElementById( 'reevents-tickets-list' );
	const add = document.getElementById( 'reevents-add-ticket' );
	const free = document.getElementById( 'reevents-add-free-ticket' );
	const template = document.getElementById( 'reevents-ticket-template' );

	if ( ! list || ! add || ! free || ! template ) {
		return;
	}

	let next = parseInt( list.dataset.next || '0', 10 );

	function addCard( values ) {
		const html = template.innerHTML.replace( /__i__/g, String( next ) );
		next += 1;

		const holder = document.createElement( 'div' );
		holder.innerHTML = html;

		const card = holder.firstElementChild;

		if ( ! card ) {
			return;
		}

		Object.entries( values ).forEach( ( [ key, value ] ) => {
			const input = card.querySelector( `input[name$="[${ key }]"]` );

			if ( input ) {
				input.value = value;
			}
		} );

		list.appendChild( card );
	}

	add.addEventListener( 'click', function () {
		addCard( {} );
	} );

	free.addEventListener( 'click', function () {
		addCard( {
			name: free.dataset.name || 'RSVP',
			price: '0.00',
			capacity: list.closest( '.reevents-tickets' ).dataset.capacity || '0',
			min_per_booking: '1',
			max_per_booking: '0',
		} );
	} );

	list.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '.reevents-remove-ticket' );

		if ( ! button ) {
			return;
		}

		const card = button.closest( '.reevents-ticket' );
		const deleted = card.querySelector( 'input[name$="[delete]"]' );

		if ( deleted ) {
			deleted.value = '1';
		}

		card.style.display = 'none';
	} );
} )();
