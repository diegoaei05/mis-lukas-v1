<?php

use App\Enums\CategoryColor;
use App\Models\Subcategory;
use App\Models\User;
use Database\Seeders\CategorySeeder;

test('each user receives the spending categories and their subcategories', function () {
    $user = User::factory()->create();
    $someoneElse = User::factory()->create();

    $this->seed(CategorySeeder::class);

    $names = fn (User $owner, ?string $category = null): array => $category === null
        ? $owner->categories()->pluck('name')->all()
        : $owner->categories()->where('name', $category)->first()->subcategories()->pluck('name')->all();

    expect($names($user))->toEqualCanonicalizing([
        'PKYTP',
        'Vivienda',
        'Financieros',
        'Comida',
        'Otros',
    ])->and($names($someoneElse))->toHaveCount(5)
        ->and($user->categories()->where('name', 'Comida')->first()->color)->toBe(CategoryColor::Green)
        ->and($user->categories()->where('name', 'Otros')->first()->color)->toBe(CategoryColor::Purple)
        ->and($user->categories()->where('name', 'Vivienda')->first()->color)->toBe(CategoryColor::Blue)
        ->and($user->categories()->where('name', 'Financieros')->first()->color)->toBe(CategoryColor::Gold)
        ->and($user->categories()->where('name', 'PKYTP')->first()->color)->toBe(CategoryColor::Red);

    expect($names($user, 'PKYTP'))->toEqualCanonicalizing([
        'PUNKUYTP',
        'IGV/Renta',
        'AWS',
        'Godaddy/Name.com',
        'Asesoría/Terceros',
        'Otros',
    ])->and($names($user, 'Vivienda'))->toEqualCanonicalizing([
        'Luz',
        'Agua',
        'Mantenimiento',
        'Gas',
        'Limpieza',
        'Cosas para el hogar',
        'Netflix',
        'HBO',
    ])->and($names($user, 'Financieros'))->toEqualCanonicalizing([
        'SUNAT (GF)',
        'Banco transferencia',
        'Wise',
        'Seguro Tarjeta (Desg y otros)',
    ])->and($names($user, 'Comida'))->toEqualCanonicalizing([
        'Super/mercado',
        'Restaurantes/bares',
        'Rappi',
        'Menus',
    ])->and($names($user, 'Otros'))->toEqualCanonicalizing([
        'Gasolina/Parking/Carro',
        'Entretenimiento',
        'Ropa/Look',
        'Taxis',
        'Hair treatment',
        'Consultas/Examenes/Medicina',
        'Viajes',
        'Cursos/Talleres',
        'Regalos',
        'Otros',
        'Deportes',
    ]);
});

test('running the category seeder again does not duplicate rows', function () {
    $user = User::factory()->create();

    $this->seed(CategorySeeder::class);
    $this->seed(CategorySeeder::class);

    expect($user->categories()->count())->toBe(5)
        ->and(Subcategory::query()->count())->toBe(33);
});
