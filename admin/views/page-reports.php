<?php
/**
 * Reports page.
 *
 * @package BakeryProductionManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap bpm-wrap bpm-reports">
	<h1><?php esc_html_e( 'Bakery Production Reports', 'bakery-production-manager' ); ?></h1>

	<div class="bpm-card bpm-report-filters">
		<h2><?php esc_html_e( 'Filter', 'bakery-production-manager' ); ?></h2>
		<div class="bpm-report-grid">
			<label>
				<span><?php esc_html_e( 'Start date', 'bakery-production-manager' ); ?></span>
				<input type="date" id="bpm-start-date" />
			</label>
			<label>
				<span><?php esc_html_e( 'End date', 'bakery-production-manager' ); ?></span>
				<input type="date" id="bpm-end-date" />
			</label>
			<label>
				<span><?php esc_html_e( 'Product', 'bakery-production-manager' ); ?></span>
				<select id="bpm-report-product" data-placeholder="<?php esc_attr_e( 'All products', 'bakery-production-manager' ); ?>"></select>
			</label>
		</div>
		<div class="bpm-actions">
			<button type="button" class="button button-primary" id="bpm-run-report">
				<span class="dashicons dashicons-update" aria-hidden="true"></span>
				<?php esc_html_e( 'Run report', 'bakery-production-manager' ); ?>
			</button>
			<button type="button" class="button" id="bpm-export-csv">
				<span class="dashicons dashicons-download" aria-hidden="true"></span>
				<?php esc_html_e( 'Export CSV', 'bakery-production-manager' ); ?>
			</button>
		</div>
	</div>

	<div class="bpm-card">
		<h2><?php esc_html_e( 'Production vs Waste vs Sales', 'bakery-production-manager' ); ?></h2>
		<canvas id="bpm-report-chart" height="180"></canvas>
	</div>

	<div class="bpm-card">
		<h2><?php esc_html_e( 'Detailed Breakdown', 'bakery-production-manager' ); ?></h2>
		<div class="bpm-table-wrapper">
			<table class="widefat fixed striped" id="bpm-report-table" aria-live="polite">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'bakery-production-manager' ); ?></th>
						<th><?php esc_html_e( 'Produced', 'bakery-production-manager' ); ?></th>
						<th><?php esc_html_e( 'Wasted', 'bakery-production-manager' ); ?></th>
						<th><?php esc_html_e( 'Net Added', 'bakery-production-manager' ); ?></th>
						<th><?php esc_html_e( 'Sold', 'bakery-production-manager' ); ?></th>
						<th><?php esc_html_e( 'Current Stock', 'bakery-production-manager' ); ?></th>
						<th><?php esc_html_e( 'Remaining', 'bakery-production-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr class="no-results">
						<td colspan="7"><?php esc_html_e( 'Run the report to see production analytics.', 'bakery-production-manager' ); ?></td>
					</tr>
				</tbody>
				<tfoot>
					<tr>
						<th><?php esc_html_e( 'Totals', 'bakery-production-manager' ); ?></th>
						<th id="bpm-total-produced">0</th>
						<th id="bpm-total-wasted">0</th>
						<th id="bpm-total-net">0</th>
						<th id="bpm-total-sold">0</th>
						<th id="bpm-total-stock">0</th>
						<th id="bpm-total-remaining">0</th>
					</tr>
				</tfoot>
			</table>
		</div>
	</div>
</div>

