<?php

use App\Services\SarsPayeEstimator;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $gross_monthly = '';

    public bool $age_65_plus = false;

    public bool $age_75_plus = false;

    public function updatedAge75Plus(bool $value): void
    {
        if ($value) {
            $this->age_65_plus = true;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEstimateProperty(): ?array
    {
        if ($this->gross_monthly === '' || ! is_numeric($this->gross_monthly)) {
            return null;
        }

        $gross = (float) $this->gross_monthly;
        if ($gross < 0) {
            return null;
        }

        return app(SarsPayeEstimator::class)->estimateMonthly(
            $gross,
            $this->age_65_plus || $this->age_75_plus,
            $this->age_75_plus,
        );
    }
};
?>

<div class="bb-page max-w-lg">
    <h1 class="text-2xl font-semibold tracking-tight">{{ __('Tax calculator') }}</h1>
    <p class="text-base-content/70 mt-1 text-sm">
        {{ __('Indicative monthly PAYE and UIF from gross salary, using SARS individual tables in config. Does not include medical credits, retirement fund deductions, or other adjustments.') }}
    </p>

    <div class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm">
        <div class="card-body gap-4 p-4 sm:p-6">
            <label class="form-control w-full">
                <span class="label-text">{{ __('Gross salary per month (ZAR)') }}</span>
                <input
                    type="text"
                    inputmode="decimal"
                    class="input input-bordered w-full font-mono"
                    wire:model.live="gross_monthly"
                    placeholder="0.00"
                />
            </label>

            <label class="label cursor-pointer justify-start gap-3">
                <input type="checkbox" class="checkbox checkbox-sm" wire:model.live="age_65_plus" @disabled($this->age_75_plus) />
                <span class="label-text">{{ __('65 years or older (secondary rebate)') }}</span>
            </label>

            <label class="label cursor-pointer justify-start gap-3">
                <input type="checkbox" class="checkbox checkbox-sm" wire:model.live="age_75_plus" />
                <span class="label-text">{{ __('75 years or older (includes tertiary rebate)') }}</span>
            </label>

            @if ($this->estimate)
                <dl class="stats stats-vertical sm:stats-horizontal shadow-sm">
                    <div class="stat place-items-start py-3">
                        <dt class="stat-title">{{ __('Tax year') }}</dt>
                        <dd class="stat-value text-base">{{ $this->estimate['tax_year_label'] }}</dd>
                    </div>
                    <div class="stat place-items-start py-3">
                        <dt class="stat-title">{{ __('Est. PAYE / month') }}</dt>
                        <dd class="stat-value text-base font-mono">
                            {{ number_format($this->estimate['monthly_paye'], 2) }}
                        </dd>
                    </div>
                    <div class="stat place-items-start py-3">
                        <dt class="stat-title">{{ __('UIF (employee) / month') }}</dt>
                        <dd class="stat-value text-base font-mono">
                            {{ number_format($this->estimate['monthly_uif_employee'], 2) }}
                        </dd>
                    </div>
                    <div class="stat place-items-start py-3">
                        <dt class="stat-title">{{ __('Est. net / month') }}</dt>
                        <dd class="stat-value text-primary text-lg font-mono">
                            {{ number_format($this->estimate['net_monthly'], 2) }}
                        </dd>
                    </div>
                </dl>
                <p class="text-base-content/60 text-xs">
                    {{ __('Annual tax before rebates: :a — rebates applied: :b — annual tax: :c', [
                        'a' => number_format($this->estimate['annual_tax_before_rebate'], 2),
                        'b' => number_format($this->estimate['rebate'], 2),
                        'c' => number_format($this->estimate['annual_tax'], 2),
                    ]) }}
                </p>
            @elseif ($this->gross_monthly !== '')
                <p class="text-base-content/60 text-sm">{{ __('Enter a valid non-negative amount.') }}</p>
            @endif
        </div>
    </div>

    <p class="mt-6 text-base-content/60 text-xs">
        {{ __('This is educational software, not professional tax advice. Consult SARS or a registered tax practitioner.') }}
    </p>

    <div class="mt-4">
        <a href="{{ route('settings') }}" class="btn btn-ghost btn-sm w-full sm:w-auto" wire:navigate>{{ __('Back to settings') }}</a>
    </div>
</div>
