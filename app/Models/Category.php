<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'budget_id',
        'name',
        'type',
        'is_system',
        'internal_transfer',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'internal_transfer' => 'boolean',
            'type' => LedgerEntryType::class,
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
    public function monthBudgets(): HasMany
    {
        return $this->hasMany(CategoryMonthBudget::class);
    }

    /**
     * System defaults plus custom categories for the budget.
     *
     * @param  Builder<Category>  $query
     */
    public function scopeVisibleToBudget(Builder $query, Budget $budget): void
    {
        $query->where(function (Builder $q) use ($budget): void {
            $q->where(function (Builder $inner): void {
                $inner->whereNull('user_id')->where('is_system', true);
            })->orWhere('budget_id', $budget->id);
        });
    }
}
