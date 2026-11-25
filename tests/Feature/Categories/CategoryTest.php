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
    $category = Category::factory()->for($user)->create([
        'name' => 'Food',
        'color' => CategoryColor::Green,
    ]);
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
            ->where('categories.0.color', 'green')
            ->has('colors', 5)
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
            'color' => 'green',
        ]);

    $category = $user->categories()->first();

    $response->assertRedirect(route('categories.index', ['category' => $category->id]));

    expect($category)->not->toBeNull()
        ->and($category->name)->toBe('Food')
        ->and($category->color)->toBe(CategoryColor::Green);
});

test('a category is saved for the signed-in user when another user id is submitted', function () {
    $user = User::factory()->create();
    $someoneElse = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('categories.store'), [
            'name' => 'Food',
            'color' => 'green',
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
            'color' => 'green',
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
            'color' => 'green',
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
            'color' => 'green',
        ])
        ->assertRedirect(route('categories.index'))
        ->assertInvalid(['name' => 'You already have a category with this name.'], 'createCategory');

    expect($user->categories()->count())->toBe(1);
})->with([
    'Food',
    'food',
    ' Food ',
]);

test('a category color is required', function (mixed $color) {
    $user = User::factory()->create();

    $payload = ['name' => 'Food'];

    if ($color !== null) {
        $payload['color'] = $color;
    }

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.store'), $payload)
        ->assertRedirect(route('categories.index'))
        ->assertInvalid(['color' => 'The color field is required.'], 'createCategory');

    expect($user->categories()->count())->toBe(0);
})->with([
    'missing' => null,
    'blank' => '',
]);

test('a category color must be one of the pill colors', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.store'), [
            'name' => 'Food',
            'color' => 'pink',
        ])
        ->assertRedirect(route('categories.index'))
        ->assertInvalid(['color' => 'The selected color is invalid.'], 'createCategory');

    expect($user->categories()->count())->toBe(0);
});

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

test('only the owner may update a subcategory', function () {
    $owner = User::factory()->create();
    $someoneElse = User::factory()->create();
    $subcategory = Subcategory::factory()->for(Category::factory()->for($owner))->create();

    expect($owner->can('update', $subcategory))->toBeTrue()
        ->and($someoneElse->can('update', $subcategory))->toBeFalse();
});

test('a user can update a subcategory name', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $subcategory = Subcategory::factory()->for($category)->create(['name' => 'Super/Market']);

    $this->actingAs($user)
        ->put(route('categories.subcategories.update', [$category, $subcategory]), [
            'name' => 'Groceries',
        ])
        ->assertRedirect(route('categories.index', ['category' => $category->id]));

    expect($subcategory->fresh()->name)->toBe('Groceries');
});

test('a user can keep a subcategory name', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $subcategory = Subcategory::factory()->for($category)->create(['name' => 'Super/Market']);

    $this->actingAs($user)
        ->put(route('categories.subcategories.update', [$category, $subcategory]), [
            'name' => 'Super/Market',
        ])
        ->assertRedirect(route('categories.index', ['category' => $category->id]));

    expect($subcategory->fresh()->name)->toBe('Super/Market');
});

test('a subcategory name is required when updating', function (string $name) {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $subcategory = Subcategory::factory()->for($category)->create(['name' => 'Super/Market']);

    $this->actingAs($user)
        ->from(route('categories.index', ['category' => $category->id]))
        ->put(route('categories.subcategories.update', [$category, $subcategory]), [
            'name' => $name,
        ])
        ->assertRedirect(route('categories.index', ['category' => $category->id]))
        ->assertInvalid(['name' => 'The name field is required.'], 'updateSubcategory-'.$subcategory->id);

    expect($subcategory->fresh()->name)->toBe('Super/Market');
})->with(['', '   ']);

test('a subcategory name cannot be longer than 255 characters when updating', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $subcategory = Subcategory::factory()->for($category)->create(['name' => 'Super/Market']);

    $this->actingAs($user)
        ->from(route('categories.index', ['category' => $category->id]))
        ->put(route('categories.subcategories.update', [$category, $subcategory]), [
            'name' => str_repeat('a', 256),
        ])
        ->assertRedirect(route('categories.index', ['category' => $category->id]))
        ->assertInvalid(['name' => 'The name field must not be greater than 255 characters.'], 'updateSubcategory-'.$subcategory->id);

    expect($subcategory->fresh()->name)->toBe('Super/Market');
});

test('a user cannot rename a subcategory to a name that category already has', function (string $name) {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    Subcategory::factory()->for($category)->create(['name' => 'Super/Market']);
    $subcategory = Subcategory::factory()->for($category)->create(['name' => 'Rappi']);

    $this->actingAs($user)
        ->from(route('categories.index', ['category' => $category->id]))
        ->put(route('categories.subcategories.update', [$category, $subcategory]), [
            'name' => $name,
        ])
        ->assertRedirect(route('categories.index', ['category' => $category->id]))
        ->assertInvalid(['name' => 'This category already has a subcategory with this name.'], 'updateSubcategory-'.$subcategory->id);

    expect($subcategory->fresh()->name)->toBe('Rappi');
})->with([
    'Super/Market',
    'super/market',
    ' Super/Market ',
]);

test('a user can rename a subcategory to a name used on a different category', function () {
    $user = User::factory()->create();
    $food = Category::factory()->for($user)->create(['name' => 'Food']);
    $transport = Category::factory()->for($user)->create(['name' => 'Transport']);
    Subcategory::factory()->for($food)->create(['name' => 'Other']);
    $subcategory = Subcategory::factory()->for($transport)->create(['name' => 'Taxi']);

    $this->actingAs($user)
        ->put(route('categories.subcategories.update', [$transport, $subcategory]), [
            'name' => 'Other',
        ])
        ->assertRedirect(route('categories.index', ['category' => $transport->id]));

    expect($subcategory->fresh()->name)->toBe('Other');
});

test('a subcategory stays on its category when another category id is submitted', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $otherCategory = Category::factory()->for($user)->create();
    $subcategory = Subcategory::factory()->for($category)->create(['name' => 'Rappi']);

    $this->actingAs($user)
        ->put(route('categories.subcategories.update', [$category, $subcategory]), [
            'name' => 'Menus',
            'category_id' => $otherCategory->id,
        ])
        ->assertRedirect(route('categories.index', ['category' => $category->id]));

    $subcategory->refresh();

    expect($subcategory->name)->toBe('Menus')
        ->and($subcategory->category_id)->toBe($category->id);
});

test('guests cannot update a subcategory', function () {
    $subcategory = Subcategory::factory()->create(['name' => 'Rappi']);

    $this->put(route('categories.subcategories.update', [$subcategory->category, $subcategory]), [
        'name' => 'Menus',
    ])->assertRedirect(route('login'));

    expect($subcategory->fresh()->name)->toBe('Rappi');
});

test('a user cannot update someone else\'s subcategory', function () {
    $owner = User::factory()->create();
    $someoneElse = User::factory()->create();
    $category = Category::factory()->for($owner)->create();
    $subcategory = Subcategory::factory()->for($category)->create(['name' => 'Rappi']);

    $this->actingAs($someoneElse)
        ->put(route('categories.subcategories.update', [$category, $subcategory]), [
            'name' => 'Menus',
        ])
        ->assertNotFound();

    expect($subcategory->fresh()->name)->toBe('Rappi');
});

test('a subcategory cannot be updated through a different category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $otherCategory = Category::factory()->for($user)->create();
    $subcategory = Subcategory::factory()->for($category)->create(['name' => 'Rappi']);

    $this->actingAs($user)
        ->put(route('categories.subcategories.update', [$otherCategory, $subcategory]), [
            'name' => 'Menus',
        ])
        ->assertNotFound();

    expect($subcategory->fresh()->name)->toBe('Rappi');
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

test('only the owner may update a category', function () {
    $owner = User::factory()->create();
    $someoneElse = User::factory()->create();
    $category = Category::factory()->for($owner)->create();

    expect($owner->can('update', $category))->toBeTrue()
        ->and($someoneElse->can('update', $category))->toBeFalse();
});

test('a user can update a category name and color', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create([
        'name' => 'Food',
        'color' => CategoryColor::Green,
    ]);

    $this->actingAs($user)
        ->put(route('categories.update', $category), [
            'name' => 'Groceries',
            'color' => 'red',
        ])
        ->assertRedirect(route('categories.index', ['category' => $category->id]));

    $category->refresh();

    expect($category->name)->toBe('Groceries')
        ->and($category->color)->toBe(CategoryColor::Red);

    $this->actingAs($user)
        ->get(route('categories.index', ['category' => $category->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('categories.0.name', 'Groceries')
            ->where('categories.0.color', 'red'),
        );
});

test('a user can keep a category name when changing its color', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create([
        'name' => 'Food',
        'color' => CategoryColor::Green,
    ]);

    $this->actingAs($user)
        ->put(route('categories.update', $category), [
            'name' => 'Food',
            'color' => 'blue',
        ])
        ->assertRedirect(route('categories.index', ['category' => $category->id]));

    $category->refresh();

    expect($category->name)->toBe('Food')
        ->and($category->color)->toBe(CategoryColor::Blue);
});

test('a category stays with the signed-in user when another user id is submitted on update', function () {
    $user = User::factory()->create();
    $someoneElse = User::factory()->create();
    $category = Category::factory()->for($user)->create(['name' => 'Food']);

    $this->actingAs($user)
        ->put(route('categories.update', $category), [
            'name' => 'Groceries',
            'color' => 'gold',
            'user_id' => $someoneElse->id,
        ])
        ->assertRedirect(route('categories.index', ['category' => $category->id]));

    $category->refresh();

    expect($category->user_id)->toBe($user->id)
        ->and($someoneElse->categories()->count())->toBe(0);
});

test('a user can rename a category to a name another user already has', function () {
    $user = User::factory()->create();
    $someoneElse = User::factory()->create();
    Category::factory()->for($someoneElse)->create(['name' => 'Food']);
    $category = Category::factory()->for($user)->create(['name' => 'Transport']);

    $this->actingAs($user)
        ->put(route('categories.update', $category), [
            'name' => 'Food',
            'color' => 'green',
        ])
        ->assertRedirect(route('categories.index', ['category' => $category->id]));

    expect($category->fresh()->name)->toBe('Food');
});

test('a category name is required when updating', function (string $name) {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create([
        'name' => 'Food',
        'color' => CategoryColor::Green,
    ]);

    $this->actingAs($user)
        ->from(route('categories.index', ['category' => $category->id]))
        ->put(route('categories.update', $category), [
            'name' => $name,
            'color' => 'green',
        ])
        ->assertRedirect(route('categories.index', ['category' => $category->id]))
        ->assertInvalid(['name' => 'The name field is required.'], 'updateCategory-'.$category->id);

    expect($category->fresh()->name)->toBe('Food');
})->with(['', '   ']);

test('a category name cannot be longer than 255 characters when updating', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['name' => 'Food']);

    $this->actingAs($user)
        ->from(route('categories.index', ['category' => $category->id]))
        ->put(route('categories.update', $category), [
            'name' => str_repeat('a', 256),
            'color' => 'green',
        ])
        ->assertRedirect(route('categories.index', ['category' => $category->id]))
        ->assertInvalid(['name' => 'The name field must not be greater than 255 characters.'], 'updateCategory-'.$category->id);

    expect($category->fresh()->name)->toBe('Food');
});

test('a user cannot rename a category to a name they already use', function (string $name) {
    $user = User::factory()->create();
    Category::factory()->for($user)->create(['name' => 'Food']);
    $category = Category::factory()->for($user)->create([
        'name' => 'Transport',
        'color' => CategoryColor::Blue,
    ]);

    $this->actingAs($user)
        ->from(route('categories.index', ['category' => $category->id]))
        ->put(route('categories.update', $category), [
            'name' => $name,
            'color' => 'blue',
        ])
        ->assertRedirect(route('categories.index', ['category' => $category->id]))
        ->assertInvalid(['name' => 'You already have a category with this name.'], 'updateCategory-'.$category->id);

    expect($category->fresh()->name)->toBe('Transport');
})->with([
    'Food',
    'food',
    ' Food ',
]);

test('a category color is required when updating', function (mixed $color) {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create([
        'name' => 'Food',
        'color' => null,
    ]);

    $payload = ['name' => 'Food'];

    if ($color !== null) {
        $payload['color'] = $color;
    }

    $this->actingAs($user)
        ->from(route('categories.index', ['category' => $category->id]))
        ->put(route('categories.update', $category), $payload)
        ->assertRedirect(route('categories.index', ['category' => $category->id]))
        ->assertInvalid(['color' => 'The color field is required.'], 'updateCategory-'.$category->id);

    expect($category->fresh()->color)->toBeNull();
})->with([
    'missing' => null,
    'blank' => '',
]);

test('a category color must be one of the pill colors when updating', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create([
        'name' => 'Food',
        'color' => CategoryColor::Green,
    ]);

    $this->actingAs($user)
        ->from(route('categories.index', ['category' => $category->id]))
        ->put(route('categories.update', $category), [
            'name' => 'Food',
            'color' => 'pink',
        ])
        ->assertRedirect(route('categories.index', ['category' => $category->id]))
        ->assertInvalid(['color' => 'The selected color is invalid.'], 'updateCategory-'.$category->id);

    expect($category->fresh()->color)->toBe(CategoryColor::Green);
});

test('guests cannot update a category', function () {
    $category = Category::factory()->create(['name' => 'Food']);

    $this->put(route('categories.update', $category), [
        'name' => 'Groceries',
        'color' => 'red',
    ])->assertRedirect(route('login'));

    expect($category->fresh()->name)->toBe('Food');
});

test('a user cannot update someone else\'s category', function () {
    $owner = User::factory()->create();
    $someoneElse = User::factory()->create();
    $category = Category::factory()->for($owner)->create([
        'name' => 'Food',
        'color' => CategoryColor::Green,
    ]);

    $this->actingAs($someoneElse)
        ->put(route('categories.update', $category), [
            'name' => 'Groceries',
            'color' => 'red',
        ])
        ->assertNotFound();

    $category->refresh();

    expect($category->name)->toBe('Food')
        ->and($category->color)->toBe(CategoryColor::Green);
});

test('only the owner may delete a category', function () {
    $owner = User::factory()->create();
    $someoneElse = User::factory()->create();
    $category = Category::factory()->for($owner)->create();

    expect($owner->can('delete', $category))->toBeTrue()
        ->and($someoneElse->can('delete', $category))->toBeFalse();
});

test('a user can delete a category and its subcategories', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['name' => 'Food']);
    $subcategory = Subcategory::factory()->for($category)->create();

    $this->actingAs($user)
        ->delete(route('categories.destroy', $category))
        ->assertRedirect(route('categories.index'));

    $this->assertModelMissing($category);
    $this->assertModelMissing($subcategory);
});

test('a category that has expenses cannot be deleted', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $subcategory = Subcategory::factory()->for($category)->create();
    $expenseType = ExpenseType::factory()->for($user)->create();

    Expense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
    ]);

    $this->actingAs($user)
        ->delete(route('categories.destroy', $category))
        ->assertRedirect(route('categories.index', ['category' => $category->id]));

    $this->assertModelExists($category);
    $this->assertModelExists($subcategory);
});

test('a category that has a fixed expense cannot be deleted', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $subcategory = Subcategory::factory()->for($category)->create();
    $expenseType = ExpenseType::factory()->for($user)->create();

    FixedExpense::factory()->create([
        'user_id' => $user->id,
        'expense_type_id' => $expenseType->id,
        'subcategory_id' => $subcategory->id,
    ]);

    $this->actingAs($user)
        ->delete(route('categories.destroy', $category))
        ->assertRedirect(route('categories.index', ['category' => $category->id]));

    $this->assertModelExists($category);
});

test('guests cannot delete a category', function () {
    $category = Category::factory()->create();

    $this->delete(route('categories.destroy', $category))
        ->assertRedirect(route('login'));

    $this->assertModelExists($category);
});

test('a user cannot delete someone else\'s category', function () {
    $owner = User::factory()->create();
    $someoneElse = User::factory()->create();
    $category = Category::factory()->for($owner)->create();

    $this->actingAs($someoneElse)
        ->delete(route('categories.destroy', $category))
        ->assertNotFound();

    $this->assertModelExists($category);
});
