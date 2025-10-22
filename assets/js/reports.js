/* global jQuery, bpmReports, BPMUtils */
(function ($) {
	'use strict';

	if (typeof bpmReports === 'undefined') {
		return;
	}

	const ReportsApp = {
		init() {
			this.$container = $('.bpm-reports');
			this.$tableBody = $('#bpm-report-table tbody');
			this.$totals = {
				produced: $('#bpm-total-produced'),
				wasted: $('#bpm-total-wasted'),
				net: $('#bpm-total-net'),
				sold: $('#bpm-total-sold'),
				stock: $('#bpm-total-stock'),
				remaining: $('#bpm-total-remaining'),
			};
			this.$start = $('#bpm-start-date');
			this.$end = $('#bpm-end-date');
			this.$product = $('#bpm-report-product');
			this.chart = null;

			this.setupSelect2();
			this.bindEvents();
			this.fetchReport();
		},

		setupSelect2() {
			this.$product.select2({
				placeholder: this.$product.data('placeholder'),
				allowClear: true,
				width: '100%',
				ajax: {
					url: bpmReports.ajaxUrl,
					dataType: 'json',
					delay: 200,
					data(params) {
						return {
							action: 'bpm_search_products',
							nonce: bpmReports.nonce,
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
				},
			});
		},

		bindEvents() {
			$('#bpm-run-report').on('click', () => this.fetchReport());

			$('#bpm-export-csv').on('click', () => this.exportCsv());
		},

		getFilters() {
			return {
				start_date: this.$start.val(),
				end_date: this.$end.val(),
				product_id: this.$product.val() || '',
			};
		},

		fetchReport() {
			const filters = this.getFilters();

			BPMUtils.block(this.$container);

			$.ajax({
				url: bpmReports.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'bpm_get_report_data',
					nonce: bpmReports.nonce,
					...filters,
				},
			})
				.done((response) => {
					if (!response || !response.success || !response.data) {
						this.renderEmpty();
						BPMUtils.showNotice(bpmReports.messages.noData, 'warning');
						return;
					}

					this.applyFilters(response.data.filters || {});
					this.renderTable(response.data.rows || []);
					this.renderTotals(response.data.totals || {});
					this.renderChart(response.data.chart || {});
				})
				.fail(() => {
					this.renderEmpty();
					BPMUtils.showNotice(bpmReports.messages.noData, 'error');
				})
				.always(() => {
					BPMUtils.unblock(this.$container);
				});
		},

		renderEmpty() {
			const emptyMessage = (bpmReports.labels && bpmReports.labels.noData) || 'No data available.';
			this.$tableBody.html(`<tr class="no-results"><td colspan="7">${emptyMessage}</td></tr>`);
			this.renderTotals({});
			this.renderChart({});
		},

		applyFilters(filters) {
			if (filters.start_date) {
				this.$start.val(filters.start_date);
			}
			if (filters.end_date) {
				this.$end.val(filters.end_date);
			}
			if (filters.product_id) {
				const currentOption = this.$product.find(`option[value="${filters.product_id}"]`);

				if (!currentOption.length) {
					this.$product.append(
						$('<option>', {
							value: filters.product_id,
							text: filters.product_name || `#${filters.product_id}`,
							selected: true,
						})
					);
				}

				this.$product.val(filters.product_id).trigger('change');
			}
		},

		renderTable(rows) {
			if (!rows.length) {
				const emptyMessage = (bpmReports.labels && bpmReports.labels.noData) || 'No data available.';
				this.$tableBody.html(`<tr class="no-results"><td colspan="7">${emptyMessage}</td></tr>`);
				return;
			}

			const fragment = $(document.createDocumentFragment());

			rows.forEach((row) => {
				const oversoldLabel = (bpmReports.labels && bpmReports.labels.oversold) || 'Oversold vs Produced';
				const oversold = row.oversold ? `<span class="bpm-badge bpm-badge-danger">${oversoldLabel}</span>` : '';
				const $tr = $('<tr>').toggleClass('bpm-oversold', !!row.oversold);

				const productCell = $('<td>').html(`${row.product_name || `#${row.product_id}`} ${oversold}`);
				$tr.append(productCell);
				$tr.append($('<td class="num">').text(BPMUtils.formatNumber(row.total_produced)));
				$tr.append($('<td class="num">').text(BPMUtils.formatNumber(row.total_wasted)));
				$tr.append($('<td class="num">').text(BPMUtils.formatNumber(row.net_added)));
				$tr.append($('<td class="num">').text(BPMUtils.formatNumber(row.total_sold)));
				$tr.append($('<td class="num">').text(BPMUtils.formatNumber(row.current_stock)));
				$tr.append($('<td class="num">').text(BPMUtils.formatNumber(row.remaining)));

				fragment.append($tr);
			});

			this.$tableBody.html(fragment);
		},

		renderTotals(totals) {
			this.$totals.produced.text(BPMUtils.formatNumber(totals.produced || 0));
			this.$totals.wasted.text(BPMUtils.formatNumber(totals.wasted || 0));
			this.$totals.net.text(BPMUtils.formatNumber(totals.net_added || 0));
			this.$totals.sold.text(BPMUtils.formatNumber(totals.sold || 0));
			this.$totals.stock.text(BPMUtils.formatNumber(totals.stock || 0));
			this.$totals.remaining.text(BPMUtils.formatNumber(totals.remaining || 0));
		},

		renderChart(chartData) {
			const ctx = document.getElementById('bpm-report-chart').getContext('2d');

			if (this.chart) {
				this.chart.destroy();
			}

			if (!chartData || !Array.isArray(chartData.labels) || !chartData.labels.length) {
				ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
				return;
			}

			const labels = bpmReports.labels || {};
			this.chart = new window.Chart(ctx, {
				type: 'bar',
				data: {
					labels: chartData.labels,
					datasets: [
						{
							label: labels.produced || 'Produced',
							backgroundColor: '#22c55e',
							data: chartData.produced,
						},
						{
							label: labels.wasted || 'Wasted',
							backgroundColor: '#f97316',
							data: chartData.wasted,
						},
						{
							label: labels.sold || 'Sold',
							backgroundColor: '#3b82f6',
							data: chartData.sold,
						},
					],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					scales: {
						y: {
							beginAtZero: true,
						},
					},
				},
			});
		},

		exportCsv() {
			const filters = this.getFilters();

			BPMUtils.block(this.$container);

			$.ajax({
				url: bpmReports.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'bpm_export_csv',
					nonce: bpmReports.nonce,
					...filters,
				},
			})
				.done((response) => {
					if (!response || !response.success || !response.data) {
						BPMUtils.showNotice(bpmReports.messages.csvError, 'error');
						return;
					}

					this.downloadCsv(response.data);
				})
				.fail(() => {
					BPMUtils.showNotice(bpmReports.messages.csvError, 'error');
				})
				.always(() => {
					BPMUtils.unblock(this.$container);
				});
		},

		downloadCsv(data) {
			if (!data || !data.content) {
				return;
			}

			const decoded = atob(data.content);
			const array = new Uint8Array(decoded.length);
			for (let i = 0; i < decoded.length; i += 1) {
				array[i] = decoded.charCodeAt(i);
			}

			const blob = new Blob([array], { type: 'text/csv;charset=utf-8;' });
			const url = URL.createObjectURL(blob);
			const link = document.createElement('a');
			link.href = url;
			link.download = data.filename || 'bakery-production-report.csv';
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			URL.revokeObjectURL(url);
		},
	};

	$(document).ready(() => {
		ReportsApp.init();
	});
})(jQuery);
