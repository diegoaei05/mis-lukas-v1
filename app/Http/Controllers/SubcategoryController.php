<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubcategoryRequest;
use App\Http\Requests\UpdateSubcategoryRequest;
use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SubcategoryController extends Controller
{
    /**
     * Store a subcategory on one of the signed-in user's categories.
     */
    public function store(StoreSubcategoryRequest $request, Category $category): RedirectResponse
    {
        $category->subcategories()->create($request->safe()->only(['name', 'color']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subcategory created.')]);

        return to_route('categories.index', ['category' => $category->id]);
    }

    /**
     * Update a subcategory on one of the signed-in user's categories.
     */
    public function update(UpdateSubcategoryRequest $request, Category $category, Subcategory $subcategory): RedirectResponse
    {
        $subcategory->update($request->safe()->only(['name', 'color']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subcategory updated.')]);

        return to_route('categories.index', ['category' => $category->id]);
    }

    /**
     * Delete a subcategory from one of the signed-in user's categories.
     */
    public function destroy(Category $category, Subcategory $subcategory): RedirectResponse
    {
        Gate::authorize('delete', $subcategory);

        if ($subcategory->expenses()->exists() || $subcategory->fixedExpenses()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This subcategory has expenses and cannot be deleted.'),
            ]);

            return to_route('categories.index', ['category' => $category->id]);
        }

        $subcategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subcategory deleted.')]);

        return to_route('categories.index', ['category' => $category->id]);
    }
}
