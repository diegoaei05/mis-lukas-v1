<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\Month;
use App\Models\Expense;
use App\Models\FixedExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show fixed bills and variable expenses totaled for each month of a year.
     */
    public function index(Request $request): Response
    {
        $year = $this->year($request->integer('year'));

        $totals = $request->user()
            ->expenses()
            ->where('year', $year)
            ->select(['month', 'currency'])
            ->selectRaw('SUM(amount) as total')
            ->groupBy('month', 'currency')
            ->get();

        return Inertia::render('dashboard', [
            'year' => $year,
            'fixedMonths' => $this->fixedMonths(
                $request->user()
                    ->fixedExpenses()
                    ->with(['payments' => fn ($query) => $query->where('year', $year)])
                    ->get(),
            ),
            'variableMonths' => $this->months($totals),
        ]);
    }

    /**
     * Keep a requested year inside the range the form allows.
     */
    private function year(int $year): int
    {
        if ($year < 2000 || $year > 2100) {
            return (int) now()->year;
        }

        return $year;
    }

    /**
     * Twelve months, with a total for each currency.
     *
     * @param  Collection<int, Expense>  $totals
     * @return list<array{month: int, label: string, soles: string, dollars: string}>
     */
    private function months(Collection $totals): array
    {
        $months = [];

        foreach (Month::cases() as $month) {
            $months[$month->value] = [
                'month' => $month->value,
                'label' => $month->name,
                'soles' => '0.00',
                'dollars' => '0.00',
            ];
        }

        foreach ($totals as $total) {
            $month = $total->month->value;
            $amount = number_format((float) $total->getAttribute('total'), 2, '.', '');

            if ($total->currency === Currency::Pen) {
                $months[$month]['soles'] = $amount;
            } else {
                $months[$month]['dollars'] = $amount;
            }
        }

        return array_values($months);
    }

    /**
     * Twelve months of fixed bills. A payment replaces the usual amount.
     *
     * @param  Collection<int, FixedExpense>  $bills
     * @return list<array{month: int, label: string, soles: string, dollars: string}>
     */
    private function fixedMonths(Collection $bills): array
    {
        $months = [];

        foreach (Month::cases() as $month) {
            $months[$month->value] = [
                'month' => $month->value,
                'label' => $month->name,
                'soles' => '0.00',
                'dollars' => '0.00',
            ];
        }

        foreach ($bills as $bill) {
            $payments = $bill->payments->keyBy(fn ($payment) => $payment->month->value);

            foreach (Month::cases() as $month) {
                $payment = $payments->get($month->value);
                $amount = $payment === null ? (string) $bill->amount : (string) $payment->amount;
                $side = $bill->currency === Currency::Pen ? 'soles' : 'dollars';
                $months[$month->value][$side] = bcadd($months[$month->value][$side], $amount, 2);
            }
        }

        return array_values($months);
    }
}
