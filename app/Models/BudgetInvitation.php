<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BudgetInvitation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'budget_id',
        'email',
        'token_hash',
        'expires_at',
        'accepted_at',
        'invited_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * @return BelongsToMany<BankAccount, $this>
     */
    public function bankAccounts(): BelongsToMany
    {
        return $this->belongsToMany(BankAccount::class, 'budget_invitation_bank_account', 'budget_invitation_id', 'bank_account_id')
            ->withTimestamps();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
