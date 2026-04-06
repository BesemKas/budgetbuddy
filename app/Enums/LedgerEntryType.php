<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case Income = 'income';
    case Expense = 'expense';
}
