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
     * Page sizes the expenses table accepts.
     *
     * @var list<int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

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

        $concept = $this->concept($request);
        $months = $this->months($request);
        $perPage = $this->perPage($request);

        $expensesQuery = $request->user()
            ->expenses()
            ->where('year', $year);

        if ($concept !== '') {
            $expensesQuery->whereRaw('LOWER(concept) LIKE ? ESCAPE ?', ['%'.mb_strtolower(addcslashes($concept, '%_\\')).'%', '\\']);
        }

        if ($months !== []) {
            $expensesQuery->whereIn('month', $months);
        }

        $paginator = $expensesQuery
            ->with(['expenseType', 'category', 'subcategory.category'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $expenses = $paginator->getCollection()
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
                'categoryColor' => ($expense->category ?? $expense->subcategory?->category)?->color?->value,
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
            'filters' => [
                'concept' => $concept,
                'months' => $months,
                'perPage' => $perPage,
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'expenseTypes' => $expenseTypes,
            'categories' => $categories,
            'months' => array_map(fn (Month $month): array => [
                'value' => $month->value,
                'label' => $month->name,
                'short' => $month->short(),
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

    /**
     * The concept text to match, trimmed to the column length.
     */
    private function concept(Request $request): string
    {
        return mb_substr(trim($request->string('concept')->toString()), 0, 255);
    }

    /**
     * The months to include. An empty list means every month.
     *
     * @return list<int>
     */
    private function months(Request $request): array
    {
        $months = $request->input('months', []);

        if (! is_array($months)) {
            return [];
        }

        $selected = [];

        foreach ($months as $month) {
            if (! is_numeric($month)) {
                continue;
            }

            $value = (int) $month;

            if ($value >= 1 && $value <= 12) {
                $selected[$value] = $value;
            }
        }

        $selected = array_values($selected);
        sort($selected);

        return $selected;
    }

    /**
     * Keep the page size to one of the offered choices.
     */
    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page');

        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            return 25;
        }

        return $perPage;
    }
}
