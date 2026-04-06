<?php

use App\Enums\LedgerEntryType;
use App\Services\BankStatement\GenericCsvParser;
use Tests\TestCase;

uses(TestCase::class);

it('parses signed amount csv', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'bbcsv');
    if ($path === false) {
        throw new RuntimeException('tempnam failed');
    }

    unlink($path);
    $path .= '.csv';
    file_put_contents($path, "Date,Amount,Description\n2026-01-15,-10.5000,Test shop\n2026-01-16,100,Salary\n");

    $parser = app(GenericCsvParser::class);
    $rows = $parser->parseSignedAmount($path);
    @unlink($path);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['type'])->toBe(LedgerEntryType::Expense)
        ->and($rows[0]['amount'])->toBe('10.5000')
        ->and($rows[1]['type'])->toBe(LedgerEntryType::Income)
        ->and($rows[1]['amount'])->toBe('100.0000');
});

it('parses debit credit csv', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'bbcsv');
    if ($path === false) {
        throw new RuntimeException('tempnam failed');
    }

    unlink($path);
    $path .= '.csv';
    file_put_contents($path, "Date,Debit,Credit,Description\n2026-02-01,25.00,,Coffee\n2026-02-02,,500.00,Pay\n");

    $parser = app(GenericCsvParser::class);
    $rows = $parser->parseDebitCredit($path);
    @unlink($path);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['type'])->toBe(LedgerEntryType::Expense)
        ->and($rows[1]['type'])->toBe(LedgerEntryType::Income);
});

it('parses fnb-style transaction amount column', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'bbcsv');
    if ($path === false) {
        throw new RuntimeException('tempnam failed');
    }

    unlink($path);
    $path .= '.csv';
    file_put_contents($path, "Date,Transaction Amount,Description\n2026-01-10,-50.00,ATM\n");

    $parser = app(GenericCsvParser::class);
    $rows = $parser->parseFnb($path);
    @unlink($path);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['type'])->toBe(LedgerEntryType::Expense)
        ->and($rows[0]['amount'])->toBe('50.0000');
});

it('parses standard bank debit amount and credit amount columns', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'bbcsv');
    if ($path === false) {
        throw new RuntimeException('tempnam failed');
    }

    unlink($path);
    $path .= '.csv';
    file_put_contents($path, "Date,Description,Debit Amount,Credit Amount\n2026-03-01,Shop,12.50,\n2026-03-02,Salary,,800.00\n");

    $parser = app(GenericCsvParser::class);
    $rows = $parser->parseStandardBank($path);
    @unlink($path);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['type'])->toBe(LedgerEntryType::Expense)
        ->and($rows[1]['type'])->toBe(LedgerEntryType::Income);
});
