<?php

namespace Database\Seeders;

use App\Enums\CategoryColor;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Categories and the subcategories that belong to each one.
     *
     * @var array<string, list<string>>
     */
    private const CATALOG = [
        'PKYTP' => [
            'PUNKUYTP',
            'IGV/Renta',
            'AWS',
            'Godaddy/Name.com',
            'Asesoría/Terceros',
            'Otros',
        ],
        'Vivienda' => [
            'Luz',
            'Agua',
            'Mantenimiento',
            'Gas',
            'Limpieza',
            'Cosas para el hogar',
            'Netflix',
            'HBO',
        ],
        'Financieros' => [
            'SUNAT (GF)',
            'Banco transferencia',
            'Wise',
            'Seguro Tarjeta (Desg y otros)',
        ],
        'Comida' => [
            'Super/mercado',
            'Restaurantes/bares',
            'Rappi',
            'Menus',
        ],
        'Otros' => [
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
        ],
    ];

    /**
     * Give every user the spending categories and their subcategories.
     */
    public function run(): void
    {
        User::query()->each(function (User $user): void {
            foreach (self::CATALOG as $categoryName => $subcategoryNames) {
                $category = $user->categories()->firstOrCreate(
                    ['name' => $categoryName],
                    ['color' => CategoryColor::forCatalogName($categoryName)],
                );

                foreach ($subcategoryNames as $subcategoryName) {
                    $category->subcategories()->firstOrCreate([
                        'name' => $subcategoryName,
                    ]);
                }
            }
        });
    }
}
