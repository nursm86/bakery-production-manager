<?php
/**
 * AJAX endpoints.
 *
 * @package BakeryProductionManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BPM_Ajax
 */
class BPM_Ajax {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_bpm_search_products', array( $this, 'search_products' ) );
		add_action( 'wp_ajax_bpm_get_product_stock', array( $this, 'get_product_stock' ) );
		add_action( 'wp_ajax_bpm_save_production_entries', array( $this, 'save_production_entries' ) );
		add_action( 'wp_ajax_bpm_get_report_data', array( $this, 'get_report_data' ) );
		add_action( 'wp_ajax_bpm_export_csv', array( $this, 'export_csv' ) );
		add_action( 'wp_ajax_bpm_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_bpm_get_latest_summary', array( $this, 'get_latest_summary' ) );
	}

	/**
	 * Ensure request is authorised.
	 *
	 * @return void
	 */
	private function verify_request() {
		check_ajax_referer( 'bpm_ajax_nonce', 'nonce' );

		if ( ! BPM_Helpers::current_user_can_manage() ) {
			$this->send_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'bakery-production-manager' ),
				),
				403
			);
		}
	}

	/**
	 * Send a JSON error response ensuring buffered output is discarded first.
	 *
	 * @param array $data        Payload.
	 * @param int   $status_code HTTP status.
	 *
	 * @return void
	 */
	private function send_error( $data, $status_code = 400 ) {
		BPM_Helpers::clean_ajax_output_buffer();
		wp_send_json_error( $data, $status_code );
	}

	/**
	 * Send a JSON success response ensuring buffered output is discarded first.
	 *
	 * @param mixed $data        Payload.
	 * @param int   $status_code HTTP status.
	 *
	 * @return void
	 */
	private function send_success( $data, $status_code = 200 ) {
		BPM_Helpers::clean_ajax_output_buffer();
		wp_send_json_success( $data, $status_code );
	}

	/**
	 * AJAX: Search WooCommerce products for Select2 dropdown.
	 *
	 * @return void
	 */
	public function search_products() {
		global $wpdb;

		$this->verify_request();

		$term  = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$page  = isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1;
		$limit = 20;
		$offset = ( $page - 1 ) * $limit;

		$product_ids = array();
		$total       = 0;

		if ( '' !== $term ) {
			// Search within title, SKU, and short description.
			$like_term = '%' . $wpdb->esc_like( $term ) . '%';

			$sql = $wpdb->prepare(
				"SELECT DISTINCT p.ID
				FROM {$wpdb->posts} AS p
				LEFT JOIN {$wpdb->postmeta} AS sku_meta
					ON ( p.ID = sku_meta.post_id AND sku_meta.meta_key = '_sku' )
				WHERE p.post_type = 'product'
					AND p.post_status = 'publish'
					AND (
						p.post_title LIKE %s
						OR p.post_excerpt LIKE %s
						OR ( sku_meta.meta_value IS NOT NULL AND sku_meta.meta_value LIKE %s )
					)
				ORDER BY p.post_title ASC
				LIMIT %d OFFSET %d",
				$like_term,
				$like_term,
				$like_term,
				$limit,
				$offset
			);

			$product_ids = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			$count_sql = $wpdb->prepare(
				"SELECT COUNT( DISTINCT p.ID )
				FROM {$wpdb->posts} AS p
				LEFT JOIN {$wpdb->postmeta} AS sku_meta
					ON ( p.ID = sku_meta.post_id AND sku_meta.meta_key = '_sku' )
				WHERE p.post_type = 'product'
					AND p.post_status = 'publish'
					AND (
						p.post_title LIKE %s
						OR p.post_excerpt LIKE %s
						OR ( sku_meta.meta_value IS NOT NULL AND sku_meta.meta_value LIKE %s )
					)",
				$like_term,
				$like_term,
				$like_term
			);

			$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$args = array(
				'limit'      => $limit,
				'status'     => array( 'publish' ),
				'orderby'    => 'title',
				'order'      => 'ASC',
				'paginate'   => true,
				'return'     => 'ids',
				'page'       => $page,
			);

			$query       = wc_get_products( $args );
			$product_ids = is_object( $query ) && isset( $query->products ) ? (array) $query->products : array();
			$total       = is_object( $query ) && isset( $query->total ) ? (int) $query->total : 0;
		}

		$results = array();
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$results[] = array(
				'id'   => $product->get_id(),
				'text' => $product->get_formatted_name(),
			);
		}

		$this->send_success(
			array(
				'results'    => $results,
				'pagination' => array(
					'more' => ( $page * $limit ) < $total,
				),
			)
		);
	}

	/**
	 * AJAX: Get current stock for selected product.
	 *
	 * @return void
	 */
	public function get_product_stock() {
		$this->verify_request();

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( ! $product_id ) {
			$this->send_error(
				array(
					'message' => __( 'Invalid product.', 'bakery-production-manager' ),
				),
				400
			);
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			$this->send_error(
				array(
					'message' => __( 'Product not found.', 'bakery-production-manager' ),
				),
				404
			);
		}

		$stock        = $product->get_stock_quantity();
		$manage_stock = $product->get_manage_stock();

		$this->send_success(
			array(
				'product_id'    => $product_id,
				'stock'         => is_null( $stock ) ? 0 : (float) $stock,
				'manage_stock'  => $manage_stock ? 1 : 0,
				'product_name'  => $product->get_formatted_name(),
			)
		);
	}

	/**
	 * AJAX: Save production entries and update stock.
	 *
	 * @return void
	 */
	public function save_production_entries() {
		global $wpdb;

		$this->verify_request();

		$raw_entries = isset( $_POST['entries'] ) ? wp_unslash( $_POST['entries'] ) : array();

		if ( is_string( $raw_entries ) ) {
			$decoded = json_decode( $raw_entries, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$raw_entries = $decoded;
			}
		}

		if ( empty( $raw_entries ) || ! is_array( $raw_entries ) ) {
			$this->send_error(
				array(
					'message' => __( 'No production entries received.', 'bakery-production-manager' ),
				),
				400
			);
		}

		$current_user_id = get_current_user_id();
		$production_date = isset( $_POST['production_date'] ) ? sanitize_text_field( wp_unslash( $_POST['production_date'] ) ) : '';
		$timestamp       = BPM_Helpers::normalize_datetime( $production_date );
		$table_name      = $wpdb->prefix . 'bakery_production_log';

		$helpers  = bpm( 'helpers' );
		$settings = $helpers ? $helpers->get_settings() : array(
			'enable_manage_stock' => 1,
		);

		$response_rows   = array();
		$total_produced  = 0;
		$total_wasted    = 0;
		$errors          = array();

		foreach ( $raw_entries as $entry ) {
			$product_id        = isset( $entry['product_id'] ) ? absint( $entry['product_id'] ) : 0;
			$quantity_produced = isset( $entry['quantity_produced'] ) ? BPM_Helpers::sanitize_float( $entry['quantity_produced'] ) : 0;
			$quantity_wasted   = isset( $entry['quantity_wasted'] ) ? BPM_Helpers::sanitize_float( $entry['quantity_wasted'] ) : 0;
			$unit_type         = isset( $entry['unit_type'] ) ? sanitize_text_field( $entry['unit_type'] ) : '';
			$note              = isset( $entry['note'] ) ? sanitize_textarea_field( $entry['note'] ) : '';

			if ( ! $product_id || $quantity_produced < 0 || $quantity_wasted < 0 ) {
				$errors[] = __( 'Invalid production entry detected.', 'bakery-production-manager' );
				continue;
			}

			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				$errors[] = sprintf(
					/* translators: %d product ID. */
					__( 'Product with ID %d could not be found.', 'bakery-production-manager' ),
					$product_id
				);
				continue;
			}

			$previous_stock = $product->get_stock_quantity();
			if ( null === $previous_stock ) {
				$previous_stock = 0;
			}

			if ( ! empty( $settings['enable_manage_stock'] ) && ! $product->get_manage_stock() ) {
				$product->set_manage_stock( true );
			}

			$new_stock = (float) $previous_stock + $quantity_produced - $quantity_wasted;
			if ( $new_stock < 0 ) {
				$new_stock = 0;
			}

			$product->set_stock_quantity( $new_stock );
			$product->set_stock_status( $new_stock > 0 ? 'instock' : 'outofstock' );
			$product->save();

			$wpdb->insert(
				$table_name,
				array(
					'product_id'        => $product_id,
					'quantity_produced' => $quantity_produced,
					'quantity_wasted'   => $quantity_wasted,
					'previous_stock'    => $previous_stock,
					'new_stock'         => $new_stock,
					'unit_type'         => $unit_type,
					'note'              => $note,
					'created_by'        => $current_user_id,
					'created_at'        => $timestamp,
				),
				array(
					'%d',
					'%f',
					'%f',
					'%f',
					'%f',
					'%s',
					'%s',
					'%d',
					'%s',
				)
			);

			$response_rows[] = array(
				'product_id'     => $product_id,
				'product_name'   => $product->get_formatted_name(),
				'previous_stock' => (float) $previous_stock,
				'produced'       => $quantity_produced,
				'wasted'         => $quantity_wasted,
				'new_stock'      => $new_stock,
			);

			$total_produced += $quantity_produced;
			$total_wasted   += $quantity_wasted;
		}

		if ( ! empty( $errors ) && empty( $response_rows ) ) {
			$this->send_error(
				array(
					'message' => implode( ' ', $errors ),
				),
				400
			);
		}

		$this->send_success(
			array(
				'rows'          => $response_rows,
				'totalProduced' => $total_produced,
				'totalWasted'   => $total_wasted,
				'warnings'      => $errors,
				'timestamp'     => $timestamp,
				'created_by'    => $current_user_id,
				'hasHistory'    => true,
			)
		);
	}

	/**
	 * AJAX: Fetch report data.
	 *
	 * @return void
	 */
	public function get_report_data() {
		$this->verify_request();

		$args = array(
			'start_date' => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '',
			'end_date'   => isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '',
			'product_id' => isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0,
		);

		if ( empty( $args['start_date'] ) ) {
			$this->send_error(
				array(
					'message' => __( 'Please provide a start date.', 'bakery-production-manager' ),
				),
				400
			);
		}

		$reports = bpm( 'reports' );

		if ( ! $reports ) {
			$this->send_error(
				array(
					'message' => __( 'Reports module unavailable.', 'bakery-production-manager' ),
				),
				500
			);
		}

		$data = $reports->get_report( $args );

		$this->send_success( $data );
	}

	/**
	 * AJAX: Export report data to CSV.
	 *
	 * @return void
	 */
	public function export_csv() {
		$this->verify_request();

		$args = array(
			'start_date' => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '',
			'end_date'   => isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '',
			'product_id' => isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0,
		);

		if ( empty( $args['start_date'] ) ) {
			$this->send_error(
				array(
					'message' => __( 'Please provide a start date.', 'bakery-production-manager' ),
				),
				400
			);
		}

		$reports = bpm( 'reports' );

		if ( ! $reports ) {
			$this->send_error(
				array(
					'message' => __( 'Reports module unavailable.', 'bakery-production-manager' ),
				),
				500
			);
		}

		$data = $reports->get_report( $args );
		$csv  = $reports->generate_csv( $data, $args );

		$this->send_success(
			array(
				'filename' => sprintf(
					'bakery-production-report-%s.csv',
					gmdate( 'Ymd-His' )
				),
				'content'  => base64_encode( $csv ),
			)
		);
	}

	/**
	 * AJAX: Fetch the latest production summary.
	 *
	 * @return void
	 */
	public function get_latest_summary() {
		global $wpdb;

		$this->verify_request();

		$table_name = $wpdb->prefix . 'bakery_production_log';

		$latest = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT created_at, created_by FROM {$table_name} ORDER BY created_at DESC, id DESC LIMIT 1",
			ARRAY_A
		);

		if ( ! $latest ) {
			$this->send_success(
				array(
					'rows'          => array(),
					'totalProduced' => 0,
					'totalWasted'   => 0,
					'warnings'      => array(),
					'timestamp'     => '',
					'created_by'    => 0,
					'hasHistory'    => false,
				)
			);
		}

		$entries = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE created_at = %s ORDER BY id ASC",
				$latest['created_at']
			),
			ARRAY_A
		);

		$response_rows  = array();
		$total_produced = 0;
		$total_wasted   = 0;
		$warnings       = array();

		foreach ( (array) $entries as $entry ) {
			$product      = wc_get_product( (int) $entry['product_id'] );
			$product_name = $product ? $product->get_formatted_name() : sprintf(
				/* translators: %d product ID. */
				__( 'Deleted Product #%d', 'bakery-production-manager' ),
				$entry['product_id']
			);

			if ( ! $product ) {
				$warnings[] = sprintf(
					/* translators: %d product ID. */
					__( 'Product with ID %d is no longer available.', 'bakery-production-manager' ),
					$entry['product_id']
				);
			}

			$response_rows[] = array(
				'product_id'     => (int) $entry['product_id'],
				'product_name'   => $product_name,
				'previous_stock' => (float) $entry['previous_stock'],
				'produced'       => (float) $entry['quantity_produced'],
				'wasted'         => (float) $entry['quantity_wasted'],
				'new_stock'      => (float) $entry['new_stock'],
			);

			$total_produced += (float) $entry['quantity_produced'];
			$total_wasted   += (float) $entry['quantity_wasted'];
		}

		$this->send_success(
			array(
				'rows'          => $response_rows,
				'totalProduced' => $total_produced,
				'totalWasted'   => $total_wasted,
				'warnings'      => $warnings,
				'timestamp'     => $latest['created_at'],
				'created_by'    => (int) $latest['created_by'],
				'hasHistory'    => ! empty( $response_rows ),
			)
		);
	}

	/**
	 * AJAX: Save plugin settings.
	 *
	 * @return void
	 */
	public function save_settings() {
		$this->verify_request();

		$unit_types = isset( $_POST['unit_types'] ) ? (array) wp_unslash( $_POST['unit_types'] ) : array();
		$manage     = isset( $_POST['enable_manage_stock'] ) ? (int) $_POST['enable_manage_stock'] : 0;
		$email      = isset( $_POST['summary_email'] ) ? sanitize_text_field( wp_unslash( $_POST['summary_email'] ) ) : '';

		$helpers = bpm( 'helpers' );

		if ( ! $helpers ) {
			$this->send_error(
				array(
					'message' => __( 'Settings helper unavailable.', 'bakery-production-manager' ),
				),
				500
			);
		}

		$helpers->update_settings(
			array(
				'unit_types'          => $unit_types,
				'enable_manage_stock' => $manage,
				'summary_email'       => $email,
			)
		);

		$this->send_success(
			array(
				'settings' => $helpers->get_settings(),
			)
		);
	}
}
