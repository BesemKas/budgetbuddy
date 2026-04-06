<?php

namespace App\Models;

use App\Enums\BudgetRole;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'owner_user_id',
        'base_currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_currency' => 'string',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<BankAccount, $this>
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<BudgetInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(BudgetInvitation::class);
    }

    public function roleFor(User $user): ?BudgetRole
    {
        $pivot = $this->users()->where('users.id', $user->id)->first()?->pivot;

        if ($pivot === null) {
            return null;
        }

        return BudgetRole::from($pivot->role);
    }

    public static function bootstrapPersonalForUser(User $user): self
    {
        $budget = self::query()->create([
            'name' => 'Personal',
            'owner_user_id' => $user->id,
            'base_currency' => $user->base_currency ?? 'ZAR',
        ]);

        $budget->users()->attach($user->id, [
            'role' => BudgetRole::Owner->value,
        ]);

        return $budget;
    }
}
