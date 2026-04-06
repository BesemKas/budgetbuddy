<?php

namespace App\Enums;

enum BudgetPriority: string
{
    case Needs = 'needs';
    case Wants = 'wants';
    case Savings = 'savings';
}
