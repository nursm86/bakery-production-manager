<?php
/**
 * Handles reporting logic.
 *
 * @package BakeryProductionManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BPM_Reports
 */
class BPM_Reports {

	/**
	 * Build aggregated report data.
	 *
	 * @param array $args Filter arguments.
	 *
	 * @return array
	 */
	public function get_report( $args = array() ) {
		global $wpdb;

		$start_date = isset( $args['start_date'] ) ? $args['start_date'] : '';
		$end_date   = isset( $args['end_date'] ) ? $args['end_date'] : '';
		$product_id = isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0;

		$range = BPM_Helpers::prepare_date_range( $start_date, $end_date );

		$table_name = $wpdb->prefix . 'bakery_production_log';

		$where_clauses = array(
			'created_at >= %s',
			'created_at <= %s',
		);
		$params        = array(
			$range['start'],
			$range['end'],
		);

		if ( $product_id ) {
			$where_clauses[] = 'product_id = %d';
			$params[]        = $product_id;
		}

		$sql = "
			SELECT
				product_id,
				SUM(quantity_produced) AS total_produced,
				SUM(quantity_wasted) AS total_wasted
			FROM {$table_name}
			WHERE " . implode( ' AND ', $where_clauses ) . '
			GROUP BY product_id
		';

		$production_rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$sales_rows = $this->get_sales_data( $range, $product_id );

		$products_data = array();
		$product_ids   = array();

		foreach ( (array) $production_rows as $row ) {
			$id = (int) $row['product_id'];

			$products_data[ $id ] = array(
				'product_id'     => $id,
				'product_name'   => '',
				'total_produced' => (float) $row['total_produced'],
				'total_wasted'   => (float) $row['total_wasted'],
				'net_added'      => (float) $row['total_produced'] - (float) $row['total_wasted'],
				'total_sold'     => 0.0,
				'current_stock'  => 0.0,
				'remaining'      => 0.0,
				'oversold'       => false,
			);
			$product_ids[] = $id;
		}

		foreach ( (array) $sales_rows as $row ) {
			$id        = (int) $row['product_id'];
			$qty_sold  = (float) $row['qty_sold'];
			$product_ids[] = $id;

			if ( ! isset( $products_data[ $id ] ) ) {
				$products_data[ $id ] = array(
					'product_id'     => $id,
					'product_name'   => '',
					'total_produced' => 0.0,
					'total_wasted'   => 0.0,
					'net_added'      => 0.0,
					'total_sold'     => $qty_sold,
					'current_stock'  => 0.0,
					'remaining'      => -1 * $qty_sold,
					'oversold'       => true,
				);
				continue;
			}

			$products_data[ $id ]['total_sold'] = $qty_sold;
		}

		$products_data = $this->hydrate_products( $products_data );

		$rows = array();
		$totals = array(
			'produced'  => 0.0,
			'wasted'    => 0.0,
			'net_added' => 0.0,
			'sold'      => 0.0,
			'remaining' => 0.0,
			'stock'     => 0.0,
		);

		foreach ( $products_data as $data ) {
			$totals['produced']  += $data['total_produced'];
			$totals['wasted']    += $data['total_wasted'];
			$totals['net_added'] += $data['net_added'];
			$totals['sold']      += $data['total_sold'];
			$totals['remaining'] += $data['remaining'];
			$totals['stock']     += $data['current_stock'];

			$rows[] = $data;
		}

		$chart = array(
			'labels'   => array_map(
				static function( $item ) {
					return $item['product_name'];
				},
				$rows
			),
			'produced' => array_map(
				static function( $item ) {
					return $item['total_produced'];
				},
				$rows
			),
			'wasted'   => array_map(
				static function( $item ) {
					return $item['total_wasted'];
				},
				$rows
			),
			'sold'     => array_map(
				static function( $item ) {
					return $item['total_sold'];
				},
				$rows
			),
		);

		$product_name = '';
		if ( $product_id ) {
			if ( ! empty( $rows ) ) {
				$product_name = $rows[0]['product_name'];
			} else {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$product_name = $product->get_formatted_name();
				}
			}
		}

		return array(
			'filters' => array(
				'start_date' => substr( $range['start'], 0, 10 ),
				'end_date'   => substr( $range['end'], 0, 10 ),
				'product_id' => $product_id,
				'product_name' => $product_name,
			),
			'rows'    => $rows,
			'totals'  => $totals,
			'chart'   => $chart,
		);
	}

	/**
	 * Fetch sales aggregated data.
	 *
	 * @param array $range      Date range.
	 * @param int   $product_id Optional filter by product.
	 *
	 * @return array
	 */
	private function get_sales_data( $range, $product_id = 0 ) {
		global $wpdb;

		$order_items      = $wpdb->prefix . 'woocommerce_order_items';
		$order_item_meta  = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$posts_table      = $wpdb->posts;

		$sql = "
			SELECT
				CAST(
					CASE
						WHEN (variation_meta.meta_value IS NOT NULL AND variation_meta.meta_value <> '' AND variation_meta.meta_value <> '0')
							THEN variation_meta.meta_value
						ELSE product_meta.meta_value
					END AS UNSIGNED
				) AS product_id,
				SUM(CAST(qty_meta.meta_value AS DECIMAL(15,4))) AS qty_sold
			FROM {$order_items} AS oi
				INNER JOIN {$posts_table} AS p ON oi.order_id = p.ID
				INNER JOIN {$order_item_meta} AS product_meta ON oi.order_item_id = product_meta.order_item_id AND product_meta.meta_key = '_product_id'
				LEFT JOIN {$order_item_meta} AS variation_meta ON oi.order_item_id = variation_meta.order_item_id AND variation_meta.meta_key = '_variation_id'
				INNER JOIN {$order_item_meta} AS qty_meta ON oi.order_item_id = qty_meta.order_item_id AND qty_meta.meta_key = '_qty'
			WHERE p.post_type = 'shop_order'
				AND p.post_status IN ( 'wc-processing', 'wc-completed' )
				AND p.post_date >= %s
				AND p.post_date <= %s
		";

		$params = array(
			$range['start'],
			$range['end'],
		);

		if ( $product_id ) {
			$sql    .= ' AND ( CAST(variation_meta.meta_value AS UNSIGNED) = %d OR CAST(product_meta.meta_value AS UNSIGNED) = %d )';
			$params[] = $product_id;
			$params[] = $product_id;
		}

		$sql .= ' GROUP BY product_id';

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Hydrate products with names, stocks, and derived metrics.
	 *
	 * @param array $products_data Products data keyed by product_id.
	 *
	 * @return array
	 */
	private function hydrate_products( $products_data ) {
		foreach ( $products_data as $product_id => $data ) {
			$product = wc_get_product( $product_id );

			if ( $product ) {
				$current_stock = $product->get_stock_quantity();
				if ( null === $current_stock ) {
					$current_stock = 0;
				}

				$data['product_name']  = $product->get_formatted_name();
				$data['current_stock'] = (float) $current_stock;
			} else {
				$data['product_name']  = sprintf(
					/* translators: %d product ID. */
					__( 'Deleted Product #%d', 'bakery-production-manager' ),
					$product_id
				);
				$data['current_stock'] = 0.0;
			}

			$data['remaining'] = (float) $data['net_added'] - (float) $data['total_sold'];

			if ( $data['current_stock'] < 0 ) {
				$data['current_stock'] = 0.0;
			}

			$data['oversold'] = $data['total_sold'] > $data['net_added'];

			$products_data[ $product_id ] = $data;
		}

		return $products_data;
	}

	/**
	 * Generate CSV string of report data.
	 *
	 * @param array $data Report data.
	 * @param array $args Filter args.
	 *
	 * @return string
	 */
	public function generate_csv( $data, $args = array() ) {
		$fh = fopen( 'php://temp', 'w+' );

		$headers = array(
			__( 'Product ID', 'bakery-production-manager' ),
			__( 'Product Name', 'bakery-production-manager' ),
			__( 'Total Produced', 'bakery-production-manager' ),
			__( 'Total Wasted', 'bakery-production-manager' ),
			__( 'Net Added', 'bakery-production-manager' ),
			__( 'Total Sold', 'bakery-production-manager' ),
			__( 'Current Stock', 'bakery-production-manager' ),
			__( 'Remaining Stock', 'bakery-production-manager' ),
			__( 'Oversold', 'bakery-production-manager' ),
		);

		fputcsv( $fh, $headers );

		if ( ! empty( $data['rows'] ) ) {
			foreach ( $data['rows'] as $row ) {
				fputcsv(
					$fh,
					array(
						$row['product_id'],
						$row['product_name'],
						$row['total_produced'],
						$row['total_wasted'],
						$row['net_added'],
						$row['total_sold'],
						$row['current_stock'],
						$row['remaining'],
						$row['oversold'] ? __( 'Yes', 'bakery-production-manager' ) : __( 'No', 'bakery-production-manager' ),
					)
				);
			}
		}

		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );

		return $csv ?: '';
	}
}
