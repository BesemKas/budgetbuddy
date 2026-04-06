<?php

namespace App\Services;

/**
 * Indicative monthly PAYE and UIF from gross salary. Not tax advice — excludes medical credits, RA deductions, etc.
 */
final class SarsPayeEstimator
{
    /**
     * @return array{
     *     tax_year_label: string,
     *     gross_monthly: float,
     *     taxable_annual: float,
     *     annual_tax_before_rebate: float,
     *     rebate: float,
     *     annual_tax: float,
     *     monthly_paye: float,
     *     monthly_uif_employee: float,
     *     net_monthly: float,
     * }
     */
    public function estimateMonthly(float $grossMonthly, bool $age65Plus = false, bool $age75Plus = false): array
    {
        $grossMonthly = max(0, $grossMonthly);
        $taxableAnnual = $grossMonthly * 12;

        $annualTaxBeforeRebate = $this->annualTaxBeforeRebate($taxableAnnual);
        $rebate = $this->rebate($age65Plus, $age75Plus);
        $annualTax = max(0.0, round($annualTaxBeforeRebate - $rebate, 2));
        $monthlyPaye = round($annualTax / 12, 2);

        $uifConfig = config('sars_tax.uif', []);
        $rate = (float) ($uifConfig['employee_rate'] ?? 0.01);
        $ceiling = (float) ($uifConfig['monthly_ceiling'] ?? 17_712);
        $rawUif = $grossMonthly * $rate;
        $monthlyUif = round(min($rawUif, $ceiling * $rate), 2);

        $netMonthly = round($grossMonthly - $monthlyPaye - $monthlyUif, 2);

        return [
            'tax_year_label' => (string) config('sars_tax.tax_year_label'),
            'gross_monthly' => round($grossMonthly, 2),
            'taxable_annual' => round($taxableAnnual, 2),
            'annual_tax_before_rebate' => round($annualTaxBeforeRebate, 2),
            'rebate' => round($rebate, 2),
            'annual_tax' => $annualTax,
            'monthly_paye' => $monthlyPaye,
            'monthly_uif_employee' => $monthlyUif,
            'net_monthly' => $netMonthly,
        ];
    }

    /**
     * Progressive tax on annual taxable income (2026 year of assessment bands — update when SARS publishes changes).
     *
     * @see https://www.sars.gov.za/tax-rates/income-tax/rates-of-tax-for-individuals/
     */
    private function annualTaxBeforeRebate(float $ti): float
    {
        if ($ti <= 0) {
            return 0.0;
        }

        if ($ti <= 245_100) {
            return $ti * 0.18;
        }

        if ($ti <= 383_100) {
            return 44_118 + ($ti - 245_100) * 0.26;
        }

        if ($ti <= 530_200) {
            return 79_998 + ($ti - 383_100) * 0.31;
        }

        if ($ti <= 695_800) {
            return 125_599 + ($ti - 530_200) * 0.36;
        }

        if ($ti <= 887_000) {
            return 185_215 + ($ti - 695_800) * 0.39;
        }

        if ($ti <= 1_878_600) {
            return 259_783 + ($ti - 887_000) * 0.41;
        }

        return 666_339 + ($ti - 1_878_600) * 0.45;
    }

    private function rebate(bool $age65Plus, bool $age75Plus): float
    {
        $rebates = config('sars_tax.rebates', []);
        $primary = (float) ($rebates['primary_under_65'] ?? 17_235);
        $secondary = (float) ($rebates['secondary_65_and_older'] ?? 9_444);
        $tertiary = (float) ($rebates['tertiary_75_and_older'] ?? 3_145);

        if ($age75Plus) {
            return $primary + $secondary + $tertiary;
        }

        if ($age65Plus) {
            return $primary + $secondary;
        }

        return $primary;
    }
}
