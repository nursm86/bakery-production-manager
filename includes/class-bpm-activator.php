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
			previous_stock FLOAT NOT NULL DEFAULT 0,
			new_stock FLOAT NOT NULL DEFAULT 0,
			unit_type VARCHAR(50) NOT NULL DEFAULT '',
			note TEXT NULL,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$cold_storage_table = $wpdb->prefix . 'bakery_cold_storage';
		$cold_storage_sql = "CREATE TABLE {$cold_storage_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			quantity FLOAT NOT NULL DEFAULT 0,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY product_id (product_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		dbDelta( $cold_storage_sql );
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

	/**
	 * Ensure schema is up to date for existing installations.
	 *
	 * @return void
	 */
	public static function ensure_schema() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bakery_production_log';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return;
		}

		self::maybe_add_column( $table_name, 'previous_stock', "ALTER TABLE {$table_name} ADD COLUMN previous_stock FLOAT NOT NULL DEFAULT 0 AFTER quantity_wasted" );
		self::maybe_add_column( $table_name, 'new_stock', "ALTER TABLE {$table_name} ADD COLUMN new_stock FLOAT NOT NULL DEFAULT 0 AFTER previous_stock" );
		
		// Ensure unit_type is long enough
		$wpdb->query( "ALTER TABLE {$table_name} MODIFY COLUMN unit_type VARCHAR(50) NOT NULL DEFAULT ''" );
	}

	/**
	 * Conditionally add a column when missing.
	 *
	 * @param string $table  Table name.
	 * @param string $column Column name to check.
	 * @param string $sql    SQL statement to execute when missing.
	 *
	 * @return void
	 */
	private static function maybe_add_column( $table, $column, $sql ) {
		global $wpdb;

		$exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $exists ) {
			return;
		}

		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
