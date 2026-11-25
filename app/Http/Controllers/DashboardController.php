<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\Month;
use App\Models\Category;
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
     * Show one month as two category pies: fixed bills and variable expenses.
     */
    public function show(Request $request, int $year, string $month): Response
    {
        $monthEnum = $this->month($year, $month);

        return Inertia::render('dashboard/month', [
            'year' => $year,
            'month' => $monthEnum->slug(),
            'monthLabel' => $monthEnum->name,
            'soles' => [
                'fixed' => $this->fixedCategories($request, $year, $monthEnum, Currency::Pen),
                'variable' => $this->variableCategories($request, $year, $monthEnum, Currency::Pen),
            ],
            'dollars' => [
                'fixed' => $this->fixedCategories($request, $year, $monthEnum, Currency::Usd),
                'variable' => $this->variableCategories($request, $year, $monthEnum, Currency::Usd),
            ],
        ]);
    }

    /**
     * Show one category as a ring of its subcategories.
     */
    public function subcategories(Request $request, int $year, string $month, Category $category): Response
    {
        $monthEnum = $this->month($year, $month);

        if ($category->user_id !== $request->user()->id) {
            abort(404);
        }

        return Inertia::render('dashboard/subcategories', [
            'year' => $year,
            'month' => $monthEnum->slug(),
            'monthLabel' => $monthEnum->name,
            'categoryId' => $category->id,
            'category' => $category->name,
            'categories' => $request->user()
                ->categories()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Category $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                ])
                ->all(),
            'soles' => [
                'fixed' => $this->fixedSubcategories($request, $year, $monthEnum, Currency::Pen, $category),
                'variable' => $this->variableSubcategories($request, $year, $monthEnum, Currency::Pen, $category),
            ],
            'dollars' => [
                'fixed' => $this->fixedSubcategories($request, $year, $monthEnum, Currency::Usd, $category),
                'variable' => $this->variableSubcategories($request, $year, $monthEnum, Currency::Usd, $category),
            ],
        ]);
    }

    /**
     * Keep a requested year and month, or end the request.
     */
    private function month(int $year, string $month): Month
    {
        if ($year < 2000 || $year > 2100) {
            abort(404);
        }

        $monthEnum = Month::fromSlug($month);

        if ($monthEnum === null) {
            abort(404);
        }

        return $monthEnum;
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

    /**
     * Fixed bills for one month and currency, summed by category.
     *
     * @return list<array{id: int|null, category: string, color: string|null, amount: string}>
     */
    private function fixedCategories(Request $request, int $year, Month $month, Currency $currency): array
    {
        $bills = $request->user()
            ->fixedExpenses()
            ->where('currency', $currency)
            ->with([
                'subcategory.category',
                'payments' => fn ($query) => $query->where('year', $year)->where('month', $month),
            ])
            ->get();

        $totals = [];

        foreach ($bills as $bill) {
            $category = $bill->subcategory->category;
            $payment = $bill->payments->first();
            $amount = $payment === null ? (string) $bill->amount : (string) $payment->amount;
            $this->addSlice($totals, (string) $category->id, $category->name, $category->color?->value, $amount, $category->id);
        }

        return $this->sortedSlices($totals);
    }

    /**
     * Variable expenses for one month and currency, summed by category.
     *
     * @return list<array{id: int|null, category: string|null, color: string|null, amount: string}>
     */
    private function variableCategories(Request $request, int $year, Month $month, Currency $currency): array
    {
        $expenses = $request->user()
            ->expenses()
            ->where('year', $year)
            ->where('month', $month)
            ->where('currency', $currency)
            ->with(['category', 'subcategory.category'])
            ->get();

        $totals = [];

        foreach ($expenses as $expense) {
            $category = $expense->category ?? $expense->subcategory?->category;
            $key = $category === null ? 'none' : (string) $category->id;
            $this->addSlice($totals, $key, $category?->name, $category?->color?->value, (string) $expense->amount, $category?->id);
        }

        return $this->sortedSlices($totals);
    }

    /**
     * Fixed bills in one category, summed by subcategory.
     *
     * @return list<array{id: int|null, category: string|null, color: string|null, amount: string}>
     */
    private function fixedSubcategories(Request $request, int $year, Month $month, Currency $currency, Category $category): array
    {
        $bills = $request->user()
            ->fixedExpenses()
            ->where('currency', $currency)
            ->whereHas('subcategory', fn ($query) => $query->where('category_id', $category->id))
            ->with([
                'subcategory',
                'payments' => fn ($query) => $query->where('year', $year)->where('month', $month),
            ])
            ->get();

        $totals = [];

        foreach ($bills as $bill) {
            $subcategory = $bill->subcategory;
            $payment = $bill->payments->first();
            $amount = $payment === null ? (string) $bill->amount : (string) $payment->amount;
            $this->addSlice($totals, (string) $subcategory->id, $subcategory->name, null, $amount);
        }

        return $this->sortedSlices($totals);
    }

    /**
     * Variable expenses in one category, summed by subcategory.
     *
     * @return list<array{id: int|null, category: string|null, color: string|null, amount: string}>
     */
    private function variableSubcategories(Request $request, int $year, Month $month, Currency $currency, Category $category): array
    {
        $expenses = $request->user()
            ->expenses()
            ->where('year', $year)
            ->where('month', $month)
            ->where('currency', $currency)
            ->with(['category', 'subcategory.category'])
            ->get();

        $totals = [];

        foreach ($expenses as $expense) {
            $resolved = $expense->category ?? $expense->subcategory?->category;

            if ($resolved === null || $resolved->id !== $category->id) {
                continue;
            }

            $subcategory = $expense->subcategory;
            $key = $subcategory === null ? 'none' : (string) $subcategory->id;
            $this->addSlice($totals, $key, $subcategory?->name, null, (string) $expense->amount);
        }

        return $this->sortedSlices($totals);
    }

    /**
     * @param  array<string, array{id: int|null, category: string|null, color: string|null, amount: string}>  $totals
     */
    private function addSlice(array &$totals, string $key, ?string $category, ?string $color, string $amount, ?int $id = null): void
    {
        if (! isset($totals[$key])) {
            $totals[$key] = [
                'id' => $id,
                'category' => $category,
                'color' => $color,
                'amount' => '0.00',
            ];
        }

        $totals[$key]['amount'] = bcadd($totals[$key]['amount'], $amount, 2);
    }

    /**
     * @param  array<string, array{id: int|null, category: string|null, color: string|null, amount: string}>  $totals
     * @return list<array{id: int|null, category: string|null, color: string|null, amount: string}>
     */
    private function sortedSlices(array $totals): array
    {
        $slices = array_values(array_filter(
            $totals,
            fn (array $slice): bool => bccomp($slice['amount'], '0.00', 2) === 1,
        ));

        usort($slices, function (array $left, array $right): int {
            $byAmount = bccomp($right['amount'], $left['amount'], 2);

            if ($byAmount !== 0) {
                return $byAmount;
            }

            return strcasecmp((string) $left['category'], (string) $right['category']);
        });

        return $slices;
    }
}
