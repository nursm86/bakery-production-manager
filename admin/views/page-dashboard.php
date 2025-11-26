<?php
/**
 * Unified Dashboard View (Tailwind CSS)
 *
 * @package BakeryProductionManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Fetch Data
$cold_table = $wpdb->prefix . 'bakery_cold_storage';
$cold_items = $wpdb->get_results( "SELECT * FROM {$cold_table} WHERE quantity > 0 ORDER BY updated_at DESC" );

$log_table = $wpdb->prefix . 'bakery_production_log';
$recent_production = $wpdb->get_results( "SELECT * FROM {$log_table} ORDER BY created_at DESC LIMIT 10" );

$materials_table = $wpdb->prefix . 'rim_raw_materials';
$materials = $wpdb->get_results( "SELECT id, name, unit_type, quantity, warning_quantity FROM {$materials_table} ORDER BY name ASC" );

// Calculate Stats
$total_produced_today = $wpdb->get_var( "SELECT SUM(quantity_produced) FROM {$log_table} WHERE DATE(created_at) = CURDATE()" );
$low_stock_count = 0;
foreach($materials as $mat) {
    if($mat->quantity <= $mat->warning_quantity) $low_stock_count++;
}
$cold_storage_count = count($cold_items);

?>
<div class="wrap bg-slate-50 min-h-screen p-6 -ml-5">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Bakery Manager</h1>
                <p class="text-slate-500 mt-1">Overview of production and inventory</p>
            </div>
            <div class="text-sm text-slate-400">
                <?php echo date_i18n( get_option( 'date_format' ) ); ?>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Stat 1 -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-100">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-slate-500 text-sm font-medium uppercase tracking-wider">Produced Today</h3>
                    <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                        <span class="dashicons dashicons-buddicons-replies"></span>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-800"><?php echo number_format_i18n( (float) $total_produced_today ); ?></div>
                <div class="text-xs text-green-600 mt-2 font-medium flex items-center">
                    <span class="dashicons dashicons-arrow-up-alt mr-1" style="font-size:14px;width:14px;height:14px;"></span>
                    <span>Units</span>
                </div>
            </div>

            <!-- Stat 2 -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-100">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-slate-500 text-sm font-medium uppercase tracking-wider">Cold Storage</h3>
                    <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                        <span class="dashicons dashicons-products"></span>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-800"><?php echo $cold_storage_count; ?></div>
                <div class="text-xs text-slate-400 mt-2">Active Items</div>
            </div>

            <!-- Stat 3 -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-100">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-slate-500 text-sm font-medium uppercase tracking-wider">Low Stock Alerts</h3>
                    <div class="p-2 bg-rose-50 rounded-lg text-rose-600">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-800"><?php echo $low_stock_count; ?></div>
                <div class="text-xs text-rose-500 mt-2 font-medium">Attention Needed</div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Left Column: Actions -->
            <div class="space-y-8">
                <!-- Quick Production Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-50 bg-slate-50/50">
                        <h2 class="text-lg font-semibold text-slate-800 flex items-center">
                            <span class="dashicons dashicons-plus-alt mr-2 text-blue-500"></span>
                            Quick Production
                        </h2>
                    </div>
                    <div class="p-6">
                        <form id="bpm-quick-production-form" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Product</label>
                                <select id="bpm-quick-production-product" class="bpm-select2 w-full"></select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Quantity</label>
                                    <input type="number" id="bpm-quick-production-qty" step="0.01" min="0" required 
                                           class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                                    <select id="bpm-quick-production-type" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="direct">Direct (Ready to Sell)</option>
                                        <option value="cold_storage">To Cold Storage</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center">
                                Record Production
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Quick Inventory Usage Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-50 bg-slate-50/50">
                        <h2 class="text-lg font-semibold text-slate-800 flex items-center">
                            <span class="dashicons dashicons-minus mr-2 text-amber-500"></span>
                            Quick Inventory Usage
                        </h2>
                    </div>
                    <div class="p-6">
                        <form id="bpm-quick-usage-form" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Ingredient</label>
                                <select id="bpm-quick-usage-material" class="bpm-select2 w-full">
                                    <option value="">Select Ingredient...</option>
                                    <?php foreach ( $materials as $mat ) : ?>
                                        <option value="<?php echo esc_attr( $mat->id ); ?>">
                                            <?php echo esc_html( $mat->name . ' (' . $mat->unit_type . ')' ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Quantity Used</label>
                                    <input type="number" id="bpm-quick-usage-qty" step="0.01" min="0" required 
                                           class="w-full rounded-lg border-slate-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Reason</label>
                                    <input type="text" id="bpm-quick-usage-reason" placeholder="e.g. Daily Prep"
                                           class="w-full rounded-lg border-slate-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                </div>
                            </div>
                            <button type="submit" class="w-full bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 font-medium py-2.5 px-4 rounded-lg transition-colors duration-200">
                                Record Usage
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Lists -->
            <div class="space-y-8">
                <!-- Cold Storage List -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-50 flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-slate-800">Cold Storage Inventory</h2>
                        <span class="bg-indigo-100 text-indigo-800 text-xs font-medium px-2.5 py-0.5 rounded-full"><?php echo count($cold_items); ?> Items</span>
                    </div>
                    <div class="divide-y divide-slate-100 max-h-[400px] overflow-y-auto">
                        <?php if ( empty( $cold_items ) ) : ?>
                            <div class="p-8 text-center text-slate-400">
                                <span class="dashicons dashicons-archive text-4xl mb-2"></span>
                                <p>Cold storage is empty.</p>
                            </div>
                        <?php else : ?>
                            <?php foreach ( $cold_items as $item ) : 
                                $product = wc_get_product( $item->product_id );
                                $name = $product ? $product->get_name() : 'Unknown Product #' . $item->product_id;
                            ?>
                                <div class="p-4 flex items-center justify-between hover:bg-slate-50 transition-colors">
                                    <div>
                                        <div class="font-medium text-slate-800"><?php echo esc_html( $name ); ?></div>
                                        <div class="text-sm text-slate-500">
                                            Updated: <?php echo date_i18n( 'M j', strtotime( $item->updated_at ) ); ?>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-lg font-bold text-indigo-600"><?php echo esc_html( $item->quantity ); ?></span>
                                        <button type="button" 
                                                class="bpm-waste-cold-storage-btn text-sm bg-rose-100 hover:bg-rose-200 text-rose-700 px-3 py-1.5 rounded-md transition-colors mr-2"
                                                data-product-id="<?php echo esc_attr( $item->product_id ); ?>"
                                                data-max-qty="<?php echo esc_attr( $item->quantity ); ?>"
                                                title="Record Waste">
                                            <span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;vertical-align:middle;"></span>
                                        </button>
                                        <button type="button" 
                                                class="bpm-cook-cold-storage-btn text-sm bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-md transition-colors"
                                                data-product-id="<?php echo esc_attr( $item->product_id ); ?>"
                                                data-max-qty="<?php echo esc_attr( $item->quantity ); ?>">
                                            Cook
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-50">
                        <h2 class="text-lg font-semibold text-slate-800">Recent Production</h2>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <?php if ( empty( $recent_production ) ) : ?>
                            <div class="p-6 text-center text-slate-400">No recent activity.</div>
                        <?php else : ?>
                            <?php foreach ( $recent_production as $log ) : 
                                $product = wc_get_product( $log->product_id );
                                $name = $product ? $product->get_name() : 'Unknown';
                            ?>
                                <div class="p-4 flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center mr-3">
                                            <span class="dashicons dashicons-yes" style="font-size:16px;width:16px;height:16px;"></span>
                                        </div>
                                        <div>
                                            <div class="font-medium text-slate-800"><?php echo esc_html( $name ); ?></div>
                                            <div class="text-xs text-slate-500">
                                                <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->created_at ) ) ); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <?php if ( $log->quantity_produced > 0 ) : ?>
                                            <div class="font-bold text-slate-700">+<?php echo esc_html( $log->quantity_produced ); ?></div>
                                        <?php elseif ( 'cold_storage_waste' === $log->unit_type ) : ?>
                                            <div class="font-bold text-rose-600"><?php esc_html_e( 'Waste', 'bakery-production-manager' ); ?></div>
                                        <?php else : ?>
                                            <div class="font-bold text-slate-400">—</div>
                                        <?php endif; ?>

                                        <?php if ( $log->quantity_wasted > 0 ) : ?>
                                            <div class="text-xs text-rose-500">-<?php echo esc_html( $log->quantity_wasted ); ?> <?php esc_html_e( 'wasted', 'bakery-production-manager' ); ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if ( 'cold_storage_cook' === $log->unit_type ) : ?>
                                            <div class="text-xs text-indigo-500"><?php esc_html_e( 'From Cold Storage', 'bakery-production-manager' ); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Override WP Admin styles for this page */
    #wpcontent { padding-left: 0; }
    .wrap { margin: 0; }
    /* Select2 Tailwind Fixes */
    .select2-container .select2-selection--single {
        height: 42px !important;
        border-color: #d1d5db !important;
        border-radius: 0.5rem !important;
        padding-top: 6px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        top: 8px !important;
    }
</style>
