<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Enums\Month;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Subcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    /**
     * Show the signed-in user's expenses.
     */
    public function index(Request $request): Response
    {
        $year = $this->year($request->integer('year'));

        $recordedYears = $request->user()
            ->expenses()
            ->distinct()
            ->orderBy('year')
            ->pluck('year')
            ->map(fn (mixed $recordedYear): int => (int) $recordedYear);

        $years = collect([(int) now()->year, $year - 1, $year, $year + 1])
            ->merge($recordedYears)
            ->filter(fn (int $option): bool => $option >= 2000 && $option <= 2100)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $expenses = $request->user()
            ->expenses()
            ->where('year', $year)
            ->with(['expenseType', 'category', 'subcategory.category'])
            ->latest()
            ->get()
            ->map(fn (Expense $expense): array => [
                'id' => $expense->id,
                'month' => $expense->month->name,
                'monthValue' => $expense->month->value,
                'year' => $expense->year,
                'type' => $expense->expenseType->acronym,
                'typeId' => $expense->expense_type_id,
                'concept' => $expense->concept,
                'category' => $expense->category?->name ?? $expense->subcategory?->category?->name,
                'categoryId' => $expense->category_id ?? $expense->subcategory?->category_id,
                'subcategory' => $expense->subcategory?->name,
                'subcategoryId' => $expense->subcategory_id,
                'currency' => $expense->currency->value,
                'amount' => $expense->amount,
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
            'year' => $year,
            'years' => $years,
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

        if ($request->subcategory() instanceof Subcategory) {
            $expense->subcategory()->associate($request->subcategory());
            $expense->category()->associate($request->subcategory()->category);
        } elseif ($request->category() instanceof Category) {
            $expense->category()->associate($request->category());
        }

        $request->user()->expenses()->save($expense);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense created.')]);

        return to_route('expenses.index', ['year' => $expense->year]);
    }

    /**
     * Update an existing expense for the signed-in user.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $expense->fill($request->safe()->only([
            'month',
            'year',
            'concept',
            'currency',
            'amount',
        ]));
        $expense->expenseType()->associate($request->expenseType());
        $expense->category()->dissociate();
        $expense->subcategory()->dissociate();

        if ($request->subcategory() instanceof Subcategory) {
            $expense->subcategory()->associate($request->subcategory());
            $expense->category()->associate($request->subcategory()->category);
        } elseif ($request->category() instanceof Category) {
            $expense->category()->associate($request->category());
        }

        $expense->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense updated.')]);

        return to_route('expenses.index', ['year' => $expense->year]);
    }

    /**
     * Delete an expense for the signed-in user.
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('delete', $expense);

        $year = $expense->year;

        $expense->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense deleted.')]);

        return to_route('expenses.index', ['year' => $year]);
    }

    /**
     * Keep a requested year inside the range an expense allows.
     */
    private function year(int $year): int
    {
        if ($year < 2000 || $year > 2100) {
            return (int) now()->year;
        }

        return $year;
    }
}
