<?php

use App\Enums\LedgerEntryType;
use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('internal_transfer')->default(false)->after('is_system');
        });

        foreach ([
            LedgerEntryType::Expense,
            LedgerEntryType::Income,
        ] as $type) {
            Category::query()->updateOrCreate(
                [
                    'name' => 'Account transfer',
                    'user_id' => null,
                    'type' => $type->value,
                ],
                [
                    'is_system' => true,
                    'internal_transfer' => true,
                    'budget_id' => null,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Category::query()
            ->where('name', 'Account transfer')
            ->whereNull('user_id')
            ->whereIn('type', [LedgerEntryType::Expense->value, LedgerEntryType::Income->value])
            ->delete();

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('internal_transfer');
        });
    }
};
