<?php

use App\Models\Category;
use App\Models\ExpenseType;
use App\Models\FixedExpense;
use App\Models\Subcategory;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{user: User, category: Category, subcategory: Subcategory, expenseType: ExpenseType}
 */
function reportBillContext(): array
{
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['name' => 'Vivienda']);
    $subcategory = Subcategory::factory()->for($category)->create(['name' => 'Renta']);
    $expenseType = ExpenseType::factory()->for($user)->create([
        'acronym' => 'EF',
        'description' => 'Efectivo',
    ]);

    return compact('user', 'category', 'subcategory', 'expenseType');
}

test('guests are redirected to the login page', function () {
    $this->get(route('general-report.index'))
        ->assertRedirect(route('login'));
});

test('unverified users are redirected to the email verification prompt', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('general-report.index'))
        ->assertRedirect(route('verification.notice'));
});

test('the report opens on the current year', function () {
    $this->travelTo('2026-03-15');
    ['user' => $user] = reportBillContext();

    $this->actingAs($user)
        ->get(route('general-report.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('general-report/index')
            ->where('year', 2026)
            ->has('months', 12),
        );
});

test('a year outside the allowed range falls back to the current year', function () {
    $this->travelTo('2026-03-15');
    ['user' => $user] = reportBillContext();

    $this->actingAs($user)
        ->get(route('general-report.index', ['year' => 1999]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('year', 2026));
});

test('the report groups the user bills by category and totals each month', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = reportBillContext();
    $cursor = Subcategory::factory()->for($category)->create(['name' => 'Cursor']);
    $salud = Category::factory()->for($user)->create(['name' => 'Salud']);
    $clinic = Subcategory::factory()->for($salud)->create(['name' => 'San Pablo']);

    $renta = FixedExpense::factory()->for($user)->for($expenseType)->for($subcategory)->create([
        'name' => 'Renta',
        'currency' => 'pen',
        'amount' => '1600.00',
    ]);
    FixedExpense::factory()->for($user)->for($expenseType)->for($cursor)->create([
        'name' => 'Cursor',
        'currency' => 'usd',
        'amount' => '20.00',
    ]);
    $sanPablo = FixedExpense::factory()->for($user)->for($expenseType)->for($clinic)->create([
        'name' => 'San Pablo',
        'currency' => 'pen',
        'amount' => '183.35',
    ]);

    $renta->payments()->create([
        'year' => 2026,
        'month' => 2,
        'amount' => '1700.00',
    ]);
    $sanPablo->payments()->create([
        'year' => 2026,
        'month' => 1,
        'amount' => '183.35',
    ]);

    $someoneElse = User::factory()->create();
    $hiddenCategory = Category::factory()->for($someoneElse)->create(['name' => 'Otros']);
    $hiddenSubcategory = Subcategory::factory()->for($hiddenCategory)->create();
    $hiddenType = ExpenseType::factory()->for($someoneElse)->create();
    FixedExpense::factory()->for($someoneElse)->for($hiddenType)->for($hiddenSubcategory)->create([
        'name' => 'Secreto',
    ]);

    $this->actingAs($user)
        ->get(route('general-report.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups', 2)
            ->where('groups.0.category', 'Vivienda')
            ->where('groups.0.bills.0.name', 'Renta')
            ->where('groups.0.bills.0.months.0.soles', '1600.00')
            ->where('groups.0.bills.0.months.0.paid', false)
            ->where('groups.0.bills.0.months.0.changed', false)
            ->where('groups.0.bills.0.months.1.soles', '1700.00')
            ->where('groups.0.bills.0.months.1.paid', true)
            ->where('groups.0.bills.0.months.1.changed', true)
            ->where('groups.0.bills.1.name', 'Cursor')
            ->where('groups.0.bills.1.months.1.dollars', '20.00')
            ->where('groups.0.bills.1.months.1.paid', false)
            ->where('groups.0.bills.1.months.1.changed', false)
            ->where('groups.0.totals.0.soles', '1600.00')
            ->where('groups.0.totals.0.dollars', '20.00')
            ->where('groups.0.totals.1.soles', '1700.00')
            ->where('groups.0.totals.1.dollars', '20.00')
            ->where('groups.1.category', 'Salud')
            ->where('groups.1.bills.0.name', 'San Pablo')
            ->where('groups.1.bills.0.months.0.soles', '183.35')
            ->where('groups.1.bills.0.months.0.dollars', null)
            ->where('groups.1.bills.0.months.0.paid', true)
            ->where('groups.1.bills.0.months.0.changed', false)
            ->where('groups.1.bills.0.months.1.paid', false)
            ->where('groups.1.totals.0.soles', '183.35')
            ->where('groups.1.totals.0.dollars', null)
            ->where('totals.0.soles', '1783.35')
            ->where('totals.0.dollars', '20.00')
            ->where('totals.1.soles', '1883.35')
            ->where('totals.1.dollars', '20.00')
            ->missing('groups.2'),
        );
});
