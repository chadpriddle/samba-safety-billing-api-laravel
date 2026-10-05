# Samba Billing for Laravel

Standalone Laravel Composer wrapper for the SambaSafety Billing API.

> This package is for the **Billing API**, not the separate SambaSafety/MVR API.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13

## Local installation

A convenient development layout is:

```text
/var/www/
├── towing/
└── samba-billing-laravel/
```

Unzip/copy this package to:

```bash
/var/www/samba-billing-laravel
```

In your Laravel application's `composer.json`, add a path repository:

```json
"repositories": [
    {
        "type": "path",
        "url": "../samba-billing-laravel",
        "options": {
            "symlink": true
        }
    }
]
```

Then from `/var/www/towing`:

```bash
composer require chadpriddle/samba-billing:@dev
```

Composer should symlink the local package so changes made in
`/var/www/samba-billing-laravel` are immediately available to the application.

## Configuration

Optionally publish the config:

```bash
php artisan vendor:publish --tag=samba-billing-config
```

Add to `.env`:

```env
SAMBA_BILLING_URL=https://billling.sambasafety.com
SAMBA_BILLING_API_KEY=your-jwt-api-key
SAMBA_BILLING_TIMEOUT=60
SAMBA_BILLING_CONNECT_TIMEOUT=15
SAMBA_BILLING_RETRY_TIMES=3
SAMBA_BILLING_RETRY_SLEEP_MS=2000
```

**Important:** The documentation supplied for this package showed
`https://billling.sambasafety.com` with three `l` characters. Confirm the exact
production hostname with SambaSafety before production use.

The API key is treated as an opaque Bearer token. The package does not decode or
depend on JWT claims.

## Usage

Dependency injection:

```php
use ChadPriddle\SambaBilling\SambaBilling;

public function index(SambaBilling $billing)
{
    $accounts = $billing->accounts();
}
```

Facade:

```php
use ChadPriddle\SambaBilling\Facades\SambaBilling;

$accounts = SambaBilling::accounts();
```

### Accounts

```php
$accounts = $billing->accounts();
```

Duplicate account IDs returned by Samba are removed.

### Invoices

```php
$invoices = $billing->invoices($accountId);
```

### Invoice for a month

```php
$invoice = $billing->invoiceForMonth($accountId, 2026, 9);
```

Samba does not provide a direct month endpoint. This helper examines
`invoiceDate`.

### Download raw invoice CSV

```php
$csv = $billing->invoiceDetails($accountId, $invoiceId);
```

### Parse details into a Laravel Collection

```php
$rows = $billing->invoiceDetailsArray($accountId, $invoiceId);
```

Example:

```php
$total = $rows->sum(fn ($row) => (float) ($row['TOTAL'] ?? 0));
```

### Transactions for a month

```php
$rows = $billing->transactionsForMonth($accountId, 2026, 9);
```

This helper finds the invoice for the requested month, verifies that details are
available, downloads the CSV, and parses it into a Collection.

### Save details to disk

```php
$path = $billing->downloadInvoiceDetails(
    $accountId,
    $invoiceId,
    storage_path('app/samba/invoices/invoice.csv')
);
```

## API endpoints

The package implements:

```text
GET /bill/accounts
GET /bill/accounts/{AccountId}/invoices
GET /bill/accounts/{AccountId}/invoices/{InvoiceId}
```

Account IDs and invoice IDs are treated as opaque values.

## Rate limiting / polling

Samba documents that endpoints are rate limited and may return HTTP 429.
Avoid parallel bulk invoice downloads. Daily polling for new invoices is
acceptable according to the supplied documentation; Samba does not provide a
webhook.

The package uses configurable retries, but intentionally does not add aggressive
parallelism.

## Tests

```bash
composer install
vendor/bin/phpunit
```

## License

MIT
