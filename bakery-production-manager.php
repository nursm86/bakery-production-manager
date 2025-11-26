<?php
/**
 * Plugin Name: Bakery Production Manager
 * Plugin URI: https://nurislam.online
 * Description: Manage daily bakery production and keep WooCommerce stock in sync with an AJAX powered workflow.
 * Author: Md. Nur Islam
 * Author URI: https://nurislam.online
 * Text Domain: bakery-production-manager
 * Domain Path: /languages
 * GitHub Plugin URI: nursm86/bakery-production-manager
 * Primary Branch: main
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BPM_PLUGIN_VERSION' ) ) {
	define( 'BPM_PLUGIN_VERSION', '1.0.0' );
}

if ( ! defined( 'BPM_PLUGIN_DIR' ) ) {
	define( 'BPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'BPM_PLUGIN_URL' ) ) {
	define( 'BPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'BPM_PLUGIN_BASENAME' ) ) {
	define( 'BPM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'BPM_AJAX_BUFFER_LEVEL' ) && defined( 'DOING_AJAX' ) && DOING_AJAX ) {
	$requested_action = isset( $_REQUEST['action'] ) ? (string) $_REQUEST['action'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( $requested_action && 0 === strpos( strtolower( $requested_action ), 'bpm_' ) ) {
		ob_start();
		define( 'BPM_AJAX_BUFFER_LEVEL', ob_get_level() );
	}
}

require_once BPM_PLUGIN_DIR . 'includes/class-bpm-activator.php';
require_once BPM_PLUGIN_DIR . 'includes/class-bpm-deactivator.php';
require_once BPM_PLUGIN_DIR . 'includes/class-bpm-helpers.php';
require_once BPM_PLUGIN_DIR . 'includes/class-bpm-admin-menu.php';
require_once BPM_PLUGIN_DIR . 'includes/class-bpm-assets.php';
require_once BPM_PLUGIN_DIR . 'includes/class-bpm-ajax.php';
require_once BPM_PLUGIN_DIR . 'includes/class-bpm-reports.php';

// Inventory (Merged from RIM)
require_once BPM_PLUGIN_DIR . 'includes/inventory/helpers.php';
require_once BPM_PLUGIN_DIR . 'includes/inventory/class-rim-activator.php';
require_once BPM_PLUGIN_DIR . 'includes/inventory/class-rim-admin.php';
require_once BPM_PLUGIN_DIR . 'includes/inventory/class-rim-ajax.php';
require_once BPM_PLUGIN_DIR . 'includes/inventory/class-rim-email.php';

/**
 * Prepare plugin activation.
 */
function bpm_activate_plugin() {
	BPM_Activator::activate();
}

/**
 * Prepare plugin deactivation.
 */
function bpm_deactivate_plugin() {
	BPM_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'bpm_activate_plugin' );
register_deactivation_hook( __FILE__, 'bpm_deactivate_plugin' );

/**
 * Main plugin controller.
 */
final class BPM_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var BPM_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Flag to store WooCommerce availability.
	 *
	 * @var bool
	 */
	private $woocommerce_ready = false;

	/**
	 * Classes.
	 *
	 * @var array<string, mixed>
	 */
	private $container = array();

	/**
	 * Magic getter for container lookup.
	 *
	 * @param string $key Service key.
	 *
	 * @return mixed|null
	 */
	public function __get( $key ) {
		return $this->get( $key );
	}

	/**
	 * Get singleton instance.
	 *
	 * @return BPM_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Retrieve a stored service.
	 *
	 * @param string $service Service key.
	 *
	 * @return mixed|null
	 */
	public function get( $service ) {
		return isset( $this->container[ $service ] ) ? $this->container[ $service ] : null;
	}

	/**
	 * BPM_Plugin constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Bootstrap once dependencies are ready.
	 *
	 * @return void
	 */
	public function on_plugins_loaded() {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->woocommerce_ready = true;
			$this->init();
			return;
		}

		add_action(
			'admin_init',
			static function () {
				deactivate_plugins( BPM_PLUGIN_BASENAME );
			}
		);
	}

	/**
	 * Initialise core classes.
	 *
	 * @return void
	 */
	private function init() {
		BPM_Activator::ensure_schema();

		$this->container['helpers']    = new BPM_Helpers();
		$this->container['admin_menu'] = new BPM_Admin_Menu();
		$this->container['assets']     = new BPM_Assets();
		$this->container['ajax']       = new BPM_Ajax();
		$this->container['reports']    = new BPM_Reports();

		// Initialize Inventory
		if ( is_admin() ) {
			$this->container['rim_admin'] = new RIM_Admin();
			$this->container['rim_admin']->hooks();
		}
		$this->container['rim_ajax'] = new RIM_Ajax();
		$this->container['rim_ajax']->hooks();

		do_action( 'bpm_plugin_loaded', $this );
	}

	/**
	 * Display admin notice if WooCommerce is missing.
	 *
	 * @return void
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( $this->woocommerce_ready ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__( 'Bakery Production Manager requires WooCommerce to be installed and active.', 'bakery-production-manager' ) . '</p></div>';
	}
}

/**
 * Expose helper for accessing plugin container.
 *
 * @param string $service Service key.
 *
 * @return mixed|null
 */
function bpm( $service = '' ) {
	$plugin = BPM_Plugin::instance();

	if ( empty( $service ) ) {
		return $plugin;
	}

	return $plugin->get( $service );
}

BPM_Plugin::instance();
