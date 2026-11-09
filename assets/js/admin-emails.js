( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.reevents-placeholder' );

		if ( ! button ) {
			return;
		}

		var field = document.getElementById( button.dataset.target );

		if ( ! field ) {
			return;
		}

		field.setRangeText( button.dataset.token, field.selectionStart, field.selectionEnd, 'end' );
		field.focus();
	} );
}() );
