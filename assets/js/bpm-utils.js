/* global jQuery */
(function ($) {
	'use strict';

	const BPMUtils = {
		formatNumber(value) {
			const number = parseFloat(value);
			if (Number.isNaN(number)) {
				return '0';
			}
			return number % 1 === 0 ? number.toString() : number.toFixed(2);
		},

		showNotice(message, type = 'success') {
			const existing = $('.bpm-flash');
			if (existing.length) {
				existing.remove();
			}

			const $notice = $('<div>', {
				class: `notice notice-${type} is-dismissible bpm-flash`,
			}).append($('<p>').text(message));

			$('#wpbody-content').prepend($notice);

			setTimeout(() => {
				$notice.fadeOut(400, () => $notice.remove());
			}, 6000);
		},

		scrollToTop() {
			$('html, body').animate({ scrollTop: 0 }, 300);
		},

		block($el) {
			if (!$el || !$el.length) {
				return;
			}

			const $blocker = $('<div class="bpm-blocker"><span class="spinner is-active"></span></div>');
			$el.append($blocker);
		},

		unblock($el) {
			if (!$el || !$el.length) {
				return;
			}

			$el.find('.bpm-blocker').remove();
		},
	};

	window.BPMUtils = BPMUtils;
})(jQuery);

