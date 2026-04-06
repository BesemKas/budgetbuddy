<?php

namespace App\Enums;

enum BudgetRole: string
{
    case Owner = 'owner';
    case Viewer = 'viewer';

    public function canManageBudgetSettings(): bool
    {
        return $this === self::Owner;
    }

    public function canManageAccounts(): bool
    {
        return $this === self::Owner;
    }

    public function canManageCategories(): bool
    {
        return $this === self::Owner;
    }

    public function canInvite(): bool
    {
        return $this === self::Owner;
    }

    public function canEditMonthlyBudget(): bool
    {
        return $this === self::Owner;
    }
}
