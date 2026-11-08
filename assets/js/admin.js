(function () {
	'use strict';

	function setupRepeater(listId, templateId, addId, removeClass, rowClass) {
		const list = document.getElementById(listId);
		const template = document.getElementById(templateId);
		const add = document.getElementById(addId);

		if (!list || !template || !add) {
			return;
		}

		add.addEventListener('click', function () {
			list.appendChild(template.content.cloneNode(true));
		});

		list.addEventListener('click', function (event) {
			if (event.target.classList.contains(removeClass)) {
				const row = event.target.closest('.' + rowClass);

				if (row) {
					row.remove();
				}
			}
		});
	}

	function init() {
		setupRepeater('reevents-online-urls', 'reevents-url-template', 'reevents-add-url', 'reevents-remove-url', 'reevents-url-row');
		setupRepeater('reevents-exdates', 'reevents-exdate-template', 'reevents-add-exdate', 'reevents-remove-exdate', 'reevents-exdate-row');

		const allDay = document.getElementById('reevents-all-day');
		const start = document.getElementById('reevents-start');
		const exdateTemplate = document.getElementById('reevents-exdate-template');

		if (allDay) {
			allDay.addEventListener('change', function () {
				const type = allDay.checked ? 'date' : 'datetime-local';
				const time = start && start.value.includes('T') ? start.value.slice(11) : '00:00';

				document.querySelectorAll('#reevents-exdates input[name="reevents_exdates[]"]').forEach(function (input) {
					input.type = type;

					if (type === 'date') {
						input.value = input.value.slice(0, 10);
					} else if (input.value && input.value.length === 10) {
						input.value += 'T' + time;
					}
				});

				if (exdateTemplate) {
					const templateInput = exdateTemplate.content.querySelector('input[name="reevents_exdates[]"]');

					if (templateInput) {
						templateInput.type = type;
					}
				}
			});
		}

		const repeat = document.getElementById('reevents-repeat');
		const end = document.getElementById('reevents-end');

		if (!repeat || !end) {
			return;
		}

		const intervalRow = document.getElementById('reevents-interval-row');
		const weeklyRow = document.getElementById('reevents-weekly-row');
		const monthlyRow = document.getElementById('reevents-monthly-row');
		const endRow = document.getElementById('reevents-end-row');
		const untilRow = document.getElementById('reevents-until-row');
		const countRow = document.getElementById('reevents-count-row');
		const unit = document.getElementById('reevents-interval-unit');

		const units = {
			daily: 'day(s)',
			weekly: 'week(s)',
			monthly: 'month(s)',
			yearly: 'year(s)'
		};

		function toggle(element, visible) {
			if (element) {
				element.hidden = !visible;
			}
		}

		function update() {
			const freq = repeat.value;
			const repeats = freq !== 'none';
			const endMode = end.value;

			toggle(intervalRow, repeats);
			toggle(weeklyRow, freq === 'weekly');
			toggle(monthlyRow, freq === 'monthly');
			toggle(endRow, repeats);
			toggle(untilRow, repeats && endMode === 'until');
			toggle(countRow, repeats && endMode === 'count');

			if (unit) {
				unit.textContent = units[freq] || '';
			}
		}

		repeat.addEventListener('change', update);
		end.addEventListener('change', update);
		update();
	}

	if (window.wp && window.wp.domReady) {
		window.wp.domReady(init);
	} else if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
