(function () {
	'use strict';

	function init() {
		document.querySelectorAll('.reevents-map[data-provider="google"]').forEach(function (element) {
			if (element.dataset.initialized) {
				return;
			}

			let markers = [];

			try {
				markers = JSON.parse(element.dataset.markers || '[]');
			} catch (error) {
				return;
			}

			if (!markers.length) {
				return;
			}

			element.dataset.initialized = '1';

			if (element.dataset.height) {
				element.style.height = element.dataset.height + 'px';
			}

			const map = new google.maps.Map(element, {
				zoom: 12,
				center: { lat: markers[0].lat, lng: markers[0].lng }
			});
			const bounds = new google.maps.LatLngBounds();

			markers.forEach(function (marker) {
				const pin = new google.maps.Marker({
					position: { lat: marker.lat, lng: marker.lng },
					map: map,
					title: marker.title
				});

				bounds.extend(pin.getPosition());

				const content = document.createElement('div');

				if (marker.url) {
					const link = document.createElement('a');
					link.href = marker.url;
					link.textContent = marker.title;
					content.appendChild(link);
				} else {
					content.textContent = marker.title;
				}

				const info = new google.maps.InfoWindow({ content: content });

				pin.addListener('click', function () {
					info.open({ anchor: pin, map: map });
				});
			});

			if (markers.length > 1) {
				map.fitBounds(bounds);
			}
		});
	}

	let attempts = 0;

	function boot() {
		if (typeof google === 'undefined' || !google.maps) {
			attempts += 1;

			if (attempts < 100) {
				window.setTimeout(boot, 100);
			}

			return;
		}

		init();
	}

	boot();
})();
