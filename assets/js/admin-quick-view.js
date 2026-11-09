( function () {
	'use strict';

	var modal = document.getElementById( 'reevents-quick-view' );

	if ( ! modal ) {
		return;
	}

	var body = modal.querySelector( '.reevents-modal-body' );
	var template = wp.template( 'reevents-booking' );
	var lastFocus = null;

	function open( id, trigger ) {
		lastFocus = trigger;
		modal.hidden = false;
		body.innerHTML = '<p>' + wp.i18n.__( 'Loading…', 're-events' ) + '</p>';
		body.focus();

		wp.apiFetch( { path: '/re-events/v1/bookings/' + id } )
			.then( function ( data ) {
				body.innerHTML = template( data );
				body.focus();
			} )
			.catch( function ( error ) {
				body.innerHTML = '<p>' + ( error.message || wp.i18n.__( 'Could not load this booking.', 're-events' ) ) + '</p>';
			} );
	}

	function close() {
		modal.hidden = true;
		body.innerHTML = '';

		if ( lastFocus ) {
			lastFocus.focus();
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( '.reevents-quick-view' );

		if ( link ) {
			event.preventDefault();
			open( link.dataset.bookingId, link );
			return;
		}

		if ( event.target.closest( '.reevents-modal-close' ) || event.target.classList.contains( 'reevents-modal-backdrop' ) ) {
			close();
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( modal.hidden ) {
			return;
		}

		if ( 'Escape' === event.key ) {
			close();
			return;
		}

		if ( 'Tab' !== event.key ) {
			return;
		}

		var focusable = body.querySelectorAll( 'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])' );

		if ( ! focusable.length ) {
			return;
		}

		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];

		if ( event.shiftKey && ( document.activeElement === first || document.activeElement === body ) ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	} );
}() );
