<?php

namespace App\Models;

use App\Enums\BankAccountKind;
use App\Enums\LedgerEntryType;
use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'budget_id',
        'name',
        'kind',
        'currency_code',
        'balance',
        'include_in_budget_reports',
        'exchange_rate',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => BankAccountKind::class,
            'balance' => 'decimal:4',
            'include_in_budget_reports' => 'boolean',
            'exchange_rate' => 'decimal:8',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<CategoryMonthBudget, $this>
     */
    public function categoryMonthBudgets(): HasMany
    {
        return $this->hasMany(CategoryMonthBudget::class);
    }

    public function recalculateBalanceFromTransactions(): void
    {
        $income = (string) $this->transactions()
            ->where('type', LedgerEntryType::Income)
            ->sum('amount');

        $expense = (string) $this->transactions()
            ->where('type', LedgerEntryType::Expense)
            ->sum('amount');

        $balance = bcsub($income, $expense, 4);

        $this->updateQuietly(['balance' => $balance]);
    }
}
