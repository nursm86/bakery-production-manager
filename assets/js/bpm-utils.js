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

		parseJsonResponse(payload) {
			const debug = !!(window.bpmDebug);
			if (debug) {
				try { console.log('[BPM][parseJson] raw type:', typeof payload, 'preview:', typeof payload === 'string' ? payload.slice(0, 200) : payload); } catch (e) {}
			}
			if (payload === null || typeof payload === 'undefined') {
				return null;
			}

			if (typeof payload === 'object') {
				return payload;
			}

			if (typeof payload !== 'string') {
				return null;
			}

			let text = payload.trim();

			if (!text) {
				return null;
			}

			const firstBrace = text.indexOf('{');
			const firstBracket = text.indexOf('[');

			if (firstBrace === -1 && firstBracket === -1) {
				return null;
			}

			let startIndex;

			if (firstBrace === -1) {
				startIndex = firstBracket;
			} else if (firstBracket === -1) {
				startIndex = firstBrace;
			} else {
				startIndex = Math.min(firstBrace, firstBracket);
			}

			text = text.slice(startIndex);

			const lastBrace = text.lastIndexOf('}');
			const lastBracket = text.lastIndexOf(']');
			const endIndex = Math.max(lastBrace, lastBracket);

			if (endIndex >= 0) {
				text = text.slice(0, endIndex + 1);
			}

				try {
					const parsed = JSON.parse(text);
					if (debug) { try { console.log('[BPM][parseJson] parsed OK'); } catch (e) {} }
					return parsed;
				} catch (error) {
					if (debug) { try { console.warn('[BPM][parseJson] primary parse failed:', error && error.message); } catch (e) {} }
					try {
						const fallbackMatch = text.match(/(\{[\s\S]*\}|\[[\s\S]*\])/);
						if (fallbackMatch && fallbackMatch[1]) {
							const alt = JSON.parse(fallbackMatch[1]);
							if (debug) { try { console.log('[BPM][parseJson] parsed via fallback'); } catch (e) {} }
							return alt;
						}
					} catch (nestedError) {
						if (debug) { try { console.error('[BPM][parseJson] fallback parse failed:', nestedError && nestedError.message); } catch (e) {} }
					}
				}

				return null;
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
