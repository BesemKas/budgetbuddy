<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sinking_fund_rules', function (Blueprint $table) {
            $table->string('goal_name')->nullable()->after('monthly_amount');
            $table->decimal('target_amount', 15, 2)->nullable()->after('goal_name');
        });
    }

    public function down(): void
    {
        Schema::table('sinking_fund_rules', function (Blueprint $table) {
            $table->dropColumn(['goal_name', 'target_amount']);
        });
    }
};
