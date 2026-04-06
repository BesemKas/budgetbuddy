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
            ['name' => 'Salary', 'type' => LedgerEntryType::Income],
            ['name' => 'Groceries', 'type' => LedgerEntryType::Expense],
            ['name' => 'Rent / Bond', 'type' => LedgerEntryType::Expense],
            ['name' => 'Transport', 'type' => LedgerEntryType::Expense],
            ['name' => 'Utilities', 'type' => LedgerEntryType::Expense],
        ];

        foreach ($defaults as $row) {
            Category::query()->firstOrCreate(
                [
                    'name' => $row['name'],
                    'user_id' => null,
                    'type' => $row['type']->value,
                ],
                ['is_system' => true]
            );
        }
    }
}
