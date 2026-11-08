(function () {
	'use strict';

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

			const map = L.map(element).setView([markers[0].lat, markers[0].lng], 12);

			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				attribution: '&copy; OpenStreetMap contributors'
			}).addTo(map);

			markers.forEach(function (marker) {
				const popup = document.createElement('div');
				const link = document.createElement('a');

				link.href = marker.url;
				link.textContent = marker.title;
				popup.appendChild(link);

				L.marker([marker.lat, marker.lng]).addTo(map).bindPopup(popup);
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
