(function () {
	'use strict';

	function newToken() {
		return window.crypto && window.crypto.randomUUID
			? window.crypto.randomUUID()
			: String(Date.now()) + Math.random();
	}

	document.querySelectorAll('.reevents-booking-form').forEach(function (form) {
		const ticket = form.querySelector('select[name="ticket_id"]');
		const quantity = form.querySelector('input[name="quantity"]');
		const tokenField = form.querySelector('input[name="booking_token"]');
		let bookingToken = tokenField ? tokenField.value : newToken();

		function resetToken() {
			bookingToken = newToken();

			if (tokenField) {
				tokenField.value = bookingToken;
			}
		}

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
			const headers = { 'Content-Type': 'application/json' };
			const nonce = String(data.get('reevents_rest_nonce') || '');

			if (nonce) {
				headers['X-WP-Nonce'] = nonce;
			}

			if ( button ) {
				button.disabled = true;
			}
			const fields = {};

			form.querySelectorAll('[data-reevents-field]').forEach(function (field) {
				fields[field.dataset.reeventsField] = String(field.value || '');
			});

			const payload = {
				ticket_id: Number(data.get('ticket_id')),
				occurrence_id: Number(data.get('occurrence_id') || 0),
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
				headers: headers,
				body: JSON.stringify(payload)
			})
				.then(function (response) {
					return response.json().then(function (body) {
						return { ok: response.ok, body: body };
					});
				})
				.then(function (result) {
					if (result.ok) {
						if (result.body.checkout_url) {
							window.location.assign(result.body.checkout_url);
							return;
						}

						const success = form.dataset.successMessage || 'Booking confirmed.';
						const referenceLabel = form.dataset.referenceLabel || 'Reference:';
						const reference = result.body.reference ? ' ' + referenceLabel + ' ' + result.body.reference : '';

						message.textContent = success + reference;
						form.reset();
						resetToken();
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
