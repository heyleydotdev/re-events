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
		if (typeof L === 'undefined') {
			return;
		}

		document.querySelectorAll('.reevents-map').forEach(function (element) {
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
			const map = L.map(element).setView(start, zoom);
			const bounds = L.latLngBounds([]);

			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				attribution: '&copy; OpenStreetMap contributors'
			}).addTo(map);

			markers.forEach(function (marker) {
				const popup = document.createElement('div');

				if (marker.url) {
					const link = document.createElement('a');
					link.href = marker.url;
					link.textContent = marker.title;
					popup.appendChild(link);
				} else {
					const title = document.createElement('strong');
					title.textContent = marker.title;
					popup.appendChild(title);
				}

				['address', 'next'].forEach(function (key) {
					if (marker[key]) {
						const line = document.createElement('div');
						line.textContent = marker[key];
						popup.appendChild(line);
					}
				});

				const pin = L.marker([marker.lat, marker.lng]).addTo(map).bindPopup(popup);
				bounds.extend(pin.getLatLng());
			});

			if (element.dataset.fit === '1' && markers.length > 1) {
				map.fitBounds(bounds);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
