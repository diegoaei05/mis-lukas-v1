<?php

namespace Database\Seeders;

use App\Enums\Currency;
use App\Models\User;
use Illuminate\Database\Seeder;

class FixedExpenseSeeder extends Seeder
{
    /**
     * Fixed bills from the yearly sheet. The usual amount is 100.
     * Currency follows the column that held the amount.
     *
     * @var list<array{category: string, name: string, currency: Currency}>
     */
    private const BILLS = [
        ['category' => 'Vivienda', 'name' => 'Renta', 'currency' => Currency::Pen],
        ['category' => 'Vivienda', 'name' => 'Celular', 'currency' => Currency::Pen],
        ['category' => 'Vivienda', 'name' => 'Internet', 'currency' => Currency::Pen],
        ['category' => 'Salud', 'name' => 'San Pablo', 'currency' => Currency::Pen],
        ['category' => 'Salud', 'name' => 'RIMAC', 'currency' => Currency::Pen],
        ['category' => 'Financieros', 'name' => 'Transferencia (GF)', 'currency' => Currency::Usd],
        ['category' => 'Financieros', 'name' => 'Transferencia (LD)', 'currency' => Currency::Usd],
        ['category' => 'Financieros', 'name' => 'Seguro Tarjeta Interbank', 'currency' => Currency::Pen],
        ['category' => 'Otros', 'name' => 'Primevideo', 'currency' => Currency::Pen],
        ['category' => 'Otros', 'name' => 'Rappi Pro', 'currency' => Currency::Pen],
        ['category' => 'Otros', 'name' => 'Cursor', 'currency' => Currency::Usd],
        ['category' => 'Otros', 'name' => 'Trello', 'currency' => Currency::Usd],
        ['category' => 'Otros', 'name' => 'Smartfit', 'currency' => Currency::Pen],
        ['category' => 'Otros', 'name' => 'Pacifico/Carro', 'currency' => Currency::Usd],
        ['category' => 'Otros', 'name' => 'Jujitsu', 'currency' => Currency::Pen],
    ];

    /**
     * Give every user who already has an expense type the fixed bills.
     */
    public function run(): void
    {
        User::query()->each(function (User $user): void {
            $expenseType = $user->expenseTypes()->orderBy('id')->first();

            if ($expenseType === null) {
                return;
            }

            foreach (self::BILLS as $bill) {
                $category = $user->categories()->firstOrCreate([
                    'name' => $bill['category'],
                ]);

                $subcategory = $category->subcategories()->firstOrCreate([
                    'name' => $bill['name'],
                ]);

                $user->fixedExpenses()->firstOrCreate(
                    ['name' => $bill['name']],
                    [
                        'expense_type_id' => $expenseType->id,
                        'subcategory_id' => $subcategory->id,
                        'currency' => $bill['currency'],
                        'amount' => '100.00',
                    ],
                );
            }
        });
    }
}
