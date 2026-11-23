<?php

use App\Enums\Currency;
use App\Models\Category;
use App\Models\ExpenseType;
use App\Models\FixedExpense;
use App\Models\FixedExpensePayment;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{user: User, category: Category, subcategory: Subcategory, expenseType: ExpenseType}
 */
function fixedBillContext(): array
{
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['name' => 'Vivienda']);
    $subcategory = Subcategory::factory()->for($category)->create(['name' => 'Netflix']);
    $expenseType = ExpenseType::factory()->for($user)->create([
        'acronym' => 'IO',
        'description' => 'IO credit card',
    ]);

    return compact('user', 'category', 'subcategory', 'expenseType');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fixedBillPayload(Category $category, Subcategory $subcategory, ExpenseType $expenseType, array $overrides = []): array
{
    return array_merge([
        'name' => 'Netflix',
        'expense_type_id' => $expenseType->id,
        'category_id' => $category->id,
        'subcategory_id' => $subcategory->id,
        'currency' => 'pen',
        'amount' => '45.00',
        'view_month' => 1,
        'view_year' => 2026,
    ], $overrides);
}

test('guests are redirected to the login page', function () {
    $this->get(route('fixed-expenses.index'))
        ->assertRedirect(route('login'));
});

test('guests cannot create a fixed expense', function () {
    $this->post(route('fixed-expenses.store'), [
        'name' => 'Netflix',
    ])->assertRedirect(route('login'));

    expect(FixedExpense::query()->count())->toBe(0);
});

test('unverified users are redirected to the email verification prompt', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('fixed-expenses.index'))
        ->assertRedirect(route('verification.notice'));
});

test('the fixed expense page opens on the current month and year', function () {
    $this->travelTo('2026-03-15');
    ['user' => $user] = fixedBillContext();

    $this->actingAs($user)
        ->get(route('fixed-expenses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('fixed-expenses/index')
            ->where('month', 3)
            ->where('year', 2026),
        );
});

test('a user sees their bill in every month', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = fixedBillContext();

    $this->actingAs($user)
        ->post(route('fixed-expenses.store'), fixedBillPayload($category, $subcategory, $expenseType, [
            'name' => ' Netflix ',
            'amount' => '45,00',
        ]))
        ->assertRedirect(route('fixed-expenses.index', ['month' => 1, 'year' => 2026]));

    $bill = $user->fixedExpenses()->first();

    expect($bill)->not->toBeNull()
        ->and($bill->name)->toBe('Netflix')
        ->and($bill->amount)->toBe('45.00')
        ->and($bill->currency)->toBe(Currency::Pen);

    $someoneElse = User::factory()->create();
    $hiddenCategory = Category::factory()->for($someoneElse)->create();
    $hiddenSubcategory = Subcategory::factory()->for($hiddenCategory)->create();
    $hiddenType = ExpenseType::factory()->for($someoneElse)->create(['acronym' => 'EF']);
    FixedExpense::factory()->create([
        'user_id' => $someoneElse->id,
        'expense_type_id' => $hiddenType->id,
        'subcategory_id' => $hiddenSubcategory->id,
        'name' => 'Luz',
    ]);

    $this->actingAs($user)
        ->get(route('fixed-expenses.index', ['month' => 1, 'year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('fixedExpenses', 1)
            ->where('fixedExpenses.0.name', 'Netflix')
            ->where('fixedExpenses.0.type', 'IO')
            ->where('fixedExpenses.0.category', 'Vivienda')
            ->where('fixedExpenses.0.subcategory', 'Netflix')
            ->where('fixedExpenses.0.soles', '45.00')
            ->where('fixedExpenses.0.dollars', null)
            ->where('fixedExpenses.0.paidAmount', null)
            ->missing('fixedExpenses.1'),
        );

    $this->actingAs($user)
        ->get(route('fixed-expenses.index', ['month' => 2, 'year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('fixedExpenses', 1)
            ->where('fixedExpenses.0.name', 'Netflix'),
        );
});

test('a user can mark a bill paid for one month and clear that mark only', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = fixedBillContext();

    $this->actingAs($user)->post(route('fixed-expenses.store'), fixedBillPayload($category, $subcategory, $expenseType, [
        'amount' => '45.00',
    ]));

    $bill = $user->fixedExpenses()->first();

    $this->actingAs($user)
        ->post(route('fixed-expenses.payments.store', $bill), [
            'month' => 1,
            'year' => 2026,
            'amount' => '49.90',
        ])
        ->assertRedirect(route('fixed-expenses.index', ['month' => 1, 'year' => 2026]));

    $this->actingAs($user)
        ->post(route('fixed-expenses.payments.store', $bill), [
            'month' => 2,
            'year' => 2026,
            'amount' => '45.00',
        ]);

    $january = $bill->payments()->where('month', 1)->where('year', 2026)->first();

    expect($bill->fresh()->amount)->toBe('45.00')
        ->and($january->amount)->toBe('49.90');

    $this->actingAs($user)
        ->delete(route('fixed-expenses.payments.destroy', $bill), [
            'month' => 1,
            'year' => 2026,
        ])
        ->assertRedirect(route('fixed-expenses.index', ['month' => 1, 'year' => 2026]));

    expect($bill->payments()->where('month', 1)->where('year', 2026)->exists())->toBeFalse()
        ->and($bill->payments()->where('month', 2)->where('year', 2026)->exists())->toBeTrue()
        ->and($bill->fresh())->not->toBeNull();
});

test('a fixed expense is saved for the signed-in user when another user id is submitted', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = fixedBillContext();
    $someoneElse = User::factory()->create();

    $this->actingAs($user)
        ->post(route('fixed-expenses.store'), fixedBillPayload($category, $subcategory, $expenseType, [
            'user_id' => $someoneElse->id,
        ]))
        ->assertRedirect(route('fixed-expenses.index', ['month' => 1, 'year' => 2026]));

    expect($user->fixedExpenses()->count())->toBe(1)
        ->and($someoneElse->fixedExpenses()->count())->toBe(0);
});

test('a fixed expense name is required', function (string $name) {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = fixedBillContext();

    $this->actingAs($user)
        ->from(route('fixed-expenses.index', ['month' => 1, 'year' => 2026]))
        ->post(route('fixed-expenses.store'), fixedBillPayload($category, $subcategory, $expenseType, [
            'name' => $name,
        ]))
        ->assertRedirect(route('fixed-expenses.index', ['month' => 1, 'year' => 2026]))
        ->assertInvalid(['name' => 'The name field is required.'], 'createFixedExpense');

    expect($user->fixedExpenses()->count())->toBe(0);
})->with(['', '   ']);

test('a user cannot create a fixed expense with a name they already use', function (string $name) {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = fixedBillContext();
    $existing = FixedExpense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
        'name' => 'Netflix',
    ]);

    $this->actingAs($user)
        ->from(route('fixed-expenses.index', ['month' => 1, 'year' => 2026]))
        ->post(route('fixed-expenses.store'), fixedBillPayload($category, $subcategory, $expenseType, [
            'name' => $name,
        ]))
        ->assertRedirect(route('fixed-expenses.index', ['month' => 1, 'year' => 2026]))
        ->assertInvalid(['name' => 'You already have a fixed expense with this name.'], 'createFixedExpense');

    expect($user->fixedExpenses()->count())->toBe(1);
})->with(['Netflix', 'netflix', ' Netflix ']);

test('a usual amount is required', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = fixedBillContext();

    $payload = fixedBillPayload($category, $subcategory, $expenseType);
    unset($payload['amount']);

    $this->actingAs($user)
        ->from(route('fixed-expenses.index', ['month' => 1, 'year' => 2026]))
        ->post(route('fixed-expenses.store'), $payload)
        ->assertRedirect(route('fixed-expenses.index', ['month' => 1, 'year' => 2026]))
        ->assertInvalid(['amount' => 'The amount field is required.'], 'createFixedExpense');

    expect($user->fixedExpenses()->count())->toBe(0);
});

test('a subcategory from another category is rejected', function () {
    ['user' => $user, 'category' => $category, 'expenseType' => $expenseType] = fixedBillContext();
    $otherCategory = Category::factory()->for($user)->create(['name' => 'Otros']);
    $otherSubcategory = Subcategory::factory()->for($otherCategory)->create(['name' => 'Taxis']);

    $this->actingAs($user)
        ->from(route('fixed-expenses.index', ['month' => 1, 'year' => 2026]))
        ->post(route('fixed-expenses.store'), fixedBillPayload($category, $otherSubcategory, $expenseType))
        ->assertRedirect(route('fixed-expenses.index', ['month' => 1, 'year' => 2026]))
        ->assertInvalid(['subcategory_id' => 'The subcategory does not belong to the selected category.'], 'createFixedExpense');

    expect($user->fixedExpenses()->count())->toBe(0);
});

test('another user\'s expense type is a 404', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory] = fixedBillContext();
    $someoneElse = User::factory()->create();
    $hiddenType = ExpenseType::factory()->for($someoneElse)->create();

    $this->actingAs($user)
        ->post(route('fixed-expenses.store'), fixedBillPayload($category, $subcategory, $hiddenType))
        ->assertNotFound();

    expect(FixedExpense::query()->count())->toBe(0);
});

test('another user\'s subcategory is a 404', function () {
    ['user' => $user, 'category' => $category, 'expenseType' => $expenseType] = fixedBillContext();
    $someoneElse = User::factory()->create();
    $hiddenCategory = Category::factory()->for($someoneElse)->create();
    $hiddenSubcategory = Subcategory::factory()->for($hiddenCategory)->create();

    $this->actingAs($user)
        ->post(route('fixed-expenses.store'), fixedBillPayload($category, $hiddenSubcategory, $expenseType))
        ->assertNotFound();

    expect(FixedExpense::query()->count())->toBe(0);
});

test('the owner can pay their bill and another user cannot', function () {
    ['user' => $user, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = fixedBillContext();
    $bill = FixedExpense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
    ]);
    $someoneElse = User::factory()->create();

    expect(Gate::forUser($user)->inspect('pay', $bill)->allowed())->toBeTrue()
        ->and(Gate::forUser($someoneElse)->inspect('pay', $bill)->denied())->toBeTrue();

    $this->actingAs($someoneElse)
        ->post(route('fixed-expenses.payments.store', $bill), [
            'month' => 1,
            'year' => 2026,
            'amount' => '45.00',
        ])
        ->assertNotFound();

    expect(FixedExpensePayment::query()->count())->toBe(0);
});

test('a subcategory that has a fixed expense cannot be deleted', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = fixedBillContext();
    $bill = FixedExpense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
    ]);

    $this->actingAs($user)
        ->delete(route('categories.subcategories.destroy', [$category, $subcategory]))
        ->assertRedirect(route('categories.index', ['category' => $category->id]));

    expect($subcategory->fresh())->not->toBeNull();
});

test('an expense type that has a fixed expense cannot be deleted', function () {
    ['user' => $user, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = fixedBillContext();
    FixedExpense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
    ]);

    expect(fn () => $expenseType->delete())->toThrow(QueryException::class);
    expect($expenseType->fresh())->not->toBeNull();
});

test('deleting a user deletes their fixed expenses', function () {
    ['user' => $user, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = fixedBillContext();
    $bill = FixedExpense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
    ]);
    $bill->payments()->create(['month' => 1, 'year' => 2026, 'amount' => '45.00']);

    $user->delete();

    expect(FixedExpense::query()->whereKey($bill->id)->exists())->toBeFalse()
        ->and(FixedExpensePayment::query()->count())->toBe(0);
});
