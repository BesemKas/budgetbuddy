<?php

use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->foreignId('budget_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('budget_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('budget_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::transaction(function (): void {
            foreach (User::query()->cursor() as $user) {
                $budget = Budget::query()->create([
                    'name' => 'Personal',
                    'owner_user_id' => $user->id,
                    'base_currency' => $user->base_currency ?? 'ZAR',
                ]);

                $budget->users()->attach($user->id, [
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                BankAccount::query()->where('user_id', $user->id)->update(['budget_id' => $budget->id]);
                Transaction::query()->where('user_id', $user->id)->update(['budget_id' => $budget->id]);
                Category::query()->where('user_id', $user->id)->update(['budget_id' => $budget->id]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_id');
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_id');
        });
    }
};
