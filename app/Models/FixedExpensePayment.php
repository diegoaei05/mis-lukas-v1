<?php

namespace App\Models;

use App\Enums\Month;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fixed_expense_id
 * @property int $year
 * @property Month $month
 * @property string $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['year', 'month', 'amount'])]
class FixedExpensePayment extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => Month::class,
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Get the bill this payment belongs to.
     */
    public function fixedExpense(): BelongsTo
    {
        return $this->belongsTo(FixedExpense::class);
    }
}
