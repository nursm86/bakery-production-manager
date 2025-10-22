<?php
/**
 * Handles plugin activation tasks.
 *
 * @package BakeryProductionManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BPM_Activator
 */
class BPM_Activator {

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::maybe_seed_settings();
	}

	/**
	 * Create custom database tables.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'bakery_production_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			quantity_produced FLOAT NOT NULL DEFAULT 0,
			quantity_wasted FLOAT NOT NULL DEFAULT 0,
			unit_type VARCHAR(20) NOT NULL DEFAULT '',
			note TEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Populate default settings when option is missing.
	 *
	 * @return void
	 */
	private static function maybe_seed_settings() {
			$defaults = array(
				'unit_types'           => array( 'kg', 'litre', 'piece' ),
				'enable_manage_stock'  => 1,
				'summary_email'        => '',
			);

		$existing = get_option( 'bpm_settings', array() );

		if ( empty( $existing ) ) {
			add_option( 'bpm_settings', $defaults );
			return;
		}

		$merged = wp_parse_args( $existing, $defaults );
		update_option( 'bpm_settings', $merged );
	}
}
