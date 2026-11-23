<?php

use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\FixedExpense;
use App\Models\Subcategory;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->travelTo('2026-03-15');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('year', 2026)
            ->has('fixedMonths', 12)
            ->has('variableMonths', 12)
            ->where('fixedMonths.0.label', 'Enero')
            ->where('fixedMonths.0.soles', '0.00')
            ->where('variableMonths.11.label', 'Diciembre'),
        );
});

test('a year outside the allowed range falls back to the current year', function () {
    $this->travelTo('2026-03-15');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', ['year' => 1999]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('year', 2026));
});

test('the dashboard totals the signed-in user expenses by month', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $subcategory = Subcategory::factory()->for($category)->create();
    $expenseType = ExpenseType::factory()->for($user)->create();

    Expense::factory()->for($user)->for($expenseType)->for($subcategory)->create([
        'month' => 1,
        'year' => 2026,
        'currency' => 'pen',
        'amount' => '10.00',
    ]);
    Expense::factory()->for($user)->for($expenseType)->for($subcategory)->create([
        'month' => 1,
        'year' => 2026,
        'currency' => 'pen',
        'amount' => '5.50',
    ]);
    Expense::factory()->for($user)->for($expenseType)->for($subcategory)->create([
        'month' => 2,
        'year' => 2026,
        'currency' => 'usd',
        'amount' => '8.00',
    ]);
    Expense::factory()->for($user)->for($expenseType)->for($subcategory)->create([
        'month' => 1,
        'year' => 2025,
        'currency' => 'pen',
        'amount' => '99.00',
    ]);

    $someoneElse = User::factory()->create();
    $hiddenCategory = Category::factory()->for($someoneElse)->create();
    $hiddenSubcategory = Subcategory::factory()->for($hiddenCategory)->create();
    $hiddenType = ExpenseType::factory()->for($someoneElse)->create();
    Expense::factory()->for($someoneElse)->for($hiddenType)->for($hiddenSubcategory)->create([
        'month' => 1,
        'year' => 2026,
        'currency' => 'pen',
        'amount' => '40.00',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('variableMonths.0.soles', '15.50')
            ->where('variableMonths.0.dollars', '0.00')
            ->where('variableMonths.1.soles', '0.00')
            ->where('variableMonths.1.dollars', '8.00')
            ->where('variableMonths.2.soles', '0.00')
            ->where('variableMonths.2.dollars', '0.00'),
        );
});

test('the dashboard totals fixed bills for every month and uses a paid amount', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $subcategory = Subcategory::factory()->for($category)->create();
    $expenseType = ExpenseType::factory()->for($user)->create();

    $renta = FixedExpense::factory()->for($user)->for($expenseType)->for($subcategory)->create([
        'name' => 'Renta',
        'currency' => 'pen',
        'amount' => '1600.00',
    ]);
    FixedExpense::factory()->for($user)->for($expenseType)->for($subcategory)->create([
        'name' => 'Cursor',
        'currency' => 'usd',
        'amount' => '20.00',
    ]);
    $renta->payments()->create([
        'year' => 2026,
        'month' => 2,
        'amount' => '1700.00',
    ]);

    $someoneElse = User::factory()->create();
    $hiddenCategory = Category::factory()->for($someoneElse)->create();
    $hiddenSubcategory = Subcategory::factory()->for($hiddenCategory)->create();
    $hiddenType = ExpenseType::factory()->for($someoneElse)->create();
    FixedExpense::factory()->for($someoneElse)->for($hiddenType)->for($hiddenSubcategory)->create([
        'name' => 'Secreto',
        'currency' => 'pen',
        'amount' => '500.00',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('fixedMonths.0.soles', '1600.00')
            ->where('fixedMonths.0.dollars', '20.00')
            ->where('fixedMonths.1.soles', '1700.00')
            ->where('fixedMonths.1.dollars', '20.00')
            ->where('fixedMonths.2.dollars', '20.00'),
        );
});
