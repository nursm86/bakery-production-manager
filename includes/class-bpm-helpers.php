<?php
/**
 * Shared helpers.
 *
 * @package BakeryProductionManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BPM_Helpers
 */
class BPM_Helpers {

	/**
	 * Cached settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = $this->get_settings();
	}

	/**
	 * Retrieve plugin settings merged with defaults.
	 *
	 * @return array
	 */
	public function get_settings() {
		$defaults = array(
			'unit_types'              => array( 'kg', 'litre', 'piece' ),
			'enable_manage_stock'     => 1,
			'enable_decimal_quantities' => 0,
			'summary_email'           => '',
		);

		$settings = get_option( 'bpm_settings', array() );

		if ( empty( $settings ) ) {
			return $defaults;
		}

		$settings['unit_types'] = isset( $settings['unit_types'] ) && is_array( $settings['unit_types'] )
			? array_values( array_filter( array_map( 'sanitize_text_field', $settings['unit_types'] ) ) )
			: $defaults['unit_types'];

		$settings['enable_manage_stock']     = isset( $settings['enable_manage_stock'] ) ? (int) (bool) $settings['enable_manage_stock'] : $defaults['enable_manage_stock'];
		$settings['enable_decimal_quantities'] = isset( $settings['enable_decimal_quantities'] ) ? (int) (bool) $settings['enable_decimal_quantities'] : $defaults['enable_decimal_quantities'];
		$settings['summary_email']           = isset( $settings['summary_email'] ) ? sanitize_email( $settings['summary_email'] ) : $defaults['summary_email'];

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Persist settings.
	 *
	 * @param array $settings Settings payload.
	 *
	 * @return void
	 */
	public function update_settings( $settings ) {
		$settings = wp_parse_args(
			$settings,
			array(
				'unit_types'              => array(),
				'enable_manage_stock'     => 0,
				'enable_decimal_quantities' => 0,
				'summary_email'           => '',
			)
		);

		$unit_types = array();

		if ( ! empty( $settings['unit_types'] ) && is_array( $settings['unit_types'] ) ) {
			foreach ( $settings['unit_types'] as $unit ) {
				$unit = sanitize_text_field( $unit );
				if ( '' !== $unit ) {
					$unit_types[] = $unit;
				}
			}
		}

		$payload = array(
			'unit_types'              => ! empty( $unit_types ) ? $unit_types : array( 'kg', 'litre', 'piece' ),
			'enable_manage_stock'     => ! empty( $settings['enable_manage_stock'] ) ? 1 : 0,
			'enable_decimal_quantities' => ! empty( $settings['enable_decimal_quantities'] ) ? 1 : 0,
			'summary_email'           => ! empty( $settings['summary_email'] ) ? sanitize_email( $settings['summary_email'] ) : '',
		);

		update_option( 'bpm_settings', $payload );
		$this->settings = $payload;
	}

	/**
	 * Retrieve unit types list.
	 *
	 * @return array
	 */
	public function get_unit_types() {
		return $this->settings['unit_types'];
	}

	/**
	 * Whether current user can manage the plugin.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Sanitize a float/decimal value.
	 *
	 * @param mixed $value Value to sanitize.
	 *
	 * @return float
	 */
	public static function sanitize_float( $value ) {
		if ( function_exists( 'wc_format_decimal' ) ) {
			return (float) wc_format_decimal( $value, 4 );
		}

		return (float) filter_var( $value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
	}

	/**
	 * Prepare date range boundaries.
	 *
	 * @param string|null $start Start date (Y-m-d).
	 * @param string|null $end   End date (Y-m-d).
	 *
	 * @return array{start:string,end:string}
	 */
	public static function prepare_date_range( $start, $end ) {
		$today = current_time( 'Y-m-d' );

		if ( empty( $start ) ) {
			$start = date( 'Y-m-d', strtotime( $today . ' -6 days' ) );
		}

		if ( empty( $end ) ) {
			$end = $today;
		}

		$start_dt = date_create_from_format( 'Y-m-d', $start );
		$end_dt   = date_create_from_format( 'Y-m-d', $end );

		if ( false === $start_dt || false === $end_dt ) {
			$start_dt = date_create_from_format( 'Y-m-d', $today );
			$end_dt   = clone $start_dt;
		}

		if ( $start_dt > $end_dt ) {
			$tmp      = $start_dt;
			$start_dt = $end_dt;
			$end_dt   = $tmp;
		}

		return array(
			'start' => $start_dt->format( 'Y-m-d 00:00:00' ),
			'end'   => $end_dt->format( 'Y-m-d 23:59:59' ),
		);
	}

	/**
	 * Drop any buffered output captured during AJAX calls so JSON responses stay valid.
	 *
	 * @return void
	 */
	public static function clean_ajax_output_buffer() {
		if ( ! defined( 'BPM_AJAX_BUFFER_LEVEL' ) ) {
			return;
		}

		while ( ob_get_level() >= BPM_AJAX_BUFFER_LEVEL ) {
			ob_end_clean();
		}
	}

	/**
	 * Normalise a provided datetime-local string into MySQL format.
	 *
	 * @param string $input Datetime string from the UI.
	 *
	 * @return string
	 */
	public static function normalize_datetime( $input ) {
		if ( empty( $input ) ) {
			return current_time( 'mysql' );
		}

		$input    = trim( str_replace( 'T', ' ', $input ) );
		$timezone = wp_timezone();

		try {
			$datetime = new \DateTime( $input, $timezone );
		} catch ( \Exception $e ) {
			$timestamp = strtotime( $input );
			if ( false === $timestamp ) {
				return current_time( 'mysql' );
			}

			$datetime = new \DateTime( 'now', $timezone );
			$datetime->setTimestamp( $timestamp );
		}

		return $datetime->format( 'Y-m-d H:i:s' );
	}
}
