(function () {
	'use strict';

	function parseCenter(value) {
		const parts = String(value || '').split(',').map(Number);

		if (parts.length !== 2 || !parts.every(Number.isFinite)) {
			return null;
		}

		return Math.abs(parts[0]) <= 90 && Math.abs(parts[1]) <= 180 ? parts : null;
	}

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

			const start = parseCenter(element.dataset.center) || [markers[0].lat, markers[0].lng];
			const zoom = Number(element.dataset.zoom || 0) || 12;
			const map = new google.maps.Map(element, {
				zoom: zoom,
				center: { lat: start[0], lng: start[1] }
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
					const title = document.createElement('strong');
					title.textContent = marker.title;
					content.appendChild(title);
				}

				['address', 'next'].forEach(function (key) {
					if (marker[key]) {
						const line = document.createElement('div');
						line.textContent = marker[key];
						content.appendChild(line);
					}
				});

				const info = new google.maps.InfoWindow({ content: content });

				pin.addListener('click', function () {
					info.open({ anchor: pin, map: map });
				});
			});

			if (element.dataset.fit === '1' && markers.length > 1) {
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
