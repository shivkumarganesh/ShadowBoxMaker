/* global wcbpTutorial, jQuery */
(function ($) {
	'use strict';

	var currentStep = parseInt(wcbpTutorial.current_step, 10) || 1;

	function saveStep(step) {
		$.post(wcbpTutorial.ajax_url, {
			action: 'wcbp_save_tutorial_step',
			nonce : wcbpTutorial.nonce,
			step  : step,
		});
	}

	$('#wcbp-tut-next').on('click', function () {
		var nextStep = currentStep + 1;
		if (nextStep > parseInt(wcbpTutorial.total_steps, 10) + 1) {
			// Complete
			$.post(wcbpTutorial.ajax_url, {
				action: 'wcbp_complete_tutorial',
				nonce : wcbpTutorial.nonce,
			}, function (res) {
				if (res.success && res.data.redirect) {
					window.location.href = res.data.redirect;
				}
			});
			return;
		}
		saveStep(nextStep);
		window.location.href = wcbpTutorial.page_url + '&step=' + nextStep;
	});

	$('#wcbp-tut-skip').on('click', function () {
		$.post(wcbpTutorial.ajax_url, {
			action: 'wcbp_complete_tutorial',
			nonce : wcbpTutorial.nonce,
		}, function (res) {
			if (res.success && res.data.redirect) {
				window.location.href = res.data.redirect;
			}
		});
	});

	// Dot navigation
	$(document).on('click', '.wcbp-tut-dot', function () {
		var step = parseInt($(this).data('step'), 10);
		saveStep(step);
		window.location.href = wcbpTutorial.page_url + '&step=' + step;
	});

}(jQuery));
