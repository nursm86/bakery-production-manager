/* global jQuery, bpmSettings, BPMUtils */
(function ($) {
	'use strict';

	if (typeof bpmSettings === 'undefined') {
		return;
	}

	const SettingsApp = {
		init() {
			this.$form = $('#bpm-settings-form');
			this.$unitTypes = $('#bpm-unit-types');
			this.$manageStock = this.$form.find('input[name="enable_manage_stock"]');
			this.$summaryEmail = $('#bpm-summary-email');

			this.setupSelect2();
			this.bindEvents();
		},

		setupSelect2() {
			this.$unitTypes.select2({
				tags: true,
				width: '100%',
				placeholder: 'kg',
			});
		},

		bindEvents() {
			this.$form.on('submit', (event) => {
				event.preventDefault();
				this.saveSettings();
			});
		},

		saveSettings() {
			const unitTypes = this.$unitTypes.val() || [];

			BPMUtils.block(this.$form);

		$.ajax({
			url: bpmSettings.ajaxUrl,
			method: 'POST',
			dataType: 'text',
			data: {
				action: 'bpm_save_settings',
				nonce: bpmSettings.nonce,
				unit_types: unitTypes,
				enable_manage_stock: this.$manageStock.is(':checked') ? 1 : 0,
				summary_email: this.$summaryEmail.val(),
			},
		})
			.done((rawResponse) => {
				const response = BPMUtils.parseJsonResponse(rawResponse);

				if (!response || !response.success || !response.data) {
					BPMUtils.showNotice(bpmSettings.messages.error, 'error');
					return;
				}

				this.refreshForm(response.data.settings);
				BPMUtils.showNotice(bpmSettings.messages.saved, 'success');
			})
			.fail((jqXHR) => {
				const parsed = BPMUtils.parseJsonResponse(jqXHR && jqXHR.responseText ? jqXHR.responseText : null);
				const message = parsed && parsed.data && parsed.data.message
					? parsed.data.message
					: bpmSettings.messages.error;
				BPMUtils.showNotice(message, 'error');
			})
			.always(() => {
				BPMUtils.unblock(this.$form);
			});
	},

		refreshForm(settings) {
			if (!settings) {
				return;
			}

			this.$unitTypes.empty();

			if (Array.isArray(settings.unit_types)) {
				settings.unit_types.forEach((unit) => {
					this.$unitTypes.append(new Option(unit, unit, true, true));
				});
			}

			this.$unitTypes.trigger('change');
			this.$manageStock.prop('checked', !!parseInt(settings.enable_manage_stock, 10));
			this.$summaryEmail.val(settings.summary_email || '');
		},
	};

	$(document).ready(() => {
		SettingsApp.init();
	});
})(jQuery);
