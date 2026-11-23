<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\Month;
use App\Models\FixedExpense;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeneralReportController extends Controller
{
    /**
     * Category bands, in the order of the fixed-expenses sheet.
     *
     * @var list<string>
     */
    private const CATEGORY_ORDER = [
        'Vivienda',
        'Salud',
        'Financieros',
        'Otros',
    ];

    /**
     * Bills inside each category, in the order of the fixed-expenses sheet.
     *
     * @var array<string, list<string>>
     */
    private const BILL_ORDER = [
        'Vivienda' => ['Renta', 'Celular', 'Internet'],
        'Salud' => ['San Pablo', 'RIMAC'],
        'Financieros' => ['Transferencia (GF)', 'Transferencia (LD)', 'Seguro Tarjeta Interbank'],
        'Otros' => ['Primevideo', 'Rappi Pro', 'Cursor', 'Trello', 'Smartfit', 'Pacifico/Carro', 'Jujitsu'],
    ];

    /**
     * Show the signed-in user's fixed bills for one year.
     */
    public function index(Request $request): Response
    {
        $year = $this->year($request->integer('year'));

        $groups = $request->user()
            ->fixedExpenses()
            ->with([
                'subcategory.category',
                'payments' => fn ($query) => $query->where('year', $year),
            ])
            ->orderBy('name')
            ->get()
            ->groupBy(fn (FixedExpense $fixedExpense): string => $fixedExpense->subcategory->category->name)
            ->sortBy(function ($bills, string $category): array {
                $position = array_search($category, self::CATEGORY_ORDER, true);

                return [$position === false ? PHP_INT_MAX : $position, $category];
            })
            ->map(function ($bills, string $category): array {
                $order = self::BILL_ORDER[$category] ?? [];
                $rows = $bills
                    ->sortBy(function (FixedExpense $fixedExpense) use ($order): array {
                        $position = array_search($fixedExpense->name, $order, true);

                        return [$position === false ? PHP_INT_MAX : $position, $fixedExpense->name];
                    })
                    ->values()
                    ->map(fn (FixedExpense $fixedExpense): array => [
                        'id' => $fixedExpense->id,
                        'name' => $fixedExpense->name,
                        'months' => $this->billMonths($fixedExpense),
                    ])
                    ->all();

                return [
                    'category' => $category,
                    'bills' => $rows,
                    'totals' => $this->sumMonths(array_column($rows, 'months')),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('general-report/index', [
            'groups' => $groups,
            'totals' => $this->sumMonths(array_column($groups, 'totals')),
            'year' => $year,
            'months' => array_map(fn (Month $month): array => [
                'value' => $month->value,
                'label' => $month->name,
            ], Month::cases()),
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
     * Build the twelve month cells for one bill.
     *
     * A recorded payment replaces the usual amount for that month.
     *
     * @return array<int, array{month: int, soles: string|null, dollars: string|null, paid: bool, changed: bool}>
     */
    private function billMonths(FixedExpense $fixedExpense): array
    {
        $payments = $fixedExpense->payments->keyBy(fn ($payment) => $payment->month->value);

        return array_map(function (Month $month) use ($fixedExpense, $payments): array {
            $payment = $payments->get($month->value);
            $amount = $payment === null ? $fixedExpense->amount : $payment->amount;
            $changed = $payment !== null && bccomp((string) $payment->amount, (string) $fixedExpense->amount, 2) !== 0;

            return [
                'month' => $month->value,
                'soles' => $fixedExpense->currency === Currency::Pen ? $amount : null,
                'dollars' => $fixedExpense->currency === Currency::Usd ? $amount : null,
                'paid' => $payment !== null,
                'changed' => $changed,
            ];
        }, Month::cases());
    }

    /**
     * Add Soles and Dólares down a set of month rows.
     *
     * @param  array<int, array<int, array{month: int, soles: string|null, dollars: string|null}>>  $monthRows
     * @return array<int, array{month: int, soles: string|null, dollars: string|null}>
     */
    private function sumMonths(array $monthRows): array
    {
        $totals = [];

        foreach (Month::cases() as $month) {
            $totals[$month->value] = [
                'month' => $month->value,
                'soles' => null,
                'dollars' => null,
            ];
        }

        foreach ($monthRows as $row) {
            foreach ($row as $cell) {
                $totals[$cell['month']]['soles'] = $this->addMoney($totals[$cell['month']]['soles'], $cell['soles']);
                $totals[$cell['month']]['dollars'] = $this->addMoney($totals[$cell['month']]['dollars'], $cell['dollars']);
            }
        }

        return array_values($totals);
    }

    /**
     * Add two money strings. An empty side leaves the other side unchanged.
     */
    private function addMoney(?string $left, ?string $right): ?string
    {
        if ($right === null) {
            return $left;
        }

        if ($left === null) {
            return $right;
        }

        return bcadd($left, $right, 2);
    }
}
