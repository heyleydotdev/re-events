( function () {
	'use strict';

	function decode( html ) {
		const element = document.createElement( 'textarea' );
		element.innerHTML = html;

		return element.value;
	}

	function setup( container ) {
		const search = container.querySelector( '.reevents-event-filter-search' );
		const select = container.querySelector( '.reevents-event-filter-select' );

		if ( ! search || ! select ) {
			return;
		}

		const allLabel = select.dataset.allLabel || 'All events';
		let selected = select.dataset.selected || '0';
		let timer = null;
		let request = 0;

		function setOptions( events ) {
			const current = select.value || selected;
			const seen = {};
			const fragment = document.createDocumentFragment();
			const all = document.createElement( 'option' );
			all.value = '0';
			all.textContent = allLabel;
			fragment.appendChild( all );

			if ( '0' !== selected && ! events.some( function ( event ) {
				return String( event.id ) === selected;
			} ) ) {
				const existing = select.querySelector( 'option[value="' + selected + '"]' );

				if ( existing ) {
					fragment.appendChild( existing.cloneNode( true ) );
				}
			}

			events.forEach( function ( event ) {
				const id = String( event.id );

				if ( seen[ id ] ) {
					return;
				}

				seen[ id ] = true;
				const option = document.createElement( 'option' );
				option.value = id;
				option.textContent = decode( event.title.rendered );
				fragment.appendChild( option );
			} );

			select.textContent = '';
			select.appendChild( fragment );

			select.value = select.querySelector( 'option[value="' + current + '"]' ) ? current : '0';
		}

		function load( term ) {
			const id = ++request;
			let path = '/wp/v2/reevents-events?per_page=20&orderby=title&order=asc&_fields=id,title';

			if ( term ) {
				path += '&search=' + encodeURIComponent( term );
			}

			wp.apiFetch( { path: path } )
				.then( function ( events ) {
					if ( id === request && Array.isArray( events ) ) {
						setOptions( events );
					}
				} )
				.catch( function () {
					if ( id === request ) {
						setOptions( [] );
					}
				} );
		}

		search.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				load( search.value.trim() );
			}, 250 );
		} );

		select.addEventListener( 'change', function () {
			selected = select.value;
		} );

		load( '' );
	}

	document.querySelectorAll( '.reevents-event-filter' ).forEach( setup );
}() );
