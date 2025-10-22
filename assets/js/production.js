/* global jQuery, bpmProduction, BPMUtils */
(function ($) {
	'use strict';

	if (typeof bpmProduction === 'undefined') {
		return;
	}

	const ProductionApp = {
		init() {
			this.$form = $('#bpm-production-form');
			this.$rowsContainer = $('#bpm-production-rows');
			this.$summary = $('#bpm-production-summary');
			this.template = $('#bpm-row-template').html();
			this.rowIndex = 0;

			this.bindEvents();
			this.addRow();
		},

		bindEvents() {
			this.$form.on('click', '.bpm-add-row', (event) => {
				event.preventDefault();
				this.addRow();
			});

			this.$form.on('click', '.bpm-remove-row', (event) => {
				event.preventDefault();
				const $row = $(event.currentTarget).closest('.bpm-row');
				this.removeRow($row);
			});

			this.$form.on('submit', (event) => {
				event.preventDefault();
				this.save();
			});

			this.$form.on('input change', '.bpm-input-produced, .bpm-input-wasted', (event) => {
				const $row = $(event.currentTarget).closest('.bpm-row');
				this.updateTotals($row);
			});
		},

		addRow() {
			this.rowIndex += 1;
			const html = this.template
				.replace(/{{rowId}}/g, `row-${this.rowIndex}`)
				.replace(/{{rowNumber}}/g, this.rowIndex);

			const $row = $(html);

			this.$rowsContainer.append($row);
			this.initialiseRow($row);
			this.updateRemoveButtons();
		},

		initialiseRow($row) {
			const $productSelect = $row.find('.bpm-product-select');
			const $unitSelect = $row.find('.bpm-unit-select');

			$productSelect.select2({
				ajax: {
					url: bpmProduction.ajaxUrl,
					dataType: 'json',
					delay: 200,
					data(params) {
						return {
							action: 'bpm_search_products',
							nonce: bpmProduction.nonce,
							term: params.term || '',
							page: params.page || 1,
						};
					},
					processResults(response) {
						if (response && response.success && response.data) {
							return {
								results: response.data.results,
								pagination: response.data.pagination,
							};
						}
						return { results: [] };
					},
					cache: true,
				},
				placeholder: $productSelect.data('placeholder'),
				minimumInputLength: 2,
				width: '100%',
			});

			$productSelect.on('select2:select', (event) => {
				const selected = event.params.data;
				$row.attr('data-product-id', selected.id);
				this.fetchProductStock($row, selected.id);
			});

			$productSelect.on('select2:clear', () => {
				$row.removeAttr('data-product-id');
				this.resetStockMeta($row);
			});

			this.populateUnits($unitSelect);
			$unitSelect.select2({
				tags: true,
				width: '100%',
				placeholder: bpmProduction.unitTypes.length ? bpmProduction.unitTypes[0] : 'unit',
			});
		},

		populateUnits($select) {
			$select.empty();

			if (Array.isArray(bpmProduction.unitTypes)) {
				bpmProduction.unitTypes.forEach((unit) => {
					$select.append(new Option(unit, unit, false, false));
				});
			}

			const defaultUnit = bpmProduction.unitTypes && bpmProduction.unitTypes.length ? bpmProduction.unitTypes[0] : '';
			if (defaultUnit) {
				$select.val(defaultUnit).trigger('change');
			}
		},

		fetchProductStock($row, productId) {
			if (!productId) {
				return;
			}

			const $status = $row.find('.bpm-stock-badge');
			$status.text('Fetching stock…');

			$.ajax({
				url: bpmProduction.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'bpm_get_product_stock',
					nonce: bpmProduction.nonce,
					product_id: productId,
				},
			})
				.done((response) => {
					if (!response || !response.success || !response.data) {
						this.resetStockMeta($row);
						return;
					}

					const stock = parseFloat(response.data.stock) || 0;
					$row.data('previousStock', stock);
					$row.find('.bpm-stock-previous').text(BPMUtils.formatNumber(stock));
					$status.text(response.data.product_name || '');
					this.updateTotals($row);
				})
				.fail(() => {
					this.resetStockMeta($row);
					BPMUtils.showNotice(bpmProduction.messages.error, 'error');
				});
		},

		updateTotals($row) {
			const produced = parseFloat($row.find('.bpm-input-produced').val()) || 0;
			const wasted = parseFloat($row.find('.bpm-input-wasted').val()) || 0;
			const previous = parseFloat($row.data('previousStock')) || 0;

			const newStock = Math.max(previous + produced - wasted, 0);

			$row.find('.bpm-stock-produced').text(BPMUtils.formatNumber(produced));
			$row.find('.bpm-stock-wasted').text(BPMUtils.formatNumber(wasted));
			$row.find('.bpm-stock-new').text(BPMUtils.formatNumber(newStock));

			if ($row.attr('data-product-id')) {
				$row.find('.bpm-stock-badge').text(
					`${BPMUtils.formatNumber(previous)} + ${BPMUtils.formatNumber(produced)} − ${BPMUtils.formatNumber(wasted)} = ${BPMUtils.formatNumber(newStock)}`
				);
			}
		},

		resetStockMeta($row) {
			$row.removeData('previousStock');
			$row.find('.bpm-stock-previous, .bpm-stock-produced, .bpm-stock-wasted, .bpm-stock-new').text('0');
			$row.find('.bpm-stock-badge').text('Awaiting product selection…');
		},

		removeRow($row) {
			if (this.$rowsContainer.find('.bpm-row').length === 1) {
				$row.find('select').val(null).trigger('change');
				$row.find('input').val('');
				this.resetStockMeta($row);
				return;
			}

			$row.remove();
			this.updateRowLabels();
			this.updateRemoveButtons();
		},

		updateRowLabels() {
			this.$rowsContainer.find('.bpm-row').each((index, element) => {
				const number = index + 1;
				$(element)
					.find('.bpm-row-title')
					.text(`${bpmProduction.labels && bpmProduction.labels.product ? bpmProduction.labels.product : 'Product'} #${number}`);
			});
		},

		updateRemoveButtons() {
			const $rows = this.$rowsContainer.find('.bpm-row');
			if ($rows.length <= 1) {
				$rows.find('.bpm-remove-row').prop('disabled', true).addClass('disabled');
			} else {
				$rows.find('.bpm-remove-row').prop('disabled', false).removeClass('disabled');
			}
		},

		getEntries() {
			const entries = [];
			let isValid = true;

			this.$rowsContainer.find('.bpm-row').each((index, element) => {
				const $row = $(element);
				const productId = $row.attr('data-product-id') ? parseInt($row.attr('data-product-id'), 10) : 0;
				const produced = parseFloat($row.find('.bpm-input-produced').val()) || 0;
				const wasted = parseFloat($row.find('.bpm-input-wasted').val()) || 0;
				const unitType = $row.find('.bpm-unit-select').val() || '';
				const note = $row.find('.bpm-input-note').val() || '';
				const previousStock = parseFloat($row.data('previousStock')) || 0;

				if (!productId) {
					isValid = false;
					return;
				}

				entries.push({
					product_id: productId,
					quantity_produced: produced,
					quantity_wasted: wasted,
					unit_type: unitType,
					note,
					previous_stock: previousStock,
				});
			});

			return {
				entries,
				isValid,
			};
		},

		save() {
			const { entries, isValid } = this.getEntries();

			if (!entries.length) {
				BPMUtils.showNotice(bpmProduction.messages.noEntries, 'warning');
				return;
			}

			if (!isValid) {
				BPMUtils.showNotice(bpmProduction.messages.validation, 'warning');
				return;
			}

			BPMUtils.block(this.$rowsContainer);

			$.ajax({
				url: bpmProduction.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'bpm_save_production_entries',
					nonce: bpmProduction.nonce,
					entries: JSON.stringify(entries),
				},
			})
				.done((response) => {
					if (!response || !response.success || !response.data) {
						BPMUtils.showNotice(bpmProduction.messages.error, 'error');
						return;
					}

					this.renderSummary(response.data);
					BPMUtils.showNotice(bpmProduction.messages.saved, 'success');
					this.resetForm();
				})
				.fail(() => {
					BPMUtils.showNotice(bpmProduction.messages.error, 'error');
				})
				.always(() => {
					BPMUtils.unblock(this.$rowsContainer);
				});
		},

		renderSummary(data) {
			if (!data || !Array.isArray(data.rows) || !data.rows.length) {
				this.$summary.html(`<p class="description">${bpmProduction.messages.noEntries}</p>`);
				return;
			}

			const summary = $('<div class="bpm-summary-content"></div>');

			data.rows.forEach((row) => {
				const $row = $(`
					<div class="bpm-summary-row">
						<strong>${row.product_name}</strong>
						<span>${BPMUtils.formatNumber(row.previous_stock)} + ${BPMUtils.formatNumber(row.produced)} − ${BPMUtils.formatNumber(row.wasted)} = ${BPMUtils.formatNumber(row.new_stock)}</span>
					</div>
				`);

				summary.append($row);
			});

			if (Array.isArray(data.warnings) && data.warnings.length) {
				summary.append(`
					<div class="bpm-summary-row bpm-summary-warning">
						<strong>Warnings</strong>
						<span>${data.warnings.join(' ')}</span>
					</div>
				`);
			}

			const labels = bpmProduction.labels || {};
			summary.append(`
				<div class="bpm-summary-row">
					<strong>${labels.totalProduced || 'Total Produced'}</strong>
					<span>${BPMUtils.formatNumber(data.totalProduced)}</span>
				</div>
				<div class="bpm-summary-row">
					<strong>${labels.totalWasted || 'Total Wasted'}</strong>
					<span>${BPMUtils.formatNumber(data.totalWasted)}</span>
				</div>
				<div class="bpm-summary-row">
					<strong>${labels.submittedBy || 'Submitted by'}</strong>
					<span>#${data.created_by} · ${data.timestamp}</span>
				</div>
			`);

			this.$summary.html(summary);
		},

		resetForm() {
			this.$rowsContainer.empty();
			this.rowIndex = 0;
			this.addRow();
		},
	};

	$(document).ready(() => {
		ProductionApp.init();
	});
})(jQuery);
