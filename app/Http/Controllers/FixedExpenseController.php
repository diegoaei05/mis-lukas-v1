<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\Month;
use App\Http\Requests\DestroyFixedExpensePaymentRequest;
use App\Http\Requests\StoreFixedExpensePaymentRequest;
use App\Http\Requests\StoreFixedExpenseRequest;
use App\Models\Category;
use App\Models\ExpenseType;
use App\Models\FixedExpense;
use App\Models\Subcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FixedExpenseController extends Controller
{
    /**
     * Show the signed-in user's fixed expenses for one month.
     */
    public function index(Request $request): Response
    {
        [$month, $year] = $this->period($request->integer('month'), $request->integer('year'));

        $fixedExpenses = $request->user()
            ->fixedExpenses()
            ->with([
                'expenseType',
                'subcategory.category',
                'payments' => fn ($query) => $query->where('year', $year)->where('month', $month),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (FixedExpense $fixedExpense) {
                $payment = $fixedExpense->payments->first();

                return [
                    'id' => $fixedExpense->id,
                    'name' => $fixedExpense->name,
                    'type' => $fixedExpense->expenseType->acronym,
                    'category' => $fixedExpense->subcategory->category->name,
                    'subcategory' => $fixedExpense->subcategory->name,
                    'amount' => $fixedExpense->amount,
                    'soles' => $fixedExpense->currency === Currency::Pen ? $fixedExpense->amount : null,
                    'dollars' => $fixedExpense->currency === Currency::Usd ? $fixedExpense->amount : null,
                    'paidAmount' => $payment?->amount,
                ];
            })
            ->all();

        $expenseTypes = $request->user()
            ->expenseTypes()
            ->orderBy('acronym')
            ->get(['id', 'acronym', 'description'])
            ->map(fn (ExpenseType $expenseType): array => [
                'id' => $expenseType->id,
                'acronym' => $expenseType->acronym,
                'description' => $expenseType->description,
            ])
            ->all();

        $categories = $request->user()
            ->categories()
            ->with(['subcategories' => function ($query) {
                $query->orderBy('name')->select(['id', 'category_id', 'name']);
            }])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'subcategories' => $category->subcategories
                    ->map(fn (Subcategory $subcategory): array => [
                        'id' => $subcategory->id,
                        'name' => $subcategory->name,
                    ])
                    ->all(),
            ])
            ->all();

        return Inertia::render('fixed-expenses/index', [
            'fixedExpenses' => $fixedExpenses,
            'expenseTypes' => $expenseTypes,
            'categories' => $categories,
            'month' => $month,
            'year' => $year,
            'months' => array_map(fn (Month $monthOption): array => [
                'value' => $monthOption->value,
                'label' => $monthOption->name,
            ], Month::cases()),
            'currencies' => array_map(fn (Currency $currency): array => [
                'value' => $currency->value,
                'label' => $currency->label(),
            ], Currency::cases()),
        ]);
    }

    /**
     * Store a fixed expense for the signed-in user.
     */
    public function store(StoreFixedExpenseRequest $request): RedirectResponse
    {
        $fixedExpense = new FixedExpense($request->safe()->only(['name', 'currency', 'amount']));
        $fixedExpense->expenseType()->associate($request->expenseType());
        $fixedExpense->subcategory()->associate($request->subcategory());
        $request->user()->fixedExpenses()->save($fixedExpense);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fixed expense created.')]);

        [$month, $year] = $this->period($request->integer('view_month'), $request->integer('view_year'));

        return to_route('fixed-expenses.index', ['month' => $month, 'year' => $year]);
    }

    /**
     * Mark a fixed expense paid for one month.
     */
    public function storePayment(StoreFixedExpensePaymentRequest $request, FixedExpense $fixedExpense): RedirectResponse
    {
        $fixedExpense->payments()->create($request->safe()->only(['year', 'month', 'amount']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fixed expense marked paid.')]);

        return to_route('fixed-expenses.index', [
            'month' => $request->integer('month'),
            'year' => $request->integer('year'),
        ]);
    }

    /**
     * Clear the paid mark for one month. The bill remains.
     */
    public function destroyPayment(DestroyFixedExpensePaymentRequest $request, FixedExpense $fixedExpense): RedirectResponse
    {
        $deleted = $fixedExpense->payments()
            ->where('year', $request->integer('year'))
            ->where('month', $request->integer('month'))
            ->delete();

        abort_unless($deleted === 1, 404);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Paid mark cleared.')]);

        return to_route('fixed-expenses.index', [
            'month' => $request->integer('month'),
            'year' => $request->integer('year'),
        ]);
    }

    /**
     * Keep a requested month and year inside the range the form allows.
     *
     * @return array{0: int, 1: int}
     */
    private function period(int $month, int $year): array
    {
        if (Month::tryFrom($month) === null) {
            $month = (int) now()->month;
        }

        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        return [$month, $year];
    }
}
