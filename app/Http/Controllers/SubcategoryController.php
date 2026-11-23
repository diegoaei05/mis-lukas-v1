<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubcategoryRequest;
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
        $category->subcategories()->create($request->safe()->only(['name']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subcategory created.')]);

        return to_route('categories.index', ['category' => $category->id]);
    }

    /**
     * Delete a subcategory from one of the signed-in user's categories.
     */
    public function destroy(Category $category, Subcategory $subcategory): RedirectResponse
    {
        Gate::authorize('delete', $subcategory);

        $subcategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subcategory deleted.')]);

        return to_route('categories.index', ['category' => $category->id]);
    }
}
