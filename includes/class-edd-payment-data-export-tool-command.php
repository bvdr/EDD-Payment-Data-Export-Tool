<?php
/**
 * EDD Payment Data Export Tool
 *
 * @package EDDTools
 */

declare( strict_types=1 );

/**
 * EDD Payment Data Export Tool.
 */
class EDD_Payment_Data_Export_Tool_Command extends WP_CLI_Command {
	private const FIELDS = [
		'id',
		'customer-id',
		'date',
		'status',
		'amount',
		'gateway',
		'name',
		'note',
		'address',
		'email',
		'phone',
	];

	private const DEFAULT_FIELDS = [
		'id',
		'customer-id',
		'date',
		'status',
		'amount',
		'gateway',
	];

	/**
	 * Export payment data command.
	 *
	 * ## OPTIONS
	 *
	 *  [--start-date=<start-date>]
	 *  : The start date for the payment data export. Format: Y-m-d (e.g., 2023-11-01).
	 *
	 *  [--end-date=<end-date>]
	 *  : The end date for the payment data export. Format: Y-m-d (e.g., 2023-11-31).
	 *
	 *  [--last-days=<last-days>]
	 *  : Export payment data from the last X days. Example: 7 (for the last 7 days). Predefined options: today, yesterday, this_week, last_week, this_month, last_month, this_quarter, last_quarter, this_year, last_year.
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
	 *  : Filter payments based on amount criteria. Example: '>$1.00' or '< $100' (greater than $100).
	 *
	 *  [--status-filter=<status-filter>]
	 *  : Filter payments based on status criteria. Example: "publish,refunded" (include complete and refunded payments).
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
	 *  wp edd export_payment_data --last-days=7 --format=csv --output=shell --amount-filter='> $1.00' --status-filter='publish,refunded'
	 *
	 *  # Export payment data between specific dates in JSON format to a file
	 *  wp edd export_payment_data --start-date=2023-11-01 --end-date=2023-11-30 --format=json --output=file --file=/path/to/export.json
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command associative arguments.
	 */
	public function export_payment_data( $args, $assoc_args ): void {
		// Check if Easy Digital Downloads is active.
		if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
			WP_CLI::error( 'Easy Digital Downloads is not installed.' );
		}

		// Validate the array of arguments.
		$this->validate_arguments( $args, $assoc_args );

		// Get the export data.
		$export_data = $this->get_export_data( $assoc_args );

		// Get the output format.
		$output = $assoc_args['output'] ?? 'shell';

		// Get the format csv or json.
		$format = $assoc_args['format'] ?? 'csv';

		// If 'shell' output, print the data as table.
		if ( 'shell' === $output ) {
			if ( 'json' === $format ) {
				WP_CLI::line( wp_json_encode( $export_data, JSON_PRETTY_PRINT ) );
			} elseif ( 'csv' === $format ) {
				WP_CLI\Utils\format_items( 'table', $export_data, $assoc_args['fields'] ? explode( ',', $assoc_args['fields'] ) : self::DEFAULT_FIELDS );
			}
		}

		// If 'file' output, write the data to a file.
		if ( 'file' === $output ) {
			// Write the data to the file.
			$this->update_payments_file( $export_data, $assoc_args, $overwrite = true );
		}

		WP_CLI::line( 'Exported ' . count( $export_data ) . ' payments.' );
	}

	/**
	 * Validates the command arguments.
	 *
	 * @param array $args       Command arguments.
	 * @param array $assoc_args Command associative arguments.
	 *
	 * @return void
	 */
	protected function validate_arguments( $args, $assoc_args ) {
		// Validate the start date.
		if ( isset( $assoc_args['start-date'] ) ) {
			$start_date = $assoc_args['start-date'];

			if ( ! $this->validate_date( $start_date ) ) {
				WP_CLI::error( 'Invalid start date. Format: Y-m-d (e.g., 2023-11-01).' );
			}
		}

		// Validate the end date.
		if ( isset( $assoc_args['end-date'] ) ) {
			$end_date = $assoc_args['end-date'];

			if ( ! $this->validate_date( $end_date ) ) {
				WP_CLI::error( "Invalid end date: \"{$end_date}\". Format: Y-m-d (e.g., 2023-11-30)." );
			}
		}

		// Validate the last days.
		if ( isset( $assoc_args['last-days'] ) ) {
			$last_days       = $assoc_args['last-days'];
			$edd_query       = new EDD_Payments_Query();
			$allowed_strings = array_keys( $edd_query->get_predefined_dates() );

			// Invalidate start date and end date if last days is set.
			if ( isset( $assoc_args['start-date'] ) || isset( $assoc_args['end-date'] ) ) {
				WP_CLI::error( 'Cannot use start date or end date with last days.' );
			}

			if ( ! is_numeric( $last_days ) && ! in_array( $last_days, $allowed_strings, true ) ) {
				WP_CLI::error( "Invalid last days: \"{$last_days}\". Must be a number or one of the following strings: " . implode( ', ', $allowed_strings ) );
			}
		}

		// Validate the format.
		if ( isset( $assoc_args['format'] ) ) {
			$format = $assoc_args['format'];

			if ( ! in_array( $format, [ 'csv', 'json' ], true ) ) {
				WP_CLI::error( "Invalid format: \"{$format}\". Options: csv, json." );
			}
		}

		// Validate the fields.
		if ( isset( $assoc_args['fields'] ) ) {
			$fields = $assoc_args['fields'];

			if ( ! is_string( $fields ) ) {
				WP_CLI::error( "Invalid fields: \"{$fields}\". Must be a string. (e.g., \"customer-id,amount,email,customer_id\"" );
			}

			// Check if all the fields are valid.
			$fields_array = explode( ',', $fields );

			foreach ( $fields_array as $field ) {
				if ( ! in_array( $field, self::FIELDS, true ) ) {
					$fields_string = implode( ', ', self::FIELDS );
					WP_CLI::error( "Invalid field: \"{$field}\". Available options: {$fields_string}" );
				}
			}
		}

		// Validate the output.
		if ( isset( $assoc_args['output'] ) ) {
			$output = $assoc_args['output'];

			if ( ! in_array( $output, [ 'shell', 'file' ], true ) ) {
				WP_CLI::error( "Invalid output: \"{$output}\". Options: shell, file." );
			}

			// Check if the file argument is set.
			if ( 'file' === $output && ! isset( $assoc_args['file'] ) ) {
				WP_CLI::error( 'File argument is required when output is set to "file".' );
			}

			// Check if the file argument is set with 'shell' output.
			if ( 'shell' === $output && isset( $assoc_args['file'] ) ) {
				WP_CLI::error( 'File argument is not allowed when output is set to "shell".' );
			}
		}

		// Validate the file.
		if ( isset( $assoc_args['file'] ) ) {
			$file   = $assoc_args['file'];
			$format = $assoc_args['format'] ?? 'csv';

			if ( ! is_string( $file ) ) {
				WP_CLI::error( "Invalid file: \"{$file}\". Must be a string." );
			}

			// Validate the file format. (e.g., format=csv => /path/to/file.csv / format=json => /path/to/file.json).
			if ( ! preg_match( '/^(.+)\.(csv|json)$/', $file ) ) {
				WP_CLI::error( "Invalid file extension: \"{$file}\". Must be a string (e.g., \"/path/to/file.csv\" or \"/path/to/file.json\")" );
			}

			// For format=json, allow only .json extension.
			if ( 'json' === $format && ! preg_match( '/^(.+)\.json$/', $file ) ) {
				WP_CLI::error( "Invalid file extension: \"{$file}\". Must be a string (e.g., \"/path/to/file.json\")" );
			}

			// For format=csv, allow only .csv extension.
			if ( 'csv' === $format && ! preg_match( '/^(.+)\.csv$/', $file ) ) {
				WP_CLI::error( "Invalid file extension: \"{$file}\". Must be a string (e.g., \"/path/to/file.csv\")" );
			}

			// Check if folder exists and ask to create it.
			if ( ! file_exists( dirname( $file ) ) ) {
				// If the user does not want to create the folder, exit.
				WP_CLI::confirm( "Folder does not exist: \"{$file}\". Create it?", $assoc_args );
			}

			// Check if the file exists and ask to overwrite or not.
			if ( file_exists( $file ) ) {
				WP_CLI::confirm( "File already exists: \"{$file}\". Overwrite?", $assoc_args );
			}

			// Check if the folder is writable.
			if ( ! is_writable( dirname( $file ) ) ) {
				$folder = dirname( $file );

				while ( ! is_writable( $folder ) && '/' !== $folder ) {
					$folder = dirname( $folder );
				}

				if ( ! is_writable( $folder ) ) {
					WP_CLI::error( 'Folder is not writable.' );
				}
			}
		}

		// Validate the amount filter.
		if ( isset( $assoc_args['amount-filter'] ) ) {
			$amount_filter = $assoc_args['amount-filter'];

			if ( ! is_string( $amount_filter ) ) {
				WP_CLI::error( "Invalid amount filter: \"{$amount_filter}\". Must be a string (e.g., \"> $1.00\" or \"< $100)\"" );
			}

			// Validate the amount filter format.
			if ( ! preg_match( '/^([><])\s?\$?(\d+(?:\.\d{1,2})?)$/', $amount_filter ) ) {
				WP_CLI::error( "Invalid amount filter format: \"{$amount_filter}\". Must be a string (e.g., '> $1.00' or '< $100')" );
			}
		}

		// Validate the status filter.
		if ( isset( $assoc_args['status-filter'] ) ) {
			$status_filter = $assoc_args['status-filter'];

			if ( ! is_string( $status_filter ) ) {
				WP_CLI::error( "Invalid status filter: \"{$status_filter}\". Must be a string (e.g., \"complete,refunded\")" );
			}

			// Check if all the statuses are valid.
			$status_array = explode( ',', $status_filter );

			foreach ( $status_array as $status ) {
				if ( ! in_array( $status, edd_get_payment_status_keys(), true ) ) {
					$statuses_string = implode( ', ', edd_get_payment_status_keys() );
					WP_CLI::error( "Invalid status: \"{$status}\". Available options: {$statuses_string}" );
				}
			}
		}

		// Validate the customer filter.
		if ( isset( $assoc_args['customer-filter'] ) ) {
			$customer_filter = $assoc_args['customer-filter'];

			// Validate if the input is an email address or a number.
			if ( ( is_string( $customer_filter ) && ! filter_var( $customer_filter, FILTER_VALIDATE_EMAIL ) ) && ! is_numeric( $customer_filter ) ) {
				WP_CLI::error( "Invalid customer filter: \"{$customer_filter}\". Must be an email or integer (e.g., \"client.name@company.com\" or \"20123\")" );
			}
		}

		// Validate the product filter.
		if ( isset( $assoc_args['product-filter'] ) ) {
			$product_filter = $assoc_args['product-filter'];

			if ( ! is_string( $product_filter ) ) {
				WP_CLI::error( "Invalid product filter: \"{$product_filter}\". Must be a string (e.g., \"123,456\")" );
			}

			// Validate the product filter format by checking if the status is in constant STATUSES.
			if ( ! preg_match( '/^(\d+(?:,\s*\d+)*)$/', $product_filter ) ) {
				WP_CLI::error( "Invalid product filter format: \"{$product_filter}\". Must be a string (e.g., \"123,456\")" );
			}
		}
	}

	/**
	 * Validates the date format.
	 *
	 * @param string $date The date to validate.
	 *
	 * @return bool
	 */
	protected function validate_date( $date ) {
		// Validate the date format.
		$date_format = 'Y-m-d';
		$date_object = DateTime::createFromFormat( $date_format, $date );

		if ( ! $date_object || $date_object->format( $date_format ) !== $date ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the export data from Easy Digital Downloads.
	 *
	 * @param array $assoc_args Command associative arguments.
	 *
	 * @return array The export data.
	 */
	protected function get_export_data( $assoc_args ): array {
		$start_date      = $assoc_args['start-date'] ?? '';
		$end_date        = $assoc_args['end-date'] ?? '';
		$last_days       = $assoc_args['last-days'] ?? '';
		$amount_filter   = $assoc_args['amount-filter'] ?? '';
		$status_filter   = $assoc_args['status-filter'] ?? '';
		$customer_filter = $assoc_args['customer-filter'] ?? '';
		$product_filter  = $assoc_args['product-filter'] ?? '';
		$fields          = isset( $assoc_args['fields'] ) ? explode( ',', $assoc_args['fields'] ) : self::DEFAULT_FIELDS;

		$result = $this->edd_payment_data_fetch( $start_date, $end_date, $last_days, $amount_filter, $status_filter, $customer_filter, $product_filter, $fields );

		return $result;
	}

	/**
	 * Fetch payment data from Easy Digital Downloads.
	 *
	 * @param string $start_date      The start date for the payment data.
	 * @param string $end_date        The end date for the payment data.
	 * @param string $last_days       The number of days for which to fetch the payment data.
	 * @param string $amount_filter   The amount filter for payment data.
	 * @param string $status_filter   The status filter for payment data.
	 * @param string $customer_filter The customer filter for payment data.
	 * @param string $product_filter  The product filter for payment data.
	 * @param array  $fields          The product filter for payment data.
	 *
	 * @return array The fetched payment data.
	 */
	protected function edd_payment_data_fetch( $start_date = '', $end_date = '', $last_days = '', $amount_filter = '', $status_filter = '', $customer_filter = '', $product_filter = '', $fields = [] ) {
		// Include the necessary Easy Digital Downloads files.
		if ( ! class_exists( 'EDD_Payments_Query' ) ) {
			require_once EDD_PLUGIN_DIR . 'includes/payments/class-payments-query.php';
		}

		// Prepare the query arguments for EDD_Payments_Query.
		$args = [
			'number' => - 1,
			'page'   => 1,
		];

		// Set the date query based on the provided parameters.
		if ( ! empty( $start_date ) ) {
			$args['start_date'] = $start_date;
		}

		if ( ! empty( $end_date ) ) {
			$args['end_date'] = $end_date;

			// Since EDD will try to figure the start day to be the first day of the month (`setup_dates( $_start_date = 'this_month', $_end_date = false )`), we force it to an absurd past date. 🤔 Not the most elegant solution but it works well.
			if ( empty( $start_date ) ) {
				$args['start_date'] = '1970-01-01';
			}
		}

		// If last days is set, use it to set the start date.
		if ( ! empty( $last_days ) ) {
			$args['start_date'] = $this->calculate_start_date( $last_days );
			if ( is_numeric( $last_days ) ) {
				// Set the end date to today. This is needed since the default calculate-date function sets the end date to end of start day. This way the end_date will be set as the end of today.
				$args['end_date'] = date( 'Y-m-d', strtotime( 'today' ) );

			} else {
				$args['end_date'] = false;
			}
		}

		// Set the amount filter if provided.
		if ( ! empty( $amount_filter ) ) {
			// Remove the $ sign.
			$amount_filter = str_replace( '$', '', $amount_filter );

			// Remove any spaces.
			$amount_filter = str_replace( ' ', '', $amount_filter );

			// Remove any commas.
			$amount_filter = str_replace( ',', '', $amount_filter );

			$operator = substr( $amount_filter, 0, 1 );
			$amount   = substr( $amount_filter, 1 );

			// Set the amount filter.
			$args['meta_query'][] = [
				'key'     => '_edd_payment_total',
				'value'   => $amount,
				'compare' => $operator,
				'type'    => 'DECIMAL',
			];
		}

		// Set the status filter if provided.
		if ( ! empty( $status_filter ) ) {
			$args['status'] = explode( ',', $status_filter );
		}

		// Set the customer filter if provided.
		if ( ! empty( $customer_filter ) ) {
			if ( is_numeric( $customer_filter ) ) {
				$args['customer'] = (int) $customer_filter;
			} else {
				$args['meta_query'][] = [
					'key'     => '_edd_payment_user_email',
					'value'   => $customer_filter,
					'compare' => '=',
				];
			}
		}

		// Set the product filter if provided.
		if ( ! empty( $product_filter ) ) {
			$args['download'] = explode( ',', $product_filter );
		}

		// Asses performance with microtime.
		$start_time = microtime( true );

		// Perform the payment data query.
		$payment_query = new EDD_Payments_Query( $args );
		$payments      = $payment_query->get_payments();
		$end_time      = microtime( true );
		WP_CLI::line( 'Query time: ' . (int) ( $end_time - $start_time ) . ' seconds' );

		// Prepare the payment data for output.
		$payment_data = [];

		foreach ( $payments as $payment ) {
			$single_payment = [];

			// Get the payment data fields that you want to include in the output.
			in_array( 'id', $fields, true ) ? $single_payment['id']                   = $payment->ID : '';
			in_array( 'customer-id', $fields, true ) ? $single_payment['customer-id'] = $payment->customer_id : '';
			in_array( 'date', $fields, true ) ? $single_payment['date']               = $payment->date : '';
			in_array( 'status', $fields, true ) ? $single_payment['status']           = $payment->status : '';
			in_array( 'amount', $fields, true ) ? $single_payment['amount']           = $payment->total : '';
			in_array( 'gateway', $fields, true ) ? $single_payment['gateway']         = $payment->gateway : '';
			in_array( 'name', $fields, true ) ? $single_payment['name']               = $payment->name : '';
			in_array( 'note', $fields, true ) ? $single_payment['note']               = $payment->note : '';
			in_array( 'address', $fields, true ) ? $single_payment['address']         = $payment->address : '';
			in_array( 'email', $fields, true ) ? $single_payment['email']             = $payment->email : '';
			in_array( 'phone', $fields, true ) ? $single_payment['phone']             = $payment->phone : '';

			$payment_data[] = $single_payment;
		}

		return $payment_data;
	}

	/**
	 * The function that writes the payments to the file.
	 *
	 * @param array $payments   The payments to write to the file.
	 * @param array $assoc_args Command associative arguments.
	 * @param bool  $overwrite  Whether to overwrite the file or not. Currently it is always true.
	 *
	 * @return void
	 */
	protected function update_payments_file( array $payments, array $assoc_args, bool $overwrite = true ): void {
		// Get the file path.
		$file   = $assoc_args['file'];
		$format = $assoc_args['format'] ?? 'csv';

		// Create folder structure if it does not exist.
		if ( ! file_exists( dirname( $file ) ) ) {
			mkdir( dirname( $file ), 0755, true );
		}

		// Use file_put_contents to write the data to the file.
		// @todo: [Enhancement] Initialize the WP_Filesystem class and use it instead  of php file_put_contents.
		if ( 'json' === $format ) {
			$write_result = file_put_contents( $file, wp_json_encode( $payments, JSON_PRETTY_PRINT ) ); // phpcs:ignore
		} elseif ( 'csv' === $format ) {
			$write_result = file_put_contents( $file, $this->array_to_csv( $payments ) ); // phpcs:ignore
		}

		if ( false === $write_result ) {
			WP_CLI::error( 'Error writing to file.' );
		}
	}

	/**
	 * Create a CSV string from an array.
	 *
	 * @param array $array The array to convert to CSV.
	 *
	 * @return string
	 */
	protected function array_to_csv( array $array ): string {
		$csv = '';

		// If the array is empty, return an empty string.
		if ( empty( $array ) ) {
			return $csv;
		}

		// Set the headers.
		$headers = array_keys( $array[0] ?? [] );
		$csv    .= implode( ',', $headers ) . "\n";

		// Set the rows.
		foreach ( $array as $item ) {
			$csv .= implode( ',', $item ) . "\n";
		}

		return $csv;
	}

	/**
	 * Calculate the start date in Y-m-d format if it's numeric (e.g., 7 = 7 days ago).
	 *
	 * @param string $start_date The start date.
	 *
	 * @return string
	 */
	protected function calculate_start_date( string $start_date ) {
		// Bail fast. If it's not numeric return.
		if ( ! is_numeric( $start_date ) ) {
			return $start_date;
		}

		// Get the start date in Y-m-d format.
		return gmdate( 'Y-m-d', strtotime( "-{$start_date} days" ) );
	}
}

WP_CLI::add_command( 'edd', 'EDD_Payment_Data_Export_Tool_Command' );
