<?php
/**
 * Registers admin menus.
 *
 * @package BakeryProductionManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BPM_Admin_Menu
 */
class BPM_Admin_Menu {

	/**
	 * Menu page hook suffixes.
	 *
	 * @var array
	 */
	private $hooks = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu_pages' ) );
	}

	/**
	 * Register the plugin menu and subpages.
	 *
	 * @return void
	 */
	public function register_menu_pages() {
		$capability = 'manage_woocommerce';

		$this->hooks['production'] = add_menu_page(
			__( 'Production Entry', 'bakery-production-manager' ),
			__( 'Bakery Production', 'bakery-production-manager' ),
			$capability,
			'bakery-production-manager',
			array( $this, 'render_production_page' ),
			'dashicons-buddicons-replies',
			56
		);

		$this->hooks['reports'] = add_submenu_page(
			'bakery-production-manager',
			__( 'Production Reports', 'bakery-production-manager' ),
			__( 'Reports', 'bakery-production-manager' ),
			$capability,
			'bpm-reports',
			array( $this, 'render_reports_page' )
		);

		$this->hooks['settings'] = add_submenu_page(
			'bakery-production-manager',
			__( 'Bakery Production Settings', 'bakery-production-manager' ),
			__( 'Settings', 'bakery-production-manager' ),
			$capability,
			'bpm-settings',
			array( $this, 'render_settings_page' )
		);

		do_action( 'bpm_admin_menu_registered', $this->hooks );
	}

	/**
	 * Render production entry page.
	 *
	 * @return void
	 */
	public function render_production_page() {
		$helpers    = bpm( 'helpers' );
		$unit_types = $helpers ? $helpers->get_unit_types() : array( 'kg', 'litre', 'piece' );

		include BPM_PLUGIN_DIR . 'admin/views/page-production.php';
	}

	/**
	 * Render reports page.
	 *
	 * @return void
	 */
	public function render_reports_page() {
		$helpers    = bpm( 'helpers' );
		$unit_types = $helpers ? $helpers->get_unit_types() : array();

		include BPM_PLUGIN_DIR . 'admin/views/page-reports.php';
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$helpers  = bpm( 'helpers' );
		$settings = $helpers ? $helpers->get_settings() : array();

		include BPM_PLUGIN_DIR . 'admin/views/page-settings.php';
	}

	/**
	 * Public accessor for screen hooks.
	 *
	 * @return array
	 */
	public function get_hooks() {
		return $this->hooks;
	}
}
