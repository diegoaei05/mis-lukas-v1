<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseTypeController;
use App\Http\Controllers\FixedExpenseController;
use App\Http\Controllers\GeneralReportController;
use App\Http\Controllers\SubcategoryController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/{year}/{month}', [DashboardController::class, 'show'])
        ->name('dashboard.show')
        ->whereNumber('year')
        ->where('month', 'jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec');
    Route::get('dashboard/{year}/{month}/{category}', [DashboardController::class, 'subcategories'])
        ->name('dashboard.subcategories')
        ->whereNumber(['year', 'category'])
        ->where('month', 'jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec');

    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('expense-types', ExpenseTypeController::class)->only(['index', 'store']);
    Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('fixed-expenses', FixedExpenseController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['fixed-expenses' => 'fixedExpense']);
    Route::resource('general-report', GeneralReportController::class)->only(['index']);
    Route::post('fixed-expenses/{fixedExpense}/payments', [FixedExpenseController::class, 'storePayment'])
        ->name('fixed-expenses.payments.store');
    Route::delete('fixed-expenses/{fixedExpense}/payments', [FixedExpenseController::class, 'destroyPayment'])
        ->name('fixed-expenses.payments.destroy');
    Route::post('categories/{category}/subcategories', [SubcategoryController::class, 'store'])
        ->name('categories.subcategories.store');
    Route::put('categories/{category}/subcategories/{subcategory}', [SubcategoryController::class, 'update'])
        ->name('categories.subcategories.update')
        ->scopeBindings();
    Route::delete('categories/{category}/subcategories/{subcategory}', [SubcategoryController::class, 'destroy'])
        ->name('categories.subcategories.destroy')
        ->scopeBindings();
});

require __DIR__.'/settings.php';

Route::fallback(function (Request $request): RedirectResponse {
    if ($request->user() === null || $request->expectsJson() || $request->is('api/*')) {
        abort(404);
    }

    return redirect()->route('dashboard');
});
