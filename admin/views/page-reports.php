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

<script>
  (function() {
    try {
      if (typeof window.bpmReports === 'undefined') {
        window.bpmReports = {
          ajaxUrl: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
          nonce: '<?php echo esc_js( wp_create_nonce( 'bpm_ajax_nonce' ) ); ?>',
          messages: {
            noData: '<?php echo esc_js( __( 'No data found for the selected filters.', 'bakery-production-manager' ) ); ?>',
            csvError: '<?php echo esc_js( __( 'Unable to export CSV at this time.', 'bakery-production-manager' ) ); ?>',
            startRequired: '<?php echo esc_js( __( 'Please select a start date before running the report.', 'bakery-production-manager' ) ); ?>',
          },
          labels: {
            produced: '<?php echo esc_js( __( 'Produced', 'bakery-production-manager' ) ); ?>',
            wasted: '<?php echo esc_js( __( 'Wasted', 'bakery-production-manager' ) ); ?>',
            sold: '<?php echo esc_js( __( 'Sold', 'bakery-production-manager' ) ); ?>',
            oversold: '<?php echo esc_js( __( 'Oversold vs Produced', 'bakery-production-manager' ) ); ?>',
            noData: '<?php echo esc_js( __( 'No data available.', 'bakery-production-manager' ) ); ?>',
          }
        };
      }

      function injectScript(src, onload) {
        var el = document.createElement('script');
        el.src = src;
        el.async = false;
        if (onload) { el.onload = onload; }
        document.body.appendChild(el);
      }

      function injectStyle(href) {
        var el = document.createElement('link');
        el.rel = 'stylesheet';
        el.href = href;
        document.head.appendChild(el);
      }

      if (typeof window.BPMUtils === 'undefined') {
        injectScript('<?php echo esc_js( BPM_PLUGIN_URL . 'assets/js/bpm-utils.js' ); ?>');
      }
      var hasSelect2 = !!(jQuery && jQuery.fn && jQuery.fn.select2);
      var hasSelectWoo = !!(jQuery && jQuery.fn && jQuery.fn.selectWoo);
      if (!hasSelect2 && !hasSelectWoo) {
        <?php if ( defined( 'WC_PLUGIN_FILE' ) ) :
          $selectwoo_js  = plugins_url( 'assets/js/selectWoo/selectWoo.full.min.js', WC_PLUGIN_FILE );
          $selectwoo_css = plugins_url( 'assets/css/select2.css', WC_PLUGIN_FILE );
        ?>
        injectStyle('<?php echo esc_js( $selectwoo_css ); ?>');
        injectScript('<?php echo esc_js( $selectwoo_js ); ?>');
        <?php else : ?>
        injectStyle('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        injectScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js');
        <?php endif; ?>
      }
      var hasReports = false;
      var scripts = document.getElementsByTagName('script');
      for (var i = 0; i < scripts.length; i++) { if (scripts[i].src && scripts[i].src.indexOf('assets/js/reports.js') !== -1) { hasReports = true; break; } }
      if (!hasReports) {
        injectScript('<?php echo esc_js( BPM_PLUGIN_URL . 'assets/js/reports.js' ); ?>');
      }
    } catch (e) {}
  })();
</script>
