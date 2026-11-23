<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\Month;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Subcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    /**
     * Show the signed-in user's expenses.
     */
    public function index(Request $request): Response
    {
        $expenses = $request->user()
            ->expenses()
            ->with(['expenseType', 'subcategory.category'])
            ->latest()
            ->get()
            ->map(fn (Expense $expense): array => [
                'id' => $expense->id,
                'month' => $expense->month->name,
                'year' => $expense->year,
                'type' => $expense->expenseType->acronym,
                'concept' => $expense->concept,
                'category' => $expense->subcategory->category->name,
                'subcategory' => $expense->subcategory->name,
                'soles' => $expense->currency === Currency::Pen ? $expense->amount : null,
                'dollars' => $expense->currency === Currency::Usd ? $expense->amount : null,
            ])
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

        return Inertia::render('expenses/index', [
            'expenses' => $expenses,
            'expenseTypes' => $expenseTypes,
            'categories' => $categories,
            'months' => array_map(fn (Month $month): array => [
                'value' => $month->value,
                'label' => $month->name,
            ], Month::cases()),
            'currencies' => array_map(fn (Currency $currency): array => [
                'value' => $currency->value,
                'label' => $currency->label(),
            ], Currency::cases()),
        ]);
    }

    /**
     * Store an expense for the signed-in user.
     */
    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = new Expense($request->safe()->only([
            'month',
            'year',
            'concept',
            'currency',
            'amount',
        ]));

        $expense->expenseType()->associate($request->expenseType());
        $expense->subcategory()->associate($request->subcategory());

        $request->user()->expenses()->save($expense);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense created.')]);

        return to_route('expenses.index');
    }
}
