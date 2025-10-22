<?php
/**
 * Production entry page.
 *
 * @package BakeryProductionManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap bpm-wrap bpm-production">
	<h1><?php esc_html_e( 'Bakery Production Entry', 'bakery-production-manager' ); ?></h1>

	<div class="bpm-card">
		<p class="description"><?php esc_html_e( 'Record today’s production, waste, and automatically update WooCommerce stock. Add as many products as needed without reloading the page.', 'bakery-production-manager' ); ?></p>

		<form id="bpm-production-form">
			<div id="bpm-production-rows" class="bpm-production-rows" aria-live="polite"></div>

			<div class="bpm-actions">
				<button type="button" class="button bpm-add-row">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'Add another product', 'bakery-production-manager' ); ?>
				</button>

				<button type="submit" class="button button-primary bpm-save-production">
					<span class="dashicons dashicons-saved" aria-hidden="true"></span>
					<?php esc_html_e( 'Save Production', 'bakery-production-manager' ); ?>
				</button>
			</div>
		</form>
	</div>

	<div class="bpm-summary-card">
		<h2><?php esc_html_e( 'Latest Submission Summary', 'bakery-production-manager' ); ?></h2>
		<div class="bpm-summary-content" id="bpm-production-summary">
			<p class="description"><?php esc_html_e( 'Submit production entries to see a live summary including updated stock levels.', 'bakery-production-manager' ); ?></p>
		</div>
	</div>
</div>

<script type="text/template" id="bpm-row-template">
	<div class="bpm-row" data-row="{{rowId}}">
		<div class="bpm-row-header">
			<span class="bpm-row-title"><?php esc_html_e( 'Product', 'bakery-production-manager' ); ?> #{{rowNumber}}</span>
			<button type="button" class="button-link bpm-remove-row" aria-label="<?php esc_attr_e( 'Remove row', 'bakery-production-manager' ); ?>">
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
			</button>
		</div>

		<div class="bpm-row-grid">
			<label>
				<span><?php esc_html_e( 'Product', 'bakery-production-manager' ); ?></span>
				<select class="bpm-product-select" data-placeholder="<?php esc_attr_e( 'Search product…', 'bakery-production-manager' ); ?>"></select>
			</label>

			<label>
				<span><?php esc_html_e( 'Quantity Produced', 'bakery-production-manager' ); ?></span>
				<input type="number" step="0.01" min="0" class="bpm-input-produced" placeholder="0" />
			</label>

			<label>
				<span><?php esc_html_e( 'Quantity Wasted', 'bakery-production-manager' ); ?></span>
				<input type="number" step="0.01" min="0" class="bpm-input-wasted" placeholder="0" />
			</label>

			<label>
				<span><?php esc_html_e( 'Unit Type', 'bakery-production-manager' ); ?></span>
				<select class="bpm-unit-select"></select>
			</label>

			<label>
				<span><?php esc_html_e( 'Note (optional)', 'bakery-production-manager' ); ?></span>
				<input type="text" class="bpm-input-note" placeholder="<?php esc_attr_e( 'e.g. Night shift batch', 'bakery-production-manager' ); ?>" />
			</label>
		</div>

		<div class="bpm-stock-meta">
			<div class="bpm-stock-formula">
				<strong><?php esc_html_e( 'Stock Calculation', 'bakery-production-manager' ); ?>:</strong>
				<span class="bpm-stock-text">
					<?php esc_html_e( 'Previous Stock', 'bakery-production-manager' ); ?>
					<span class="bpm-stock-previous">0</span>
					+
					<?php esc_html_e( 'Produced', 'bakery-production-manager' ); ?>
					<span class="bpm-stock-produced">0</span>
					−
					<?php esc_html_e( 'Wasted', 'bakery-production-manager' ); ?>
					<span class="bpm-stock-wasted">0</span>
					=
					<?php esc_html_e( 'New Stock', 'bakery-production-manager' ); ?>
					<span class="bpm-stock-new">0</span>
				</span>
			</div>
			<div class="bpm-stock-status">
				<span class="bpm-stock-badge"><?php esc_html_e( 'Awaiting product selection…', 'bakery-production-manager' ); ?></span>
			</div>
		</div>
	</div>
</script>

