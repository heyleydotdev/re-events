( function () {
	const list = document.getElementById( 'reevents-tickets-list' );
	const add = document.getElementById( 'reevents-add-ticket' );
	const template = document.getElementById( 'reevents-ticket-template' );

	if ( ! list || ! add || ! template ) {
		return;
	}

	let next = parseInt( list.dataset.next || '0', 10 );

	add.addEventListener( 'click', function () {
		const html = template.innerHTML.replace( /__i__/g, String( next ) );
		next += 1;

		const holder = document.createElement( 'div' );
		holder.innerHTML = html;

		if ( holder.firstElementChild ) {
			list.appendChild( holder.firstElementChild );
		}
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
