<?php

namespace App\Console\Commands;

use App\Models\CategoryMonthBudget;
use App\Models\SinkingFundRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ApplySinkingFundRules extends Command
{
    protected $signature = 'budget:apply-sinking-fund-rules';

    protected $description = 'Add monthly sinking-fund amounts to the current month category plans (scheduled on the 1st).';

    public function handle(): int
    {
        $now = now();
        $year = (int) $now->year;
        $month = (int) $now->month;

        $applied = 0;

        foreach (SinkingFundRule::query()->where('is_active', true)->with(['budget', 'category'])->cursor() as $rule) {
            if ((int) $rule->category->budget_id !== (int) $rule->budget_id) {
                continue;
            }

            $cacheKey = "sinking_fund_applied:{$rule->id}:{$year}-{$month}";
            if (Cache::has($cacheKey)) {
                continue;
            }

            $line = CategoryMonthBudget::query()->firstOrCreate(
                [
                    'budget_id' => $rule->budget_id,
                    'category_id' => $rule->category_id,
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'amount' => '0',
                    'bank_account_id' => null,
                    'priority' => null,
                ]
            );

            $newAmount = bcadd((string) $line->amount, (string) $rule->monthly_amount, 4);
            $line->update(['amount' => $newAmount]);

            Cache::put($cacheKey, true, $now->copy()->endOfMonth());
            $applied++;
        }

        $this->info("Applied {$applied} sinking fund rule(s) for {$year}-".str_pad((string) $month, 2, '0', STR_PAD_LEFT).'.');

        return self::SUCCESS;
    }
}
