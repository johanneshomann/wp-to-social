/**
 * WP to Social — Admin JS
 */
(function ($) {
	'use strict';

	// Disconnect confirmation.
	$(document).on('click', '[data-confirm="disconnect"]', function (e) {
		if (!confirm(wpts.i18n.confirm_disconnect)) {
			e.preventDefault();
		}
	});

	// Retry button.
	$(document).on('click', '.wpts-retry-btn', function () {
		var $btn = $(this);
		var activityId = $btn.data('activity-id');
		var nonce = $btn.data('nonce');

		$btn.prop('disabled', true).text(wpts.i18n.retrying);

		$.post(wpts.ajax_url, {
			action: 'wpts_retry_post',
			activity_id: activityId,
			nonce: nonce,
		})
		.done(function (response) {
			if (response.success) {
				$btn.text(wpts.i18n.retry_success);
				setTimeout(function () {
					location.reload();
				}, 1500);
			} else {
				$btn.text(wpts.i18n.retry_failed).prop('disabled', false);
			}
		})
		.fail(function () {
			$btn.text(wpts.i18n.retry_failed).prop('disabled', false);
		});
	});

})(jQuery);
