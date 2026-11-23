<?php

use App\Models\Category;
use App\Models\Subcategory;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('categories.index'))
        ->assertRedirect(route('login'));
});

test('guests cannot create a category', function () {
    $this->post(route('categories.store'), [
        'name' => 'Food',
    ])->assertRedirect(route('login'));

    expect(Category::query()->count())->toBe(0);
});

test('unverified users are redirected to the email verification prompt', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('categories.index'))
        ->assertRedirect(route('verification.notice'));
});

test('a user sees only their categories and subcategories', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['name' => 'Food']);
    Subcategory::factory()->for($category)->create(['name' => 'Super/Market']);

    $someoneElse = User::factory()->create();
    $hiddenCategory = Category::factory()->for($someoneElse)->create(['name' => 'Transport']);
    Subcategory::factory()->for($hiddenCategory)->create(['name' => 'Taxi']);

    $this->actingAs($user)
        ->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('categories/index')
            ->has('categories', 1)
            ->where('categories.0.name', 'Food')
            ->has('categories.0.subcategories', 1)
            ->where('categories.0.subcategories.0.name', 'Super/Market')
            ->missing('categories.1'),
        );
});

test('a user can create a category', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('categories.store'), [
            'name' => 'Food',
        ]);

    $category = $user->categories()->first();

    $response->assertRedirect(route('categories.index', ['category' => $category->id]));

    expect($category)->not->toBeNull()
        ->and($category->name)->toBe('Food');
});

test('a category is saved for the signed-in user when another user id is submitted', function () {
    $user = User::factory()->create();
    $someoneElse = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('categories.store'), [
            'name' => 'Food',
            'user_id' => $someoneElse->id,
        ]);

    $category = $user->categories()->where('name', 'Food')->first();

    $response->assertRedirect(route('categories.index', ['category' => $category->id]));

    expect($category)->not->toBeNull()
        ->and($someoneElse->categories()->count())->toBe(0);
});

test('a category name is required', function (string $name) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.store'), [
            'name' => $name,
        ])
        ->assertRedirect(route('categories.index'))
        ->assertInvalid(['name' => 'The name field is required.'], 'createCategory');

    expect($user->categories()->count())->toBe(0);
})->with(['', '   ']);

test('a category name cannot be longer than 255 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.store'), [
            'name' => str_repeat('a', 256),
        ])
        ->assertRedirect(route('categories.index'))
        ->assertInvalid(['name' => 'The name field must not be greater than 255 characters.'], 'createCategory');

    expect($user->categories()->count())->toBe(0);
});

test('a user cannot create a category with a name they already use', function (string $name) {
    $user = User::factory()->create();
    Category::factory()->for($user)->create(['name' => 'Food']);

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.store'), [
            'name' => $name,
        ])
        ->assertRedirect(route('categories.index'))
        ->assertInvalid(['name' => 'You already have a category with this name.'], 'createCategory');

    expect($user->categories()->count())->toBe(1);
})->with([
    'Food',
    'food',
    ' Food ',
]);

test('a user can create a subcategory on their category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['name' => 'Food']);

    $this->actingAs($user)
        ->post(route('categories.subcategories.store', $category), [
            'name' => 'Super/Market',
        ])
        ->assertRedirect(route('categories.index', ['category' => $category->id]));

    expect($category->subcategories()->where('name', 'Super/Market')->exists())->toBeTrue();
});

test('a subcategory name is required', function (string $name) {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.subcategories.store', $category), [
            'name' => $name,
        ])
        ->assertRedirect(route('categories.index'))
        ->assertInvalid(['name' => 'The name field is required.'], 'subcategory-'.$category->id);

    expect($category->subcategories()->count())->toBe(0);
})->with(['', '   ']);

test('a subcategory name cannot be longer than 255 characters', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.subcategories.store', $category), [
            'name' => str_repeat('a', 256),
        ])
        ->assertRedirect(route('categories.index'))
        ->assertInvalid(['name' => 'The name field must not be greater than 255 characters.'], 'subcategory-'.$category->id);

    expect($category->subcategories()->count())->toBe(0);
});

test('a user cannot add a duplicate subcategory to the same category', function (string $name) {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    Subcategory::factory()->for($category)->create(['name' => 'Super/Market']);

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.subcategories.store', $category), [
            'name' => $name,
        ])
        ->assertRedirect(route('categories.index'))
        ->assertInvalid(['name' => 'This category already has a subcategory with this name.'], 'subcategory-'.$category->id);

    expect($category->subcategories()->count())->toBe(1);
})->with([
    'Super/Market',
    'super/market',
    ' Super/Market ',
]);

test('a user can reuse a subcategory name on a different category', function () {
    $user = User::factory()->create();
    $food = Category::factory()->for($user)->create(['name' => 'Food']);
    $transport = Category::factory()->for($user)->create(['name' => 'Transport']);
    Subcategory::factory()->for($food)->create(['name' => 'Other']);

    $this->actingAs($user)
        ->post(route('categories.subcategories.store', $transport), [
            'name' => 'Other',
        ])
        ->assertRedirect(route('categories.index', ['category' => $transport->id]));

    expect($transport->subcategories()->where('name', 'Other')->exists())->toBeTrue();
});

test('only the owner may create a subcategory on a category', function () {
    $owner = User::factory()->create();
    $someoneElse = User::factory()->create();
    $category = Category::factory()->for($owner)->create();

    expect($owner->can('create', [Subcategory::class, $category]))->toBeTrue()
        ->and($someoneElse->can('create', [Subcategory::class, $category]))->toBeFalse();
});

test('a user cannot add a subcategory to someone else\'s category', function () {
    $owner = User::factory()->create();
    $someoneElse = User::factory()->create();
    $category = Category::factory()->for($owner)->create();

    $this->actingAs($someoneElse)
        ->post(route('categories.subcategories.store', $category), [
            'name' => 'Rent',
        ])
        ->assertNotFound();

    expect($category->subcategories()->count())->toBe(0);
});

test('a missing category cannot receive a subcategory', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('categories.subcategories.store', 9999), [
            'name' => 'Rent',
        ])
        ->assertNotFound();

    expect(Subcategory::query()->count())->toBe(0);
});

test('only the owner may delete a subcategory', function () {
    $owner = User::factory()->create();
    $someoneElse = User::factory()->create();
    $subcategory = Subcategory::factory()->for(Category::factory()->for($owner))->create();

    expect($owner->can('delete', $subcategory))->toBeTrue()
        ->and($someoneElse->can('delete', $subcategory))->toBeFalse();
});

test('a user can delete a subcategory from their category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $subcategory = Subcategory::factory()->for($category)->create();

    $this->actingAs($user)
        ->delete(route('categories.subcategories.destroy', [$category, $subcategory]))
        ->assertRedirect(route('categories.index', ['category' => $category->id]));

    $this->assertModelMissing($subcategory);
});

test('a user cannot delete someone else\'s subcategory', function () {
    $owner = User::factory()->create();
    $someoneElse = User::factory()->create();
    $category = Category::factory()->for($owner)->create();
    $subcategory = Subcategory::factory()->for($category)->create();

    $this->actingAs($someoneElse)
        ->delete(route('categories.subcategories.destroy', [$category, $subcategory]))
        ->assertNotFound();

    $this->assertModelExists($subcategory);
});

test('a subcategory cannot be deleted through a different category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $otherCategory = Category::factory()->for($user)->create();
    $subcategory = Subcategory::factory()->for($category)->create();

    $this->assertModelExists($subcategory);
});

test('guests cannot delete a subcategory', function () {
    $subcategory = Subcategory::factory()->create();

    $this->delete(route('categories.subcategories.destroy', [$subcategory->category, $subcategory]))
        ->assertRedirect(route('login'));

    $this->assertModelExists($subcategory);
});
