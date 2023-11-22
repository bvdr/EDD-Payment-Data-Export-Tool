<?php
/**
 * Plugin Name: EDD Payment Data Export Tool (WP-CLI)
 * Description: 🛠️ WP-CLI command to export payment data from Easy Digital Downloads. Run "wp edd export_payment_data --help" for usage instructions.
 * Author: Bogdan Dragomir
 * Author URI: https://bogdan.is?utm_source=wordpress&utm_medium=plugin&utm_campaign=edd-payment-data-export-tool
 * Version: 1.1.0
 * Text Domain: easy-digital-downloads-tools
 * Domain Path: languages
 *
 * @package EDDTools
 * @author Bogdan Dragomir
 * @version 1.0.6
 */

// Exit if accessed directly.
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
