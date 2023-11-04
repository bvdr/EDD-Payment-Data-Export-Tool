<?php
/**
 * Plugin Name: EDD Payment Data Export Tool
 * Description: 🛠️ WP-CLI command to export payment data from Easy Digital Downloads.
 * Version: 1.0
 * Author: Bogdan Dragomir
 * Author URI: bogdan.is?utm_source=edd-payment-data-export-tool
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

	/**
	 * Export payment data from Easy Digital Downloads.
	 */
	class EDD_Payment_Data_Export_Tool_Command extends WP_CLI_Command {

		/**
		 * Export payment data command.
		 *
		 * ## OPTIONS
		 *
		 *  [--start-date=<start-date>]
		 *  : The start date for the payment data export. Format: Y-m-d (e.g., 2022-01-01).
		 *
		 *  [--end-date=<end-date>]
		 *  : The end date for the payment data export. Format: Y-m-d (e.g., 2022-01-31).
		 *
		 *  [--last-days=<last-days>]
		 *  : Export payment data from the last X days. Example: 7 (for the last 7 days).
		 *
		 *  [--format=<format>]
		 *  : The output format for the payment data export. Options: csv, json. Default: csv.
		 *
		 *  [--fields=<fields>]
		 *  : The fields to include in the payment data export (comma-separated). Default: email,date,status,amount,id,gateway.
		 *
		 *  [--output=<output>]
		 *  : The output destination for the payment data export. Options: shell, file. Default: shell.
		 *
		 *  [--file=<file>]
		 *  : The file path for the payment data export. Required if output is set to "file".
		 *
		 *  [--amount-filter=<amount-filter>]
		 *  : Filter payments based on amount criteria. Example: ">100" (greater than $100).
		 *
		 *  [--status-filter=<status-filter>]
		 *  : Filter payments based on status criteria. Example: "complete,refunded" (include complete and refunded payments).
		 *
		 *  [--customer-filter=<customer-filter>]
		 *  : Filter payments based on customer email or ID.
		 *
		 *  [--product-filter=<product-filter>]
		 *  : Filter payments based on product variations by providing price/download IDs.
		 *
		 *  ## EXAMPLES
		 *
		 *  # Export payment data for the last 7 days in CSV format to the shell
		 *  wp edd_payment_data_export_tool export --last-days=7 --format=csv --output=shell
		 *
		 *  # Export payment data between specific dates in JSON format to a file
		 *  wp edd_payment_data_export_tool export --start-date=2022-01-01 --end-date=2022-01-31 --format=json --output=file --file=/path/to/export.json
		 *
		 * @param array $args Command arguments.
		 * @param array $assoc_args Command associative arguments.
		 */
		public function export_payment_data( $args, $assoc_args ) {
			// Check if Easy Digital Downloads is active.
			if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
				WP_CLI::error( 'Easy Digital Downloads is not installed.' );
			}
		}
	}

	WP_CLI::add_command( 'edd', 'EDD_Payment_Data_Export_Tool_Command' );
}

// Register the WP-CLI command.
edd_payment_data_export_tool_register_wpcli_command();
