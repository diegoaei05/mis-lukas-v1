<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseTypeRequest;
use App\Models\ExpenseType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseTypeController extends Controller
{
    /**
     * Show the signed-in user's expense types.
     */
    public function index(Request $request): Response
    {
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

        return Inertia::render('expense-types/index', [
            'expenseTypes' => $expenseTypes,
        ]);
    }

    /**
     * Store an expense type for the signed-in user.
     */
    public function store(StoreExpenseTypeRequest $request): RedirectResponse
    {
        $request->user()->expenseTypes()->create(
            $request->safe()->only(['acronym', 'description']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense type created.')]);

        return to_route('expense-types.index');
    }
}
