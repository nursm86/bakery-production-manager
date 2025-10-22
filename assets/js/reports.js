/* global jQuery, bpmReports, BPMUtils */
(function ($) {
	'use strict';

	if (typeof bpmReports === 'undefined') {
		return;
	}

	// Enable console debugging
	window.bpmDebug = (typeof window.bpmDebug !== 'undefined') ? window.bpmDebug : (bpmReports && bpmReports.debug ? !!bpmReports.debug : true);
	const _bpmLog = (...args) => { if (window.bpmDebug && window.console && console.log) { try { console.log('[BPM Reports]', ...args); } catch (e) {} } };

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

		// Ensure dates are set even if optional libs (Select2) fail to load
		this.ensureDefaultDates(true);
		_bpmLog('init -> dates defaulted', this.$start.val(), this.$end.val());
		this.setupSelect2();
		this.bindEvents();
		_bpmLog('init -> starting initial fetch');
		this.fetchReport();
	},

	setupSelect2() {
		_bpmLog('setupSelect2');
		if (!this.$product || !this.$product.length) { _bpmLog('no product select found'); return; }

		// Prefer Select2; fall back to WooCommerce's SelectWoo when available
		const hasSelect2 = typeof this.$product.select2 === 'function';
		const hasSelectWoo = typeof this.$product.selectWoo === 'function';
		if (!hasSelect2 && !hasSelectWoo) { _bpmLog('Select2/SelectWoo not available, skipping enhancement'); return; }
		const initFn = hasSelect2 ? 'select2' : 'selectWoo';

		const $dropdownParent = this.$product.closest('.bpm-report-filters');
		this.$product[initFn]({
			placeholder: this.$product.data('placeholder'),
			allowClear: true,
			width: '100%',
			minimumInputLength: 0,
			dropdownParent: $dropdownParent.length ? $dropdownParent : undefined,
			ajax: {
				url: bpmReports.ajaxUrl,
				dataType: 'text',
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
					_bpmLog('select2 processResults raw', response);
					const parsed = BPMUtils.parseJsonResponse(response);
					_bpmLog('select2 processResults parsed', parsed);
					if (parsed && parsed.success && parsed.data) {
						return {
							results: parsed.data.results,
							pagination: parsed.data.pagination,
						};
					}
					return { results: [] };
				},
				cache: true,
			},
		});

		this.$product.on('select2:open', () => {
			const $searchField = $('.select2-container--open .select2-search__field');
			if ($searchField.length) {
				if (!$searchField.data('bpm-initialised')) {
					$searchField.data('bpm-initialised', true);
					setTimeout(() => {
						const original = $searchField.val();
						if (!original) {
							$searchField.val(' ');
							$searchField.trigger('input');
							$searchField.val('');
						}
					}, 0);
				}
			}
		});
	},

		bindEvents() {
			$('#bpm-run-report').on('click', () => this.fetchReport());

			$('#bpm-export-csv').on('click', () => this.exportCsv());
		},

	getFilters() {
		this.ensureDefaultDates();
		const startDate = this.$start.val();
		let endDate = this.$end.val();
		if (startDate && endDate && startDate > endDate) {
			endDate = startDate;
			this.$end.val(endDate);
		}
		const f = {
			start_date: startDate,
			end_date: endDate,
			product_id: this.$product.val() || '',
		};
		_bpmLog('getFilters', f);
		return f;
	},

	fetchReport() {
		const filters = this.getFilters();

		if (!filters.start_date) {
			this.renderEmpty(bpmReports.messages.startRequired);
			BPMUtils.showNotice(bpmReports.messages.startRequired, 'warning');
			return;
		}

		BPMUtils.block(this.$container);
		_bpmLog('fetchReport -> ajax start', filters);

			$.ajax({
				url: bpmReports.ajaxUrl,
				method: 'POST',
				dataType: 'text',
				data: {
					action: 'bpm_get_report_data',
					nonce: bpmReports.nonce,
					...filters,
				},
			})
				.done((rawResponse) => {
					_bpmLog('fetchReport -> ajax done, raw length', rawResponse && rawResponse.length);
					const response = BPMUtils.parseJsonResponse(rawResponse);
					_bpmLog('fetchReport -> parsed', response);

					if (!response || !response.success || !response.data) {
						const message = response && response.data && response.data.message
							? response.data.message
							: bpmReports.messages.noData;
						this.renderEmpty(message);
						BPMUtils.showNotice(message, 'warning');
						return;
					}

					this.applyFilters(response.data.filters || {});
					this.renderTable(response.data.rows || []);
					this.renderTotals(response.data.totals || {});
					this.renderChart(response.data.chart || {});
				})
				.fail((jqXHR) => {
					_bpmLog('fetchReport -> ajax fail', jqXHR && jqXHR.status, jqXHR && jqXHR.responseText && jqXHR.responseText.slice(0, 200));
					const parsed = BPMUtils.parseJsonResponse(jqXHR && jqXHR.responseText ? jqXHR.responseText : null);
					const message = parsed && parsed.data && parsed.data.message
						? parsed.data.message
						: bpmReports.messages.noData;
					this.renderEmpty(message);
					BPMUtils.showNotice(message, 'error');
				})
				.always(() => {
					_bpmLog('fetchReport -> ajax always');
					BPMUtils.unblock(this.$container);
				});
		},

	renderEmpty(message) {
		const fallback = (bpmReports.labels && bpmReports.labels.noData) || 'No data available.';
		const output = message || fallback;
		this.$tableBody.html(`<tr class="no-results"><td colspan="7">${output}</td></tr>`);
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
			_bpmLog('renderTable rows', Array.isArray(rows) ? rows.length : 0);
			if (!rows.length) {
				const emptyMessage = (bpmReports.labels && bpmReports.labels.noData) || 'No data available.';
				this.renderEmpty(emptyMessage);
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
			_bpmLog('renderTotals', totals);
			this.$totals.produced.text(BPMUtils.formatNumber(totals.produced || 0));
			this.$totals.wasted.text(BPMUtils.formatNumber(totals.wasted || 0));
			this.$totals.net.text(BPMUtils.formatNumber(totals.net_added || 0));
			this.$totals.sold.text(BPMUtils.formatNumber(totals.sold || 0));
			this.$totals.stock.text(BPMUtils.formatNumber(totals.stock || 0));
			this.$totals.remaining.text(BPMUtils.formatNumber(totals.remaining || 0));
		},

		renderChart(chartData) {
			_bpmLog('renderChart labels', chartData && chartData.labels ? chartData.labels.length : 0);
			const ctx = document.getElementById('bpm-report-chart').getContext('2d');

			if (this.chart) {
				this.chart.destroy();
			}

			if (!chartData || !Array.isArray(chartData.labels) || !chartData.labels.length) {
				ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
				return;
			}

			// If Chart.js failed to load (offline), skip rendering gracefully
			if (typeof window.Chart === 'undefined') { _bpmLog('Chart.js missing, skip render'); return; }

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

			if (!filters.start_date) {
				BPMUtils.showNotice(bpmReports.messages.startRequired, 'warning');
				return;
			}

			BPMUtils.block(this.$container);

			$.ajax({
				url: bpmReports.ajaxUrl,
				method: 'POST',
				dataType: 'text',
				data: {
					action: 'bpm_export_csv',
					nonce: bpmReports.nonce,
					...filters,
				},
			})
				.done((rawResponse) => {
					const response = BPMUtils.parseJsonResponse(rawResponse);

					if (!response || !response.success || !response.data) {
						const message = response && response.data && response.data.message
							? response.data.message
							: bpmReports.messages.csvError;
						BPMUtils.showNotice(message, 'error');
						return;
					}

					this.downloadCsv(response.data);
				})
				.fail((jqXHR) => {
					const parsed = BPMUtils.parseJsonResponse(jqXHR && jqXHR.responseText ? jqXHR.responseText : null);
					const message = parsed && parsed.data && parsed.data.message
						? parsed.data.message
						: bpmReports.messages.csvError;
					BPMUtils.showNotice(message, 'error');
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

		ensureDefaultDates(force = false) {
			const todayObj = new Date();
			const startObj = new Date(todayObj);
			startObj.setDate(startObj.getDate() - 6);
			const startDefault = this.formatDate(startObj);
			const endDefault = this.formatDate(todayObj);

			if (force || !this.$start.val()) {
				this.$start.val(startDefault);
			}
			if (force || !this.$end.val()) {
				this.$end.val(endDefault);
			}
		},

		formatDate(date) {
			const year = date.getFullYear();
			const month = `${date.getMonth() + 1}`.padStart(2, '0');
			const day = `${date.getDate()}`.padStart(2, '0');
			return `${year}-${month}-${day}`;
		},
	};

	$(document).ready(() => {
		ReportsApp.init();
	});
})(jQuery);
