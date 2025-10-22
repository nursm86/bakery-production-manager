<?php
/**
 * Handles plugin deactivation tasks.
 *
 * @package BakeryProductionManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BPM_Deactivator
 */
class BPM_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// No cleanup required at the moment.
	}
}

