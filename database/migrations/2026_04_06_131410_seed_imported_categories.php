<?php

use App\Enums\LedgerEntryType;
use App\Models\Category;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ([
            ['name' => 'Imported', 'type' => LedgerEntryType::Expense],
            ['name' => 'Imported income', 'type' => LedgerEntryType::Income],
        ] as $row) {
            Category::query()->firstOrCreate(
                [
                    'name' => $row['name'],
                    'user_id' => null,
                    'type' => $row['type']->value,
                ],
                ['is_system' => true, 'budget_id' => null]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Category::query()
            ->whereIn('name', ['Imported', 'Imported income'])
            ->where('is_system', true)
            ->whereNull('user_id')
            ->delete();
    }
};
