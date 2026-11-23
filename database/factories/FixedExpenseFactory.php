<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\ExpenseType;
use App\Models\FixedExpense;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixedExpense>
 */
class FixedExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'expense_type_id' => ExpenseType::factory(),
            'subcategory_id' => Subcategory::factory(),
            'name' => fake()->unique()->words(2, true),
            'currency' => Currency::Pen,
            'amount' => fake()->randomFloat(2, 1, 500),
        ];
    }
}
