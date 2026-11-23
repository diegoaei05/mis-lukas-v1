<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $requestedCategoryId = $request->integer('category');
        $selectedCategoryId = collect($categories)->contains('id', $requestedCategoryId)
            ? $requestedCategoryId
            : null;

        return Inertia::render('categories/index', [
            'categories' => $categories,
            'selectedCategoryId' => $selectedCategoryId,
        ]);
    }

    /**
     * Store a category for the signed-in user.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = $request->user()->categories()->create($request->safe()->only(['name']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('categories.index', ['category' => $category->id]);
    }
}
