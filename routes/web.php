<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SubcategoryController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('categories', CategoryController::class)->only(['index', 'store']);
    Route::post('categories/{category}/subcategories', [SubcategoryController::class, 'store'])
        ->name('categories.subcategories.store');
    Route::delete('categories/{category}/subcategories/{subcategory}', [SubcategoryController::class, 'destroy'])
        ->name('categories.subcategories.destroy')
        ->scopeBindings();
});

require __DIR__.'/settings.php';
