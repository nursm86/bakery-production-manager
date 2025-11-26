<?php
/**
 * Handles WordPress admin integration.
 *
 * @package RestaurantInventoryManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RIM_Admin
 */
class RIM_Admin {

	/**
	 * Bootstraps hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_low_stock_notice' ) );
	}

	/**
	 * Registers admin menus.
	 *
	 * @return void
	 */
	public function register_menus() {
		if ( ! rim_user_can_manage() ) {
			return;
		}

		$capability = rim_get_manage_capability();

		// Raw Materials
		add_submenu_page(
			'bakery-manager',
			__( 'Raw Materials', 'restaurant-inventory-manager' ),
			__( 'Inventory', 'restaurant-inventory-manager' ),
			$capability,
			'rim-raw-materials',
			array( $this, 'render_raw_materials' )
		);

		add_submenu_page(
			'bakery-manager',
			__( 'Stock Transactions', 'restaurant-inventory-manager' ),
			__( 'Stock Transactions', 'restaurant-inventory-manager' ),
			$capability,
			'rim-stock-transactions',
			array( $this, 'render_stock_transactions' )
		);

		add_submenu_page(
			'bakery-manager',
			__( 'Inventory Reports', 'restaurant-inventory-manager' ),
			__( 'Inventory Reports', 'restaurant-inventory-manager' ),
			$capability,
			'rim-reports',
			array( $this, 'render_reports' )
		);

		add_submenu_page(
			'bakery-manager',
			__( 'Inventory Settings', 'restaurant-inventory-manager' ),
			__( 'Inventory Settings', 'restaurant-inventory-manager' ),
			$capability,
			'rim-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Enqueues assets on plugin screens.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 0 !== strpos( $page, 'rim-' ) ) {
			return;
		}

		$settings    = rim_get_settings();
		$settings    = rim_get_settings();
		// DataTables CSS
		wp_enqueue_style(
			'rim-datatables',
			'https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css',
			array(),
			'1.13.7'
		);
		$style_deps[] = 'rim-datatables';

		$css_file = BPM_PLUGIN_DIR . 'admin/css/rim-admin.css';
		wp_enqueue_style(
			'rim-admin',
			BPM_PLUGIN_URL . 'admin/css/rim-admin.css',
			$style_deps,
			file_exists( $css_file ) ? filemtime( $css_file ) : BPM_PLUGIN_VERSION
		);

		// DataTables JS
		wp_enqueue_script(
			'rim-datatables',
			'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js',
			array( 'jquery' ),
			'1.13.7',
			true
		);
		$script_deps[] = 'rim-datatables';

		$js_file = BPM_PLUGIN_DIR . 'admin/js/rim-admin.js';
		wp_enqueue_script(
			'rim-admin',
			BPM_PLUGIN_URL . 'admin/js/rim-admin.js',
			$script_deps,
			file_exists( $js_file ) ? filemtime( $js_file ) : BPM_PLUGIN_VERSION,
			true
		);

		// Alpine JS
		wp_enqueue_script(
			'rim-alpine',
			'https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js',
			array( 'rim-admin' ),
			'3.13.3',
			true
		);

		wp_localize_script(
			'rim-admin',
			'rimAdminData',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'rim_admin_nonce' ),
				'settings' => array(
					'units'         => $settings['units_list'],
					'alertsEnabled' => ! empty( $settings['alerts_enabled'] ),
				),
				'i18n'     => array(
					'success'            => __( 'Success', 'restaurant-inventory-manager' ),
					'error'              => __( 'Error', 'restaurant-inventory-manager' ),
					'confirmDelete'      => __( 'Are you sure you want to delete this material?', 'restaurant-inventory-manager' ),
					'loading'            => __( 'Loading…', 'restaurant-inventory-manager' ),
					'duplicateMaterial'  => __( 'A material with that name already exists.', 'restaurant-inventory-manager' ),
					'invalidQuantity'    => __( 'Please enter a valid quantity.', 'restaurant-inventory-manager' ),
					'negativeStockError' => __( 'Not enough stock available for this operation.', 'restaurant-inventory-manager' ),
					'editField'          => __( 'Edit value', 'restaurant-inventory-manager' ),
					'edit'               => __( 'Edit', 'restaurant-inventory-manager' ),
					'delete'             => __( 'Delete', 'restaurant-inventory-manager' ),
					'add'                => __( 'Add', 'restaurant-inventory-manager' ),
					'use'                => __( 'Use', 'restaurant-inventory-manager' ),
				),
			)
		);
	}

	/**
	 * Renders Raw Materials page.
	 *
	 * @return void
	 */
	public function render_raw_materials() {
		if ( ! rim_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'restaurant-inventory-manager' ) );
		}

		require BPM_PLUGIN_DIR . 'admin/views/inventory/raw-materials.php';
	}

	/**
	 * Renders Stock Transactions page.
	 *
	 * @return void
	 */
	public function render_stock_transactions() {
		if ( ! rim_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'restaurant-inventory-manager' ) );
		}

		require BPM_PLUGIN_DIR . 'admin/views/inventory/stock-transactions.php';
	}

	/**
	 * Renders Reports page.
	 *
	 * @return void
	 */
	public function render_reports() {
		if ( ! rim_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'restaurant-inventory-manager' ) );
		}

		require BPM_PLUGIN_DIR . 'admin/views/inventory/reports.php';
	}

	/**
	 * Renders Settings page.
	 *
	 * @return void
	 */
	public function render_settings() {
		if ( ! rim_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'restaurant-inventory-manager' ) );
		}

		require BPM_PLUGIN_DIR . 'admin/views/inventory/settings.php';
	}

	/**
	 * Displays low stock admin notices.
	 *
	 * @return void
	 */
	public function render_low_stock_notice() {
		if ( ! rim_user_can_manage() ) {
			return;
		}

		$notice = get_transient( 'rim_low_stock_notice' );

		if ( empty( $notice ) || ! is_array( $notice ) ) {
			return;
		}

		delete_transient( 'rim_low_stock_notice' );

		$message = sprintf(
			/* translators: 1: material name, 2: quantity, 3: warning quantity */
			esc_html__( '%1$s stock is low. Current quantity: %2$s (warning threshold: %3$s).', 'restaurant-inventory-manager' ),
			esc_html( $notice['name'] ),
			esc_html( rim_format_quantity( $notice['quantity'] ) ),
			esc_html( rim_format_quantity( $notice['warning_quantity'] ) )
		);
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php echo wp_kses_post( $message ); ?></p>
		</div>
		<?php
	}
}
