<?php
/**
 * Settings page.
 *
 * @package BakeryProductionManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$unit_types          = isset( $settings['unit_types'] ) ? (array) $settings['unit_types'] : array( 'kg', 'litre', 'piece' );
$enable_manage_stock = ! empty( $settings['enable_manage_stock'] );
$summary_email       = isset( $settings['summary_email'] ) ? $settings['summary_email'] : '';
?>
<div class="wrap bpm-wrap bpm-settings">
	<h1><?php esc_html_e( 'Bakery Production Settings', 'bakery-production-manager' ); ?></h1>

	<div class="bpm-card">
		<form id="bpm-settings-form">
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<label for="bpm-unit-types"><?php esc_html_e( 'Available unit types', 'bakery-production-manager' ); ?></label>
						</th>
						<td>
							<select id="bpm-unit-types" name="unit_types[]" multiple="multiple" class="bpm-unit-types">
								<?php foreach ( $unit_types as $unit ) : ?>
									<option value="<?php echo esc_attr( $unit ); ?>" selected="selected"><?php echo esc_html( $unit ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Add or remove units used across your bakery (e.g. kg, litre, piece).', 'bakery-production-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Automatically enable “manage stock”?', 'bakery-production-manager' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="enable_manage_stock" value="1" <?php checked( $enable_manage_stock ); ?> />
								<?php esc_html_e( 'If a product does not manage stock, enable it when production is logged.', 'bakery-production-manager' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Enable Decimal Quantities?', 'bakery-production-manager' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="enable_decimal_quantities" value="1" <?php checked( ! empty( $settings['enable_decimal_quantities'] ) ); ?> />
								<?php esc_html_e( 'Allow customers to purchase fractional quantities (e.g. 0.5 kg).', 'bakery-production-manager' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bpm-summary-email"><?php esc_html_e( 'Daily summary email', 'bakery-production-manager' ); ?></label>
						</th>
						<td>
							<input type="email" id="bpm-summary-email" name="summary_email" value="<?php echo esc_attr( $summary_email ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Optional: receive future daily summaries at this address.', 'bakery-production-manager' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Save Settings', 'bakery-production-manager' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>

