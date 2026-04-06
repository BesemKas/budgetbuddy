<?php

namespace Database\Seeders;

use App\Enums\LedgerEntryType;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            ['name' => 'Salary', 'type' => LedgerEntryType::Income, 'internal_transfer' => false],
            ['name' => 'Groceries', 'type' => LedgerEntryType::Expense, 'internal_transfer' => false],
            ['name' => 'Rent / Bond', 'type' => LedgerEntryType::Expense, 'internal_transfer' => false],
            ['name' => 'Transport', 'type' => LedgerEntryType::Expense, 'internal_transfer' => false],
            ['name' => 'Utilities', 'type' => LedgerEntryType::Expense, 'internal_transfer' => false],
            ['name' => 'Imported', 'type' => LedgerEntryType::Expense, 'internal_transfer' => false],
            ['name' => 'Imported income', 'type' => LedgerEntryType::Income, 'internal_transfer' => false],
            ['name' => 'Account transfer', 'type' => LedgerEntryType::Expense, 'internal_transfer' => true],
            ['name' => 'Account transfer', 'type' => LedgerEntryType::Income, 'internal_transfer' => true],
        ];

        foreach ($defaults as $row) {
            Category::query()->firstOrCreate(
                [
                    'name' => $row['name'],
                    'user_id' => null,
                    'type' => $row['type']->value,
                ],
                [
                    'is_system' => true,
                    'internal_transfer' => $row['internal_transfer'],
                ]
            );
        }
    }
}
