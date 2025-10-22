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
	}

	/**
	 * Ensure request is authorised.
	 *
	 * @return void
	 */
	private function verify_request() {
		check_ajax_referer( 'bpm_ajax_nonce', 'nonce' );

		if ( ! BPM_Helpers::current_user_can_manage() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'bakery-production-manager' ),
				),
				403
			);
		}
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

		wp_send_json_success(
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
			wp_send_json_error(
				array(
					'message' => __( 'Invalid product.', 'bakery-production-manager' ),
				),
				400
			);
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			wp_send_json_error(
				array(
					'message' => __( 'Product not found.', 'bakery-production-manager' ),
				),
				404
			);
		}

		$stock        = $product->get_stock_quantity();
		$manage_stock = $product->get_manage_stock();

		wp_send_json_success(
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
			wp_send_json_error(
				array(
					'message' => __( 'No production entries received.', 'bakery-production-manager' ),
				),
				400
			);
		}

		$current_user_id = get_current_user_id();
		$timestamp       = current_time( 'mysql' );
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
					'unit_type'         => $unit_type,
					'note'              => $note,
					'created_by'        => $current_user_id,
					'created_at'        => $timestamp,
				),
				array(
					'%d',
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
			wp_send_json_error(
				array(
					'message' => implode( ' ', $errors ),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'rows'          => $response_rows,
				'totalProduced' => $total_produced,
				'totalWasted'   => $total_wasted,
				'warnings'      => $errors,
				'timestamp'     => $timestamp,
				'created_by'    => $current_user_id,
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

		$reports = bpm( 'reports' );

		if ( ! $reports ) {
			wp_send_json_error(
				array(
					'message' => __( 'Reports module unavailable.', 'bakery-production-manager' ),
				),
				500
			);
		}

		$data = $reports->get_report( $args );

		wp_send_json_success( $data );
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

		$reports = bpm( 'reports' );

		if ( ! $reports ) {
			wp_send_json_error(
				array(
					'message' => __( 'Reports module unavailable.', 'bakery-production-manager' ),
				),
				500
			);
		}

		$data = $reports->get_report( $args );
		$csv  = $reports->generate_csv( $data, $args );

		wp_send_json_success(
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
			wp_send_json_error(
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

		wp_send_json_success(
			array(
				'settings' => $helpers->get_settings(),
			)
		);
	}
}
