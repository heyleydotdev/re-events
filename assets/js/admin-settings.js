( function () {
	'use strict';

	function slugify( value ) {
		return value.toLowerCase().trim().replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' );
	}

	function setupAttendeeFields() {
		const container = document.getElementById( 'reevents-attendee-fields' );
		const body = document.getElementById( 'reevents-attendee-rows' );
		const template = document.getElementById( 'reevents-attendee-field-template' );
		const add = document.getElementById( 'reevents-add-attendee-field' );

		if ( ! container || ! body || ! template || ! add ) {
			return;
		}

		add.addEventListener( 'click', function () {
			const row = template.content.firstElementChild.cloneNode( true );
			body.appendChild( row );

			const label = row.querySelector( '[data-reevents-label]' );

			if ( label ) {
				label.focus();
			}
		} );

		container.addEventListener( 'input', function ( event ) {
			if ( ! event.target.matches( '[data-reevents-label]' ) ) {
				return;
			}

			const row = event.target.closest( '.reevents-attendee-row' );
			const key = row && row.querySelector( '[data-reevents-key]' );

			if ( key && row.dataset.new === '1' ) {
				key.value = slugify( event.target.value );
			}
		} );

		container.addEventListener( 'click', function ( event ) {
			const row = event.target.closest( '.reevents-attendee-row' );

			if ( ! row ) {
				return;
			}

			if ( event.target.closest( '.reevents-remove-attendee-field' ) ) {
				row.remove();
			} else if ( event.target.closest( '.reevents-move-up' ) && row.previousElementSibling ) {
				body.insertBefore( row, row.previousElementSibling );
			} else if ( event.target.closest( '.reevents-move-down' ) && row.nextElementSibling ) {
				body.insertBefore( row.nextElementSibling, row );
			}
		} );
	}

	function setupPlaceholders() {
		document.addEventListener( 'click', function ( event ) {
			const button = event.target.closest( '.reevents-placeholder' );

			if ( ! button ) {
				return;
			}

			const field = document.getElementById( button.dataset.target );

			if ( ! field ) {
				return;
			}

			field.setRangeText( button.dataset.token, field.selectionStart, field.selectionEnd, 'end' );
			field.focus();
		} );
	}

	setupAttendeeFields();
	setupPlaceholders();
}() );
