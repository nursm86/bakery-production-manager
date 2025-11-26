<?php
/**
 * Handles admin assets.
 *
 * @package BakeryProductionManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BPM_Assets
 */
class BPM_Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue plugin styles and scripts for admin pages.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 *
	 * @return void
	 */
    public function enqueue_admin_assets( $hook_suffix ) {
        // Load assets on any Bakery Production Manager screen
        $is_plugin_screen = ( false !== strpos( (string) $hook_suffix, 'bakery-production-manager' ) ) || ( false !== strpos( (string) $hook_suffix, 'bakery-manager' ) );
        if ( ! $is_plugin_screen ) {
            return;
        }

		$helpers    = bpm( 'helpers' );
		$unit_types = $helpers ? $helpers->get_unit_types() : array( 'kg', 'litre', 'piece' );

		wp_enqueue_style(
			'bpm-admin',
			BPM_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			BPM_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'bpm-tailwind',
			'https://cdn.tailwindcss.com',
			array(),
			'3.4.1',
			false
		);

		wp_enqueue_style(
			'bpm-select2',
			'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
			array(),
			'4.1.0'
		);

		wp_enqueue_script(
			'bpm-select2',
			'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
			array( 'jquery' ),
			'4.1.0',
			true
		);

		wp_enqueue_script(
			'bpm-utils',
			BPM_PLUGIN_URL . 'assets/js/bpm-utils.js',
			array( 'jquery' ),
			BPM_PLUGIN_VERSION,
			true
		);

		// Check for both old toplevel hook (just in case) and new submenu hook
		if ( 'toplevel_page_bakery-production-manager' === $hook_suffix || false !== strpos( $hook_suffix, 'bpm-production' ) ) {
			wp_enqueue_script(
				'bpm-production',
				BPM_PLUGIN_URL . 'assets/js/production.js',
				array( 'jquery', 'bpm-select2', 'bpm-utils' ),
				BPM_PLUGIN_VERSION,
				true
			);

			wp_localize_script(
				'bpm-production',
				'bpmProduction',
				array(
					'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
					'nonce'         => wp_create_nonce( 'bpm_ajax_nonce' ),
					'rimNonce'      => wp_create_nonce( 'rim_admin_nonce' ),
					'unitTypes'     => $unit_types,
					'debug'         => true,
					'labels'        => array(
						'product'      => __( 'Product', 'bakery-production-manager' ),
						'material'     => __( 'Material', 'bakery-production-manager' ),
						'totalProduced'=> __( 'Total Produced', 'bakery-production-manager' ),
						'totalWasted'  => __( 'Total Wasted', 'bakery-production-manager' ),
						'submittedBy'  => __( 'Submitted by', 'bakery-production-manager' ),
					),
					'messages'      => array(
						'rowRemoved'    => __( 'Row removed.', 'bakery-production-manager' ),
						'saved'         => __( 'Production saved successfully.', 'bakery-production-manager' ),
						'validation'    => __( 'Please complete all required fields before saving.', 'bakery-production-manager' ),
						'noEntries'     => __( 'Add at least one product to save production.', 'bakery-production-manager' ),
						'productionDateRequired' => __( 'Please choose a production date.', 'bakery-production-manager' ),
						'noHistory'     => __( 'No production history found yet.', 'bakery-production-manager' ),
						'error'         => __( 'Something went wrong. Please try again.', 'bakery-production-manager' ),
					),
				)
			);
		}

        if ( false !== strpos( (string) $hook_suffix, 'bpm-reports' ) ) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                array(),
                '4.4.1',
                true
            );

            wp_enqueue_script(
                'bpm-reports',
                BPM_PLUGIN_URL . 'assets/js/reports.js',
                array( 'jquery', 'bpm-select2', 'chartjs', 'bpm-utils' ),
                BPM_PLUGIN_VERSION,
                true
            );

            wp_localize_script(
                'bpm-reports',
                'bpmReports',
                array(
                    'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                    'nonce'      => wp_create_nonce( 'bpm_ajax_nonce' ),
                    'messages'   => array(
                        'noData'       => __( 'No data found for the selected filters.', 'bakery-production-manager' ),
                        'csvError'     => __( 'Unable to export CSV at this time.', 'bakery-production-manager' ),
                        'startRequired' => __( 'Please select a start date before running the report.', 'bakery-production-manager' ),
                    ),
                    'labels'     => array(
                        'produced' => __( 'Produced', 'bakery-production-manager' ),
                        'wasted'   => __( 'Wasted', 'bakery-production-manager' ),
                        'sold'     => __( 'Sold', 'bakery-production-manager' ),
                        'oversold' => __( 'Oversold vs Produced', 'bakery-production-manager' ),
                        'noData'   => __( 'No data available.', 'bakery-production-manager' ),
                    ),
                )
            );
        }

        if ( false !== strpos( (string) $hook_suffix, 'bpm-settings' ) ) {
            wp_enqueue_script(
                'bpm-settings',
                BPM_PLUGIN_URL . 'assets/js/settings.js',
                array( 'jquery', 'bpm-select2', 'bpm-utils' ),
                BPM_PLUGIN_VERSION,
                true
            );

			wp_localize_script(
				'bpm-settings',
				'bpmSettings',
                array(
                    'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                    'nonce'     => wp_create_nonce( 'bpm_ajax_nonce' ),
                    'unitTypes' => $unit_types,
                    'messages'  => array(
                        'saved' => __( 'Settings updated successfully.', 'bakery-production-manager' ),
                        'error' => __( 'Unable to save settings. Please try again.', 'bakery-production-manager' ),
                    ),
                )
            );
        }
        if ( 'toplevel_page_bakery-manager' === $hook_suffix ) {
            wp_enqueue_style(
                'bpm-dashboard',
                BPM_PLUGIN_URL . 'admin/css/dashboard.css',
                array( 'bpm-admin' ),
                BPM_PLUGIN_VERSION
            );

            wp_enqueue_script(
                'bpm-dashboard',
                BPM_PLUGIN_URL . 'assets/js/dashboard.js',
                array( 'jquery', 'bpm-select2', 'bpm-utils' ),
                BPM_PLUGIN_VERSION,
                true
            );

            wp_localize_script(
                'bpm-dashboard',
                'bpmDashboard',
                array(
                    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'bpm_ajax_nonce' ),
                    'rimNonce'=> wp_create_nonce( 'rim_admin_nonce' ),
                    'labels'  => array(
                        'searchPlaceholder' => __( 'Search for a product...', 'bakery-production-manager' ),
                    ),
                    'messages' => array(
                        'saved'      => __( 'Production saved successfully.', 'bakery-production-manager' ),
                        'usageSaved' => __( 'Inventory usage recorded.', 'bakery-production-manager' ),
                        'error'      => __( 'Something went wrong.', 'bakery-production-manager' ),
                        'validation' => __( 'Please fill in all fields.', 'bakery-production-manager' ),
                        'enterCookQty' => __( 'Enter quantity to cook:', 'bakery-production-manager' ),
                        'enterWasteQty'=> __( 'Enter quantity wasted:', 'bakery-production-manager' ),
                        'invalidQty'   => __( 'Invalid quantity.', 'bakery-production-manager' ),
                        'wasteRecorded'=> __( 'Waste recorded successfully.', 'bakery-production-manager' ),
                    ),
                )
            );
        }
    }
}
