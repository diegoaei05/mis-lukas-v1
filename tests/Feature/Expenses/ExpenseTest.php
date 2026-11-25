<?php

use App\Enums\CategoryColor;
use App\Enums\Currency;
use App\Enums\Month;
use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{user: User, category: Category, subcategory: Subcategory, expenseType: ExpenseType}
 */
function expenseOwner(): array
{
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create([
        'name' => 'Comida',
        'color' => CategoryColor::Green,
    ]);
    $subcategory = Subcategory::factory()->for($category)->create(['name' => 'Restaurantes/bares']);
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
function expensePayload(Category $category, Subcategory $subcategory, ExpenseType $expenseType, array $overrides = []): array
{
    return array_merge([
        'month' => 1,
        'year' => 2026,
        'expense_type_id' => $expenseType->id,
        'category_id' => $category->id,
        'subcategory_id' => $subcategory->id,
        'concept' => 'Koanna - Rest',
        'currency' => 'pen',
        'amount' => '47.00',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function expenseUpdatePayload(Expense $expense, array $overrides = []): array
{
    return array_merge([
        'month' => $expense->month->value,
        'year' => $expense->year,
        'expense_type_id' => $expense->expense_type_id,
        'category_id' => $expense->category_id ?? '',
        'subcategory_id' => $expense->subcategory_id ?? '',
        'concept' => $expense->concept,
        'currency' => $expense->currency->value,
        'amount' => $expense->amount,
    ], $overrides);
}

test('guests are redirected to the login page', function () {
    $this->get(route('expenses.index'))
        ->assertRedirect(route('login'));
});

test('guests cannot create an expense', function () {
    $this->post(route('expenses.store'), [
        'concept' => 'Koanna - Rest',
    ])->assertRedirect(route('login'));

    expect(Expense::query()->count())->toBe(0);
});

test('unverified users are redirected to the email verification prompt', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('expenses.index'))
        ->assertRedirect(route('verification.notice'));
});

test('a user sees only their expenses with the type acronym and one currency column filled', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
        'month' => 1,
        'year' => 2026,
        'concept' => 'Koanna - Rest',
        'currency' => Currency::Pen,
        'amount' => 47,
        'created_at' => now()->subMinute(),
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
        'month' => 1,
        'year' => 2026,
        'concept' => 'Uber',
        'currency' => Currency::Usd,
        'amount' => 12.9,
        'created_at' => now(),
    ]);

    $someoneElse = User::factory()->create();
    $hiddenCategory = Category::factory()->for($someoneElse)->create(['name' => 'Otros']);
    $hiddenSubcategory = Subcategory::factory()->for($hiddenCategory)->create(['name' => 'Taxis']);
    $hiddenType = ExpenseType::factory()->for($someoneElse)->create(['acronym' => 'EF']);
    Expense::factory()->create([
        'user_id' => $someoneElse->id,
        'expense_type_id' => $hiddenType->id,
        'subcategory_id' => $hiddenSubcategory->id,
        'concept' => 'Hidden',
    ]);

    $this->actingAs($user)
        ->get(route('expenses.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/index')
            ->where('year', 2026)
            ->where('years', fn (mixed $years): bool => collect($years)->contains(2026))
            ->has('expenses', 2)
            ->where('expenses.0.month', 'Enero')
            ->where('expenses.0.monthValue', 1)
            ->where('expenses.0.year', 2026)
            ->where('expenses.0.type', 'IO')
            ->where('expenses.0.typeId', $expenseType->id)
            ->where('expenses.0.concept', 'Uber')
            ->where('expenses.0.currency', 'usd')
            ->where('expenses.0.amount', '12.90')
            ->where('expenses.0.category', 'Comida')
            ->where('expenses.0.categoryId', $category->id)
            ->where('expenses.0.categoryColor', 'green')
            ->where('expenses.0.subcategory', 'Restaurantes/bares')
            ->where('expenses.0.subcategoryId', $subcategory->id)
            ->where('expenses.0.soles', null)
            ->where('expenses.0.dollars', '12.90')
            ->where('expenses.1.concept', 'Koanna - Rest')
            ->where('expenses.1.soles', '47.00')
            ->where('expenses.1.dollars', null)
            ->missing('expenses.2'),
        );
});

test('a user can record a soles expense', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    $this->actingAs($user)
        ->post(route('expenses.store'), expensePayload($category, $subcategory, $expenseType, [
            'concept' => ' Koanna - Rest ',
            'amount' => '47,00',
        ]))
        ->assertRedirect(route('expenses.index', ['year' => 2026]));

    $expense = $user->expenses()->first();

    expect($expense)->not->toBeNull()
        ->and($expense->concept)->toBe('Koanna - Rest')
        ->and($expense->currency)->toBe(Currency::Pen)
        ->and($expense->amount)->toBe('47.00')
        ->and($expense->month->name)->toBe('Enero')
        ->and($expense->year)->toBe(2026)
        ->and($expense->expense_type_id)->toBe($expenseType->id)
        ->and($expense->category_id)->toBe($category->id)
        ->and($expense->subcategory_id)->toBe($subcategory->id);
});

test('an expense can be saved without a category or subcategory', function () {
    ['user' => $user, 'expenseType' => $expenseType] = expenseOwner();

    $this->actingAs($user)
        ->post(route('expenses.store'), [
            'month' => 1,
            'year' => 2026,
            'expense_type_id' => $expenseType->id,
            'category_id' => '',
            'subcategory_id' => '',
            'concept' => 'Koanna - Rest',
            'currency' => 'pen',
            'amount' => '47.00',
        ])
        ->assertRedirect(route('expenses.index', ['year' => 2026]));

    $expense = $user->expenses()->first();

    expect($expense)->not->toBeNull()
        ->and($expense->category_id)->toBeNull()
        ->and($expense->subcategory_id)->toBeNull();

    $this->actingAs($user)
        ->get(route('expenses.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('expenses.0.concept', 'Koanna - Rest')
            ->where('expenses.0.category', null)
            ->where('expenses.0.categoryId', null)
            ->where('expenses.0.categoryColor', null)
            ->where('expenses.0.subcategory', null)
            ->where('expenses.0.subcategoryId', null),
        );
});

test('an expense can be saved with a category and no subcategory', function () {
    ['user' => $user, 'category' => $category, 'expenseType' => $expenseType] = expenseOwner();

    $this->actingAs($user)
        ->post(route('expenses.store'), [
            'month' => 1,
            'year' => 2026,
            'expense_type_id' => $expenseType->id,
            'category_id' => $category->id,
            'concept' => 'Mercado',
            'currency' => 'pen',
            'amount' => '20.00',
        ])
        ->assertRedirect(route('expenses.index', ['year' => 2026]));

    $expense = $user->expenses()->first();

    expect($expense)->not->toBeNull()
        ->and($expense->category_id)->toBe($category->id)
        ->and($expense->subcategory_id)->toBeNull();

    $this->actingAs($user)
        ->get(route('expenses.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('expenses.0.category', 'Comida')
            ->where('expenses.0.categoryColor', 'green')
            ->where('expenses.0.subcategory', null),
        );
});

test('a user can record a dollars expense', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    $this->actingAs($user)
        ->post(route('expenses.store'), expensePayload($category, $subcategory, $expenseType, [
            'currency' => 'usd',
            'amount' => '12.90',
            'concept' => 'Uber',
        ]))
        ->assertRedirect(route('expenses.index', ['year' => 2026]));

    expect($user->expenses()->first())
        ->currency->toBe(Currency::Usd)
        ->amount->toBe('12.90');
});

test('two expenses can share a concept', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    $payload = expensePayload($category, $subcategory, $expenseType, ['concept' => 'Uber']);

    $this->actingAs($user)
        ->post(route('expenses.store'), $payload)
        ->assertRedirect(route('expenses.index', ['year' => 2026]));

    $this->actingAs($user)
        ->post(route('expenses.store'), $payload)
        ->assertRedirect(route('expenses.index', ['year' => 2026]));

    expect($user->expenses()->where('concept', 'Uber')->count())->toBe(2);
});

test('an expense is saved for the signed-in user when another user id is submitted', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();
    $someoneElse = User::factory()->create();

    $this->actingAs($user)
        ->post(route('expenses.store'), expensePayload($category, $subcategory, $expenseType, [
            'user_id' => $someoneElse->id,
            'expense_type_id' => $expenseType->id,
        ]))
        ->assertRedirect(route('expenses.index', ['year' => 2026]));

    expect($user->expenses()->count())->toBe(1)
        ->and($someoneElse->expenses()->count())->toBe(0);
});

test('a concept is required', function (string $concept) {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    $this->actingAs($user)
        ->from(route('expenses.index'))
        ->post(route('expenses.store'), expensePayload($category, $subcategory, $expenseType, [
            'concept' => $concept,
        ]))
        ->assertRedirect(route('expenses.index'))
        ->assertInvalid(['concept' => 'The concept field is required.'], 'createExpense');

    expect($user->expenses()->count())->toBe(0);
})->with(['', '   ']);

test('a concept cannot be longer than 255 characters', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    $this->actingAs($user)
        ->from(route('expenses.index'))
        ->post(route('expenses.store'), expensePayload($category, $subcategory, $expenseType, [
            'concept' => str_repeat('a', 256),
        ]))
        ->assertRedirect(route('expenses.index'))
        ->assertInvalid(['concept' => 'The concept field must not be greater than 255 characters.'], 'createExpense');

    expect($user->expenses()->count())->toBe(0);
});

test('an amount is required', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    $payload = expensePayload($category, $subcategory, $expenseType);
    unset($payload['amount']);

    $this->actingAs($user)
        ->from(route('expenses.index'))
        ->post(route('expenses.store'), $payload)
        ->assertRedirect(route('expenses.index'))
        ->assertInvalid(['amount' => 'The amount field is required.'], 'createExpense');

    expect($user->expenses()->count())->toBe(0);
});

test('an amount must be greater than zero', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    $this->actingAs($user)
        ->from(route('expenses.index'))
        ->post(route('expenses.store'), expensePayload($category, $subcategory, $expenseType, [
            'amount' => '0',
        ]))
        ->assertRedirect(route('expenses.index'))
        ->assertInvalid(['amount' => 'The amount field must be greater than 0.'], 'createExpense');

    expect($user->expenses()->count())->toBe(0);
});

test('an amount cannot have more than two decimal places', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    $this->actingAs($user)
        ->from(route('expenses.index'))
        ->post(route('expenses.store'), expensePayload($category, $subcategory, $expenseType, [
            'amount' => '10.999',
        ]))
        ->assertRedirect(route('expenses.index'))
        ->assertInvalid(['amount' => 'The amount field must have 0-2 decimal places.'], 'createExpense');

    expect($user->expenses()->count())->toBe(0);
});

test('an expense needs one currency', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    $payload = expensePayload($category, $subcategory, $expenseType);
    unset($payload['currency']);

    $this->actingAs($user)
        ->from(route('expenses.index'))
        ->post(route('expenses.store'), $payload)
        ->assertRedirect(route('expenses.index'))
        ->assertInvalid(['currency' => 'The currency field is required.'], 'createExpense');

    expect($user->expenses()->count())->toBe(0);
});

test('an expense cannot be saved in both currencies', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    $this->actingAs($user)
        ->from(route('expenses.index'))
        ->post(route('expenses.store'), expensePayload($category, $subcategory, $expenseType, [
            'currency' => ['pen', 'usd'],
        ]))
        ->assertRedirect(route('expenses.index'))
        ->assertInvalid(['currency' => 'The currency field must be a string.'], 'createExpense');

    expect($user->expenses()->count())->toBe(0);
});

test('a month must be one of the twelve months', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    $this->actingAs($user)
        ->from(route('expenses.index'))
        ->post(route('expenses.store'), expensePayload($category, $subcategory, $expenseType, [
            'month' => 13,
        ]))
        ->assertRedirect(route('expenses.index'))
        ->assertInvalid(['month' => 'The selected month is invalid.'], 'createExpense');

    expect($user->expenses()->count())->toBe(0);
});

test('a year must fall between 2000 and 2100', function (int $year) {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    $this->actingAs($user)
        ->from(route('expenses.index'))
        ->post(route('expenses.store'), expensePayload($category, $subcategory, $expenseType, [
            'year' => $year,
        ]))
        ->assertRedirect(route('expenses.index'))
        ->assertInvalid(['year' => 'The year field must be between 2000 and 2100.'], 'createExpense');

    expect($user->expenses()->count())->toBe(0);
})->with([1999, 2101]);

test('a subcategory from another category is rejected', function () {
    ['user' => $user, 'category' => $category, 'expenseType' => $expenseType] = expenseOwner();
    $otherCategory = Category::factory()->for($user)->create(['name' => 'Otros']);
    $otherSubcategory = Subcategory::factory()->for($otherCategory)->create(['name' => 'Taxis']);

    $this->actingAs($user)
        ->from(route('expenses.index'))
        ->post(route('expenses.store'), expensePayload($category, $otherSubcategory, $expenseType))
        ->assertRedirect(route('expenses.index'))
        ->assertInvalid(['subcategory_id' => 'The subcategory does not belong to the selected category.'], 'createExpense');

    expect($user->expenses()->count())->toBe(0);
});

test('another user\'s expense type sends the user to the dashboard', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory] = expenseOwner();
    $someoneElse = User::factory()->create();
    $hiddenType = ExpenseType::factory()->for($someoneElse)->create(['acronym' => 'EF']);

    $this->actingAs($user)
        ->post(route('expenses.store'), expensePayload($category, $subcategory, $hiddenType))
        ->assertRedirect(route('dashboard'));

    expect($user->expenses()->count())->toBe(0)
        ->and(Expense::query()->count())->toBe(0);
});

test('another user\'s subcategory sends the user to the dashboard', function () {
    ['user' => $user, 'category' => $category, 'expenseType' => $expenseType] = expenseOwner();
    $someoneElse = User::factory()->create();
    $hiddenCategory = Category::factory()->for($someoneElse)->create();
    $hiddenSubcategory = Subcategory::factory()->for($hiddenCategory)->create();

    $this->actingAs($user)
        ->post(route('expenses.store'), expensePayload($category, $hiddenSubcategory, $expenseType))
        ->assertRedirect(route('dashboard'));

    expect(Expense::query()->count())->toBe(0);
});

test('another user\'s category sends the user to the dashboard', function () {
    ['user' => $user, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();
    $someoneElse = User::factory()->create();
    $hiddenCategory = Category::factory()->for($someoneElse)->create();

    $this->actingAs($user)
        ->post(route('expenses.store'), expensePayload($hiddenCategory, $subcategory, $expenseType))
        ->assertRedirect(route('dashboard'));

    expect(Expense::query()->count())->toBe(0);
});

test('a subcategory that has expenses cannot be deleted', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
    ]);

    $this->actingAs($user)
        ->delete(route('categories.subcategories.destroy', [$category, $subcategory]))
        ->assertRedirect(route('categories.index', ['category' => $category->id]));

    expect($subcategory->fresh())->not->toBeNull();
});

test('an expense type that has expenses cannot be deleted', function () {
    ['user' => $user, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
    ]);

    expect(fn () => $expenseType->delete())->toThrow(QueryException::class);
    expect($expenseType->fresh())->not->toBeNull();
});

test('deleting a user deletes their expenses', function () {
    ['user' => $user, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
    ]);

    $user->delete();

    expect(Expense::query()->whereKey($expense->id)->exists())->toBeFalse();
});

test('the expense list shows the selected year', function () {
    ['user' => $user, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
        'year' => 2026,
        'concept' => 'This year',
    ]);

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
        'year' => 2025,
        'concept' => 'Last year',
    ]);

    $this->actingAs($user)
        ->get(route('expenses.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('year', 2026)
            ->has('expenses', 1)
            ->where('expenses.0.concept', 'This year')
            ->where('years', fn (mixed $years): bool => collect($years)->contains(2025) && collect($years)->contains(2026)),
        );

    $this->actingAs($user)
        ->get(route('expenses.index', ['year' => 1999]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('year', (int) now()->year),
        );
});

test('guests cannot update an expense', function () {
    $expense = Expense::factory()->create();

    $this->put(route('expenses.update', $expense), [
        'category_id' => $expense->category_id,
    ])->assertRedirect(route('login'));
});

test('a user can file a category and subcategory on an existing expense', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'category_id' => null,
        'subcategory_id' => null,
        'concept' => 'Testing',
        'amount' => 49,
    ]);

    $this->actingAs($user)
        ->from(route('expenses.index'))
        ->put(route('expenses.update', $expense), expenseUpdatePayload($expense, [
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
        ]))
        ->assertRedirect(route('expenses.index', ['year' => $expense->year]));

    $expense->refresh();

    expect($expense->concept)->toBe('Testing')
        ->and($expense->amount)->toBe('49.00')
        ->and($expense->category_id)->toBe($category->id)
        ->and($expense->subcategory_id)->toBe($subcategory->id);
});

test('a user can file a category without a subcategory', function () {
    ['user' => $user, 'category' => $category, 'expenseType' => $expenseType] = expenseOwner();

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'category_id' => null,
        'subcategory_id' => null,
    ]);

    $this->actingAs($user)
        ->put(route('expenses.update', $expense), expenseUpdatePayload($expense, [
            'category_id' => $category->id,
            'subcategory_id' => '',
        ]))
        ->assertRedirect(route('expenses.index', ['year' => $expense->year]));

    $expense->refresh();

    expect($expense->category_id)->toBe($category->id)
        ->and($expense->subcategory_id)->toBeNull();
});

test('a user can clear the category and subcategory on an expense', function () {
    ['user' => $user, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();

    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
    ]);

    $this->actingAs($user)
        ->put(route('expenses.update', $expense), expenseUpdatePayload($expense, [
            'category_id' => '',
            'subcategory_id' => '',
        ]))
        ->assertRedirect(route('expenses.index', ['year' => $expense->year]));

    $expense->refresh();

    expect($expense->category_id)->toBeNull()
        ->and($expense->subcategory_id)->toBeNull();
});

test('a subcategory from another category cannot be filed onto an expense', function () {
    ['user' => $user, 'category' => $category, 'expenseType' => $expenseType] = expenseOwner();
    $otherCategory = Category::factory()->for($user)->create(['name' => 'Otros']);
    $otherSubcategory = Subcategory::factory()->for($otherCategory)->create(['name' => 'Taxis']);
    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'category_id' => null,
        'subcategory_id' => null,
    ]);

    $this->actingAs($user)
        ->from(route('expenses.index'))
        ->put(route('expenses.update', $expense), expenseUpdatePayload($expense, [
            'category_id' => $category->id,
            'subcategory_id' => $otherSubcategory->id,
        ]))
        ->assertRedirect(route('expenses.index'))
        ->assertInvalid(['subcategory_id' => 'The subcategory does not belong to the selected category.'], 'updateExpense-'.$expense->id);

    $expense->refresh();

    expect($expense->category_id)->toBeNull()
        ->and($expense->subcategory_id)->toBeNull();
});

test('another user cannot update an expense', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();
    $someoneElse = User::factory()->create();
    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
    ]);

    $this->actingAs($someoneElse)
        ->put(route('expenses.update', $expense), expenseUpdatePayload($expense, [
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
        ]))
        ->assertRedirect(route('dashboard'));

    expect($expense->refresh()->subcategory_id)->toBe($subcategory->id)
        ->and(Gate::forUser($user)->inspect('update', $expense)->allowed())->toBeTrue()
        ->and(Gate::forUser($someoneElse)->inspect('update', $expense)->denied())->toBeTrue();
});

test('another user\'s category cannot be filed onto an expense', function () {
    ['user' => $user, 'expenseType' => $expenseType] = expenseOwner();
    $someoneElse = User::factory()->create();
    $hiddenCategory = Category::factory()->for($someoneElse)->create();
    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'category_id' => null,
        'subcategory_id' => null,
    ]);

    $this->actingAs($user)
        ->put(route('expenses.update', $expense), expenseUpdatePayload($expense, [
            'category_id' => $hiddenCategory->id,
            'subcategory_id' => '',
        ]))
        ->assertRedirect(route('dashboard'));

    expect($expense->refresh()->category_id)->toBeNull();
});

test('a user can update every field on an expense', function () {
    ['user' => $user, 'category' => $category, 'subcategory' => $subcategory, 'expenseType' => $expenseType] = expenseOwner();
    $cash = ExpenseType::factory()->for($user)->create([
        'acronym' => 'EF',
        'description' => 'Efectivo',
    ]);
    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'category_id' => null,
        'subcategory_id' => null,
        'month' => 11,
        'year' => 2026,
        'concept' => 'Testing',
        'currency' => Currency::Pen,
        'amount' => 49,
    ]);

    $this->actingAs($user)
        ->put(route('expenses.update', $expense), expenseUpdatePayload($expense, [
            'month' => 2,
            'year' => 2027,
            'expense_type_id' => $cash->id,
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'concept' => ' Mercado ',
            'currency' => 'usd',
            'amount' => '15,50',
        ]))
        ->assertRedirect(route('expenses.index', ['year' => 2027]));

    $expense->refresh();

    expect($expense->month)->toBe(Month::Febrero)
        ->and($expense->year)->toBe(2027)
        ->and($expense->expense_type_id)->toBe($cash->id)
        ->and($expense->category_id)->toBe($category->id)
        ->and($expense->subcategory_id)->toBe($subcategory->id)
        ->and($expense->concept)->toBe('Mercado')
        ->and($expense->currency)->toBe(Currency::Usd)
        ->and($expense->amount)->toBe('15.50');
});

test('an expense update requires a concept', function () {
    ['user' => $user, 'expenseType' => $expenseType] = expenseOwner();
    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'category_id' => null,
        'subcategory_id' => null,
        'concept' => 'Testing',
    ]);

    $this->actingAs($user)
        ->from(route('expenses.index'))
        ->put(route('expenses.update', $expense), expenseUpdatePayload($expense, [
            'concept' => '   ',
        ]))
        ->assertRedirect(route('expenses.index'))
        ->assertInvalid(['concept' => 'The concept field is required.'], 'updateExpense-'.$expense->id);

    expect($expense->refresh()->concept)->toBe('Testing');
});

test('guests cannot delete an expense', function () {
    $expense = Expense::factory()->create();

    $this->delete(route('expenses.destroy', $expense))
        ->assertRedirect(route('login'));

    expect($expense->fresh())->not->toBeNull();
});

test('a user can delete an expense', function () {
    ['user' => $user, 'expenseType' => $expenseType] = expenseOwner();
    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'category_id' => null,
        'subcategory_id' => null,
        'year' => 2026,
        'concept' => 'Testing',
    ]);

    $this->actingAs($user)
        ->delete(route('expenses.destroy', $expense))
        ->assertRedirect(route('expenses.index', ['year' => 2026]));

    expect(Expense::query()->whereKey($expense->id)->exists())->toBeFalse()
        ->and(Gate::forUser($user)->inspect('delete', $expense)->allowed())->toBeTrue();
});

test('another user cannot delete an expense', function () {
    ['user' => $user, 'expenseType' => $expenseType] = expenseOwner();
    $someoneElse = User::factory()->create();
    $expense = Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'category_id' => null,
        'subcategory_id' => null,
    ]);

    $this->actingAs($someoneElse)
        ->delete(route('expenses.destroy', $expense))
        ->assertRedirect(route('dashboard'));

    expect(Expense::query()->whereKey($expense->id)->exists())->toBeTrue()
        ->and(Gate::forUser($someoneElse)->inspect('delete', $expense)->denied())->toBeTrue();
});
