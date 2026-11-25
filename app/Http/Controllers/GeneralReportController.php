<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\Month;
use App\Models\Category;
use App\Models\Expense;
use App\Models\FixedExpense;
use App\Models\Subcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
     * Category bands for variable expenses, in the order of the category sheet.
     *
     * @var list<string>
     */
    private const VARIABLE_CATEGORY_ORDER = [
        'PKYTP',
        'Vivienda',
        'Financieros',
        'Comida',
        'Otros',
    ];

    /**
     * Subcategories inside each variable category, in the order of the category sheet.
     *
     * @var array<string, list<string>>
     */
    private const SUBCATEGORY_ORDER = [
        'PKYTP' => ['PUNKUYTP', 'IGV/Renta', 'AWS', 'Godaddy/Name.com', 'Asesoría/Terceros', 'Otros'],
        'Vivienda' => ['Luz', 'Agua', 'Mantenimiento', 'Gas', 'Limpieza', 'Cosas para el hogar', 'Netflix', 'HBO'],
        'Financieros' => ['SUNAT (GF)', 'Banco transferencia', 'Wise', 'Seguro Tarjeta (Desg y otros)'],
        'Comida' => ['Super/mercado', 'Restaurantes/bares', 'Rappi', 'Menus'],
        'Otros' => ['Gasolina/Parking/Carro', 'Entretenimiento', 'Ropa/Look', 'Taxis', 'Hair treatment', 'Consultas/Examenes/Medicina', 'Viajes', 'Cursos/Talleres', 'Regalos', 'Otros', 'Deportes'],
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
     * Show the signed-in user's fixed bills and variable expenses for one year.
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
            ->sortBy(fn ($bills, string $category): array => $this->categorySortKey($category))
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
                    'color' => $bills->first()?->subcategory->category->color?->value,
                    'bills' => $rows,
                    'totals' => $this->sumMonths(array_column($rows, 'months')),
                ];
            })
            ->values()
            ->all();

        $variableGroups = $this->variableGroups(
            $request->user()->categories()->with('subcategories')->get(),
            $request->user()->expenses()->where('year', $year)->get(),
        );

        return Inertia::render('general-report/index', [
            'groups' => $groups,
            'totals' => $this->sumMonths(array_column($groups, 'totals')),
            'variableGroups' => $variableGroups,
            'variableTotals' => $this->sumMonths(array_column($variableGroups, 'totals')),
            'year' => $year,
            'months' => array_map(fn (Month $month): array => [
                'value' => $month->value,
                'label' => $month->name,
            ], Month::cases()),
        ]);
    }

    /**
     * List every category and its subcategories, with the year's variable expenses summed into each subcategory.
     *
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, Expense>  $expenses
     * @return list<array{category: string, color: string|null, subcategories: list<array{id: int, name: string, months: array<int, array{month: int, soles: string|null, dollars: string|null}>}>, totals: array<int, array{month: int, soles: string|null, dollars: string|null}>}>
     */
    private function variableGroups(Collection $categories, Collection $expenses): array
    {
        $expensesBySubcategory = $expenses
            ->filter(fn (Expense $expense): bool => $expense->subcategory_id !== null)
            ->groupBy('subcategory_id');

        return $categories
            ->filter(fn (Category $category): bool => $category->subcategories->isNotEmpty())
            ->sortBy(fn (Category $category): array => $this->listSortKey($category->name, self::VARIABLE_CATEGORY_ORDER))
            ->map(function (Category $category) use ($expensesBySubcategory): array {
                $order = self::SUBCATEGORY_ORDER[$category->name] ?? [];
                $rows = $category->subcategories
                    ->sortBy(fn (Subcategory $subcategory): array => $this->listSortKey($subcategory->name, $order))
                    ->values()
                    ->map(function (Subcategory $subcategory) use ($expensesBySubcategory): array {
                        return [
                            'id' => $subcategory->id,
                            'name' => $subcategory->name,
                            'months' => $this->subcategoryMonths(
                                $expensesBySubcategory->get($subcategory->id, collect()),
                            ),
                        ];
                    })
                    ->all();

                return [
                    'category' => $category->name,
                    'color' => $category->color?->value,
                    'subcategories' => $rows,
                    'totals' => $this->sumMonths(array_column($rows, 'months')),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Place the sheet categories first, then any other category by name.
     *
     * @return array{0: int, 1: string}
     */
    private function categorySortKey(string $category): array
    {
        return $this->listSortKey($category, self::CATEGORY_ORDER);
    }

    /**
     * Place names from the sheet first, then any other name alphabetically.
     *
     * @param  list<string>  $order
     * @return array{0: int, 1: string}
     */
    private function listSortKey(string $name, array $order): array
    {
        $position = array_search($name, $order, true);

        return [$position === false ? PHP_INT_MAX : $position, $name];
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
     * Sum one subcategory's variable expenses into the twelve month cells.
     *
     * A month with no purchase stays empty.
     *
     * @param  Collection<int, Expense>  $expenses
     * @return array<int, array{month: int, soles: string|null, dollars: string|null}>
     */
    private function subcategoryMonths(Collection $expenses): array
    {
        $byMonth = $expenses->groupBy(fn (Expense $expense): int => $expense->month->value);

        return array_map(function (Month $month) use ($byMonth): array {
            $soles = null;
            $dollars = null;

            foreach ($byMonth->get($month->value, collect()) as $expense) {
                if ($expense->currency === Currency::Pen) {
                    $soles = $this->addMoney($soles, $expense->amount);
                } else {
                    $dollars = $this->addMoney($dollars, $expense->amount);
                }
            }

            return [
                'month' => $month->value,
                'soles' => $soles,
                'dollars' => $dollars,
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
