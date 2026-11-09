(function () {
	'use strict';

	document.querySelectorAll('.reevents-booking-form').forEach(function (form) {
		const ticket = form.querySelector('select[name="ticket_id"]');
		const quantity = form.querySelector('input[name="quantity"]');
		const bookingToken = window.crypto && window.crypto.randomUUID
			? window.crypto.randomUUID()
			: String(Date.now()) + Math.random();

		function syncLimits() {
			if (!ticket || !quantity || !ticket.options[ticket.selectedIndex]) {
				return;
			}

			const option = ticket.options[ticket.selectedIndex];

			quantity.min = option.dataset.min || '1';
			quantity.max = option.dataset.max && option.dataset.max !== '0' ? option.dataset.max : '';
		}

		if (ticket && quantity) {
			ticket.addEventListener('change', syncLimits);
			syncLimits();
		}

		form.addEventListener('submit', function (event) {
			if (!window.fetch) {
				return;
			}

			event.preventDefault();

			if ( form.dataset.reeventsSubmitting === '1' ) {
				return;
			}

			form.dataset.reeventsSubmitting = '1';

			const button = form.querySelector( 'button[type="submit"]' );
			const message = form.querySelector('.reevents-booking-message');
			const data = new FormData(form);

			if ( button ) {
				button.disabled = true;
			}
			const fields = {};

			form.querySelectorAll('[data-reevents-field]').forEach(function (field) {
				fields[field.dataset.reeventsField] = String(field.value || '');
			});

			const payload = {
				ticket_id: Number(data.get('ticket_id')),
				quantity: Number(data.get('quantity')),
				booking_token: bookingToken,
				attendees: [
					{
						name: String(data.get('name') || ''),
						email: String(data.get('email') || ''),
						fields: fields
					}
				],
				website: String(data.get('website') || '')
			};

			fetch(form.dataset.endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(payload)
			})
				.then(function (response) {
					return response.json().then(function (body) {
						return { ok: response.ok, body: body };
					});
				})
				.then(function (result) {
					if (result.ok) {
						message.textContent = 'Booking confirmed. Reference: ' + result.body.reference;
						form.reset();
						syncLimits();
					} else {
						message.textContent = result.body && result.body.message ? result.body.message : 'Booking failed.';
					}
				})
				.catch(function () {
					message.textContent = 'Booking failed.';
				})
				.finally(function () {
					form.dataset.reeventsSubmitting = '';
					if ( button ) {
						button.disabled = false;
					}
				});
		});
	});
})();
