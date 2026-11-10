(function () {
	'use strict';

	document.querySelectorAll('[data-reevents-timeslots]').forEach(function (fieldset) {
		const rows = fieldset.querySelector('.reevents-timeslot-rows');
		const template = fieldset.querySelector('.reevents-timeslot-template');
		const add = fieldset.querySelector('[data-reevents-add-timeslot]');

		if (!rows || !template || !add) {
			return;
		}

		add.addEventListener('click', function () {
			const row = template.content.firstElementChild.cloneNode(true);
			rows.appendChild(row);
		});

		fieldset.addEventListener('click', function (event) {
			const remove = event.target.closest('[data-reevents-remove-timeslot]');

			if (!remove) {
				return;
			}

			const row = remove.closest('.reevents-timeslot-row');

			if (row) {
				row.remove();
			}
		});
	});
})();
