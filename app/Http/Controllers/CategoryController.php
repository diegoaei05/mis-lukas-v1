<?php

namespace App\Http\Controllers;

use App\Enums\CategoryColor;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Show the signed-in user's categories and their subcategories.
     */
    public function index(Request $request): Response
    {
        $categories = $request->user()
            ->categories()
            ->with(['subcategories' => function ($query) {
                $query->orderBy('name')->select(['id', 'category_id', 'name', 'color']);
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color?->value,
                'subcategories' => $category->subcategories
                    ->map(fn (Subcategory $subcategory): array => [
                        'id' => $subcategory->id,
                        'name' => $subcategory->name,
                        'color' => $subcategory->color?->value,
                    ])
                    ->all(),
            ])
            ->all();

        $requestedCategoryId = $request->integer('category');
        $selectedCategoryId = collect($categories)->contains('id', $requestedCategoryId)
            ? $requestedCategoryId
            : null;

        return Inertia::render('categories/index', [
            'categories' => $categories,
            'selectedCategoryId' => $selectedCategoryId,
            'colors' => array_map(fn (CategoryColor $color): array => [
                'value' => $color->value,
                'label' => $color->label(),
            ], CategoryColor::cases()),
        ]);
    }

    /**
     * Store a category for the signed-in user.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = $request->user()->categories()->create($request->safe()->only(['name', 'color']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('categories.index', ['category' => $category->id]);
    }

    /**
     * Update one of the signed-in user's categories.
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->safe()->only(['name', 'color']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return to_route('categories.index', ['category' => $category->id]);
    }

    /**
     * Delete one of the signed-in user's categories.
     */
    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $inUse = $category->expenses()->exists()
            || $category->subcategories()->whereHas('fixedExpenses')->exists();

        if ($inUse) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This category has expenses and cannot be deleted.'),
            ]);

            return to_route('categories.index', ['category' => $category->id]);
        }

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return to_route('categories.index');
    }
}
