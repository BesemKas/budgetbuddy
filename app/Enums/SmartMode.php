<?php

namespace App\Enums;

enum SmartMode: string
{
    case Standard = 'standard';
    case Survival = 'survival';
    case ZeroBased = 'zero_based';
    case Travel = 'travel';

    public function label(): string
    {
        return match ($this) {
            self::Standard => __('Standard'),
            self::Survival => __('Survival'),
            self::ZeroBased => __('Zero-based'),
            self::Travel => __('Travel'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Standard => __('Balanced defaults for everyday budgeting.'),
            self::Survival => __('Large expenses need a short note so every spend is intentional.'),
            self::ZeroBased => __('Every transaction needs a note so each rand has a purpose.'),
            self::Travel => __('Highlights planning for foreign currency and trip spending.'),
        };
    }
}
