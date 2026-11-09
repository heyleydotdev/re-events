(function () {
	'use strict';

	try {
		const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

		if (timezone) {
			document.cookie = 'reevents_timezone=' + encodeURIComponent(timezone) + '; path=/; max-age=31536000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
		}
	} catch (error) {
		// Intl is unavailable; the server falls back to the site timezone.
	}
})();
