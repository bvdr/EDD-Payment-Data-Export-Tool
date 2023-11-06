<?php
declare( strict_types=1 );

class EDD_Payment_Data_Export_Tool_Command extends WP_CLI_Command {
	private const STATUSES = [ 'complete', 'refunded', 'pending', 'failed', 'revoked', 'abandoned', 'processing', 'failed' ];

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
	 *  : Filter payments based on amount criteria. Example: '>$1.00' or '< $100' (greater than $100).
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
	 *  wp edd export_payment_data --last-days=7 --format=csv --output=shell --amount-filter='> $1.00' --status-filter='complete,refunded'
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
			$last_days = $assoc_args['last-days'];

			// Invalidate start date and end date if last days is set.
			if ( isset( $assoc_args['start-date'] ) || isset( $assoc_args['end-date'] ) ) {
				WP_CLI::error( 'Cannot use start date or end date with last days.' );
			}

			if ( ! is_numeric( $last_days ) ) {
				WP_CLI::error( "Invalid last days: \"{$last_days}\". Must be a number." );
			}
		}

		// Validate the format.
		if ( isset( $assoc_args['format'] ) ) {
			$format = $assoc_args['format'];

			if ( ! in_array( $format, [ 'csv', 'json' ] ) ) {
				WP_CLI::error( "Invalid format: \"{$format}\". Options: csv, json." );
			}
		}

		// Validate the fields.
		if ( isset( $assoc_args['fields'] ) ) {
			$fields = $assoc_args['fields'];

			if ( ! is_string( $fields ) ) {
				WP_CLI::error( "Invalid fields: \"{$fields}\". Must be a string." );
			}
		}

		// Validate the output.
		if ( isset( $assoc_args['output'] ) ) {
			$output = $assoc_args['output'];

			if ( ! in_array( $output, [ 'shell', 'file' ] ) ) {
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
			$file = $assoc_args['file'];

			if ( ! is_string( $file ) ) {
				WP_CLI::error( "Invalid file: \"{$file}\". Must be a string." );
			}

			// Check if folder exists and ask to create it.
			if ( ! file_exists( dirname( $file ) ) ) {
				// If the user does not want to create the folder, exit.
				WP_CLI::confirm( "Folder does not exist: \"{$file}\". Create it?" );
			}

			// Check if the file exists and ask to overwrite or not.
			if ( file_exists( $file ) ) {
				WP_CLI::confirm( "File already exists: \"{$file}\". Overwrite?" );
			}

			// Check if the folder is writable.
			if ( ! is_writable( dirname( $file ) ) ) {
				$folder = dirname( $file );

				while ( ! is_writable( $folder ) && '/' !== $folder ) {
					$folder = dirname( $folder );
				}

				if ( ! is_writable( $folder ) ) {
					WP_CLI::error( "Folder is not writable." );
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

			// Validate the status filter format by checking if the status is in constant STATUSES
			if ( ! in_array( $status_filter, self::STATUSES ) ) {
				$statuses_string = implode( ', ', self::STATUSES );
				WP_CLI::error( "Invalid status: \"{$status_filter}\". Available options: {$statuses_string}" );
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

			// Validate the product filter format by checking if the status is in constant STATUSES
			if ( ! preg_match( '/^(\d+(?:,\s*\d+)*)$/', $product_filter ) ) {
				WP_CLI::error( "Invalid product filter format: \"{$product_filter}\". Must be a string (e.g., \"123,456\")" );
			}
		}
	}

	protected function validate_date( $date ) {
		// Validate the date format.
		$date_format = 'Y-m-d';
		$date_object = DateTime::createFromFormat( $date_format, $date );

		if ( ! $date_object || $date_object->format( $date_format ) !== $date ) {
			return false;
		}

		return true;
	}
}

WP_CLI::add_command( 'edd', 'EDD_Payment_Data_Export_Tool_Command' );
