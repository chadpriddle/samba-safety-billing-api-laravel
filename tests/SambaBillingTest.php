<?php

namespace ChadPriddle\SambaBilling\Tests;

use ChadPriddle\SambaBilling\SambaBilling;
use Illuminate\Support\Facades\Http;

class SambaBillingTest extends TestCase
{
    public function test_it_returns_unique_accounts(): void
    {
        Http::fake([
            'billing.example.test/bill/accounts' => Http::response([
                ['id' => 'abc', 'name' => 'Test', 'number' => 'A1'],
                ['id' => 'abc', 'name' => 'Test', 'number' => 'A1'],
                ['id' => 'def', 'name' => 'Other', 'number' => 'A2'],
            ]),
        ]);

        $accounts = app(SambaBilling::class)->accounts();

        $this->assertCount(2, $accounts);
        $this->assertSame('abc', $accounts->first()['id']);
    }

    public function test_it_finds_an_invoice_by_month(): void
    {
        Http::fake([
            'billing.example.test/bill/accounts/acct/invoices' => Http::response([
                [
                    'id' => 'inv1',
                    'invoiceNumber' => 'INV1',
                    'invoiceDate' => '2026-08-31T00:00:00.000+00:00',
                    'hasDetailsFile' => true,
                ],
                [
                    'id' => 'inv2',
                    'invoiceNumber' => 'INV2',
                    'invoiceDate' => '2026-09-30T00:00:00.000+00:00',
                    'hasDetailsFile' => true,
                ],
            ]),
        ]);

        $invoice = app(SambaBilling::class)->invoiceForMonth('acct', 2026, 9);

        $this->assertSame('inv2', $invoice['id']);
    }

    public function test_it_parses_invoice_csv(): void
    {
        $csv = "\"BILLING_MONTH\",\"CUSTOMER\",\"TOTAL\"\n"
            . "\"202609\",\"ABC Towing\",\"25.50\"\n";

        $rows = app(SambaBilling::class)->parseCsv($csv);

        $this->assertCount(1, $rows);
        $this->assertSame('ABC Towing', $rows->first()['CUSTOMER']);
        $this->assertSame('25.50', $rows->first()['TOTAL']);
    }
}
