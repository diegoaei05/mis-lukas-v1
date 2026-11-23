<?php

use App\Models\ExpenseType;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('expense-types.index'))
        ->assertRedirect(route('login'));
});

test('guests cannot create an expense type', function () {
    $this->post(route('expense-types.store'), [
        'acronym' => 'IO',
        'description' => 'IO credit card',
    ])->assertRedirect(route('login'));

    expect(ExpenseType::query()->count())->toBe(0);
});

test('unverified users are redirected to the email verification prompt', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('expense-types.index'))
        ->assertRedirect(route('verification.notice'));
});

test('a user sees only their expense types ordered by acronym', function () {
    $user = User::factory()->create();
    ExpenseType::factory()->for($user)->create([
        'acronym' => 'IO',
        'description' => 'IO credit card',
    ]);
    ExpenseType::factory()->for($user)->create([
        'acronym' => 'EF',
        'description' => 'Efectivo',
    ]);

    $someoneElse = User::factory()->create();
    ExpenseType::factory()->for($someoneElse)->create([
        'acronym' => 'TC',
        'description' => 'Tarjeta de crédito',
    ]);

    $this->actingAs($user)
        ->get(route('expense-types.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('expense-types/index')
            ->has('expenseTypes', 2)
            ->where('expenseTypes.0.acronym', 'EF')
            ->where('expenseTypes.0.description', 'Efectivo')
            ->where('expenseTypes.1.acronym', 'IO')
            ->where('expenseTypes.1.description', 'IO credit card')
            ->missing('expenseTypes.2'),
        );
});

test('a user can create an expense type', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('expense-types.store'), [
            'acronym' => ' IO ',
            'description' => ' IO credit card ',
        ])
        ->assertRedirect(route('expense-types.index'));

    $expenseType = $user->expenseTypes()->first();

    expect($expenseType)->not->toBeNull()
        ->and($expenseType->acronym)->toBe('IO')
        ->and($expenseType->description)->toBe('IO credit card');
});

test('an expense type is saved for the signed-in user when another user id is submitted', function () {
    $user = User::factory()->create();
    $someoneElse = User::factory()->create();

    $this->actingAs($user)
        ->post(route('expense-types.store'), [
            'acronym' => 'IO',
            'description' => 'IO credit card',
            'user_id' => $someoneElse->id,
        ])
        ->assertRedirect(route('expense-types.index'));

    expect($user->expenseTypes()->where('acronym', 'IO')->exists())->toBeTrue()
        ->and($someoneElse->expenseTypes()->count())->toBe(0);
});

test('an acronym is required', function (string $acronym) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('expense-types.index'))
        ->post(route('expense-types.store'), [
            'acronym' => $acronym,
            'description' => 'IO credit card',
        ])
        ->assertRedirect(route('expense-types.index'))
        ->assertInvalid(['acronym' => 'The acronym field is required.'], 'createExpenseType');

    expect($user->expenseTypes()->count())->toBe(0);
})->with(['', '   ']);

test('a description is required', function (string $description) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('expense-types.index'))
        ->post(route('expense-types.store'), [
            'acronym' => 'IO',
            'description' => $description,
        ])
        ->assertRedirect(route('expense-types.index'))
        ->assertInvalid(['description' => 'The description field is required.'], 'createExpenseType');

    expect($user->expenseTypes()->count())->toBe(0);
})->with(['', '   ']);

test('an acronym cannot be longer than 20 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('expense-types.index'))
        ->post(route('expense-types.store'), [
            'acronym' => str_repeat('a', 21),
            'description' => 'IO credit card',
        ])
        ->assertRedirect(route('expense-types.index'))
        ->assertInvalid(['acronym' => 'The acronym field must not be greater than 20 characters.'], 'createExpenseType');

    expect($user->expenseTypes()->count())->toBe(0);
});

test('a description cannot be longer than 255 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('expense-types.index'))
        ->post(route('expense-types.store'), [
            'acronym' => 'IO',
            'description' => str_repeat('a', 256),
        ])
        ->assertRedirect(route('expense-types.index'))
        ->assertInvalid(['description' => 'The description field must not be greater than 255 characters.'], 'createExpenseType');

    expect($user->expenseTypes()->count())->toBe(0);
});

test('a user cannot create an expense type with an acronym they already use', function (string $acronym) {
    $user = User::factory()->create();
    ExpenseType::factory()->for($user)->create([
        'acronym' => 'IO',
        'description' => 'IO credit card',
    ]);

    $this->actingAs($user)
        ->from(route('expense-types.index'))
        ->post(route('expense-types.store'), [
            'acronym' => $acronym,
            'description' => 'Another card',
        ])
        ->assertRedirect(route('expense-types.index'))
        ->assertInvalid(['acronym' => 'You already have an expense type with this acronym.'], 'createExpenseType');

    expect($user->expenseTypes()->count())->toBe(1);
})->with([
    'IO',
    'io',
    ' IO ',
]);

test('another user can create the same acronym', function () {
    $user = User::factory()->create();
    $someoneElse = User::factory()->create();
    ExpenseType::factory()->for($someoneElse)->create([
        'acronym' => 'IO',
        'description' => 'IO credit card',
    ]);

    $this->actingAs($user)
        ->post(route('expense-types.store'), [
            'acronym' => 'IO',
            'description' => 'IO credit card',
        ])
        ->assertRedirect(route('expense-types.index'));

    expect($user->expenseTypes()->where('acronym', 'IO')->exists())->toBeTrue()
        ->and($someoneElse->expenseTypes()->count())->toBe(1);
});

test('deleting a user deletes their expense types', function () {
    $user = User::factory()->create();
    $expenseType = ExpenseType::factory()->for($user)->create();

    $user->delete();

    expect(ExpenseType::query()->whereKey($expenseType->id)->exists())->toBeFalse();
});
