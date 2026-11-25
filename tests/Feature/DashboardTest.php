<?php

use App\Enums\CategoryColor;
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

test('guests are redirected from a month page', function () {
    $this->get(route('dashboard.show', ['year' => 2026, 'month' => 'jan']))
        ->assertRedirect(route('login'));
});

test('an unknown month sends the user to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.show', ['year' => 2026, 'month' => 'xyz']))
        ->assertRedirect(route('dashboard'));
});

test('a numbered month address sends the user to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard/2026/1/pen')
        ->assertRedirect(route('dashboard'));
});

test('a year outside the allowed range on a month page sends the user to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.show', ['year' => 1999, 'month' => 'jan']))
        ->assertRedirect(route('dashboard'));
});

test('a month page groups fixed bills by category and uses a paid amount', function () {
    $user = User::factory()->create();
    $vivienda = Category::factory()->for($user)->create([
        'name' => 'Vivienda',
        'color' => CategoryColor::Blue,
    ]);
    $renta = Subcategory::factory()->for($vivienda)->create(['name' => 'Renta']);
    $internet = Subcategory::factory()->for($vivienda)->create(['name' => 'Internet']);
    $otros = Category::factory()->for($user)->create([
        'name' => 'Otros',
        'color' => CategoryColor::Purple,
    ]);
    $cursor = Subcategory::factory()->for($otros)->create(['name' => 'Cursor']);
    $expenseType = ExpenseType::factory()->for($user)->create();

    $bill = FixedExpense::factory()->for($user)->for($expenseType)->for($renta)->create([
        'currency' => 'pen',
        'amount' => '1600.00',
    ]);
    FixedExpense::factory()->for($user)->for($expenseType)->for($internet)->create([
        'currency' => 'pen',
        'amount' => '100.00',
    ]);
    FixedExpense::factory()->for($user)->for($expenseType)->for($cursor)->create([
        'currency' => 'usd',
        'amount' => '20.00',
    ]);
    $bill->payments()->create([
        'year' => 2026,
        'month' => 2,
        'amount' => '1700.00',
    ]);

    $someoneElse = User::factory()->create();
    $hiddenCategory = Category::factory()->for($someoneElse)->create(['name' => 'Secreto']);
    $hiddenSubcategory = Subcategory::factory()->for($hiddenCategory)->create();
    $hiddenType = ExpenseType::factory()->for($someoneElse)->create();
    FixedExpense::factory()->for($someoneElse)->for($hiddenType)->for($hiddenSubcategory)->create([
        'currency' => 'pen',
        'amount' => '500.00',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.show', ['year' => 2026, 'month' => 'feb']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/month')
            ->where('monthLabel', 'Febrero')
            ->has('soles.fixed', 1)
            ->where('soles.fixed.0.id', $vivienda->id)
            ->where('soles.fixed.0.category', 'Vivienda')
            ->where('soles.fixed.0.color', 'blue')
            ->where('soles.fixed.0.amount', '1800.00')
            ->has('soles.variable', 0)
            ->has('dollars.fixed', 1)
            ->where('dollars.fixed.0.category', 'Otros')
            ->where('dollars.fixed.0.color', 'purple')
            ->where('dollars.fixed.0.amount', '20.00')
            ->has('dollars.variable', 0),
        );

    $this->actingAs($user)
        ->get(route('dashboard.show', ['year' => 2026, 'month' => 'jan']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('soles.fixed.0.amount', '1700.00'),
        );
});

test('a month page groups variable expenses by category', function () {
    $user = User::factory()->create();
    $comida = Category::factory()->for($user)->create([
        'name' => 'Comida',
        'color' => CategoryColor::Green,
    ]);
    $restaurants = Subcategory::factory()->for($comida)->create();
    $expenseType = ExpenseType::factory()->for($user)->create();

    Expense::factory()->for($user)->for($expenseType)->for($restaurants)->create([
        'month' => 1,
        'year' => 2026,
        'currency' => 'pen',
        'amount' => '10.00',
    ]);
    Expense::factory()->for($user)->for($expenseType)->for($restaurants)->create([
        'month' => 1,
        'year' => 2026,
        'currency' => 'pen',
        'amount' => '5.50',
    ]);
    Expense::factory()->for($user)->for($expenseType)->create([
        'category_id' => null,
        'subcategory_id' => null,
        'month' => 1,
        'year' => 2026,
        'currency' => 'pen',
        'amount' => '3.00',
    ]);
    Expense::factory()->for($user)->for($expenseType)->for($restaurants)->create([
        'month' => 1,
        'year' => 2026,
        'currency' => 'usd',
        'amount' => '8.00',
    ]);
    Expense::factory()->for($user)->for($expenseType)->for($restaurants)->create([
        'month' => 2,
        'year' => 2026,
        'currency' => 'pen',
        'amount' => '40.00',
    ]);

    $someoneElse = User::factory()->create();
    $hiddenCategory = Category::factory()->for($someoneElse)->create();
    $hiddenSubcategory = Subcategory::factory()->for($hiddenCategory)->create();
    $hiddenType = ExpenseType::factory()->for($someoneElse)->create();
    Expense::factory()->for($someoneElse)->for($hiddenType)->for($hiddenSubcategory)->create([
        'month' => 1,
        'year' => 2026,
        'currency' => 'pen',
        'amount' => '99.00',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.show', ['year' => 2026, 'month' => 'jan']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('soles.fixed', 0)
            ->has('soles.variable', 2)
            ->where('soles.variable.0.id', $comida->id)
            ->where('soles.variable.0.category', 'Comida')
            ->where('soles.variable.0.color', 'green')
            ->where('soles.variable.0.amount', '15.50')
            ->where('soles.variable.1.id', null)
            ->where('soles.variable.1.category', null)
            ->where('soles.variable.1.color', null)
            ->where('soles.variable.1.amount', '3.00')
            ->has('dollars.fixed', 0)
            ->has('dollars.variable', 1)
            ->where('dollars.variable.0.category', 'Comida')
            ->where('dollars.variable.0.amount', '8.00'),
        );
});

test('guests are redirected from a subcategory page', function () {
    $category = Category::factory()->create();

    $this->get(route('dashboard.subcategories', [
        'year' => 2026,
        'month' => 'jan',
        'category' => $category,
    ]))->assertRedirect(route('login'));
});

test('another user cannot open a category ring', function () {
    $owner = User::factory()->create();
    $category = Category::factory()->for($owner)->create();
    $visitor = User::factory()->create();

    $this->actingAs($visitor)
        ->get(route('dashboard.subcategories', [
            'year' => 2026,
            'month' => 'jan',
            'category' => $category,
        ]))
        ->assertRedirect(route('dashboard'));
});

test('a group in the category address sends the user to the dashboard', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    $this->actingAs($user)
        ->get("/dashboard/2026/jan/fixed/{$category->id}")
        ->assertRedirect(route('dashboard'));
});

test('a category ring groups fixed bills by subcategory and uses a paid amount', function () {
    $user = User::factory()->create();
    $vivienda = Category::factory()->for($user)->create(['name' => 'Vivienda']);
    $renta = Subcategory::factory()->for($vivienda)->create(['name' => 'Renta']);
    $internet = Subcategory::factory()->for($vivienda)->create(['name' => 'Internet']);
    $otros = Category::factory()->for($user)->create(['name' => 'Otros']);
    $cursor = Subcategory::factory()->for($otros)->create(['name' => 'Cursor']);
    $expenseType = ExpenseType::factory()->for($user)->create();

    $bill = FixedExpense::factory()->for($user)->for($expenseType)->for($renta)->create([
        'currency' => 'pen',
        'amount' => '1600.00',
    ]);
    FixedExpense::factory()->for($user)->for($expenseType)->for($internet)->create([
        'currency' => 'pen',
        'amount' => '100.00',
    ]);
    FixedExpense::factory()->for($user)->for($expenseType)->for($cursor)->create([
        'currency' => 'usd',
        'amount' => '20.00',
    ]);
    $bill->payments()->create([
        'year' => 2026,
        'month' => 2,
        'amount' => '1700.00',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.subcategories', [
            'year' => 2026,
            'month' => 'feb',
            'category' => $vivienda,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/subcategories')
            ->where('category', 'Vivienda')
            ->has('soles.fixed', 2)
            ->where('soles.fixed.0.category', 'Renta')
            ->where('soles.fixed.0.amount', '1700.00')
            ->where('soles.fixed.1.category', 'Internet')
            ->where('soles.fixed.1.amount', '100.00')
            ->has('soles.variable', 0)
            ->has('dollars.fixed', 0)
            ->has('dollars.variable', 0),
        );
});

test('a category ring groups variable expenses by subcategory', function () {
    $user = User::factory()->create();
    Category::factory()->for(User::factory())->create(['name' => 'Ajena']);
    $comida = Category::factory()->for($user)->create(['name' => 'Comida']);
    $restaurants = Subcategory::factory()->for($comida)->create(['name' => 'Restaurantes']);
    $menus = Subcategory::factory()->for($comida)->create(['name' => 'Menus']);
    $otros = Category::factory()->for($user)->create(['name' => 'Otros']);
    $misc = Subcategory::factory()->for($otros)->create(['name' => 'Misc']);
    $expenseType = ExpenseType::factory()->for($user)->create();

    Expense::factory()->for($user)->for($expenseType)->for($restaurants)->create([
        'month' => 1,
        'year' => 2026,
        'currency' => 'pen',
        'amount' => '10.00',
    ]);
    Expense::factory()->for($user)->for($expenseType)->for($restaurants)->create([
        'month' => 1,
        'year' => 2026,
        'currency' => 'pen',
        'amount' => '5.50',
    ]);
    Expense::factory()->for($user)->for($expenseType)->for($menus)->create([
        'month' => 1,
        'year' => 2026,
        'currency' => 'pen',
        'amount' => '4.00',
    ]);
    Expense::factory()->for($user)->for($expenseType)->for($comida)->create([
        'subcategory_id' => null,
        'month' => 1,
        'year' => 2026,
        'currency' => 'pen',
        'amount' => '2.00',
    ]);
    Expense::factory()->for($user)->for($expenseType)->for($misc)->create([
        'month' => 1,
        'year' => 2026,
        'currency' => 'pen',
        'amount' => '40.00',
    ]);
    Expense::factory()->for($user)->for($expenseType)->for($restaurants)->create([
        'month' => 1,
        'year' => 2026,
        'currency' => 'usd',
        'amount' => '8.00',
    ]);
    Expense::factory()->for($user)->for($expenseType)->for($restaurants)->create([
        'month' => 2,
        'year' => 2026,
        'currency' => 'pen',
        'amount' => '9.00',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.subcategories', [
            'year' => 2026,
            'month' => 'jan',
            'category' => $comida,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('categoryId', $comida->id)
            ->has('categories', 2)
            ->where('categories.0.name', 'Comida')
            ->where('categories.1.name', 'Otros')
            ->has('soles.fixed', 0)
            ->has('soles.variable', 3)
            ->where('soles.variable.0.category', 'Restaurantes')
            ->where('soles.variable.0.amount', '15.50')
            ->where('soles.variable.1.category', 'Menus')
            ->where('soles.variable.1.amount', '4.00')
            ->where('soles.variable.2.category', null)
            ->where('soles.variable.2.amount', '2.00')
            ->has('dollars.fixed', 0)
            ->has('dollars.variable', 1)
            ->where('dollars.variable.0.category', 'Restaurantes')
            ->where('dollars.variable.0.amount', '8.00'),
        );
});
