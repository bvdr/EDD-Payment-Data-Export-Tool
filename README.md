# EDD Payment Data Export Tool Plugin [Alpha]

This plugin provides a command-line interface for exporting payment data from Easy Digital Downloads.
Works with EDD 2.9.14+.

## Usage

### `wp edd export_payment_data`

Export payment data based on various options.

#### Options

- `--start-date=<start-date>`: The start date for the payment data export. Format: Y-m-d (e.g., 2023-11-01).
- `--end-date=<end-date>`: The end date for the payment data export. Format: Y-m-d (e.g., 2023-11-30).
- `--last-days=<last-days>`: Export payment data from the last X days. Example: 7 (for the last 7 days).
- `--format=<format>`: The output format for the payment data export. Options: csv, json. Default: csv.
- `--fields=<fields>`: The fields to include in the payment data export (comma-separated). Default: email,date,status,amount,id,gateway.
- `--output=<output>`: The output destination for the payment data export. Options: shell, file. Default: shell.
- `--file=<file>`: The file path for the payment data export. Required if output is set to "file".
- `--amount-filter=<amount-filter>`: Filter payments based on amount criteria. Example: '>$1.00' or '< $100' (greater than $100).
- `--status-filter=<status-filter>`: Filter payments based on status criteria. Example: "complete,refunded" (include complete and refunded payments).
- `--customer-filter=<customer-filter>`: Filter payments based on customer email or ID.
- `--product-filter=<product-filter>`: Filter payments based on product variations by providing price/download
