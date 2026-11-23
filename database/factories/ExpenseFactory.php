<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
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
            'month' => fake()->numberBetween(1, 12),
            'year' => (int) now()->year,
            'concept' => fake()->sentence(3),
            'currency' => Currency::Pen,
            'amount' => fake()->randomFloat(2, 1, 500),
        ];
    }
}
