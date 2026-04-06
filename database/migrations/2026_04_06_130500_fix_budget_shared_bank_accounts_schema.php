<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('budget_invitation_bank_account');
        Schema::dropIfExists('budget_shared_bank_accounts');

        Schema::create('budget_shared_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['budget_id', 'user_id', 'bank_account_id'], 'budget_shared_acct_member_unique');
        });

        Schema::create('budget_invitation_bank_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_invitation_id')->constrained('budget_invitations')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['budget_invitation_id', 'bank_account_id'], 'budget_invitation_acct_unique');
        });

        DB::transaction(function (): void {
            $viewerRows = DB::table('budget_user')->where('role', 'viewer')->get();
            foreach ($viewerRows as $row) {
                $accountIds = DB::table('bank_accounts')
                    ->where('budget_id', $row->budget_id)
                    ->pluck('id');
                foreach ($accountIds as $accountId) {
                    DB::table('budget_shared_bank_accounts')->insertOrIgnore([
                        'budget_id' => $row->budget_id,
                        'user_id' => $row->user_id,
                        'bank_account_id' => $accountId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $invitations = DB::table('budget_invitations')->whereNull('accepted_at')->get();
            foreach ($invitations as $inv) {
                $accountIds = DB::table('bank_accounts')
                    ->where('budget_id', $inv->budget_id)
                    ->pluck('id');
                foreach ($accountIds as $accountId) {
                    DB::table('budget_invitation_bank_account')->insertOrIgnore([
                        'budget_invitation_id' => $inv->id,
                        'bank_account_id' => $accountId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_invitation_bank_account');
        Schema::dropIfExists('budget_shared_bank_accounts');
    }
};
