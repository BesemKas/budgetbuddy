<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SinkingFundRule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'budget_id',
        'category_id',
        'monthly_amount',
        'goal_name',
        'target_amount',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'target_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Rough months to reach target_amount saving monthly_amount (from zero), for UI hints only.
     */
    public function estimatedMonthsAtMonthlyRate(): ?int
    {
        if ($this->target_amount === null) {
            return null;
        }
        if (bccomp((string) $this->monthly_amount, '0', 2) <= 0) {
            return null;
        }
        if (bccomp((string) $this->target_amount, '0', 2) <= 0) {
            return null;
        }

        return (int) ceil((float) $this->target_amount / (float) $this->monthly_amount);
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
