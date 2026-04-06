<?php

namespace App\Enums;

enum BankAccountKind: string
{
    case Liquid = 'liquid';
    case Credit = 'credit';

    public function isLiquid(): bool
    {
        return $this === self::Liquid;
    }
}
