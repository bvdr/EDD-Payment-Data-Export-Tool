<?php
/**
 * Plugin Name: EDD Payment Data Export Tool
 * Description: 🛠️ WP-CLI command to export payment data from Easy Digital Downloads.
 * Version: 1.0
 * Author: Bogdan Dragomir
 * Author URI: bogdan.is?utm_source=edd-payment-data-export-tool
 *
 * @package EDD_Payment_Data_Export_Tool
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Register the WP-CLI command.
 */
function edd_payment_data_export_tool_register_wpcli_command() {
	if ( ! class_exists( 'WP_CLI' ) ) {
		return;
	}

	require_once __DIR__ . '/includes/class-edd-payment-data-export-tool-command.php';
}

add_action( 'init', 'edd_payment_data_export_tool_register_wpcli_command' );
