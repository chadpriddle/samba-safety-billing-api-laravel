<?php

namespace ChadPriddle\SambaBilling;

use ChadPriddle\SambaBilling\Exceptions\AuthenticationException;
use ChadPriddle\SambaBilling\Exceptions\InvoiceDetailsUnavailableException;
use ChadPriddle\SambaBilling\Exceptions\RateLimitException;
use ChadPriddle\SambaBilling\Exceptions\SambaBillingException;
use DateTimeImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class SambaBilling
{
    public function __construct(
        protected ?string $baseUrl = null,
        protected ?string $apiKey = null,
    ) {
        $this->baseUrl ??= (string) config('samba-billing.url');
        $this->apiKey ??= (string) config('samba-billing.api_key');
    }

    protected function http(): PendingRequest
    {
        if ($this->apiKey === '') {
            throw new SambaBillingException('Samba Billing API key is not configured.');
        }

        return Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout((int) config('samba-billing.timeout', 60))
            ->connectTimeout((int) config('samba-billing.connect_timeout', 15))
            ->retry(
                (int) config('samba-billing.retry.times', 3),
                (int) config('samba-billing.retry.sleep_ms', 2000),
                throw: false
            );
    }

    protected function url(string $endpoint): string
    {
        return rtrim((string) $this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    protected function ensureSuccessful(Response $response): Response
    {
        if ($response->status() === 401 || $response->status() === 403) {
            throw new AuthenticationException(
                'Samba Billing API authentication failed. Check the API key and account permissions.',
                $response->status()
            );
        }

        if ($response->status() === 429) {
            throw new RateLimitException(
                'Samba Billing API rate limit exceeded. Reduce polling/concurrency and try again later.',
                429
            );
        }

        if ($response->failed()) {
            throw new SambaBillingException(
                sprintf(
                    'Samba Billing API request failed with HTTP %d: %s',
                    $response->status(),
                    mb_substr($response->body(), 0, 1000)
                ),
                $response->status()
            );
        }

        return $response;
    }

    /**
     * Return accessible accounts, de-duplicated by Samba's opaque account id.
     */
    public function accounts(): Collection
    {
        $response = $this->ensureSuccessful(
            $this->http()->get($this->url('/bill/accounts'))
        );

        return collect($response->json() ?? [])
            ->unique('id')
            ->values();
    }

    public function account(string $accountId): ?array
    {
        return $this->accounts()->firstWhere('id', $accountId);
    }

    /**
     * Samba documents this list as containing approximately the last 18 months.
     */
    public function invoices(string $accountId): Collection
    {
        $response = $this->ensureSuccessful(
            $this->http()->get(
                $this->url('/bill/accounts/' . rawurlencode($accountId) . '/invoices')
            )
        );

        return collect($response->json() ?? []);
    }

    public function invoice(string $accountId, string $invoiceId): ?array
    {
        return $this->invoices($accountId)->firstWhere('id', $invoiceId);
    }

    /**
     * Find the invoice whose invoiceDate falls in the requested calendar month.
     *
     * Samba does not provide a direct "invoice for month" endpoint; month
     * selection is derived from invoiceDate.
     */
    public function invoiceForMonth(string $accountId, int $year, int $month): ?array
    {
        return $this->invoices($accountId)
            ->filter(function (array $invoice) use ($year, $month): bool {
                if (empty($invoice['invoiceDate'])) {
                    return false;
                }

                try {
                    $date = new DateTimeImmutable($invoice['invoiceDate']);
                } catch (\Throwable) {
                    return false;
                }

                return (int) $date->format('Y') === $year
                    && (int) $date->format('n') === $month;
            })
            ->sortByDesc('invoiceDate')
            ->first();
    }

    public function invoiceDetails(string $accountId, string $invoiceId): string
    {
        $invoice = $this->invoice($accountId, $invoiceId);

        if ($invoice !== null && array_key_exists('hasDetailsFile', $invoice)
            && ! $invoice['hasDetailsFile']) {
            throw new InvoiceDetailsUnavailableException(
                "Invoice {$invoiceId} does not have a details file."
            );
        }

        $response = $this->ensureSuccessful(
            $this->http()
                ->accept('text/csv')
                ->get(
                    $this->url(
                        '/bill/accounts/' . rawurlencode($accountId)
                        . '/invoices/' . rawurlencode($invoiceId)
                    )
                )
        );

        return $response->body();
    }

    public function invoiceDetailsArray(string $accountId, string $invoiceId): Collection
    {
        return $this->parseCsv($this->invoiceDetails($accountId, $invoiceId));
    }

    public function transactionsForMonth(
        string $accountId,
        int $year,
        int $month
    ): Collection {
        $invoice = $this->invoiceForMonth($accountId, $year, $month);

        if ($invoice === null) {
            return collect();
        }

        if (array_key_exists('hasDetailsFile', $invoice) && ! $invoice['hasDetailsFile']) {
            return collect();
        }

        return $this->invoiceDetailsArray($accountId, (string) $invoice['id']);
    }

    public function downloadInvoiceDetails(
        string $accountId,
        string $invoiceId,
        string $path
    ): string {
        $csv = $this->invoiceDetails($accountId, $invoiceId);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new SambaBillingException("Unable to create directory: {$directory}");
        }

        if (file_put_contents($path, $csv) === false) {
            throw new SambaBillingException("Unable to write invoice details to: {$path}");
        }

        return $path;
    }

    public function parseCsv(string $csv): Collection
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new SambaBillingException('Unable to create temporary stream for CSV parsing.');
        }

        fwrite($handle, $csv);
        rewind($handle);

        $headers = fgetcsv($handle);

        if ($headers === false || $headers === [null]) {
            fclose($handle);
            return collect();
        }

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);

        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || count($data) !== count($headers)) {
                continue;
            }

            $rows[] = array_combine($headers, $data);
        }

        fclose($handle);

        return collect($rows);
    }
}
